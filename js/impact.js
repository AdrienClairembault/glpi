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
};

// Constants to represent nodes and edges
var NODE = 1;
var EDGE = 2;

// Constant for graph direction (bitmask)
var FORWARD  = 1;   // 0b01
var BACKWARD = 2;   // 0b10
var BOTH     = 3;   // 0b11

// Constant for graph edition mode
var EDITION_DEFAULT = 1;
var EDITION_ADD_NODE = 2;
var EDITION_ADD_EDGE = 3;

// Load cytoscape
var cytoscape = window.cytoscape;

var impact = {
   // Store the user modification
   delta: {},

   // Locales for the labels
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
   editionMode: EDITION_DEFAULT,

   // Start node of the graph
   startNode: null,

   /**
    * Get network style
    *
    * @returns {Array}
    */
   getNetworkStyle: function() {
      return [
         {
            selector: 'node[image]',
            style: {
               'label'             : 'data(label)',
               'shape'             : 'rectangle',
               'background-color'  : '#666',
               'background-image'  : 'data(image)',
               'background-fit'    : 'contain',
               'background-opacity': '0',
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
         name: 'cose',
         // transform: function(node, position) {
         //    var sin = Math.sin(90);
         //    var cos = Math.cos(90);

         //    return {
         //       x: position.x * cos - position.y * sin,
         //       y: position.y * cos + position.x * sin
         //    };
         // }
      };
   },

   /**
    * Initialise variables
    *
    * @param {JQuery} impactContainer
    * @param {string} locales (json)
    * @param {Object} colors properties: default, forward, backward, both
    * @param {string} startNode
    */
   prepareNetwork: function(impactContainer, locales, colors, startNode) {
      // Set container
      this.impactContainer = impactContainer;

      // Set locales from json
      this.locales = JSON.parse(locales);

      // Init directionVisibility
      this.directionVisibility[FORWARD] = true;
      this.directionVisibility[BACKWARD] = true;

      // Set colors for edges
      this.edgeColors[0]         = colors.default;
      this.edgeColors[FORWARD]   = colors.forward;
      this.edgeColors[BACKWARD]  = colors.backward;
      this.edgeColors[BOTH]      = colors.both;

      // Set start node
      this.startNode = startNode;
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

      // Register events handlers
      this.cy.on('mousedown', 'node', this.nodeOnMousedown);
      this.cy.on('mouseup', 'node', this.nodeOnMouseup);
      this.cy.on('mousemove', this.onMousemove);
      this.cy.on('click', this.onClick);
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
    * Update the delta to be sent to the backend
    *
    * @param {string} action
    * @param {string} edgeID
    */
   updateDelta: function(action, edgeID) {
      var nodesID = edgeID.split('->');

      // Remove useless changes (add + delete the same edge)
      if (this.delta.hasOwnProperty(edgeID)) {

         if (this.delta[edgeID] == action) {
            // Duplicate delta, should not be possible, ignore it
            return;
         } else {
            // An edge was added then delete : remove the delta
            delete this.delta[edgeID];
            return;
         }
      }

      var source = nodesID[0].split("::");
      var impacted = nodesID[1].split("::");

      this.delta[edgeID] = {
         action           : action,
         itemtype_source  : source[0],
         items_id_source  : source[1],
         itemtype_impacted: impacted[0],
         items_id_impacted: impacted[1]
      };
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
    * Get the node selected in the Add Node dialog
    *
    * @returns {Object}
    */
   getAddNodeDialogSelectedNode: function() {
      return {
         'itemType': $("select[name=item_type] option:selected").val(),
         'itemID'  : $("select[name=item_id] option:selected").val()
      };
   },

   /**
    * Ask the backend to build a graph from a specific node
    *
    * @param {Object} node
    * @returns {Array|null}
    */
   buildGraphFromNode: function(node) {
      var graph = null;

      // Request to backend
      $.ajax({
         type: "POST",
         url: CFG_GLPI.root_doc + "/ajax/impact.php",
         dataType: "json",
         data: node,
         success: function(data, textStatus, jqXHR) {
            graph = data;
         },
      });

      return graph;
   },

   insertGraph: function(){

   },

   /**
    * Handle click events
    *
    * @param {JQuery.Event} event
    */
   onClick: function (event) {
      // Click in EDITION_ADD_NODE : add a new node
      if (impact.editionMode == EDITION_ADD_NODE) {
         $( "#addNodedialog" ).dialog({
            modal: true,
            buttons: [
               {
                  text: impact.getLocale("add"),
                  // Build a new graph from the selected node and insert it
                  click: function() {
                     var node = impact.getAddNodeDialogSelectedNode();
                     var nodeID = impact.makeID(NODE, node.itemType, node.itemID);

                     // Check if the node is already on the graph
                     if (event.cy.filter('node[id="' + nodeID + '"]')
                        .length > 0) {
                        alert(getLocale("duplicateAsset"));
                        return;
                     }

                     // Build the new subgraph
                     var graph = impact.buildGraphFromNode(node);

                     // Ajax call failed in buildGraphFromNode
                     if (graph == null) {
                        alert(getLocale("unexpectedError"));
                        $(this).dialog("close");
                        return;
                     }

                     // Insert the new graph data into the current graph
                     impact.insertGraph(graph);
                     impact.updateFlags();

                     $(this).dialog("close");
                  }
               },
               {
                  text: impact.getLocale("cancel"),
                  // Cancel
                  click: function() {
                     $(this).dialog("close");
                  }
               }
            ]
         });

         console.log("New node at " + event.position.x + ";" + event.position.y);
         event.cy.add({
            group: 'nodes',
            data: {
               id: 'node ' + event.position.x + ";" + event.position.y,
            },
            position: {
               x: event.position.x,
               y: event.position.y
            }
         });
      }
   },

   /**
    * Handle mouse down events on nodes
    * Used by the new edge action
    *
    * @param {JQuery.Event} event
    */
   nodeOnMousedown: function (event) {
      if (impact.editionMode == EDITION_ADD_EDGE) {
         eventData.addEdgeStart = this.data('id');
      }
   },

   /**
    * Handle mouse down events on nodes
    * Used by the new edge action
    *
    * @param {JQuery.Event} event
    */
   nodeOnMouseup: function (event) {
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

      // Option 1: Edge between a node and nothing -> ignore
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
      impact.updateDelta("add", edgeID);

      // Update dependencies flags according to the new link
      impact.updateFlags();
   },

   /**
    * Handle mouse move events on nodes
    * Used by the new edge action
    *
    * @param {JQuery.Event} event
    */
   onMousemove: function (event) {
      // No action if we are not placing an edge
      if (eventData.addEdgeStart == null) {
         return;
      }

      // Remove current tmp collection
      if (eventData.tmpEles != null) {
         event.cy.remove(eventData.tmpEles);
      }

      // Check if there is a node at the current mouse position
      // This node can't be the starting node or a node already linked to us
      var nodes = event.cy.nodes();
      var node = null;

      for (var i=0; i<nodes.length; i++) {
         if (nodes[i].boundingBox().x1 < event.position.x
            && nodes[i].boundingBox().x2 > event.position.x
            && nodes[i].boundingBox().y1 < event.position.y
            && nodes[i].boundingBox().y2 > event.position.y
            && nodes[i].id() != eventData.addEdgeStart) {
               var edgeID = impact.makeID(
                  EDGE,
                  eventData.addEdgeStart,
                  nodes[i].id()
               );
               if (event.cy.filter('edge[id="' + edgeID + '"]').length > 0) {
                  break;
               }

               // Correct node found, set node id
               node = nodes[i].id();
               break;
            }
      }

      if (node != null) {
         // Add temporary edge to node at mouse position
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
                  id: impact.makeID(EDGE, eventData.addEdgeStart, "tmp_node"),
                  source: eventData.addEdgeStart,
                  target: 'tmp_node',
               }
            }
         ]);
      }
   },
};

// Impact toolbar
$(document).ready(function() {

   /**
    * Add a new node on the graph
    */
   $("#add_node").click(function() {
      if (impact.editionMode == EDITION_DEFAULT) {
         // Enter add edge mode and disable node dragging
         impact.editionMode = EDITION_ADD_NODE;
      } else {
         // Exit add node
         impact.editionMode = EDITION_DEFAULT;
      }
   });


   /**
    * Add a new edge on the graph
    */
   $("#add_edge").click(function() {
      if (impact.editionMode == EDITION_DEFAULT) {
         // Enter add edge mode and disable node dragging
         impact.editionMode = EDITION_ADD_EDGE;
         impact.cy.nodes().ungrabify();
      } else {
         // Exit add edge
         impact.editionMode = EDITION_DEFAULT;
         impact.cy.nodes().grabify();
      }
   });
});