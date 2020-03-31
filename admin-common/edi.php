<?php
/*
                      Inroads Shopping Cart - EDI Processing

                       Written 2016-2019 by Randall Severy
                        Copyright 2016-2019 Inroads, LLC
*/

require_once '../engine/ui.php';
require_once '../engine/db.php';
require_once 'cartconfig-common.php';
require_once 'orders-common.php';
require_once 'customers-common.php';
require_once 'vendors-common.php';
require_once 'shopping-common.php';
require_once 'products-common.php';
require_once 'inventory-common.php';
require_once 'utility.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('AS2_INTERFACE',1);
define('FTP_INTERFACE',2);

if (! isset($as2secure_dir)) $as2secure_dir = '../admin/as2secure';

set_time_limit(0);
ini_set('memory_limit','2048M');

function escape_edi($edi_value)
{
    global $field_sep,$trans_sep,$comp_sep,$escape_sep;

    if ($escape_sep) {
       $edi_value = str_replace($escape_sep,$escape_sep.$escape_sep,$edi_value);
       $edi_value = str_replace($field_sep,$escape_sep.$field_sep,$edi_value);
       $edi_value = str_replace($trans_sep[0],$escape_sep.$trans_sep,$edi_value);
       $edi_value = str_replace($comp_sep,$escape_sep.$comp_sep,$edi_value);
    }
    else {
       $edi_value = str_replace($field_sep,'',$edi_value);
       $edi_value = str_replace($trans_sep[0],'',$edi_value);
       $edi_value = str_replace($comp_sep,'',$edi_value);
    }
    return $edi_value;
}

