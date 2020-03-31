<?php
/*
                 Inroads Shopping Cart - Google Shopping Admin Functions

                         Written 2016-2018 by Randall Severy
                           Copyright 2016-2018 Inroads, LLC
*/

$google_shopping = null;

define('GOOGLE_PRODUCT_TYPE_URL',
       'http://www.google.com/basepages/producttype/taxonomy.en-US.txt');

function use_google_api($db=null)
{
    static $use_google_shopping_api = null;

    if ($use_google_shopping_api !== null) return $use_google_shopping_api;
    if (! $db) $db = new DB;
    $query = 'select config_value from cart_config ' .
             'where config_name="google_shopping_use_api"';
    $row = $db->get_record($query);
    if (! empty($row['config_value'])) $use_google_shopping_api = true;
    else $use_google_shopping_api = false;
    return $use_google_shopping_api;
}

function add_google_product_fields(&$product_record)
{
    $product_record['google_shopping_id'] = array('type' => CHAR_TYPE);
    $product_record['google_shopping_type'] = array('type' => CHAR_TYPE);
    $product_record['google_shopping_cat'] = array('type' => CHAR_TYPE);
    $product_record['google_adwords'] = array('type' => CHAR_TYPE);
    $product_record['google_shopping_updated'] = array('type' => INT_TYPE);
    $product_record['google_shopping_error'] = array('type' => CHAR_TYPE);
    $product_record['google_shopping_warnings'] = array('type' => CHAR_TYPE);
}

function add_google_shopping_fields($db,$dialog,$row)
{
    $shopping_flags = get_row_value($row,'shopping_flags');
    if ($shopping_flags === '') $shopping_flags = 0;
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                   "<div class=\"shopping_title\">Google Shopping\n");
    add_shopping_flag($dialog,GOOGLE_SHOPPING_FLAG,$shopping_flags);
    $dialog->write("</div></td></tr>\n");
    $google_shopping_id = get_row_value($row,'google_shopping_id');
    $dialog->start_row('ID:');
    $dialog->write('<tt id="google_shopping_id_string">');
    if ($google_shopping_id) $dialog->write($google_shopping_id);
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
    $dialog->start_row('Category:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_cat\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_cat'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Category\" class=\"select_shopping_" .
                   "button\" onClick=\"select_shopping_field('" .
                   "google_shopping_cat','Google Product Category',1100);\">\n");
    $dialog->end_row();
    $dialog->start_row('Product Type:');
    $dialog->write("<input type=\"text\" class=\"text select_shopping_input\" name=\"" .
                   "google_shopping_type\" size=\"70\" value=\"");
    write_form_value(get_row_value($row,'google_shopping_type'));
    $dialog->write("\">&nbsp;<input type=\"button\" value=\"" .
                   "Select Product Type\" class=\"select_shopping_button\" " .
                   "onClick=\"select_shopping_field('google_shopping_type'," .
                   "'Google Product Type',1100);\">\n");
    $dialog->end_row();
    $dialog->add_edit_row('AdWords Labels:','google_adwords',$row,85);
    $google_updated = get_row_value($row,'google_shopping_updated');
    $google_error = get_row_value($row,'google_shopping_error');
    $dialog->start_hidden_row('Last Uploaded:','google_updated_row',
                              ((! $google_updated) || $google_error));
    $dialog->write('<tt id="google_shopping_updated">');
    if ($google_updated)
       $dialog->write(date('F j, Y g:i:s a',$google_updated));
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
    $dialog->start_hidden_row('Error:','google_error_row',
                       empty($row['google_shopping_error']),'top');
    $dialog->write('<tt id="google_shopping_error">');
    if (! empty($row['google_shopping_error']))
       $dialog->write($row['google_shopping_error']);
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
    $dialog->start_hidden_row('Warnings:','google_warnings_row',
                       empty($row['google_shopping_warnings']),'top');
    $dialog->write('<tt id="google_shopping_warnings">');
    if (! empty($row['google_shopping_warnings'])) {
       $warnings = str_replace('|','<br>',$row['google_shopping_warnings']);
       $dialog->write($warnings);
    }
    else $dialog->write('&nbsp;');
    $dialog->write('</tt>');
    $dialog->end_row();
}

