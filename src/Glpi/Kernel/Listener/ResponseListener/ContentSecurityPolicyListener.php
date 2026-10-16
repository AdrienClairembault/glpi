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

namespace Glpi\Kernel\Listener\ResponseListener;

use Glpi\Kernel\ListenersPriority;
use Glpi\Security\ContentSecurityPolicy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContentSecurityPolicyListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', ListenersPriority::RESPONSE_LISTENERS_PRIORITIES[self::class]],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (ContentSecurityPolicy::isUsingPageNonce()) {
            // The response content (HTML fragment, JSON containing HTML, ...) depends on the nonce provided by the request.
            $response->setVary(ContentSecurityPolicy::NONCE_HEADER, false);
        }

        $header_name = ContentSecurityPolicy::getHeaderName();
        if ($header_name === null) {
            return;
        }

        $headers = $response->headers;

        if (
            $headers->has('Content-Security-Policy')
            || $headers->has('Content-Security-Policy-Report-Only')
        ) {
            // Keep the policy defined by the controller.
            return;
        }

        $content_type = $headers->get('Content-Type');
        if ($content_type !== null && !\str_starts_with(\strtolower($content_type), 'text/html')) {
            // The policy is only relevant for HTML documents.
            // A missing content type will default to `text/html`.
            return;
        }

        $headers->set($header_name, ContentSecurityPolicy::getPolicy());
    }
}
