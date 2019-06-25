<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2019 Teclib' and contributors.
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

include ('../inc/includes.php');
Html::header(__('Impact'), $_SERVER['PHP_SELF'], "tools", "impact");


$itemType = $_POST["type"]   ?? $_GET["type"]  ?? null;
$itemID =   $_POST["id"]     ?? $_GET["id"]    ?? null;

// Handle submitted form
if (!empty($itemType) && !empty($itemID) &&
   Impact::assetExist($itemType, $itemID)) {

   $item = new $itemType;
   $item->getFromDB($itemID);
   Impact::prepareImpactNetwork();
   Impact::buildNetwork($item);
}
printForm();
Html::footer();

// Print the item_type and item_id selection form
function printForm() {
   global $CFG_GLPI;
   $rand = mt_rand();
   // Session::checkRight("impact", READ);

   echo "<form name=\"item\" action=\"{$_SERVER['PHP_SELF']}\" method=\"GET\">";
   echo '<div id="itemform" title="' . __('New asset') . '">';

   echo '<table class="tab_cadre_fixe" style="width:30%">';

   echo "<tr>";
   echo "<th colspan=\"2\">" . __('Impact analysis') . "</th>";
   echo "</tr>";

   // Item type field
   echo "<tr>";
   echo "<td width=\"40%\"> <label>" . __('Item type') . "</label> </td>";
   echo "<td>";
   Dropdown::showItemTypes(
      'type',
      $CFG_GLPI['impact_assets_list'],
      [
         'value'        => null,
         'width'        => '100%',
         'emptylabel'   => Dropdown::EMPTY_VALUE,
         'rand'         => $rand
      ]
   );
   echo "</td>";
   echo "</tr>";

   // Item id field
   echo "<tr>";
   echo "<td> <label>" . __('Item') . "</label> </td>";
   echo "<td>";
   Ajax::updateItemOnSelectEvent("dropdown_type$rand", "form_results",
      $CFG_GLPI["root_doc"] . "/ajax/dropdownTrackingDeviceType.php",
      [
         'itemtype'        => '__VALUE__',
         'entity_restrict' => 0,
         'multiple'        => 1,
         'admin'           => 1,
         'rand'            => $rand,
         'myname'          => "id",
      ]
   );
   echo "<span id='form_results'>\n";
   echo "</span>\n";
   echo "</td>";
   echo "</tr>";

   echo "<tr><td colspan=\"2\" style=\"text-align:center\">";
   echo Html::submit(__("Show impact analysis"));
   echo "</td></tr>";

   echo "</table>";
   echo "</div>";
   echo "<br><br>";
   Html::closeForm();
}