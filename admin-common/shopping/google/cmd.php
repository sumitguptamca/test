<?php
/*
               Inroads Shopping Cart - Google Shopping Commands Module

                         Written 2010-2019 by Randall Severy
                           Copyright 2010-2019 Inroads, LLC


     If running on CloudLinux, prevent killing of background tasks by editing
     /usr/sbin/kill_php_script and add: grep -v google/cmd.php

*/

chdir(dirname(__FILE__));   chdir('../..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../cartengine/shopping-common.php';
require_once '../cartengine/utility.php';
require_once 'shopping/google/googleshopping.php';
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once '../cartengine/cartconfig-common.php';
}
else $shopping_cart = false;
require_once '../cartengine/products-common.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';

if (isset($_SERVER['SERVER_SOFTWARE'])) $interactive = true;
else $interactive = false;

ini_set('memory_limit','4096M');

define('MAX_LOAD_PRODUCTS',100);
define('GOOGLE_REFRESH_DAYS',25);
define('GOOGLE_REFRESH_BATCH_SIZE',100);
define('GOOGLE_SHOPPING_FLAG',1);

function add_item()
{
    $id = get_form_field('id');
    if (! $id) {
       print "Error: No ID specified<br>\n";   return;
    }
    $db = new DB;
    $google_shopping = new GoogleShopping($db);
    $shopping_item = $google_shopping->build_item_array($id);
    if (! $shopping_item) {
       print 'Error 1 ['.date('D M d Y H:i:s').']: ' .
             $google_shopping->error."<br>\n";   return;
    }
    $item_id = $google_shopping->add_item($shopping_item);
    if (! $item_id) {
       print 'Error 2 ['.date('D M d Y H:i:s').']: ' .
             $google_shopping->error."<br>\n";   return;
    }
    $google_shopping->log_activity('Added Google Shopping Item '.$item_id .
                                   ' (Product #'.$id.')');
    $query = 'update products set google_shopping_id=?,' .
             'google_shopping_updated=? where id=?';
    $query = $db->prepare_query($query,$item_id,time(),$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       print 'Database Error ['.date('D M d Y H:i:s').']: ' .
              $db->error."<br>\n";
       return null;
    }

    print '<html><head><title>Google Shopping Item Added</title>' .
          "</head><body>\n";
    print "<h3>Google Shopping Item Added</h3>\n";
    print "</body></html>\n";
}

function update_item()
{
    $id = get_form_field('id');
    if (! $id) {
       print "Error: No ID specified<br>\n";   return;
    }
    $db = new DB;
    $google_shopping = new GoogleShopping($db);
    $shopping_item = $google_shopping->build_item_array($id);
    if (! $shopping_item) {
       print 'Error 3 ['.date('D M d Y H:i:s').']: ' .
             $google_shopping->error."<br>\n";   return;
    }
    if ((! isset($shopping_item['item_id'])) || (! $shopping_item['item_id'])) {
       print "Error: Product doesn't have a Google Shopping ID<br>\n";
       return;
    }
    $item_id = $shopping_item['item_id'];

    if (! $google_shopping->update_item($item_id,$shopping_item)) {
       print 'Error 4 ['.date('D M d Y H:i:s').']: ' .
             $google_shopping->error."<br>\n";   return;
    }
    $google_shopping->log_activity('Updated Google Shopping Item '.$item_id .
                                   ' (Product #'.$id.')');

    print "<html><head><title>Google Shopping Item Updated</title></head><body>\n";
    print "<h3>Google Shopping Item Updated</h3>\n";
    print "</body></html>\n";
}

