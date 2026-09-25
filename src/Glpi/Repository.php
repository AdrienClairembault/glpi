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

namespace Glpi\Repository;

use CommonDBTM;
use DBmysql;
use Glpi\Exception\TooManyResultsException;
use Glpi\Toolbox\SingletonTrait;
use Toolbox;

final class Repository
{
    use SingletonTrait;

    private DBmysql $db;

    private function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * @template T of CommonDBTM
     * @param class-string<T> $itemtype
     * @return T|false
     *
     * TODOs:
     * - Change return type to CommonDBTM and throw exception when item is not found.
     * - Throw exception when $itemtype is invalid.
     * - Simplify by removing `$item::getIndexName()`, always use harcoded id column. This allow $id to become `int`.
     */
    public function getById(string $itemtype, int|string $id): CommonDBTM|false
    {
        if ((string) $id === '') {
            return false;
        }

        $item = getItemForItemtype($itemtype);
        if ($item === false) {
            return false;
        }

        $iterator = $this->db->request([
            'FROM'   => $item::getTable(),
            'WHERE'  => [
                $item::getTable() . '.' . $item::getIndexName() => Toolbox::cleanInteger($id),
            ],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 1) {
            $item->fields = $iterator->current();
            $item->post_getFromDB();
            return $item;
        } elseif (count($iterator) > 1) {
            throw new TooManyResultsException(
                sprintf(
                    '`%1$s::getFromDB()` expects to get one result, %2$s found in query "%3$s".',
                    $item::class,
                    count($iterator),
                    $iterator->getSql()
                )
            );
        }

        return false;
    }
}
