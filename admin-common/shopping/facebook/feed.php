<?php
/*
            Inroads Shopping Cart - Facebook Commerce Data Feed API Module

                        Written 2019 by Randall Severy
                         Copyright 2019 Inroads, LLC
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

function format_data($buffer,$strip=true)
{
    $buffer = str_replace("\"","\"\"",$buffer);
    $buffer = str_replace("\r",' ',$buffer);
    $buffer = str_replace("\n",' ',$buffer);
    if ($strip) $buffer = strip_tags($buffer);
    return "\"".$buffer."\"";
}

function export_feed()
{
    set_remote_user('facebook');
    set_time_limit(0);
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    $feed_type = get_cart_config_value('facebook_feed_type',$db);
    if (! $feed_type) $feed_type = 'Commerce';
    $query = 'select * from products where '.get_shopping_status_where() .
             ' and (shopping_flags & 64) order by name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (! isset($db->error)) print "No Products found to load\n";
       return;
    }

    $feed_filename = tempnam(sys_get_temp_dir(),'facebook');
    $feed_file = fopen($feed_filename,'w');
    if ($feed_type == 'Commerce')
       fwrite($feed_file,'"id","title","description","rich_text_description",' .
          '"availability","condition","price","link","image_link","brand",' .
          '"additional_image_link","gender","google_product_category",' .
          '"product_type","gtin","mpn"'."\n");
    else fwrite($feed_file,'"id","title","description",' .
          '"availability","condition","price","link","image_link","brand",' .
          '"additional_image_link","age_group","gender","google_product_category",' .
          '"product_type"'."\n");

    $html_symbols = array('&reg;','&nbsp;','&rdquo;','&frac14;','&frac12;',
                          '&frac34;','&ldquo;','&quot;','&rsquo;','&#39;',
                          '&ndash;','&hellip;','&amp;');
    $symbol_chars = array('®',' ','"','¼','½','¾','"','"','\'','\'','-','...','&');
 
    foreach ($rows as $row) {
       if (function_exists('update_shopping_product_row'))
          update_shopping_product_row('facebook',$db,$row);
       if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       $product_name = str_replace($html_symbols,$symbol_chars,$product_name);
       $product_url = build_product_url($db,$row);
       $image_url = build_image_url($db,$row);
       $addl_image_url = build_image_url($db,$row,'original','1,1');
       if (! $addl_image_url) $addl_image_url = '';
       $price = get_product_price($db,$row,$features);
       $category = get_product_category($db,$row);
       if ($row['short_description'])
          $description = $row['short_description'];
       else $description = $row['long_description'];
       $description = str_replace($html_symbols,$symbol_chars,$description);
       $status = $row['status'];
       if (empty($status)) $status = 0;
       if (($status == 0) || ($status == 3)) $availability = 'in stock';
       else $availability = 'out of stock';
       switch ($row['shopping_gender']) {
          case 'unisex': $gender = 'Unisex';   break;
          case 'female': $gender = 'Female';   break;
          case 'male': $gender = 'Male';   break;
          default: $gender = '';
       }
       if ($feed_type == 'Commerce')
          $line = format_data($row['id']).',' .
                  format_data($product_name).',' .
                  format_data($description).',' .
                  format_data($description,false).',' .
                  format_data($availability).',' .
                  format_data($row['shopping_condition']).',' .
                  format_data($price.' USD').',' .
                  format_data($product_url).',' .
                  format_data($image_url).',' .
                  format_data($row['shopping_brand']).',' .
                  format_data($addl_image_url).',' .
                  format_data($gender).',' .
                  format_data($row['google_shopping_cat']).',' .
                  format_data($category).',' .
                  format_data($row['shopping_gtin']).',' .
                  format_data($row['shopping_mpn'])."\n";
       else $line = format_data($row['id']).',' .
                    format_data($product_name).',' .
                    format_data($description).',' .
                    format_data($availability).',' .
                    format_data($row['shopping_condition']).',' .
                    format_data($price.' USD').',' .
                    format_data($product_url).',' .
                    format_data($image_url).',' .
                    format_data($row['shopping_brand']).',' .
                    format_data($addl_image_url).',' .
                    format_data($row['shopping_age']).',' .
                    format_data($gender).',' .
                    format_data($row['google_shopping_cat']).',' .
                    format_data($category)."\n";

       fwrite($feed_file,$line);
    }
    fclose($feed_file);

    header('Content-type: text/csv');
    header('Content-Length: '.filesize($feed_filename));
    header('Content-Disposition: attachment; filename="facebook.csv"');
    header('Cache-Control: no-cache');
    $feed_data = file_get_contents($feed_filename);
    if (! unlink($feed_filename))
       log_error('Unable to delete '.$feed_filename);
    if (file_exists($feed_filename))
       log_error('Feed Filename '.$feed_filename.' still exists after unlink');
    log_activity('Generated Facebook Commerce Data Feed');
    print $feed_data;
}

export_feed();

DB::close_all();

?>