function delete_item()
{
    $id = get_form_field('id');
    if (! $id) {
       print "Error: No ID specified<br>\n";   return;
    }
    $db = new DB;
    $query = 'select google_shopping_id from products where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) print 'Database Error: '.$db->error."<br>\n";
       else print "Error: Product not found<br>\n";
       return;
    }
    if (! $row['google_shopping_id']) {
       print "Error: Product doesn't have a Google Shopping ID<br>\n";
       return;
    }
    $item_id = $row['google_shopping_id'];
    $google_shopping = new GoogleShopping($db);
    if (! $google_shopping->delete_item($item_id,$id)) {
       if (strpos($google_shopping->error,'item not found') === false) {
          print $google_shopping->error."\n";   return;
       }
    }
    $google_shopping->log_activity('Deleted Google Shopping Item '.$item_id .
                                   ' (Product #'.$id.')');
    $query = 'update products set google_shopping_id=null,' .
             'google_shopping_updated=null where id=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       print 'Database Error: '.$db->error."<br>\n";   return null;
    }

    print '<html><head><title>Google Shopping Item Deleted' .
          "</title></head><body>\n";
    print "<h3>Google Shopping Item Deleted</h3>\n";
    print "</body></html>\n";
}

function cleanup_items()
{
    global $interactive;

    set_remote_user('googleshopping');
    set_time_limit(0);
    ignore_user_abort(true);
    $db = new DB;
    $query = 'select id,status,shopping_flags,google_shopping_id from ' .
             'products order by id';
    $products = $db->get_records($query,'id');
    $google_shopping = new GoogleShopping($db);
    $db->close();
    if (! $products) {
       if (! isset($db->error)) print "No Products found to cleanup\n";
       else print 'Database Error: '.$db->error."\n";
       return;
    }

    $items = $google_shopping->list_items();
    if (! $items) {
       print $google_shopping->error;   return;
    }
    if ($interactive) {
       $title = 'Cleaning up Deleted/Off Sale/Not Published Products from ' .
                'Google Shopping';
       print '<html><head><title>'.$title."</title></head><body>\n";
       print '<h3>'.$title.'</h3>\n<pre>';
       flush();
    }

    $deleted_count = 0;   $error_count = 0;
    foreach ($items as $item) {
       $delete_item = false;
       $product_id = $item['offerId'];
       if (isset($products[$product_id])) {
          $row = $products[$product_id];
          if (($row['shopping_flags'] !== '') &&
              (! ($row['shopping_flags'] & 2))) $delete_item = true;
          else if (! check_shopping_status($row['status']))
             $delete_item = true;
       }
       else {
          $delete_item = true;   $row = null;
       }
       if ($delete_item) {
          $item_id = $item['id'];
          $db = new DB;   $google_shopping->db = $db;
          if (! $google_shopping->delete_item($item_id,$product_id)) {
             print 'Unable to delete Item '.$item_id.': ' .
                   $google_shopping->error."\n";
             $error_count++;   $db->close();   continue;
          }
          else if ($interactive) {
             print 'Deleted Google Shopping Item '.$item_id.' for Product #' .
                   $product_id."\n";
             flush();
          }
          $deleted_count++;
          $google_shopping->log_activity('Deleted Google Shopping Item ' .
             $item_id.' for Product #'.$product_id);
          if ($row && $row['google_shopping_id']) {
             $query = 'update products set google_shopping_id=null,' .
                      'google_shopping_updated=null where id=?';
             $query = $db->prepare_query($query,$product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                print 'Database Error: '.$db->error."\n";   $db->close();
                break;
             }
          }
          $db->close();
       }
    }

    $log_string = 'Cleaned Up '.$deleted_count .
                  ' Items from Google Shopping (Errors: '.$error_count.')';
    $google_shopping->log_activity($log_string);   log_activity($log_string);
    if ($interactive) {
       print "</pre><h3>Google Shopping Items Cleaned Up</h3>\n";
       print "</body></html>\n";
    }
}

