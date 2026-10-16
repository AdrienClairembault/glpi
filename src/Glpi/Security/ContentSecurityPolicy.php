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

namespace Glpi\Security;

use Symfony\Component\HttpFoundation\Request;

use function Safe\preg_match;

final class ContentSecurityPolicy
{
    public const MODE_DISABLED    = 'disabled';
    public const MODE_REPORT_ONLY = 'report-only';
    public const MODE_ENFORCE     = 'enforce';

    /**
     * Header used by AJAX requests to provide the nonce of the page that will receive the response.
     */
    public const NONCE_HEADER = 'X-Glpi-Csp-Nonce';

    /**
     * Nonces are 18 random bytes, base64 encoded (no padding).
     */
    private const NONCE_PATTERN = '/^[A-Za-z0-9+\/]{24}$/D';

    /**
     * Nonce of the current response, used in its `Content-Security-Policy` header.
     */
    private static ?string $response_nonce = null;

    /**
     * Nonce of the page that requested the current response, provided by the `X-Glpi-Csp-Nonce` header.
     */
    private static ?string $page_nonce = null;

    /**
     * Initializes the nonces for the given request.
     */
    public static function initFromRequest(Request $request): void
    {
        self::$response_nonce = null;
        self::$page_nonce     = null;

        // The page nonce is only accepted from a request header, as it cannot be set by an attacker
        // without being able to execute JS code in the GLPI origin.
        // It must NEVER be accepted from a request parameter.
        $page_nonce = $request->headers->get(self::NONCE_HEADER);
        if (\is_string($page_nonce) && preg_match(self::NONCE_PATTERN, $page_nonce) === 1) {
            self::$page_nonce = $page_nonce;
        }
    }

    /**
     * Returns the nonce that must be added to every legitimate `<script>` tag.
     *
     * Many HTML fragments loaded by AJAX (tabs, modals, ...) contain `<script>` tags that are executed by jQuery
     * in the context of the page that loaded them, and so they must carry the nonce of this page.
     * In this case, the nonce of the page is provided by the request header. Otherwise, the response nonce is used.
     */
    public static function getNonce(): string
    {
        return self::$page_nonce ?? self::getResponseNonce();
    }

    /**
     * Indicates whether the nonce of the requesting page is used in the current response.
     */
    public static function isUsingPageNonce(): bool
    {
        return self::$page_nonce !== null;
    }

    /**
     * Returns the CSP mode, from the `GLPI_CSP_MODE` constant.
     */
    public static function getMode(): string
    {
        return \in_array(GLPI_CSP_MODE, [self::MODE_DISABLED, self::MODE_REPORT_ONLY, self::MODE_ENFORCE], true)
            ? GLPI_CSP_MODE
            : self::MODE_REPORT_ONLY;
    }

    /**
     * Returns the name of the header to send, or `null` if no policy should be sent.
     */
    public static function getHeaderName(): ?string
    {
        return match (self::getMode()) {
            self::MODE_ENFORCE     => 'Content-Security-Policy',
            self::MODE_REPORT_ONLY => 'Content-Security-Policy-Report-Only',
            default                => null,
        };
    }

    /**
     * Returns the policy to send.
     */
    public static function getPolicy(): string
    {
        return \implode('; ', [
            // The page nonce is never used here, as it is provided by the client.
            \sprintf("script-src 'nonce-%s'", self::getResponseNonce()),
            "object-src 'none'",
            "base-uri 'self'",
        ]);
    }

    private static function getResponseNonce(): string
    {
        return self::$response_nonce ??= \base64_encode(\random_bytes(18));
    }
}
