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

// Global to store event data;
var eventData = {
   addEdgeStart: null, // Store starting node of a new edge
   tmpEles     : null, // Temporary collection used when adding an edge
   boxSelected : []
};

// Constants to represent nodes and edges
var NODE = 1;
var EDGE = 2;

// Constant for graph direction (bitmask)
var DEFAULT  = 0;   // 0b00
var FORWARD  = 1;   // 0b01
var BACKWARD = 2;   // 0b10
var BOTH     = 3;   // 0b11

// Constant for graph edition mode
var EDITION_DEFAULT      = 1;
var EDITION_ADD_NODE     = 2;
var EDITION_ADD_EDGE     = 3;
var EDITION_DELETE       = 4;
var EDITION_ADD_COMPOUND = 5;

// Constant for ID separator
var NODE_ID_SEPERATOR = "::";
var EDGE_ID_SEPERATOR = "->";

// Const for delta action
var DELTA_ACTION_ADD    = 1;
var DELTA_ACTION_UPDATE = 2;
var DELTA_ACTION_DELETE = 3;

// Load cytoscape
var cytoscape = window.cytoscape;

var impact = {

   // Store the initial state of the graph
   initialState: null,

   // Store translated labels
   locales: {},

   // Store if the different direction of the graph should be colorized
   directionVisibility: {},

   // Store color for egdes
   edgeColors: {},

   // Cytoscape instance
   cy: null,

   // The impact network container
   impactContainer: null,

   // The graph edition mode
   editionMode: null,

   // Start node of the graph
   startNode: null,

   // Store registered dialogs and their inputs
   dialogs: {
      addNode: {
         id: null,
         inputs: {
            itemType: null,
            itemID  : null
         }
      },
      configColor: {
         id: null,
         inputs: {
            dependsColor         : null,
            impactColor          : null,
            impactAndDependsColor: null
         }
      },
      exportDialog: {
         id: null,
         inputs: {
            format    : null,
            background: null,
            link      : null
         }
      },
      ongoingDialog: {
         id: null
      },
      editCompoundDialog: {
         id: null,
         inputs: {
            name : null,
            color: null
         }
      }
   },

   // Store registered toolbar items
   toolbar: {
      helpText      : null,
      tools         : null,
      addNode       : null,
      addEdge       : null,
      addCompound   : null,
      deleteElement : null,
      export        : null,
      expandToolbar : null,
      toggleImpact  : null,
      toggleDepends : null,
      colorPicker   : null,
      cancel        : null,
   },

   /**
    * Get network style
    *
    * @returns {Array}
    */
   getNetworkStyle: function() {
      return [
         {
            selector: 'core',
            style: {
               'selection-box-opacity': '0.2',
               'selection-box-border-width': '0',
               'selection-box-color': '#24acdf'
            }
         },
         {
            selector: 'node:parent',
            style: {
               'padding'           : '30px',
               'shape'             : 'roundrectangle',
               'border-width'      : '0',
               'background-opacity': '1',
               'font-size'         : '1.3em',
               'background-color'  : '#d2d2d2'
            }
         },
         {
            selector: 'node:parent[label]',
            style: {
               'label': 'data(label)',
            }
         },
         {
            selector: 'node:parent[color]',
            style: {
               'border-color'      : 'data(color)',
               'background-color'  : 'data(color)',
            }
         },
         {
            selector: ':selected',
            style: {
               'overlay-opacity': 0.2,
            }
         },
         {
            selector: '[todelete=1]:selected',
            style: {
               'overlay-opacity': 0.2,
               'overlay-color': 'red',
            }
         },
         {
            selector: 'node[image]',
            style: {
               'label'             : 'data(label)',
               'shape'             : 'rectangle',
               'background-color'  : '#666',
               'background-image'  : 'data(image)',
               'background-fit'    : 'contain',
               'background-opacity': '0',
               'font-size'         : '1em'
            }
         },
         {
            selector: '[hidden=1]',
            style: {
               'opacity': '0',
            }
         },
         {
            selector: '[hidden=0]',
            style: {
               'opacity': '1',
            }
         },
         {
            selector: '[id="tmp_node"]',
            style: {
               'opacity': '0',
            }
         },
         {
            selector: 'edge',
            style: {
               'width'             : 3,
               'line-color'        : this.edgeColors[0],
               'target-arrow-color': this.edgeColors[0],
               'target-arrow-shape': 'triangle',
               'curve-style'       : 'bezier'
            }
         },
         {
            selector: '[flag=' + FORWARD + ']',
            style: {
               'line-color'        : this.edgeColors[FORWARD],
               'target-arrow-color': this.edgeColors[FORWARD],
            }
         },
         {
            selector: '[flag=' + BACKWARD + ']',
            style: {
               'line-color'        : this.edgeColors[BACKWARD],
               'target-arrow-color': this.edgeColors[BACKWARD],
            }
         },
         {
            selector: '[flag=' + BOTH + ']',
            style: {
               'line-color'        : this.edgeColors[BOTH],
               'target-arrow-color': this.edgeColors[BOTH],
            }
         }
      ];
   },

   /**
    * Get network layout
    *
    * @returns {Object}
    */
   getNetworkLayout: function () {
      return {
         name: 'dagre',
         rankDir: 'LR'
         // transform: function(node, position) {
            // var sin = 1 // Math.sin(90 * (Math.PI / 180));
            // var cos = 0; // Math.cos(360 * (Math.PI / 180));

            // return {
               // x: position.x * cos - position.y * sin,
               // y: position.x * sin + position.y * cos
            // };
         // }
      };
   },

   /**
    * Get the current state of the graph
    *
    * @returns {Object}
    */
   getCurrentState: function() {
      var data = {edges: {}, compounds: {}, parents: {}};

      // Load edges
      impact.cy.edges().forEach(function(edge) {
         data.edges[edge.data('id')] = {
            source: edge.data('source'),
            target: edge.data('target'),
         };
      });

      // Load compounds
      impact.cy.filter("node:parent").forEach(function(compound) {
         data.compounds[compound.data('id')] = {
            name: compound.data('label'),
            color: compound.data('color'),
         };
      });

      // Load parents
      impact.cy.filter("node:child").forEach(function(node) {
         data.parents[node.data('id')] = node.data('parent');
      });

      return data;
   },

   /**
    * Delta computation for edges
    *
    * @returns {Object}
    */
   computeEdgeDelta: function(currentEdges) {
      var edgesDelta = {};

      // First iterate on the edges we had in the initial state
      Object.keys(impact.initialState.edges).forEach(function(edgeID) {
         var edge = impact.initialState.edges[edgeID];
         if (currentEdges.hasOwnProperty(edgeID)) {
            // If the edge is still here in the current state, nothing happened
            // Remove it from the currentEdges data
            delete currentEdges[edgeID];
         } else {
            // If the edge is missing in the current state, it has been deleted
            var source = edge.source.split(NODE_ID_SEPERATOR);
            var target = edge.target.split(NODE_ID_SEPERATOR);
            edgesDelta[edgeID] = {
               action           : DELTA_ACTION_DELETE,
               itemtype_source  : source[0],
               items_id_source  : source[1],
               itemtype_impacted: target[0],
               items_id_impacted: target[1]
            };
         }
      });

      // Now iterate on the edges we have in the current state
      // Since we removed the edges that were not modified in the previous step,
      // the remaining edges are new ones
      Object.keys(currentEdges).forEach(function (edgeID) {
         var edge = currentEdges[edgeID];
         var source = edge.source.split(NODE_ID_SEPERATOR);
         var target = edge.target.split(NODE_ID_SEPERATOR);
         edgesDelta[edgeID] = {
            action           : DELTA_ACTION_ADD,
            itemtype_source  : source[0],
            items_id_source  : source[1],
            itemtype_impacted: target[0],
            items_id_impacted: target[1]
         };
      });

      return edgesDelta;
   },

   /**
    * Delta computation for compounds
    *
    * @returns {Object}
    */
   computeCompoundsDelta: function(currentCompounds) {
      var compoundsDelta = {};

      // First iterate on the compounds we had in the initial state
      Object.keys(impact.initialState.compounds).forEach(function(compoundID) {
         var compound = impact.initialState.compounds[compoundID];
         if (currentCompounds.hasOwnProperty(compoundID)) {
            // If the compound is still here in the current state
            var currentCompound = currentCompounds[compoundID];

            // Check for updates ...
            if (compound.name != currentCompound.name
               || compound.color != currentCompound.color) {
               compoundsDelta[compoundID] = {
                  action: DELTA_ACTION_UPDATE,
                  name  : currentCompound.name,
                  color : currentCompound.color
               }
            }

            // Remove it from the currentCompounds data
            delete currentCompounds[compoundID];
         } else {
            // If the compound is missing in the current state, it's been deleted
            compoundsDelta[compoundID] = {
               action           : DELTA_ACTION_DELETE,
            };
         }
      });

      // Now iterate on the compounds we have in the current state
      Object.keys(currentCompounds).forEach(function (compoundID) {
         compoundsDelta[compoundID] = {
            action: DELTA_ACTION_ADD,
            name  : currentCompounds[compoundID].name,
            color : currentCompounds[compoundID].color
         }
      });

      return compoundsDelta;
   },

   /**
    * Delta computation for parents
    *
    * @returns {Object}
    */
   computeParentsDelta: function(currentParents) {
      var parentsDelta = {};

      // First iterate on the parents we had in the initial state
      Object.keys(impact.initialState.parents).forEach(function(nodeID) {
         var parent = impact.initialState.parents[nodeID];
         if (currentParents.hasOwnProperty(nodeID)) {
            // If the node still have a parrent in the current state
            var currentParent = currentParents[nodeID];

            // Check for updates ...
            if (parent != currentParent) {
               var nodeDetails = nodeID.split(NODE_ID_SEPERATOR);
               parentsDelta[nodeID] = {
                  action   : DELTA_ACTION_UPDATE,
                  parent_id: currentParent,
                  itemtype : nodeDetails[0],
                  items_id : nodeDetails[1]
               }
            }

            // Remove it from the currentParents data
            delete currentParents[nodeID];
         } else {
            // If the parent is missing in the current state, it's been deleted
            var nodeDetails = nodeID.split(NODE_ID_SEPERATOR);
            parentsDelta[nodeID] = {
               action           : DELTA_ACTION_DELETE,
               itemtype : nodeDetails[0],
               items_id : nodeDetails[1]
            };
         }
      });

      // Now iterate on the parents we have in the current state
      Object.keys(currentParents).forEach(function (nodeID) {
         var nodeDetails = nodeID.split(NODE_ID_SEPERATOR);
         parentsDelta[nodeID] = {
            action   : DELTA_ACTION_ADD,
            parent_id: currentParents[nodeID],
            itemtype : nodeDetails[0],
            items_id : nodeDetails[1]
         };
      });

      return parentsDelta;
   },

   /**
    * Compute the delta betwteen the initial state and the current state
    *
    * @returns {Object}
    */
   computeDelta: function () {
      // Store the delta for edges, compounds and parent
      var result = {};

      // Get the current state of the graph
      var currentState = this.getCurrentState();

      // Compute each deltas
      result.edges = this.computeEdgeDelta(currentState.edges);
      result.compounds = this.computeCompoundsDelta(currentState.compounds);
      result.parents = this.computeParentsDelta(currentState.parents);

      return result;
   },

   /**
    * Get the context menu items
    *
    * @returns {Array}
    */
   getContextMenuItems: function(){
      return [
         {
            id: 'goTo',
            content: this.getLocale("goTo"),
            tooltipText: this.getLocale("goTo+"),
            selector: 'node',
            onClickFunction: this.menuOnGoTo
         },
         {
            id: 'showOngoing',
            content: this.getLocale("showOngoing"),
            tooltipText: this.getLocale("showOngoing+"),
            selector: 'node[hasITILObjects=1]',
            onClickFunction: this.menuOnShowOngoing
         },
         {
            id: 'editCompound',
            content: this.getLocale("compoundProperties"),
            tooltipText: this.getLocale("compoundProperties+"),
            selector: 'node:parent',
            onClickFunction: this.menuOnEditCompound
         },
         {
            id: 'removeFromCompound',
            content: this.getLocale("removeFromCompound"),
            tooltipText: this.getLocale("removeFromCompound+"),
            selector: 'node:child',
            onClickFunction: this.menuOnRemoveFromCompound
         },
         {
            id: 'delete',
            content: this.getLocale("delete"),
            tooltipText: this.getLocale("delete+"),
            selector: 'node, edge',
            onClickFunction: this.menuOnDelete
         }
      ];
   },

   /**
    * Build the add node dialog
    *
    * @param {string} itemID
    * @param {string} itemType
    * @param {Object} position x, y
    *
    * @returns {Object}
    */
   getAddNodeDialog: function(itemID, itemType, position) {
      // Build a new graph from the selected node and insert it
      var buttonAdd = {
         text: impact.getLocale("add"),
         click: function() {
            var node = {
               itemType: $(itemID).val(),
               itemID  : $(itemType).val(),
            };
            var nodeID = impact.makeID(NODE, node.itemType, node.itemID);

            // Check if the node is already on the graph
            if (impact.cy.filter('node[id="' + nodeID + '"]')
               .length > 0) {
               alert(impact.getLocale("duplicateAsset"));
               return;
            }

            // Build the new subgraph
            $.when(impact.buildGraphFromNode(node)).then(
               function (graph) {
                  // Insert the new graph data into the current graph
                  impact.insertGraph(graph, {
                     id: nodeID,
                     x: position.x,
                     y: position.y
                  });
                  impact.updateFlags();
                  $(impact.dialogs.addNode.id).dialog("close");
                  impact.setEditionMode(EDITION_DEFAULT);
               },
               function () {
                  // Ajax failed
                  alert(impact.getLocale("unexpectedError"));
               },
            );
         }
      }

      // Exit edit mode
      var buttonCancel = {
         text: impact.getLocale("cancel"),
         click: function() {
            $(this).dialog("close");
            impact.setEditionMode(EDITION_DEFAULT);
         }
      };

      return {
         modal: true,
         position: {
            my: 'center',
            at: 'center',
            of: impact.impactContainer
         },
         buttons: [buttonAdd, buttonCancel]
      };
   },

   /**
    * Build the color picker dialog
    *
    * @param {JQuery} backward
    * @param {JQuery} forward
    * @param {JQuery} both
    *
    * @returns {Object}
    */
   getColorPickerDialog: function(backward, forward, both) {
      var buttonUpdate = {
         text: "Update",
         click: function() {
            impact.setEdgeColors({
               backward: backward.val(),
               forward : forward.val(),
               both    : both.val(),
            });
            impact.updateStyle();
            $(this).dialog( "close" );
         }
      };

      return {
         modal: true,
         width: 'auto',
         position: {
            my: 'center',
            at: 'center',
            of: impact.impactContainer
         },
         draggable: false,
         title: this.getLocale("colorConfiguration"),
         buttons: [buttonUpdate]
      };
   },

   /**
    * Build the export dialog
    *
    * @param {JQuery} format
    * @param {JQuery} transparentBackground
    * @param {JQuery} link
    *
    * @returns {Object}
    */
   getExportDialog: function(format, transparentBackground, link) {
      var exportButton = {
         text: this.getLocale("export"),
         click: function() {
            var exportData = impact.exportGraph(
               format.find("option:selected").val(),
               transparentBackground.is(':checked')
            );
            link.prop('download', exportData.filename);
            link.prop("href", exportData.filecontent);
            link[0].click();
         }
      };

      return {
         modal: true,
         width: 'auto',
         position: {
            my: 'center',
            at: 'center',
            of: impact.impactContainer
         },
         draggable: false,
         title: this.getLocale("export"),
         buttons: [exportButton]
      };
   },

    /**
    * Build the add node dialog
    *
    * @param {string} itemID
    * @param {string} itemType
    * @param {Object} position x, y
    *
    * @returns {Object}
    */
   getOngoingDialog: function(itemID, itemType, position) {
      return {
         title: impact.getLocale("ongoingTickets"),
         modal: true,
         position: {
            my: 'center',
            at: 'center',
            of: impact.impactContainer
         },
         buttons: []
      };
   },

   /**
    * Build the add node dialog
    *
    * @param {string} itemID
    * @param {string} itemType
    * @param {Object} position x, y
    *
    * @returns {Object}
    */
   getEditCompoundDialog: function(compound) {
      // Reset inputs:
      $(impact.dialogs.editCompoundDialog.inputs.name).val(
         compound.data('label')
      );
      $(impact.dialogs.editCompoundDialog.inputs.color).spectrum(
         "set",
         compound.data('color')
      );

      // Save group details
      var buttonSave = {
         text: impact.getLocale("save"),
         click: function() {
            // Save compound name
            compound.data(
               'label',
               $(impact.dialogs.editCompoundDialog.inputs.name).val()
            );

            // Save compound color
            compound.data(
               'color',
               $(impact.dialogs.editCompoundDialog.inputs.color).val()
            );

            // Close dialog
            $(this).dialog("close");
         }
      }

      return {
         title: impact.getLocale("editGroup"),
         modal: true,
         position: {
            my: 'center',
            at: 'center',
            of: impact.impactContainer
         },
         buttons: [buttonSave]
      };
   },

   /**
    * Register the dialogs generated by the backend server
    *
    * @param {string} key
    * @param {string} id
    * @param {Object} inputs
    */
   registerDialog: function(key, id, inputs) {
      impact.dialogs[key]['id'] = id;
      if (inputs) {
         Object.keys(inputs).forEach(function (inputKey){
            impact.dialogs[key]['inputs'][inputKey] = inputs[inputKey];
         });
      }
   },

   /**
    * Register the toolbar elements generated by the backend server
    *
    * @param {string} key
    * @param {string} id
    */
   registerToobar: function(key, id,) {
      impact.toolbar[key] = id;
   },

   /**
    * Create a tooltip for a toolbar's item
    *
    * @param {string} content
    *
    * @returns {Object}
    */
   getTooltip: function(content) {
      return {
         position: {
            my: 'bottom center',
            at: 'top center'
         },
         content: this.getLocale(content),
         style: {
            classes: 'qtip-shadow qtip-bootstrap'
         },
         show: {
            solo: true,
            delay: 100
         },
         hide: {
            fixed: true,
            delay: 100
         }
      };
   },

   /**
    * Initialise variables
    *
    * @param {JQuery} impactContainer
    * @param {string} locales json
    * @param {Object} colors properties: default, forward, backward, both
    * @param {string} startNode
    * @param {string} dialogs json
    * @param {string} toolbar json
    */
   prepareNetwork: function(
      impactContainer,
      locales,
      colors,
      startNode,
      dialogs,
      toolbar) {

      // Set container
      this.impactContainer = impactContainer;

      // Set locales from json
      this.locales = JSON.parse(locales);

      // Init directionVisibility
      this.directionVisibility[FORWARD] = true;
      this.directionVisibility[BACKWARD] = true;

      // Set colors for edges
      this.setEdgeColors(colors);

      // Set start node
      this.startNode = startNode;

      // Register dialogs
      JSON.parse(dialogs).forEach(function(dialog) {
         impact.registerDialog(dialog.key, dialog.id, dialog.inputs);
      });

      // Register toolbars
      JSON.parse(toolbar).forEach(function(element) {
         impact.registerToobar(element.key, element.id);
      });
      this.initToolbar();
   },

   /**
    * Build the network graph
    *
    * @param {string} data (json)
    */
   buildNetwork: function(data) {
      console.log(data);
      this.cy = cytoscape({
         container: this.impactContainer,
         elements : data,
         style    : this.getNetworkStyle(),
         layout   : this.getNetworkLayout(),
      });

      // Store initial data
      this.initialState = this.getCurrentState();

      // Enable context menu
      this.cy.contextMenus({
         menuItems: this.getContextMenuItems(),
         menuItemClasses: [],
         contextMenuClasses: []
      });

      // Enable grid
      this.cy.gridGuide({
         gridStackOrder: 0,
         snapToGridOnRelease: true,
         snapToGridDuringDrag: true,
         gridSpacing: 8,
         drawGrid: true,
         panGrid: true,
      });

      // Register events handlers for cytoscape object
      this.cy.on('mousedown', 'node', this.nodeOnMousedown);
      this.cy.on('mouseup', 'node', this.nodeOnMouseup);
      this.cy.on('mousemove', this.onMousemove);
      this.cy.on('mouseover', this.onMouseover);
      this.cy.on('mouseout', this.onMouseout);
      this.cy.on('click', this.onClick);
      this.cy.on('click', 'edge', this.edgeOnClick);
      this.cy.on('click', 'node', this.nodeOnClick);
      this.cy.on('box', this.onBox);

      // Enter EDITION_DEFAULT mode
      this.setEditionMode(EDITION_DEFAULT);
   },

   /**
    * Load another graph
    *
    * @param {Array} newGraph
    */
   replaceGraph(newGraph) {
      // Remove current graph
      this.cy.remove("");
      this.delta = {edges: {}, compounds: {}, parents: {}};

      // Set the new graph and apply layout to nodes
      var layout = this.cy.add(newGraph).layout(impact.getNetworkLayout());
      layout.run();
   },

   /**
    * Create ID for nodes and egdes
    *
    * @param {number} type (NODE or EDGE)
    * @param {string} a
    * @param {string} b
    *
    * @returns {string|null}
    */
   makeID: function(type, a, b) {
      switch (type) {
         case NODE:
            return a + "::" + b;
         case EDGE:
            return a + "->" + b;
      }

      return null;
   },

   /**
    * Helper to make an ID selector
    * We can't use the short syntax "#id" because our ids contains
    * non-alpha-numeric characters
    *
    * @param {string} id
    *
    * @returns {string}
    */
   makeIDSelector: function(id) {
      return "[id='" + id + "']";
   },

   /**
    * Reload the graph style
    */
   updateStyle: function() {
      this.cy.style(this.getNetworkStyle());
   },

   /**
    * Update the flags of the edges of the graph
    * Explore the graph forward then backward
    */
   updateFlags: function() {
      // Keep track of visited nodes
      var exploredNodes;

      // Set all flag to the default value (0)
      this.cy.edges().forEach(function(edge) {
         edge.data("flag", 0);
      });

      // Run through the graph forward
      exploredNodes = {};
      exploredNodes[this.startNode] = true;
      this.exploreGraph(exploredNodes, FORWARD, this.startNode);

      // Run through the graph backward
      exploredNodes = {};
      exploredNodes[this.startNode] = true;
      this.exploreGraph(exploredNodes, BACKWARD, this.startNode);
   },

   /**
    * Toggle impact/depends visibility
    *
    * @param {*} toToggle
    */
   toggleVisibility: function(toToggle) {
      // Update visibility setting
      impact.directionVisibility[toToggle] = !impact.directionVisibility[toToggle];

      // Compute direction
      var forward = impact.directionVisibility[FORWARD];
      var backward = impact.directionVisibility[BACKWARD];

      if (forward && backward) {
         direction = BOTH;
      } else if (!forward && backward) {
         direction = BACKWARD;
      } else if (forward && !backward) {
         direction = FORWARD;
      } else {
         direction = 0;
      }

      // Hide all nodes
      impact.cy.filter("node").data('hidden', 1);

      impact.cy.filter("edge").forEach(function(edge) {
         // Show/Hide edges according to the direction
         if (edge.data('flag') & direction) {
            edge.data('hidden', 0);

            // If the edge is visible, show the nodes they are connected to it
            var sourceFilter = "node[id='" + edge.data('source') + "']";
            var targetFilter = "node[id='" + edge.data('target') + "']";
            impact.cy.filter(sourceFilter + ", " + targetFilter)
               .data("hidden", 0);
         } else {
            edge.data('hidden', 1);
         }
      });

      // Start node should always be visible
      impact.cy.filter(impact.makeIDSelector(impact.startNode))
         .data("hidden", 0);
   },

   /**
    * Explore a graph in a given direction using recursion
    *
    * @param {Array} exploredNodes
    * @param {number} direction
    * @param {string} currentNodeID
    */
   exploreGraph: function(exploredNodes, direction, currentNodeID) {

      // Depending on the direction, we are looking for edge that either begin
      // from the current node (source) or end on the current node (target)
      var sourceOrTarget;

      // The next node is the opposite of sourceOrTarget : if our node is at
      // the start (source) then the next is at the end (target)
      var nextNode;

      switch (direction) {
         case FORWARD:
            sourceOrTarget = "source";
            nextNode       = "target";
            break;
         case BACKWARD:
            sourceOrTarget = "target";
            nextNode       = "source";
            break;
      }

      // Find the edges connected to the current node
      this.cy.elements('edge[' + sourceOrTarget + '="' + currentNodeID + '"]')
         .forEach(function(edge) {

         // Get target node from computer nextNode att name
         targetNode = edge.data(nextNode);

         // Set flag
         edge.data("flag", direction | edge.data("flag"));

         // Check we haven't go through this node yet
         if(exploredNodes[targetNode] == undefined) {
            exploredNodes[targetNode] = true;
            // Go to next node
            impact.exploreGraph(exploredNodes, direction, targetNode);
         }
      });
   },

   /**
    * Get translated value for a given key
    *
    * @param {string} key
    */
   getLocale: function(key) {
      return this.locales[key];
   },

   /**
    * Ask the backend to build a graph from a specific node
    *
    * @param {Object} node
    * @returns {Array|null}
    */
   buildGraphFromNode: function(node) {
      var dfd = jQuery.Deferred();

      // Request to backend
      $.ajax({
         type: "GET",
         url: CFG_GLPI.root_doc + "/ajax/impact.php",
         dataType: "json",
         data: node,
         success: function(data, textStatus, jqXHR) {
            dfd.resolve(JSON.parse(data));
         },
         error: function (data, textStatus, jqXHR) {
            dfd.reject();
         }
      });

      return dfd.promise();
   },

   /**
    * Insert another new graph into the current one
    *
    * @param {Array} graph
    * @param {Object} startNode data, x, y
    */
   insertGraph: function(graph, startNode) {
      var toAdd = [];

      for (var i=0; i<graph.length; i++) {
         var id = graph[i].data.id;
         // Check that the element is not already on the graph,
         if (this.cy.filter('[id="' + id + '"]').length > 0) {
            continue
         }

         if (id == startNode.id) {
            // Immediatly add starting node at given position
            graph[i].position = {
               x: startNode.x,
               y: startNode.y,
            };
            this.cy.add(graph[i])
         } else {
            // Store others node to add them at once with a layout
            toAdd.push(graph[i]);
         }
      }

      // Add nodes and apply layout
      var eles = this.cy.add(toAdd);
      var layout = eles.layout(impact.getNetworkLayout());

      layout.run();
   },

   /**
    * Set the colors
    *
    * @param {object} colors default, backward, forward, both
    */
   setEdgeColors: function (colors) {
      this.setColorIfExist(DEFAULT, colors.default);
      this.setColorIfExist(BACKWARD, colors.backward)
      this.setColorIfExist(FORWARD, colors.forward)
      this.setColorIfExist(BOTH, colors.both)
   },

   /**
    * Set color if exist
    *
    * @param {object} colors default, backward, forward, both
    */
   setColorIfExist: function (index, color) {
      if (color !== undefined) {
         this.edgeColors[index] = color;
      }
   },

   /**
    * Exit current edition mode and enter a new one
    *
    * @param {number} mode
    */
   setEditionMode: function (mode) {
      // Switching to a mode we are already in -> go to default
      if (this.editionMode == mode) {
         mode = EDITION_DEFAULT
      }

      this.exitEditionMode();
      this.enterEditionMode(mode);
      this.editionMode = mode;
   },

   /**
    * Exit current edition mode
    */
   exitEditionMode: function(mode) {
      switch (this.editionMode) {
         case EDITION_DEFAULT:
            impact.cy.nodes().ungrabify();
            break;

         case EDITION_ADD_NODE:
            $(this.toolbar.addNode).removeClass("active");
            break;

         case EDITION_ADD_EDGE:
            $(impact.toolbar.addEdge).removeClass("active");
            break;

         case EDITION_DELETE:
            this.cy.filter().unselect();
            this.cy.data('todelete', 0);
            $(impact.toolbar.deleteElement).removeClass("active");
            break;

         case EDITION_ADD_COMPOUND:
            impact.cy.panningEnabled(true);
            impact.cy.boxSelectionEnabled(false);
            break;
      }
   },

   /**
    * Enter a new edition mode
    *
    * @param {number} mode
    */
   enterEditionMode: function(mode) {
      switch (mode) {
         case EDITION_DEFAULT:
            this.clearHelpText();
            impact.cy.nodes().grabify();
            $(this.impactContainer).css('cursor', "move");
            break;

         case EDITION_ADD_NODE:
            this.showHelpText("addNodeHelpText");
            $(this.impactContainer).css('cursor', "copy");
            break;

         case EDITION_ADD_EDGE:
            this.showHelpText("addEdgeHelpText");
            $(this.impactContainer).css('cursor', "crosshair");
            break;

         case EDITION_DELETE:
            this.cy.filter().unselect();
            this.showHelpText("deleteHelpText");
            break;

         case EDITION_ADD_COMPOUND:
            impact.cy.panningEnabled(false);
            impact.cy.boxSelectionEnabled(true);
            this.showHelpText("addCompoundHelpText");
            $(this.impactContainer).css('cursor', "crosshair");
            break;
      }
   },

   /**
    * Hide the toolbar and show an help text
    *
    * @param {string} text
    */
   showHelpText: function(text) {
      $(impact.toolbar.tools).hide();
      $(impact.toolbar.helpText).html(this.getLocale(text)).show();
      $(impact.toolbar.cancel).show();
   },

   /**
    * Hide the help text and show the toolbar
    */
   clearHelpText: function() {
      $(impact.toolbar.helpText).hide();
      $(impact.toolbar.tools).show();
      $(impact.toolbar.cancel).hide();
   },

   /**
    * Export the graph in the given format
    *
    * @param {string} format
    * @param {boolean} transparentBackground (png only)
    *
    * @returns {Object} filename, filecontent
    */
   exportGraph: function(format, transparentBackground) {
      switch (format) {
         case 'png':
            return {
               filename: "impact.png",
               filecontent: this.cy.png({
                  bg: transparentBackground ? "transparent" : "white"
               })
            };

         case 'jpeg':
            return {
               filename: "impact.jpeg",
               filecontent: this.cy.jpg()
            };
      }
   },

   /**
    * Get node at target position
    *
    * @param {Object} position x, y
    * @param {function} filter if false return null
    */
   getNodeAt: function(position, filter) {
      var nodes = this.cy.nodes();

      for (var i=0; i<nodes.length; i++) {
         if (nodes[i].boundingBox().x1 < position.x
          && nodes[i].boundingBox().x2 > position.x
          && nodes[i].boundingBox().y1 < position.y
          && nodes[i].boundingBox().y2 > position.y) {
            // Check if the node is excluded
            if (filter(nodes[i])) {
               return nodes[i];
            }
         }
      }

      return null;
   },

    /**
    * Build the ongoing dialog content according to the list of ITILObjects
    *
    * @param {Object} ITILObjects requests, incidents, changes, problems
    *
    * @returns {string}
    */
   buildOngoingDialogContent: function(ITILObjects) {
      return this.listElements("Requests", ITILObjects.requests, "ticket")
         + this.listElements("Incidents", ITILObjects.incidents, "ticket")
         + this.listElements("Changes", ITILObjects.changes , "change")
         + this.listElements("Problems", ITILObjects.problems, "problem");
   },

   /**
    * Build an html list
    *
    * @param {string} title requests, incidents, changes, problems
    * @param {string} elements requests, incidents, changes, problems
    * @param {string} url key used to generate the URL
    *
    * @returns {string}
    */
   listElements: function(title, elements, url) {
      html = "";

      if (elements.length > 0) {
         html += "<h3>" + this.getLocale(title) + "</h3>";
         html += "<ul>";

         elements.forEach(function(element) {
            var link = "./" + url + ".form.php?id=" + element.id;
            html += '<li><a target="_blank" href="' + link + '">' + element.name
               + '</a></li>';
         });
         html += "</ul>";
      }

      return html;
   },

   /**
    * Add a new compound from the selected nodes
    */
   addCompoundFromSelection: _.debounce(function(){
      // Check that there is enough selected nodes
      if (eventData.boxSelected.length < 2) {
         alert(impact.getLocale("notEnoughItems"));
      } else {
         // Create the compound
         var newCompound = impact.cy.add({group: 'nodes'});

         // Set parent for coumpound member
         eventData.boxSelected.forEach(function(ele) {
            ele.move({'parent': newCompound.data('id')});
         });

         // Show edit dialog
         $(impact.dialogs.editCompoundDialog.id).dialog(
            impact.getEditCompoundDialog(newCompound)
         );

         // Back to default mode
         impact.setEditionMode(EDITION_DEFAULT);
      }

      // Clear the selection
      eventData.boxSelected = [];
      impact.cy.filter(":selected").unselect();
   }, 100, false),

   /**
    * Remove an element from the graph
    *
    * @param {object} ele
    */
   deleteFromGraph: function(ele) {
      if (ele.isParent()) {
         // Remove only the parent
         ele.children().move({parent: null});
         ele.remove();

      } else {
         // Remove parent if last child
         if (!ele.isOrphan() && ele.parent().children().length == 1) {
            this.deleteFromGraph(ele.parent());
         }

         // Remove all edges connected to this node from graph and delta
         this.cy.remove(impact.makeIDSelector(ele.data('id')));
      }
   },

   /**
    * Handle global click events
    *
    * @param {JQuery.Event} event
    */
   onClick: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            break;

         case EDITION_ADD_NODE:
            // Click in EDITION_ADD_NODE : add a new node
            $(impact.dialogs.addNode.id).dialog(impact.getAddNodeDialog(
               impact.dialogs.addNode.inputs.itemType,
               impact.dialogs.addNode.inputs.itemID,
               event.position,
            ));
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            break;
      }
   },

   /**
    * Handle click on edge
    *
    * @param {JQuery.Event} event
    */
   edgeOnClick: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            // Remove the edge from the graph
            event.cy.remove(impact.makeIDSelector(this.data('id')));
            break;
      }
   },

   /**
    * Handle click on node
    *
    * @param {JQuery.Event} event
    */
   nodeOnClick: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            impact.deleteFromGraph(event.target);
            break;
      }
   },

   /**
    * Handle end of box selection event
    *
    * @param {JQuery.Event} event
    */
   onBox: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            break;

         case EDITION_ADD_COMPOUND:
            var ele = event.target;
            // Add node to selected list if he is not part of a compound already
            if (ele.isNode() && ele.isOrphan() && !ele.isParent()) {
               eventData.boxSelected.push(ele);
            }
            impact.addCompoundFromSelection();
            break;
      }
   },

   /**
    * Handle mousedown events on nodes
    *
    * @param {JQuery.Event} event
    */
   nodeOnMousedown: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            $(impact.impactContainer).css('cursor', "grabbing");

            // If we are not on a compound node or a node already inside one
            if (event.target.isOrphan() && !event.target.isParent()) {
               eventData.grabNodeStart = event.target;
            }
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            if (!event.target.isParent()) {
               eventData.addEdgeStart = this.data('id');
            }
            break;

         case EDITION_DELETE:
            break;

         case EDITION_ADD_COMPOUND:
            break;
      }
   },

   /**
    * Handle mouseup events on nodes
    *
    * @param {JQuery.Event} event
    */
   nodeOnMouseup: function (event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            $(impact.impactContainer).css('cursor', "grab");

            // Check if we were grabbing a node
            if (eventData.grabNodeStart != null) {
               // Reset eventData for node grabbing
               eventData.grabNodeStart = null;
               eventData.boundingBox = null;
            }

            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            // Exit if no start node
            if (eventData.addEdgeStart == null) {
               return;
            }

            // Reset addEdgeStart
            var startEdge = eventData.addEdgeStart; // Keep a copy to use later
            eventData.addEdgeStart = null;

            // Remove current tmp collection
            event.cy.remove(eventData.tmpEles);
            eventData.tmpEles = null;

            // Option 1: Edge between a node and the fake tmp_node -> ignore
            if (this.data('id') == 'tmp_node') {
               return;
            }

            // Option 2: Edge between two nodes that already exist -> ignore
            var edgeID = impact.makeID(EDGE, startEdge, this.data('id'));
            if (event.cy.filter('edge[id="' + edgeID + '"]').length > 0) {
               return;
            }

            // Option 3: Both end of the edge are actually the same node -> ignore
            if (startEdge == this.data('id')) {
               return;
            }

            // Option 4: Edge between two nodes that does not exist yet -> create it!
            event.cy.add({
               group: 'edges',
               data: {
                  id: edgeID,
                  source: startEdge,
                  target: this.data('id')
               }
            });

            // Update dependencies flags according to the new link
            impact.updateFlags();
            break;

         case EDITION_DELETE:
            break;
      }
   },

   /**
    * Handle mousemove events on nodes
    *
    * @param {JQuery.Event} event
    */
   onMousemove: _.throttle(function(event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            // No action if we are not grabbing a node
            if (eventData.grabNodeStart == null) {
               return;
            }

            // Look for a compound at the cursor position
            var node = impact.getNodeAt(event.position, function(node) {
               return node.isParent();
            });

            if (node) {
               // If we have a bounding box defined, the grabbed node is already
               // being placed into a compound, we need to check if it was moved
               // outside this original bouding box to know if the user is trying
               // to move if away from the compound
               if (eventData.boundingBox != null) {
                  // If the user tried to move out of the compound
                  if (eventData.boundingBox.x1 > event.position.x
                     || eventData.boundingBox.x2 < event.position.x
                     || eventData.boundingBox.y1 > event.position.y
                     || eventData.boundingBox.y2 < event.position.y) {
                     // Remove it from the compound
                     eventData.grabNodeStart.move({parent: null});
                     eventData.boundingBox = null;
                  }
               } else {
                  // If we found a compound, add the grabbed node inside
                  eventData.grabNodeStart.move({parent: node.data('id')});

                  // Store the original bouding box of the compound
                  eventData.boundingBox = node.boundingBox();
               }
            } else {
               // Else; reset it's parent so it can be removed from any temporary
               // compound while the user is stil grabbing
               eventData.grabNodeStart.move({parent: null});
            }

            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            // No action if we are not placing an edge
            if (eventData.addEdgeStart == null) {
               return;
            }

            // Remove current tmp collection
            if (eventData.tmpEles != null) {
               event.cy.remove(eventData.tmpEles);
            }

            var node = impact.getNodeAt(event.position, function(node) {
               var nodeID = node.data('id');

               // Can't link to itself
               if (nodeID == eventData.addEdgeStart) {
                  return false;
               }

               // Can't link to parent
               if (node.isParent()) {
                  return false;
               }

               // The created edge shouldn't already exist
               var edgeID = impact.makeID(EDGE, eventData.addEdgeStart, nodeID);
               if (impact.cy.filter('edge[id="' + edgeID + '"]').length > 0) {
                  return false;
               }

               // The node must be visible
               if (impact.cy.getElementById(nodeID).data('hidden')) {
                  return false;
               }

               return true;
            });

            if (node != null) {
               node = node.data('id');

               // Add temporary edge to node hovered by the user
               eventData.tmpEles = event.cy.add([
                  {
                     group: 'edges',
                     data: {
                        id: impact.makeID(EDGE, eventData.addEdgeStart, node),
                        source: eventData.addEdgeStart,
                        target: node
                     }
                  }
               ]);
            } else {
               // Add temporary edge to a new invisible node at mouse position
               eventData.tmpEles = event.cy.add([
                  {
                     group: 'nodes',
                     data: {
                        id: 'tmp_node',
                     },
                     position: {
                        x: event.position.x,
                        y: event.position.y
                     }
                  },
                  {
                     group: 'edges',
                     data: {
                        id: impact.makeID(
                           EDGE,
                           eventData.addEdgeStart,
                           "tmp_node"
                        ),
                        source: eventData.addEdgeStart,
                        target: 'tmp_node',
                     }
                  }
               ]);
            }
            break;

         case EDITION_DELETE:
            break;
      }
   }, 25),

   /**
    * Handle global mouseover events
    *
    * @param {JQuery.Event} event
    */
   onMouseover: function(event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            if (event.target.data('id') == undefined || !event.target.isNode()) {
               break;
            }
            $(impact.impactContainer).css('cursor', "grab");
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            if (event.target.data('id') == undefined) {
               break;
            }

            $(impact.impactContainer).css('cursor', "default");
            var id = event.target.data('id');

            // Remove red overlay
            event.cy.filter().data('todelete', 0);
            event.cy.filter().unselect();

            // Add red overlay
            event.target.data('todelete', 1);
            event.target.select();

            if (event.target.isNode()){
               var sourceFilter = "edge[source='" + id + "']";
               var targetFilter = "edge[target='" + id + "']";
               event.cy.filter(sourceFilter + ", " + targetFilter)
                  .data('todelete', 1)
                  .select();
            }
            break;
      }
   },

   /**
    * Handle global mouseout events
    *
    * @param {JQuery.Event} event
    */
   onMouseout: function(event) {
      switch (impact.editionMode) {
         case EDITION_DEFAULT:
            $(impact.impactContainer).css('cursor', "move");
            break;

         case EDITION_ADD_NODE:
            break;

         case EDITION_ADD_EDGE:
            break;

         case EDITION_DELETE:
            // Remove red overlay
            $(impact.impactContainer).css('cursor', "move");
            event.cy.filter().data('todelete', 0);
            event.cy.filter().unselect();
            break;
      }
   },

   /**
    * Handle "goTo" menu event
    *
    * @param {JQuery.Event} event
    */
   menuOnGoTo: function(event) {
      window.open(event.target.data('link'), 'blank');
   },

   /**
    * Handle "showOngoing" menu event
    *
    * @param {JQuery.Event} event
    */
   menuOnShowOngoing: function(event) {
      $(impact.dialogs.ongoingDialog.id).html(
         impact.buildOngoingDialogContent(event.target.data('ITILObjects'))
      );
      $(impact.dialogs.ongoingDialog.id).dialog(impact.getOngoingDialog());
   },

   /**
    * Handle "EditCompound" menu event
    *
    * @param {JQuery.Event} event
    */
   menuOnEditCompound: function (event) {
      $(impact.dialogs.editCompoundDialog.id).dialog(
         impact.getEditCompoundDialog(event.target)
      );
   },

    /**
    * Handler for "removeFromCompound" action
    *
    * @param {JQuery.Event} event
    */
   menuOnRemoveFromCompound: function(event) {
      var parent = impact.cy.getElementById(
         event.target.data('parent')
      );

      // Remove node from compound
      event.target.move({parent: null});

      // Destroy compound if only one or zero member left
      if (parent.children().length < 2) {
         parent.children().move({parent: null});
         impact.cy.remove(parent);
      }
   },

   /**
    * Handler for "delete" menu action
    *
    * @param {JQuery.Event} event
    */
   menuOnDelete: function(event){
      impact.deleteFromGraph(event.target);
   },

   /**
    * Set event handler for toolbar events
    */
   initToolbar: function() {
      // Add a new node on the graph
      $(impact.toolbar.addNode).click(function() {
         impact.setEditionMode(EDITION_ADD_NODE);
      });
      $(impact.toolbar.addNode).qtip(this.getTooltip("addNodeTooltip"));

      // Add a new edge on the graph
      $(impact.toolbar.addEdge).click(function() {
         impact.setEditionMode(EDITION_ADD_EDGE);
      });
      $(impact.toolbar.addEdge).qtip(this.getTooltip("addEdgeTooltip"));

      // Add a new compound on the graph
      $(impact.toolbar.addCompound).click(function() {
         impact.setEditionMode(EDITION_ADD_COMPOUND);
      });
      $(impact.toolbar.addCompound).qtip(this.getTooltip("addCompoundTooltip"));

      // Enter delete mode
      $(impact.toolbar.deleteElement).click(function() {
         impact.setEditionMode(EDITION_DELETE);
      });
      $(impact.toolbar.deleteElement).qtip(this.getTooltip("deleteTooltip"));

      // Export graph
      $(impact.toolbar.export).click(function() {
         $(impact.dialogs.exportDialog.id).dialog(impact.getExportDialog(
            $(impact.dialogs.exportDialog.inputs.format),
            $(impact.dialogs.exportDialog.inputs.background),
            $(impact.dialogs.exportDialog.inputs.link)
         ));
      });
      $(impact.toolbar.export).qtip(this.getTooltip("downloadTooltip"));

      // "More" dropdown menu
      $(impact.toolbar.expandToolbar).click(showMenu);

      // Toggle impact visibility
      $(impact.toolbar.toggleImpact).click(function() {
         impact.toggleVisibility(FORWARD);
         $(impact.toolbar.toggleImpact).find('i')
            .toggleClass("fa-eye fa-eye-slash");
      });

      // Toggle depends visibility
      $(impact.toolbar.toggleDepends).click(function() {
         impact.toggleVisibility(BACKWARD);
         $(impact.toolbar.toggleDepends).find('i')
            .toggleClass("fa-eye fa-eye-slash");
      });

      // Color picker
      $(impact.toolbar.colorPicker).click(function() {
         console.log("clicked");
         $(impact.dialogs.configColor.id).dialog(impact.getColorPickerDialog(
            $(impact.dialogs.configColor.inputs.dependsColor),
            $(impact.dialogs.configColor.inputs.impactColor),
            $(impact.dialogs.configColor.inputs.impactAndDependsColor)
         ));
      });

      // Go back to default mode
      $(impact.toolbar.cancel).click(function() {
         impact.setEditionMode(EDITION_DEFAULT);
      });
   }
};

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
   }
}

function hideMenu(e) {
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

