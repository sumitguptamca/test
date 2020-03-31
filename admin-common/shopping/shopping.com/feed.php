<?php
/*
               Inroads Shopping Cart - shopping.com Datafeed API Module

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
    $buffer = str_replace("\"","\"\"",$buffer);
    $buffer = str_replace("\r",' ',$buffer);
    $buffer = str_replace("\n",' ',$buffer);
    $buffer = strip_tags($buffer);
    return "\"".$buffer."\"";
}

function export_feed()
{
    set_remote_user('shopping.com');
    set_time_limit(0);
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    $categories = $db->get_records('select * from shopping_categories','id',
                                   'category');
    $query = 'select * from products where '.get_shopping_status_where() .
             ' and (shopping_flags & 4) order by name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (! isset($db->error)) print "No Products found to load\n";
       return;
    }

    $feed_filename = tempnam(sys_get_temp_dir(),'shopping.com');
    $feed_file = fopen($feed_filename,'w');
    fwrite($feed_file,"\"Unique Merchant SKU\",\"Product Name\",\"Product URL\"," .
           "\"Image URL\",\"Current Price\",\"Stock Availability\"," .
           "\"Condition\",\"MPN/ISBN\",\"UPC\",\"Original Price\"," .
           "\"Manufacturer\",\"Product Description\",\"Product Type\"," .
           "\"Category Name\",\"Category ID\",\"Gender\",\"Color\"\n");

    foreach ($rows as $row) {
       if (function_exists('update_shopping_product_row'))
          update_shopping_product_row(SHOPPING_COM_FEED,$db,$row);
       if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       $product_url = build_product_url($db,$row);
       $image_url = build_image_url($db,$row);
       $price = get_product_price($db,$row,$features);
       $list_price = get_product_list_price($db,$row,$features);
       if ($row['short_description'])
          $description = $row['short_description'];
       else $description = $row['long_description'];
       $category_id = $row['shopping_cat'];
       if (isset($categories[$category_id]))
          $category = $categories[$category_id];
       else $category = '';
       switch ($row['shopping_gender']) {
          case 'unisex': $gender = 'Unisex';   break;
          case 'female': $gender = 'Women';   break;
          case 'male': $gender = 'Men';   break;
          default: $gender = '';
       }
       $line = format_data($row['id']).',' .
               format_data($product_name).',' .
               format_data($product_url).',' .
               format_data($image_url).',' .
               format_data($price).',"Yes","New",' .
               format_data($row['shopping_mpn']).',' .
               format_data($row['shopping_gtin']).',' .
               format_data($list_price).',' .
               format_data($row['shopping_brand']).',' .
               format_data($description).',' .
               format_data($row['shopping_type']).',' .
               format_data($category).',' .
               format_data($category_id).',' .
               format_data($gender).',' .
               format_data($row['shopping_color'])."\n";
       fwrite($feed_file,$line);
    }
    fclose($feed_file);

    header('Content-type: text/csv');
    header('Content-Length: '.filesize($feed_filename));
    $feed_data = file_get_contents($feed_filename);
    if (! unlink($feed_filename)) log_error('Unable to delete '.$feed_filename);
    if (file_exists($feed_filename))
       log_error('Feed Filename '.$feed_filename.' still exists after unlink');
    log_activity('Generated shopping.com Datafeed');
    print $feed_data;
}

export_feed();

DB::close_all();

?>
