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

   const EDGE = 1;
   const NODE = 2;

   /**
    * Return the localized name of the current Type (Asset impacts)
    *
    * @since 9.5
    *
    * @param integer $nb Number of items
    *
    * @return string
    */
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
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      global $DB;

      // Get direct dependencies
      $it = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'OR' => [
               'source_asset_type'     => get_class($item),
               'source_asset_id'       => $item->getID(),
            ], [
               'impacted_asset_type' => get_class($item),
               'impacted_asset_id' => $item->getID(),
            ]
         ]
      ]);

      $tabName = _n('Impact', 'Impacts', Session::getPluralNumber(), 'impacts');

      return self::createTabEntry($tabName, count($it));
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
    */
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
      $formName = "form_impact_network";

      echo "<form name=\"$formName\" action=\"$action\" method=\"post\">";

      echo "<table class='tab_cadre_fixe'>";

      echo "<tr class='tab_bg_2'>";
      echo "<th colspan=\"2\">" . __('Impact graph') . "</th>";
      echo "</tr>";

      echo "<tr>";
      echo "<td width=\"80%\">";
      echo '<div id="networkContainer"></div>';
      echo "</td>";
      echo "<td>";
      self::printOptionForm();
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
      self::printOptionFormInteractions();
   }

   static function printOptionForm() {
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr class='tab_bg_2'>";
      echo "<th colspan=\"1\">" . __('Options (WIP)') . "</th>";
      echo "</tr>";

      echo "<tr>";
      $dropdown = Dropdown::showFromArray("direction", [
         self::DIRECTION_BOTH => "Both",
         self::DIRECTION_FORWARD => "FORWARD",
         self::DIRECTION_BACKWARD => "BACKWARD",
      ], [
         'display' => false
      ]);
      echo "<td><label>Direction: </label>$dropdown</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>";
      echo "<input type=\"checkbox\" id=\"colorizeImpacted\" checked>";
      echo "<label> color impact </label>";
      echo "</td>";
      echo "</tr>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>";
      echo "<input type=\"checkbox\" id=\"colorizeDepends\" checked>";
      echo "<label> color depends </label>";
      echo "</td>";
      echo "</tr>";
      echo "</tr>";

      echo "<tr>";
      echo "<td> <a id=\"export_link\" href=\"\" download=\"impact.png\">";
      echo "<button type=\"button\" id=\"export\">Export</button>";
      echo "</a></td>";
      echo "</tr>";
      echo "</tr>";

      echo "</table>";
   }

   // Export this to js file ?
   static function printOptionFormInteractions() {
      echo HTML::scriptBlock("
         // On submit convert data to JSON
         $('form[name=form_impact_network]').on('submit', function(event) {
            $('input[name=impacts]').val(JSON.stringify(delta));
         });

         // Change graph
         $('select[name=direction]').on('change', function () {
            switchGraph($('select[name=direction] option:selected').val());
         });

         // Remove colors
         $('#colorizeImpacted').on('change', function () {
            toggleColors(
               " . self::DIRECTION_FORWARD . ",
               $('#colorizeImpacted').is(\":checked\")
            );
         });

         // Remove colors
         $('#colorizeDepends').on('change', function () {
            toggleColors(
               " . self::DIRECTION_BACKWARD . ",
               $('#colorizeDepends').is(\":checked\")
            );
         });

         // Export graph
         $('#export').on('click', function (e) {
            exportCanvas();
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
    *       - arrows : fixed values "to"
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
    * @param bool $main  Used to check if we are in the original or in a
    *    recursive call of this function.
    */
   public static function buildGraph(
      CommonDBTM $item,
      array &$edges,
      array &$nodes,
      int $direction = self::DIRECTION_BOTH,
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
         $direction
      );

      /* If we found no edges after exploring all dependencies,
         create a single node for the current item */
      if (count($nodes) == 0 && $main == true) {
         $currentKey = self::createID(
            self::NODE,
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
    */
   public static function explodeDependencies(
      array $impacts,
      array &$edges,
      array &$nodes,
      int $direction) {

      foreach ($impacts as $impact) {
         $source = $impact['source'];
         $impacted = $impact['impacted'];

         // Build key of the source item (class::id)
         $sourceKey = self::createID(
            self::NODE,
            get_class($source),
            $source->getID()
         );

         // Build key of the impacted item (class::id)
         $impactedKey = self::createID(
            self::NODE,
            get_class($impacted),
            $impacted->getID()
         );

         $egdeKey = self::createID(self::EDGE, $sourceKey, $impactedKey);

         // Check that this edge is not registered yet
         if (!isset($edges[$egdeKey])) {

            // Add the new edge
            $edges[$egdeKey] = [
               'id'        => $egdeKey,
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
                  false
               );
            } else {
               self::buildGraph(
                  $source,
                  $edges,
                  $nodes,
                  $direction,
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
    */
   public static function buildNetwork(CommonDBTM $item) {
      // Load script
      echo HTML::script("js/impact_network.js");

      $locales = self::getVisJSLocales();

      // Get current object key
      $currentItem = self::createID(
         self::NODE,
         get_class($item),
         $item->getID()
      );

      $direction = self::DIRECTION_BOTH;

      $js = "
         var glpiLocales = '$locales';
         var currentItem = '$currentItem';
         var direction = $direction;
         initImpactNetwork(glpiLocales, currentItem, direction);";

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
            'myname'          => "item_id"
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
    */
   public static function showImpactNetwork(CommonDBTM $item) {

      // Load the vis.js library
      self::loadVisJS();

      // Load #networkContainer style
      self::loadNetworkContainerStyle();

      // Print the HTML part of the impact network
      self::printImpactNetwork();

      // Print the "add node" dialog
      self::printAddNodeDialog();

      // Prepare the graph
      // $edges = [];
      // $nodes = [];
      // self::buildGraph($item, $edges, $nodes, self::DIRECTION_BOTH);

      // Build the network
      // self::buildNetwork($nodes, $edges, $item);
      self::buildNetwork($item);
   }

   /**
    * Add a new impact relation
    *
    * @param array $input   Array containing the new relations values
    * @param array $options
    * @param bool  $history
    *
    * @return bool false on failure
    */
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
      if (!self::assetExist(
            $input['source_asset_type'],
            $input['source_asset_id']) ||
         !self::assetExist(
            $input['impacted_asset_type'],
            $input['impacted_asset_id'])) {
         return false;
      }

      return parent::add($input, $options, $history);
   }

   /**
    * Delete an existing impact relation
    *
    * @param array $input   Array containing the impact to be deleted
    * @param array $options
    * @param bool  $history
    *
    * @return bool false on failure
    */
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
         return parent::delete($input, $options, $history);
      }
   }

   /**
    * Check that a given asset exist in the db
    *
    * @param string $itemType Class of the asset
    * @param string $itemID id of the asset
    */
   public static function assetExist(string $itemType, string $itemID) {
      try {
         // Check this asset type is enabled
         $conf = Config::getConfigurationValues('core');
         $enabledClasses = $conf['impact_assets_list'];
         if (array_search($itemType, $enabledClasses) === false) {
            return false;
         }

         $reflectionClass = new ReflectionClass($itemType);

         if (!$reflectionClass->isInstantiable()) {
            return false;
         }

         $asset = new $itemType();
         return $asset->getFromDB($itemID);
      } catch (ReflectionException $e) {
         // class does not exist
         return false;
      }
   }

   /**
    * Create ID used for the node and eges on the graph
    *
    * @param int     $type NODE or EDGE
    * @param string  $a     first element of the id :
    *                         - an item type for NODE
    *                         - a node id for EDGE
    * @param string|int  $b second element of the id :
    *                         - an item id for NODE
    *                         - a node id for EDGE
    *
    * @return string|null
    */
   public static function createID(int $type, string $a, $b) {
      switch ($type) {
         case self::NODE:
            return "$a::$b";
         case self::EDGE:
            return "$a->$b";
      }

      return null;
   }

   /**
    * Get search function for Impacts
    *
    * @return array
    */
   public function rawSearchOptions() {
      return [];
   }

   /**
    * Build the visjs locales
    *
    * @return string json encoded locales array
    */
   public static function getVisJSLocales() {
      $locales = [
         'edit'   => __('Edit'),
         'del'    => __('Delete selected'),
         'back'   => __('Back'),
         'addNode'   => __('Add Asset'),
         'addEdge'   => __('Add Impact relation'),
         'editNode'   => __('Edit Asset'),
         'editEdge'   => __('Edit Impact relation'),
         'addDescription'   => __('Click in an empty space to place a new asset.'),
         'edgeDescription'   => __('Click on an asset and drag the link to another asset to connect them.'),
         'editEdgeDescription'   => __('Click on the control points and drag them to a asset to connect to it.'),
         'createEdgeError'   => __('Cannot link edges to a cluster.'),
         'deleteClusterError'   => __('Clusters cannot be deleted.'),
         'editClusterError'   => __('Clusters cannot be edited.'),
         'duplicateAsset'   => __('This asset already exist.'),
         'linkToSelf'   => __("Can't link an asset to itself."),
         'duplicateEdge'   => __("An identical link already exist between theses two asset."),
         'unexpectedError' => __("Unexpected error.")
      ];

      return addslashes(json_encode($locales));
   }
}