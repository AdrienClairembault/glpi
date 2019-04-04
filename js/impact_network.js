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

// Store user action on the network
// var delta;
// var data;
// var locales;

// Start impact network
function initImpactNetwork (glpiData, glpiLocales, currentNode) {

   // Init global vars
   window.delta = {};
   window.data = glpiData;
   window.locales = {
      default: JSON.parse(glpiLocales)
   };

   // Network container
   var container = document.getElementById("networkContainer");

   var options = {
      manipulation: {
         enabled:          true,
         initiallyActive:  true,
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
      locales: locales,
      locale: "default"
   };

   window.network = new vis.Network(container, data, options);

   // Mode to current node on first drawing
   window.currentNodeFocused = false;
   window.network.on('beforeDrawing', function (){
      if (!window.currentNodeFocused) {
         window.currentNodeFocused = true;
         window.network.focus(currentNode, {
            locked: false
         });
      }
   });

   // Select current node
   window.network.selectNodes([currentNode]);
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

               

               /* We may have new nodes and eges linked to the new node
                 to insert into the graph */
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
   }
}


// Create ID for nodes and egdes
var makeID = function (type, a, b) {
   switch (type) {
      case NODE:
         return a + "::" + b;
      case EDGE:
         return a + "->" + b;
   }

   return null;
}