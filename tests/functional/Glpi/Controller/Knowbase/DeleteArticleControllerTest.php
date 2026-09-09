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

namespace tests\units\Glpi\Controller\Knowbase;

use Glpi\Controller\Knowbase\DeleteArticleController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Tests\DbTestCase;
use KnowbaseItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Safe\json_decode;
use function Safe\json_encode;

class DeleteArticleControllerTest extends DbTestCase
{
    public function testUnknownArticleIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        $this->callController(999999999);
    }

    public function testRootArticleIsDenied(): void
    {
        $this->login();

        $this->expectException(AccessDeniedHttpException::class);
        $this->callController(KnowbaseItem::getRootId());
    }

    public function testDeletionIsDeniedWithoutTheDeleteRight(): void
    {
        $this->login();
        $article = $this->makeArticle();

        $_SESSION['glpiactiveprofile']['knowbase'] = READ | UPDATE;

        $this->expectException(AccessDeniedHttpException::class);
        $this->callController($article);
    }

    public function testLeafArticleIsDeleted(): void
    {
        $this->login();
        $article = $this->makeArticle();

        $response = $this->callController($article);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([$article], $this->getDeletedIds($response));
        $this->assertFalse((new KnowbaseItem())->getFromDB($article));
    }

    public function testCascadeIsRefusedUnlessTheCallerAsksForIt(): void
    {
        $this->login();
        $parent = $this->makeArticle();
        $child  = $this->makeArticle([$parent]);

        // A page rendered before the child was created would otherwise delete a
        // branch the user never saw.
        try {
            $this->callController($parent);
            $this->fail('The cascade was not refused.');
        } catch (BadRequestHttpException) {
            // Expected
        }

        $this->assertTrue((new KnowbaseItem())->getFromDB($parent));
        $this->assertTrue((new KnowbaseItem())->getFromDB($child));
    }

    public function testCascadeDeletesTheBranchWhenAsked(): void
    {
        $this->login();
        $parent      = $this->makeArticle();
        $child       = $this->makeArticle([$parent]);
        $grand_child = $this->makeArticle([$child]);

        $response = $this->callController($parent, delete_descendants: true);

        $this->assertSame(200, $response->getStatusCode());
        // Children before their parents, and the article itself last: the
        // caller needs the whole list to know the page it stands on is gone.
        $this->assertSame([$grand_child, $child, $parent], $this->getDeletedIds($response));
        foreach ([$parent, $child, $grand_child] as $deleted_id) {
            $this->assertFalse((new KnowbaseItem())->getFromDB($deleted_id));
        }
    }

    public function testCascadeLeavesTheSubArticlesThatHaveAnotherParent(): void
    {
        $this->login();
        $parent       = $this->makeArticle();
        $other_parent = $this->makeArticle();
        $shared_child = $this->makeArticle([$parent, $other_parent]);

        $response = $this->callController($parent, delete_descendants: true);

        $this->assertSame([$parent], $this->getDeletedIds($response));
        $this->assertTrue((new KnowbaseItem())->getFromDB($shared_child));
    }

    /**
     * @return int[]
     */
    private function getDeletedIds(Response $response): array
    {
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('deleted_ids', $body);

        return array_map('intval', $body['deleted_ids']);
    }

    private function callController(int $id, ?bool $delete_descendants = null): Response
    {
        $request = Request::create(
            '/Knowbase/KnowbaseItem/' . $id . '/Delete',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(
                $delete_descendants === null ? [] : ['delete_descendants' => $delete_descendants],
            ),
        );
        return (new DeleteArticleController())->__invoke($id, $request);
    }

    /**
     * Deleting an article is a purge, and `canPurgeItem()` checks the entity:
     * the article has to live in an entity the test session works in.
     *
     * @param int[] $parents
     */
    private function makeArticle(array $parents = []): int
    {
        return $this->createItem(KnowbaseItem::class, [
            'name'        => 'Delete ' . $this->getUniqueString(),
            'answer'      => '<p>x</p>',
            'entities_id' => $this->getTestRootEntity(true),
            '_parents'    => $parents,
        ])->getID();
    }
}
