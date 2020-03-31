<?php
/*
                      Inroads Shopping Cart - DHL API Module

                        Written 2013-2019 by Randall Severy
                         Copyright 2013-2019 Inroads, LLC
*/

global $dhl_options;
$dhl_options = array('1' => 'Domestic Express 12:00',
   '2' => 'B2C','3' => 'B2C','4' => 'JetLine','5' => 'SprintLine',
   '6' => 'Secure Line','7' => 'Express Easy','8' => 'Express Easy',
   '9' => 'EuroPack','A' => 'Auto Reversals','B' => 'Break Bulk Express',
   'C' => 'Medical Express','D' => 'Express Worldwide','E' => 'Express 9:00',
   'F' => 'Freight Worldwide','G' => 'Domestic Economy Select','H' => 'Economy Select',
   'I' => 'Break Bulk Economy','J' => 'Jumbo Box','K' => 'Express 9:00',
   'L' => 'Express 10:30','M' => 'Express 10:30','N' => 'Domestic Express',
   'O' => 'Domestic Express 10:30','P' => 'Express Worldwide','Q' => 'Medical Express',
   'R' => 'GlobalMail Business','S' => 'Same Day','T' => 'Express 12:00',
   'U' => 'Express Worldwide','V' => 'EuroPack','W' => 'Economy Select',
   'X' => 'Express Envelope','Y' => 'Express 12:00','Z' => 'Destination Charges');

function dhl_module_labels(&$module_labels)
{
    $module_labels['dhl'] = 'DHL';
}

function dhl_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['dhl_rates'] = 'DHL Rates';
    $shipping_tabs['dhl_labels'] = 'DHL Labels';
}