function load_google_product_types($dialog,$value)
{
    $google_shopping_taxonomy = file_get_contents(GOOGLE_PRODUCT_TYPE_URL);
    if ($google_shopping_taxonomy) {
       $google_shopping_types = explode("\n",$google_shopping_taxonomy);
       foreach ($google_shopping_types as $shopping_type) {
          if ((! isset($shopping_type[0])) || ($shopping_type[0] == '#'))
             continue;
          trim($shopping_type);
          $dialog->add_list_item($shopping_type,$shopping_type,
                                 $value == $shopping_type);
       }
    }
}

function update_google_product_status($db,$product_id,$status,&$error_msg)
{
    global $off_sale_option,$sold_out_option;

    if (! use_google_api($db)) return true;

    $query = 'select shopping_flags,google_shopping_id from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $product_info = $db->get_record($query);
    if (! $product_info) {
       $error_msg = $db->error;   return false;
    }
    if ((! $product_info['shopping_flags'] & 2) ||
        (! $product_info['google_shopping_id'])) return true;

    require_once '../cartengine/cartconfig-common.php';
    require_once 'googleshopping.php';
    $google_shopping = new GoogleShopping($db);
    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($sold_out_option)) $sold_out_option = 2;

    if (($status == $off_sale_option) || ($status == $sold_out_option)) {
       $item_id = $product_info['google_shopping_id'];
       if (! $google_shopping->delete_item($item_id,$product_id)) {
          log_error($google_shopping->error);
          if (($google_shopping->error !=
               'Google Shopping Error: Invalid request URI') &&
              (strpos($google_shopping->error,'item not found') === false)) {
             $error_msg = $google_shopping->error;   return false;
          }
       }
       log_activity('Deleted Google Shopping Item '.$item_id);
    }
    else {
       $shopping_item = $google_shopping->build_item_array($product_id);
       if (! $shopping_item) {
          $error_msg = $google_shopping->error;   return false;
       }
       if ($shopping_item['price'] == 0) return true;
       $item_id = $google_shopping->add_item($shopping_item);
       if (! $item_id) {
          $error_msg = $google_shopping->error;   return false;
       }
       log_activity('Added Google Shopping Item '.$item_id);
       $query = 'update products set google_shopping_id=?,' .
                'google_shopping_updated=? where id=?';
       $query = $db->prepare_query($query,$item_id,time(),$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error_msg = $db->error;   return false;
       }
    }
    return true;
}

function update_google_product_id($db,$row,$old_id,$new_id,$cgi)
{
    global $google_shopping;

    $shopping_flags = $row['shopping_flags'];
    if (! ($shopping_flags & 2)) return;
    if (empty($row['google_shopping_id'])) return;
    if (! use_google_api($db)) return true;

    if (! $google_shopping) {
       require_once 'googleshopping.php';
       $google_shopping = new GoogleShopping($db);
    }

    $item_id = $row['google_shopping_id'];
    reset_msg('   Updating Google Shopping Item '.$item_id,$cgi);
    if (! $google_shopping->delete_item($item_id,$old_id)) {
       if (strpos($google_shopping->error,'item not found') === false) {
          reset_msg('   Google Shopping Error: '.$google_shopping->error,$cgi);
       }
    }
    $shopping_item = $google_shopping->build_item_array($new_id);
    if (! $shopping_item)
       reset_msg('   Google Shopping Error: '.$google_shopping->error,$cgi);
    else {
       $item_id = $google_shopping->add_item($shopping_item);
       if (! $item_id)
          reset_msg('   Google Shopping Error: '.$google_shopping->error,$cgi);
       else {
          $query = 'update products set google_shopping_id=?,' .
                   'google_shopping_updated=? where id=?';
          $query = $db->prepare_query($query,$item_id,time(),$new_id);
          if (! $db->query($query)) {
             reset_msg('Query: '.$query,$cgi);
             reset_msg('Database Error: '.$db->error,$cgi);
             exit;
          }
       }
    }
}