function build_edi_850_document($db,$order_ids,$vendor_info)
{
    global $field_sep,$trans_sep,$comp_sep,$escape_sep;

    $field_sep = '*';   $trans_sep = "\n";   $comp_sep = '>';
    $escape_sep = null;
    $sender_qual = $vendor_info['edi_sender_qual'];
    $sender_id = $vendor_info['edi_sender_id'];
    $receiver_qual = $vendor_info['edi_receiver_qual'];
    $receiver_id = $vendor_info['edi_receiver_id'];
    $first_order_id = reset($order_ids);
    $interchange_control_number =
       '1'.str_pad($first_order_id,8,'0',STR_PAD_LEFT);
    $group_control_number = '2'.str_pad($first_order_id,6,'0',STR_PAD_LEFT);

    $output = '';
    $output .= 'ISA'.$field_sep.'00'.$field_sep.'          '.$field_sep.'00' .
               $field_sep.'          '.$field_sep.$sender_qual.$field_sep .
               str_pad($sender_id,15).$field_sep.$receiver_qual.$field_sep .
               str_pad($receiver_id,15).$field_sep.date('ymd').$field_sep .
               date('Hi').$field_sep.'U'.$field_sep.'00401'.$field_sep .
               $interchange_control_number.$field_sep.'0'.$field_sep.'P' .
               $field_sep.$comp_sep.$trans_sep;
    $output .= 'GS'.$field_sep.'PO'.$field_sep.$sender_id.$field_sep .
               $receiver_id.$field_sep.date('Ymd').$field_sep.date('Hi') .
               $field_sep.$group_control_number.$field_sep.'X'.$field_sep .
               '004010'.$trans_sep;
    $num_orders = 0;
    foreach ($order_ids as $order_id) {
       $order = load_order($db,$order_id,$error_msg);
       if (! $order) {
          if (isset($db->error)) $error = 'Database Error: '.$db->error;
          else $error = $error_msg;
          return null;
       }
       $document_rows = 14;
       update_vendor_order_info($order,$vendor_info['id']);
       $set_control_number = '30000'.($num_orders + 1);
       $output .= 'ST'.$field_sep.'850'.$field_sep.$set_control_number .
                  $trans_sep;
       $purchase_order = get_row_value($order->info,'order_number');
       $output .= 'BEG'.$field_sep.'00'.$field_sep.'SA'.$field_sep .
                  escape_edi($purchase_order).$field_sep.$field_sep.date('Ymd') .
                  $trans_sep;
       $output .= 'REF'.$field_sep.'OQ'.$field_sep.escape_edi($order_id) .
                  $trans_sep;
       $output .= 'PER'.$field_sep.'CN'.$field_sep.'EM'.$field_sep .
                  escape_edi(get_cart_config_value('contactemail')).$field_sep .
                  'TE'.$field_sep.escape_edi(get_cart_config_value('contactphone')) .
                  $trans_sep;
       $output .= 'PER'.$field_sep.'BL'.$field_sep.'EM'.$field_sep .
                  'customersupport@inroads.us'.$field_sep.'TE'.$field_sep.'301-473-9750' .
                  $trans_sep;

       $total_amount = 0;
       $tax = get_row_value($order->info,'tax');
       if ($tax != 0) {
          $output .= 'SAC'.$field_sep.'C'.$field_sep.'H750'.$field_sep .
                     $field_sep.$field_sep.floor($tax * 100).$trans_sep;
          $total_amount += $tax;   $document_rows++;
       }
       $output .= 'DTM'.$field_sep.'008'.$field_sep.date('Ymd').$field_sep .
                  date('Hi').$trans_sep;
       $company = get_row_value($order->info,'company');
       $contact_name = get_row_value($order->info,'fname');
       $mname = get_row_value($order->info,'mname');
       if ($mname != '') $contact_name .= ' '.$mname;
       $contact_name .= ' '.get_row_value($order->info,'lname');
       $output .= 'N1'.$field_sep.'BT'.$field_sep.escape_edi($contact_name) .
                  $field_sep.'91'.$trans_sep;
       if ($company)
          $output .= 'N2'.$field_sep.escape_edi($company).$trans_sep;
       $address = get_row_value($order->billing,'address1');
       $address2 = get_row_value($order->billing,'address2');
       $output .= 'N3'.$field_sep.escape_edi($address).$field_sep .
                  escape_edi($address2).$trans_sep;
       $city = get_row_value($order->billing,'city');
       $state = get_row_value($order->billing,'state');
       $zip = get_row_value($order->billing,'zipcode');
       $country = get_row_value($order->shipping,'country');
       if (! $country) $country = 1;
       $country_info = get_country_info($country,$db);
       $output .= 'N4'.$field_sep.escape_edi($city).$field_sep .
                  escape_edi($state).$field_sep.escape_edi($zip).$field_sep .
                  escape_edi($country_info['code']).$trans_sep;
       $shipto = get_row_value($order->shipping,'shipto');
       if (! $shipto) $shipto = $contact_name;
       $output .= 'N1'.$field_sep.'ST'.$field_sep.escape_edi($shipto) .
                  $field_sep.'91'.$field_sep.'1462301'.$trans_sep;
       $company = get_row_value($order->shipping,'company');
       if ($company)
          $output .= 'N2'.$field_sep.escape_edi($company).$trans_sep;
       $address = get_row_value($order->shipping,'address1');
       $address2 = get_row_value($order->shipping,'address2');
       $output .= 'N3'.$field_sep.escape_edi($address).$field_sep .
                  escape_edi($address2).$trans_sep;
       $city = get_row_value($order->shipping,'city');
       $state = get_row_value($order->shipping,'state');
       $zip = get_row_value($order->shipping,'zipcode');
       $country = get_row_value($order->shipping,'country');
       if (! $country) $country = 1;
       $country_info = get_country_info($country,$db);
       $output .= 'N4'.$field_sep.escape_edi($city).$field_sep .
                  escape_edi($state).$field_sep.escape_edi($zip).$field_sep .
                  escape_edi($country_info['code']).$trans_sep;
       if ($order->items) {
          $num_items = count($order->items);
          foreach ($order->items as $item_id => $order_item) {
             $output .= 'PO1'.$field_sep.$item_id.$field_sep .
                        $order_item['qty'].$field_sep.$order_item['unit'].$field_sep .
                        $order_item['cost'].$field_sep.$field_sep.'VC' .
                        $field_sep.escape_edi($order_item['part_number']) .
                        $field_sep.'IN'.$field_sep .
                        escape_edi($order_item['product_id']).$trans_sep;
             $output .= 'PID'.$field_sep.'F'.$field_sep.$field_sep.$field_sep .
                        $field_sep.escape_edi($order_item['product_name']) .
                        $trans_sep;
             $total_amount += $order_item['cost'];
          }
       }
       else $num_items = 0;
       $output .= 'CTT'.$field_sep.$num_items.$field_sep.$total_amount .
                  $trans_sep;
       $document_rows += ($num_items * 2);
       $output .= 'SE'.$field_sep.$document_rows.$field_sep .
                  $set_control_number.$trans_sep;
       $num_orders++;
    }
    $output .= 'GE'.$field_sep.$num_orders.$field_sep.$group_control_number .
               $trans_sep;
    $output .= 'IEA'.$field_sep.'1'.$field_sep.$interchange_control_number .
               $trans_sep;
    return $output;
}