function load_items($import_page=null,$full_refresh=false)
{
    global $interactive,$off_sale_option;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    set_remote_user('googleshopping');
    set_time_limit(0);
    ignore_user_abort(true);
    $db = new DB;
    $google_shopping = new GoogleShopping($db);
    if (! $google_shopping->use_api) return;
    $features = get_cart_config_value('features',$db);
    if ($full_refresh) $refresh_config = 'google_shopping_last_full_refresh';
    else $refresh_config = 'google_shopping_last_refresh';
    $last_refresh = get_cart_config_value($refresh_config,$db);
    if ($full_refresh)
       $query = 'select * from products where (not isnull(shopping_flags)) ' .
                'and (shopping_flags & 2) order by name ';
    else {
       $expire_time = time() - (GOOGLE_REFRESH_DAYS * 86400);
       $query = 'select * from products where ((not isnull(google_shopping_id)) ' .
          'and (google_shopping_id!="")) and (isnull(google_shopping_updated) ' .
          'or (google_shopping_updated=0) or (google_shopping_updated<?)) and ' .
          '(not isnull(shopping_flags)) and (shopping_flags & 2) and ' .
          '(isnull(google_shopping_error) or (google_shopping_error="")) ' .
          'order by google_shopping_updated,id limit '.GOOGLE_REFRESH_BATCH_SIZE;
       $query = $db->prepare_query($query,$expire_time);
    }
    $products = $db->get_records($query,'id');
    if (! $products) {
       if (! $interactive) return;
       if (! isset($db->error)) print "No Products found to load\n";
       return;
    }
    $db->close();
    if ($interactive) {
       print '<html><head><title>Loading Google Shopping Items' .
             "</title></head><body>\n";
       print "<h3>Loading Google Shopping Items</h3>\n<pre>";   flush();
    }
    $total_count = count($products);

    if (($import_page === null) && (! $interactive) && $full_refresh &&
        ($total_count > MAX_LOAD_PRODUCTS)) {
       $log_string = 'Starting Loading of '.$total_count .
                     ' Products into Google Shopping';
       log_activity($log_string);
       $google_shopping->log_activity($log_string);
       $num_pages = ceil($total_count / MAX_LOAD_PRODUCTS);
       for ($import_page = 0;  $import_page < $num_pages;  $import_page++) {
          $command = 'shopping/google/cmd.php loadproducts '.$import_page;
          $process = new Process($command);
          if ($process->return != 0) {
             log_error('Unable to start Google Shopping Import (' .
                       $process->return.')');
             return;
          }
          $counter = 0;
          while ($process->status()) {
             if ($counter == 300) {
                $process->stop();
                log_error('Google Shopping Import took too long');   return;
             }
             sleep(60);
             $counter++;
          }
       }
       $log_string = 'Finished Loading of '.$total_count .
                     ' Products into Google Shopping';
       log_activity($log_string);   $google_shopping->log_activity($log_string);
       $db = new DB;
       if ($last_refresh) {
          $query = 'update cart_config set config_value=? where config_name=?';
          $query = $db->prepare_query($query,time(),$refresh_config);
       }
       else {
          $query = 'insert into cart_config values(?,?)';
          $query = $db->prepare_query($query,$refresh_config,time());
       }
       $db->log_query($query);   $db->query($query);   $db->close();
       return;
    }

    if ($import_page !== null) {
       $start_count = ($import_page * MAX_LOAD_PRODUCTS) + 1;
       $end_count = ($start_count + MAX_LOAD_PRODUCTS);
       if ($end_count > ($total_count + 1)) $end_count = $total_count + 1;
       $total_count = $end_count - $start_count;
    }

    $added_count = 0;   $updated_count = 0;   $product_count = 0;
    $deleted_count = 0;   $error_count = 0;   $skipped_count = 0;
    $log_string = 'Loading '.$total_count.' Products';
    if ($import_page !== null)
       $log_string .= ' (#'.$start_count.' - #'.($end_count - 1).')';
    $log_string .= ' into Google Shopping';
    log_activity($log_string);   $google_shopping->log_activity($log_string);

    foreach ($products as $product_id => $row) {
       $product_count++;
       if ($import_page !== null) {
          if ($product_count < $start_count) continue;
          if ($product_count == $end_count) break;
       }
       if (($row['shopping_flags'] !== '') &&
           (! ($row['shopping_flags'] & 2))) {
          if ($row['google_shopping_id']) $row['status'] = $off_sale_option;
          else {
             $skipped_count++;   continue;
          }
       }
       if (! check_shopping_status($row['status'])) {
          if ($row['google_shopping_id']) {
             $db = new DB;   $google_shopping->db = $db;
             $item_id = $row['google_shopping_id'];
             if (! $google_shopping->delete_item($item_id,$product_id)) {
                print 'Error 5 ['.date('D M d Y H:i:s').']: ' .
                      $google_shopping->error."\n";
                if (strpos($google_shopping->error,'item not found') === false) {
                   $error_count++;   $db->close();   continue;
                }
             }
             if ($interactive) {
                print 'Deleted Google Shopping Item '.$item_id .
                      ' for Product #'.$product_id."\n";
                flush();
             }
             $google_shopping->log_activity('Deleted Google Shopping Item ' .
                $item_id.' for Product #'.$product_id);
             $query = 'update products set google_shopping_id=null,' .
                      'google_shopping_updated=null where id=?';
             $query = $db->prepare_query($query,$product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                print 'Database Error: '.$db->error."\n";
                $error_count++;   $db->close();   continue;
             }
             $deleted_count++;   $db->close();
          }
          $skipped_count++;   continue;
       }
       $db = new DB;   $google_shopping->db = $db;
       $shopping_item = $google_shopping->build_item_array($product_id,
                                                   $features,$row);
       if (! $shopping_item) {
          print 'Error 6 ['.date('D M d Y H:i:s').']: ' .
                $google_shopping->error.', Product ID: '.$product_id."\n";
          $error_count++;   $db->close();   continue;
       }
       if (! $row['google_shopping_id']) {
          if ($interactive) {
             print 'Adding '.$shopping_item['title'].'...';   flush();
          }
          $item_id = $google_shopping->add_item($shopping_item);
          if (! $item_id) {
             print 'Error 7 ['.date('D M d Y H:i:s').']: ' .
                   $google_shopping->error.', Product ID: '.$product_id."\n";
             $error_count++;   $db->close();   continue;
          }
          $google_shopping->log_activity('Added Product #'.$product_id .
                                         ' to Google Shopping Item '.$item_id);
          $query = 'update products set google_shopping_id=?,' .
                   'google_shopping_updated=? where id=?';
          $query = $db->prepare_query($query,$item_id,time(),$product_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             print 'Database Error: '.$db->error."\n";
             $error_count++;   $db->close();   continue;
          }
          $added_count++;
       }
       else {
          if ($interactive) {
             print 'Updating '.$shopping_item['title'].'...';   flush();
          }
          $item_id = $shopping_item['item_id'];
          if (! $google_shopping->update_item($item_id,$shopping_item)) {
             if ((strpos($google_shopping->error,'not found') !== false) ||
                 ($google_shopping->error = 'server failure')) {
                if ($interactive) {
                   print 'Item not found on Google, adding instead...';
                   flush();
                }
                $item_id = $google_shopping->add_item($shopping_item);
                if (! $item_id) {
                   print 'Error 8 ['.date('D M d Y H:i:s').']: ' .
                         $google_shopping->error.', Product ID: '.$product_id."\n";
                   $error_count++;   $db->close();   continue;
                }
                $google_shopping->log_activity('Added Product #'.$product_id .
                             ' to Google Shopping Item '.$item_id);
                $query = 'update products set google_shopping_id=?,' .
                         'google_shopping_updated=? where id=?';
                $query = $db->prepare_query($query,$item_id,time(),$product_id);
                $db->log_query($query);
                if (! $db->query($query)) {
                   print 'Database Error: '.$db->error."\n";
                   $error_count++;   $db->close();   continue;
                }
                $added_count++;
             }
             else {
                print 'Error 9 ['.date('D M d Y H:i:s').']: ' .
                      $google_shopping->error.', Product ID: '.$product_id."\n";
                $error_count++;   $db->close();   continue;
             }
          }
          else {
             $google_shopping->log_activity('Updated Product #'.$product_id .
                ' with Google Shopping Item '.$item_id);
             $query = 'update products set google_shopping_updated=? where id=?';
             $query = $db->prepare_query($query,time(),$product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                print 'Database Error: '.$db->error."\n";
                $error_count++;   $db->close();   continue;
             }
             $updated_count++;
          }
       }
       if ($interactive) {
          print "Done\n";   flush();
       }
       $db->close();
    }

    $log_string = 'Finished Loading '.$total_count.' Products into Google ' .
                 'Shopping (Added: '.$added_count.', Updated: '.$updated_count .
                 ', Deleted: '.$deleted_count.', Skipped: '.$skipped_count .
                 ', Errors: '.$error_count.')';
    $google_shopping->log_activity($log_string);   log_activity($log_string);

    if (($import_page === null) && (! $full_refresh)) {
       $db = new DB;
       if ($last_refresh) {
          $query = 'update cart_config set config_value=? where config_name=?';
          $query = $db->prepare_query($query,time(),$refresh_config);
       }
       else {
          $query = 'insert into cart_config values(?,?)';
          $query = $db->prepare_query($query,$refresh_config,time());
       }
       $db->log_query($query);   $db->query($query);   $db->close();
    }
    if ((! $full_refresh) && $last_refresh) {
       $refresh_day = date('j',$last_refresh);
       if ($refresh_day != date('j')) cleanup_items();
    }
    if ($interactive) {
       print "</pre><h3>Google Shopping Items Loaded</h3>\n";
       print "</body></html>\n";
    }
}

