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

namespace Glpi\Knowbase;

/**
 * What a cascade deletion of a knowledge base article would really do, see
 * `KnowbaseItem::getDeletionImpact()`.
 *
 * A knowledge base article may have several parents, so a descendant is not
 * necessarily lost when one of its ancestors goes away: only the ones whose
 * every parent is deleted too are part of the cascade.
 */
final readonly class DeletionImpact
{
    /**
     * @param int   $article_id    The article the deletion starts from.
     * @param int[] $deletable_ids Every article the cascade deletes, children
     *                             before their parents, `$article_id` last.
     * @param int[] $kept_ids      Descendants that stay in the knowledge base
     *                             because they keep a parent outside of the
     *                             cascade.
     * @param int[] $blocked_ids   Deleted descendants that the current user is
     *                             not allowed to delete. The cascade must be
     *                             refused as a whole when this is not empty.
     *                             `$article_id` is never part of it: its own
     *                             deletion is gated by the caller.
     */
    public function __construct(
        public int $article_id,
        public array $deletable_ids,
        public array $kept_ids,
        public array $blocked_ids,
    ) {}

    /**
     * The deleted descendants, i.e. everything but the article itself.
     *
     * @return int[]
     */
    public function getDeletableDescendantIds(): array
    {
        return array_values(array_diff($this->deletable_ids, [$this->article_id]));
    }

    /**
     * Whether deleting the article takes descendants with it. A plain
     * confirmation is enough when it does not.
     */
    public function isCascade(): bool
    {
        return $this->getDeletableDescendantIds() !== [];
    }

    /**
     * Whether the current user misses the rights on a part of the cascade, see
     * `$blocked_ids`.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_ids !== [];
    }
}
