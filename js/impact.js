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

// Constants to represent nodes and edges
var NODE = 1;
var EDGE = 2;

// Constant for graph direction (bitmask)
var FORWARD  = 1;   // 0b01
var BACKWARD = 2;   // 0b10
var BOTH     = 3;   // 0b11

// Load cytoscape
var cytoscape = window.cytoscape;

var impact = {
   // Store the user modification
   delta: {},

   // Locales for the labels
   locales: {},

   // Check if the graph is in edit mode
   editMode: false,

   // Store if the different direction of the graph should be colorized
   directionVisibility: {},

   // Store color for egdes
   edgeColors: {},

   // Cytoscape instance
   cy: null,

   // The impact network container
   impactContainer: null,

   /**
    * Initialise variables
    *
    * @param {JQuery} impactContainer
    * @param {string} locales (json)
    * @param {Object} colors properties: default, forward, backward, both
    */
   prepareNetwork: function(impactContainer, locales, colors) {
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
         elements: data,
         style: [
            {
               selector: 'node',
               style: {
                  'label': 'data(label)',
                  'shape': 'rectangle',
                  'background-color': '#666',
                  'background-image': 'data(image)',
                  'background-fit': 'contain',
                  'background-opacity': '0',
               }
            },
            {
               selector: 'edge',
               style: {
                  'width': 3,
                  'line-color': this.edgeColors[0],
                  'target-arrow-color': '#0c0',
                  'target-arrow-shape': 'triangle',
                  'curve-style': 'bezier'
               }
            },
            {
               selector: '[flag=' + FORWARD + ']',
               style: {
                  'line-color': this.edgeColors[FORWARD],
               }
            },
            {
               selector: '[flag=' + BACKWARD + ']',
               style: {
                  'line-color': this.edgeColors[BACKWARD],
               }
            },
            {
               selector: '[flag=' + BOTH + ']',
               style: {
                  'line-color': this.edgeColors[BOTH],
               }
            }
         ],
         layout: {
            name: 'cose',
            // transform: function(node, position) {
            //    var sin = Math.sin(90);
            //    var cos = Math.cos(90);

            //    return {
            //       x: position.x * cos - position.y * sin,
            //       y: position.y * cos + position.x * sin
            //    };
            // }
         }
      });
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

};