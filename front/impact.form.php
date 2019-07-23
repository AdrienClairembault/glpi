<?php

include "../inc/includes.php";

Session::checkLoginUser();

// Const for delta action
const DELTA_ACTION_ADD   = 1;
const DELTA_ACTION_UPDATE  = 2;
const DELTA_ACTION_DELETE= 3;

if (isset($_POST['impacts'])) {
   // Decode data (should be json)
   $data = Toolbox::jsonDecode($_POST['impacts'], true);

   if (!is_array($data)) {
      print_r($data);
      Html::back();
      die;
   }

   // Save edge delta
   $em = new Impact();
   foreach ($data['edges'] as $impact) {
      // Extract action
      $action = $impact['action'];
      unset($impact['action']);

      switch ($action) {
         case DELTA_ACTION_ADD:
            $em->add($impact);
            break;

         case DELTA_ACTION_DELETE:
            $em->delete($impact);
            break;

         default:
            break;
      }
   }

   // Save compound delta
   $em = new ImpactCompound();
   foreach ($data['compounds'] as $id => $compound) {
      // Extract action
      $action = $compound['action'];
      unset($compound['action']);

      switch ($action) {
         case DELTA_ACTION_ADD:
            $newCompoundID = $em->add($compound);

            // Update id reference in parent delta
            // This is needed because some nodes might have this compound
            // temporary id as their parent id
            foreach ($data['parents'] as $nodeID => $node) {
               if ($node['parent_id'] == $id) {
                  $data['parents'][$nodeID]['parent_id'] = $newCompoundID;
               }
            }
            break;

         case DELTA_ACTION_UPDATE:
            $compound['id'] = $id;
            $em->update($compound);
            break;

         case DELTA_ACTION_DELETE:
            $em->delete(['id' => $id]);
            break;

         default:
            break;
      }
   }

   // Save parent delta
   $em = new ImpactItem();
   foreach ($data['parents'] as $parent) {
      // Extract action
      $action = $parent['action'];
      unset($parent['action']);

      switch ($action) {
         case DELTA_ACTION_ADD:
            $em->add($parent);
            break;

         case DELTA_ACTION_UPDATE:
            $em->update($parent);
            break;

         case DELTA_ACTION_DELETE:
            // $parent should contains the itemtype and items_id
            $em->delete($parent);
            break;
      }
   }
}

Html::back();