function init_as2secure()
{
    global $as2secure_dir;

    require_once $as2secure_dir.'/lib/Mail/RFC822.php';
    require_once $as2secure_dir.'/lib/Mail/mimeDecode.php';
    require_once $as2secure_dir.'/lib/Horde/String.php';
    require_once $as2secure_dir.'/lib/Horde/Util.php';
    require_once $as2secure_dir.'/lib/Horde/MIME.php';
    require_once $as2secure_dir.'/lib/Horde/MIME/Part.php';
    require_once $as2secure_dir.'/lib/Horde/MIME/Message.php';
    require_once $as2secure_dir.'/lib/Horde/MIME/Structure.php';
    require_once $as2secure_dir.'/lib/AS2Log.php';
    require_once $as2secure_dir.'/lib/AS2Header.php';
    require_once $as2secure_dir.'/lib/AS2Connector.php';
    require_once $as2secure_dir.'/lib/AS2Partner.php';
    require_once $as2secure_dir.'/lib/AS2Abstract.php';
    require_once $as2secure_dir.'/lib/AS2Exception.php';
    require_once $as2secure_dir.'/lib/AS2Adapter.php';
    require_once $as2secure_dir.'/lib/AS2Client.php';
    require_once $as2secure_dir.'/lib/AS2Message.php';
    require_once $as2secure_dir.'/lib/AS2MDN.php';
    require_once $as2secure_dir.'/lib/AS2Request.php';
    require_once $as2secure_dir.'/lib/AS2Server.php';

    define('AS2_DIR_PARTNERS', $as2secure_dir.'/partners/');
    define('AS2_DIR_LOGS',     $as2secure_dir.'/logs/');
    define('AS2_DIR_MESSAGES', $as2secure_dir.'/messages/');
    define('AS2_DIR_BIN',      $as2secure_dir.'/bin/');
}

