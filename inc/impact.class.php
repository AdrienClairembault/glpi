<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class Impact extends CommonDBRelation {

   // Constants used to express the direction of a graph
   const DIRECTION_FORWARD    = 0b01;
   const DIRECTION_BACKWARD   = 0b10;
   const DIRECTION_BOTH       = 0b11;

   // TODO : export to conf
   const IMPACT_COLOR               = '#DC143C';
   const DEPENDS_COLOR              = '#000080';
   const IMPACT_AND_DEPENDS_COLOR   = '#4B0082';

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
    * Do I have the global right to "view" the Object
    *
    * @return boolean
    */
   public static function canView() {
      return true;
   }

   /**
    * Do I have the right to "view" the Object
    *
    * @return boolean
    */
   public static function canUpdate() {
      return true;
   }

   /**
    * Do I have the global right to "create" the Object
    *
    * @return boolean
    */
   public static function canCreate() {
      return true;
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

      // Check rights
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
               height: 50vh;
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
   public static function printImpactNetworkContainer() {
      $action = Toolbox::getItemTypeFormURL(__CLASS__);
      $formName = "form_impact_network";

      echo "<form name=\"$formName\" action=\"$action\" method=\"post\">";
      echo "<table class='tab_cadre_fixe'>";

      // First row : header
      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Impact graph') . "</th>";
      echo "</tr>";

      // Second row : network graph
      echo "<tr><td>";
      echo '<div id="networkContainer"></div>';
      echo "</td></tr>";

      // Third row : network graph options
      echo "<tr><td>";
      self::printOptionForm();
      echo "</td></tr>";

      // Fourth row : save button
      echo "<tr><td style=\"text-align:center\">";
      echo Html::submit(_sx('button', 'Save'), [
         'name' => 'save'
      ]);
      echo "</td></tr>";

      echo "</table>";

      // Hidden input to update the network graph
      echo Html::input("impacts", [
         'type' => 'hidden'
      ]);
      HTML::closeForm();

      // Color picker for network graph colors options
      // Shouldn't this be already loaded somewhere else ?
      echo Html::css('lib/jqueryplugins/spectrum-colorpicker/spectrum.css');
      Html::requireJs('colorpicker');
   }

   /**
    * Print the option form for the impact analysis
    *
    * @since 9.5
    */
   public static function printOptionForm() {
      // Table that will contains the options related to the Impact graph
      echo "<table class='tab_cadre_fixe'>";

      // First row : Headers
      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Visibility') . "</th>";
      echo "<th>" . __('Colors') . "</th>";
      echo "<th>" . __('Export') . "</th>";
      echo "</tr>";

      // Second row : options (separated in individuals tables)
      echo "<tr>";

      // First option table : visility
      echo "<td style=\"vertical-align:top;width:33%\">";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<td>";
      echo "<input type=\"checkbox\" id=\"showDepends\" checked>";
      echo "<label>&nbsp;" . __("Show assets that depends on the current item") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      echo "<input type=\"checkbox\" id=\"showImpacted\" checked>";
      echo "<label>&nbsp;" . __("Show assets that impact the current item") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "</td>";

      // Second option table  : colors of the arrows
      echo "<td style=\"vertical-align:top;width:33%\">";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<td>";
      HTML::showColorField("depends_color", [
         'value' => self::DEPENDS_COLOR
      ]);
      echo "<label>&nbsp;" . __("Depends on the current item") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      HTML::showColorField("impact_color", [
         'value' => self::IMPACT_COLOR
      ]);
      echo "<label>&nbsp;" . __("Impact the current item") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      HTML::showColorField("impact_and_depends_color", [
         'value' => self::IMPACT_AND_DEPENDS_COLOR
      ]);
      echo "<label>&nbsp;" . __("Impact and depends on the current item") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "</td>";

      // Third option table : export
      echo "<td style=\"vertical-align:top;width:33%\">";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<td>";
      echo "<label>" . __("File format: ") . "</label>";
      $dropdown = Dropdown::showFromArray("impact_format", [
            'png' => "PNG",
            'jpeg' => "JPEG",
      ]);
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      echo "<a id=\"export_link\" href=\"\" download=\"impact.png\">";
      echo "<button class=\"x-button\" type=\"button\" id=\"export_network\">Export</button>";
      echo "</a>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "</td>";

      echo "</tr>";

      echo "</table>";

      // JS to handle the options
      self::printOptionFormInteractions();
   }

   /**
    * Print the js used to interact with the impact analysis through the option
    * form
    *
    * @since 9.5
    */
   public static function printOptionFormInteractions() {
      echo HTML::scriptBlock("
         // Send data as JSON on submit
         $('form[name=form_impact_network]').on('submit', function(event) {
            $('input[name=impacts]').val(JSON.stringify(delta));
         });

         // Update the graph direction
         $('#showDepends, #showImpacted').on('change', function () {
            var showDepends   = $('#showDepends').prop('checked');
            var showImpact    = $('#showImpacted').prop('checked');
            var direction     = 0;

            if (showDepends && showImpact) {
               direction = " . self::DIRECTION_BOTH . ";
            } else if (!showDepends && showImpact) {
               direction = " . self::DIRECTION_FORWARD . ";
            } else if (showDepends && !showImpact) {
               direction = " . self::DIRECTION_BACKWARD . ";
            }

            hideDisabledNodes(direction);
         });

         // Update graph colors
         $('input[name=depends_color]').change(function(){
            setColor(BACKWARD, $('input[name=depends_color]').val());
         });
         $('input[name=impact_color]').change(function(){
            setColor(FORWARD, $('input[name=impact_color]').val());
         });
         $('input[name=impact_and_depends_color]').change(function(){
            setColor(BOTH, $('input[name=impact_and_depends_color]').val());
         });

         // Export graph to png
         $('#export_network').on('click', function (e) {
            var format = $('select[name=\"impact_format\"] option:selected').val();
            $('#export_link').prop('download', 'impact.' + format);
            exportCanvas(format);
         });
      ");
   }

   /**
    * Build the impact graph starting from a node
    *
    * @since 9.5
    *
    * @param CommonDBTM $item    Current item
    *
    * @return array Array containing edges and nodes
    *    See addNode and addEdge to learn the expected node and edge data
    *   Example of a node :
    *      'Computer::1' => [
    *         'id'     => "Computer::1"
    *         'label'  => "PC 1"
    *      ]
    *   Example of an edge :
    *      'Computer::1->Computer::2' => [
    *         'from'   => "Computer::1"
    *         'to'     => "Computer::2"
    *         'arrows' => "to"
    *      ]
    */
   public static function buildGraph(CommonDBTM $item) {
      global $DB;

      $nodes = [];
      $edges = [];

      // Explore the graph forward
      self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_FORWARD);

      // Explore the graph backward
      self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_BACKWARD);

      // Add current node to the graph if no impact relations were found
      if (count($nodes) == 0) {
         self::addNode($nodes, $item);
      }

      return [
         'nodes' => $nodes,
         'edges' => $edges
      ];
   }

   /**
    * Explore dependencies of the current item, subfunction of buildGraph()
    * See buildGraph for more details on shared params
    *
    * @since 9.5
    *
    * @param array      $edges         Edges of the graph
    * @param array      $nodes         Nodes of the graph
    * @param CommonDBTM $node          Current node
    * @param int        $direction     The direction in which the graph
    *    is being explored : DIRECTION_FORWARD or DIRECTION_BACKWARD
    * @param array      $exploredNodes List of nodes that have already been
    *    explored
    */
   public static function buildGraphFromNode(
      array &$nodes,
      array &$edges,
      CommonDBTM $node,
      int $direction,
      array $exploredNodes = []) {

      global $DB;

      // Source and target are determined by the direction in which we are
      // exploring the graph
      switch ($direction) {
         case self::DIRECTION_FORWARD:
            $source = "source_asset";
            $target = "impacted_asset";
            break;
         case self::DIRECTION_BACKWARD:
            $source = "impacted_asset";
            $target = "source_asset";
            break;
      }

      // Get relations of the current node
      $relations = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            $target . '_type'  => get_class($node),
            $target . '_id'    => $node->getID()
         ]
      ]);

      // Add current code to the graph if we found at least one impact relation
      if (count($relations)) {
         self::addNode($nodes, $node);
      }

      foreach ($relations as $relatedItem) {
         // Add the related node
         $relatedNode = new $relatedItem[$source . '_type'];
         $relatedNode->getFromDB($relatedItem[$source . '_id']);
         self::addNode($nodes, $relatedNode);

         // Add or update the relation on the graph
         $edgeID = self::getEdgeID($node, $relatedNode, $direction);
         self::addEdge($edges, $edgeID, $node, $relatedNode, $direction);

         // Keep exploring from this node unless we already went through it
         $relatedNodeID = self::getNodeID($relatedNode);
         if (!isset($exploredNodes[$relatedNodeID])) {
            $exploredNodes[$relatedNodeID] = true;
            self::buildGraphFromNode(
               $nodes,
               $edges,
               $relatedNode,
               $direction,
               $exploredNodes
            );
         }
      }
   }

   /**
    * Add a node to the node list if missing
    *
    * @param array      $nodes  Nodes of the graph
    * @param CommonDBTM $item   Node to add
    *
    * @since 9.5
    *
    * @return bool true if the node was missing, else false
    */
   public static function addNode(array &$nodes, CommonDBTM $item) {

      // Check if the node already exist
      $key = self::getNodeID($item);
      if (isset($nodes[$key])) {
         return false;
      }

      $imageName = strtolower(get_class($item));

      $newNode = [
         'id'     => $key,
         'label'  => $item->fields['name'],
         'shape'  => "image",
         'image'  => "../pics/impact/$imageName.png",
         'font'   => [
            // 'strokeColor' => "#00ff00",
            'multi'       => 'html',
            'face' => 'FontAwesome',
         ],
         'incidents' => [],
         'requests'  => [],
         'changes'   => [],
         'problems'  => [],
      ];

      $ticket = new Ticket();
      $problem = new Problem();
      $change = new Change();

      $incidents = $ticket->getActiveTicketsForItem(
         get_class($item),
         $item->getID(),
         Ticket::INCIDENT_TYPE
      );

      $requests = $ticket->getActiveTicketsForItem(
         get_class($item),
         $item->getID(),
         Ticket::DEMAND_TYPE
      );

      $problems = $problem->getActiveProblemsForItem(
         get_class($item),
         $item->getID()
      );

      $changes = $change->getActiveChangesForItem(
         get_class($item),
         $item->getID()
      );

      // Warning and tooltip if at least one ticket is found
      if (count($incidents) > 0 || count($requests) > 0 ||
          count($problems) > 0 || count($changes) > 0) {
         $newNode['label'] .= ' \uf071';
         $newNode['title'] = __("Click to see ongoing tickets...");
      }

      // Store each request, incident and change details
      foreach ($incidents as $incident) {
         $newNode['incidents'][] = $incident;
      }
      foreach ($requests as $request) {
         $newNode['requests'][] = $request;
      }
      foreach ($problems as $problem) {
         $newNode['problems'][] = $problem;
      }
      foreach ($changes as $change) {
         $newNode['changes'][] = $change;
      }

      // Insert the node
      $nodes[$key] = $newNode;
      return true;
   }

   /**
    * Add an edge to the edge list if missing, else update it's direction
    *
    * @param array      $edges      Edges of the graph
    * @param string     $key        ID of the new edge
    * @param CommonDBTM $itemA      One of the node connected to this edge
    * @param CommonDBTM $itemB      The other node connected to this edge
    * @param int        $direction  Direction of the edge : A to B or B to A ?
    *
    * @since 9.5
    *
    * @return bool true if the node was missing, else false
    */
   public static function addEdge(
      array &$edges,
      string $key,
      CommonDBTM $itemA,
      CommonDBTM $itemB,
      int $direction) {

      // Just update the flag if the edge already exist
      if (isset($edges[$key])) {
         $flag = $edges[$key]['flag'];
         $edges[$key]['flag'] = $edges[$key]['flag'] | $direction;
         return;
      }

      // Add the new edge
      switch ($direction) {
         case self::DIRECTION_FORWARD:
            $from = self::getNodeID($itemA);
            $to = self::getNodeID($itemB);
            break;
         case self::DIRECTION_BACKWARD:
            $from = self::getNodeID($itemB);
            $to = self::getNodeID($itemA);
            break;
      }

      $edges[$key] = [
         'id'        => $key,
         'from'      => $from,
         'to'        => $to,
         'arrows'    => "to",
         'flag'      => $direction
      ];
   }

   /**
    * Build the vis.js object and insert it into the page
    *
    * @since 9.5
    *
    * @param array $nodes  Nodes of the graph
    * @param array $edges  Edges of the graph
    */
   public static function buildNetwork(CommonDBTM $item) {
      // Load script
      echo HTML::script("js/impact_network.js");

      // Get needed var from php to init the network
      $locales       = self::getVisJSLocales();
      $currentItem   = self::getNodeID($item);

      $js = "
         // Shared const
         var FORWARD  = " . self::DIRECTION_FORWARD . ";
         var BACKWARD = " . self::DIRECTION_BACKWARD . ";
         var BOTH     = " . self::DIRECTION_BOTH . ";

         var IMPACT_COLOR             = '" . self::IMPACT_COLOR . "';
         var DEPENDS_COLOR            = '" . self::DEPENDS_COLOR . "';
         var IMPACT_AND_DEPENDS_COLOR = '" . self::IMPACT_AND_DEPENDS_COLOR . "';

         // Init network
         var glpiLocales = '$locales';
         var currentItem = '$currentItem';
         initImpactNetwork(glpiLocales, currentItem);";

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

      echo '<div id="ticketsDialog"></div>';
   }

   /**
    * Show the impact network for a specified item
    *
    * @since 9.5
    *
    * @param CommonDBTM $item The specified item
    */
   public static function showImpactNetwork(CommonDBTM $item) {

      // Load the required elements
      self::loadVisJS();
      self::loadNetworkContainerStyle();
      self::printImpactNetworkContainer();
      self::printAddNodeDialog();

      // Start the network
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
    * Create an ID for a node (ItemType::ItemID)
    *
    * @param CommonDBTM  $item Name of the node
    *
    * @return string
    */
   public static function getNodeID(CommonDBTM $item) {
      return get_class($item) . "::" . $item->getID();
   }

   /**
    * Create an ID for an edge (NodeID->NodeID)
    *
    * @param CommonDBTM  $itemA     First node of the edge
    * @param CommonDBTM  $itemB     Second node of the edge
    * @param int         $direction Direction of the edge : A to B or B to A ?
    *
    * @return string|null
    */
   public static function getEdgeID(
      CommonDBTM $itemA,
      CommonDBTM $itemB,
      int $direction) {

      switch ($direction) {
         case self::DIRECTION_FORWARD:
            return self::getNodeID($itemA) . "->" . self::getNodeID($itemB);

         case self::DIRECTION_BACKWARD:
            return self::getNodeID($itemB) . "->" . self::getNodeID($itemA);
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
         'unexpectedError' => __("Unexpected error."),
         'Incidents' => __("Incidents"),
         'Requests' => __("Requests"),
         'Changes' => __("Changes"),
         'Problems' => __("Problems"),
      ];

      return addslashes(json_encode($locales));
   }
}