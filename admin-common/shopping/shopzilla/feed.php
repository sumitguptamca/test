<?php
/*
               Inroads Shopping Cart - ShopZilla Datafeed API Module

                       Written 2013-2018 by Randall Severy
                        Copyright 2013-2018 Inroads, LLC
*/

chdir(dirname(__FILE__));   chdir('../..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../cartengine/shopping-common.php';
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once '../cartengine/cartconfig-common.php';
}
else $shopping_cart = false;
if (file_exists('custom-config.php')) require_once 'custom-config.php';

function format_data($buffer)
{
    $buffer = str_replace("\t",' ',$buffer);
    $buffer = str_replace("\r",' ',$buffer);
    $buffer = str_replace("\n",' ',$buffer);
    $buffer = strip_tags($buffer);
    return $buffer;
}

function export_feed()
{
    set_remote_user('shopzilla');
    set_time_limit(0);
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    $query = 'select * from products where '.get_shopping_status_where() .
             ' and (shopping_flags & 16) order by name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) log_error('Database Error: '.$db->error);
       else print "No Products found to load\n";
       return;
    }

    $feed_filename = tempnam(sys_get_temp_dir(),'shopzilla');
    $feed_file = fopen($feed_filename,'w');
    fwrite($feed_file,"Category ID\tManufacturer\tTitle\tDescription\t" .
           "Product URL\tImage URL\tSKU\tAvailability\tCondition\t" .
           "Ship Weight\tShip Cost\tBid\tPromotional Code\tUPC\tPrice\n");

    foreach ($rows as $row) {
       if (function_exists('update_shopping_product_row'))
          update_shopping_product_row(SHOPZILLA_FEED,$db,$row);
       if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       $product_url = build_product_url($db,$row);
       $image_url = build_image_url($db,$row);
       $price = get_product_price($db,$row,$features);
       if ($row['short_description'])
          $description = $row['short_description'];
       else $description = $row['long_description'];
       $line = format_data($row['shopzilla_cat'])."\t" .
               format_data($row['shopping_brand'])."\t" .
               format_data($product_name)."\t" .
               format_data($description)."\t" .
               format_data($product_url)."\t" .
               format_data($image_url)."\t" .
               format_data($row['id'])."\tIn Stock\tNew\t\t0.00\t\t\t" .
               format_data($row['shopping_gtin'])."\t" .
               format_data($price)."\n";
       fwrite($feed_file,$line);
    }
    fclose($feed_file);

    header('Content-type: text/tab-separated-values');
    header('Content-Length: '.filesize($feed_filename));
    $feed_data = file_get_contents($feed_filename);
    unlink($feed_filename);
    log_activity('Generated ShopZilla Datafeed');
    print $feed_data;
}

export_feed();

DB::close_all();

?>
