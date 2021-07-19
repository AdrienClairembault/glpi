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

namespace Glpi\ContentTemplates\Parameters;

use CommonDBTM;
use CommonITILActor;
use CommonITILObject;
use Entity;
use Glpi\ContentTemplates\Parameters\Parameters_Types\ArrayParameter;
use Glpi\ContentTemplates\Parameters\Parameters_Types\AttributeParameter;
use Glpi\ContentTemplates\Parameters\Parameters_Types\ObjectParameter;
use Group;
use ITILCategory;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Parameters for "CommonITILObject" items
 */
class CommonITILObjectParameters extends AbstractParameters
{
   public static function getRootName(): string {
      return 'commonitil';
   }

   public static function getTargetClasses(): array {
      return [CommonITILObject::class];
   }

   public function defineParameters(): array {
      return [
         new AttributeParameter("id", __("Ticket's id")),
         new AttributeParameter("ref", __("Reference of the ticket (# + id)")),
         new AttributeParameter("link", __("Link to this ticket"), "raw"),
         new AttributeParameter("name", __("Ticket's title")),
         new AttributeParameter("content", __("Ticket's description"), "raw"),
         new AttributeParameter("date", __("Ticket's opening date"), 'date("d/m/y H:i")'),
         new AttributeParameter("solvedate", __("Ticket's resolution date"), 'date("d/m/y H:i")'),
         new AttributeParameter("closedate", __("Ticket's close date"), 'date("d/m/y H:i")'),
         new AttributeParameter("status", __("Ticket's status")),
         new AttributeParameter("urgency", __("Ticket's urgency")),
         new AttributeParameter("impact", __("Ticket's impact")),
         new AttributeParameter("priority", __("Ticket's priority")),
         new AttributeParameter("itemtype", __("Itemtype")),
         new ObjectParameter("entity", new EntityParameters()),
         new ObjectParameter("itilcategory", new ITILCategoryParameters()),
         new ArrayParameter("requesters.users", 'user', new UserParameters(), __("Ticket's requesters (users)")),
         new ArrayParameter("observers.users", 'user', new UserParameters(), __("Ticket's observers (users)")),
         new ArrayParameter("technicians.users", 'user', new UserParameters(), __("Ticket's technicians (users)")),
         new ArrayParameter("requesters.groups", 'group', new GroupParameters(), __("Ticket's requesters (groups)")),
         new ArrayParameter("observers.groups", 'group', new GroupParameters(), __("Ticket's observers (groups)")),
         new ArrayParameter("technicians.groups", 'group', new GroupParameters(), __("Ticket's technicians (groups)")),
      ];
   }

   protected function defineValues(CommonDBTM $commonitil): array {
      /** @var CommonITILObject $commonitil  */

      // Base values from ticket property
      $values = [
         'id'        => $commonitil->fields['id'],
         'ref'       => "#" . $commonitil->fields['id'],
         'link'      => $commonitil->getLink(),
         'name'      => $commonitil->fields['name'],
         'content'   => $commonitil->fields['content'],
         'date'      => $commonitil->fields['date'],
         'solvedate' => $commonitil->fields['solvedate'],
         'closedate' => $commonitil->fields['closedate'],
         'status'    => $commonitil::getStatus($commonitil->fields['status']),
         'urgency'   => $commonitil::getUrgencyName($commonitil->fields['urgency']),
         'impact'    => $commonitil::getImpactName($commonitil->fields['impact']),
         'priority'  => $commonitil::getPriorityName($commonitil->fields['priority']),
         'itemtype'  => $commonitil::getType(),
      ];

      // Add ticket's entity
      if ($entity = Entity::getById($commonitil->fields['entities_id'])) {
         $entity_parameters = new EntityParameters();
         $values['entity'] = $entity_parameters->getValues($entity);
      }

      // Add ticket's category
      if ($itilcategory = ITILCategory::getById($commonitil->fields['itilcategories_id'])) {
         $itilcategory_parameters = new ITILCategoryParameters();
         $values['itilcategory'] = $itilcategory_parameters->getValues($itilcategory);
      }

      // Add requesters / observers / assigned data
      $commonitil->loadActors();

      $values['requesters'] = [
         'users'  => [],
         'groups' => [],
      ];
      $values['observers'] = [
         'users'  => [],
         'groups' => [],
      ];
      $values['technicians'] = [
         'users'  => [],
         'groups' => [],
      ];

      $user_parameters = new UserParameters();
      $users_to_add = [
         'requesters' => CommonITILActor::REQUESTER,
         'observers'  => CommonITILActor::OBSERVER,
         'assignees'  => CommonITILActor::ASSIGN,
      ];
      foreach ($users_to_add as $key => $type) {
         foreach ($commonitil->getUsers($type) as $data) {
            $user = User::getById($data['users_id']);
            if ($user) {
               $values[$key]['users'][] = $user_parameters->getValues($user);
            }
         }
      }

      $group_parameters = new GroupParameters();
      $groups_to_add = [
         'requesters' => CommonITILActor::REQUESTER,
         'observers'  => CommonITILActor::OBSERVER,
         'assignees'  => CommonITILActor::ASSIGN,
      ];
      foreach ($groups_to_add as $key => $type) {
         foreach ($commonitil->getGroups($type) as $data) {
            $group = Group::getById($data['groups_id']);
            if ($group) {
               $values[$key]['groups'][] = $group_parameters->getValues($group);
            }
         }
      }

      return $values;
   }
}
