<?php
/*
        Inroads Shopping Cart - Facebook Commerce Module Installation Script

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC

*/

chdir(dirname(__FILE__));   chdir('..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../engine/installutils.php';

$db = new DB;
if (! $db) process_install_error(50,'Unable to open database');
if (! apply_database_updates($db,'upgrade/facebook.sql',$error))
   process_install_error(51,$error);
$db->close();

?>
