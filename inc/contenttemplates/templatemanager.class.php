<?php
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

namespace Glpi\ContentTemplates;

use Glpi\Toolbox\RichText;
use Session;
use Toolbox;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\Source;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Handle user defined twig templates :
 *  - followup templates
 *  - tasks templates
 *  - solutions templates
 */
class TemplateManager
{
   /**
    * Boiler plate code to render a user template
    *
    * @param string $content        Template content (html + twig)
    * @param array $params          Variables to be exposed to the templating engine
    * @param bool $add_slashes_deep Should we call add_slashes_deep on rendered
    *                               content ? Should be true if inserting content
    *                               directly into the database and false if
    *                               displaying the content into a form
    *
    * @return string The rendered HTML
    */
   public static function render(
      string $content,
      array $params,
      bool $add_slashes_deep = true
   ): string {
      $loader = new ArrayLoader(['template' => $content]);
      $twig = new Environment($loader);

      // Use sandbox extension to restrict code execution
      $twig->addExtension(new SandboxExtension(self::getSecurityPolicy(), true));

      try {
         // Render the template
         $html = $twig->render('template', $params);
         $html = RichText::getSafeHtml($html, true);
         return $add_slashes_deep ? Toolbox::addslashes_deep($html) : $html;
      } catch (\Twig\Sandbox\SecurityError $e) {
         // Security policy error: the template use a forbidden tag/function/...
         Session::addMessageAfterRedirect(
            sprintf('%s: %s', __("Invalid twig template"), $e->getMessage()),
            false,
            ERROR
         );
         return "";
      } catch (\Twig\Error\SyntaxError $e) {
         // Syntax error, note that we do not show the exception message in the
         // error sent to the users as it not really helpful and is more likely
         // to confuse them that to help them fix the issue
         Session::addMessageAfterRedirect(
            __("Invalid twig template syntax"),
            false,
            ERROR
         );
         return "";
      }
   }

   /**
    * Boiler plate code to validate a template that user is trying to submit
    *
    * @param string $content     Template content (html + twig)
    * @param string $field_label Name of the field containing the template, may
    *                            be used in some error messages.
    *
    * @return bool
    */
   public static function validate(string $content, string $field_label): bool {
      // Needed as GLPI auto escape quotes to \" and this seems to make render
      // and tokenize fails in this context.
      // This step was not needed in TemplateManager::render() because the data is
      // probably already "cleaned" before being inserted in the database
      // whereas we are dealing with POST data here.
      $content = str_replace('\"', '"', $content);

      $twig = new Environment(new ArrayLoader(['template' => $content]));
      $twig->addExtension(new SandboxExtension(self::getSecurityPolicy(), true));

      try {
         // Test if template is valid
         $twig->parse($twig->tokenize(new Source($content, 'template')));

         // Security policies are not valided with the previous step so we
         // need to actually try to render the template to validate them
         $twig->render('template', []);

         return true;
      } catch (\Twig\Sandbox\SecurityError $e) {
         // Security policy error: the template use a forbidden tag/function/...
         Session::addMessageAfterRedirect(
            sprintf('%s: %s', __("Invalid twig template"), $e->getMessage()),
            false,
            ERROR
         );

         // Keep template in session to not lose the user's input
         $_SESSION['twig_restore_input'] = $content;

         return false;
      } catch (\Twig\Error\SyntaxError $e) {
         // Syntax error, note that we do not show the exception message in the
         // error sent to the users as it not really helpful and is more likely
         // to confuse them that to help them fix the issue
         Session::addMessageAfterRedirect(
            sprintf('%s: %s', "Invalid twig template syntax", $field_label),
            false,
            ERROR
         );

         // Keep template in session to not lose the user's input
         $_SESSION['twig_restore_input'] = $content;

         return false;
      }
   }

   /**
    * Define our security policies for the sandbox extension
    *
    * @return SecurityPolicy
    */
   public static function getSecurityPolicy(): SecurityPolicy {
      $tags = ['if', 'for'];
      $filters = ['escape', 'upper', 'date', 'length', 'round', 'lower', 'trim', 'raw'];
      $methods = [];
      $properties = [];
      $functions = ['date', 'max', 'min','random', 'range'];
      return new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
   }
}
