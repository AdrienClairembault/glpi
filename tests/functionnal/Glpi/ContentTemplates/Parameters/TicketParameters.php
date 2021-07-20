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

namespace tests\units\Glpi\ContentTemplates\Parameters;

use Glpi\ContentTemplates\Parameters\TicketParameters as CoreTicketParameters;

class TicketParameters extends AbstractParameters
{
   public function testGetValues(): void {
      $this->login();
      $test_entity_id = getItemByTypeName('Entity', '_test_child_2', true);

      $this->createItem('ITILCategory', [
         'name' => 'category_testGetValues'
      ]);
      $itilcategories_id = getItemByTypeName('ITILCategory', 'category_testGetValues', true);

      $this->createItem('Location', [
         'name' => 'location_testGetValues'
      ]);
      $locations_id = getItemByTypeName('Location', 'location_testGetValues', true);

      $this->createItems('SLA', [
         [
            'name'            => 'sla_tto_testGetValue',
            'entities_id'     => $test_entity_id,
            'type'            => 1,
            'number_time'     => 10,
            'definition_time' => 'minute',
         ],
         [
            'name'            => 'sla_ttr_testGetValue',
            'entities_id'     => $test_entity_id,
            'type'            => 0,
            'number_time'     => 3,
            'definition_time' => 'hour',
         ],
      ]);
      $slas_id_tto = getItemByTypeName('SLA', 'sla_tto_testGetValue', true);
      $slas_id_ttr = getItemByTypeName('SLA', 'sla_ttr_testGetValue', true);

      $this->createItems('OLA', [
         [
            'name'            => 'ola_tto_testGetValue',
            'entities_id'     => $test_entity_id,
            'type'            => 1,
            'number_time'     => 15,
            'definition_time' => 'minute',
         ],
         [
            'name'            => 'ola_ttr_testGetValue',
            'entities_id'     => $test_entity_id,
            'type'            => 0,
            'number_time'     => 4,
            'definition_time' => 'hour',
         ],
      ]);
      $olas_id_tto = getItemByTypeName('OLA', 'ola_tto_testGetValue', true);
      $olas_id_ttr = getItemByTypeName('OLA', 'ola_ttr_testGetValue', true);

      $now = date('Y-m-d H:i:s');
      $this->createItem('Ticket', [
         'name'              => 'ticket_testGetValues',
         'content'           => '<p>ticket_testGetValues content</p>',
         'entities_id'       => $test_entity_id,
         'date'              => '2021-07-19 17:11:28',
         'itilcategories_id' => $itilcategories_id,
         'locations_id'      => $locations_id,
         'slas_id_tto'       => $slas_id_tto,
         'slas_id_ttr'       => $slas_id_ttr,
         'olas_id_tto'       => $olas_id_tto,
         'olas_id_ttr'       => $olas_id_ttr,
         'date'              => $now,
      ]);

      $tickets_id = getItemByTypeName('Ticket', 'ticket_testGetValues', true);

      $parameters = new CoreTicketParameters();
      $values = $parameters->getValues(getItemByTypeName('Ticket', 'ticket_testGetValues'));
      // var_dump($values);
      $this->array($values)->isEqualTo([
         'id'        => $tickets_id,
         'ref'       => "#$tickets_id",
         'link'      => "<a  href='/glpi/front/ticket.form.php?id=$tickets_id'  title=\"ticket_testGetValues\">ticket_testGetValues</a>",
         'name'      => 'ticket_testGetValues',
         'content'   => '<p>ticket_testGetValues content</p>',
         'date'      => $now,
         'solvedate' => null,
         'closedate' => null,
         'status'    => 'Processing (assigned)',
         'urgency'   => 'Medium',
         'impact'    => 'Medium',
         'priority'  => 'Medium',
         'itemtype'  => 'Ticket',
         'entity'    => [
            'name' => '_test_child_2',
         ],
         'itilcategory' => [
            'id'   => $itilcategories_id,
            'name' => 'category_testGetValues'
         ],
         'requesters' => [
            'users'  => [
               ['name' => TU_USER],
            ],
            'groups' => [],
         ],
         'observers' => [
            'users'  => [],
            'groups' => [],
         ],
         'assignees' => [
            'users'  => [
               ['name' => TU_USER],
            ],
            'groups' => [],
         ],
         'type'      => 'Incident',
         'global_validation' => "Not subject to approval",
         'tto' => date('Y-m-d H:i:s', strtotime($now) + 10 * 60),
         'ttr' => date('Y-m-d H:i:s', strtotime($now) + 3* 3600),
         'sla_tto' => [
            'id'       => $slas_id_tto,
            'name'     => 'sla_tto_testGetValue',
            'type'     => 'Time to own',
            'duration' => '10',
            'unit'     => 'minutes',
         ],
         'sla_ttr' => [
            'id'       => $slas_id_ttr,
            'name'     => 'sla_ttr_testGetValue',
            'type'     => 'Time to resolve',
            'duration' => '3',
            'unit'     => 'hours',
         ],
         'ola_tto' => [
            'id'       => $olas_id_tto,
            'name'     => 'ola_tto_testGetValue',
            'type'     => 'Time to own',
            'duration' => '15',
            'unit'     => 'minutes',
         ],
         'ola_ttr' => [
            'id'       => $olas_id_ttr,
            'name'     => 'ola_ttr_testGetValue',
            'type'     => 'Time to resolve',
            'duration' => '4',
            'unit'     => 'hours',
         ],
         'location' => [
            'id'           => $locations_id,
            'name'         => 'location_testGetValues',
            'completename' => 'location_testGetValues',
         ],
         'requesttype' => [
            'id'   => 1,
            'name' => 'Helpdesk'
         ],
         'knowbaseitems' => [],
         'assets'        => [],
      ]);

      $this->testGetAvailableParameters($values, $parameters->getAvailableParameters());
   }
}
