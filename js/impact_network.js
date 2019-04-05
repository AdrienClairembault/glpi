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

const NODE = 1;
const EDGE = 2;

const FORWARD = 1;
const BACKWARD = 2;
const BOTH = 3;

const IMPACT_COLOR =  'red';
const DEPENDS_COLOR =  'navy';
const IMPACT_AND_DEPENDS_COLOR =  'purple';
const DEFAUT_COLOR =  'black';

// Start impact network
function initImpactNetwork (glpiLocales, startNode, defautView) {

   // Init global vars
   window.delta = {};
   window.locales = {
      default: JSON.parse(glpiLocales)
   };
   window.startNode = startNode;
   window.graphs = {};
   window.editMode = false;
   window.colorize = {};
   window.colorize[FORWARD] = true;
   window.colorize[BACKWARD] = true;

   var toBeLoaded = [BOTH, FORWARD, BACKWARD];
   var calls = [];

   toBeLoaded.forEach(function(item) {
      loadData(item, calls);
   });

   // Create network after all the graphs are loaded
   $.when.apply($, calls).then(function() {
      createNetwork(direction);
  });
}

function loadData(direction, calls) {
   startNodeDetails = window.startNode.split('::');

   calls.push($.ajax({
      type: "POST",
      url: "../ajax/impact.php",
      data: {
         itemType:   startNodeDetails[0],
         itemID:     startNodeDetails[1],
         direction:  direction
      },
      success: function(data, textStatus, jqXHR) {
         window.graphs[direction] = data;
      },
      dataType: "json"
   }));
}

function createNetwork (direction) {
   // Network container
   var container = document.getElementById("networkContainer");

   var options = {
      manipulation: {
         enabled:          true,
         initiallyActive:  false,
         addNode:          addNodeHandler,
         addEdge:          addEdgeHandler,
         editEdge:         editEdgeHandler,
         deleteNode:       deleteHandler,
         deleteEdge:       deleteHandler
      },
      physics: {
         enabled: true,
         maxVelocity: 5,
         minVelocity: 0.1
      },
      edges: {
         color: {
            inherit: false
         }
      },
      locales: locales,
      locale: "default"
   };

   window.data = {
      nodes: new vis.DataSet(window.graphs[direction]['nodes']),
      edges: new vis.DataSet(window.graphs[direction]['edges'])
   };
   window.network = new vis.Network(container, window.data, options);

   // Mutation observer
   var config = { attributes: true, childList: true, subtree: true };
   var callback = function(mutationsList, observer) {
      // Enter edit mode
      if (!window.editMode && $(".vis-close:visible").length == 1) {
         window.editMode = true;
         // Force to "both" graph
         if ($('select[name=direction] option:selected').val() != BOTH) {
            $('select[name=direction]').val(BOTH).change();
         }
         // Disable graph selection
         $('select[name=direction]').prop('disabled', true);
      }

      // Exit edit mode
      else if (window.editMode && $(".vis-close:visible").length == 0) {
         window.editMode = false;
         // Enable graph selection
         $('select[name=direction]').prop('disabled', false);
      }
  };
  var observer = new MutationObserver(callback);
  observer.observe(container, config);

   selectFirstNode();
   updateGraph();
}

function switchGraph(direction) {
   window.data.edges.clear();
   window.data.nodes.clear();
   window.data.nodes.add(window.graphs[direction].nodes);
   window.data.edges.add(window.graphs[direction].edges);
   selectFirstNode();
   updateGraph();
}

function applyColors() {
   edges = window.data.edges.get();

   edges.forEach(function(edge){
      var color;

      // Filter on bitmast if forward links are hidden
      if (!window.colorize[FORWARD] && edge.flag & FORWARD) {
         edge.flag = edge.flag - FORWARD;
      }

      // Filter on bitmast if backward links are hidden
      if (!window.colorize[BACKWARD] && edge.flag & BACKWARD) {
         edge.flag = edge.flag - BACKWARD;
      }

      switch (edge.flag) {
         case FORWARD:
            color = IMPACT_COLOR;
            break;
         case BACKWARD:
            color = DEPENDS_COLOR;
            break;
         case BOTH:
            color = IMPACT_AND_DEPENDS_COLOR;
            break;
         default:
            color = DEFAUT_COLOR;
      }

      window.data.edges.update({
         id: edge.id,
         color: {
            color: color,
            highlight: color
         }
      });
   });
}

function selectFirstNode(){
   // Move the "camero " to the current node, doesn't always work ...
   // Todo : fix or remove ? do we really need to center the camera on the
   // current node ? It looks bad on small graph but may be usefull on big ones
   // -> currently disabled
   // window.firstLoad = false;
   // window.network.on('afterDrawing', function (){
   //    if (!window.firstLoad) {
   //       window.firstLoad = true;
   //       window.network.focus(window.startNode, {
   //          locked: false
   //       });
   //    }
   // });

   // Select current node
   window.network.selectNodes([window.startNode]);
}

function getLocale(key) {
   return window.locales['default'][key];
}

// Update the delta
function updateDelta (action, edge) {
   var key = edge.from + "->" + edge.to;

   // Remove useless changes (add + delete the same edge)
   if (window.delta.hasOwnProperty(key)) {
      delete delta[key];
      return;
   }

   var source = edge.from.split("::");
   var impacted = edge.to.split("::");

   window.delta[key] = {
      action:              action,
      source_asset_type:   source[0],
      source_asset_id:     source[1],
      impacted_asset_type: impacted[0],
      impacted_asset_id:   impacted[1]
   };
}