function send_orders($argv)
{
    $db = new DB;

    $query = 'select o.id,p.id as product_id,p.vendor,v.new_order_flag,' .
             'v.send_order_flag from orders o left join order_items i on ' .
             'i.parent=o.id and i.parent_type=0 left join products p on ' .
             'p.id=i.product_id left join vendors v on v.id=p.vendor where ';
    if (isset($argv[2],$argv[3])) {
       $order_ids = explode(',',$argv[2]);
       if (count($order_ids) == 0) return;
       $vendor_ids = explode(',',$argv[3]);
       if (count($vendor_ids) == 0) return;
       $query .= '(o.id in (?))';
       $query = $db->prepare_query($query,$order_ids);
       $all_new_orders = false;
    }
    else {
       $order_ids = array();   $vendor_ids = array();
       $query .= '(o.status=0)';   $all_new_orders = true;
    }
    $order_items = $db->get_records($query);
    if (! $order_items) return;
    if ($all_new_orders) {
       foreach ($order_items as $row) {
          if ($row['new_order_flag'] != SEND_ORDER_BY_EDI) continue;
          if ($row['send_order_flag'] == SEND_ORDER_AUTO) continue;
          if (! in_array($row['id'],$order_ids)) $order_ids[] = $row['id'];
          if (! in_array($row['vendor'],$vendor_ids))
             $vendor_ids[] = $row['vendor'];
       }
       if (count($order_ids) == 0) return;
       if (count($vendor_ids) == 0) return;
    }

    $query = 'select * from vendors where id in (?)';
    $query = $db->prepare_query($query,$vendor_ids);
    $vendors = $db->get_records($query);
    if (! $vendors) return;

    $as2secure_loaded = false;
    load_cart_config_values($db);

    foreach ($vendors as $vendor_info) {
       if (! $vendor_info['edi_interface']) {
          log_error('No EDI Interface specified for vendor ' .
                    $vendor_info['name']);
          continue;
       }
       $vendor_id = $vendor_info['id'];
       $vendor_orders = array();
       foreach ($order_items as $row) {
          if (($row['vendor'] == $vendor_id) &&
              (! in_array($row['id'],$vendor_orders)))
             $vendor_orders[] = $row['id'];
       }
       if (count($vendor_orders) == 0) continue;
       $edi_document = build_edi_850_document($db,$vendor_orders,$vendor_info);
       if (! $edi_document) return;

       if ($vendor_info['edi_interface'] == AS2_INTERFACE) {
          if (! $as2secure_loaded) {
             init_as2secure();   $as2secure_loaded = true;
          }
          try {
             $partner_from = $vendor_info['edi_sender_qual'] .
                             $vendor_info['edi_sender_id'];
             $partner_to = $vendor_info['edi_receiver_qual'] .
                           $vendor_info['edi_receiver_id'];
             $params = array('partner_from'=>$partner_from,
                             'partner_to'=>$partner_to);
             $tmp_file = AS2Adapter::getTempFilename();
             file_put_contents($tmp_file,$edi_document);
             $message = new AS2Message(false,$params);
             $message->addFile($tmp_file);
             $message->encode();
             $client = new AS2Client();
             $result = $client->sendRequest($message);
             if ($vendor_info['sent_status']) {
                $new_status = $vendor_info['sent_status'];
                $current_time = time();
                foreach ($vendor_orders as $order_id) {
                   $query = 'update orders set status=?,updated_date=? where id=?';
                   $query = $db->prepare_query($query,$new_status,$current_time,
                                               $order_id);
                   $db->log_query($query);
                   if (! $db->query($query)) return;
                }
             }
             foreach ($vendor_orders as $order_id) {
                $log_msg = 'Sent 850 Purchase Order to '.$vendor_info['name'] .
                           ' for Order #'.$order_id;
                log_activity($log_msg);   log_vendor_activity($log_msg);
             }
          }
          catch (Exception $e) {
             log_error('AS2 Exception: '.$e->getMessage());
             print 'AS2 Exception: '.$e->getMessage()."\n";
          }
       }
       else {
          if (! $vendor_info['edi_ftp_directory']) {
             log_error('No EDI FTP Directory specified for vendor ' .
                       $vendor_info['name']);
             continue;
          }
          $filename = $vendor_info['edi_ftp_directory'].'/850-'.$vendor_id;
          foreach ($vendor_orders as $order_id) $filename .= '-'.$order_id;
          $filename .= '.edi';
          if (file_put_contents($filename,$edi_document) === false) {
             log_error('Unable to save '.$filename.' for vendor ' .
                       $vendor_info['name']);
             continue;
          }
          if ($vendor_info['sent_status']) {
             $new_status = $vendor_info['sent_status'];
             $current_time = time();
             foreach ($vendor_orders as $order_id) {
                $query = 'update orders set status=?,updated_date=? where id=?';
                $query = $db->prepare_query($query,$new_status,$current_time,
                                            $order_id);
                $db->log_query($query);
                if (! $db->query($query)) return;
             }
          }
          foreach ($vendor_orders as $order_id) {
             $log_msg = 'Saved 850 Purchase Order for '.$vendor_info['name'] .
                        ' for Order #'.$order_id;
             log_activity($log_msg);   log_vendor_activity($log_msg);
          }
       }
    }
}

function load_existing_products($db,$vendor_id)
{
    $query = 'select p.id,p.status,i.part_number,i.qty,i.min_qty,p.markup,' .
             'p.override_markup,p.import_flags from products p join ' .
             'product_inventory i on i.parent=p.id where p.vendor='.$vendor_id;
    $products = $db->get_records($query,'part_number');
    if (! $products) {
       if (! isset($db->error)) {
          $error = 'No Products found for Vendor #'.$vendor_id.' in EDI Import';
          log_error($error);   log_import($error);
       }
       else log_import('Database Error: '.$db->error);   return;
       return null;
    }
    return $products;
}

