<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 9.5.0
 */
class ImpactParent extends CommonDBTM {

   public function prepareInputForUpdate($input) {
      global $DB;

      // Find id from itemtype and items_id
      $it = $DB->request([
         'FROM'   => 'glpi_impacts_parents',
         'WHERE'  => [
            'itemtype'   => $input['itemtype'],
            'items_id'   => $input['items_id'],
         ]
      ]);

      if (count($it) !== 1) {
         throw new Exception("Can't find item in glpi_impacts_parents");
      }

      $input['id'] = $it->next()['id'];
      return $input;
   }

   public function delete(array $input, $options = [], $history = true) {
      global $DB;

      // Find id from itemtype and items_id
      $it = $DB->request([
         'FROM'   => 'glpi_impacts_parents',
         'WHERE'  => [
            'itemtype'   => $input['itemtype'],
            'items_id'   => $input['items_id'],
         ]
      ]);

      if (count($it) !== 1) {
         return false;
      }

      $input['id'] = $it->next()['id'];
      return parent::delete($input, $options, $history);
   }
}