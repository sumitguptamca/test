<?php
/*
                      Inroads Shopping Cart - GLS API Module

                        Written 2014-2018 by Randall Severy
                         Copyright 2014-2018 Inroads, LLC
*/

global $gls_label_formats;
$gls_label_formats = array(
   'A6' => 'A6 format, blank label',
   'A6_PP' => 'A6 format, preprinted label',
   'A6_ONA4' => 'A6 format, printed on A4',
   'A4_2x2' => 'A4 format, 4 labels on layout 2x2',
   'A4_4x1' => 'A4 format, 4 labels on layout 4x1'
);

global $gls_services;
$gls_services = array(
   'T12' => 'Express Service',
   'PSS' => 'Pick&Ship Service',
   'PRS' => 'Pick&Return Service',
   'XS'  => 'Exchange Service',
   'SZL' => 'DocumentReturn Service',
   'INS' => 'DeclaredValueInsurance',
   'SBS' => 'Standby Service',
   'DDS' => 'DayDefinite',
   'SDS' => 'ScheduledDelivery',
   'SAT' => 'Saturday Service',
   'AOS' => 'AddresseeOnly',
   '24H' => 'Guaranteed24 Service',
   'EXW' => 'ExWorks Service',
   'SM1' => 'SMS Service',
   'SM2' => 'PreAdvice Service',
   'CS1' => 'Contact Service',
   'TGS' => 'ThinkGreen Service',
   'FDS' => 'FlexDelivery Service',
   'FSS' => 'FlexDeivery SMS Service',
   'PSD' => 'ShopDelivery Service',
   'DPV' => 'DeclaredParcelValue'
);

function gls_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['gls_labels'] = 'GLS Labels';
}

