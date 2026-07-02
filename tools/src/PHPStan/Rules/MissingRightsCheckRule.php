<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Tools\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects CommonDBTM write operations (add/update/delete/restore) that are not
 * preceded, on the same execution path, by a rights check.
 *
 * A rights check is considered present when, earlier on the path (in the current block,
 * an enclosing block, or an `if`/`try` guard that precedes the mutation), one of the
 * following was seen:
 *  - an item-level check: `->check()`, `->checkGlobal()`, or any `can*` accessor
 *    (`->can()`, `->canEdit()`, `->canApprove()`, ...) on any object -- the target may
 *    differ from the mutated object, e.g. checking a parent item before adding a child;
 *  - a class-level right accessor used as a guard: `$item::canUpdate()`,
 *    `Ticket::canCreate()`, typically as `if (!$item::canUpdate()) { throw ...; }`;
 *  - a write-level session gate: `Session::checkRight()` / `checkRightsOr()` /
 *    `checkSeveralRightsOr()` whose arguments reference CREATE, UPDATE, DELETE or PURGE.
 *
 * A bare `Session::checkRight($rightname, READ)` (the module-access gate found at the
 * top of most form scripts) does NOT satisfy a mutation, which is precisely how the
 * "READ gate only, item check forgotten" bug class gets surfaced.
 *
 * Known trade-offs (biased towards low noise): presence of a check is verified, not its
 * correctness -- the rule does not confirm the checked right matches the operation, nor
 * that the check targets the mutated object. Legitimate self-service updates (a user
 * editing their own record via `Session::getLoginUserID()`) are reported and are best
 * handled through the PHPStan baseline.
 *
 * Scope: only files located under `front/` and `ajax/` are analysed. To avoid noise,
 * a mutation is only reported when its receiver can be positively resolved to a
 * CommonDBTM subclass (through a local `$var = new Foo()` assignment or a direct
 * `(new Foo())->update()`); anything the rule cannot resolve is left alone.
 *
 * @implements Rule<FileNode>
 */
final class MissingRightsCheckRule implements Rule
{
    /** Method names (lower-cased) that mutate a CommonDBTM row. */
    private const MUTATION_METHODS = ['add', 'update', 'delete', 'restore'];

    /** Session:: methods (lower-cased) that gate on a right passed as argument. */
    private const SESSION_CHECK_METHODS = ['checkright', 'checkrightsor', 'checkseveralrightsor'];

    /** Right constants that denote a write operation. */
    private const WRITE_RIGHTS = ['CREATE', 'UPDATE', 'DELETE', 'PURGE'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInScope($scope->getFile())) {
            return [];
        }

        $errors = [];
        $this->walk($node->getNodes(), false, [], $errors);

