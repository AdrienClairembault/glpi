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

import { expect, test } from '../../fixtures/glpi_fixture';
import { Profiles } from '../../utils/Profiles';
import { getWorkerEntityId, getWorkerUserId } from '../../utils/WorkerEntities';
import type { Api } from '../../utils/Api';
import type { FrameLocator, Locator, Page } from '@playwright/test';

async function openDisplayPreferences(page: Page): Promise<FrameLocator>
{
    await page.getByRole('button', { name: 'Select default items to show' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    return page.frameLocator('[data-testid="display-preference-iframe"]');
}

// Each tab keeps its own copy of the form in the DOM once it has been visited,
// so every locator must be scoped to the tab that is currently displayed.
// `getByRole()` only matches elements exposed in the accessibility tree, which
// leaves out the hidden panels of the other tabs.
function getActiveTab(frame: FrameLocator): Locator
{
    return frame.getByRole('tabpanel');
}

// The Global View and Personal View forms are rendered as bootstrap tabs of
// the same page: once loaded, both stay in the DOM at the same time, only
// one being visible. The "add option" dropdown of each form must only look
// at its own container to hide already added options, not at the whole page.

function getAddOptionDropdown(frame: FrameLocator): Locator
{
    // Select2 hides the original, labelled <select> and renders the visible
    // combobox into a sibling <span>.
    // eslint-disable-next-line playwright/no-raw-locators
    return getActiveTab(frame)
        .getByLabel('Select an option to add', { exact: true })
        .locator('+ span')
        .getByRole('combobox')
    ;
}

async function goToTab(frame: FrameLocator, name: string): Promise<void>
{
    // The modal iframe is narrower than the "md" breakpoint, so GLPI renders
    // its tabs as a <select> (mobile layout) instead of the usual nav-tabs.
    // eslint-disable-next-line playwright/no-raw-locators
    await frame.locator('#tabspanel-select').selectOption({ label: name });

    // The panel content is fetched by ajax: wait for it, otherwise the callers
    // would look at a still empty panel and wrongly conclude that an option is
    // absent from this tab.
    // eslint-disable-next-line playwright/no-raw-locators
    await expect(getActiveTab(frame).locator('.display_preference_config'))
        .toBeVisible()
    ;
}

async function getAddOptionChoices(frame: FrameLocator): Promise<string[]>
{
    const dropdown = getAddOptionDropdown(frame);
    await dropdown.click();
    const options = await frame.getByRole('listbox').getByRole('option').all();
    const texts = await Promise.all(options.map((option) => option.textContent()));
    await dropdown.click(); // Close the dropdown without selecting anything
    return texts
        .map((text) => (text ?? '').trim())
        .filter((text) => text.length > 0)
    ;
}

// The list rows are draggable <li> elements whose ARIA role is toggled
// between "listitem" and "option" by the sortable library depending on
// whether they went through its (re)initialization, so it cannot be relied
// on to find a specific row: match on the "data-opt-id" attribute instead.
function getOptionRow(frame: FrameLocator, name: string): Locator
{
    // eslint-disable-next-line playwright/no-raw-locators
    return getActiveTab(frame).locator('li[data-opt-id]').filter({ hasText: name });
}

async function addOption(frame: FrameLocator, name: string): Promise<void>
{
    const dropdown = getAddOptionDropdown(frame);
    await dropdown.click();
    await frame.getByRole('listbox').getByRole('option', { name: name, exact: true }).click();
    await getActiveTab(frame).getByRole('button', { name: 'Add' }).click();
    await expect(getOptionRow(frame, name)).toBeVisible();
}

async function removeOptionIfPresent(frame: FrameLocator, tab: string, name: string): Promise<void>
{
    await goToTab(frame, tab);
    const row = getOptionRow(frame, name);
    if (await row.count() === 0) {
        return;
    }
    // The remove button is icon-only and, unlike other icon buttons in this
    // form, its accessible name does not fall back to its "title" attribute,
    // so it cannot be matched by name; each row only has one button though.
    await row.getByRole('button').click();
    await expect(row).toBeHidden();
}

/**
 * Personal preferences do not exist until explicitly activated. Make sure
 * they are, so that the personal form is rendered instead of the
 * "Create personal parameters?" prompt.
 *
 * @returns true if the personal view was created by this call.
 */
async function ensurePersonalViewExists(frame: FrameLocator): Promise<boolean>
{
    await goToTab(frame, 'Personal View');
    const create_button = getActiveTab(frame).getByRole('button', { name: 'Create' });
    const add_dropdown = getAddOptionDropdown(frame);
    await expect(create_button.or(add_dropdown)).toBeVisible();

    const has_create_button = await create_button.isVisible();
    if (!has_create_button) {
        return false;
    }

    await create_button.click();
    await expect(add_dropdown).toBeVisible();
    return true;
}

async function deletePersonalView(frame: FrameLocator): Promise<void>
{
    await goToTab(frame, 'Personal View');
    await getActiveTab(frame).getByRole('button', { name: 'Delete personal view', exact: true }).click();
}

async function deletePersonalViewIfCreated(frame: FrameLocator, created: boolean): Promise<void>
{
    if (!created) {
        return;
    }
    await deletePersonalView(frame);
}

test('Global and personal display preference forms have independent "add option" dropdowns', async ({ page, profile }) => {
    await profile.set(Profiles.SuperAdmin);
    await page.goto('/front/computer.php');

    const frame = await openDisplayPreferences(page);

    const created_personal_view = await ensurePersonalViewExists(frame);
    const personal_choices = await getAddOptionChoices(frame);

    await goToTab(frame, 'Global View');
    const global_choices = await getAddOptionChoices(frame);

    // Only rely on options available on both forms, so the test is not
    // affected by whatever is already configured on either of them.
    const common_choices = personal_choices.filter((choice) => global_choices.includes(choice));
    expect(common_choices.length).toBeGreaterThanOrEqual(2);
    const [personal_only_option, global_only_option] = common_choices;

    try {
        // Add an option on the personal view only.
        await goToTab(frame, 'Personal View');
        await addOption(frame, personal_only_option);

        // It must still be selectable on the global view: it must not be
        // hidden there just because it was added on the personal view.
        await goToTab(frame, 'Global View');
        expect(await getAddOptionChoices(frame)).toContain(personal_only_option);

        // Add a different option on the global view only.
        await addOption(frame, global_only_option);

        // It must still be selectable on the personal view: it must not be
        // hidden there just because it was added on the global view.
        await goToTab(frame, 'Personal View');
        expect(await getAddOptionChoices(frame)).toContain(global_only_option);
    } finally {
        // Best-effort cleanup so the suite stays idempotent for other runs.
        await removeOptionIfPresent(frame, 'Global View', global_only_option);
        await removeOptionIfPresent(frame, 'Personal View', personal_only_option);
        await deletePersonalViewIfCreated(frame, created_personal_view);
    }
});

// Migrated from tests/cypress/e2e/search/display_preferences.cy.js
const PENDING_REASON = 'Pending reason';
const GLOBAL_VIEW = 'Global View';
const HELPDESK_VIEW = 'Helpdesk View';

function getColumnHeader(page: Page, name: string): Locator
{
    return page.getByRole('columnheader', { name: name, exact: true });
}

/**
 * The header row of a search list is hidden while the list has no result, and
 * hidden elements are not exposed in the accessibility tree, so an assertion
 * on a missing column would pass for the wrong reason on an empty list.
 */
async function expectSearchListIsNotEmpty(page: Page): Promise<void>
{
    await expect(page.getByRole('columnheader').first()).toBeVisible();
}

/**
 * The cypress version created a ticket so the ticket list has something to
 * display. It must be visible in the helpdesk interface too, which only lists
 * the tickets of the current user, and the `api` fixture is authenticated as
 * its own account: set the worker user as the requester explicitly.
 */
async function createTicketVisibleToWorker(api: Api): Promise<void>
{
    await api.createItem('Ticket', {
        name: 'Display preferences test ticket',
        content: 'Display preferences test ticket',
        entities_id: getWorkerEntityId(),
        _users_id_requester: getWorkerUserId(),
    });
}

/**
 * A previous crashed run may have left the option behind on either view. The
 * cypress version purged it through the API in a `before()` hook, which would
 * delete rows another worker is using: do it through the modal instead, so
 * only this test's own itemtype and option are touched.
 */
async function resetBothViews(frame: FrameLocator): Promise<void>
{
    await removeOptionIfPresent(frame, GLOBAL_VIEW, PENDING_REASON);
    await removeOptionIfPresent(frame, HELPDESK_VIEW, PENDING_REASON);
}

test.describe('Ticket display preference scopes', () => {
    // Both tests write the same option to the same shared, global
    // (`users_id = 0`) display preferences of the Ticket itemtype, one for the
    // central interface and one for the helpdesk one, and each asserts that the
    // other interface was left untouched. `fullyParallel` would run them in two
    // workers at the same time, where they would see each other's writes.
    test.describe.configure({ mode: 'serial' });

    test('can add a column to the global view', async ({ page, profile, api }) => {
        await profile.set(Profiles.SuperAdmin);
        await createTicketVisibleToWorker(api);

        await page.goto('/front/ticket.php');
        const frame = await openDisplayPreferences(page);
        await resetBothViews(frame);

        await goToTab(frame, GLOBAL_VIEW);
        await addOption(frame, PENDING_REASON);

        try {
            // The column must now be part of the central ticket list.
            await page.reload();
            await expect(getColumnHeader(page, PENDING_REASON)).toBeVisible();

            // ... but the helpdesk interface must be left untouched.
            await profile.set(Profiles.SelfService);
            await page.goto('/front/ticket.php');
            await expectSearchListIsNotEmpty(page);
            await expect(getColumnHeader(page, PENDING_REASON))
                .not.toBeAttached()
            ;

            // The same scoping must be visible in the configuration itself.
            await profile.set(Profiles.SuperAdmin);
            await page.goto('/front/ticket.php');
            const config = await openDisplayPreferences(page);

            await goToTab(config, GLOBAL_VIEW);
            await expect(getOptionRow(config, PENDING_REASON)).toBeVisible();

            await goToTab(config, HELPDESK_VIEW);
            await expect(getOptionRow(config, PENDING_REASON))
                .not.toBeAttached()
            ;
        } finally {
            // Restore the global preferences shared by every worker.
            await profile.set(Profiles.SuperAdmin);
            await page.goto('/front/ticket.php');
            await resetBothViews(await openDisplayPreferences(page));
        }
    });

    test('can add a column to the helpdesk view', async ({ page, profile, api }) => {
        await profile.set(Profiles.SuperAdmin);
        await createTicketVisibleToWorker(api);

        await page.goto('/front/ticket.php');
        const frame = await openDisplayPreferences(page);
        await resetBothViews(frame);

        await goToTab(frame, HELPDESK_VIEW);
        await addOption(frame, PENDING_REASON);

        try {
            // The central ticket list must be left untouched.
            await page.reload();
            await expectSearchListIsNotEmpty(page);
            await expect(getColumnHeader(page, PENDING_REASON))
                .not.toBeAttached()
            ;

            // ... while the helpdesk interface now displays the column.
            await profile.set(Profiles.SelfService);
            await page.goto('/front/ticket.php');
            await expect(getColumnHeader(page, PENDING_REASON)).toBeVisible();

            // The same scoping must be visible in the configuration itself.
            await profile.set(Profiles.SuperAdmin);
            await page.goto('/front/ticket.php');
            const config = await openDisplayPreferences(page);

            await goToTab(config, HELPDESK_VIEW);
            await expect(getOptionRow(config, PENDING_REASON)).toBeVisible();

            await goToTab(config, GLOBAL_VIEW);
            await expect(getOptionRow(config, PENDING_REASON))
                .not.toBeAttached()
            ;
        } finally {
            // Restore the global preferences shared by every worker.
            await profile.set(Profiles.SuperAdmin);
            await page.goto('/front/ticket.php');
            await resetBothViews(await openDisplayPreferences(page));
        }
    });
});
