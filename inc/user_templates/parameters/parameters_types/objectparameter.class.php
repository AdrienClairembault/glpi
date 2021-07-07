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

namespace Glpi\User_Templates\Parameters\Parameters_Types;

use Glpi\User_Templates\Parameters\AbstractTemplatesParameters;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * ObjectParameter represent a whole object to use as a parameter.
 * For exemple, this entity of a ticket or its category.
 */
class ObjectParameter extends AbstractParameterType
{
   /**
    * Parameters availables in the item that will be linked
    *
    * @var AbstractTemplatesParameters
    */
   protected $template_parameters;

   /**
    * @param string $key                                       Key to access this value
    * @param AbstractTemplatesParameters $template_parameters  Parameters to add
    */
   public function __construct(string $key, AbstractTemplatesParameters $template_parameters) {
      $this->key = $key;
      $this->template_parameters = $template_parameters;
   }

   public function compute(): array {
      return [
         'type'       => "ObjectParameter",
         'key'        => $this->key,
         'properties' => $this->template_parameters->getAvailableParameters(),
      ];
   }
}
