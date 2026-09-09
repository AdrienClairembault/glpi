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

import { randomUUID } from "crypto";
import { expect, test } from "../../fixtures/glpi_fixture";
import { KnowbaseItemPage } from "../../pages/KnowbaseItemPage";
import { Profiles } from "../../utils/Profiles";
import { getWorkerEntityId } from "../../utils/WorkerEntities";

test('Can delete an article', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const id = await api.createItem('KnowbaseItem', {
        name: 'My kb entry for delete test',
        entities_id: getWorkerEntityId(),
        answer: "My answer to delete",
    });

    await kb.goto(id);
    await kb.articleActionsMenu.click();

    // Delete article
    const delete_button = kb.getButton('Delete article');
    await expect(delete_button).toBeVisible();
    await delete_button.click();

    // Confirm deletion in dialog
    const confirm_button = page.getByRole('button', { name: 'Delete' });
    await expect(confirm_button).toBeVisible();
    await confirm_button.click();

    // Sent back to the entry point of the knowledge base, which opens the root
    // article now that the deleted one is gone.
    await expect(page).toHaveURL(/\/front\/knowbaseitem\.form\.php\?id=\d+/);
    await expect(page.getByText('Item successfully deleted.')).toBeVisible();

    // Article should no longer exist
    await kb.goto(id);
    await expect(page.getByText('The requested item has not been found')).toBeVisible();
});

test('Deleting an article with sub-articles asks for a typed confirmation', async ({ page, profile, api }) => {
    await profile.set(Profiles.SuperAdmin);
    const kb = new KnowbaseItemPage(page);

    const parent_name = `My kb parent for cascade test ${randomUUID().slice(0, 8)}`;
    const parent_id = await api.createItem('KnowbaseItem', {
        name: parent_name,
        entities_id: getWorkerEntityId(),
        answer: "My answer to delete",
    });
    const child_id = await api.createItem('KnowbaseItem', {
        name: 'My kb child for cascade test',
        entities_id: getWorkerEntityId(),
        answer: "My answer to delete too",
        _parents: [parent_id],
    });

    await kb.goto(parent_id);
    await kb.articleActionsMenu.click();
    await kb.getButton('Delete article').click();

    // An article that hosts children gets the modal, not the plain dialog: it
    // states what goes away, and the deletion stays out of reach until the
    // article name is typed back.
    const modal = kb.getDialog('Delete article');
    await expect(modal.getByText('This action also deletes 1 sub-article.')).toBeVisible();
    await expect(modal.getByText('My kb child for cascade test')).toBeVisible();

    const confirm_button = modal.getByRole('button', { name: 'Delete', exact: true });
    await expect(confirm_button).toBeDisabled();

    // A near miss is not enough.
    const confirm_input = modal.getByRole('textbox', { name: 'Name of the article to delete' });
    await confirm_input.fill(parent_name.slice(0, -1));
    await expect(confirm_button).toBeDisabled();

    await confirm_input.fill(parent_name);
    await expect(confirm_button).toBeEnabled();
    await confirm_button.click();

    // Sent back to the entry point of the knowledge base, and the whole branch
    // is gone.
    await expect(page).toHaveURL(/\/front\/knowbaseitem\.form\.php\?id=\d+/);
    await expect(page.getByText('Item successfully deleted.')).toBeVisible();

    for (const deleted_id of [parent_id, child_id]) {
        await kb.goto(deleted_id);
        await expect(page.getByText('The requested item has not been found')).toBeVisible();
    }
});
