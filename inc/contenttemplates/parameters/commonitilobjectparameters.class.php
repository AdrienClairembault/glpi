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
use Glpi\ContentTemplates\Parameters\ParametersTypes\ArrayParameter;
use Glpi\ContentTemplates\Parameters\ParametersTypes\AttributeParameter;
use Glpi\ContentTemplates\Parameters\ParametersTypes\ObjectParameter;
use Group;
use ITILCategory;
use Session;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Parameters for "CommonITILObject" items
 */
class CommonITILObjectParameters extends AbstractParameters
{
   public static function getRootNodeName(): string {
      return 'commonitil';
   }

   public static function getObjectLabel(): string {
      return '';
   }

   protected function getTargetClasses(): array {
      return [CommonITILObject::class];
   }

   public function defineParameters(): array {
      return [
         new AttributeParameter("id", __('ID')),
         new AttributeParameter("ref", __("Reference (# + id)")),
         new AttributeParameter("link", _n('Link', 'Links', 1), "raw"),
         new AttributeParameter("name", __('Title')),
         new AttributeParameter("content", __('Description'), "raw"),
         new AttributeParameter("date", __('Opening date'), 'date("d/m/y H:i")'),
         new AttributeParameter("solvedate", __('Resolution date'), 'date("d/m/y H:i")'),
         new AttributeParameter("closedate", __('Closing date'), 'date("d/m/y H:i")'),
         new AttributeParameter("status", __('Status')),
         new AttributeParameter("urgency", __('Urgency')),
         new AttributeParameter("impact", __('Impact')),
         new AttributeParameter("priority", __('Priority')),
         new AttributeParameter("itemtype", __('Itemtype')),
         new ObjectParameter("entity", new EntityParameters()),
         new ObjectParameter("itilcategory", new ITILCategoryParameters()),
         new ArrayParameter("requesters.users", 'user', new UserParameters(), _n('Requester', 'Requesters', Session::getPluralNumber())),
         new ArrayParameter("observers.users", 'user', new UserParameters(), _n('Watcher', 'Watchers', Session::getPluralNumber())),
         new ArrayParameter("assignees.users", 'user', new UserParameters(), _n('Assignee', 'Assignees', Session::getPluralNumber())),
         new ArrayParameter("requesters.groups", 'group', new GroupParameters(), _n('Requester group', 'Requester groups', Session::getPluralNumber())),
         new ArrayParameter("observers.groups", 'group', new GroupParameters(), _n('Watcher group', 'Watcher groups', Session::getPluralNumber())),
         new ArrayParameter("assignees.groups", 'group', new GroupParameters(), _n('Assigned group', 'Assigned groups', Session::getPluralNumber())),
      ];
   }

   protected function defineValues(CommonDBTM $commonitil): array {
      /** @var CommonITILObject $commonitil  */

      // Output "unsanitized" values
      $fields = Toolbox::unclean_cross_side_scripting_deep($commonitil->fields);

      // Base values from ticket property
      $values = [
         'id'        => $fields['id'],
         'ref'       => "#" . $fields['id'],
         'link'      => $commonitil->getLink(),
         'name'      => $fields['name'],
         'content'   => $fields['content'],
         'date'      => $fields['date'],
         'solvedate' => $fields['solvedate'],
         'closedate' => $fields['closedate'],
         'status'    => $commonitil::getStatus($fields['status']),
         'urgency'   => $commonitil::getUrgencyName($fields['urgency']),
         'impact'    => $commonitil::getImpactName($fields['impact']),
         'priority'  => $commonitil::getPriorityName($fields['priority']),
         'itemtype'  => $commonitil::getType(),
      ];

      // Add ticket's entity
      if ($entity = Entity::getById($fields['entities_id'])) {
         $entity_parameters = new EntityParameters();
         $values['entity'] = $entity_parameters->getValues($entity);
      }

      // Add ticket's category
      if ($itilcategory = ITILCategory::getById($fields['itilcategories_id'])) {
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
      $values['assignees'] = [
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