function gls_shipping_cart_config_section($db,$dialog,$values)
{
    global $gls_label_formats,$gls_services;

    $dialog->start_subtab_content('gls_labels_content',
                                  $dialog->current_subtab == 'gls_labels_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('gls_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('GLS Hostname:','gls_label_hostname',$values,40);
    $dialog->add_edit_row('GLS User Name:','gls_label_username',$values,40);
    $dialog->add_edit_row('GLS Password:','gls_label_password',$values,40);
    $dialog->add_edit_row('GLS Client Number:','gls_label_senderid',$values,40);
    $dialog->add_edit_row('Shipper Name:','gls_label_name',$values,40);
    $dialog->add_edit_row('Shipper Address:','gls_label_address',$values,40);
    $dialog->add_edit_row('Shipper City:','gls_label_city',$values,40);
    $dialog->add_edit_row('Shipper Postal Code:','gls_label_zip',$values,20);
    $gls_label_country = get_row_value($values,'gls_label_country');
    if (empty($gls_label_country)) $gls_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('gls_label_country');
    load_country_list($gls_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Contact Person:','gls_label_contact',$values,40);
    $dialog->add_edit_row('Shipper Phone Number:','gls_label_phone',$values,40);
    $dialog->add_edit_row('Shipper E-Mail Address:','gls_label_email',$values,40);
    $dialog->add_edit_row('Label Content:','gls_label_content',$values,40);
    $dialog->start_row('Service:','middle');
    $gls_label_service = get_row_value($values,'gls_label_service');
    $dialog->start_choicelist('gls_label_service');
    $dialog->add_list_item('','',(! $gls_label_service));
    foreach ($gls_services as $curr_code => $curr_label)
       $dialog->add_list_item($curr_code,$curr_label,($curr_code == $gls_label_service));
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Label Format:','middle');
    $gls_label_format = get_row_value($values,'gls_label_format');
    $dialog->start_choicelist('gls_label_format');
    $dialog->add_list_item('','',(! $gls_label_format));
    foreach ($gls_label_formats as $curr_code => $curr_label)
       $dialog->add_list_item($curr_code,$curr_label,
                             ($curr_code == $gls_label_format));
    $dialog->end_choicelist();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function gls_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('gls_label_hostname','gls_label_username',
       'gls_label_password','gls_label_senderid','gls_label_name',
       'gls_label_address','gls_label_city','gls_label_zip',
       'gls_label_country','gls_label_contact','gls_label_phone',
       'gls_label_email','gls_label_content','gls_label_service',
       'gls_label_format');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function gls_process_shipping(&$order,$shipping_method)
{
    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    $order->set('shipping_method',$shipping_info[1]);
}

function gls_display_shipping_info($dialog,$order)
{
    $dialog->add_text_row('Shipping Carrier:','GLS');
}

function gls_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'GLS';
    return null;
}

function gls_get_tracking_url($tracking)
{
    return null;
}

function gls_available_methods()
{
    return array();
}

function gls_all_methods()
{
    return gls_available_methods();
}

function getHash($data)
{
    $hashBase = '';
    foreach ($data as $key => $value) {
       if (($key != 'services') && ($key != 'hash') && ($key != 'timestamp') &&
           ($key != 'printit') && ($key != 'printertemplate'))
          $hashBase .= $value;
    }
    return sha1($hashBase);
}

function gls_verify_shipping_label($db)
{
    $gls_hostname = get_cart_config_value('gls_label_hostname',$db);
    if (! $gls_hostname) return false;
    return true;
}

function gls_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.pdf';
}

function gls_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                     $label_filename)
{
    require_once '../engine/nusoap/nusoap.php';

    load_cart_config_values($order->db);
    $hostname = get_cart_config_value('gls_label_hostname');

    $gls_url = 'https://'.$hostname.'/webservices/soap_server.php?wsdl';
    $gls = new nusoap_client($gls_url,'wsdl');
    $gls->soap_defencoding = 'UTF-8';
    $gls->decode_utf8 = false;
    $sender_country_info = get_country_info(get_cart_config_value('gls_label_country'),
                                            $order->db);
    if ((! isset($order->shipping['shipto'])) || ($order->shipping['shipto'] == '')) {
       $recipient_name = $order->info['fname'];
       if (isset($order->info['mname']) && ($order->info['mname'] != ''))
          $recipient_name .= ' '.$order->info['mname'];
       $recipient_name .= ' '.$order->info['lname'];
    }
    else $recipient_name = $order->shipping['shipto'];
    $country_id = $order->shipping['country'];
    $recipient_country_info = get_country_info($country_id,$order->db);
    $label_service = get_cart_config_value('gls_label_service');
    if ($label_service) $services = array(array('code' => $label_service));
    else $services = array();
    $default_timezone = date_default_timezone_get();
    date_default_timezone_set('Europe/Budapest');
    $pickupdate = date('Y-m-d');
    $timestamp = date('YmdHis');
    date_default_timezone_set($default_timezone);
    $codamount = ceil($order->info['balance_due'] / 5) * 5;

    $request = array(
       'username' => get_cart_config_value('gls_label_username'),
       'password' => get_cart_config_value('gls_label_password'),
       'senderid' => get_cart_config_value('gls_label_senderid'),
       'sender_name' => get_cart_config_value('gls_label_name'),
       'sender_address' => get_cart_config_value('gls_label_address'),
       'sender_city' => get_cart_config_value('gls_label_city'),
       'sender_zipcode' => get_cart_config_value('gls_label_zip'),
       'sender_country' => $sender_country_info['code'],
       'sender_contact' => get_cart_config_value('gls_label_contact'),
       'sender_phone' => get_cart_config_value('gls_label_phone'),
       'sender_email' => get_cart_config_value('gls_label_email'),
       'consig_name' => $recipient_name,
       'consig_address' => $order->shipping['address1'],
       'consig_city' => $order->shipping['city'],
       'consig_zipcode' => $order->shipping['zipcode'],
       'consig_country' => $recipient_country_info['code'],
       'consig_contact' => $recipient_name,
       'consig_phone' => $order->billing['phone'],
       'consig_email' => $order->info['email'],
       'pcount' => 1,
       'pickupdate' => $pickupdate,
       'content' => get_cart_config_value('gls_label_content'),
       'clientref' => $order->customer_id,
       'codamount' => $codamount,
       'codref' => $order->id,
       'services' => $services,
       'printertemplate' => get_cart_config_value('gls_label_format'),
       'printit' => true,
       'timestamp' => $timestamp,
       'hash' => 'xsd:string'
    );
    $request['hash'] = getHash($request);
    $log_string = print_r($request,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('GLS Sent: '.$log_string);

    $return = $gls->call('printlabel',$request);
    if (! $return) {
       if (isset($gls->error_str)) $order->error = $gls->error_str;
       else $order->error = 'Unknown GLS Error';
       $order->log_shipping('GLS Error: '.$order->error);
       log_activity('GLS Error: '.$order->error);   return false;
       return false;
    }
    $log_string = print_r($return,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('GLS Response: '.$log_string);
    if (! isset($return['successfull'])) {
       $order->error = $return['errdesc'];
       $order->log_shipping('GLS Error: '.$order->error);
       log_activity('GLS Error: '.$order->error);   return false;
       return false;
    }
    $pdf = base64_decode($return['pdfdata']);
    $pdf_file = fopen($label_filename,'wb');
    if (! $pdf_file) {
       $order->error = 'Unable to create label pdf file';   return false;
    }
    fwrite($pdf_file,$pdf);
    fclose($pdf_file);

    return true;
}

function gls_print_shipping_label($db,$order,$label_filename)
{
    header('Content-Type: application/pdf');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    print file_get_contents($label_filename);
}

?>
