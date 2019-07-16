<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 9.5.0
 */
class ImpactItem extends CommonDBTM {

   public function prepareInputForUpdate($input) {
      global $DB;

      // Find id from itemtype and items_id
      $it = $DB->request([
         'FROM'   => 'glpi_impactitems',
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
         'FROM'   => 'glpi_impactitems',
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

   public static function findForItem(CommonDBTM $item) {
      global $DB;

      $it = $DB->request([
         'SELECT' => [
            'glpi_impactitems.parent_id',
            'glpi_impactcompounds.name',
            'glpi_impactcompounds.color',
         ],
         'FROM' => 'glpi_impactitems',
         'JOIN' => [
            'glpi_impactcompounds' => [
               'ON' => [
                  'glpi_impactitems'   => 'parent_id',
                  'glpi_impactcompounds' => 'id',
               ]
            ]
         ],
         'WHERE'  => [
            'glpi_impactitems.itemtype' => get_class($item),
            'glpi_impactitems.items_id' => $item->getID(),
         ]
      ]);

      return $it->next();
   }
}