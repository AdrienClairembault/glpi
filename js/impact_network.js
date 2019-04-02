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
var delta;

// Start impact network
function initImpactNetwork (data) {

   // Network container
   var container = document.getElementById("networkContainer");
   
   // Empty delta
   delta = {}; 

   var options = {
      manipulation: {
         enabled:          true,
         initiallyActive:  true,
         addNode:          addNodeHandler,
         deleteNode:       deleteNodeHandler,
         addEdge:          addEdgeHandler,
         deleteEdge:       deleteEdgeHandler
      },
      locale: "en"
   };

   var network = new vis.Network(container, data, options);
}

// Update the delta
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

// Handler for when a node is added
var addNodeHandler = function (node, callback) {
   // Dialog to pick the item type and id
   $( "#addNodedialog" ).dialog({
      modal: true,
      buttons: [
         {
            text: "Add",
            click: function() {
               node.id = makeID(
                  NODE,
                  $("select[name=item_type] option:selected").val(),
                  $("select[name=item_id] option:selected").val()
               );
               node.label = $("select[name=item_id] option:selected").text();

               // Check for existing node
               if (data.node.get(node.id) !== null) {
                  alert("This node already exist");
                  return;
               }
                  
               // Add the node
               callback(node);
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

// Handler for when a node is deleted
var deleteNodeHandler = function (node, callback) {
   // Update delta for each edge in the node
   node.edges.forEach(function (edgeID){
      // Get edge and update delta
      egde = data.edges.get(edgeID);
      updateDelta("delete", egde);
   });
   callback(node);
};

// Handler for when an edge is added to the graph
var addEdgeHandler = function(edge, callback) {   
   // We don't allow edge to the same node
   if (edge.to == edge.from) {
      alert("Can't link a node to itself");
      return;
   }

   edge.id = makeID(EDGE, edge.to, edge.from);
   edge.arrows = "to";

   // Check for existing node
   if (data.edges.get(edge.id) !== null) {
      alert("An identical link already exist between theses two nodes");
      return;
   }

   updateDelta("add", edge);
   callback(edge);
}

// Handler for when an edge is deleted
var deleteEdgeHandler = function(edge, callback) {
   // Get edge
   edge = data.edges.get(edge.edges[0]);
   updateDelta("delete", realedgeEdge);
   callback(edge);
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