<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace Glpi\ContentTemplates\Parameters;

use CommonDBTM;
use Glpi\ContentTemplates\Parameters\Parameters_Types\AttributeParameter;
use SLA;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Parameters for "SLA" items
 */
class SLAParameters extends AbstractTemplatesParameters
{
   public static function getRootName(): string {
      return 'sla';
   }

   public static function getTargetClasses(): array {
      return [SLA::class];
   }

   public function defineParameters(): array {
      return [
         new AttributeParameter("id", __("SLA's ID")),
         new AttributeParameter("name", __("SLAs's name")),
         new AttributeParameter("type", __("SLA's type")),
         new AttributeParameter("duration", __("SLA's duration")),
         new AttributeParameter("unit", __("SLA's duration unit")),
      ];
   }

   protected function defineValues(CommonDBTM $sla): array {
      return [
         'id'       => $sla->fields['id'],
         'name'     => $sla->fields['name'],
         'type'     => SLA::getOneTypeName($sla->fields['type']),
         'duration' => $sla->fields['number_time'],
         'unit'     => strtolower(SLA::getDefinitionTimeLabel($sla->fields['definition_time'])),
      ];
   }
}