function dhl_shipping_cart_config_section($db,$dialog,$values)
{
    $dialog->start_subtab_content('dhl_rates_content',
                                  $dialog->current_subtab == 'dhl_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('dhl_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('DHL Site ID:','dhl_siteid',$values,30);
    $dialog->add_edit_row('DHL Password:','dhl_password',$values,30);
    $dialog->add_edit_row('DHL URL:','dhl_url',$values,50);
    add_handling_field($dialog,'dhl_handling',$values);
    $dhl_country = get_row_value($values,'dhl_country');
    if (empty($dhl_country)) $dhl_country = 1;
    $dialog->start_row('Default Origin Country:','middle');
    $dialog->start_choicelist('dhl_country');
    load_country_list($dhl_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Default Origin City:','dhl_city',$values,50);
    $dialog->add_edit_row('Default Origin Zip Code:','dhl_origin',$values,10);
    $dialog->add_edit_row('Default Weight:','dhl_weight',$values,10,null,' (Lbs)');

    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('dhl_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('dhl_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('DHL Account Number:','dhl_label_account',$values,20);
    $dialog->add_edit_row('Shipper Attention:','dhl_label_attn',$values,40);
    $dialog->add_edit_row('Shipper Company Name:','dhl_label_company',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','dhl_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','dhl_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','dhl_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','dhl_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','dhl_label_zip',$values,20);
    $dhl_label_country = get_row_value($values,'dhl_label_country');
    if (empty($dhl_label_country)) $dhl_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('dhl_label_country');
    load_country_list($dhl_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Phone Number:','dhl_label_phone',$values,40);

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function ups_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('dhl_siteid','dhl_password','dhl_url','dhl_handling',
       'dhl_country','dhl_city','dhl_origin','dhl_weight','dhl_label_account',
       'dhl_label_attn','dhl_label_company','dhl_label_address1',
       'dhl_label_address2','dhl_label_city','dhl_label_state','dhl_label_zip',
       'dhl_label_country','dhl_label_phone');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function dhl_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'dhl_handling')
       $new_field_value = parse_handling_field('dhl_handling');
    else return false;
    return true;
}

function call_dhl($obj,$post_string,&$error)
{
    $obj->log_shipping('DHL Sent: '.$post_string);
    $url = get_cart_config_value('dhl_url');
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $http->set_content_type('text/xml');
    $response_string = $http->call($post_string);
    if (! $response_string) {
       $error = $http->error.' ('.$http->status.')';   return null;
    }
    $response_string = str_replace("\n",'',$response_string);
    $response_string = str_replace("\r",'',$response_string);
    $obj->log_shipping('DHL Response: '.$response_string);
    if (($http->status != 100) && ($http->status != 200)) {
       $error = $response_string.' ('.$http->status.')';   return null;
    }
    return $response_string;
}

function get_dhl_rate(&$cart,$dhl_options,&$dhl_rates,$from_country,$from_city,
                      $from_zip,$to_country,$to_city,$to_zip,$weight,
                      &$dhl_currency)
{
    if ($from_country == 'US') {
       $size_measure = 'IN';   $weight_measure = 'LB';
    }
    else {
       $size_measure = 'CM';   $weight_measure = 'KG';
       $weight = $weight / 2.2;
    }

    $dhl_siteid = get_cart_config_value('dhl_siteid');
    $dhl_password = get_cart_config_value('dhl_password');

    $post_string = '<?xml version="1.0" encoding="UTF-8"?>';
    $post_string .= '<p:DCTRequest xmlns:p="http://www.dhl.com" xmlns:p1="' .
                    'http://www.dhl.com/datatypes" xmlns:p2="' .
                    'http://www.dhl.com/DCTRequestdatatypes" xmlns:xsi="' .
                    'http://www.w3.org/2001/XMLSchema-instance" ' .
                    'xsi:schemaLocation="http://www.dhl.com DCT-req.xsd ">';
    $post_string .= '<GetQuote><Request><ServiceHeader>';
    $post_string .= '<MessageTime>'.date('c').'</MessageTime>';
    $post_string .= '<SiteID>'.$dhl_siteid.'</SiteID>';
    $post_string .= '<Password>'.$dhl_password.'</Password>';
    $post_string .= '</ServiceHeader></Request>';
    $post_string .= '<From><CountryCode>'.$from_country.'</CountryCode>';
    $post_string .= '<Postalcode>'.$from_zip.'</Postalcode>';
    $post_string .= '<City>'.$from_city.'</City>';
    $post_string .= '</From>';
    $post_string .= '<BkgDetails>';
    $post_string .= '<PaymentCountryCode>'.$from_country.'</PaymentCountryCode>';
    $post_string .= '<Date>'.date('Y-m-d').'</Date>';
    $post_string .= '<ReadyTime>PT10H21M</ReadyTime>';
    $post_string .= '<DimensionUnit>'.$size_measure.'</DimensionUnit>';
    $post_string .= '<WeightUnit>'.$weight_measure.'</WeightUnit>';
    $post_string .= '<Pieces><Piece><PieceID>1</PieceID><Height>10</Height>';
    $post_string .= '<Depth>10</Depth><Width>10</Width><Weight>' .
                    ceil($weight).'</Weight></Piece></Pieces>';
    $post_string .= '<IsDutiable>N</IsDutiable>';
    $post_string .= '</BkgDetails>';
    $post_string .= '<To><CountryCode>'.$to_country.'</CountryCode>';
    $post_string .= '<Postalcode>'.$to_zip.'</Postalcode>';
    $post_string .= '<City>'.$to_city.'</City>';
    $post_string .= '</To>';
    $post_string .= '</GetQuote>';
    $post_string .= '</p:DCTRequest>';

    $response_string = call_dhl($cart,$post_string,$error);
    if (! $response_string) {
       $cart->errors['shippingerror'] = true;
       $cart->error = $error;   return false;
    }

    if ((strpos($response_string,'<res:ErrorResponse') !== false) ||
        (strpos($response_string,'<BkgDetails>') === false)) {
       $start_pos = strpos($response_string,'<ConditionData>');
       $start_pos += 15;
       $end_pos = strpos($response_string,'</ConditionData>',$start_pos);
       $cart->errors['shippingerror'] = true;
       $cart->error = substr($response_string,$start_pos,$end_pos - $start_pos);
       return false;
    }

    $start_pos = strpos($response_string,'<BkgDetails>');
    $start_pos += 12;
    $end_pos = strpos($response_string,'</BkgDetails>',$start_pos);
    $shipment_details = substr($response_string,$start_pos,$end_pos - $start_pos);

    $services_array = explode('</QtdShp>',$shipment_details);
    for ($loop = 0;  $loop < count($services_array) - 1;  $loop++) {
       $start_pos = strpos($services_array[$loop],'<GlobalProductCode>');
       $start_pos += 19;
       $end_pos = strpos($services_array[$loop],'</GlobalProductCode>');
       $dhl_code = substr($services_array[$loop],$start_pos,$end_pos - $start_pos);
       $pricing_array = explode('<QtdSInAdCur>',$services_array[$loop]);
       for ($loop2 = 1;  $loop2 < count($pricing_array);  $loop2++) {
          $start_pos = strpos($pricing_array[$loop2],'<CurrencyRoleTypeCode>');
          $start_pos += 22;
          $end_pos = strpos($pricing_array[$loop2],'</CurrencyRoleTypeCode>');
          $type_code = substr($pricing_array[$loop2],$start_pos,$end_pos - $start_pos);
          if ($type_code != 'BILLC') continue;
          if ($dhl_currency == '') {
             $start_pos = strpos($pricing_array[$loop2],'<CurrencyCode>');
             if ($start_pos !== false) {
                $start_pos += 14;
                $end_pos = strpos($pricing_array[$loop2],'</CurrencyCode>');
                $dhl_currency = substr($pricing_array[$loop2],$start_pos,$end_pos - $start_pos);
             }
          }
          $start_pos = strpos($pricing_array[$loop2],'<TotalAmount>');
          $start_pos += 13;
          $end_pos = strpos($pricing_array[$loop2],'</TotalAmount>');
          $dhl_rate = substr($pricing_array[$loop2],$start_pos,$end_pos - $start_pos);
          $index = 0;
          foreach ($dhl_options as $id => $label) {
             if ($dhl_code == $id) {
                $dhl_rates[$index] += $dhl_rate;   break;
             }
             $index++;
          }
       }
    }

    return true;
}

function dhl_load_shipping_options(&$cart,$customer)
{
    require 'dhl-common.php';
    require_once 'currency.php';

    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];
    $shipping_country_info = get_country_info($customer->shipping_country,
                                              $cart->db);
    $dhl_handling = $cart->get_handling($shipping_country_info,$customer,
                                        'dhl_handling');
    $shipping_country = $shipping_country_info['code'];
    $dhl_country = get_cart_config_value('dhl_country');
    $dhl_country_info = get_country_info($dhl_country,$cart->db);
    $dhl_country = $dhl_country_info['code'];
    $dhl_city = get_cart_config_value('dhl_city');
    $default_origin = get_cart_config_value('dhl_origin');
    $default_weight = get_cart_config_value('dhl_weight');
    $to_city = $customer->get('ship_city');
    $to_zip = $customer->get('ship_zipcode');
    $num_options = count($dhl_options);
    $dhl_rates = array($num_options);
    for ($loop = 0;  $loop < $num_options;  $loop++) $dhl_rates[$loop] = 0;

    $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                          $customer);
    $dhl_currency = '';
    foreach ($origin_info as $origin_zip => $weight) {
       if (isset($cart->use_default_origin) && $cart->use_default_origin)
          $origin_zip = $default_origin;
       $origin_country = $dhl_country;   $origin_city = $dhl_city;
       if (function_exists('get_dhl_origin_info')) {
          if (! get_dhl_origin_info($origin_zip,$origin_country,$origin_city))
             continue;
       }
       if (! get_dhl_rate($cart,$dhl_options,$dhl_rates,$origin_country,
                $origin_city,$origin_zip,$shipping_country,$to_city,$to_zip,
                floatval($weight),$dhl_currency))
          log_activity('DHL Error: '.$cart->error);
    }
    if ($dhl_currency && (($currency != 'USD') || ($dhl_currency != 'USD')))
       $exchange_rate = get_exchange_rate($dhl_currency,$currency);
    else $exchange_rate = 0.0;

    $shipping_method = get_form_field('shipping_method');
    if ($shipping_method) {
       $shipping_method_info = explode('|',$shipping_method);
       if (count($shipping_method_info) == 2)
          $shipping_method = $shipping_method_info[0];
       else $shipping_method = $shipping_method_info[1];
    }
    else if (isset($cart->info['shipping_method']))
       $shipping_method = $cart->info['shipping_method'];
    else if (function_exists('get_custom_shipping_default'))
       $shipping_method = get_custom_shipping_default($cart,null);
    else $shipping_method = null;
    $index = 0;   $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($dhl_options as $id => $label) {
       if ($dhl_rates[$index] == 0) {   $index++;   continue;   }
       $handling = $dhl_handling;
       if ($exchange_rate != 0.0)
          $rate = round(floatval($dhl_rates[$index]) * $exchange_rate,2);
       else $rate = $dhl_rates[$index];
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($rate * ($handling/100)),2);
       }
       $rate += $handling;
       $cart->add_shipping_option('dhl',$id,$rate,'DHL '.$label,
                                  $shipping_method == $id);
       $index++;
    }
}

