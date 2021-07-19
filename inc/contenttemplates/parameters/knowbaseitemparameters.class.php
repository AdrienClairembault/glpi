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
use KnowbaseItem;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Parameters for "KnowbaseItem" items
 */
class KnowbaseItemParameters extends AbstractTemplatesParameters
{
   public static function getRootName(): string {
      return 'knowbaseitem';
   }

   public static function getTargetClasses(): array {
      return [KnowbaseItem::class];
   }

   public function defineParameters(): array {
      return [
         new AttributeParameter("id", __("Knowledge base article's id")),
         new AttributeParameter("name", __("Knowledge base article's title")),
         new AttributeParameter("answer", __("Knowledge base article's content"), "raw"),
         new AttributeParameter("link", __("Link to the knowledge base article"), "raw"),
      ];
   }

   protected function defineValues(CommonDBTM $kbi): array {
      return [
         'id'     => $kbi->fields['id'],
         'name'   => $kbi->fields['name'],
         'answer' => $kbi->fields['answer'],
         'link'   => $kbi->getLink(),
      ];
   }
}
