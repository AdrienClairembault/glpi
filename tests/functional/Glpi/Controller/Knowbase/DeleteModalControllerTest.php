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

use Glpi\Controller\Knowbase\DeleteModalController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Tests\DbTestCase;
use KnowbaseItem;
use Symfony\Component\HttpFoundation\Response;

class DeleteModalControllerTest extends DbTestCase
{
    public function testUnknownArticleIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        $this->callController(999999999);
    }

    public function testModalIsDeniedWithoutThePurgeRight(): void
    {
        $this->login();
        $article = $this->makeArticle('Denied');

        // Deleting a knowledge base article is a purge; READ and UPDATE are not
        // enough to open the modal.
        $_SESSION['glpiactiveprofile']['knowbase'] = READ | UPDATE;

        $this->expectException(AccessDeniedHttpException::class);
        $this->callController($article);
    }

    public function testRootArticleIsDenied(): void
    {
        $this->login();

        $this->expectException(AccessDeniedHttpException::class);
        $this->callController(KnowbaseItem::getRootId());
    }

    public function testModalStatesTheImpactAndNamesTheDeletedSubArticles(): void
    {
        $this->login();
        $parent      = $this->makeArticle('Parent');
        $child       = $this->makeArticle('Child', [$parent]);
        $this->makeArticle('Grand child', [$child]);

        $content = $this->callController($parent)->getContent();

        $this->assertStringContainsString('This action also deletes 2 sub-articles.', $content);
        $this->assertStringContainsString('You cannot undo this action.', $content);
        $this->assertStringContainsString('Child', $content);
        $this->assertStringContainsString('Grand child', $content);

        // The user has to type the article name back before the deletion is
        // offered, see DeleteModalController.js.
        $this->assertStringContainsString(
            'data-glpi-kb-delete-confirm-phrase="Parent"',
            $content,
        );
        $this->assertStringContainsString('data-glpi-kb-delete-submit', $content);
    }

    public function testSubArticlesThatHaveAnotherParentAreAnnouncedAsKept(): void
    {
        $this->login();
        $parent       = $this->makeArticle('Parent');
        $other_parent = $this->makeArticle('Other parent');
        $this->makeArticle('Shared child', [$parent, $other_parent]);

        $content = $this->callController($parent)->getContent();

        // Nothing else goes away, so the impact is stated without a count.
        $this->assertStringContainsString('This action deletes this article.', $content);
        $this->assertStringContainsString(
            'sub-article has another parent. It stays in the knowledge base.',
            $content,
        );
        $this->assertStringNotContainsString('Shared child', $content);
    }

    public function testBranchHoldingAnUndeletableSubArticleOffersNoDeletion(): void
    {
        $this->login();

        // The parent is recursive over the whole test tree, the child lives in
        // one branch of it only.
        $parent = $this->createItem(KnowbaseItem::class, [
            'name'         => 'Parent ' . $this->getUniqueString(),
            'answer'       => '<p>x</p>',
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ])->getID();
        $child = $this->createItem(KnowbaseItem::class, [
            'name'        => 'Out of reach ' . $this->getUniqueString(),
            'answer'      => '<p>x</p>',
            'entities_id' => getItemByTypeName('Entity', '_test_child_1', true),
            '_parents'    => [$parent],
        ])->getID();

        // Working in the parent entity only: a purge needs the very entity of
        // the article, so the child is out of reach and the whole cascade is
        // refused instead of leaving the branch half deleted.
        $this->setEntity('_test_root_entity', false);
        $article = new KnowbaseItem();
        $this->assertTrue($article->getFromDB($parent));
        $this->assertTrue($article->can($parent, PURGE));
        $out_of_reach = new KnowbaseItem();
        $this->assertTrue($out_of_reach->getFromDB($child));
        $this->assertFalse($out_of_reach->can($child, PURGE));

        $content = $this->callController($parent)->getContent();

        $this->assertStringContainsString('This article cannot be deleted', $content);
        $this->assertStringContainsString(
            'It has a sub-article you are not allowed to delete.',
            $content,
        );
        // Nothing to confirm: the action itself is not offered.
        $this->assertStringNotContainsString('data-glpi-kb-delete-submit', $content);
        $this->assertStringNotContainsString('data-glpi-kb-delete-confirm-input', $content);
    }

    private function callController(int $id): Response
    {
        return (new DeleteModalController())->__invoke($id);
    }

    /**
     * Deleting an article is a purge, and `canPurgeItem()` checks the entity:
     * the article has to live in an entity the test session works in.
     *
     * @param int[] $parents
     */
    private function makeArticle(string $name, array $parents = []): int
    {
        return $this->createItem(KnowbaseItem::class, [
            'name'        => $name,
            'answer'      => '<p>x</p>',
            'entities_id' => $this->getTestRootEntity(true),
            '_parents'    => $parents,
        ])->getID();
    }
}
