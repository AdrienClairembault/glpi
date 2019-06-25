<?php

include ( "../inc/includes.php");

Session::checkLoginUser();

if (isset($_POST['save']) && isset($_POST['impacts'])) {
   $em = new Impact();

   // Decode data (should be json)
   $data = Toolbox::jsonDecode($_POST['impacts'], true);

   if (!is_array($data)) {
      print_r($data);
      Html::back();
      die;
   }

   foreach ($data as $impact) {
      // Extract action
      $action = $impact['action'];
      unset($impact['action']);

      switch ($action) {
         case 'add':
            $em->add($impact);
            break;

         case 'delete':
            $em->delete($impact);
            break;

         default:
            break;
      }
   }
}

Html::back();
