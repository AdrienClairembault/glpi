<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Summary of PluginImpactsImpact
 * Manage impactship between assets
 */
class Impact extends CommonDBRelation {
   const DIRECTION_FORWARD    = 0b01;
   const DIRECTION_BACKWARD   = 0b10;
   const DIRECTION_BOTH       = 0b11;

   /**
    * Return the localized name of the current Type (Asset impacts)
    *
    * @since 9.5
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   public static function getTypeName($nb = 0) {
      return _n('Asset impact', 'Asset impacts', $nb);
   }

   /**
    * Get Tab Name used for itemtype
    *
    * @since 9.5
    *
    * @param CommonGLPI $item         Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return string tab name
    **/
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $nbimpacts = 0;

      // TODO : enable or disable count on config

      // if ($_SESSION['glpishow_count_on_tabs']) {
      //    self::getOppositeItems($item, $opposite);
      //    $nbimpacts = count($opposite);
      // }

      return self::createTabEntry(_n('Impact', 'Impacts', Session::getPluralNumber(), 'impacts'), $nbimpacts);
   }

   /**
    * Show the impact analysis network centered on the specified item
    *
    * @since 9.5
    *
    * @param $item  Starting point of the network
    *
    * @return bool
    */
   public static function showForItem(CommonDBTM $item) {
      $ID = $item->getID();

      // Don't show the impact analysis on new object
      if ($item->isNewID($ID)) {
         return false;
      }

      // Right check
      $itemtype = $item->getType();
      if (!$itemtype::canView()) {
         return false;
      }

      // Show the impact network
      self::showImpactNetwork($item, PHP_INT_MAX);

      return true;
   }

   /**
    * Load the vis.js library used to build the impact analysis graph
    *
    * @since 9.5
    */
   public static function loadVisJS() {
      $baseURL = "lib/vis";

      echo HTML::script("$baseURL/vis.min.js", [], false);
      echo HTML::css("$baseURL/vis.min.css", [], false);
   }

   /**
    * Load the #networkContainer div style
    *
    * @since 9.5
    */
   public static function loadNetworkContainerStyle() {
      echo '
         <style type="text/css">
            #networkContainer {
               width: 100%;
               height: 800px;
               border: 1px solid lightgray;
            }
         </style>
      ';
   }

   /**
    * Load the impact network html content
    *
    * @since 9.5
    */
   public static function printImpactNetwork() {
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Impact graph', 'impacts') . "</th>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>";
      echo '<div id="networkContainer"></div>';
      echo "</td>";
      echo "</tr>";

      echo "</table>";
   }

   /**
    * Build the network graph recursively
    *
    * @since 9.5
    *
    * @param CommonDBTM $item    Current item
    * @param array      $edges   Store the edges of the graph
    *    example :
    *       [
    *          'Computer::1_Computer::2' => [
    *             'from'   => "Computer::1"
    *             'to'     => "Computer::2"
    *             'arrows' => "to"
    *          ]
    *      ]
    *   The keys of this array are made using the following format :
    *      sourceItemType::sourceItemID_ImpactedItemType::ImpactedItemId
    *   Theses keys are used to check (with isset()) if an edge already exist
    *   in the graph.
    *   The values of this array are another array containing the following
    *   values :
    *       - from   : id of the source node
    *       - to     : id of the impacted node
    *       - arrows : fixed values "to" for now
    * @param array $nodes Store the nodes of the graph
    *    example :
    *       [
    *          'Computer::1' => [
    *             'id'     => "Computer::1"
    *             'label'  => "PC 1"
    *          ]
    *      ]
    *   The keys of this array are made using the following format :
    *       itemType::itemID
    *   Theses keys are used to check (with isset()) if a node already exist
    *   in the graph.
    *   The values of this array are another array containing the following
    *   values :
    *       - id     : id given to this node (will be reused by edges)
    *       - label  : label given to this node
    * @param int $direction Specify if the network should contain the items
    *    impacted by the current item (DIRECTION_FORWARD), the items that
    *    impact the current item (DIRECTION_BACKWARD) or both (DIRECTION_BOTH).
    * @param int  $level depth level, not used yet
    * @param bool $main  Used to check if we are in the original or in a
    *    recursive call of this function.
    */
   public static function buildGraph(
      CommonDBTM $item,
      array &$edges,
      array &$nodes,
      int $direction = self::DIRECTION_BOTH,
      int $level = 0,
      bool $main = true) {

      global $DB;

      $currentItemDependencies = [];

      // Get all items that depend from the current item
      if ($direction & self::DIRECTION_FORWARD) {
         $depend = $DB->request([
            'FROM'   => 'glpi_impacts',
            'WHERE'  => [
               'source_asset_type'  => get_class($item),
               'source_asset_id'    => $item->getID()
            ]
         ]);

         // Add these items to $currentItemDependencies
         foreach ($depend as $impactedItem) {
            $id = $impactedItem['impacted_asset_id'];
            $impactedItem = new $impactedItem['impacted_asset_type'];
            $impactedItem->getFromDB($id);

            $currentItemDependencies[] = [
               "source" => $item,
               "impacted" => $impactedItem,
               "direction" => self::DIRECTION_FORWARD
            ];
         }
      }

      // Get all items that impact the current item
      if ($direction & self::DIRECTION_BACKWARD) {
         $impact = $DB->request([
            'FROM'   => 'glpi_impacts',
            'WHERE'  => [
               'impacted_asset_type'  => get_class($item),
               'impacted_asset_id'    => $item->getID()
            ]
         ]);

         // Add these items to $currentItemDependencies
         foreach ($impact as $sourceItem) {
            $id = $sourceItem['source_asset_id'];
            $sourceItem = new $sourceItem['source_asset_type'];
            $sourceItem->getFromDB($id);

            $currentItemDependencies[] = [
               "source" => $sourceItem,
               "impacted" => $item,
               "direction" => self::DIRECTION_BACKWARD
            ];
         }
      }

      // Explore each dependency
      self::explodeDependencies(
         $currentItemDependencies,
         $edges,
         $nodes,
         $direction,
         $level
      );

      /* If we found no edges after exploring all dependencies,
         create a single node for the current item */
      if ($level == 0 && count($nodes) == 0 && $main == true) {
         $currentKey = sprintf(
            "%s::%s",
            get_class($item),
            $item->getID()
         );

         $nodes[$currentKey] = [
            'id'     => $currentKey,
            'label'  => $item->fields['name']
         ];
      }
   }

   /**
    * Explore dependencies of the current item, subfunction of buildGraph()
    * See buildGraph for more details on shared params
    *
    * @since 9.5
    *
    * @param array      $impacts   Dependencies found from the current item
    *    Each row contains the following values :
    *       - source :     source item
    *       - impacted :   impacted item
    *       - direction :  from which direction we came from :
    *             self::DIRECTION_FORWARD or self::DIRECTION_BACKWARD
    * @param array      $edges     Store the edges of the graph
    * @param array      $nodes     Store the nodes of the graph
    * @param int        $direction Specify if the network should contain the
    *    items impacted by the current item (DIRECTION_FORWARD), the items that
    *    impact the current item (DIRECTION_BACKWARD) or both (DIRECTION_BOTH).
    * @param int        $level      depth level, not used yet
    */
   public static function explodeDependencies(
      array $impacts,
      array &$edges,
      array &$nodes,
      int $direction,
      int $level) {

      foreach ($impacts as $impact) {
         $source = $impact['source'];
         $impacted = $impact['impacted'];

         // Build key of the source item (class::id)
         $sourceKey = sprintf(
            "%s::%s",
            get_class($source),
            $source->getID()
         );

         // Build key of the impacted item (class::id)
         $impactedKey = sprintf(
            "%s::%s",
            get_class($impacted),
            $impacted->getID()
         );

         // Check that this edge is not registered yet
         if (!isset($edges["$sourceKey" . "_" . "$impactedKey"])) {

            // Add the new edge
            $edges["$sourceKey" . "_" . "$impactedKey"] = [
               'from'      => $sourceKey,
               'to'        => $impactedKey,
               'arrows'    => "to"
            ];

            // Add source node if missing
            if (!isset($nodes[$sourceKey])) {
               $nodes[$sourceKey] = [
                  'id'     => $sourceKey,
                  'label'  => $source->fields['name']
               ];
            }

            // Add impacted node if missing
            if (!isset($nodes[$impactedKey])) {
               $nodes[$impactedKey] = [
                  'id'     => $impactedKey,
                  'label'  => $impacted->fields['name']
               ];
            }

            /* Keep going in the same direction:
               - Use the impacted item if we came forward
               - Use the source item if we came backward
            */
            if ($impact['direction'] === self::DIRECTION_FORWARD) {
               self::buildGraph(
                  $impacted,
                  $edges,
                  $nodes,
                  $direction,
                  $level++,
                  false
               );
            } else {
               self::buildGraph(
                  $source,
                  $edges,
                  $nodes,
                  $direction,
                  $level--,
                  false
               );
            }
         }
      }
   }

   public static function buildVis(array $nodes, array $edges) {
      $nodes = self::prepareArrayForJS($nodes);
      $edges = self::prepareArrayForJS($edges);

      $str = '
         // create an array with nodes
         var nodes = new vis.DataSet(JSON.parse(\'' . json_encode($nodes) . '\'));

         // create an array with edges
         var edges = new vis.DataSet(JSON.parse(\'' . json_encode($edges) . '\'));

         // create a network
         var container = document.getElementById("networkContainer");
         var data = {
            nodes: nodes,
            edges: edges
         };
         var options = {};

         var network = new vis.Network(container, data, options);
      ';
            // error_log($str);
      echo HTML::scriptBlock($str);
   }

   public static function prepareArrayForJs(array $array) {
      // error_log(print_r(array_values($array), true));
      // error_log(json_encode(array_values($array), true));
      return array_values($array);
   }

   public static function showImpactNetwork(CommonDBTM $item, $level = 1) {
      global $CFG_GLPI;

      // Load vis.js lib
      self::loadVisJS();

      // Load #networkContainer style
      self::loadNetworkContainerStyle();

      // Print the HTML part of the impact network
      self::printImpactNetwork();

      $edges = [];
      $nodes = [];
      self::buildGraph($item, $edges, $nodes, self::DIRECTION_BOTH);

      self::buildVis($nodes, $edges);
   }

   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item         is the item
    * @param mixed      $tabnum       is the tab num
    * @param mixed      $withtemplate has template
    * @return mixed
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      self::showForItem($item);
      return true;
   }
}