function add_google_product($db,$product_record,&$error)
{
    global $google_shopping;

    if (! use_google_api($db)) return true;
    if (! $google_shopping) {
       require_once 'googleshopping.php';
       $google_shopping = new GoogleShopping($db);
    }

    $shopping_item = $google_shopping->build_item_array($product_id);
    if (! $shopping_item) {
       $error = $google_shopping->error;   return false;
    }
    if ($shopping_item['price'] == 0) {
       $google_shopping->log_activity(
          'Skipping Google Shopping Item Update because price is zero');
       $google_shopping->write_error($product_id,'Price is Zero');
    }
    else {
       $item_id = $google_shopping->add_item($shopping_item);
       if (! $item_id) {
          $error = $google_shopping->error;   return false;
       }
       $product_record['google_shopping_id']['value'] = $item_id;
       $query = 'update products set google_shopping_id=?,' .
                'google_shopping_updated=? where id=?';
       $query = $db->prepare_query($item_id,time(),$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = $db->error;   return false;
       }
    }
    return true;
}

function update_google_product($db,$product_record,$old_product_info,&$error,
                               $bulk_flag)
{
    global $google_shopping;

    if (! use_google_api($db)) return true;
    if (! $google_shopping) {
       require_once 'googleshopping.php';
       $google_shopping = new GoogleShopping($db);
    }

    if (isset($product_record['status']['value']))
       $product_status = $product_record['status']['value'];
    else $product_status = $old_product_info['status'];
    if (check_shopping_status($product_status)) {
       $product_id = $product_record['id']['value'];
       $shopping_item = $google_shopping->build_item_array($product_id);
       if (! $shopping_item) {
          $error = $google_shopping->error;   return false;
       }
       $add_google_item = false;
       if ($shopping_item['price'] == 0) {
          $google_shopping->log_activity(
             'Skipping Google Shopping Item Update because price is zero');
          $google_shopping->write_error($product_id,'Price is Zero');
       }
       else if (! empty($product_record['google_shopping_id']['value'])) {
          $item_id = $product_record['google_shopping_id']['value'];
          if (! $google_shopping->update_item($item_id,$shopping_item)) {
             if (($google_shopping->error == 'server failure') ||
                 ($google_shopping->error == 'item not found'))
                $add_google_item = true;
             else {
                $error = $google_shopping->error;   return false;
             }
          }
       }
       else $add_google_item = true;
       if ($add_google_item) {
          $item_id = $google_shopping->add_item($shopping_item);
          if (! $item_id) {
             if ($bulk_flag) {
                log_error($google_shopping->error);   return true;
             }
             $error = $google_shopping->error;   return false;
          }
          $product_record['google_shopping_id']['value'] = $item_id;
          $query = 'update products set google_shopping_id=?,' .
                   'google_shopping_updated=? where id=?';
          $query = $db->prepare_query($query,$item_id,time(),$product_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             $error = $db->error;   return false;
          }
       }
    }
    else if (isset($product_record['google_shopping_id']['value']) &&
             $product_record['google_shopping_id']['value']) {
       $item_id = $product_record['google_shopping_id']['value'];
       if (! $google_shopping->delete_item($item_id,$product_id)) {
          if (strpos($google_shopping->error,'item not found') === false) {
             $error = $google_shopping->error;   return false;
          }
       }
       $product_record['google_shopping_id']['value'] = '';
       $query = 'update products set google_shopping_id=null,' .
                'google_shopping_updated=null where id=?';
       $query = $db->prepare_query($query,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error = $db->error;   return false;
       }
    }
    return true;
}

function delete_google_product($db,$row,&$error)
{
    global $google_shopping;

    if (! use_google_api($db)) return true;
    if (! $google_shopping) {
       require_once 'googleshopping.php';
       $google_shopping = new GoogleShopping($db);
    }

    $item_id = $row['google_shopping_id'];
    $product_id = $row['id'];
    if (! $google_shopping->delete_item($item_id,$product_id)) {
       log_error($google_shopping->error);
       if (($google_shopping->error !=
            'Google Shopping Error: Invalid request URI') &&
           (strpos($google_shopping->error,'item not found') === false)) {
          $error = $google_shopping->error;   return false;
       }
    }
    return true;
}

?>
