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

$AJAX_INCLUDE = 1;
include ('../inc/includes.php');

// Send UTF8 Headers
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$itemType = $_POST["itemType"]   ?? $_GET["itemType"]  ?? "";
$itemID =   $_POST["itemID"]     ?? $_GET["itemID"]    ?? "";

// Required params
if (empty($itemType) || empty($itemID)) {
   http_response_code(400);
   die;
}

// Try to get the target item
if (!Impact::assetExist($itemType, $itemID)) {
   http_response_code(400);
   die;
}

$item = new $itemType;
$item->getFromDB($itemID);
$graph = Impact::makeDataForCytoscape(Impact::buildGraph($item));

// Remove array keys
$graph['nodes'] = array_values($graph['nodes']);
$graph['edges'] = array_values($graph['edges']);

// Export graph to json
$json = json_encode($graph);
echo str_replace('\\\\', '\\', $json);
// error_log($graph);