function resubmit_items()
{
    putenv('SERVER_SOFTWARE');
    $spawn_result = spawn_program('shopping/google/cmd.php resubmit');
    if ($spawn_result != 0) {
       $error = 'Google Resubmit Request returned '.$spawn_result;
       http_response(422,$error);   return;
    }
    log_activity('Started Google Shopping Resubmit Process');
    http_response(202,'Submitted Resubmit Request');
}

function list_items()
{
    global $interactive;

    set_time_limit(0);
    ignore_user_abort(true);
    $google_shopping = new GoogleShopping();
    $items = $google_shopping->list_items();
    if (! $items) {
       print $google_shopping->error."\n";   return;
    }
    if ($interactive) {
       print '<html><head><title>Google Shopping Items' .
             "</title></head><body>\n";
       print "<h3>Google Shopping Items</h3>\n";
       print "<pre>\n";
    }
    if (get_form_field('details') == 'true') print_r($items);
    else {
       foreach ($items as $item)
          print $item['offerId'].': '.$item['title']."\n";
    }
    if ($interactive) print "</pre></body></html>\n";
}

function list_status()
{
    global $interactive;

    set_time_limit(0);
    ignore_user_abort(true);
    $google_shopping = new GoogleShopping();
    $items = $google_shopping->get_product_status();
    if (! $items) {
       print $google_shopping->error."\n";   return;
    }
    if ($interactive) {
       print '<html><head><title>Google Shopping Item Status' .
             "</title></head><body>\n";
       print "<h3>Google Shopping Item Status</h3>\n";
       print "<pre>\n";
    }
    if (get_form_field('details') == 'true') print_r($items);
    else {
       foreach ($items as $item)
print_r($item)."\n";
//          print $item['offerId'].': '.$item['title']."\n";
    }
    if ($interactive) print "</pre></body></html>\n";
}

