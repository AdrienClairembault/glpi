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

import { expect, test } from "../../fixtures/glpi_fixture";
import { KnowbaseItemPage } from "../../pages/KnowbaseItemPage";
import { Profiles } from "../../utils/Profiles";
import { getUniqueName } from "../../utils/Random";
import { getWorkerEntityId } from "../../utils/WorkerEntities";

test('Can add and remove a favorite from the aside dots menu', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const target_name = getUniqueName(`Aside favorite`);
    const target_id = await api.knowbase.createArticle({
        name: target_name,
        answer: "My answer",
    });

    await kb.goto(viewed_id);
    await expect(kb.getFavoriteArticle(target_name)).toBeHidden();

    // Add to favorites from the tree row's dots menu.
    await kb.doOpenAsideArticleMenu(target_id);
    const favorite_checkbox = kb.getAsideArticleAction(target_id, 'Add to favorites').getByRole('checkbox');
    await expect(favorite_checkbox).not.toBeChecked();

    await kb.doToggleAsideFavorite(target_id);

    // It now appears in the favorites section and the toggle reflects the state.
    await expect(favorite_checkbox).toBeChecked();
    await expect(kb.getFavoriteArticle(target_name)).toBeVisible();

    // Only the tree row's menu stays open: the freshly cloned favorites row
    // must not render a second, orphaned open menu.
    await expect(kb.openAsideFavoriteToggles).toHaveCount(1);

    // Remove it again from the same menu.
    await kb.doToggleAsideFavorite(target_id);
    await expect(favorite_checkbox).not.toBeChecked();
    await expect(kb.getFavoriteArticle(target_name)).toBeHidden();
});

test('A nested article dots menu acts on that article, not on its parent', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside nested viewed`),
        answer: "My answer",
    });
    const parent_name = getUniqueName(`Aside nested parent`);
    const parent_id = await api.createItem('KnowbaseItem', {
        name: parent_name,
        answer: 'Parent content',
        entities_id: getWorkerEntityId(),
    });
    const child_name = getUniqueName(`Aside nested child`);
    const child_id = await api.createItem('KnowbaseItem', {
        name: child_name,
        answer: 'Child content',
        entities_id: getWorkerEntityId(),
        _parents: [parent_id],
    });

    await kb.goto(viewed_id);
    await kb.waitForAsideReady();

    // Load parent menu actions. Revealing the child under its parent (the tree
    // is folded by default) already interacts with the parent row, which
    // prefetches them, so the waiter has to be armed before that.
    const parent_actions = page.waitForResponse(
        (response) => response.url().includes(`/Knowbase/${parent_id}/AsideActions`),
    );
    await kb.doExpandAsideCategory(parent_name);
    await kb.getAsideArticleTitleLink(parent_name).hover();
    await parent_actions;

    // Favorite the child from its own menu.
    await kb.doOpenAsideArticleMenu(child_id);
    await kb.doToggleAsideFavorite(child_id);

    // The child is favorited, the parent is untouched.
    await expect(kb.getFavoriteArticle(child_name)).toBeVisible();
    await expect(kb.getFavoriteArticle(parent_name)).toBeHidden();
});

test('Can toggle FAQ status from the aside dots menu', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const target_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside FAQ`),
        answer: "My answer",
    });

    // Go to article and open aside menu for another article
    await kb.goto(viewed_id);
    await kb.doOpenAsideArticleMenu(target_id);

    // It should be unchecked by default
    const faq_checkbox = kb.getAsideArticleAction(target_id, 'Add to FAQ').getByRole('checkbox');
    await expect(faq_checkbox).not.toBeChecked();

    // We toggle the value
    await kb.doToggleAsideFaq(target_id);
    await expect(faq_checkbox).toBeChecked();

    // Make sure the change is persisted by reloading the page to refresh the data
    await page.reload();
    await kb.doOpenAsideArticleMenu(target_id);
    await expect(kb.getAsideArticleAction(target_id, 'Add to FAQ').getByRole('checkbox')).toBeChecked();
});

