/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
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

/* global tinymce */

var GLPI = GLPI || {};
GLPI.RichText = GLPI.RichText || {};

/**
 * User templates parameters autocompleter.
 *
 * @since 10.0.0
 */
GLPI.RichText.UserTemplatesParameters = class {

   /**
    * @param {Editor} editor
    * @param {string} values Auto completion possible values
    */
   constructor(editor, values) {
      this.editor = editor;
      this.values = this.parseParameters(JSON.parse(values));
   }

   /**
    * Register as autocompleter to editor.
    *
    * @returns {void}
    */
   register() {
      const that = this;

      // Register autocompleter
      this.editor.ui.registry.addAutocompleter(
         'user_mention',
         {
            ch: '{',
            minChars: 0,
            fetch: function (pattern) {
               return that.fetchItems(pattern);
            },
            onAction: function (autocompleteApi, range, value) {
               that.insertTwigContent(autocompleteApi, range, value);
            }
         }
      );
   }

   /**
    * Fetch autocompleter items.
    *
    * @private
    *
    * @param {string} pattern
    *
    * @returns {tinymce.util.Promise}
    */
   fetchItems(pattern) {
      const that = this;
      const editor_content = this.editor.getContent();

      return new tinymce.util.Promise(
         function (resolve) {
            const items = that.values.filter(
               function(item) {
                  // Text do not match item, skip
                  if (!item.value.includes('{' + pattern)) {
                     return false;
                  }

                  // Hidden item, don't show if specific property is not defined
                  // This is used for loops content, we do not want to show the
                  // autocompletion value of the iterable content if the loop
                  // is not defined
                  if (item.show !== undefined && !editor_content.includes(item.show)) {
                     return false;
                  }

                  return true;
               }
            );
            resolve(items);
         }
      );
   }

   /**
    * Recursive function to parse available parametes into a format that can
    * be handled by the autocompletion
    *
    * @private
    *
    * @param {Array} parameters
    * @param {string} prefix
    *
    * @returns {Array} Parsed parameters
    */
   parseParameters(parameters, prefix = "") {
      const parsed_parameters = [];
      const that = this;

      parameters.forEach(parameter => {
         // Add prefix, needed when we go down recursivly so we don't lose track
         // of the main item (e.g ticket.entity.name instead of entity.name)
         if (prefix.length > 0) {
            parameter.key = prefix + "." + parameter.key;
         }

         switch (parameter.type) {
            // Add a simple attribute to autocomplete
            case 'AttributeParameter': {
               let value = '{{ ' + parameter.key;
               if (parameter.filter && parameter.filter.length) {
                  value += ' | ' + parameter.filter;
               }
               value += " }}";

               parsed_parameters.push({
                  type: 'autocompleteitem',
                  value: value,
                  text: value + ' - ' + parameter.label,
               });
               break;
            }

            // Recursivly parse parameters of the given object
            case 'ObjectParameter': {
               parsed_parameters.push(...that.parseParameters(parameter.properties, parameter.key));
               break;
            }

            // Add a possible loop to the autocomplete, with extra autocomplete
            // support for the content of the array.
            case 'ArrayParameter': {
               parsed_parameters.push({
                  type: 'autocompleteitem',
                  value: '{% for ' + parameter.items_key + ' in ' + parameter.key + ' %}',
                  text: '{% for ' + parameter.items_key + ' in ' + parameter.key + ' %} - ' + parameter.label,
               });

               // Push content of array, hidden by default unless the parent loop exist in the editor
               const content = that.parseParameters([parameter.content]);
               parsed_parameters.push(
                  ...content.map(
                     function(item) {
                        item.show = '{% for ' + parameter.items_key + ' in ' + parameter.key + ' %}';
                        return item;
                     }
                  )
               );
               break;
            }
         }
      });

      return parsed_parameters;
   }

   /**
    * Add mention to selected user in editor.
    *
    * @private
    *
    * @param {AutocompleterInstanceApi} autocompleteApi
    * @param {Range} range
    * @param {string} value
    *
    * @returns {void}
    */
   insertTwigContent(autocompleteApi, range, value) {
      this.editor.selection.setRng(range);

      // Special case for loops, auto add closing tag
      if (value.indexOf("{% for ") == 0) {
         value = value + "<br><br>{% endfor %}";
      }

      this.editor.insertContent(value);
      autocompleteApi.hide();
   }
};