function process_edi_856_document($db,$filename,$lines,$vendor_info)
{
    global $shipped_option;

    if (! isset($shipped_option)) $shipped_option = 1;
    $order_id = null;   $shipping_carrier = null;   $shipping_method = null;
    $shipping_date = null;   $tracking = null;
    foreach ($lines as $line) {
       $fields = explode('*',$line);
       if (($fields[0] == 'REF') && ($fields[1] == 'OQ') && isset($fields[2]))
          $order_id = $fields[2];
       if (($fields[0] == 'TD5') && isset($fields[5])) {
          $shipping_carrier = $fields[3];   $shipping_method = $fields[5];
       }
       else if (($fields[0] == 'DTM') && isset($fields[2])) {
          $datestring = $fields[2];
          if (isset($fields[3])) $datestring .= ' '.$fields[3];
          else $datestring .= ' 1200';
          $shipping_date = strtotime($datestring);
       }
       else if (($fields[0] == 'REF') && ($fields[1] == 'CN') &&
                isset($fields[2]))
          $tracking = $fields[2];
    }
    if (! $order_id) {
       log_error('Order ID not found in EDI 856 document '.$filename);
       return;
    }

    if ($shipping_carrier == 'FDXG') $shipping_carrier = 'fedex';
    if ($shipping_method == 'FEDEX GROUND') $shipping_method = '11|Ground';

    $query = 'update orders set status=?,updated_date=? where id=?';
    $query = $db->prepare_query($query,$shipped_option,time(),$order_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_error('Database Error: '.$db->error);   return;
    }
    $order = load_order($db,$order_id,$error_msg);
    if (! $order) {
       log_error($error_msg);   return;
    }
    if ($shipping_date) $order->info['shipped_date'] = $shipping_date;
    if ($shipping_carrier) $order->info['shipping_carrier'] = $shipping_carrier;
    if ($shipping_method) $order->info['shipping_method'] = $shipping_method;
    if ($tracking) $order->info['tracking'] = $tracking;
    if (! create_order_shipment($db,$order->info,$order->items,$error_msg)) {
       log_error($error_msg);   return;
    }
    $log_msg = 'Processed 856 Ship Notice from '.$vendor_info['name'] .
               ' for Order #'.$order_id;
    log_activity($log_msg);   log_vendor_activity($log_msg);
    require_once '../engine/email.php';
    $notify_flags = get_cart_config_value('notifications',$db);
    if (($notify_flags & NOTIFY_SHIPPED) &&
        ((! isset($order->info['external_source'])) ||
         ($order->info['external_source'] != 'Amazon'))) {
       $email = new Email(SHIP_NOTIFY_EMAIL,array('order' => 'obj',
                                                  'order_obj' => $order));
       if (! $email->send()) log_error($email->error);
       if (! empty($order->customer_id))
          write_customer_activity($email->activity,$order->customer_id,$db);
    }
}

