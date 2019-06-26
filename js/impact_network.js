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

var NODE = 1;
var EDGE = 2;

// Start impact network
function initImpactNetwork (glpiLocales, startNode) {

   /*
    * Init global vars
    */

   // Store the user modification
   window.delta = {};

   // Store the translations
   window.locales = {
      default: JSON.parse(glpiLocales)
   };

   // The current item from which the graph was built
   window.startNode = startNode;

   // Store the graph
   window.data = {};

   // Check if the graph is in edit mode
   window.editMode = false;

   // Store if the different direction of the graph should be colorized
   window.colorize = {};
   window.colorize[FORWARD] = true;
   window.colorize[BACKWARD] = true;

   // Store default colors
   window.colors = {};
   window.colors[0]         = 'black';
   window.colors[FORWARD]   = IMPACT_COLOR;
   window.colors[BACKWARD]  = DEPENDS_COLOR;
   window.colors[BOTH]      = IMPACT_AND_DEPENDS_COLOR;

   // Store if the different direction of the graph should be colorized
   window.visibility = {};
   window.visibility[FORWARD] = true;
   window.visibility[BACKWARD] = true;

   // Get start node type and id
   var startNodeDetails = window.startNode.split('::');

   // Load the graph
   $.ajax({
      type: "POST",
      url: CFG_GLPI.root_doc + "/ajax/impact.php",
      data: {
         itemType:   startNodeDetails[0],
         itemID:     startNodeDetails[1],
      },
      success: function(data, textStatus, jqXHR) {
         window.data = data;
         createNetwork();
         addCustomOptions();
      },
      dataType: "json"
   });
}

function updateEditLabel() {
   var editLabel = '<i class="fas fa-pencil-alt fa-impact-manipulation"></i>&nbsp;' + getLocale("edit");
   $("div.vis-edit-mode div.vis-label").eq(0).html(editLabel);
}

function addCustomOptions() {
   // Add fa icon to edit
   updateEditLabel();

   // Offset from previous button
   var offset = 15 + $("div.vis-edit-mode").eq(0)
      .find('div.vis-button.vis-edit.vis-edit-mode')[0]
      .getBoundingClientRect()
      .width;

   // Custom button 1 : Toggle depends div
   $("div.vis-edit-mode").eq(0).after(
      '<div id="toggleDependsDiv" class="vis-edit-mode" style="display: block; left: ' + offset + 'px">' +
      '   <div class="vis-button vis-edit vis-edit-mode" style="touch-action: pan-y; -moz-user-select: none;">' +
      '      <div class="vis-label">' +
      '         <i class="fas fa-toggle-on fa-impact-manipulation"></i><i class="fas fa-toggle-off fa-impact-manipulation" style="display:none;"></i>&nbsp;' + getLocale("showDepends").replace(" ", " ") +
      '      </div>' +
      '   </div>' +
      '</div>'
   );

   $("#toggleDependsDiv").click(function() {
      $("#toggleDependsDiv .fas").toggle();
      window.visibility[FORWARD] = !window.visibility[FORWARD];
      updateVisibility();
   });

   // Offset from previous button
   offset += 15 + $("div.vis-edit-mode").eq(2)
      .find('div.vis-button.vis-edit.vis-edit-mode')[0]
      .getBoundingClientRect()
      .width;

   // Custom button 2 : Toggle depends div
   $("div.vis-edit-mode").eq(2).after(
      '<div id="toggleImpactDiv" class="vis-edit-mode" style="display: block; left: ' + offset + 'px">' +
      '   <div class="vis-button vis-edit vis-edit-mode" style="touch-action: pan-y; -moz-user-select: none;">' +
      '      <div class="vis-label">' +
      '         <i class="fas fa-toggle-on fa-impact-manipulation"></i><i class="fas fa-toggle-off fa-impact-manipulation" style="display:none;"></i>&nbsp;' + getLocale("showImpact").replace(" ", " ") +
      '      </div>' +
      '   </div>' +
      '</div>'
   );

   $("#toggleImpactDiv").click(function() {
      $("#toggleImpactDiv .fas").toggle();
      window.visibility[BACKWARD] = !window.visibility[BACKWARD];
      updateVisibility();
   });

   // Offset from previous button
   offset += 15 + $("div.vis-edit-mode").eq(4)
      .find('div.vis-button.vis-edit.vis-edit-mode')[0]
      .getBoundingClientRect()
      .width;

   // Custom button 3 : color picker
   $("div.vis-edit-mode").eq(4).after(
      '<div id="configColorsDiv" class="vis-edit-mode" style="display: block; left: ' + offset + 'px">' +
      '   <div class="vis-button vis-edit vis-edit-mode" style="touch-action: pan-y; -moz-user-select: none;">' +
      '      <div class="vis-label">' +
      '         <i class="fas fa-palette fa-impact-manipulation"></i>&nbsp;' + getLocale("colorConfiguration").replace(" ", " ") +
      '      </div>' +
      '   </div>' +
      '</div>'
   );

   $("#configColorsDiv").click(function() {
      $("#configColorDialog").dialog({
         modal: true,
         width: 'auto',
         draggable: false,
         title: getLocale("colorConfiguration"),
         buttons: [{
            text: "Update",
            click: function() {
               setColor(BACKWARD, $('input[name=depends_color]').val());
               setColor(FORWARD, $('input[name=impact_color]').val());
               setColor(BOTH, $('input[name=impact_and_depends_color]').val());
               $(this).dialog( "close" );
            }
         }]
      });
   });

   // Offset from previous button
   offset += 15 + $("div.vis-edit-mode").eq(6)
      .find('div.vis-button.vis-edit.vis-edit-mode')[0]
      .getBoundingClientRect()
      .width;

   // Custom button 4 : export
   $("div.vis-edit-mode").eq(6).after(
      '<div id="exportDiv" class="vis-edit-mode" style="display: block; left: ' + offset + 'px">' +
      '   <div class="vis-button vis-edit vis-edit-mode" style="touch-action: pan-y; -moz-user-select: none;">' +
      '      <div class="vis-label">' +
      '         <i class="fas fa-download fa-impact-manipulation"></i>&nbsp;' + getLocale("export").replace(" ", " ") +
      '      </div>' +
      '   </div>' +
      '</div>'
   );

   $("#exportDiv").click(function() {
      $("#exportDialog").dialog({
         modal: true,
         width: 'auto',
         draggable: false,
         title: getLocale("export"),
         buttons: [{
            text: "Export",
            click: function() {
               var format = $('select[name=\"impact_format\"] option:selected')
                  .val();
               $('#export_link').prop('download', 'impact.' + format);
               $("#export_link").prop("href", exportCanvasToURI(
                  window.$("#networkContainer canvas").get(0),
                  format
               ));
               $('#export_link')[0].click();
            }
         }]
      });
   });
}

