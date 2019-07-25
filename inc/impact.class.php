<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 9.5.0
 */
class Impact extends CommonDBRelation {

   static public $itemtype_1          = 'itemtype_source';
   static public $items_id_1          = 'items_id_source';

   static public $itemtype_2          = 'itemtype_impacted';
   static public $items_id_2          = 'items_id_impacted';

   // Constants used to express the direction of a graph
   const DIRECTION_FORWARD    = 0b01;
   const DIRECTION_BACKWARD   = 0b10;
   const DIRECTION_BOTH       = 0b11;

   // TODO : export to conf ?
   const IMPACT_COLOR               = '#DC143C';
   const DEPENDS_COLOR              = '#000080';
   const IMPACT_AND_DEPENDS_COLOR   = '#4B0082';

   public static function getTypeName($nb = 0) {
      return _n('Asset impact', 'Asset impacts', $nb);
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      global $CFG_GLPI, $DB;

      // Class of the current item
      $class = get_class($item);

      if (in_array($class, $CFG_GLPI['impact_assets_list'])) {
         // Asset : get number of directs dependencies
         $it = $DB->request([
            'FROM'   => 'glpi_impacts',
            'WHERE'  => [
               'OR' => [
                  [
                     'itemtype_source' => get_class($item),
                     'items_id_source'   => $item->getID(),
                  ],
                  [
                     'itemtype_impacted' => get_class($item),
                     'items_id_impacted'   => $item->getID(),
                  ]
               ]
            ]
         ]);
         $total = count($it);

      } else if (in_array($class, [Ticket::class, Problem::class, Change::class])) {
         // ITIL object : no count
         $total = 0;
      }

      return self::createTabEntry(
         // TODO remove trailing space (cannot do it now due to pot plural form incompatibility)
         _n('Impact ', 'Impacts ', Session::getPluralNumber()),
         $total
      );
   }

   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0) {

      global $CFG_GLPI;

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

      $class = get_class($item);

      // For an ITIL object, load the first linked element by default
      if (in_array($class, [Ticket::class, Problem::class, Change::class])) {
         $linkedItems = $item->getLinkedItems();

         // Search for a valid linked item
         $found = false;
         foreach ($linkedItems as $linkedItem) {
            $class = $linkedItem['itemtype'];
            if (in_array($class, $CFG_GLPI['impact_assets_list'])) {
               self::printAssetSelectionForm($linkedItems);
               $found = true;
               $item = new $class;
               $item->getFromDB($linkedItem['items_id']);
               break;
            }
         }

         // No impact to display, tab shouldn't be visible
         if (!$found) {
            return true;
         }
      }

      if (in_array($class, $CFG_GLPI['impact_assets_list'])) {
         // Asset : show the impact network
         self::loadLibs();
         self::prepareImpactNetwork($item);
         self::buildNetwork($item);
         // TODO: fix after cytoscape
         // } else if (in_array($class, [Ticket::class, Problem::class, Change::class])) {
         //    // ITIL object : show asset selection form
         //    self::loadLibs();
         //    self::printAssetSelectionForm($item->getLinkedItems());
         //    self::prepareImpactNetwork();
      }

      return true;
   }

   /**
    * Load the cytoscape and spectrum-colorpicker librairies
    *
    * @since 9.5
    */
   public static function loadLibs() {
      echo Html::css('public/lib/spectrum-colorpicker.css');
      echo Html::script("public/lib/spectrum-colorpicker.js");
      echo Html::css('public/lib/cytoscape.css');
      echo Html::script("public/lib/cytoscape.js");
   }

   /**
    * Load the #networkContainer div style
    *
    * @since 9.5
    */
   public static function loadNetworkContainerStyle() {
      echo '
         <style type="text/css">
            .networkParent {
               border: 1px solid #f1f1f1;;
            }

            .networkTable {
               max-width: none !important;
            }

            #networkContainer {
              /* width: 100%;*/
               height: 70vh;
            }

            #networkContainer div {
               z-index: 1 !important;
               position: absolute !important;
            }

            .impactDialog {
               display: none;
            }

            i.fa-impact-manipulation {
               display: inline;
               font-size: 14px;
            }

            .impact-dropdown {
               /* position: relative; */
               display: inline-block;
            }

            .impact-dropdown-content {
               display: none;
               position: absolute;
               background-color: white;
               min-width: 100px;
               z-index: 15;
               right: 0;
            }

            .impact-dropdown-content li {
               color: black;
               padding: 12px 16px;
               text-decoration: none;
               display: block;
             }

            .impact-dropdown:hover .impact-dropdown-content {
               display: block;
            }


            div.vis-network div.vis-edit-mode div.vis-button.vis-edit {
               background-image: url() !important;
            }

            div.vis-network div.vis-edit-mode div.vis-label {
               margin: 0 !important;
            }

            .impact_toolbar {
               position: relative;
               z-index: 20;
               /*overflow: auto;*/
            }

            #impactTools {
               float: right;
               background-color: white;
               padding: 5px;
               border: 1px solid lightgray;
               border-radius: 2px;
            }

            .impact_toolbar span {
               float: left;
               color: gray;
               font-size: 1.3em;
               padding: 4px 8px;
               transition: all 0.3s ease;
               cursor: pointer;
               border: 2px inset transparent;
            }

            .impact_toolbar .active {
               border: 2px inset #f4f4f4;
            }

            .impact_toolbar_right {
               float: right !important;
            }

            #impactTools span:hover, .networkToolbarHightlight:hover {
               background-color: lightgray;
               border-radius: 2px;
            }

            #helpText {
               font-weight: bold;
            }

            #saveImpact {
               font-weight   : bold;
               text-transform: uppercase;
               letter-spacing: 0.04em;
               color         : gray;
               margin-right  : 20px;
            }

            .dirty {
               color         : #eea818 !important;
            }

            .dirty:hover {
               background-color: #fec95c !important;
               color: #8f5a0a !important;
            }

            /* Page */

            .more-menu {
               /*width: 100px;*/
            }

            /* More Button / Dropdown Menu */

            .more-btn,
            .more-menu-btn {
               background: none;
               border: 0 none;
               line-height: normal;
               overflow: visible;
               -webkit-user-select: none;
               -moz-user-select: none;
               -ms-user-select: none;
               width: 100%;
               text-align: left;
               outline: none;
               cursor: pointer;
            }

            .more-dot {
               background-color: #aab8c2;
               margin: 0 auto;
               display: inline-block;
               width: 7px;
               height: 7px;
               margin-right: 1px;
               border-radius: 50%;
               transition: background-color 0.3s;
            }

            .more-menu {
               position: absolute;
               top: 42px;
               z-index: 900;
               /*float: left;*/
               padding: 10px 0;
               /*margin-top: 35px;*/
               background-color: #fff;
               border: 1px solid #ccd8e0;
               border-radius: 4px;
               box-shadow: 1px 1px 3px rgba(0,0,0,0.25);
               opacity: 0;
               transform: translate(0, 15px) scale(.95);
               transition: transform 0.1s ease-out, opacity 0.1s ease-out;
               pointer-events: none;
               right: 1px;
            }

            .more-menu-caret {
               position: absolute;
               top: -10px;
               right: 5px;
               width: 18px;
               height: 10px;
               float: left;
               overflow: hidden;
            }

            .more-menu-caret-outer,
            .more-menu-caret-inner {
               position: absolute;
               display: inline-block;
               margin-left: -1px;
               font-size: 0;
               line-height: 1;
            }

            .more-menu-caret-outer {
               border-bottom: 10px solid #c1d0da;
               border-left: 10px solid transparent;
               border-right: 10px solid transparent;
               height: auto;
               left: 0;
               top: 0;
               width: auto;
            }

            .more-menu-caret-inner {
               top: 1px;
               left: 1px;
               border-left: 9px solid transparent;
               border-right: 9px solid transparent;
               border-bottom: 9px solid #fff;
            }

            .more-menu-items {
               margin: 0;
               list-style: none;
               padding: 0;
            }

            .more-menu-item {
               display: block;
            }

            .more-menu-btn {
               min-width: 100%;
               color: #66757f;
               cursor: pointer;
               display: block;
               font-size: 13px;
               line-height: 18px;
               padding: 5px 10px;
               position: relative;
               white-space: nowrap;
               font-size: 1.2em !important;
            }

            .more-menu-item:hover {
               background-color: lightgray;
            }

            .more-menu-item:hover .more-menu-btn {
               /*color: #fff;*/
            }

            .more-btn:hover .more-dot,
            .show-more-menu .more-dot {
               background-color: #516471;
            }

            .show-more-menu .more-menu {
               opacity: 1;
               transform: translate(0, 0) scale(1);
               pointer-events: auto;
            }

         </style>
      ';
   }

   /**
    * Print the asset selection form used in the impact tab of ITIL objects
    *
    * @since 9.5
    */
   public static function printAssetSelectionForm($items) {

      global $CFG_GLPI;

      // prepare values
      $values = [];

      foreach ($items as $item) {
         if (in_array($item['itemtype'], $CFG_GLPI['impact_assets_list'])) {
            // Add itemtype if not found yet
            $itemTypeLabel = __($item['itemtype']);
            if (!isset($values[$itemTypeLabel])) {
               $values[$itemTypeLabel] = [];
            }

            $key = $item['itemtype'] . "::" . $item['items_id'];
            $values[$itemTypeLabel][$key] = $item['name'];
         }
      }

      Dropdown::showFromArray("impact_assets_selection_dropdown", $values);
      echo "<br><br>";

      // Form interaction
      echo Html::scriptBlock('
         $(function() {
            var selector = "select[name=impact_assets_selection_dropdown]";

            $(selector).change(function(){
               var value = $(selector + " option:selected").val();

               // Ignore default value (Dropdown::EMPTY_VALUE)
               if (value == "default") {
                  return;
               }

               values = value.split("::");

               $.ajax({
                  type: "POST",
                  url: "'. $CFG_GLPI['root_doc'] . '/ajax/impact.php",
                  data: {
                     itemType:   values[0],
                     itemID:     values[1],
                  },
                  success: function(data, textStatus, jqXHR) {
                     console.log(data);
                     impact.replaceGraph(JSON.parse(data));
                  },
                  dataType: "json"
               });
            });
         });
      ');
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
      echo "<table class='tab_cadre_fixe networkTable'>";

      // First row : header
      echo "<tr class='tab_bg_2'>";
      echo "<th>" . __('Impact graph') . "</th>";
      echo "</tr>";

      // Second row : network graph
      echo "<tr><td class=\"networkParent\">";
      echo '<div class="impact_toolbar">';
      $hidden = 'style="display: none;"';
      echo '<div>';
      echo '<span id="helpText" ' . $hidden . '></span>';
      // echo '<span id="cancel" ' . $hidden . ' class="impact_toolbar_right networkToolbarHightlight"><i class="fas fa-times"></i></span>';
      echo '</div>';
      echo '<div id="impactTools">';
      echo '<span id="saveImpact">' . __("Save") . '</span>';
      echo '<span id="add_node"><i class="fas fa-plus"></i></span>';
      echo '<span id="add_edge"><i class="fas fa-marker"></i></span>';
      echo '<span id="addCompound"><i class="far fa-square"></i></span>';
      echo '<span id="delete_element"><i class="fas fa-trash"></i></span>';
      echo '<span id="exportGraph"><i class="fas fa-download"></i></span>';
      echo '<span id="expandToolbar"><i class="fas fa-ellipsis-v "></i></span>';
      echo '</div>';
      self::printDropdownMenu();
      echo '</div>';
      echo '<div id="networkContainer"></div>';
      echo "</td></tr>";

      // Third row : network graph options
      echo "<tr><td>";
      echo "</td></tr>";

      echo "</table>";

      // Hidden input to update the network graph
      echo Html::input("impacts", [
         'type' => 'hidden'
      ]);
      Html::closeForm();
   }

   public static function printDropdownMenu() {
      echo
         '<div class="more">' .
            '<div class="more-menu" style="display: none;">' .
               '<div class="more-menu-caret">' .
                  '<div class="more-menu-caret-outer"></div>' .
                  '<div class="more-menu-caret-inner"></div>' .
               '</div>' .
               '<ul class="more-menu-items" tabindex="-1">' .
                  '<li id="toggle_impact" class="more-menu-item">' .
                     '<button type="button" class="more-menu-btn">' .
                        '<i class="fas fa-eye"></i> Toggle impact' .
                     '</button>' .
                  '</li>' .
                  '<li id="toggle_depends" class="more-menu-item">' .
                     '<button type="button" class="more-menu-btn">' .
                        '<i class="fas fa-eye"></i> Toggle depends' .
                     '</button>' .
                  '</li>' .
                  '<li id="color_picker" class="more-menu-item">' .
                     '<button type="button" class="more-menu-btn">' .
                        '<i class="fas fa-palette"></i> Colors' .
                     '</button>' .
                  '</li>' .
               '</ul>' .
            '</div>' .
         "</div>";

      echo Html::scriptBlock("
         var el = document.querySelector('.more');
         var btn = $('.more')[0];
         var menu = el.querySelector('.more-menu');
         var visible = false;

         function showMenu(e) {
            e.preventDefault();
            if (!visible) {
               visible = true;
               el.classList.add('show-more-menu');
               $(menu).show();
               document.addEventListener('mousedown', hideMenu, false);
            } else {
               visible = false;
               el.classList.remove('show-more-menu');
               $(menu).hide();
               document.removeEventListener('mousedown', hideMenu);
            }
         }

         function hideMenu(e) {
            if (e.target.id == 'expandToolbar') {
               console.log('nfees');
               return;
            }

            if (btn.contains(e.target)) {
               return;
            }
            if (visible) {
               visible = false;
               el.classList.remove('show-more-menu');
               $(menu).hide();
               document.removeEventListener('mousedown', hideMenu);
            }
         }
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
         case self::DIRECTION_BACKWARD:
            $source = "source";
            $target = "impacted";
            break;
         case self::DIRECTION_FORWARD:
            $source = "impacted";
            $target = "source";
            break;
      }

      // Get relations of the current node
      $relations = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'itemtype_' . $target => get_class($node),
            'items_id_' . $target => $node->getID()
         ]
      ]);

      // Add current code to the graph if we found at least one impact relation
      if (count($relations)) {
         self::addNode($nodes, $node);
      }

      foreach ($relations as $relatedItem) {
         // Add the related node
         $relatedNode = new $relatedItem['itemtype_' . $source];
         $relatedNode->getFromDB($relatedItem['items_id_' . $source]);
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
      global $CFG_GLPI;

      // Check if the node already exist
      $key = self::getNodeID($item);
      if (isset($nodes[$key])) {
         return false;
      }
      $imageName = strtolower(get_class($item));

      $newNode = [
         'id'          => $key,
         'label'       => $item->fields['name'],
         'image'       => $CFG_GLPI["root_doc"]."/pics/impact/$imageName.png",
         'ITILObjects' => $item->getITILTickets(true),
         'link'        => $item->getLinkURL()
      ];

      $itilTicketsCount = $newNode['ITILObjects']['count'];
      if ($itilTicketsCount > 0) {
         $newNode['label'] .= " ($itilTicketsCount)";
         $newNode['hasITILObjects'] = 1;
      }

      // Add parent if exist
      $impactItem = ImpactItem::findForItem($item);
      if ($impactItem) {
         $newNode['parent'] = $impactItem['parent_id'];

         // Add parent node if missing
         if (!isset($nodes[$newNode['parent']])) {
            $nodes[$newNode['parent']] = [
               'id'    => $impactItem['parent_id'],
               'label' => $impactItem['name'],
               'color' => $impactItem['color'],
            ];
         }
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
         $edges[$key]['flag'] = $edges[$key]['flag'] | $direction;
         return;
      }

      // Assign 'from' and 'to' according to the direction
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

      // Add the new edge
      $edges[$key] = [
         'id'     => $key,
         'source' => $from,
         'target' => $to,
         'flag'   => $direction
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
      // Build the graph
      $graph = self::makeDataForCytoscape(Impact::buildGraph($item));

      echo Html::scriptBlock("
         $(function() {
            impact.buildNetwork($graph);
         });
      ");
   }

   /**
    * Convert the php array reprensenting the graph into the format required by
    * the Cytoscape library
    *
    * @param array $graph
    *
    * @return string json data
    */
   public static function makeDataForCytoscape(array $graph) {
      $data = [];

      foreach ($graph['nodes'] as $id => $node) {
         $data[] = [
            'group' => 'nodes',
            'data'  => $node,
         //    'data'  => [
         //       'id'    => $id,
         //       'label' => $node['label'],
         //       'image' => $node['image'],
         //       'link'  => $node['link'],
         //    ]
         ];
      }

      foreach ($graph['edges'] as $id => $edge) {
         $data[] = [
            'group' => 'edges',
            'data'  => $edge,
            // 'data'  => [
               // 'id' => $id,
               // 'source' => $edge['from'],
               // 'target' => $edge['to'],
               // 'flag'   => $edge['flag']
            // ]
         ];
      }

      return json_encode($data);
   }

   /**
    * Load the add node dialog
    *
    * @since 9.5
    */
   public static function printAddNodeDialog() {
      global $CFG_GLPI;
      $rand = mt_rand();

      echo '<div id="addNodeDialog" title="' . __('New asset') . '" class="impactDialog">';
      echo '<table class="tab_cadre_fixe">';

      // Item type field
      echo "<tr>";
      echo "<td> <label>" . __('Item type') . "</label> </td>";
      echo "<td>";
      Dropdown::showItemTypes(
         'item_type',
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
            'test'            => "test"
         ]
      );
      echo "<span id='results'>\n";
      echo "</span>\n";
      echo "</td>";
      echo "</tr>";

      echo "</table>";
      echo "</div>";

      // This dialog will be built on the front end
      echo '<div id="ongoingDialog"></div>';
   }

   public static function printColorConfigDialog() {
      echo '<div id="configColorDialog" class="impactDialog">';
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<td>";
      Html::showColorField("depends_color", [
         'value' => self::DEPENDS_COLOR
      ]);
      echo "<label>&nbsp;" . __("Depends") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      Html::showColorField("impact_color", [
         'value' => self::IMPACT_COLOR
      ]);
      echo "<label>&nbsp;" . __("Impact") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      Html::showColorField("impact_and_depends_color", [
         'value' => self::IMPACT_AND_DEPENDS_COLOR
      ]);
      echo "<label>&nbsp;" . __("Impact and depends") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "</div>";
   }

   public static function printExportDialog() {
      echo '<div id="exportDialog" class="impactDialog">';
      echo "<table>";
      echo "<tr>";
      echo "<td>";
      echo "<label>" . __("File format: ") . "</label>";
      Dropdown::showFromArray("impact_format", [
         'png' => "PNG",
         'jpeg' => "JPEG",
      ]);
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      echo Html::getCheckbox([
         "id"    => "transparentBackground",
         "name"  => "transparentBackground",
         "title" => __("Transparent background (only available for png)")
      ]);
      echo "&nbsp;<label>" . __("Transparent background (only available for png)") . "</label>";
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "<a id=\"export_link\" href=\"\" download=\"impact.png\" style=\"display:none;\">";
      echo "</div>";
   }

   public static function printEditCompoundDialog() {
      echo '<div id="editCompoundDialog"  class="impactDialog">';
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<td>";
      echo "<label>&nbsp;" . __("Name") . "</label>";
      echo "</td>";
      echo "<td>";
      echo Html::input("compoundName", []);
      echo "</td>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>";
      echo "<label>&nbsp;" . __("Color") . "</label>";
      echo "</td>";
      echo "<td>";
      Html::showColorField("compoundColor", [
         'value' => '#d2d2d2'
      ]);
      echo "</td>";
      echo "</tr>";
      echo "</table>";
      echo "</div>";
   }

   /**
    * Prepare the impact network
    *
    * @since 9.5
    *
    * @param CommonGLPI $item The specified item
    */
   public static function prepareImpactNetwork(CommonGLPI $item) {

      // Load requirements
      self::loadNetworkContainerStyle();
      self::printImpactNetworkContainer();
      self::printAddNodeDialog();
      self::printColorConfigDialog();
      self::printExportDialog();
      self::printEditCompoundDialog();

      // Print impact script
      echo Html::script("js/impact.js");

      $locales   = self::getVisJSLocales();
      $default   = "black";
      $forward   = self::IMPACT_COLOR;
      $backward  = self::DEPENDS_COLOR;
      $both      = self::IMPACT_AND_DEPENDS_COLOR;
      $startNode = self::getNodeID($item);
      $form      = "form[name=form_impact_network]";
      $dialogs   = json_encode([
         [
            'key'    => 'addNode',
            'id'     => "#addNodeDialog",
            'inputs' => [
               'itemType' => "select[name=item_type]",
               'itemID'   => "select[name=item_id]"
            ]
         ],
         [
            'key'    => 'configColor',
            'id'     => '#configColorDialog',
            'inputs' => [
               'dependsColor'          => "input[name=depends_color]",
               'impactColor'           => "input[name=impact_color]",
               'impactAndDependsColor' => "input[name=impact_and_depends_color]",
            ]
         ],
         [
            'key'    => 'exportDialog',
            'id'     => '#exportDialog',
            'inputs' => [
               'format'     => "select[name=impact_format]",
               'background' => "#transparentBackground",
               'link'       => "#export_link",
            ]
         ],
         [
            'key' => "ongoingDialog",
            'id'  => "#ongoingDialog"
         ],
         [
            'key'    => "editCompoundDialog",
            'id'     => "#editCompoundDialog",
            'inputs' => [
               'name'  => "input[name=compoundName]",
               'color' => "input[name=compoundColor]",
            ]
         ]
      ]);
      $toolbar = json_encode([
         ['key'    => 'helpText',      'id' => "#helpText"],
         ['key'    => 'tools',         'id' => "#impactTools"],
         ['key'    => 'save',          'id' => "#saveImpact"],
         ['key'    => 'addNode',       'id' => "#add_node"],
         ['key'    => 'addEdge',       'id' => "#add_edge"],
         ['key'    => 'addCompound',   'id' => "#addCompound"],
         ['key'    => 'deleteElement', 'id' => "#delete_element"],
         ['key'    => 'export',        'id' => "#exportGraph"],
         ['key'    => 'expandToolbar', 'id' => "#expandToolbar"],
         ['key'    => 'toggleImpact',  'id' => "#toggle_impact"],
         ['key'    => 'toggleDepends', 'id' => "#toggle_depends"],
         ['key'    => 'colorPicker',   'id' => "#color_picker"],
         ['key'    => 'cancel',        'id' => "#cancel"]
      ]);

      // Get var from server side
      $js = "
         impact.prepareNetwork(
            $(\"#networkContainer\"),
            '$locales',
            {
               default : '$default',
               forward : '$forward',
               backward: '$backward',
               both    : '$both',
            },
            '$startNode',
            '$form',
            '$dialogs',
            '$toolbar'
         )
      ";

      echo Html::scriptBlock($js);
   }

   /**
    * Add a new impact relation
    *
    * @param array $input   Array containing the new relations values
    * @param array $options
    * @param bool  $history
    *
    * @return int|bool id or false on failure
    */
   public function add(array $input, $options = [], $history = true) {
      global $DB;

      // Check that mandatory values are set
      $required = [
         "itemtype_source",
         "items_id_source",
         "itemtype_impacted",
         "items_id_impacted"
      ];

      if (array_diff($required, array_keys($input))) {
         return false;
      }

      // Check that source and impacted are different items
      if ($input['itemtype_source'] == $input['itemtype_impacted'] &&
          $input['items_id_source'] == $input['items_id_impacted']) {
         return false;
      }

      // Check for duplicate
      $it = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'itemtype_source'   => $input['itemtype_source'],
            'items_id_source'   => $input['items_id_source'],
            'itemtype_impacted' => $input['itemtype_impacted'],
            'items_id_impacted' => $input['items_id_impacted']
         ]
      ]);

      if (count($it)) {
         return false;
      }

      // Check if source and impacted are valid objets
      if (!self::assetExist(
            $input['itemtype_source'],
            $input['items_id_source']) ||
         !self::assetExist(
            $input['itemtype_impacted'],
            $input['items_id_impacted'])) {
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

      // Check that the link exist
      $it = $DB->request([
         'FROM'   => 'glpi_impacts',
         'WHERE'  => [
            'itemtype_source'   => $input['itemtype_source'],
            'items_id_source'   => $input['items_id_source'],
            'itemtype_impacted' => $input['itemtype_impacted'],
            'items_id_impacted' => $input['items_id_impacted']
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
      global $CFG_GLPI;

      try {
         // Check this asset type is enabled
         if (!in_array($itemType, $CFG_GLPI['impact_assets_list'])) {
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
         'add'                  => __('Add'),
         'cancel'               => __('Cancel'),
         'edit'                 => __('Edit'),
         'del'                  => __('Delete selected'),
         'back'                 => __('Back'),
         'addNode'              => __('Add Asset'),
         'addEdge'              => __('Add Impact relation'),
         'editNode'             => __('Edit Asset'),
         'editEdge'             => __('Edit Impact relation'),
         'addDescription'       => __('Click in an empty space to place a new asset.'),
         'edgeDescription'      => __('Click on an asset and drag the link to another asset to connect them.'),
         'editEdgeDescription'  => __('Click on the control points and drag them to a asset to connect to it.'),
         'createEdgeError'      => __('Cannot link edges to a cluster.'),
         'deleteClusterError'   => __('Clusters cannot be deleted.'),
         'editClusterError'     => __('Clusters cannot be edited.'),
         'duplicateAsset'       => __('This asset already exists.'),
         'linkToSelf'           => __("Can't link an asset to itself."),
         'duplicateEdge'        => __("An identical link already exist between theses two asset."),
         'unexpectedError'      => __("Unexpected error."),
         'Incidents'            => __("Incidents"),
         'Requests'             => __("Requests"),
         'Changes'              => __("Changes"),
         'Problems'             => __("Problems"),
         'showDepends'          => __("Depends"),
         'showImpact'           => __("Impact"),
         'colorConfiguration'   => __("Colors"),
         'export'               => __("Export"),
         'goTo'                 => __("Go to"),
         'goTo+'                => __("Open this element in a new tab"),
         'showOngoing'          => __("Show ongoing tickets"),
         'showOngoing+'         => __("Show ongoing tickets for this item"),
         'delete'               => __("Delete"),
         'delete+'              => __("Delete element"),
         'new'                  => __("New"),
         'new+'                 => __("Add a new asset"),
         'removeFromCompound'   => __("Remove from group"),
         'removeFromCompound+'  => __("Remove this asset from the group"),
         'ongoingTickets'       => __("Ongoing tickets"),
         'addNodeTooltip'       => __("Add a new asset to the impact network"),
         'addEdgeTooltip'       => __("Add a new impact relation"),
         'addCompoundTooltip'   => __("Create a new group"),
         'deleteTooltip'        => __("Delete an element from the impact network"),
         'downloadTooltip'      => __("Export the impact network"),
         'expandToolbarTooltip' => __("Show more options ..."),
         'showImpactTooltip'    => __("Toggle \"impacted\" visibility"),
         'showDependsTooltip'   => __("Toggle \"depends\" visibility"),
         'showColorsTooltip'    => __("Edit relation's color"),
         'addNodeHelpText'      => __("Click anywhere to add a new asset"),
         'addEdgeHelpText'      => __("Draw a line between two assets to add an impact relation"),
         'addCompoundHelpText'  => __("Draw a square containing the assets you wish to group"),
         'deleteHelpText'       => __("Click on an element to remove it from the network"),
         'editGroup'            => __("Edit group"),
         'save'                 => __("Save"),
         'notEnoughItems'       => __("You need to select at least 2 assets to make a group"),
         'compoundProperties'   => __("Group properties..."),
         'compoundProperties+'  => __("Set name and/or color for this group"),
      ];

      return addslashes(json_encode($locales));
   }
}