function dhl_process_shipping(&$order,$shipping_method)
{
    require 'dhl-common.php';

    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    if (isset($dhl_options[$shipping_info[1]]))
       $order->set('shipping_method',$shipping_info[1].'|' .
                   $dhl_options[$shipping_info[1]]);
    else $order->set('shipping_method','');
}

function dhl_display_shipping_info($dialog,$order)
{
    $shipping_method = get_row_value($order->info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $dialog->add_text_row('Shipping Carrier:','DHL');
    $dialog->add_text_row('Shipping Method:','DHL '.$shipping_info[1],'top');
}

function dhl_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'DHL';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       $shipping_info = explode('|',$shipping_method);
       return 'DHL '.$shipping_info[1];
    }
    return null;
}

function dhl_get_tracking_url($tracking)
{
    $url = 'http://track.dhl-usa.com/TrackByNbr.asp?ShipmentNumber=' .
           $tracking;
// Global tracking = http://webtrack.dhlglobalmail.com/?mobile=&trackingnumber=XXXXXXXXXXXXXXXXX
    return $url;
}

function dhl_available_methods()
{
    require 'dhl-common.php';

    return $dhl_options;
}

function dhl_all_methods()
{
    return dhl_available_methods();
}

function dhl_default_weight($db)
{
    return get_cart_config_value('dhl_weight',$db);
}

