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
use Glpi\Exception\Http\NotFoundHttpException;
use KnowbaseItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The "Delete article" modal, shown for an article that hosts children: it
 * states what the cascade deletion really deletes, and asks the user to type
 * the article name back before the deletion is offered.
 *
 * A leaf article never gets here, it keeps the plain confirmation dialog, see
 * `KnowbaseItem::getAsideActions()`.
 */
final class DeleteModalController extends AbstractController
{
    /**
     * How many sub-article names the modal lists. A branch may hold hundreds of
     * articles, and the count carries the impact on its own.
     */
    private const int MAX_LISTED_NAMES = 20;

    #[Route(
        "/Knowbase/{id}/DeleteModal",
        name: "knowbase_delete_modal",
        requirements: [
            'id' => '\d+',
        ],
        methods: 'GET',
    )]
    public function __invoke(int $id): Response
    {
        $item = new KnowbaseItem();
        if (!$item->getFromDB($id)) {
            throw new NotFoundHttpException();
        }
        // READ is not enough: this modal exists only to delete. PURGE is the
        // right the action itself is gated on, and a knowledge base article has
        // no soft deletion.
        if (!$item->can($id, PURGE)) {
            throw new AccessDeniedHttpException();
        }

        $impact           = $item->getDeletionImpact();
        $descendant_count = count($impact->getDeletableDescendantIds());
        $readable         = $this->getReadableDescendants($impact->getDeletableDescendantIds());
        $name             = trim($item->getName());

        return $this->render('pages/tools/kb/modal/delete.html.twig', [
            'id'               => $id,
            // What the user has to type to enable the deletion. An article may
            // have an empty name, and no phrase means no confirmation at all.
            'confirm_phrase'   => $name !== '' ? $name : (string) $id,
            'descendant_count' => $descendant_count,
            'listed_names'     => $readable['names'],
            'unlisted_count'   => max(0, $readable['count'] - count($readable['names'])),
            // Deleted, but never named: the user may not read them, and this
            // modal must not become a way to read titles that are hidden
            // everywhere else.
            'unreadable_count' => $descendant_count - $readable['count'],
            'kept_count'       => count($impact->kept_ids),
            'blocked_count'    => count($impact->blocked_ids),
        ]);
    }

    /**
     * How many of the deleted sub-articles the current user may read, and the
     * name of the first `MAX_LISTED_NAMES` of them.
     *
     * @param int[] $descendant_ids
     *
     * @return array{count: int, names: string[]}
     */
    private function getReadableDescendants(array $descendant_ids): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($descendant_ids === []) {
            return ['count' => 0, 'names' => []];
        }

        // One query for the whole branch: the same visibility rules the tree and
        // the search apply, so the modal never names an article the user cannot
        // open anywhere else.
        $criteria = KnowbaseItem::getListRequest([], 'browse');
        // `name` is selected as well as ordered on: the request groups by the
        // primary key, which the original `SELECT glpi_knowbaseitems.*` already
        // relies on.
        $criteria['SELECT']  = [
            KnowbaseItem::getTableField('id'),
            KnowbaseItem::getTableField('name'),
        ];
        $criteria['WHERE'][] = [KnowbaseItem::getTableField('id') => $descendant_ids];
        $criteria['ORDER']   = [KnowbaseItem::getTableField('name')];

        $readable_ids = [];
        foreach ($DB->request($criteria) as $row) {
            $readable_ids[] = (int) $row['id'];
        }

        $names = [];
        foreach (array_slice($readable_ids, 0, self::MAX_LISTED_NAMES) as $article_id) {
            // Loaded one by one on purpose: `getName()` gives the translated
            // name, which is the one the user reads in the tree.
            $article = new KnowbaseItem();
            if ($article->getFromDB($article_id)) {
                $names[] = $article->getName();
            }
        }
        usort($names, 'strnatcasecmp');

        return ['count' => count($readable_ids), 'names' => $names];
    }
}
