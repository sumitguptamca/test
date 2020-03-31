<?php
/*
                    Inroads Shopping Cart - Amazon Commands Module

                         Written 2013-2019 by Randall Severy
                           Copyright 2013-2019 Inroads, LLC

     If running on CloudLinux, prevent killing of background tasks by editing
     /usr/sbin/kill_php_script and add: grep -v amazon/cmd.php

*/

chdir(dirname(__FILE__));   chdir('../..');
require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once '../engine/http.php';
if (file_exists('custom-config.php')) require_once 'custom-config.php';
require_once '../cartengine/cartconfig-common.php';
$shopping_cart = true;
require_once '../cartengine/products-common.php';
require_once '../cartengine/shopping-common.php';
require_once '../cartengine/utility.php';
require_once 'shopping/amazon/amazon.php';
require_once 'shopping/amazon/amazon-common.php';

if (isset($_SERVER['SERVER_SOFTWARE'])) $interactive = true;
else $interactive = false;

define('AMAZON_DOWNLOAD_PRODUCTS',0);
define('AMAZON_UPLOAD_PRODUCTS',1);
define('AMAZON_DELETE_PRODUCTS',2);
define('AMAZON_DOWNLOAD_ORDERS',3);
define('AMAZON_CONFIRM_SHIPMENTS',4);

define('LOG_PRODUCT_DIFFERENCES',1);

function update_sync_time($amazon,$sync_type)
{
    $query = 'select config_value from cart_config where ' .
             'config_name="amazon_sync_times"';
    $row = $amazon->db->get_record($query);
    if (! empty($row['config_value']))
       $sync_times = explode('|',$row['config_value']);
    else $sync_times = array();
    for ($index = 0;  $index <= $sync_type;  $index++) {
       if (! isset($sync_times[$index])) $sync_times[$index] = '';
    }
    $sync_times[$sync_type] = time();
    if ($row)
       $query = 'update cart_config set config_value=? where ' .
                'config_name="amazon_sync_times"';
    else $query = 'insert into cart_config values("amazon_sync_times",?)';
    $query = $amazon->db->prepare_query($query,implode('|',$sync_times));
    $amazon->db->log_query($query);
    $amazon->db->query($query);
}

function get_service_status($amazon)
{
    $status = $amazon->get_service_status();
    print 'Amazon Service Status: '.$status."\n";
}

function get_amazon_sku($product,$id_field)
{
    if (! empty($product['amazon_sku'])) $sku = $product['amazon_sku'];
    else if (! empty($product['part_number'])) $sku = $product['part_number'];
    else $sku = $product[$id_field];
    return $sku;
}

function append_image_data($amazon,$product,$image,$id_field='id')
{
    global $base_url;

    $sku = get_amazon_sku($product,$id_field);
    $image_url = $base_url.'images/original/'.rawurlencode($image);
    $xml_data = '<ProductImage>';
    $amazon->append_xml($xml_data,'SKU',$sku);
    $xml_data .= '<ImageType>Main</ImageType><ImageLocation>' .
       $image_url.'</ImageLocation></ProductImage>';
    return $xml_data;
}

