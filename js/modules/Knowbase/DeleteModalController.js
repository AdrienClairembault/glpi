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

import { deleteArticle } from "/js/modules/Knowbase/EditorActions.js";

/**
 * The "Delete article" modal of an article that hosts children: the deletion
 * is only offered once the user types the article name back, and it takes the
 * whole branch with it.
 *
 * The typed name is a safety belt, not an access control: the endpoint checks
 * the rights, and refuses a cascade that the caller did not ask for.
 */
export class GlpiKnowbaseDeleteModalController
{
    /** @type {HTMLFormElement|null} */
    #form = null;

    /** @type {HTMLInputElement|null} */
    #input = null;

    /** @type {HTMLButtonElement|null} */
    #submit = null;

    /** @type {string} The article name the user has to type. */
    #confirm_phrase = '';

    /**
     * @param {HTMLElement} modal
     */
    constructor(modal)
    {
        this.#form = modal.querySelector('[data-glpi-kb-delete-form]');
        if (!this.#form) {
            return;
        }

        this.#confirm_phrase = this.#form.dataset.glpiKbDeleteConfirmPhrase ?? '';
        this.#input = this.#form.querySelector('[data-glpi-kb-delete-confirm-input]');
        this.#submit = this.#form.querySelector('[data-glpi-kb-delete-submit]');

        // The branch holds an article the user may not delete: the modal only
        // explains why, there is nothing to confirm.
        if (!this.#input || !this.#submit) {
            return;
        }

        this.#input.addEventListener('input', () => this.#refreshSubmitState());
        this.#form.addEventListener('submit', (e) => this.#onSubmit(e));
        // The modal body is in the DOM before its opening animation ends, and
        // this controller only starts once it has: whatever was typed in the
        // meantime has to be taken into account, or the button would stay
        // disabled until the next keystroke.
        this.#refreshSubmitState();
        this.#input.focus();
    }

    #refreshSubmitState()
    {
        // Exact match, spaces around it aside: a typo has to keep the button
        // disabled, that is the whole point of the confirmation.
        this.#submit.disabled = this.#input.value.trim() !== this.#confirm_phrase.trim();
    }

    /**
     * @param {SubmitEvent} e
     */
    async #onSubmit(e)
    {
        e.preventDefault();
        // Also guards against a double click posting twice.
        this.#submit.disabled = true;

        const id = parseInt(this.#form.dataset.glpiKbDeleteArticleId);

        try {
            const response = await deleteArticle(id, true);
            const body = await response.json();

            // Reading one of the deleted articles: there is nowhere to come
            // back to, leave for the knowledge base list.
            const shown = document.querySelector('[data-glpi-kb-item-id]');
            const shown_id = shown ? parseInt(shown.dataset.glpiKbItemId) : 0;
            if (body.deleted_ids.includes(shown_id)) {
                window.location.href = body.redirect;
                return;
            }

            // Reload rather than prune the tree: a cascade touches a whole
            // branch, and the server is the only state that cannot drift.
            window.location.reload();
        } catch {
            // `post()` already raised a toast; keep the modal open.
            this.#refreshSubmitState();
        }
    }
}
