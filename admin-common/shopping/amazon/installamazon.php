<?php
/*
        Inroads Shopping Cart - Amazon Shopping Module Installation Script

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

chdir(dirname(__FILE__));   chdir('..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../engine/installutils.php';
require_once 'upgrade/upgradeutil.php';

$db = new DB;
if (! $db) process_install_error(50,'Unable to open database');
if (! apply_database_updates($db,'upgrade/amazon.sql',$error))
   process_install_error(51,$error);
if (! database_table_exists($db,'amazon_item_types')) {
   if (! apply_database_updates($db,'upgrade/amazon_item_types.sql',$error))
      process_install_error(52,$error);
}
$db->close();

?>