function exportCanvasToURI(canvas, format) {
   switch (format) {
      // No change needed, return the canvas as it is
      case 'png':
         return canvas.toDataURL("image/png");
      // We need to paste the canvas on a white background
      case 'jpeg':
         var newCanvas = canvas.cloneNode(true);
         var ctx = newCanvas.getContext('2d');
         ctx.fillStyle = "#FFF";
         ctx.fillRect(0, 0, newCanvas.width, newCanvas.height);
         ctx.drawImage(canvas, 0, 0);
         return newCanvas.toDataURL("image/jpeg");
   }
}

function updateVisibility() {
   if (window.visibility[FORWARD] && window.visibility[BACKWARD]) {
      direction = BOTH;
   } else if (!window.visibility[FORWARD] && window.visibility[BACKWARD]) {
      direction = FORWARD;
   } else if (window.visibility[FORWARD] && !window.visibility[BACKWARD]) {
      direction = BACKWARD;
   } else {
      direction = 0;
   }

   hideDisabledNodes(direction);
}

// Create the vis.js network
function createNetwork () {
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
      nodes: new vis.DataSet(window.data['nodes']),
      edges: new vis.DataSet(window.data['edges'])
   };
   window.network = new vis.Network(container, window.data, options);

   /*
    * Mutation observer used to detect when we enter or exit the edit mode
    */
   var config = { attributes: true, childList: true, subtree: true };
   var callback = function(mutationsList, observer) {
      // Enter edit mode
      if (!window.editMode && $(".vis-close:visible").length == 1) {
         window.editMode = true;

         // Reset visibily toggles as we show the entire graph in edit mode
         $("#toggleDependsDiv .fa-toggle-on").show();
         $("#toggleDependsDiv .fa-toggle-off").hide();
         $("#toggleImpactDiv .fa-toggle-on").show();
         $("#toggleImpactDiv .fa-toggle-off").hide();
         window.visibility[FORWARD] = true;
         window.visibility[BACKWARD] = true;

         // Hide custom buttons
         $("#toggleDependsDiv").hide();
         $("#toggleImpactDiv").hide();
         $("#configColorsDiv").hide();
         $("#exportDiv").hide();

         // Force total visibility
         if (!$('#showDepends').prop('checked')) {
            $('#showDepends').prop('checked', true);
         }
         if (!$('#showImpacted').prop('checked')) {
            $('#showImpacted').prop('checked', true);
         }

         $('#showDepends').prop('disabled', true);
         $('#showImpacted').prop('disabled', true);
         updateVisibility();
      }

      // Exit edit mode
      else if (window.editMode && $(".vis-close:visible").length == 0) {
         window.editMode = false;

         // Show custom buttons
         $("#toggleDependsDiv").show();
         $("#toggleImpactDiv").show();
         $("#configColorsDiv").show();
         $("#exportDiv").show();

         // Enable visibility changes
         $('#showDepends').prop('disabled', false);
         $('#showImpacted').prop('disabled', false);

         // Our custom edit label need to be rebuilt
         updateEditLabel();
      }
   };
   var observer = new MutationObserver(callback);
   observer.observe(container, config);

   // Show list of ongoing item on click
   window.network.on("click", function (params) {
      var targetNode = this.getNodeAt(params.pointer.DOM);
      if (targetNode == undefined) {
         if ($('#ticketsDialog').hasClass('ui-dialog-content')) {
            $( "#ticketsDialog" ).dialog('close');
         }
         return;
      }
      targetNode = window.data.nodes.get(targetNode);

      if (targetNode.incidents.length > 0 || targetNode.requests.length > 0 ||
          targetNode.problems.length > 0 || targetNode.changes.length > 0
         ) {

         // TODO : this dialog should open where the cursor is
         // not working since jquery-ui update
         $( "#ticketsDialog" ).dialog({
            // position:  {
            //    my: "center",
            //    of: params.event
            // },
            width: 'auto',
            draggable: false,
            title: targetNode.label.substring(0, targetNode.label.length - 2),
         });

         // Set dialog content
         $( "#ticketsDialog" ).html(printTicketsDialog(targetNode));
      }
      else {
         // Click on a node without ongoing tickets, close dialog
         if ($('#ticketsDialog').hasClass('ui-dialog-content')) {
            $( "#ticketsDialog" ).dialog('close');
         }
      }
   });

   selectFirstNode();
   applyColors();
}