test('Aside dots menu is reachable and operable with the keyboard', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const target_name = getUniqueName(`Aside favorite`);
    const target_id = await api.knowbase.createArticle({
        name: target_name,
        answer: "My answer",
    });

    await kb.goto(viewed_id);

    // Tabbing off the article link walks the row's actions in visual order:
    // the "create child article" (+) affordance first...
    await kb.getAsideTreeArticleRow(target_id).getByRole('link', { name: target_name }).focus();
    await page.keyboard.press('Tab');
    await expect(kb.getAsideArticleAddChildTrigger(target_id)).toBeFocused();

    // ...then the dots trigger.
    await page.keyboard.press('Tab');
    await expect(kb.getAsideArticleMenuTrigger(target_id)).toBeFocused();

    // ...and pressing Enter opens its menu.
    await page.keyboard.press('Enter');
    await expect(kb.getAsideArticleAction(target_id, 'Add to favorites')).toBeVisible();
});

test('Can delete an article from the aside dots menu without leaving the page', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const target_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside delete`),
        answer: "My answer",
    });

    await kb.goto(viewed_id);
    await expect(kb.getAsideTreeArticleRow(target_id)).toBeVisible();

    await kb.doOpenAsideArticleMenu(target_id);
    await kb.doDeleteAsideArticle(target_id);

    // The row is removed from the tree in place, and we stay on the article we
    // were viewing (no redirect, since it is not the deleted one).
    await expect(kb.getAsideTreeArticleRow(target_id)).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`knowbaseitem\\.form\\.php\\?id=${viewed_id}(\\D|$)`));
});

test('Deleting a parent article from the aside dots menu takes its children with it', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const parent_name = getUniqueName(`Aside parent`);
    const parent_id = await api.createItem('KnowbaseItem', {
        name: parent_name,
        entities_id: getWorkerEntityId(),
        answer: '<p>My answer</p>',
    });
    const child_id = await api.createItem('KnowbaseItem', {
        name: getUniqueName(`Aside child`),
        entities_id: getWorkerEntityId(),
        answer: '<p>My answer</p>',
        _parents: [parent_id],
    });

    await kb.goto(viewed_id);
    await expect(kb.getAsideTreeArticleRow(parent_id)).toBeVisible();

    await kb.doOpenAsideArticleMenu(parent_id);
    await kb.getAsideArticleAction(parent_id, 'Delete article').click();

    // The plain confirmation is not offered for an article that hosts children.
    const modal = kb.getDialog('Delete article');
    await expect(modal.getByText('This action also deletes 1 sub-article.')).toBeVisible();
    await modal
        .getByRole('textbox', { name: 'Name of the article to delete' })
        .fill(parent_name);
    await modal.getByRole('button', { name: 'Delete', exact: true }).click();

    // The whole branch leaves the tree, and we stay on the article we were
    // viewing: it is not part of the branch.
    await expect(kb.getAsideTreeArticleRow(parent_id)).toHaveCount(0);
    await expect(kb.getAsideTreeArticleRow(child_id)).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`knowbaseitem\\.form\\.php\\?id=${viewed_id}(\\D|$)`));
});

test('A dots menu is rebuilt after a drag gave the article a child', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const viewed_id = await api.knowbase.createArticle({
        name: getUniqueName(`Aside viewed`),
        answer: "My answer",
    });
    const host_name = getUniqueName(`Aside host`);
    const host_id = await api.knowbase.createArticle({
        name: host_name,
        answer: "My answer",
    });
    const moved_name = getUniqueName(`Aside moved`);
    await api.knowbase.createArticle({
        name: moved_name,
        answer: "My answer",
    });

    await kb.goto(viewed_id);

    // The menu is fetched once and cached: opened here, the host has no child
    // yet, so its deletion needs no confirmation modal.
    await kb.doOpenAsideArticleMenu(host_id);
    await expect(
        kb.getAsideArticleAction(host_id, 'Delete article')
    ).toHaveAttribute('data-glpi-kb-action', 'DELETE_ARTICLE');
    await page.keyboard.press('Escape');

    await Promise.all([
        page.waitForResponse('**/Knowbase/Aside/Article/*/Move'),
        kb.doDragArticleOnto(moved_name, host_name),
    ]);

    // The host now takes a child with it when deleted, and its menu says so
    // without a reload.
    await kb.doOpenAsideArticleMenu(host_id);
    await expect(
        kb.getAsideArticleAction(host_id, 'Delete article')
    ).toHaveAttribute('data-glpi-kb-action', 'OPEN_MODAL');
});