function delete_products($amazon,&$result_data)
{
    $query = 'select * from amazon_pending_deletes';
    $products = $amazon->db->get_records($query,'id');
    if (empty($products)) {
       if (isset($amazon->db->error)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return;
       }
    }
    else {
       $xml_data = $amazon->open_envelope();
       $xml_data .= '<MessageType>Product</MessageType>';
       $message_id = 1;
       foreach ($products as $product) {
          $sku = get_amazon_sku($product,'product_id');
          $xml_data .= '<Message><MessageID>'.$message_id.'</MessageID>' .
             '<OperationType>Delete</OperationType><Product>';
          $amazon->append_xml($xml_data,'SKU',$sku);
          $xml_data .= "</Product></Message>\n";
          $message_id++;
       }
       $xml_data .= $amazon->close_envelope();

       $feed_id = $amazon->submit_feed('_POST_PRODUCT_DATA_',$xml_data);
       if (! $feed_id) return;
       if (! $amazon->append_results($feed_id,'Delete Product Feed',$result_data))
          return;

       $query = 'delete from amazon_pending_deletes where id in (?)';
       $delete_ids = array();
       foreach ($products as $product) {
          $delete_ids[] = $product['id'];   $product_id = $product['product_id'];
          log_activity('Deleted Product #'.$product_id.' from Amazon');
          write_product_activity('Deleted Product from Amazon',$product_id,
                                 $amazon->db);
       }
       $query = $amazon->db->prepare_query($query,$delete_ids);
       $amazon->db->log_query($query);
       if (! $amazon->db->query($query)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
    }

    $query = 'select * from amazon_pending_image_deletes';
    $images = $amazon->db->get_records($query,'id');
    if (empty($images)) {
       if (isset($amazon->db->error)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return;
       }
    }
    else {
       $xml_data = $amazon->open_envelope();
       $xml_data .= '<MessageType>ProductImage</MessageType>';
       $message_id = 1;
       foreach ($images as $image) {
          $xml_data .= '<Message><MessageID>'.$message_id.'</MessageID>' .
             '<OperationType>Delete</OperationType>';
          $xml_data .= append_image_data($amazon,$image,$image['filename'],
                                         'product_id');
          $xml_data .= '</Message>';
          $message_id++;
       }
       $xml_data .= $amazon->close_envelope();

       $feed_id = $amazon->submit_feed('_POST_PRODUCT_IMAGE_DATA_',$xml_data);
       if (! $feed_id) return;
       if (! $amazon->append_results($feed_id,'Delete Product Image Feed',
                                     $result_data)) return;

       $query = 'delete from amazon_pending_image_deletes where id in (?)';
       $delete_ids = array();
       foreach ($images as $image) {
          $delete_ids[] = $image['id'];   $product_id = $image['product_id'];
          log_activity('Deleted Product Image '.$image['filename'] .
                       ' for Product #'.$product_id.' from Amazon');
          write_product_activity('Deleted Product Image '.$image['filename'] .
                                 ' from Amazon',$product_id,$amazon->db);
       }
       $query = $amazon->db->prepare_query($query,$delete_ids);
       $amazon->db->log_query($query);
       if (! $amazon->db->query($query)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
    }

    if ((! empty($products)) || (! empty($images)))
       log_activity('Deleted Products from Amazon');
}

function append_product_data($amazon,$product)
{
    $product_id = $product['id'];
    $sku = get_amazon_sku($product,'id');
    if ($product['display_name']) $product_name = $product['display_name'];
    else $product_name = $product['name'];
    if ($product['long_description']) $description = $product['long_description'];
    else $description = $product['short_description'];
    $taxable = $product['taxable'];
    if (($taxable === '') || ($taxable === null)) $taxable = 1;
    if ($taxable == 1) $tax_code = 'A_GEN_TAX';
    else $tax_code = 'A_GEN_NOTAX';

    $xml_data = '<Product>';
    $amazon->append_xml($xml_data,'SKU',$sku);
    if ($product['amazon_asin'])
       $xml_data .= '<StandardProductID><Type>ASIN</Type><Value>' .
          $product['amazon_asin'].'</Value></StandardProductID>';
    else if ($product['shopping_gtin']) {
       $length = strlen($product['shopping_gtin']);
       if ($length == 14) $id_type = 'GTIN';
       else if ($length == 13) $id_type = 'EAN';
       else $id_type = 'UPC';
       $xml_data .= '<StandardProductID><Type>'.$id_type.'</Type><Value>' .
          $product['shopping_gtin'].'</Value></StandardProductID>';
    }
    $xml_data .= '<ProductTaxCode>'.$tax_code.'</ProductTaxCode>';
    if (! empty($product['shopping_condition']))
       $amazon->append_xml($xml_data,'Condition',
                           $product['shopping_condition']);
    if (! $product['amazon_asin']) {
       if (function_exists('custom_amazon_product_attributes'))
          $xml_data .= custom_amazon_product_attributes($amazon,$product);
       $xml_data .= '<DescriptionData>';
       $amazon->append_xml($xml_data,'Title',$product_name,250);
       $amazon->append_xml($xml_data,'Brand',$product['shopping_brand'],50);
       $amazon->append_xml($xml_data,'Description',$description,2000);
       if (function_exists('custom_amazon_description_data'))
          $xml_data .= custom_amazon_description_data($amazon,$product);
       if (! empty($product['weight']))
          $xml_data .= '<ShippingWeight unitOfMeasure="LB">' .
                       number_format($product['weight'],2,'.','') .
                       '</ShippingWeight>';
       $xml_data .= '<MSRP currency="USD">' .
          number_format($product['list_price'],2,'.','').'</MSRP>';
       $amazon->append_xml($xml_data,'Manufacturer',$product['shopping_brand']);
       $amazon->append_xml($xml_data,'MfrPartNumber',$product['shopping_mpn']);
       $amazon->append_xml($xml_data,'ItemType',$product['amazon_item_type']);
       $xml_data .= '</DescriptionData>';
       if (function_exists('custom_amazon_product_data'))
          $xml_data .= custom_amazon_product_data($amazon,$product);
    }
    $xml_data .= '</Product>';
    return $xml_data;
}

function append_inventory_data($amazon,$product)
{
    global $system_disabled;

    if (! isset($system_disabled)) $system_disabled = false;
    $sku = get_amazon_sku($product,'id');
    $xml_data = '<Inventory>';
    $amazon->append_xml($xml_data,'SKU',$sku);
    if ($system_disabled || ($product['status'] == $amazon->ul_inactive_status))
       $amazon->append_xml($xml_data,'Available','false');
    else if (empty($product['amazon_fba_flag'])) {
       if ($amazon->features & MAINTAIN_INVENTORY) {
          if (isset($product['qty'])) $qty = $product['qty'];
          else $qty = 1;
       }
       else $qty = 9999;
       $xml_data .= '<Quantity>'.$qty.'</Quantity>';
    }
//         Amazon wants all FBA listings to have a quantity of 0
    else $xml_data .= '<Quantity>0</Quantity>';
    if (isset($product['fulfillment_latency']))
       $latency = $product['fulfillment_latency'];
    else $latency = 3;
    $xml_data .= '<FulfillmentLatency>'.$latency .
                 '</FulfillmentLatency></Inventory>';
    return $xml_data;
}

function append_price_data($amazon,$product)
{
    $sku = get_amazon_sku($product,'id');
    if (isset($product['amazon_price']) && $product['amazon_price'] &&
        (floatval($product['amazon_price']) != 0))
       $price = $product['amazon_price'];
    else $price = $product['price'];
    $xml_data = '<Price>';
    $amazon->append_xml($xml_data,'SKU',$sku);
    $xml_data .= '<StandardPrice currency="USD">' .
       number_format($price,2,'.','').'</StandardPrice></Price>';
    return $xml_data;
}

function append_override_data($amazon,$product)
{
    global $base_url;

    $sku = get_amazon_sku($product,'id');
    $xml_data = '<Override>';
    $amazon->append_xml($xml_data,'SKU',$sku);
    $ship_option = 'Std US Dom';   // Legacy Accounts
//    $ship_option = 'Std Cont US Street Addr';   // Updated Accounts
    $xml_data .= '<ShippingOverride><ShipOption>'.$ship_option .
                 '</ShipOption><Type>';
    if ($product['shipping'] == -1) {
       $xml_data .= 'Additive';   $product['shipping'] = 0.00;
    }
    else $xml_data .= 'Exclusive';
    $xml_data .= '</Type><ShipAmount currency="USD">' .
       number_format($product['shipping'],2,'.','') .
       '</ShipAmount></ShippingOverride></Override>';
    return $xml_data;
}

function update_product_results($amazon,&$products,$product_update=false)
{
    $db = $amazon->db;   $update_time = time();
    foreach ($amazon->latest_results as $result) {
       $error = '';   $warning = '';
       $product_id = $result['message_id'];    
       if ($product_id > 1000000)
          $product_id = floor(($product_id - 1000000) / 100);
       if (! isset($products[$product_id])) continue;
       $product_info = $products[$product_id];
       if (! empty($result['code'])) {
          if ($result['code'] == 'Error') {
             $error = $result['message_code'].': '.$result['description'];
          }
          else if ($result['code'] == 'Warning')
             $warning = $result['message_code'].': '.$result['description'];
       }
       if ($product_update) {
          $activity = 'Product Uploaded to Amazon';
          if ($error) $activity .= ' (Error: '.$error.')';
          if ($warning) $activity .= ' (Warning: '.$warning.')';
          write_product_activity($activity,$product_id,$amazon->db);
          $products[$product_id]['activity'] = $activity;
       }
       else {
          if ($product_info['amazon_error']) {
             if ($error) $error = $product_info['amazon_error']."\n".$error;
             else $error = $product_info['amazon_error'];
          }
          if ($product_info['amazon_warning']) {
             if ($warning)
                $warning = $product_info['amazon_warning']."\n".$warning;
             else $warning = $product_info['amazon_warning'];
          }
       }
       if (($error == $product_info['amazon_error']) &&
           ($warning == $product_info['amazon_warning']) &&
           (! $product_update)) continue;
       $products[$product_id]['amazon_error'] = $error;
       $products[$product_id]['amazon_warning'] = $warning;
       $amazon_asin = null;
       if ((! $error) && $product_update &&
           empty($product_info['amazon_asin'])) {
          $sku = get_amazon_sku($product_info,'id');
          $product_details = $amazon->get_products('SellerSKU',array($sku));
          if (! empty($product_details[$sku]['asin'])) {
             $amazon_asin = $product_details[$sku]['asin'];
             $products[$product_id]['amazon_asin'] = $amazon_asin;
          }
       }
       $query = 'update products set amazon_error=?,amazon_warning=?';
       if ($product_update) $query .= ',amazon_updated=?';
       if ($amazon_asin) $query .= ',amazon_asin=?';
       $query .= ' where id=?';
       if ($product_update) {
          if ($amazon_asin)
             $query = $db->prepare_query($query,$error,$warning,$update_time,
                                         $amazon_asin,$product_id);
          else $query = $db->prepare_query($query,$error,$warning,$update_time,
                                           $product_id);
          $products[$product_id]['updated'] = true;
       }
       else $query = $db->prepare_query($query,$error,$warning,$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
    }
    if (! $product_update) return true;

    $ids = array();
    foreach ($products as $product_info) {
       if (! empty($product_info['updated'])) continue;
       $product_id = $product_info['id'];
       if ($product_id > 1000000)
          $product_id = floor(($product_id - 1000000) / 100);
       if (empty($product_info['amazon_asin'])) {
          $sku = get_amazon_sku($product_info,'id');
          $product_details = $amazon->get_products('SellerSKU',array($sku));
          if (! empty($product_details[$sku]['asin'])) {
             $amazon_asin = $product_details[$sku]['asin'];
             $query = 'update products set amazon_error=null,' .
                      'amazon_warning=null,amazon_updated=?,amazon_asin=? ' .
                      'where id=?';
             $query = $db->prepare_query($query,$update_time,$amazon_asin,
                                         $product_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                $amazon->process_error('Database Error: '.$amazon->db->error);
                return false;
             }
             $products[$product_id]['amazon_asin'] = $amazon_asin;
             continue;
          }
       }
       $ids[] = $product_id;
    }
    if (count($ids) > 0) {
       $query = 'update products set amazon_error=null,amazon_warning=null,' .
                'amazon_updated=? where id in (?)';
       $query = $db->prepare_query($query,$update_time,$ids);
       $db->log_query($query);
       if (! $db->query($query)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
    }
    return true;
}

function upload_products($amazon,&$result_data)
{
    $db = $amazon->db;
    $upload_time = time();
    $amazon_flags = get_cart_config_value('amazon_flags',$db);
    $where = '(isnull(p.amazon_updated) or (p.last_modified>p.amazon_updated) ' .
             'or (p.id in (select i.parent from product_inventory i where ' .
             '(i.last_modified>p.amazon_updated)))) and ';
    if ($amazon_flags & UPLOAD_ONLY_WITH_ASIN)
       $where .= '((p.amazon_asin!="") and (not isnull(p.amazon_asin))) and ';
    $where .= '(p.shopping_flags & 1) and (isnull(p.status) or ' .
              '(p.status!=?)) order by p.name';
    $query = 'select * from products p where '.$where;
    $query = $db->prepare_query($query,$amazon->ul_delete_status);
    $products = $db->get_records($query,'id');
    if ((! $products) || (count($products) == 0)) {
       if (isset($db->error))
          $amazon->process_error('Database Error: '.$db->error);
       return;
    }
    $shipping_override = false;
    foreach ($products as $index => $row) {
       if (function_exists('update_shopping_product_row')) {
          update_shopping_product_row(AMAZON_FLAG,$db,$products[$index]);
          $row = $products[$index];
       }
       if (empty($row['price'])) {
          unset($products[$index]);   continue;
       }
       if (isset($row['shipping'])) $shipping_override = true;
    }
    if (count($products) == 0) return;

    $query = 'select i.* from product_inventory i join products p on ' .
             'p.id=i.parent where isnull(p.amazon_updated) or ' .
             '(i.last_modified>p.amazon_updated) or ' .
             '(p.last_modified>p.amazon_updated) order by i.parent,i.id';
    $rows = $db->get_records($query);
    if ($rows) foreach ($rows as $row) {
       $product_id = $row['parent'];
       if (isset($products[$product_id]) &&
           (! isset($products[$product_id]['part_number']))) {
          $products[$product_id]['part_number'] = $row['part_number'];
          $products[$product_id]['qty'] = $row['qty'];
          $products[$product_id]['weight'] = $row['weight'];
       }
    }

    $query = 'select i.* from images i join products p on p.id=i.parent ' .
       'where (i.parent_type=1) and (isnull(p.amazon_updated) or ' .
       '(p.last_modified>p.amazon_updated) or ' .
       '(p.id in (select pi.parent from product_inventory pi where ' .
       '(pi.last_modified>p.amazon_updated)))) order by i.parent,i.id';
    $rows = $db->get_records($query);
    if ($rows) foreach ($rows as $row) {
       $product_id = $row['parent'];
       if (! isset($products[$product_id])) continue;
       if (! isset($products[$product_id]['images']))
          $products[$product_id]['images'] = array();
       $products[$product_id]['images'][] = $row['filename'];
    }

    $xml_data = $amazon->open_envelope();
    $xml_data .= '<MessageType>Product</MessageType><PurgeAndReplace>' .
       'false</PurgeAndReplace>';
    foreach ($products as $product) {
       $xml_data .= '<Message><MessageID>'.$product['id'].'</MessageID>' .
          '<OperationType>Update</OperationType>';
       $xml_data .= append_product_data($amazon,$product);
       $xml_data .= '</Message>';
    }
    $xml_data .= $amazon->close_envelope();

    $feed_id = $amazon->submit_feed('_POST_PRODUCT_DATA_',$xml_data);
    if (! $feed_id) return;
    if (! $amazon->append_results($feed_id,'Product Feed',$result_data))
       return;
    if (! update_product_results($amazon,$products,true)) return;

    $num_uploaded = 0;   $num_errors = 0;
    foreach ($products as $product) {
       if (empty($product['activity']))
          write_product_activity('Product Uploaded to Amazon',
                                 $product['id'],$db);
       if (! empty($product['amazon_error'])) {
          $num_errors++;   continue;
       }
       $num_uploaded++;
    }
    if ($num_uploaded == 0) {
       log_activity('Unable to Import '.$num_errors.' Products into Amazon');
       return;
    }

    $xml_data = $amazon->open_envelope();
    $xml_data .= '<MessageType>Inventory</MessageType>';
    foreach ($products as $product) {
       if (! empty($product['amazon_error'])) continue;
       $xml_data .= '<Message><MessageID>'.$product['id'].'</MessageID>' .
          '<OperationType>Update</OperationType>';
       $xml_data .= append_inventory_data($amazon,$product);
       $xml_data .= '</Message>';
    }
    $xml_data .= $amazon->close_envelope();

    $inv_feed_id = $amazon->submit_feed('_POST_INVENTORY_AVAILABILITY_DATA_',
                                        $xml_data,false);
    if (! $inv_feed_id) return;

    $xml_data = $amazon->open_envelope();
    $xml_data .= '<MessageType>Price</MessageType>';
    foreach ($products as $product) {
       if (! empty($product['amazon_error'])) continue;
       $xml_data .= '<Message><MessageID>'.$product['id'].'</MessageID>' .
          '<OperationType>Update</OperationType>';
       $xml_data .= append_price_data($amazon,$product);
       $xml_data .= '</Message>';
    }
    $xml_data .= $amazon->close_envelope();

    $price_feed_id = $amazon->submit_feed('_POST_PRODUCT_PRICING_DATA_',
                                          $xml_data,false);
    if (! $price_feed_id) return;

    $xml_data = $amazon->open_envelope();
    $xml_data .= '<MessageType>ProductImage</MessageType>';
    $num_images = 0;
    foreach ($products as $product) {
       if (! empty($product['amazon_error'])) continue;
       if (! empty($product['images'])) {
          $index = 1;
          foreach ($product['images'] as $image) {
             $message_id = 1000000 + ($product['id'] * 100) + $index;
             $xml_data .= '<Message><MessageID>'.$message_id .
                          '</MessageID><OperationType>Update</OperationType>';
             $xml_data .= append_image_data($amazon,$product,$image);
             $xml_data .= '</Message>';
             $index++;   $num_images++;
          }
       }
    }
    $xml_data .= $amazon->close_envelope();

    if ($num_images > 0) {
       $image_feed_id = $amazon->submit_feed('_POST_PRODUCT_IMAGE_DATA_',
                                             $xml_data,false);
       if (! $image_feed_id) return;
    }
    else $image_feed_id = 0;

    if ($shipping_override) {
       $xml_data = $amazon->open_envelope();
       $xml_data .= '<MessageType>Override</MessageType>';
       foreach ($products as $product) {
          if (! empty($product['amazon_error'])) continue;
          if (! isset($product['shipping'])) continue;
          $xml_data .= '<Message><MessageID>'.$product['id'].'</MessageID>' .
             '<OperationType>Update</OperationType>';
          $xml_data .= append_override_data($amazon,$product);
          $xml_data .= '</Message>';
       }
       $xml_data .= $amazon->close_envelope();

       $override_feed_id = $amazon->submit_feed('_POST_PRODUCT_OVERRIDES_DATA_',
                                                $xml_data,false);
       if (! $override_feed_id) return;
    }

    $inv_status = '';   $price_status = '';
    if ($image_feed_id) $image_status = '';
    else $image_status = '_DONE_';
    if ($shipping_override) $override_status = '';
    else $override_status = '_DONE_';
    while (($inv_status != '_DONE_') || ($price_status != '_DONE_') ||
           ($image_status != '_DONE_') || ($override_status != '_DONE_')) {
       $feed_ids = array($inv_feed_id,$price_feed_id);
       if ($image_feed_id) $feed_ids[] = $image_feed_id;
       if ($shipping_override) $feed_ids[] = $override_feed_id;
       $feed_list = $amazon->get_feed_list($feed_ids);
       if (! $feed_list) return;
       $feed_results = array();
       foreach ($feed_list as $result) {
          $feed_id = $amazon->parse_tag($result,'FeedSubmissionId');
          $status = $amazon->parse_tag($result,'FeedProcessingStatus');
          if ($feed_id == $inv_feed_id) $inv_status = $status;
          else if ($feed_id == $price_feed_id) $price_status = $status;
          else if ($feed_id == $image_feed_id) $image_status = $status;
          else if ($shipping_override && ($feed_id == $override_feed_id))
             $override_status = $status;
       }
       if (($inv_status != '_DONE_') || ($price_status != '_DONE_') ||
           ($image_status != '_DONE_') || ($override_status != '_DONE_'))
          sleep(30);
    }

    if (! $amazon->append_results($inv_feed_id,'Inventory Feed',$result_data))
       return;
    if (! update_product_results($amazon,$products)) return;
    if (! $amazon->append_results($price_feed_id,'Price Feed',$result_data))
       return;
    if (! update_product_results($amazon,$products)) return;
    if ($image_feed_id &&
        (! $amazon->append_results($image_feed_id,'Image Feed',$result_data)))
       return;
    if (! update_product_results($amazon,$products)) return;
    if ($shipping_override) {
       if (! $amazon->append_results($override_feed_id,'Override Feed',
                                     $result_data)) return;
       if (! update_product_results($amazon,$products)) return;
    }

    $log_string = 'Imported '.$num_uploaded.' Products into Amazon';
    if ($num_errors) $log_string .= ' (# Errors: '.$num_errors.')';
    log_activity($log_string);
}

function get_feed_list($amazon)
{
    $feed_list = $amazon->get_feed_list();
    if (! $feed_list) return;
    foreach ($feed_list as $feed_data) {
       $id = $amazon->parse_tag($feed_data,'FeedSubmissionId');
       $type = $amazon->parse_tag($feed_data,'FeedType');
       $status = $amazon->parse_tag($feed_data,'FeedProcessingStatus');
       $date = $amazon->parse_tag($feed_data,'CompletedProcessingDate');
       print $id.'   '.$type.'   '.$status.'   '.$date."\n";
    }
}

function get_feed_result($amazon,$argv)
{
    if (! isset($argv[2])) {
       print "Feed ID must be specified\n";   return;
    }
    $feed_id = $argv[2];
    $results = $amazon->feed_results($feed_id);
    if ($results === null) {
       $amazon->process_error('Amazon Error: '.$amazon->error,true);
       return;
    }
    foreach ($results as $result) {
       print "\n".$result['code'].': '.$result['message_code'].': ' .
             $result['description'];
       if ($result['sku']) print ' (SKU: '.$result['sku'].')';
       print "\n";
    }
}

function download_orders($amazon)
{
    global $shipped_option,$fedex_option_ids;

    if (! isset($shipped_option)) $shipped_option = 1;
    $config_values = load_cart_config_values($amazon->db);
    $amazon_last_download = get_row_value($config_values,
                                          'amazon_last_download');
    if (! $amazon_last_download) {
       $edit_type = ADDRECORD;   $amazon_last_download = date('c',0);
    }
    else $edit_type = UPDATERECORD;
    $shipping_map = get_row_value($config_values,'amazon_shipping_map');
    $shipping_map = explode('|',$shipping_map);
    $map = array();
    foreach ($shipping_map as $map_entry) {
       $map_entry = explode(':',$map_entry);
       if (count($map_entry) != 3) continue;
       $map[$map_entry[0]] = array('carrier'=>$map_entry[1],
                                   'method'=>$map_entry[2]);
    }

    $orders = $amazon->get_orders($amazon_last_download);
    if (! $orders) return;

    require_once '../cartengine/api.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');
    $address_fields = array('address1','address2','city','state','zipcode',
                            'country');
    $states = get_state_array(false,$amazon->db);
    if (! $states) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    foreach ($states as $index => $state_info) {
       $states[$index]['l_code'] = strtolower($state_info['code']);
       $states[$index]['name'] = strtolower($state_info['name']);
    }

    $num_added = 0;
    foreach ($orders as $order_data) {
       $order_info = array();
       $billing_info = array();   $shipping_info = array();
       $channel = $amazon->parse_tag($order_data,'FulfillmentChannel');
       if ($channel == 'MFN') $external_source = 'Amazon';
       else $external_source = 'Amazon FBA';
       $status = $amazon->parse_tag($order_data,'OrderStatus');
       if (($channel == 'MFN') && ($status != 'Unshipped')) continue;
       if (($channel == 'AFN') && ($status != 'Shipped')) continue;
       $order_id = $amazon->parse_tag($order_data,'AmazonOrderId');
       $query = 'select id from orders where external_source=? and ' .
                'external_id=?';
       $query = $amazon->db->prepare_query($query,$external_source,$order_id);
       $dup_row = $amazon->db->get_record($query);
       if ($dup_row) {
          log_activity('Skipping Duplicate Amazon Order #'.$order_id .
                       ' (Order #'.$dup_row['id'].')');
          continue;
       }
       $total_info = $amazon->parse_tag($order_data,'OrderTotal');
       $total = floatval($amazon->parse_tag($total_info,'Amount'));
       $currency = $amazon->parse_tag($total_info,'CurrencyCode');
       $name = $amazon->parse_tag($order_data,'Name');
       $space_pos = strpos($name,' ');
       if ($space_pos !== false) {
          $first_name = substr($name,0,$space_pos);
          $last_name = substr($name,$space_pos + 1);
       }
       else {
          $first_name = '';   $last_name = $name;
       }
       $country_code = $amazon->parse_tag($order_data,'CountryCode');
       $country_info = find_country_info($country_code,$amazon->db);
       if ($country_info) $country = $country_info['id'];
       else $country = 1;
       $address1 = $amazon->parse_tag($order_data,'AddressLine1');
       $address2 = $amazon->parse_tag($order_data,'AddressLine2');
       $address3 = $amazon->parse_tag($order_data,'AddressLine3');
       $order_date = strtotime($amazon->parse_tag($order_data,'PurchaseDate'));

       $order_info['email'] = $amazon->parse_tag($order_data,'BuyerEmail');
       $order_info['fname'] = $first_name;
       $order_info['lname'] = $last_name;
       if ($address3) $order_info['company'] = $address1;
       $order_info['external_source'] = $external_source;
       $order_info['external_id'] = $order_id;
       $order_info['currency'] = $currency;
       $order_info['total'] = $total;
       $order_info['payment_amount'] = $total;
       $order_info['payment_method'] = 'Amazon';
       if ($status == 'Shipped') {
          $order_info['status'] = $shipped_option;
          $shipped_date = strtotime($amazon->parse_tag($order_data,
                                                       'LatestShipDate'));
          $order_info['shipped_date'] = $shipped_date;
       }
       else $order_info['status'] = 0;
       $order_info['order_date'] = $order_date;
       $order_info['payment_date'] = $order_date;
       $ship_service_level = $amazon->parse_tag($order_data,
                                                'ShipServiceLevel');
       if (! isset($map[$ship_service_level])) {
          if (substr($ship_service_level,0,3) == 'Exp')
             $ship_service_level = 'Expedited';
          else if (substr($ship_service_level,0,6) == 'Second')
             $ship_service_level = 'SecondDay';
          else $ship_service_level = 'Standard';
       }
       if (isset($map[$ship_service_level])) {
          $shipping_carrier = $map[$ship_service_level]['carrier'];
          $order_info['shipping_carrier'] = $shipping_carrier;
          $shipping_method = $map[$ship_service_level]['method'];
          if (shipping_module_event_exists('available_methods',
                                           $shipping_carrier)) {
             $available_methods = $shipping_carrier.'_available_methods';
             $shipping_methods = $available_methods();
             if (isset($shipping_methods[$shipping_method]))
                $shipping_method .= '|'.$shipping_methods[$shipping_method];
          }
          $order_info['shipping_method'] = $shipping_method;
       }

       if ($address3) {
          $billing_info['address1'] = $address2;
          $billing_info['address2'] = $address3;
       }
       else {
          $billing_info['address1'] = $address1;
          if ($address2) $billing_info['address2'] = $address2;
       }
       $billing_info['city'] = $amazon->parse_tag($order_data,'City');
       $state = $amazon->parse_tag($order_data,'StateOrRegion');
       if ($country == 1) {
          $state = str_replace('.','',$state);
          $lower_state = strtolower($state);
          foreach ($states as $state_info) {
             if ($lower_state == $state_info['l_code']) {
                $state = $state_info['code'];   break;
             }
             if ($lower_state == $state_info['name']) {
                $state = $state_info['code'];   break;
             }
          }
       }
       $billing_info['state'] = $state;
       $billing_info['zipcode'] = $amazon->parse_tag($order_data,'PostalCode');
       $billing_info['country'] = $country;
       $billing_info['phone'] = $amazon->parse_tag($order_data,'Phone');

       $shipping_info['profilename'] = 'Default';
       if ($address3) $shipping_info['company'] = $address1;
       foreach ($address_fields as $fieldname)
          if (isset($billing_info[$fieldname]))
             $shipping_info[$fieldname] = $billing_info[$fieldname];
       $shipping_info['address_type'] = get_address_type();
       $shipping_info['default_flag'] = 1;

       $items = $amazon->get_order_items($order_id);
       if (! $items) return;
       $order_items = array();
       $subtotal = 0.00;   $tax = 0.00;   $shipping = 0.00;   $discount = 0.00;
       foreach ($items as $item_data) {
          $order_item = array();
          $sku = $amazon->parse_tag($item_data,'SellerSKU');

          $query = 'select parent,attributes from product_inventory ' .
                   'where parent in (select id from products where ' .
                   'amazon_sku=?) limit 1';
          $query = $amazon->db->prepare_query($query,$sku);
          $inv_row = $amazon->db->get_record($query);
          if ($inv_row) {
             $order_item['product_id'] = $inv_row['parent'];
             $order_item['attributes'] = $inv_row['attributes'];
          }
          else {
             $query = 'select parent,attributes from product_inventory ' .
                      'where part_number=?';
             $query = $amazon->db->prepare_query($query,$sku);
             $inv_row = $amazon->db->get_record($query);
             if ($inv_row) {
                $order_item['product_id'] = $inv_row['parent'];
                $order_item['attributes'] = $inv_row['attributes'];
            }
          }
          $order_item['product_name'] = $amazon->parse_tag($item_data,'Title');
          $order_item['qty'] = $amazon->parse_tag($item_data,'QuantityOrdered');
          $price_info = $amazon->parse_tag($item_data,'ItemPrice');
          $item_total = floatval($amazon->parse_tag($price_info,'Amount'));
          $order_item['price'] = $item_total / $order_item['qty'];
          $subtotal += $item_total;
          $price_info = $amazon->parse_tag($item_data,'ShippingPrice');
          $shipping += floatval($amazon->parse_tag($price_info,'Amount'));
          $price_info = $amazon->parse_tag($item_data,'ShippingDiscount');
          $shipping -= floatval($amazon->parse_tag($price_info,'Amount'));
          $price_info = $amazon->parse_tag($item_data,'ItemTax');
          $tax += floatval($amazon->parse_tag($price_info,'Amount'));
          $price_info = $amazon->parse_tag($item_data,'ShippingTax');
          if ($price_info)
             $tax += floatval($amazon->parse_tag($price_info,'Amount'));
          $price_info = $amazon->parse_tag($item_data,'PromotionDiscount');
          $discount += floatval($amazon->parse_tag($price_info,'Amount'));
          $order_items[] = $order_item;
       }
       $order_info['subtotal'] = $subtotal;
       if ($tax) $order_info['tax'] = $tax;
       if ($shipping) $order_info['shipping'] = $shipping;
       if ($discount) {
          $order_info['discount_name'] = 'Promotion Discount';
          $order_info['discount_amount'] = $discount;
       }
       $order_payments = null;   $order_shipments = null;
       $order_id = $quikweb->add_order($order_info,$billing_info,
                      $shipping_info,$order_items,$order_payments,
                      $order_shipments);
       if (! $order_id) {
          $amazon->process_error('QuikWeb Error Adding Order: ' .
                                 $quikweb->error);
          return;
       }
       if (! $quikweb->send_order_notifications($order_id,false)) {
          $amazon->process_error('QuikWeb Error Sending Order Notifications ' .
                                 'for Order #'.$order_id.': '.$quikweb->error);
          return;
       }
       $num_added++;
    }

    if ($edit_type == ADDRECORD)
       $query = 'insert into cart_config values("amazon_last_download",?)';
    else $query = 'update cart_config set config_value=? where config_name="' .
                  'amazon_last_download"';
    $query = $amazon->db->prepare_query($query,$amazon_last_download);
    if ($num_added > 0) $amazon->db->log_query($query);
    if (! $amazon->db->query($query)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);   return;
    }
    if ($num_added > 0) log_activity('Downloaded Orders from Amazon');
}

function confirm_shipments($amazon,&$result_data)
{
    global $shipped_option;

    if (! isset($shipped_option)) $shipped_option = 1;
    $query = 'select id,external_id from orders where (status=?) and ' .
             '(external_source="Amazon") and (external_id!=0) and ' .
             '(not isnull(external_id)) and (isnull(flags) or (not flags&1))';
    $query = $amazon->db->prepare_query($query,$shipped_option);
    $orders = $amazon->db->get_records($query,'id');
    if (! $orders) {
       if (isset($amazon->db->error))
          $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    if (count($orders) == 0) return;
    $order_ids = array();
    foreach ($orders as $order) $order_ids[] = $order['id'];
    $query = 'select * from order_shipments where parent in (?)';
    $query = $amazon->db->prepare_query($query,$order_ids);
    $order_shipments = $amazon->db->get_records($query);
    if (! $order_shipments) {
       if (isset($amazon->db->error))
          $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $xml_data = $amazon->open_envelope();
    $xml_data .= '<MessageType>OrderFulfillment</MessageType>';
    $orders = array();
    foreach ($orders as $order) {
       $order_id = $order['id'];   $shipment_info = null;
       foreach ($order_shipments as $shipment) {
          if ($shipment['parent'] == $order['id']) {
             $shipment_info = $shipment;   break;
          }
       }
       if (empty($shipment_info['shipped_date'])) {
          unset($orders[$order_id]);   continue;
       }
       $xml_data .= '<Message><MessageID>'.$order_id .
                    '</MessageID><OrderFulfillment>';
       $amazon->append_xml($xml_data,'AmazonOrderID',$order['external_id']);
       $amazon->append_xml($xml_data,'FulfillmentDate',
                           date('c',$shipment_info['shipped_date']));
       if ($shipment_info['shipping_carrier'] && $shipment_info['tracking']) {
          $shipping_carrier = $shipment_info['shipping_carrier'];
          if (shipping_module_event_exists('format_shipping_field',
                                           $shipping_carrier)) {
             $format_shipping_field = $shipping_module.'_format_shipping_field';
             $carrier_code = $format_shipping_field($shipment_info,
                                                    'shipping_carrier');
          }
          else $carrier_code = null;
          if ($carrier_code) {
             $xml_data .= '<FulfillmentData>';
             $amazon->append_xml($xml_data,'CarrierCode',$carrier_code);
             $shipping_method = $format_shipping_field($shipment_info,
                                                       'shipping_method');
             if ($shipping_method)
                $amazon->append_xml($xml_data,'ShippingMethod',
                                    $shipping_method);
             if ($shipment_info['tracking'])
                $amazon->append_xml($xml_data,'ShipperTrackingNumber',
                                    $shipment_info['tracking']);
             $xml_data .= '</FulfillmentData>';
          }
       }
       $xml_data .= "</OrderFulfillment></Message>\n";
    }
    $xml_data .= $amazon->close_envelope();
    if (count($orders) == 0) return;

    $feed_id = $amazon->submit_feed('_POST_ORDER_FULFILLMENT_DATA_',$xml_data);
    if (! $feed_id) return;
    $results = $amazon->feed_results($feed_id);
    if ($results === null) {
       $amazon->process_error('Amazon Error: '.$amazon->error);   return null;
    }
    foreach ($results as $result) {
       $message_id = $result['message_id'];
       $result['label'] = 'Confirm Shipment';
       $result['order_id'] = $message_id;
       $result_data[] = $result;
       unset($orders[$message_id]);
    }
    $current_time = time();
    foreach ($orders as $order_id => $order_info) {
       $query = 'update orders set flags=ifnull(flags|1,1),updated_date=? ' .
                'where id=?';
       $query = $amazon->db->prepare_query($query,$current_time,$order_id);
       $amazon->db->log_query($query);
       if (! $amazon->db->query($query)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
       log_activity('Confirmed Shipment with Amazon for Order #'.$order_id);
    }
}

function get_categories($amazon)
{
    $store_url = get_cart_config_value('amazon_store_url',$amazon->db);
    if (! $store_url) {
       print "No Amazon Store URL Configured<br>\n";   return;
    }
    $categories = $amazon->scrape_categories($store_url);
    print 'Categories = '.print_r($categories,true)."\n";
}

function download_categories($amazon)
{
    global $top_category;

    log_activity('Started Category Download from Amazon');
    $store_url = get_cart_config_value('amazon_store_url',$amazon->db);
    if (! $store_url) {
       print "No Amazon Store URL Configured<br>\n";   return;
    }
    $categories = $amazon->scrape_categories($store_url);
    if (! $categories) return;

    require_once '../cartengine/api.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');

    $query = 'select id,amazon_asin from products';
    $products = $amazon->db->get_records($query,'amazon_asin','id');

    foreach ($categories as $index => $category_info) {
       $categories[$index]['seo_url'] =
          str_replace('_','-',$category_info['seo_url']);
       $categories[$index]['display_name'] = $category_info['name'];
       if ($category_info['parent'] != -1)
          $categories[$index]['name'] =
             $categories[$category_info['parent']]['name'].' > ' .
             $category_info['name'];
    }

    foreach ($categories as $index => $category_info) {
       $category_id = $quikweb->add_category($category_info);
       if (! $category_id) {
          print 'QuikWeb Error adding Category '.$category_info['name'].': ' .
                $quikweb->error."\n";
          return;
       }
       $categories[$index]['id'] = $category_id;
       if ($category_info['parent'] != -1) {
          $parent = $categories[$category_info['parent']]['id'];
          if (! $quikweb->add_subcategory($parent,$category_id)) {
             print 'QuikWeb Error adding SubCategory #'.$category_id .
                   ' to Category #'.$parent.': '.$quikweb->error."\n";
             return;
          }
       }
       if (! empty($category_info['asins'])) {
          foreach ($category_info['asins'] as $asin) {
             if (isset($products[$asin])) {
                $product_id = $products[$asin];
                if (! $quikweb->add_category_product($category_id,
                                                     $product_id)) {
                   print 'QuikWeb Error adding Product #'.$product_id .
                         ' to Category #'.$parent.': '.$quikweb->error."\n";
                   return;
                }
             }
          }
       }
       print 'Added Category '.$category_info['name']."\n";
    }
    print "Categories Downloaded\n";
    log_activity('Finished Category Download from Amazon');
}

function download_product_image($amazon,$image_url,&$image_record,
                                $images,$config_values)
{
    $image_filename = rawurldecode(basename($image_url));
    $start_pos = strpos($image_filename,'_UL1');
    if ($start_pos === false) $start_pos = strpos($image_filename,'_SL1');
    if ($start_pos !== false) {
       $end_pos = strpos($image_filename,'_.',$start_pos);
       if ($end_pos !== false)
          $image_filename = substr($image_filename,0,$start_pos) .
                            substr($image_filename,$end_pos + 2);
    }
    $local_filename = '../images/original/'.$image_filename;
    $last_modified = $amazon->get_last_modified($image_url);
    if ($last_modified == -1) {
       $amazon->process_error($amazon->error);   return true;
    }
    if (file_exists($local_filename))
       $local_modified = filemtime($local_filename);
    else $local_modified = 0;
    $product_id = $image_record['parent']['value'];
    if ((! $local_modified) || ($local_modified != $last_modified)) {
       $image_data = @file_get_contents($image_url);
       if (! $image_data) {
          $amazon->process_error('Unable to download Amazon image ' .
                                 $image_url);
          return true;
       }
       file_put_contents($local_filename,$image_data);
       if ($last_modified) touch($local_filename,$last_modified);
       if (process_image($image_filename,$local_filename,null,null,
                         null,null,$config_values,false,null)) {
          log_activity('Updated Product Image '.$image_filename);
          if ($amazon->debug)
             $amazon->log_activity('Downloaded Product Image ' .
                $image_filename.' ('.$last_modified.' <> ' .
                $local_modified.')');
       }
       write_product_activity('Downloaded Product Image '.$image_filename .
                              ' from Amazon',$product_id,$amazon->db);
       $new_image = true;
    }
    else $new_image = false;
    if ($images && isset($images[$product_id]) &&
        in_array($image_filename,$images[$product_id])) return true;
    $image_record['filename']['value'] = $image_filename;
    $image_record['sequence']['value']++;
    if (! $amazon->db->insert('images',$image_record)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return false;
    }
    if (! $new_image)
       write_product_activity('Added Existing Product Image '.$image_filename .
                              ' from Amazon',$product_id,$amazon->db);
    return true;
}

function data_record_definition()
{
    $data_record = array();
    $data_record['sequence'] = array('type' => INT_TYPE);
    $data_record['parent'] = array('type' => INT_TYPE);
    $data_record['data_type'] = array('type' => INT_TYPE);
    $data_record['label'] = array('type' => CHAR_TYPE);
    $data_record['data_value'] = array('type' => CHAR_TYPE);
    return $data_record;
}

function update_product_features($amazon,$features,$product_id)
{
    $query = 'delete from product_data where (parent=?) and (data_type=?)';
    $query = $amazon->db->prepare_query($query,$product_id,FEATURES_DATA_TYPE);
    $amazon->db->log_query($query);
    if (! $amazon->db->query($query)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return false;
    }
    $data_record = data_record_definition();
    $data_record['parent']['value'] = $product_id;
    $data_record['data_type']['value'] = FEATURES_DATA_TYPE;
    $sequence = 1;
    foreach ($features as $feature) {
       $data_record['label']['value'] = $feature;
       $data_record['sequence']['value'] = $sequence++;
       if (! $amazon->db->insert('product_data',$data_record)) {
          $amazon->process_error('Database Error: '.$amazon->db->error);
          return false;
       }
    }
    return true;
}

function build_product_info($amazon,$product,$inventory,$product_map,
   $inventory_map,&$product_info,&$inventory_info)
{
    $sku = $product['sku'];
    $product_info = array();   $inventory_info = array();
    foreach ($product_map as $amazon_field => $field_name)
       if (isset($product[$amazon_field]))
          $product_info[$field_name] = $product[$amazon_field];
    if (empty($product_info['name'])) $product_info['name'] = $sku;
    if (! empty($product_info['shopping_gender'])) {
       $parts = explode('-',$product_info['shopping_gender']);
       $gender = strtolower($parts[0]);
       if (strpos($gender,'women') !== false) $gender = 'female';
       else if (strpos($gender,'woman') !== false) $gender = 'female';
       else if (strpos($gender,'girl') !== false) $gender = 'female';
       else if (strpos($gender,'men') !== false) $gender = 'male';
       else if (strpos($gender,'man') !== false) $gender = 'male';
       else if (strpos($gender,'boy') !== false) $gender = 'male';
       else if (strpos($gender,'unisex') !== false) $gender = 'unisex';
       else if (strlen($gender) > 20) $gender = substr($gender,0,20);
       if (isset($parts[1])) $age = strtolower($parts[0]);
       else $age = 'adult';
       $product_info['shopping_gender'] = $gender;
       $product_info['shopping_age'] = $age;
    }
    foreach ($inventory_map as $amazon_field => $field_name)
       if (isset($product[$amazon_field]))
          $inventory_info[$field_name] = $product[$amazon_field];
    if (! empty($inventory_info['weight']))
       $inventory_info['weight'] = number_format($inventory_info['weight'],
                                                 2,'.','');
    if (function_exists('custom_amazon_update_product_info'))
       custom_amazon_update_product_info($amazon,$product,$product_info,
                                         $inventory_info);
    if (isset($inventory[$sku])) {
       $product_id = $inventory[$sku]['parent'];
       $product_info['id'] = $product_id;
       $inventory_info['id'] = $inventory[$sku]['id'];
       if (! empty($inventory[$sku]['amazon_price'])) {
          $product_info['amazon_price'] = $product_info['price'];
          unset($product_info['price']);
       }
       unset($inventory_info['qty']);
    }
    else $product_info['shopping_flags'] = 1;
}

function update_scraped_product_info($amazon,$product,$inventory,$product_map,
   $inventory_map,&$product_info,&$inventory_info)
{
    foreach ($product_map as $amazon_field => $field_name)
       if (isset($product[$amazon_field]) &&
           (! isset($product_info[$field_name])))
          $product_info[$field_name] = $product[$amazon_field];
    $product_info['seo_url'] = str_replace('_','-',$product_info['seo_url']);
    if (isset($inventory_info['weight'])) $weight_set = true;
    else $weight_set = false;
    foreach ($inventory_map as $amazon_field => $field_name)
       if (isset($product[$amazon_field]) &&
           (! isset($inventory_info[$field_name])))
          $inventory_info[$field_name] = $product[$amazon_field];
    if ((! $weight_set) && (! empty($inventory_info['weight'])))
       $inventory_info['weight'] = number_format($inventory_info['weight'],
                                                 2,'.','');
    if (function_exists('custom_amazon_update_scraped_product_info'))
       custom_amazon_update_scraped_product_info($amazon,$product,
          $product_info,$inventory_info);
}

function product_unchanged($existing_products,$existing_inventory,
                           $product_info,$inventory_info)
{
    if (empty($product_info['id'])) return false;
    $product_id = $product_info['id'];
    if (! isset($existing_products[$product_id])) return false;
    if (empty($inventory_info['id'])) return false;
    $inv_id = $inventory_info['id'];
    if (! isset($existing_inventory[$inv_id])) return false;
    $product = $existing_products[$product_id];
    foreach ($product_info as $field_name => $field_value) {
       if (! array_key_exists($field_name,$product)) continue;
       if ($product[$field_name] != $field_value) {
          if (LOG_PRODUCT_DIFFERENCES)
             log_activity('   Field '.$field_name.' is [' .
                          $product[$field_name].'] in existing product and [' .
                          $field_value.'] in product info');
          return false;
       }
    }
    $inventory = $existing_inventory[$inv_id];
    foreach ($inventory_info as $field_name => $field_value) {
       if (! array_key_exists($field_name,$inventory)) continue;
       if ($inventory[$field_name] != $field_value) {
          if (LOG_PRODUCT_DIFFERENCES)
             log_activity('   Field '.$field_name.' is [' .
                          $inventory[$field_name].'] in existing inventory and [' .
                          $field_value.'] in inventory info');
          return false;
       }
    }
    return true;
}

function process_downloaded_product($amazon,$quikweb,$product,$inventory,
   $images,$product_info,$inventory_info,$image_record,$config_values)
{
    $product_info['amazon_downloaded'] = time();
    $activity = ' from Amazon SKU '.$product_info['amazon_sku'].' and ASIN ' .
                $product_info['amazon_asin'];
    if (! empty($product_info['id'])) {
       $product_id = $product_info['id'];
       $product_info['amazon_warning'] = null;
       $product_info['amazon_error'] = null;
       $current_time = time();
       $product_info['last_modified'] = $current_time;
       $product_info['amazon_updated'] = $current_time;
       $product_info['activity'] = 'Product Updated'.$activity;
       if (! $quikweb->update_product($product_info,$inventory_info)) {
          $amazon->process_error('QuikWeb Error updating Product ' .
             $product_info['name'].' (#'.$product_id.'): '.$quikweb->error);
          return false;
       }
    }
    else {
       $product_info['activity'] = 'Product Added'.$activity;
       $product_id = $quikweb->add_product($product_info,$inventory_info);
       if (! $product_id) {
          $amazon->process_error('QuikWeb Error adding Product ' .
                                 $product_info['name'].': '.$quikweb->error);
          return false;
       }
    }

    if ((! empty($product['features'])) && defined('FEATURES_DATA_TYPE')) {
       if (! update_product_features($amazon,$product['features'],$product_id))
          return false;
    }

    $image_record['parent']['value'] = $product_id;
    $image_record['sequence']['value'] = 0;
    if (empty($product['images']) && (! empty($product['image_url']))) {
       $image_url = str_replace('_SL75_','_UL1500_',$product['image_url']);
       if (! download_product_image($amazon,$image_url,$image_record,
                                    $images,$config_values)) return false;
    }
    if (! empty($product['images'])) {
       foreach ($product['images'] as $image_url) {
          if (! download_product_image($amazon,$image_url,$image_record,
                                       $images,$config_values)) return false;
       }
    }

    if (function_exists('custom_amazon_update_product'))
       custom_amazon_update_product($amazon,$product_id,$product,$product_info,
                                    $inventory_info);

    return true;
}

function process_product_details($amazon,$quikweb,$product_details,$products,
   $inventory,$images,$product_map,$inventory_map,$scrape_product_map,
   $scrape_inventory_map,$existing_products,$existing_inventory,$image_record,
   $config_values)
{
    foreach ($product_details as $sku => $product_info) {
       if (empty($product_info['asin']) &&
           (! empty($products[$sku]['asin']))) {
          $asin = $products[$sku]['asin'];
          $asin_details = $amazon->get_products('ASIN',array($asin));
          if (! empty($asin_details[$asin]))
             $product_info = $asin_details[$asin];
          else $product_info = $products[$sku];
       }
       $product = array_merge($products[$sku],$product_info);
       build_product_info($amazon,$product,$inventory,$product_map,
                          $inventory_map,$product_info,$inventory_info);
       if (product_unchanged($existing_products,$existing_inventory,
                             $product_info,$inventory_info)) continue;
       $scrape_data = $amazon->scrape_product_data($product['asin']);
       if (is_array($scrape_data)) {
          $product = array_merge($product,$scrape_data);
          update_scraped_product_info($amazon,$product,$inventory,
             $scrape_product_map,$scrape_inventory_map,$product_info,
             $inventory_info);
       }
       if (! process_downloaded_product($amazon,$quikweb,$product,
                $inventory,$images,$product_info,$inventory_info,
                $image_record,$config_values)) return false;
    }
    return true;
}

function download_products($amazon)
{
    log_activity('Started Product Download from Amazon');
    $report_data = $amazon->get_report('_GET_MERCHANT_LISTINGS_ALL_DATA_',
                                       true);
    if (! $report_data) return;
    $products = array();
    foreach ($report_data as $index => $line_fields) {
       if ($index == 0) continue;
       if (isset($line_fields[28])) $status = $line_fields[28];
       else $status = '';
       if ($status == 'Active') $status = $amazon->dl_active_status;
       else if ($status == 'Inactive') $status = $amazon->dl_inactive_status;
       else if ($status == 'Incomplete')
          $status = $amazon->dl_incomplete_status;
       if ($status == -1) continue;
       $product_info = array('sku'=>$line_fields[3],'status' => $status);
       if (isset($line_fields[16])) $product_info['asin'] = $line_fields[16];
       else $product_info['asin'] = null;
       if (isset($line_fields[4])) $product_info['price'] = $line_fields[4];
       else $product_info['price'] = 0.00;
       if (isset($line_fields[5])) $product_info['qty'] = $line_fields[5];
       else $product_info['qty'] = 0;
       $products[$line_fields[3]] = $product_info;
    }
    if (count($products) == 0) {
       $amazon->process_error('Amazon Error: No Products found to Download');
       return;
    }

    require_once '../cartengine/api.php';
    require_once '../cartengine/image.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');

    $query = 'select * from products';
    $existing_products = $amazon->db->get_records($query,'id');
    if ((! $existing_products) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $query = 'select * from product_inventory';
    $existing_inventory = $amazon->db->get_records($query,'id');
    if ((! $existing_inventory) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $query = 'select i.id,i.parent,i.part_number,p.amazon_price from ' .
             'product_inventory i join products p on p.id=i.parent';
    $inventory = $amazon->db->get_records($query,'part_number');
    if ((! $inventory) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $query = 'select parent,filename from images where parent_type=1 ' .
             'order by parent';
    $image_records = $amazon->db->get_records($query);
    if ((! $image_records) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $images = array();
    foreach ($image_records as $image) {
       $parent = $image['parent'];
       if (! isset($images[$parent])) $images[$parent] = array();
       $images[$parent][] = $image['filename'];
    }
    $config_values = load_config_values($amazon->db);

    $product_map = array('title'=>'name','brand'=>'shopping_brand',
       'color'=>'shopping_color','part_number'=>'shopping_mpn',
       'asin'=>'amazon_asin','sku'=>'amazon_sku',
       'department'=>'shopping_gender','product_type'=>'amazon_type',
       'product_group'=>'amazon_item_type','status'=>'status','model'=>'model',
       'binding'=>'binding','size'=>'size','label'=>'label',
       'currency'=>'currency','manufacturer'=>'manufacturer',
       'studio'=>'studio','material'=>'material',
       'package_weight'=>'package_weight','package_height'=>'package_height',
       'package_width'=>'package_width','package_length'=>'package_length',
       'height'=>'height','width'=>'width','length'=>'length');
    $inventory_map = array('sku'=>'part_number','weight'=>'weight');
    if ($amazon->features & MAINTAIN_INVENTORY) $inventory_map['qty'] = 'qty';
    if ($amazon->features & REGULAR_PRICE_PRODUCT)
       $product_map['price'] = 'price';
    else if ($amazon->features & REGULAR_PRICE_INVENTORY)
       $inventory_map['price'] = 'price';
    if ($amazon->features & LIST_PRICE_PRODUCT)
       $product_map['list_price'] = 'list_price';
    else if ($amazon->features & LIST_PRICE_INVENTORY)
       $inventory_map['list_price'] = 'list_price';
    $scrape_product_map = array('seo_url'=>'seo_url',
       'seo_description'=>'seo_description','seo_title'=>'seo_title',
       'seo_keywords'=>'seo_keywords','description'=>'long_description',
       'UPC'=>'shopping_gtin','EAN'=>'ean','UNSPSC Code'=>'unspsc',
       'Part Number'=>'shopping_mpn','Brand Name'=>'shopping_brand',
       'Material'=>'material');
    $scrape_inventory_map = array('weight'=>'weight');

    $image_record = image_record_definition();
    $image_record['parent_type']['value'] = 1;

    $skus = array();
    foreach ($products as $product_info) {
       $skus[] = $product_info['sku'];
       if (count($skus) == 5) {
          $product_details = $amazon->get_products('SellerSKU',$skus);
          if (! $product_details) return;
          if (! process_product_details($amazon,$quikweb,$product_details,
                   $products,$inventory,$images,$product_map,$inventory_map,
                   $scrape_product_map,$scrape_inventory_map,$existing_products,
                   $existing_inventory,$image_record,$config_values)) return;
          $skus = array();
       }
    }
    if (count($skus) > 0) {
       $product_details = $amazon->get_products('SellerSKU',$skus);
       if (! $product_details) return;
       if (! process_product_details($amazon,$quikweb,$product_details,
                $products,$inventory,$images,$product_map,$inventory_map,
                $scrape_product_map,$scrape_inventory_map,$existing_products,
                $existing_inventory,$image_record,$config_values)) return;
    }
    log_activity('Finished Product Download from Amazon');
}

function update_product_status($amazon)
{
    global $skip_amazon_updates;

    $open_listings =
       $amazon->get_report('_GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_',true);
    if (! $open_listings) {
       $amazon->process_error('Unable to run Open Listings Report');   return;
    }
    $fba_inventory =
       $amazon->get_report('_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_',true);
    if (! $fba_inventory) {
       $amazon->process_error('Unable to run FBA Inventory Report');   return;
    }

    $skus = array();   $fba_skus = array();
    foreach ($open_listings as $index => $row) {
       if ($index == 0) continue;
       $sku = $row[3];   $qty = intval($row[5]);
       if ($qty < 1) continue;
       $skus[$sku] = $qty;
    }
    foreach ($fba_inventory as $index => $row) {
       if ($index == 0) continue;
       $sku = $row[0];   $qty = intval($row[10]);
       if ($qty < 1) continue;
       $skus[$sku] = $qty;
       $fba_skus[$sku] = true;
    }

    $query = 'select p.id,p.status,p.name,p.amazon_fba_flag,i.id as inv_id,' .
             'i.part_number,i.qty from products p join product_inventory i ' .
             'on i.parent=p.id where not isnull(amazon_downloaded)';
    $products = $amazon->db->get_records($query,'id');
    if (! $products) {
       if (isset($amazon->db->error))
          $amazon->process_error('Database Error: '.$amazon->db->error);
       else $amazon->process_error('No Products Found for Update Product Status');
       return;
    }

    require_once '../cartengine/api.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');
    $skip_amazon_updates = true;

    foreach ($products as $product) {
       if (empty($product['part_number'])) $sku = '';
       else $sku = $product['part_number'];
       if ($sku && isset($skus[$sku])) {
          $new_status = 0;   $qty = $skus[$sku];
       }
       else {
          $new_status = $amazon->dl_delete_status;   $qty = 0;
       }
       if ($sku && isset($fba_skus[$sku])) $amazon_fba_flag = 1;
       else $amazon_fba_flag = 0;
       if (($new_status != $product['status']) || ($qty != $product['qty']) ||
           ($amazon_fba_flag != $product['amazon_fba_flag'])) {
          $product_info = array('id' => $product['id'],
             'name' => $product['name'],'status' => $new_status,
             'amazon_fba_flag' => $amazon_fba_flag);
          if ($qty == $product['qty']) $inventory_info = null;
          else $inventory_info = array('id' => $product['inv_id'],
                                       'qty' => $qty);
          $activity = 'Product Updated from Amazon';
          if ($sku) $activity .= ' SKU '.$sku;
          if ($new_status != $product['status'])
             $activity .= ', Status: '.$product['status'].'=>'.$new_status;
          if ($qty != $product['qty'])
             $activity .= ', Qty: '.$product['qty'].'=>'.$qty;
          if ($amazon_fba_flag != $product['amazon_fba_flag'])
             $activity .= ', FBA: '.$product['amazon_fba_flag'].'=>' .
                          $amazon_fba_flag;
          $product_info['activity'] = $activity;
          if (! $quikweb->update_product($product_info,$inventory_info)) {
             $amazon->process_error('QuikWeb Error updating Product ' .
                $product_info['name'].': '.$quikweb->error);
             return;
          }
       }
    }
   
    log_activity('Updated Product Status from Amazon');
}

function download_product_images($amazon)
{
    require_once '../cartengine/api.php';
    require_once '../cartengine/image.php';
    set_remote_user('amazon');

    $query = 'select id,amazon_asin from products';
    $products = $amazon->db->get_records($query,'id');
    if ((! $products) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $query = 'select parent,filename from images where parent_type=1 ' .
             'order by parent';
    $image_records = $amazon->db->get_records($query);
    if ((! $image_records) && isset($db->error)) {
       $amazon->process_error('Database Error: '.$amazon->db->error);
       return;
    }
    $images = array();
    foreach ($image_records as $image) {
       $parent = $image['parent'];
       if (! isset($images[$parent])) $images[$parent] = array();
       $images[$parent][] = $image['filename'];
    }
    $config_values = load_config_values($amazon->db);

    $image_record = image_record_definition();
    $image_record['parent_type']['value'] = 1;

    foreach ($products as $product) {
       if (! $product['amazon_asin']) continue;
       $scrape_data = $amazon->scrape_product_data($product['amazon_asin']);
       if (empty($scrape_data['images'])) continue;
       $image_record['parent']['value'] = $product['id'];
       $image_record['sequence']['value'] = 0;
       foreach ($scrape_data['images'] as $image_url) {
          if (! download_product_image($amazon,$image_url,$image_record,
                                       $images,$config_values)) return;
       }
    }
}

function get_product_data($amazon,$argv)
{
    if (empty($argv[2])) {
       print "Usage: php cmd.php getproduct <ASIN>\n";   return;
    }
    $asins = array($argv[2]);
    $product_details = $amazon->get_products('ASIN',$asins);
    print 'Product Details = '.print_r($product_details,true)."\n";
    $scrape_data = $amazon->scrape_product_data($argv[2]);
    if (! empty($scrape_data['description']))
       $scrape_data['description'] =
          substr($scrape_data['description'],0,100).' ...';
    print 'Scrape Data = '.print_r($scrape_data,true)."\n";
}

function get_product_data_by_sku($amazon,$argv)
{
    if (empty($argv[2])) {
       print "Usage: php cmd.php getproduct <SKU>\n";   return;
    }
    $skus = array($argv[2]);
    $product_details = $amazon->get_products('SellerSKU',$skus);
    print 'Product Details = '.print_r($product_details,true)."\n";
}

function start_download($amazon)
{
    global $interactive;

    if (file_exists('amazon.locked') &&
        (filemtime('amazon.locked') > (time() - 4000))) {
       if ($interactive) http_response(409,'Amazon Sync already in progress');
       return;
    }
    putenv('SERVER_SOFTWARE');
    $spawn_result = spawn_program('shopping/amazon/cmd.php downloadproducts');
    if ($spawn_result != 0) {
       $error = 'Amazon Download Request returned '.$spawn_result;
       if ($interactive) http_response(422,$error);   return;
    }
    log_activity('Submitted Amazon Product Download Request');
    if ($interactive) http_response(202,'Submitted Download Request');
}

function update_qw_product($quikweb,$row,$asin)
{
    $product_info = array('id' => $row['id'],
       'name' => $row['name'],'amazon_asin' => $asin);
    $product_info['activity'] = 'Product Updated with Amazon ASIN '.$asin;
    if (! $quikweb->update_product($product_info,null)) {
       $amazon->process_error('QuikWeb Error updating Product ' .
                              $product_info['name'].': '.$quikweb->error);
       return false;
    }
    return true;
}

function update_asins($quikweb,$amazon,$upcs,$products,$rows)
{
    $product_details = $amazon->get_products('UPC',$upcs);
    foreach ($upcs as $upc) {
       $product_id = $products[$upc];
       $row = $rows[$product_id];
       if (! empty($product_details[$upc]))
          $asin = $product_details[$upc]['asin'];
       else $asin = null;
       if (! $asin) {
          $matching_products = $amazon->list_matching_products($upc);
          if (empty($matching_products)) continue;
          foreach ($matching_products as $product) {
             if (! empty($product['asin'])) {
                $asin = $product['asin'];   break;
             }
          }
          if (! $asin) continue;
       }
       $amazon->add_cached_asin(AMAZON_ASIN_UPC,$upc,$asin);
       if (! update_qw_product($quikweb,$row,$asin)) return false;
    }
    return true;
}

function get_asins($amazon)
{
    global $skip_amazon_updates;

    $query = 'select id,name,shopping_gtin from products where ' .
             '(shopping_gtin!="") and (not isnull(shopping_gtin)) and ' .
             '((amazon_asin="") or isnull(amazon_asin))';
    $rows = $amazon->db->get_records($query,'id');
    if (! $rows) return;

    require_once '../cartengine/api.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');
    $skip_amazon_updates = true;

    $upcs = array();   $products = array();
    foreach ($rows as $product_id => $row) {
       $upc = $row['shopping_gtin'];
       $asin = $amazon->get_cached_asin(AMAZON_ASIN_UPC,$upc);
       if ($asin !== null) {
          if ($asin && (! update_qw_product($quikweb,$row,$asin))) return;
          continue;
       }
       $upcs[] = $upc;   $products[$upc] = $product_id;
       if (count($upcs) == 5) {
          if (! update_asins($quikweb,$amazon,$upcs,$products,$rows)) return;
          $upcs = array();
       }
    }
    if (count($upcs) > 0) {
       if (! update_asins($quikweb,$amazon,$upcs,$products,$rows)) return;
    }
    log_activity('Updated Product ASINs from Amazon');
}

function update_product_asins($db,$amazon,$upcs,$products,&$rows)
{
    $product_details = $amazon->get_products('UPC',$upcs);
    foreach ($upcs as $upc) {
       $product_id = $products[$upc];
       $row = $rows[$product_id];
       if (! empty($product_details[$upc]))
          $asin = $product_details[$upc]['asin'];
       else $asin = null;
       if (! $asin) {
          $matching_products = $amazon->list_matching_products($upc);
          if (empty($matching_products)) continue;
          foreach ($matching_products as $product) {
             if (! empty($product['asin'])) {
                $asin = $product['asin'];   break;
             }
          }
          if (! $asin) continue;
       }
       $amazon->add_cached_asin(AMAZON_ASIN_UPC,$upc,$asin);
       if ($asin == $row['amazon_asin']) {
          $rows[$product_id]['updated'] = true;   continue;
       }
       $query = 'update products set amazon_asin=?,last_modified=? where id=?';
       $query = $db->prepare_query($query,$asin,time(),$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          print 'Database Error: '.$db->error."\n";   exit;
       }
       log_activity('Changed ASIN for Product #'.$product_id.' to '.$asin);
       write_product_activity('ASIN Changed to '.$asin.' by Cleanup ASINs',
                              $product_id,$db);
       $rows[$product_id]['updated'] = true;
    }
}

function cleanup_asins($amazon)
{
    $db = $amazon->db;
    $query = 'truncate table amazon_cached_asins';
    if (! $db->query($query)) {
       print 'Database Error: '.$db->error."\n";   exit;
    }

    $query = 'select id,amazon_asin,shopping_gtin from products';
    $rows = $db->get_records($query,'id');
    if (! $rows) {
       print 'Database Error: '.$db->error."\n";   exit;
    }
    $upcs = array();   $products = array();
    foreach ($rows as $product_id => $row) {
        if (empty($row['shopping_gtin'])) continue;
        $upc = $row['shopping_gtin'];
        $upcs[] = $upc;   $products[$upc] = $product_id;
        if (count($upcs) == 5) {
           update_product_asins($db,$amazon,$upcs,$products,$rows);
           $upcs = array();
        }
    }
    if (count($upcs) > 0)
       update_product_asins($db,$amazon,$upcs,$products,$rows);

    foreach ($rows as $product_id => $row) {
       if (! empty($row['updated'])) continue;
       if (empty($row['amazon_asin'])) continue;
       $query = 'update products set amazon_asin=NULL,last_modified=? ' .
                'where id=?';
       $query = $db->prepare_query($query,time(),$product_id);
       $db->log_query($query);
       if (! $db->query($query)) {
          print 'Database Error: '.$db->error."\n";   exit;
       }
       log_activity('Removed Invalid ASIN for Product #'.$product_id);
       write_product_activity('Removed Invalid ASIN by Cleanup ASINs',
                              $product_id,$db);
    }
}

function update_fba($amazon)
{
    global $skip_amazon_updates,$interactive;

    $fba_inventory =
       $amazon->get_report('_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_',true);
    if (! $fba_inventory) {
       $amazon->process_error('Unable to run FBA Inventory Report');   return;
    }

    $fba_skus = array();
    foreach ($fba_inventory as $index => $row) {
       if ($index == 0) continue;
       $fba_skus[$row[0]] = true;
    }

    $query = 'select p.id,p.name,p.amazon_fba_flag,p.amazon_sku,' .
             'i.part_number from products p join product_inventory i on ' .
             'i.parent=p.id';
    $products = $amazon->db->get_records($query,'id');
    if (! $products) {
       if (isset($amazon->db->error))
          $amazon->process_error('Database Error: '.$amazon->db->error);
       else $amazon->process_error('No Products Found for Update FBA Flags');
       return;
    }

    require_once '../cartengine/api.php';
    $quikweb = new QuikWebAPI(null,$amazon->db);
    set_remote_user('amazon');
    $skip_amazon_updates = true;

    foreach ($products as $product) {
       $sku = get_amazon_sku($product,'id');
       if (! $sku) continue;
       if (isset($fba_skus[$sku])) $amazon_fba_flag = 1;
       else $amazon_fba_flag = 0;
       if ($amazon_fba_flag && $product['amazon_fba_flag']) continue;
       if ((! $amazon_fba_flag) && (! $product['amazon_fba_flag'])) continue;
       $product_info = array('id' => $product['id'],
          'name' => $product['name'],'amazon_fba_flag' => $amazon_fba_flag);
       $activity = 'FBA Flag Updated from Amazon';
       if ($sku) $activity .= ' SKU '.$sku;
       if ($amazon_fba_flag != $product['amazon_fba_flag'])
          $activity .= ', FBA: '.$product['amazon_fba_flag'].'=>' .
                       $amazon_fba_flag;
       $product_info['activity'] = $activity;
       if (! $quikweb->update_product($product_info,null)) {
          $amazon->process_error('QuikWeb Error updating Product ' .
                                 $product_info['name'].': '.$quikweb->error);
          return;
       }
    }
   
    log_activity('Updated FBA Flags from Amazon');
    if ($interactive) http_response(201,'Updated FBA Flags');
}

function list_reports($amazon)
{
    $reports = $amazon->get_report_list();
    print "Reports = ".print_r($reports,true);
}

function process_results($amazon,$result_data)
{
    require_once '../engine/email.php';

    $label = '';   $results_text = '';   $results_html = '';
    foreach ($result_data as $result) {
       if ($result['label'] != $label) {
          $results_text .= "\n=== ".$result['label']." ===\n";
          $results_html .= '<h3>'.$result['label']."</h3>\n";
          $label = $result['label'];
       }
       $result_line = $result['code'].': '.$result['message_code'].': ' .
                      $result['description'];
       if (! empty($result['sku'])) {
          $query = 'select id,name from products where id=(select parent ' .
                   'from product_inventory where part_number=? limit 1)';
          $query = $amazon->db->prepare_query($query,$result['sku']);
          $row = $amazon->db->get_record($query);
          if ((! $row) && is_numeric($result['sku'])) {
             $query = 'select id,name from products where id=?';
             $query = $amazon->db->prepare_query($query,$result['sku']);
             $row = $amazon->db->get_record($query);
          }
          if ($row) {
             $result_line .= ' (Product: '.$row['name'].' [#'.$row['id'].'])';
             if ($row['id'] != $result['sku'])
                $result_line .= ' (Part #: '.$result['sku'].')';
          }
          else $result_line .= ' (SKU: '.$result['sku'].')';
       }
       if (! empty($result['order_id']))
          $result_line .= ' (Order #: '.$result['order_id'].')';
       $results_text .= "\n".$result_line."\n";
       $results_html .= '<p>'.$result_line."</p>\n";
    }
    $email = new Email(AMAZON_RESULTS,
                       array('results_text' => $results_text,
                             'results_html' => $results_html));
    if (! $email->send()) log_error($email->error);
}

if (file_exists('amazon.locked') &&
    (filemtime('amazon.locked') > (time() - 4000))) {
   @Amazon::log_activity('Amazon Sync already in progress');
   if ($interactive) http_response(409,'Amazon Sync already in progress');
   exit;
}

touch('amazon.locked');
set_time_limit(0);
ini_set('memory_limit','4096M');
set_remote_user('amazon');
if (! empty($argv[1])) $cmd = $argv[1];
else if ($interactive) $cmd = get_form_field('cmd');
else $cmd = null;

$result_data = array();
$amazon = new Amazon;
$amazon->debug = true;
if ($cmd == 'servicestatus') get_service_status($amazon);
else if ($cmd == 'deleteproducts') {
   delete_products($amazon,$result_data);
   update_sync_time($amazon,AMAZON_DELETE_PRODUCTS);
}
else if ($cmd == 'uploadproducts') {
   upload_products($amazon,$result_data);
   update_sync_time($amazon,AMAZON_UPLOAD_PRODUCTS);
}
else if ($cmd == 'getfeedlist') get_feed_list($amazon);
else if ($cmd == 'getfeedresult') get_feed_result($amazon,$argv);
else if ($cmd == 'downloadorders') {
   download_orders($amazon);
   update_sync_time($amazon,AMAZON_DOWNLOAD_ORDERS);
}
else if ($cmd == 'confirmship') {
   confirm_shipments($amazon,$result_data);
   update_sync_time($amazon,AMAZON_CONFIRM_SHIPMENTS);
}
else if ($cmd == 'downloadproducts') {
   download_products($amazon);
//   update_product_status($amazon);
   update_sync_time($amazon,AMAZON_DOWNLOAD_PRODUCTS);
}
else if ($cmd == 'getproduct') get_product_data($amazon,$argv);
else if ($cmd == 'getproductbysku') get_product_data_by_sku($amazon,$argv);
else if ($cmd == 'getcategories') get_categories($amazon);
else if ($cmd == 'downloadcategories') download_categories($amazon);
else if ($cmd == 'downloadproductimages') download_product_images($amazon);
else if ($cmd == 'updateproductstatus') update_product_status($amazon);
else if ($cmd == 'startdownload') start_download($amazon);
else if ($cmd == 'getasins') get_asins($amazon);
else if ($cmd == 'cleanupasins') cleanup_asins($amazon);
else if ($cmd == 'updatefba') update_fba($amazon);
else if ($cmd == 'listreports') list_reports($amazon);
else if ($cmd) print 'Unknown Amazon Command: '.$cmd."\n";
else {
   $sync_flags = get_cart_config_value('amazon_sync_flags',$amazon->db);
   if ($sync_flags & (1 << AMAZON_DELETE_PRODUCTS)) {
      delete_products($amazon,$result_data);
      update_sync_time($amazon,AMAZON_DELETE_PRODUCTS);
   }
   if ($sync_flags & (1 << AMAZON_DOWNLOAD_PRODUCTS)) {
      download_products($amazon);
      update_product_status($amazon);
      update_sync_time($amazon,AMAZON_DOWNLOAD_PRODUCTS);
   }
   if ($sync_flags & (1 << AMAZON_DOWNLOAD_ORDERS)) {
      download_orders($amazon);
      update_sync_time($amazon,AMAZON_DOWNLOAD_ORDERS);
   }
   if ($sync_flags & (1 << AMAZON_CONFIRM_SHIPMENTS)) {
      confirm_shipments($amazon,$result_data);
      update_sync_time($amazon,AMAZON_CONFIRM_SHIPMENTS);
   }
   if ($sync_flags & (1 << AMAZON_UPLOAD_PRODUCTS)) {
      upload_products($amazon,$result_data);
      update_sync_time($amazon,AMAZON_UPLOAD_PRODUCTS);
   }
}
if (count($result_data) > 0) process_results($amazon,$result_data);
$amazon->db->close();
unlink('amazon.locked');

?>
