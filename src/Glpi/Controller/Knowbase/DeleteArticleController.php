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

namespace Glpi\Controller\Knowbase;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use KnowbaseItem;
use RuntimeException;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteArticleController extends AbstractController
{
    #[Route(
        "/Knowbase/KnowbaseItem/{id}/Delete",
        name: "knowbase_article_delete",
        methods: ["POST"],
        requirements: [
            'id' => '\d+',
        ]
    )]
    public function __invoke(int $id, Request $request): Response
    {
        $item = new KnowbaseItem();
        if (!$item->getFromDB($id)) {
            throw new NotFoundHttpException();
        }

        $input = ['id' => $id];
        if (!$item->can($id, DELETE, $input)) {
            throw new AccessDeniedHttpException();
        }

        $impact = $item->getDeletionImpact();

        // The cascade is never implicit. A page rendered before someone else
        // added children under the article would otherwise destroy a branch the
        // user never saw, see `DeleteModalController` for the confirmation.
        if ($impact->isCascade() && !$request->getPayload()->getBoolean('delete_descendants')) {
            throw new BadRequestHttpException();
        }
        // All or nothing: the model refuses a partial cascade too, this only
        // turns it into an answer instead of a flash message.
        if ($impact->isBlocked()) {
            throw new AccessDeniedHttpException();
        }

        if ($impact->isCascade()) {
            $input[KnowbaseItem::DELETE_DESCENDANTS] = true;
        }
        if (!$item->delete($input)) {
            throw new RuntimeException("Failed to delete item");
        }

        Session::addMessageAfterRedirect(__s('Item successfully deleted.'));

        return new JsonResponse([
            'redirect' => KnowbaseItem::getSearchURL(),
            // The article itself included: the caller uses this to know whether
            // the page it stands on still exists.
            'deleted_ids' => $impact->deletable_ids,
        ]);
    }
}
