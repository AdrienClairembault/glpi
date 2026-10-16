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

namespace tests\units\Glpi\Application\View\NodeVisitor;

use Glpi\Application\View\Extension\SecurityExtension;
use Glpi\Application\View\TemplateRenderer;
use Glpi\ContentTemplates\TemplateManager;
use Glpi\Security\ContentSecurityPolicy;
use Glpi\Tests\GLPITestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\TextNode;

use function Safe\preg_match;

class CspNonceNodeVisitorTest extends GLPITestCase
{
    private const NONCE = 'AbCdEfGhIjKlMnOpQrSt+/09';

    public function setUp(): void
    {
        parent::setUp();

        // Use a known nonce.
        $request = new Request();
        $request->headers->set(ContentSecurityPolicy::NONCE_HEADER, self::NONCE);
        ContentSecurityPolicy::initFromRequest($request);
    }

    public function tearDown(): void
    {
        ContentSecurityPolicy::initFromRequest(new Request());

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{template: string, variables: array<string, mixed>, expected: string}>
     */
    public static function templateProvider(): iterable
    {
        $nonce = self::NONCE;

        yield 'no script' => [
            'template'  => '<div class="script"><noscript>JS is required</noscript></div><scripts></scripts>',
            'variables' => [],
            'expected'  => '<div class="script"><noscript>JS is required</noscript></div><scripts></scripts>',
        ];

        yield 'inline script' => [
            'template'  => '<script>alert(1);</script>',
            'variables' => [],
            'expected'  => '<script nonce="' . $nonce . '">alert(1);</script>',
        ];

        yield 'script with attributes' => [
            'template'  => "<script\n type=\"module\" defer>alert(1);</script><SCRIPT src=\"{{ src }}\"></SCRIPT>",
            'variables' => ['src' => '/js/test.js'],
            'expected'  => "<script nonce=\"{$nonce}\"\n type=\"module\" defer>alert(1);</script><SCRIPT nonce=\"{$nonce}\" src=\"/js/test.js\"></SCRIPT>",
        ];

        yield 'multiple scripts' => [
            'template'  => '{% if true %}<script>a();</script><p>{{ text }}</p><script>b();</script>{% endif %}<script>c();</script>',
            'variables' => ['text' => 'foo'],
            'expected'  => '<script nonce="' . $nonce . '">a();</script><p>foo</p><script nonce="' . $nonce . '">b();</script><script nonce="' . $nonce . '">c();</script>',
        ];

        yield 'script in a JS string' => [
            'template'  => '<script>$("<script>");</script>',
            'variables' => [],
            'expected'  => '<script nonce="' . $nonce . '">$("<script nonce="' . $nonce . '">");</script>',
        ];

        yield 'script in a verbatim block' => [
            'template'  => '{% verbatim %}<script>{{ foo }}</script>{% endverbatim %}',
            'variables' => [],
            'expected'  => '<script nonce="' . $nonce . '">{{ foo }}</script>',
        ];

        yield 'script in a text-only capture' => [
            'template'  => '{% set js %}<script>alert(1);</script>{% endset %}{{ js }}',
            'variables' => [],
            'expected'  => '<script nonce="' . $nonce . '">alert(1);</script>',
        ];

        yield 'script in a capture' => [
            'template'  => '{% set js %}<script>alert({{ value }});</script>{% endset %}{{ js }}',
            'variables' => ['value' => 1],
            'expected'  => '<script nonce="' . $nonce . '">alert(1);</script>',
        ];

        yield 'nonce already defined' => [
            'template'  => '<script type="module" nonce="{{ csp_nonce() }}">alert(1);</script>',
            'variables' => [],
            'expected'  => '<script type="module" nonce="' . $nonce . '">alert(1);</script>',
        ];

        // Scripts injected in values must never get the nonce.
        yield 'script in an escaped value' => [
            'template'  => '<div>{{ value }}</div>',
            'variables' => ['value' => '<script>alert(1);</script>'],
            'expected'  => '<div>&lt;script&gt;alert(1);&lt;/script&gt;</div>',
        ];

        yield 'script in a raw value' => [
            'template'  => '<div>{{ value|raw }}</div><script>var a = {{ value|json_encode|raw }};</script>',
            'variables' => ['value' => '<script>alert(1);</script>'],
            'expected'  => '<div><script>alert(1);</script></div><script nonce="' . $nonce . '">var a = "<script>alert(1);<\/script>";</script>',
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    #[DataProvider('templateProvider')]
    public function testRender(string $template, array $variables, string $expected): void
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new SecurityExtension());

        $this->assertSame($expected, $twig->render('template', $variables));
    }

    public function testRenderWithGlpiEnvironment(): void
    {
        $this->assertSame(
            '<script nonce="' . self::NONCE . '">alert(1);</script>',
            TemplateRenderer::getInstance()->renderFromStringTemplate('<script>alert(1);</script>')
        );
    }

    public function testRenderWithUserTemplatesEnvironment(): void
    {
        // Templates written by users must never get the nonce.
        $this->assertSame(
            '<script>alert(1);</script>',
            TemplateManager::render('<script>alert(1);</script>', [], expect_html: false)
        );
    }

    /**
     * Ensure that every `<script>` tag of the GLPI templates gets the nonce.
     */
    public function testAllTemplatesScriptsHaveNonce(): void
    {
        $twig = TemplateRenderer::getInstance()->getEnvironment();

        $templates_dir = GLPI_ROOT . '/templates';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templates_dir, RecursiveDirectoryIterator::SKIP_DOTS));

        $count  = 0;
        $errors = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || !\str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $name   = \substr($file->getPathname(), \strlen($templates_dir) + 1);
            $module = $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext($name)));

            $this->collectErrors($module, $name, $errors);
            $count++;
        }

        $this->assertGreaterThan(0, $count);
        $this->assertSame([], $errors);
    }

    /**
     * @param array<int, string> $errors
     */
    private function collectErrors(Node $node, string $template, array &$errors): void
    {
        if ($node instanceof TextNode) {
            if (preg_match('/<script(?![\w-])(?!\s+nonce\s*=)/i', $node->getAttribute('data')) === 1) {
                $errors[] = \sprintf('%s:%d: `<script>` tag without nonce.', $template, $node->getTemplateLine());
            }
        } elseif ($node instanceof ConstantExpression) {
            $value = $node->getAttribute('value');
            if (\is_string($value) && preg_match('/<script(?![\w-])/i', $value) === 1) {
                $errors[] = \sprintf('%s:%d: `<script>` tag in a string literal cannot get the nonce.', $template, $node->getTemplateLine());
            }
        }

        foreach ($node as $child) {
            $this->collectErrors($child, $template, $errors);
        }
    }
}
