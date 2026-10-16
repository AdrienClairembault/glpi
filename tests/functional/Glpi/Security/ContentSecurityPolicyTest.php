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

namespace tests\units\Glpi\Security;

use Glpi\Security\ContentSecurityPolicy;
use Glpi\Tests\GLPITestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

class ContentSecurityPolicyTest extends GLPITestCase
{
    public function testNonceWithoutPageNonce(): void
    {
        ContentSecurityPolicy::initFromRequest(new Request());

        $nonce = ContentSecurityPolicy::getNonce();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{24}$/', $nonce);
        $this->assertFalse(ContentSecurityPolicy::isUsingPageNonce());
        $this->assertStringContainsString("script-src 'nonce-{$nonce}'", ContentSecurityPolicy::getPolicy());

        // A new request must use a new nonce.
        ContentSecurityPolicy::initFromRequest(new Request());
        $this->assertNotEquals($nonce, ContentSecurityPolicy::getNonce());
    }

    public function testNonceWithValidPageNonce(): void
    {
        $page_nonce = 'AbCdEfGhIjKlMnOpQrSt+/09';

        $request = new Request();
        $request->headers->set('X-Glpi-Csp-Nonce', $page_nonce);
        ContentSecurityPolicy::initFromRequest($request);

        $this->assertTrue(ContentSecurityPolicy::isUsingPageNonce());
        $this->assertSame($page_nonce, ContentSecurityPolicy::getNonce());

        // The nonce provided by the client must never be used in the policy.
        $this->assertStringNotContainsString($page_nonce, ContentSecurityPolicy::getPolicy());
    }

    public static function invalidPageNonceProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['AbCdEfGhIjKlMnOpQrSt+/0'];
        yield 'too long' => ['AbCdEfGhIjKlMnOpQrSt+/090'];
        yield 'padding' => ['AbCdEfGhIjKlMnOpQrSt+/=='];
        yield 'attribute injection' => ['x" onload="alert(1)" a="'];
        yield 'trailing new line' => ["AbCdEfGhIjKlMnOpQrSt+/09\n"];
    }

    #[DataProvider('invalidPageNonceProvider')]
    public function testNonceWithInvalidPageNonce(string $page_nonce): void
    {
        $request = new Request();
        $request->headers->set('X-Glpi-Csp-Nonce', $page_nonce);
        ContentSecurityPolicy::initFromRequest($request);

        $this->assertFalse(ContentSecurityPolicy::isUsingPageNonce());
        $this->assertNotSame($page_nonce, ContentSecurityPolicy::getNonce());
        $this->assertStringContainsString(
            \sprintf("script-src 'nonce-%s'", ContentSecurityPolicy::getNonce()),
            ContentSecurityPolicy::getPolicy()
        );
    }

    public function testPageNonceIsNotReadFromParameters(): void
    {
        $page_nonce = 'AbCdEfGhIjKlMnOpQrSt+/09';

        ContentSecurityPolicy::initFromRequest(new Request(
            query: ['X-Glpi-Csp-Nonce' => $page_nonce, 'csp_nonce' => $page_nonce],
            request: ['X-Glpi-Csp-Nonce' => $page_nonce, 'csp_nonce' => $page_nonce],
            cookies: ['X-Glpi-Csp-Nonce' => $page_nonce, 'csp_nonce' => $page_nonce],
        ));

        $this->assertFalse(ContentSecurityPolicy::isUsingPageNonce());
        $this->assertNotSame($page_nonce, ContentSecurityPolicy::getNonce());
    }
}