function parse_dhl_response($response_string,$tag)
{
    $start_pos = strpos($response_string,'<'.$tag.'>');
    if ($start_pos === false) return null;
    $end_pos = strpos($response_string,'</'.$tag.'>');
    if ($end_pos === false) return null;
    $tag_length = strlen($tag);
    $value = substr($response_string,$start_pos + $tag_length + 2,
                    $end_pos - $start_pos - $tag_length - 2);
    return $value;
}

function dhl_verify_shipping_label($db)
{
    $dhl_account = get_cart_config_value('dhl_label_account',$db);
    if (! $dhl_account) return false;
    return true;
}

function dhl_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.pdf';
}

function dhl_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                     $label_filename)
{
    load_cart_config_values($order->db);
    $dhl_account = get_cart_config_value('dhl_label_account');
    if ((! isset($order->shipping['shipto'])) || ($order->shipping['shipto'] == '')) {
       $recipient_name = $order->info['fname'];
       if (isset($order->info['mname']) && ($order->info['mname'] != ''))
          $recipient_name .= ' '.$order->info['mname'];
       $recipient_name .= ' '.$order->info['lname'];
    }
    else $recipient_name = $order->shipping['shipto'];
    $default_weight = get_cart_config_value('dhl_weight');
    $weight = load_order_weight($order,$default_weight);
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $dhl_global_product_code = $shipping_info[0];

    $post_string = '<?xml version="1.0" encoding="UTF-8"?>';
    $post_string .= '<req:ShipmentValidateRequest xmlns:req="http://www.dhl.com" xmlns:xsi="' .
                    'http://www.w3.org/2001/XMLSchema-instance" ' .
                    'xsi:schemaLocation="http://www.dhl.com ship-val-req.xsd">';
    $post_string .= '<Request><ServiceHeader>';
    $post_string .= '<MessageTime>'.date('c').'</MessageTime>';
    $post_string .= '<SiteID>'.get_cart_config_value('dhl_siteid').'</SiteID>';
    $post_string .= '<Password>'.get_cart_config_value('dhl_password').'</Password>';
    $post_string .= '</ServiceHeader></Request>';
    $post_string .= '<RequestedPickupTime>N</RequestedPickupTime>';
    $post_string .= '<NewShipper>N</NewShipper>';
    $post_string .= '<LanguageCode>en</LanguageCode>';
    $post_string .= '<PiecesEnabled>Y</PiecesEnabled>';
    $post_string .= '<Billing>';
    $post_string .= '<ShipperAccountNumber>'.$dhl_account.'</ShipperAccountNumber>';
    $post_string .= '<ShippingPaymentType>S</ShippingPaymentType>';
    $post_string .= '<BillingAccountNumber>'.$dhl_account.'</BillingAccountNumber>';
    $post_string .= '<DutyPaymentType>S</DutyPaymentType>';
    $post_string .= '</Billing>';
    $post_string .= '<Consignee>';
    if ($order->info['company']) $company = $order->info['company'];
    else $company = $recipient_name;
    $post_string .= '<CompanyName>'.$company.'</CompanyName>';
    $post_string .= '<AddressLine>'.$order->shipping['address1'].'</AddressLine>';
    $address2 = $order->shipping['address2'];
    if ($address2) $post_string .= '<AddressLine>'.$address2.'</AddressLine>';
    $post_string .= '<City>'.$order->shipping['city'].'</City>';
    $state = $order->shipping['state'];
    $post_string .= '<Division>'.$state.'</Division>';
    if (strlen($state) > 2) $state = '';
    if ($state) $post_string .= '<DivisionCode>'.$state.'</DivisionCode>';
    $post_string .= '<PostalCode>'.$order->shipping['zipcode'].'</PostalCode>';
    $country_info = get_country_info($order->shipping['country'],$order->db);
    $post_string .= '<CountryCode>'.$country_info['code'].'</CountryCode>';
    $post_string .= '<CountryName>'.$country_info['country'].'</CountryName>';
    $post_string .= '<Contact>';
    $post_string .= '<PersonName>'.$recipient_name.'</PersonName>';
    $post_string .= '<PhoneNumber>'.$order->billing['phone'].'</PhoneNumber>';
    $post_string .= '</Contact>';
    $post_string .= '</Consignee>';
    $post_string .= '<ShipmentDetails>';
    $post_string .= '<NumberOfPieces>1</NumberOfPieces>';
    $post_string .= '<Pieces><Piece></Piece></Pieces>';
    $post_string .= '<Weight>'.round($weight/2.2,1).'</Weight>';
    $post_string .= '<WeightUnit>K</WeightUnit>';
    $post_string .= '<GlobalProductCode>'.$dhl_global_product_code.'</GlobalProductCode>';
    $post_string .= '<Date>'.date('Y-m-d').'</Date>';
    $post_string .= '<Contents>Shopping Cart Order #'.$order->id.'</Contents>';
    $post_string .= '<CurrencyCode>'.$order->info['currency'].'</CurrencyCode>';
    $post_string .= '</ShipmentDetails>';
    $post_string .= '<Shipper>';
    $post_string .= '<ShipperID>'.$dhl_account.'</ShipperID>';
    $post_string .= '<CompanyName>'.get_cart_config_value('dhl_label_company').'</CompanyName>';
    $post_string .= '<RegisteredAccount>'.$dhl_account.'</RegisteredAccount>';
    $post_string .= '<AddressLine>'.get_cart_config_value('dhl_label_address1').'</AddressLine>';
    $address2 = get_cart_config_value('dhl_label_address1');
    if ($address2) $post_string .= '<AddressLine>'.$address2.'</AddressLine>';
    $post_string .= '<City>'.get_cart_config_value('dhl_label_city').'</City>';
    $state = get_cart_config_value('dhl_label_state');
    $post_string .= '<Division>'.$state.'</Division>';
    if (strlen($state) > 2) $state = '';
    if ($state) $post_string .= '<DivisionCode>'.$state.'</DivisionCode>';
    $post_string .= '<PostalCode>'.get_cart_config_value('dhl_label_zip').'</PostalCode>';
    $country_info = get_country_info(get_cart_config_value('dhl_label_country'),
                                     $order->db);
    $post_string .= '<CountryCode>'.$country_info['code'].'</CountryCode>';
    $post_string .= '<CountryName>'.$country_info['country'].'</CountryName>';
    $post_string .= '<Contact>';
    $post_string .= '<PersonName>'.get_cart_config_value('dhl_label_attn').'</PersonName>';
    $post_string .= '<PhoneNumber>'.get_cart_config_value('dhl_label_phone').'</PhoneNumber>';
    $post_string .= '</Contact>';
    $post_string .= '</Shipper>';
    $post_string .= '<LabelImageFormat>PDF</LabelImageFormat>';
    $post_string .= '</req:ShipmentValidateRequest>';

    $response_string = call_dhl($order,$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    if (strpos($response_string,'<res:ShipmentValidateErrorResponse') !== false) {
       $order->error = parse_dhl_response($response_string,'ConditionData');
       return false;
    }

    $tracking_num = parse_dhl_response($response_string,'AirwayBillNumber');
    if ($tracking_num == null) {
       $order->error = 'Unable to find TrackingNumber';   return false;
    }
    $shipment_info['tracking'] = $tracking_num;

    $output_image = parse_dhl_response($response_string,'OutputImage');
    if ($output_image == null) {
       $order->error = 'Unable to find OutputImage';   return false;
    }

    $pdf = base64_decode($output_image);
    $pdf_file = fopen($label_filename,'wb');
    if (! $pdf_file) {
       $order->error = 'Unable to create label pdf file';   return false;
    }
    fwrite($pdf_file,$pdf);
    fclose($pdf_file);

    return true;
}

function dhl_print_shipping_label($db,$order,$label_filename)
{
    header('Content-Type: application/pdf');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    print file_get_contents($label_filename);
}

?>
