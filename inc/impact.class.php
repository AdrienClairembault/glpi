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
    * Show the impact network of the specified item
    *
    * @since 9.5
    *
    * @param CommonGLPI $item         Starting point of the network
    * @param integer    $tabnum       tab number (default 1)
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return boolean
   **/
   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0) {

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
            #addNodedialog {
               display: none;
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
      $action = Toolbox::getItemTypeFormURL(__CLASS__);
      echo "<form name=\"form_impact_network\" action=\"$action\" method=\"post\">";
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

      echo Html::input("impacts", [
         'type' => 'hidden'
      ]);
      echo Html::submit(_sx('button', 'Save'), [
         'name' => 'save'
      ]);

      HTML::closeForm();
      echo HTML::scriptBlock("
         $('form[name=form_impact_network]').on('submit', function(event) {
            // $('input[name=impacts]').val(delta);
            // var json = {'JObject': delta};
            // json = ;
            $('input[name=impacts]').val(JSON.stringify(delta));
            // event.preventDefault();
         });
      ");
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
    *          'Computer::1->Computer::2' => [
    *             'from'   => "Computer::1"
    *             'to'     => "Computer::2"
    *             'arrows' => "to"
    *          ]
    *      ]
    *   The keys of this array are made using the following format :
    *      sourceItemType::sourceItemID->ImpactedItemType::ImpactedItemId
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
         if (!isset($edges["$sourceKey" . "->" . "$impactedKey"])) {

            // Add the new edge
            $edges["$sourceKey" . "->" . "$impactedKey"] = [
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

   /**
    * Build the vis.js objet and insert it into the page
    *
    * @since 9.5
    *
    * @param array $nodes  Nodes of the graph
    * @param array $edges  Edges of the graph
   **/
   public static function buildNetwork(array $nodes, array $edges) {

      // Remove array keys and convert to json
      $nodes = json_encode(array_values($nodes));
      $edges = json_encode(array_values($edges));

      $js = '
         var container = document.getElementById("networkContainer");
         var data = {
            nodes: new vis.DataSet(JSON.parse(\'' . $nodes . '\')),
            edges: new vis.DataSet(JSON.parse(\'' . $edges . '\'))
         };

         var delta = {};

         function updateDelta (action, edge) {
            var key = edge.from + "->" + edge.to;

            // Remove useless changes (add + delete the same edge)
            if (delta.hasOwnProperty(key)) {
               delete delta[key];
               return;
            }

            var source = edge.from.split("::");
            var impacted = edge.to.split("::");

            delta[key] = {
               action:              action,
               source_asset_type:   source[0],
               source_asset_id:     source[1],
               impacted_asset_type: impacted[0],
               impacted_asset_id:   impacted[1]
            };
         }

         var options = {
            manipulation: {
               enabled: true,
               initiallyActive: true,
               addNode: function(nodeData,callback) {
                  $( "#addNodedialog" ).dialog({
                     modal: true,
                     buttons: [{
                           text: "Add",
                           click: function() {
                              nodeData.label = $("select[name=item_id] option:selected").text();
                              nodeData.id = $("select[name=item_type] option:selected").val() +
                                 "::" +
                                 $("select[name=item_id] option:selected").val();
                              callback(nodeData);
                              $( this ).dialog( "close" );
                           }
                        },{
                           text: "Cancel",
                           click: function() {
                              $( this ).dialog( "close" );
                           }
                        }
                     ]
                  });
               },
               deleteNode: function (node, callback) {
                  node.edges.forEach(function (e){
                     realEdge = data.edges.get(e);
                     updateDelta("delete", realEdge);
                  });
                  callback(node);
               },
               addEdge: function(edge, callback) {
                  edge.arrows = "to";
                  updateDelta("add", edge);
                  callback(edge);
               },
               deleteEdge: function(edge, callback) {
                  realEdge = data.edges.get(edge.edges[0]);
                  updateDelta("delete", realEdge);
                  callback(edge);
               }
            },
            locale: "en"
         };

         var network = new vis.Network(container, data, options);
      ';

      echo HTML::scriptBlock($js);
   }

   /**
    * Load the impact network html content
    *
    * @since 9.5
    */
   public static function printAddNodeDialog() {
      global $CFG_GLPI;
      $conf = Config::getConfigurationValues('Core');
      $rand = mt_rand();

      echo '<div id="addNodedialog" title="' . __('New asset') . '">';

      echo '<table class="tab_cadre_fixe">';

      // Item type field
      echo "<tr>";
      echo "<td> <label>" . __('Item type') . "</label> </td>";
      echo "<td>";
      Dropdown::showItemTypes(
         'item_type',
         $conf['impact_assets_list'],
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
      Ajax::updateItemOnSelectEvent("dropdown_item_type$rand", "results",
         $CFG_GLPI["root_doc"].
         "/ajax/dropdownTrackingDeviceType.php",
         [
            'itemtype'        => '__VALUE__',
            'entity_restrict' => 0,
            'multiple'        => 1,
            'admin'           => 1,
            'rand'            => $rand,
            'myname'          => "item_id",
         ]
      );
      echo "<span id='results'>\n";
      echo "</span>\n";
      echo "</td>";
      echo "</tr>";

      echo "</table>";
      echo "</div>";
   }

   /**
    * Show the impact network for a specified item
    *
    * @since 9.5
    *
    * @param CommonDBTM $item The specified item
   **/
   public static function showImpactNetwork(CommonDBTM $item) {

      // Load the vis.js library
      self::loadVisJS();

      // Load #networkContainer style
      self::loadNetworkContainerStyle();

      // Print the HTML part of the impact network
      self::printImpactNetwork();

      // Print the add node node dialog
      self::printAddNodeDialog();

      // Prepare the graph
      $edges = [];
      $nodes = [];
      self::buildGraph($item, $edges, $nodes, self::DIRECTION_BOTH);

      // Build the network
      self::buildNetwork($nodes, $edges);
   }

   public function add(array $input, $options = [], $history = true) {
      global $DB;

      // Check that mandatory values are set
      $required = [
         "source_asset_type",
         "source_asset_id",
         "impacted_asset_type",
         "impacted_asset_id"
      ];

      if (array_diff($required, array_keys($input))) {
         return false;
      }

      // Check that source and impacted are different items
      if ($input['source_asset_type'] == $input['impacted_asset_type'] &&
          $input['source_asset_id'] == $input['impacted_asset_id']) {
         return false;
      }

      // Check for duplicate
      $it = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'source_asset_type'     => $input['source_asset_type'],
            'source_asset_id'       => $input['source_asset_id'],
            'impacted_asset_type'   => $input['impacted_asset_type'],
            'impacted_asset_id'     => $input['impacted_asset_id']
         ]
      ]);

      if (count($it)) {
         return false;
      }

      // Check if source and impacted are valid objets
      if (!$this->assetExist(
            $input['source_asset_type'],
            $input['source_asset_id']) ||
         !$this->assetExist(
            $input['impacted_asset_type'],
            $input['impacted_asset_id'])) {
         return false;
      }

      parent::add($input, $options, $history);
   }

   public function delete(array $input, $options = [], $history = true) {
      global $DB;

      $var = [
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'source_asset_type'     => $input['source_asset_type'],
            'source_asset_id'       => $input['source_asset_id'],
            'impacted_asset_type'   => $input['impacted_asset_type'],
            'impacted_asset_id'     => $input['impacted_asset_id']
         ]
         ];

      // Check that the link exist
      $it = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'source_asset_type'     => $input['source_asset_type'],
            'source_asset_id'       => $input['source_asset_id'],
            'impacted_asset_type'   => $input['impacted_asset_type'],
            'impacted_asset_id'     => $input['impacted_asset_id']
         ]
      ]);

      if (count($it)) {
         $input['id'] = $it->next()['id'];
         parent::delete($input, $options, $history);
      }
   }

   public function assetExist($itemType, $itemID) {
      try {
         $reflectionClass = new ReflectionClass($itemType);
         if (!$reflectionClass->isInstantiable()) {
            return false;
         }
         $asset = new $itemType();
         return $asset->getFromDB($itemID);
      } catch (ReflectionException $e) { // class does not exist
         return false;
      }
   }

   public function rawSearchOptions() {
      return [];
   }
}