        return $errors;
    }

    private function isInScope(string $file): bool
    {
        return (bool) \preg_match('#[/\\\\](front|ajax)[/\\\\]#', $file);
    }

    /**
     * Walk a list of nodes in source order.
     *
     * @param Node[]                                       $nodes
     * @param bool                                         $checkSeen  a rights check was seen earlier on this path
     * @param array<string, string>                        $varClass   map of variable name => resolved class name
     * @param list<\PHPStan\Rules\RuleError>               $errors     collected errors (by reference)
     */
    private function walk(array $nodes, bool $checkSeen, array $varClass, array &$errors): void
    {
        foreach ($nodes as $child) {
            if (!$child instanceof Node) {
                continue;
            }

            // Statements are wrapped in an Expression node; unwrap so the checks below
            // (assignment tracking, check/mutation detection) apply at this list level and
            // their state updates persist across sibling statements.
            if ($child instanceof Node\Stmt\Expression) {
                $child = $child->expr;
            }

            // Nested scopes have their own rights-check context: do not descend.
            if (
                $child instanceof Node\Expr\Closure
                || $child instanceof Node\Expr\ArrowFunction
                || $child instanceof Node\FunctionLike
            ) {
                continue;
            }

            // Track `$var = new Foo()` so we can later resolve `$var->update()`.
            if (
                $child instanceof Assign
                && $child->var instanceof Variable
                && \is_string($child->var->name)
                && $child->expr instanceof New_
            ) {
                $class = $this->resolveNewClass($child->expr);
                if ($class !== null) {
                    $varClass[$child->var->name] = $class;
                }
                continue;
            }

            // Branching constructs: headers run unconditionally, bodies are isolated paths.
            if ($child instanceof If_) {
                $this->walk([$child->cond], $checkSeen, $varClass, $errors);
                // A check in the condition guards the branch body, and — since the guard
                // idiom `if (!$x->canUpdate()) { throw; }` aborts otherwise — also the code
                // that follows the whole `if`.
                $condCheck = $this->containsCheck($child->cond);
                $this->walk($child->stmts, $checkSeen || $condCheck, $varClass, $errors);
                foreach ($child->elseifs as $elseif) {
                    $this->walk([$elseif->cond], $checkSeen, $varClass, $errors);
                    $elseifCheck = $this->containsCheck($elseif->cond);
                    $condCheck = $condCheck || $elseifCheck;
                    $this->walk($elseif->stmts, $checkSeen || $elseifCheck, $varClass, $errors);
                }
                if ($child->else !== null) {
                    $this->walk($child->else->stmts, $checkSeen, $varClass, $errors);
                }
                if ($condCheck) {
                    $checkSeen = true;
                }
                continue;
            }

            if ($child instanceof Switch_) {
                $this->walk([$child->cond], $checkSeen, $varClass, $errors);
                foreach ($child->cases as $case) {
                    $this->walk($case->stmts, $checkSeen, $varClass, $errors);
                }
                continue;
            }

            if ($child instanceof Foreach_) {
                $this->walk([$child->expr], $checkSeen, $varClass, $errors);
                $this->walk($child->stmts, $checkSeen, $varClass, $errors);
                continue;
            }

            if ($child instanceof For_ || $child instanceof While_ || $child instanceof Do_) {
                $this->walk($child->stmts, $checkSeen, $varClass, $errors);
                continue;
            }

            if ($child instanceof TryCatch) {
                $this->walk($child->stmts, $checkSeen, $varClass, $errors);
                foreach ($child->catches as $catch) {
                    $this->walk($catch->stmts, $checkSeen, $varClass, $errors);
                }
                if ($child->finally !== null) {
                    $this->walk($child->finally->stmts, $checkSeen, $varClass, $errors);
                }
                // A check inside the `try` body still guards the code that follows: an access
                // denial throws AccessDeniedHttpException, which is not caught by the typical
                // `catch (ItemLinkException ...)` and therefore aborts before any mutation.
                foreach ($child->stmts as $tryStmt) {
                    if ($this->containsCheck($tryStmt)) {
                        $checkSeen = true;
                        break;
                    }
                }
                continue;
            }

            // A rights-check call flips the flag for subsequent siblings.
            if ($this->isRightsCheck($child)) {
                $checkSeen = true;
                continue;
            }

            // A mutation call on a resolved CommonDBTM instance must be preceded by a check.
            if (
                $child instanceof MethodCall
                && $this->methodNameIn($child, self::MUTATION_METHODS)
                && $this->receiverIsCommonDBTM($child, $varClass)
            ) {
                if (!$checkSeen) {
                    $method = $this->methodName($child) ?? 'update';
                    $errors[] = RuleErrorBuilder::message(\sprintf(
                        'Possible missing rights check: `->%s()` on a CommonDBTM instance is not '
                        . 'preceded by a rights check (`->check()`, `->can()`, or a write-level '
                        . '`Session::checkRight()`) on this code path.',
                        $method
                    ))
                        ->identifier('glpi.missingRightsCheck')
                        ->line($child->getStartLine())
                        ->build();
                }
                continue;
            }

            // Straight-line node: descend into its children in source order (nested
            // mutations are reported against the current $checkSeen state).
            foreach ($child->getSubNodeNames() as $name) {
                $sub = $child->$name;
                if (\is_array($sub)) {
                    $this->walk($sub, $checkSeen, $varClass, $errors);
                } elseif ($sub instanceof Node) {
                    $this->walk([$sub], $checkSeen, $varClass, $errors);
                }
            }

            // A rights check embedded in a larger expression (e.g. `$ro = !$item->can(...)`)
            // still guards the statements that follow it in this block.
            if ($this->containsCheck($child)) {
                $checkSeen = true;
            }
        }
    }

    private function isRightsCheck(Node $node): bool
    {
        // Item-level check on an instance: `->check()`, `->checkGlobal()`, `->can()`,
        // `->canEdit()`, `->canApprove()`, ... (any `can*` accessor).
        if (
            $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $this->isCheckMethodName($node->name->toString())
        ) {
            return true;
        }

        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $method = $node->name->toString();

            // Class-level right accessor: `$item::canUpdate()`, `Ticket::canCreate()`, ...
            if (\preg_match('/^can([A-Z]|$)/', $method) === 1) {
                return true;
            }

            // Session gate that references a write-level right.
            if (
                $node->class instanceof Name
                && \strtolower($node->class->getLast()) === 'session'
                && \in_array(\strtolower($method), self::SESSION_CHECK_METHODS, true)
            ) {
                return $this->argsReferenceWriteRight($node);
            }
        }

        return false;
    }

    /**
     * A method name that denotes an authorization check: `check`, `checkGlobal`, or any
     * `can` accessor (`can`, `canEdit`, `canApprove`, `canUpdateItem`, ...). The `can[A-Z]`
     * guard avoids matching unrelated names such as `cancel()`.
     */
    private function isCheckMethodName(string $name): bool
    {
        $lower = \strtolower($name);
        if ($lower === 'check' || $lower === 'checkglobal') {
            return true;
        }

        return \preg_match('/^can([A-Z]|$)/', $name) === 1;
    }

    /**
     * Does the node contain a rights check anywhere in its expression/statement tree?
     */
    private function containsCheck(Node $node): bool
    {
        if ($this->isRightsCheck($node)) {
            return true;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            $children = \is_array($sub) ? $sub : [$sub];
            foreach ($children as $c) {
                if ($c instanceof Node && $this->containsCheck($c)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function argsReferenceWriteRight(StaticCall $call): bool
    {
        foreach ($call->getArgs() as $arg) {
            if ($this->exprReferencesWriteRight($arg->value)) {
                return true;
            }
        }
        return false;
    }

    private function exprReferencesWriteRight(Node $node): bool
    {
        if (
            $node instanceof Node\Expr\ConstFetch
            && \in_array($node->name->getLast(), self::WRITE_RIGHTS, true)
        ) {
            return true;
        }
        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            $children = \is_array($sub) ? $sub : [$sub];
            foreach ($children as $c) {
                if ($c instanceof Node && $this->exprReferencesWriteRight($c)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<string, string> $varClass
     */
    private function receiverIsCommonDBTM(MethodCall $call, array $varClass): bool
    {
        $receiver = $call->var;

        $class = null;
        if ($receiver instanceof Variable && \is_string($receiver->name)) {
            $class = $varClass[$receiver->name] ?? null;
        } elseif ($receiver instanceof New_) {
            $class = $this->resolveNewClass($receiver);
        }

        if ($class === null) {
            return false;
        }

        return $this->isCommonDBTM($class);
    }

    private function isCommonDBTM(string $class): bool
    {
        if (!$this->reflectionProvider->hasClass($class)) {
            return false;
        }
        if ($class === 'CommonDBTM') {
            return true;
        }
        return $this->reflectionProvider->getClass($class)->isSubclassOf('CommonDBTM');
    }

    private function resolveNewClass(New_ $new): ?string
    {
        if (!$new->class instanceof Name) {
            return null;
        }
        $resolved = $new->class->getAttribute('resolvedName');
        $name = $resolved instanceof Name ? $resolved->toString() : $new->class->toString();

        return \ltrim($name, '\\');
    }

    /**
     * @param string[] $names lower-cased method names
     */
    private function methodNameIn(MethodCall $call, array $names): bool
    {
        $name = $this->methodName($call);

        return $name !== null && \in_array(\strtolower($name), $names, true);
    }

    private function methodName(MethodCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }
}