// Print the ticketsDialog
function printTicketsDialog(targetNode) {
   var html = "";

   html += listElements("Incidents", targetNode.incidents, "ticket");
   html += listElements("Requests", targetNode.requests, "ticket");
   html += listElements("Changes", targetNode.changes , "change");
   html += listElements("Problems", targetNode.problems, "problem");

   return html;
}

// List elements (request, incidents, problems or changes) in the ticketsDialog
function listElements(title, elements, url) {
   html = "";

   if (elements.length > 0) {
      html += "<h3>" + getLocale(title) + "</h3>";

      elements.forEach(function(element) {
         var link = "./" + url + ".form.php?id=" + element.id;
         html += '<a target="_blank" href="' + link + '">' + element.name + '</a><br>';
      });
   }

   return html;
}

// Highlight "impact" and/or "depends" relations if enabled
function applyColors() {
   window.data.edges.get().forEach(function(edge){
      var color;

      // Remove "impact" highlights if disabled
      if (!window.colorize[FORWARD] && edge.flag & FORWARD) {
         edge.flag = edge.flag - FORWARD;
      }

      // Remove "depends" highlights if disabled
      if (!window.colorize[BACKWARD] && edge.flag & BACKWARD) {
         edge.flag = edge.flag - BACKWARD;
      }

      color = window.colors[edge.flag];

      window.data.edges.update({
         id: edge.id,
         color: {
            color: color,
            highlight: color
         }
      });
   });
}

// Hide the disabled nodes
function hideDisabledNodes(direction) {

   window.data.nodes.get().forEach(function (node) {
      var visible = false;

      // Show all node if direction is BOTH, start node should always be visible
      if (direction == BOTH || node.id == window.startNode) {
         visible = true;
      } else {
         window.data.edges.forEach(function(edge){
            // Check that the edge is linked to the current node
            if (edge.to == node.id || edge.from == node.id) {
               // Check if the edge should be visible for the current direction
               if (edge.flag & direction) {
                  visible = true;
               }
            }
         });
      }

      // Update node visibility
      window.data.nodes.update({
         id: node.id,
         hidden: !visible,
      });
   });
}

// Select current node
function selectFirstNode(){
   window.network.selectNodes([window.startNode]);
}

// Get a locale value
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
      itemtype_source:   source[0],
      items_id_source:     source[1],
      itemtype_impacted: impacted[0],
      items_id_impacted:   impacted[1]
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
                  url: CFG_GLPI.root_doc + "/ajax/impact.php",
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

                     updateGraph();
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

// Handler called when an edge is edited
var editEdgeHandler = function(edge, callback) {
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

// Client side flag calculations
function buildFlags() {
   var exploredNodes;

   // Set all flag to the default value (0)
   window.data.edges.get().forEach(function(edge) {
      window.data.edges.update({
         id: edge.id,
         flag: 0
      });
   });

   // Run through the graph forward
   exploredNodes = {};
   exploredNodes[window.startNode] = true;
   buildFlagsFromCurrentNode(
      exploredNodes,
      FORWARD,
      window.startNode
   );

   // Run through the graph backward
   exploredNodes = {};
   exploredNodes[window.startNode] = true;
   buildFlagsFromCurrentNode(
      exploredNodes,
      BACKWARD,
      window.startNode
   );
}

// Client side flag calculations
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

// Toggle the colors global variables
function toggleColors(direction, enable) {
   window.colorize[direction] = enable;
   applyColors();
}

// Update the client side calculations (flags + colors)
function updateGraph() {
   buildFlags();
   applyColors();
}

// Set color global var
function setColor(direction, color) {
   window.colors[direction] = color;
   applyColors();
}