<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

// Fixture for Glpi\Tools\PHPStan\Rules\MissingRightsCheckRule.
// The path intentionally contains a "front/" segment so the rule considers it in scope.

// --- Should be reported: mutation with no rights check at all -----------------------
$bad = new Database();
if (isset($_POST['update'])) {
    $bad->update($_POST);
}

// --- Should be reported: only a READ-level session gate, no item check --------------
Session::checkRight('database', READ);
$readonly_gate = new Database();
if (isset($_POST['update'])) {
    $readonly_gate->update($_POST);
}

// --- OK: item-level check precedes the mutation -------------------------------------
$ok = new Database();
if (isset($_POST['update'])) {
    $ok->check($_POST['id'], UPDATE);
    $ok->update($_POST);
}

// --- OK: check is on a different object than the one mutated (cross-object) ----------
$cart    = new Cartridge();
$cartype = new CartridgeItem();
if (isset($_POST['add'])) {
    $cartype->check($_POST['id'], CREATE);
    $cart->add($_POST);
}

// --- OK: write-level session gate is sufficient -------------------------------------
$conf = new Config();
Session::checkRight('config', UPDATE);
if (isset($_POST['update'])) {
    $conf->update($_POST);
}

// --- OK: guard-and-throw with a static right accessor -------------------------------
$guarded = new Database();
if (isset($_POST['update'])) {
    if (!$guarded::canUpdate()) {
        throw new RuntimeException();
    }
    $guarded->update($_POST);
}

// --- OK: rights check buried in an assignment expression ----------------------------
$buried = new Database();
if (isset($_POST['update'])) {
    $denied = !$buried->can($buried->fields['id'], UPDATE);
    if ($denied) {
        throw new RuntimeException();
    }
    $buried->update($_POST);
}

// --- Ignored: receiver is not a CommonDBTM instance (DB query builder) --------------
/** @var object $DB */
if (isset($_POST['go'])) {
    $DB->update('glpi_table', ['a' => 1], ['id' => 2]);
}
