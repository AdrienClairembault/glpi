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

namespace Glpi\Tests\PHPStan\Rules;

use Glpi\Tools\PHPStan\Rules\MissingRightsCheckRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MissingRightsCheckRule>
 */
final class MissingRightsCheckRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MissingRightsCheckRule($this->createReflectionProvider());
    }

    public function testMissingRightsCheckAreReported(): void
    {
        $message = 'Possible missing rights check: `->update()` on a CommonDBTM instance is not '
            . 'preceded by a rights check (`->check()`, `->can()`, or a write-level '
            . '`Session::checkRight()`) on this code path.';

        // Only the two unguarded mutations are expected; every other pattern in the fixture
        // (item check, cross-object check, write-level session gate, guard-and-throw, buried
        // check, non-CommonDBTM receiver) must stay silent.
        $this->analyse(
            [__DIR__ . '/fixtures/front/missing_rights_check.php'],
            [
                [$message, 41],
                [$message, 48],
            ]
        );
    }
}
