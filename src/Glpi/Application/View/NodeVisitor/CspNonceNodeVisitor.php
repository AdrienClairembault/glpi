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

namespace Glpi\Application\View\NodeVisitor;

use Glpi\Application\View\Node\CspNonceNode;
use Twig\Environment;
use Twig\Node\CaptureNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\SetNode;
use Twig\Node\TextNode;
use Twig\NodeVisitor\NodeVisitorInterface;

use function Safe\preg_match;
use function Safe\preg_split;

/**
 * Adds the Content-Security-Policy nonce to every `<script>` tag written in the source of a template.
 *
 * Only the static text of the templates is altered. The values printed by the templates (including the ones
 * printed with the `raw` filter) are never altered, so a `<script>` tag injected in a value will not get the nonce.
 *
 * Therefore, this visitor must NEVER be used in an environment that renders templates written by users
 * (see `\Glpi\ContentTemplates\TemplateManager`).
 */
final class CspNonceNodeVisitor implements NodeVisitorInterface
{
    /**
     * Matches the `<script` opening tag (case insensitive), and captures the text that follows it.
     */
    private const SCRIPT_TAG_PATTERN = '/(<script)(?=[\s>\/])/i';

    /**
     * Matches the remaining of a tag that already defines a `nonce` attribute.
     */
    private const NONCE_ATTRIBUTE_PATTERN = '/^[^>]*\snonce\s*=/i';

    public function enterNode(Node $node, Environment $env): Node
    {
        if (
            $node instanceof SetNode
            && $node->getAttribute('safe')
            && !$node->getAttribute('capture')
            && $node->getNode('values') instanceof ConstantExpression
            && \is_string($value = $node->getNode('values')->getAttribute('value'))
            && preg_match(self::SCRIPT_TAG_PATTERN, $value) === 1
        ) {
            // `{% set var %}<script>...</script>{% endset %}` blocks that only contain text are converted to a
            // constant value by the parser. Restore the capture to be able to add the nonce to their text.
            $line = $node->getNode('values')->getTemplateLine();
            $node->setNode('values', new CaptureNode(new TextNode($value, $line), $line));
            $node->setAttribute('capture', true);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof TextNode) {
            return $node;
        }

        $parts = preg_split(self::SCRIPT_TAG_PATTERN, $node->getAttribute('data'), -1, PREG_SPLIT_DELIM_CAPTURE);
        if (\count($parts) === 1) {
            return $node;
        }

        // `$parts` contains the text before the first tag, followed by each tag and the text that follows it.
        $line  = $node->getTemplateLine();
        $text  = \array_shift($parts);
        $nodes = [];
        foreach (\array_chunk($parts, 2) as [$tag, $following_text]) {
            if (preg_match(self::NONCE_ATTRIBUTE_PATTERN, $following_text) === 1) {
                // The nonce has been added manually.
                $text .= $tag . $following_text;
                continue;
            }
            $nodes[] = new TextNode($text . $tag . ' nonce="', $line);
            $nodes[] = new CspNonceNode($line);
            $text = '"' . $following_text;
        }
        $nodes[] = new TextNode($text, $line);

        return \count($nodes) === 1 ? $nodes[0] : new Nodes($nodes, $line);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