// Check if a new edge can be added to the graph
function canAddEdge(edge) {
   // A node shouldn't be linked to itself
   if (edge.to == edge.from) {
      alert(getLocale("linkToSelf"));
      return false;
   }

   // There should be no duplicates
   if (window.data.edges.get(edge.id) !== null) {
      alert(getLocale("duplicateEdge"));
      return false;
   }

   return true
}

// Handler for when a node is added
var addNodeHandler = function (node, callback) {
   // Dialog to pick the item type and id
   $( "#addNodedialog" ).dialog({
      modal: true,
      buttons: [
         {
            text: "Add",
            click: function() {
               var itemType = $("select[name=item_type] option:selected").val();
               var itemID = $("select[name=item_id] option:selected").val();

               node.id = makeID(NODE, itemType, itemID);
               node.label = $("select[name=item_id] option:selected").text();

               // Check for existing node
               if (data.nodes.get(node.id) !== null) {
                  alert(getLocale("duplicateAsset"));
                  return;
               }

               /* We may have new nodes and eges linked to the node that we are
                 inserting into the graph, let's check by building a new graph
                 from this node */
               $.ajax({
                  type: "POST",
                  url: "../ajax/impact.php",
                  data: {
                     itemType:   itemType,
                     itemID:     itemID
                  },
                  success: function(data, textStatus, jqXHR) {
                     // Add the node
                     callback(node);

                     // Add new nodes
                     var newNodes = [];
                     data.nodes.forEach(function(newNode) {
                        newNodes.push(newNode);
                     });
                     window.data.nodes.update(newNodes);

                     // Add new edges
                     var newEdges = [];
                     data.edges.forEach(function(newEdge) {
                        newEdges.push(newEdge);
                     });
                     window.data.edges.update(newEdges);
                  },
                  error: function(data, textStatus, jqXHR) {
                    alert(getLocale("unexpectedError"));
                  },
                  dataType: "json"
               });

               $( this ).dialog( "close" );
            }
         },
         {
            text: "Cancel",
            click: function() {
               $( this ).dialog( "close" );
            }
         }
      ]
   });
};

// Handler for when an edge is added to the graph
var addEdgeHandler = function(edge, callback) {
   edge.id = makeID(EDGE, edge.from, edge.to);
   edge.arrows = "to";

   if (canAddEdge(edge)) {
      updateDelta("add", edge);
      callback(edge);
      updateGraph();
   }
}

// Handler called when a node or an edge is deleted
var deleteHandler = function (deleteData, callback) {
   // Data contains the list of node + edges deleted

   // Remove each edges from delta
   deleteData.edges.forEach(function (edgeID){
      // Get edge and update delta
      egde = data.edges.get(edgeID);
      updateDelta("delete", egde);
   });

   callback(deleteData);
   updateGraph();
}

var editEdgeHandler = function(edge, callback) {
   console.log(edge);
   // edge.id = makeID(EDGE, edge.from, edge.to);
   // edge.arrows = "to";
   callback(null);

   var newEdge = {
      id: makeID(EDGE, edge.from, edge.to),
      from: edge.from,
      to: edge.to,
      arrows: "to"
   };

   if (canAddEdge(newEdge)) {
      // Delete current edge
      updateDelta("delete", window.data.edges.get(edge.id)); // need old values
      window.data.edges.remove(edge.id);

      // Add new edge
      updateDelta("add", newEdge);
      window.data.edges.add(newEdge);

      // Update colors
      updateGraph();
   }
}


// Create ID for nodes and egdes
function makeID (type, a, b) {
   switch (type) {
      case NODE:
         return a + "::" + b;
      case EDGE:
         return a + "->" + b;
   }

   return null;
}

// Export (to png for now, we need another lib for pdf)
function exportCanvas() {
   var img = window.$("#networkContainer canvas")
      .get(0)
      .toDataURL("image/octet-stream");
   $("#export_link").prop("href", img);
}

function buildFlags() {
   var passed_nodes;

   window.data.edges.get().forEach(function(edge) {
      window.data.edges.update({
         id: edge.id,
         flag: 0
      });
   });

   passed_nodes = {};
   passed_nodes[window.startNode] = true;
   buildFlagsFromCurrentNode(
      passed_nodes,
      FORWARD,
      window.startNode
   );

   passed_nodes = {};
   passed_nodes[window.startNode] = true;
   buildFlagsFromCurrentNode(
      passed_nodes,
      BACKWARD,
      window.startNode
   );

    console.log(window.data.edges.get());
}

function buildFlagsFromCurrentNode(nodes, direction, currentNodeID) {
   // Find the edges connected to the current node
   window.data.edges.get().forEach(function(edge) {
      var str = "";
      var node_target = "";
      switch (direction) {
         case FORWARD:
            str = makeID(EDGE, currentNodeID, "");
            node_target = edge.to;
            break;
         case BACKWARD:
            str = makeID(EDGE, "", currentNodeID);
            node_target = edge.from;
            break;
      }

      // Check if edge match with our node
      if (edge.id.indexOf(str) === -1) {
         return;
      }

      // Set flag
      window.data.edges.update({
         id: edge.id,
         flag: direction | edge.flag
      });

      // Check we haven't go through this node yet
      if(nodes[node_target] == undefined) {
         nodes[node_target] = true;
         // Go to next node
         buildFlagsFromCurrentNode(nodes, direction, node_target);
      }
   });
}

// Toggle the color global variables
function toggleColors(direction, enable) {
   window.colorize[direction] = enable;
   applyColors();
}

// Update the client side calculations
// For now this only concers the edges's colors
function updateGraph() {
   buildFlags();
   applyColors();
}