function log_import($msg)
{
    global $import_log_filename;

    $log_file = @fopen($import_log_filename,'at');
    if ($log_file) {
       fwrite($log_file,'['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($log_file);
    }
}

function process_edi_846_document($db,$filename,$lines,$vendor_info)
{
    global $off_sale_option,$sold_out_option;
    global $free_shipping_option,$import_log_filename;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($sold_out_option)) $sold_out_option = 2;
    if (! isset($free_shipping_option)) $free_shipping_option = 3;
    $vendor_id = $vendor_info['id'];
    $query = 'select * from vendor_imports where parent=? and import_type=2 ' .
             'and import_source=3';
    $query = $db->prepare_query($query,$vendor_id);
    $import_info = $db->get_record($query);
    if (! $import_info) {
       if (! isset($db->error))
          log_error('No Vendor Import Found for EDI 846 document from Vendor #' .
                    $vendor_id);
       return;
    }
    $import_id = $import_info['id'];
    $log_msg = 'Started Vendor Import #'.$import_id.' ('.$import_info['name'] .
               ') for Vendor '.$vendor_info['name'];
    log_vendor_activity($log_msg);
    $import_log_filename = '../admin/vendors/import-'.$import_id.'.log';
    if (file_exists($import_log_filename)) unlink($import_log_filename);
    $query = 'update vendor_imports set import_started=?,' .
             'import_finished=null where id=?';
    $query = $db->prepare_query($query,time(),$import_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_import('Database Error: '.$db->error);   return;
    }

    $products = load_existing_products($db,$vendor_id);
    if ($products === null) return;
    $sku = null;   $discontinued = null;
    if ($import_info['discon_field'])
       $discon_field = $import_info['discon_field'];
    else $discon_field = 0;
    if ($import_info['discon_value'])
       $discon_value = $import_info['discon_value'];
    else $discon_value = null;
    $min_qty_for_sale = intval($import_info['min_qty_for_sale']);
    if ($import_info['notavail_status'])
       $notavail_status = $import_info['notavail_status'];
    else $notavail_status = $sold_out_option;
    foreach ($lines as $line) {
       $fields = explode('*',$line);
       if ($fields[0] == 'LIN') {
          if (isset($fields[3])) $sku = ltrim($fields[3],'0');
          if ($discon_field && isset($fields[$discon_field]))
             $discontinued = $fields[$discon_field];
       }
       else if (($fields[0] == 'QTY') && isset($fields[2])) {
          if (! $sku) {
             $error = 'Missing Product Number in EDI 846 document '.$filename;
             log_error($error);   log_import($error);
             continue;
          }
          $qty = intval($fields[2]);
          if (isset($products[$sku])) $product_id = $products[$sku]['id'];
          else $product_id = null;
          if (! $product_id) {
             $sku = null;   $discontinued = null;   continue;
          }
          $old_status = $products[$sku]['status'];
          if ($old_status == $off_sale_option) continue;
          if (($old_status == 0) || ($old_status == $free_shipping_option))
             $old_status = 0;
          else $old_status = $notavail_status;
          if (($qty < $min_qty_for_sale) ||
              ($discontinued && ($discontinued == $discon_value)))
             $new_status = $notavail_status;
          else $new_status = 0;
          $query = 'update product_inventory set qty=? where parent=?';
          $query = $db->prepare_query($query,$qty,$product_id);
          $db->log_query($query);
          if (! $db->query($query)) return;
          if (using_linked_inventory($db))
             update_linked_inventory($db,null,$qty,$product_id);
          if ((($old_status != $notavail_status) &&
               ($new_status == $notavail_status)) ||
              (($old_status == $notavail_status) &&
               ($new_status != $notavail_status))) {
             $query = 'update products set status=?,last_modified=? where id=?';
             $query = $db->prepare_query($query,$new_status,time(),$product_id);
             $db->log_query($query);
             if (! $db->query($query)) return;
             log_vendor_activity('Updated Qty/Status for Part Number '.$sku .
                                 ' from 846 Inventory Inquiry/Advice from ' .
                                 $vendor_info['name']);
             if (! call_shopping_event('update_product_status',
                      array($db,$product_id,$old_status,$new_status,
                            &$error_msg),false)) {
                log_error($error_msg);   log_import($error_msg);   return;
             }
          }
          else log_vendor_activity('Updated Qty for Part Number '.$sku .
                                   ' from 846 Inventory Inquiry/Advice from ' .
                                   $vendor_info['name']);

          $sku = null;   $discontinued = null;
       }
    }
    log_vendor_activity('Processed 846 Inventory Inquiry/Advice from ' .
                        $vendor_info['name']);
    $import_finished = time();
    $query = 'update vendor_imports set import_finished=? where id=?';
    $query = $db->prepare_query($query,$import_finished,$import_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_import('Database Error: '.$db->error);   return;
    }
    $query = 'update vendors set last_inv_import=? where id=?';
    $query = $db->prepare_query($query,$import_finished,$vendor_info['id']);
    $db->log_query($query);
    if (! $db->query($query)) {
       log_import('Database Error: '.$db->error);   return;
    }
    $log_msg = 'Finished Vendor Import #'.$import_id.' ('.$import_info['name'] .
               ') for Vendor '.$vendor_info['name'];
    log_vendor_activity($log_msg);
}

