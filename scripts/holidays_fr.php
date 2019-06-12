<?php
include ("../inc/includes.php");

if (isset($_SERVER['argv'])) {
   for ($i=1; $i<$_SERVER['argc']; $i++) {
      $it = explode("=", $_SERVER['argv'][$i], 2);
      $it[0] = preg_replace('/^--/', '', $it[0]);

      $_GET[$it[0]] = (isset($it[1]) ? $it[1] : true);
   }
}
if (isset($_GET['help']) || !isset($_GET['entity'])) {
   echo "Usage : php holidays_fr.php --entity=<id> [--recursive --year=<year> --maxyear=<year> ]\n";
   echo "Options values :\n";
   echo "\t--entity        Insert in this entity\n";
   echo "\t--recursive     entity recursivity (default yes)\n";
   echo "\t--year          starting year (default current year)\n";
   echo "\t--maxyear       Insert non-recurrent holidays until this year (default year+2)\n";

   exit (0);
}

$year =  date("Y");
$maxyear = $year + 2;
if (isset($_GET['year'])) {
   $year = $_GET['year'];
}
if (isset($_GET['maxyear'])) {
   $maxyear = $_GET['maxyear'];
}
if (!isset($_GET['recursive'])) {
   $_GET['recursive'] = true;
}


//add recurents holidays
$reccurent_holidays = [
   ["Jour de l\'an",   $_GET['entity'], $_GET['recursive'], "", "$year-01-01", "$year-01-01", 1],
   ["Fête du travail", $_GET['entity'], $_GET['recursive'], "", "$year-05-01", "$year-05-01", 1],
   ["8 Mai 1945",      $_GET['entity'], $_GET['recursive'], "", "$year-05-08", "$year-05-08", 1],
   ["Fête Nationale",  $_GET['entity'], $_GET['recursive'], "", "$year-07-14", "$year-07-14", 1],
   ["Assomption",      $_GET['entity'], $_GET['recursive'], "", "$year-08-05", "$year-08-05", 1],
   ["Toussaint",       $_GET['entity'], $_GET['recursive'], "", "$year-11-01", "$year-11-01", 1],
   ["Armistice",       $_GET['entity'], $_GET['recursive'], "", "$year-11-11", "$year-11-11", 1],
   ["Noël",            $_GET['entity'], $_GET['recursive'], "", "$year-12-25", "$year-12-25", 1],
];
$holiday = new Holiday;
foreach ($reccurent_holidays as $line) {
   $DB->query("INSERT INTO glpi_holidays VALUES (
      '', '".implode("', '", $line)."', NOW(), NOW()
   )");
}


//add non-reccurent holidays
for ($cyear = $year; $cyear <= $maxyear; $cyear++) {
   $easter_ts = easter_date($cyear);

   $lun_paq   = date("Y-m-d", $easter_ts + 24 * 60 * 60);
   $ascension = date("Y-m-d", $easter_ts + 39 * 24 * 60 * 60);
   $pentecote = date("Y-m-d", $easter_ts + 50 * 24 * 60 * 60);


   $easter_holidays = [
      ["Pâques $cyear",                 $_GET['entity'], $_GET['recursive'], "", $lun_paq, $lun_paq, 0],
      ["Jeudi de l\'Ascension $cyear",  $_GET['entity'], $_GET['recursive'], "", $ascension, $ascension, 0],
      ["Lundi de Pentecôte $cyear",     $_GET['entity'], $_GET['recursive'], "", $pentecote, $pentecote, 0],
   ];

   foreach ($easter_holidays as $line) {
      $DB->query("INSERT INTO glpi_holidays VALUES (
         '', '".implode("', '", $line)."', NOW(), NOW()
      )");
   }
}