function generatex509()
{
    $command_string = "openssl req -x509 -nodes -days 365 -newkey rsa:1024 -sha1 -subj \
                      '/C=US/ST=MD/L=Frederick/CN=www.icewhitediamond.com' -keyout \
                      myrsakey.pem -out myrsacert.pem";  
    $exec_output = array();  
    $result = exec($command_string,$exec_output,$return_var);  
    if ($return_var != 0) echo 'Key generation failed.';
    echo 'Successfully generated X509 key.';
}

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
    set_remote_user('googleshopping');
    set_time_limit(0);
    $db = new DB;
    $features = get_cart_config_value('features',$db);
    $query = 'select * from products where '.get_shopping_status_where() .
             ' and (shopping_flags & 2) order by name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (! isset($db->error)) print "No Products found to load\n";
       return;
    }
    $status_values = load_cart_options(PRODUCT_STATUS,$db);

    $feed_filename = tempnam(sys_get_temp_dir(),'googleshopping');
    $feed_file = fopen($feed_filename,'w');
    fwrite($feed_file,'"id","title","link","image_link","price","condition",' .
           '"availability","status","mpn","gtin","brand","product_type",' .
           '"google_product_category","adwords_labels","description",' .
           '"gender","color","age_group","shipping","shipping_weight",' .
           '"custom_label_0","custom_label_1","custom_label_2",' .
           '"custom_label_3","custom_label_4"'."\n");

    foreach ($rows as $row) {
       if (function_exists('update_shopping_product_row'))
          update_shopping_product_row(GOOGLE_SHOPPING_FLAG,$db,$row);
       if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       $product_url = build_product_url($db,$row);
       $image_url = build_image_url($db,$row);
       $price = get_product_price($db,$row,$features);
       if ($row['short_description'])
          $description = $row['short_description'];
       else $description = $row['long_description'];
       $weight = get_product_weight($db,$row,$features);
       if (empty($row['shopping_condition'])) $condition = 'new';
       else $condition = $row['shopping_condition'];
       $status = $row['status'];
       if (empty($status)) $status = 0;
       if (($status == 0) || ($status == 3)) $availability = 'in stock';
       else $availability = 'out of stock';
       if (isset($status_values[$status])) $status = $status_values[$status];
       if (! isset($row['google_adwords'])) $row['google_adwords'] = '';
       if (! isset($row['shipping'])) $row['shipping'] = '';
       if (! isset($row['custom_label_0'])) $row['custom_label_0'] = '';
       if (! isset($row['custom_label_1'])) $row['custom_label_1'] = '';
       if (! isset($row['custom_label_2'])) $row['custom_label_2'] = '';
       if (! isset($row['custom_label_3'])) $row['custom_label_3'] = '';
       if (! isset($row['custom_label_4'])) $row['custom_label_4'] = '';
       $line = format_data($row['id']).',' .
               format_data($product_name).',' .
               format_data($product_url).',' .
               format_data($image_url).',' .
               format_data($price).',' .
               format_data($condition).',' .
               format_data($availability).',' .
               format_data($status).',' .
               format_data($row['shopping_mpn']).',' .
               format_data($row['shopping_gtin']).',' .
               format_data($row['shopping_brand']).',' .
               format_data($row['google_shopping_type']).',' .
               format_data($row['google_shopping_cat']).',' .
               format_data($row['google_adwords']).',' .
               format_data($description).',' .
               format_data($row['shopping_gender']).',' .
               format_data($row['shopping_color']).',' .
               format_data($row['shopping_age']).',' .
               format_data($row['shipping']).',' .
               format_data($weight).',' .
               format_data($row['custom_label_0']).',' .
               format_data($row['custom_label_1']).',' .
               format_data($row['custom_label_2']).',' .
               format_data($row['custom_label_3']).',' .
               format_data($row['custom_label_4'])."\n";
       fwrite($feed_file,$line);
    }
    fclose($feed_file);

    header('Content-type: text/csv');
    header('Content-Length: '.filesize($feed_filename));
    header('Content-Disposition: attachment; filename="googleshopping.csv"');
    header('Cache-Control: no-cache');
    $feed_data = file_get_contents($feed_filename);
    if (! unlink($feed_filename))
       log_error('Unable to delete '.$feed_filename);
    if (file_exists($feed_filename))
       log_error('Feed Filename '.$feed_filename.' still exists after unlink');
    log_activity('Generated Google Shopping Datafeed');
    print $feed_data;
}

if ($interactive) {
   $cmd = get_form_field('cmd');
   if ($cmd && (! check_login_cookie())) exit;
   if ($cmd == 'add') add_item();
   else if ($cmd == 'update') update_item();
   else if ($cmd == 'delete') delete_item();
   else if ($cmd == 'loaditems') load_items(null,true);
   else if ($cmd == 'listitems') list_items();
   else if ($cmd == 'cleanupitems') cleanup_items();
   else if ($cmd == 'generatex509') generatex509();
   else if ($cmd == 'resubmit') resubmit_items();
   else export_feed();
}
else if (isset($argc) && ($argc > 1)) {
   $cmd = $argv[1];
   if ($cmd == 'loadproducts') {
      if ($argc == 3) load_items($argv[2],true);
   }
   else if ($cmd == 'resubmit') {
      load_items(null,true);   cleanup_items();
   }
   else if ($cmd == 'listitems') list_items();
   else if ($cmd == 'liststatus') list_status();
}
else load_items();

DB::close_all();

?>