function process_edi_command($filename=null)
{
    global $as2secure_dir;

    if ((! $filename) && ((! isset($_SERVER['REQUEST_METHOD'])) ||
                          ($_SERVER['REQUEST_METHOD'] != 'POST'))) {
       print 'You have performed an HTTP GET on this URL.<br/>' .
             'To submit an AS2 message, you must POST the message to this URL.';
       return;
    }

    try {
       init_as2secure();
       if ($filename) {
          $file_header  = $as2secure_dir.'/messages/_rawincoming/'.$filename .
                          '.as2.header';
          $file_content = $as2secure_dir.'/messages/_rawincoming/'.$filename .
                          '.as2';
          $headers = AS2Header::parseText(file_get_contents($file_header));
          $request = new AS2Request(file_get_contents($file_content),$headers);
          $request->filename = $filename;
          $response = AS2Server::handle($request);
       }
       else $response = AS2Server::handle();
       $filename = $as2secure_dir.'/messages/_rawincoming/'.$response->filename .
                   '.payload_0';
       if (! file_exists($filename)) {
          log_error('Missing Payload for EDI document '.$response->filename);
          return;
       }
       $content = file_get_contents($filename);
       if (! $content) {
          log_error('Unable to open incoming EDI document '.$filename);
          return;
       }
       $lines = explode('~',$content);   $transaction_code = '';
       $sender_qualifier = '';   $sender_id = '';
       foreach ($lines as $line) {
          $fields = explode('*',$line);
          if (($fields[0] == 'ISA') && isset($fields[5],$fields[6])) {
             $sender_qualifier = trim($fields[5]);
             $sender_id = trim($fields[6]);
          }
          else if (($fields[0] == 'ST') && isset($fields[1])) {
             $transaction_code = $fields[1];   break;
          }
       }
       if ((! $sender_qualifier) || (! $sender_id)) {
          log_error('No Sender found in EDI document '.$response->filename);
          return;
       }
       $db = new DB();
       $query = 'select * from vendors where edi_receiver_qual=? and ' .
                'edi_receiver_id=?';
       $query = $db->prepare_query($query,$sender_qualifier,$sender_id);
       $vendor_info = $db->get_record($query);
       if (! $vendor_info) {
          log_error('No Vendor found for Qualifier '.$sender_qualifier .
                    ' and ID '.$sender_id.' in EDI document ' .
                    $response->filename);
          return;
       }
       if ($transaction_code == '856')
          process_edi_856_document($db,$filename,$lines,$vendor_info);
       else if ($transaction_code == '846')
          process_edi_846_document($db,$filename,$lines,$vendor_info);
       else log_error('Unknown incoming EDI transaction code ' .
                      $transaction_code.' in EDI document ' .
                      $response->filename);
    }
    catch (Exception $e) {
       log_error('AS2 Exception: '.$e->getMessage());
    }
}

function test_edi_command($argv)
{
    if (! isset($argv[2])) {
       print "EDI Document ID is required\n";   return;
    }
    process_edi_command($argv[2]);
}

function generate_850_document($argv)
{
    if (! isset($argv[2])) {
       print "Order ID is required\n";   return;
    }
    $db = new DB;
    if (strpos($argv[2],',')) $order_ids = explode(',',$argv[2]);
    else $order_ids = array($argv[2]);
    if (empty($order_ids)) {
       print "No Order IDs Specified\n";   return;
    }
    $query = 'select o.id,p.vendor,v.new_order_flag from orders o left join ' .
             'order_items i on i.parent=o.id left join products p on p.id=' .
             'i.product_id left join vendors v on v.id=p.vendor where ' .
             '(o.id in (?))';
    $query = $db->prepare_query($query,$order_ids);
    $order_items = $db->get_records($query);
    if (! $order_items) {
       print 'Database Error: '.$db->error."\n";   return;
    }
    $vendor_ids = array();
    foreach ($order_items as $row) {
       if ($row['new_order_flag'] != 2) continue;
       if (! in_array($row['vendor'],$vendor_ids))
          $vendor_ids[] = $row['vendor'];
    }
    if (empty($vendor_ids)) {
       print "No Vendors Found\n";   return;
    }
    $query = 'select * from vendors where id in (?)';
    $query = $db->prepare_query($query,$vendor_ids);
    $vendors = $db->get_records($query);
    if (! $vendors) {
       print 'Database Error: '.$db->error."\n";   return;
    }
    foreach ($vendors as $vendor_info) {
       $vendor_id = $vendor_info['id'];
       $vendor_orders = array();
       foreach ($order_items as $row) {
          if (($row['vendor'] == $vendor_id) &&
              (! in_array($row['id'],$vendor_orders)))
             $vendor_orders[] = $row['id'];
       }
       if (count($vendor_orders) == 0) continue;
       $edi_document = build_edi_850_document($db,$vendor_orders,$vendor_info);
       if (! $edi_document) return;
       print $edi_document."\n";
    }
}

$remote_user = getenv('REMOTE_USER');
if (! $remote_user) set_remote_user('edi');
if (isset($argc,$argv[1])) {
   if ($argv[1] == 'sendorders') send_orders($argv);
   else if ($argv[1] == 'test') test_edi_command($argv);
   else if ($argv[1] == 'generate850') generate_850_document($argv);
   else process_edi_command();
}
else process_edi_command();
DB::close_all();

?>
