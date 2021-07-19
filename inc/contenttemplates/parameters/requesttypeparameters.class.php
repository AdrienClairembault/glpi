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
use RequestType;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Parameters for "RequestType" items
 */
class RequestTypeParameters extends AbstractTemplatesParameters
{
   public static function getRootName(): string {
      return 'requesttype';
   }

   public static function getTargetClasses(): array {
      return [RequestType::class];
   }

   public function defineParameters(): array {
      return [
         new AttributeParameter("id", __("Request type id")),
         new AttributeParameter("name", __("Request type name")),
      ];
   }

   public function defineValues(CommonDBTM $requesttype): array {
      return [
         'id'   => $requesttype->fields['id'],
         'name' => $requesttype->fields['name'],
      ];
   }
}
