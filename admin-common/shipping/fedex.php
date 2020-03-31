<?php
/*
                      Inroads Shopping Cart - FedEx API Module

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

define ('FEDEX_MAX_WEIGHT',150);

global $fedex_ground_delivery;
if (! isset($fedex_ground_delivery)) $fedex_ground_delivery = '';

global $fedex_options;
$fedex_options = array('Priority Overnight','Standard Overnight',
   'First Overnight','2 Day','Express Saver','International Priority',
   'International Economy','International First','Overnight Freight',
   '2 day Freight','3 day Freight','Ground'.$fedex_ground_delivery,
   'Home Delivery','Smart Post');

global $fedex_option_ids;
$fedex_option_ids = array('PRIORITY_OVERNIGHT','STANDARD_OVERNIGHT',
   'FIRST_OVERNIGHT','FEDEX_2_DAY','FEDEX_EXPRESS_SAVER',
   'INTERNATIONAL_PRIORITY','INTERNATIONAL_ECONOMY','INTERNATIONAL_FIRST',
   'FEDEX_1_DAY_FREIGHT','FEDEX_2_DAY_FREIGHT','FEDEX_3_DAY_FREIGHT',
   'FEDEX_GROUND','GROUND_HOME_DELIVERY','SMART_POST');

function fedex_module_labels(&$module_labels)
{
    $module_labels['fedex'] = 'FedEx';
}

function fedex_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['fedex_rates'] = 'FedEx Rates';
}

function fedex_shipping_cart_config_section($db,$dialog,$values)
{
    global $fedex_options;

    $dialog->start_subtab_content('fedex_rates_content',
                                  $dialog->current_subtab == 'fedex_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('fedex_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Account Number:','fedex_account',$values,15);
    $dialog->add_edit_row('Meter Number:','fedex_meter',$values,15);
    $dialog->add_edit_row('Authentication Key:','fedex_auth_key',$values,30);
    $dialog->add_edit_row('Authentication Password:','fedex_auth_password',$values,30);
    $dialog->add_edit_row('FedEx Hostname:','fedex_hostname',$values,30);
    add_handling_field($dialog,'fedex_handling',$values);
    if (empty($values['fedex_max_weight']))
       $values['fedex_max_weight'] = FEDEX_MAX_WEIGHT;
    $dialog->add_edit_row('Max Package Weight:','fedex_max_weight',$values,
                          10,null,' (Lbs)');
    $dialog->start_row('Default Origin State:','middle');
    $dialog->start_choicelist('fedex_origin_state');
    $fedex_origin_state = get_row_value($values,'fedex_origin_state');
    $dialog->add_list_item('','',! $fedex_origin_state);
    load_state_list($fedex_origin_state,false);
    $dialog->end_listbox();
    $dialog->end_row();
    $dialog->add_edit_row('Default Origin Zip:','fedex_origin_zip',$values,10);
    $dialog->add_edit_row('Default Weight:','fedex_weight',$values,10,null,' (Lbs)');
    $dialog->start_row('Dropoff Type:','middle');
    $dialog->start_choicelist('fedex_dropoff');
    $fedex_dropoff = get_row_value($values,'fedex_dropoff');
    $dropoff_convert = array('REGULARPICKUP' => 'REGULAR_PICKUP',
       'REQUESTCOURIER' => 'REQUEST_COURIER','DROPBOX' => 'DROP_BOX',
       'BUSINESSSERVICECENTER' => 'BUSINESS_SERVICE_CENTER');
    if (isset($dropoff_convert[$fedex_dropoff]))
       $fedex_dropoff = $dropoff_convert[$fedex_dropoff];
    $dialog->add_list_item('REGULAR_PICKUP','Regular Pickup',
                           $fedex_dropoff == 'REGULAR_PICKUP');
    $dialog->add_list_item('REQUEST_COURIER','Request Courier',
                           $fedex_dropoff == 'REQUEST_COURIER');
    $dialog->add_list_item('DROP_BOX','Drop Box',
                           $fedex_dropoff == 'DROP_BOX');
    $dialog->add_list_item('BUSINESS_SERVICE_CENTER',
                           'Business Service Center',
                           $fedex_dropoff == 'BUSINESS_SERVICE_CENTER');
    $dialog->add_list_item('STATION','Station',$fedex_dropoff == 'STATION');
    $dialog->end_listbox();
    $dialog->end_row();
    $fedex_rate = get_row_value($values,'fedex_rate');
    $dialog->start_row('Rate Option:','middle');
    $dialog->add_radio_field('fedex_rate','list','List Rates',$fedex_rate == 'list');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('fedex_rate','discount','Discounted Rates',
                             $fedex_rate == 'discount');
    $dialog->end_row();
    $fedex_services = get_row_value($values,'fedex_services');
    $fedex_service_default = get_row_value($values,'fedex_service_default');
    $dialog->start_row('Available Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('fedex_service_default','-1','',
                             $fedex_service_default == -1);
    $dialog->end_row();
    foreach ($fedex_options as $index => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('fedex_services_'.$index,'',
                                   $fedex_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('fedex_service_default',$index,'',
                                $index == $fedex_service_default,null);
       $dialog->end_row();
    }
    $dialog->end_table();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function fedex_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('fedex_account','fedex_meter','fedex_auth_key',
       'fedex_auth_password','fedex_hostname','fedex_handling',
       'fedex_origin_state','fedex_origin_zip','fedex_weight','fedex_dropoff',
       'fedex_rate','fedex_services','fedex_service_default',
       'fedex_max_weight');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function fedex_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    global $fedex_options;

    if ($field_name == 'fedex_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($fedex_options);  $loop++)
          if (get_form_field('fedex_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'fedex_handling')
       $new_field_value = parse_handling_field('fedex_handling');
    else return false;
    return true;
}

function get_fedex_rate(&$cart,$fedex_option_ids,&$fedex_rates,$from_state,
   $from_zip,$to_country,$to_state,$to_zip,$address_type,$weight,$qty)
{
    $fedex_account = get_cart_config_value('fedex_account');
    $fedex_meter = get_cart_config_value('fedex_meter');
    $fedex_auth_key = get_cart_config_value('fedex_auth_key');
    $fedex_auth_password = get_cart_config_value('fedex_auth_password');
    $fedex_dropoff = get_cart_config_value('fedex_dropoff');
    if ($fedex_dropoff == '') $fedex_dropoff = 'REGULAR_PICKUP';
    $fedex_rate_option = get_cart_config_value('fedex_rate');
    $now = time();
    $weight = number_format($weight,2);
    if ($weight == '0.00') $weight = '0.01';
    $tomorrow  = mktime(0,0,0,date('m',$now),date('d',$now)+1,date('Y',$now));

    $post_string = '<?xml version="1.0" encoding="UTF-8" ?>';
    $post_string .= '<RateRequest xmlns="http://fedex.com/ws/rate/v13">';
    $post_string .= '<WebAuthenticationDetail><UserCredential><Key>' .
                    $fedex_auth_key.'</Key><Password>'.$fedex_auth_password .
                    '</Password></UserCredential></WebAuthenticationDetail>';
    $post_string .= '<ClientDetail><AccountNumber>'.$fedex_account;
    $post_string .= '</AccountNumber><MeterNumber>'.$fedex_meter.'</MeterNumber>';
    $post_string .= '</ClientDetail>';
    $post_string .= '<Version>';
    $post_string .= '<ServiceId>crs</ServiceId>';
    $post_string .= '<Major>13</Major>';
    $post_string .= '<Intermediate>0</Intermediate>';
    $post_string .= '<Minor>0</Minor>';
    $post_string .= '</Version>';
    $post_string .= '<RequestedShipment>';
    $post_string .= '<DropoffType>'.$fedex_dropoff.'</DropoffType>';
    $post_string .= '<PackagingType>YOUR_PACKAGING</PackagingType>';
    $post_string .= '<Shipper><Address>';
    $post_string .= '<StateOrProvinceCode>'.$from_state.'</StateOrProvinceCode>';
    $post_string .= '<PostalCode>'.$from_zip.'</PostalCode>';
    $post_string .= '<CountryCode>US</CountryCode>';
    $post_string .= '</Address></Shipper>';
    $post_string .= '<Recipient><Address>';
    if ($to_state)
       $post_string .= '<StateOrProvinceCode>'.$to_state.'</StateOrProvinceCode>';
    $post_string .= '<PostalCode>'.$to_zip.'</PostalCode>';
    $post_string .= '<CountryCode>'.$to_country.'</CountryCode>';
    if ($address_type == 2) $post_string .= '<Residential>true</Residential>';
    $post_string .= '</Address></Recipient>';
    $post_string .= '<ShippingChargesPayment>';
    $post_string .= '<PaymentType>SENDER</PaymentType>';
    $post_string .= '</ShippingChargesPayment>';
    if ($fedex_rate_option == 'list')
       $post_string .= '<RateRequestTypes>LIST</RateRequestTypes>';
    $post_string .= '<PackageCount>1</PackageCount>';
    $post_string .= '<RequestedPackageLineItems>';
    $post_string .= '<SequenceNumber>1</SequenceNumber>';
    $post_string .= '<GroupPackageCount>1</GroupPackageCount>';
    $post_string .= '<Weight><Units>LB</Units>';
    $post_string .= '<Value>'.$weight.'</Value></Weight>';
    $post_string .= '</RequestedPackageLineItems>';
    $post_string .= '</RequestedShipment></RateRequest>';

    $cart->log_shipping('FedEx Sent: '.$post_string);
    $host = get_cart_config_value('fedex_hostname');
    require_once '../engine/http.php';
    $url = 'https://'.$host.'/xml';
    $http = new HTTP($url);
    $response_string = $http->call($post_string);
    if (! $response_string) {
       $cart->error = $http->error.' ('.$http->status.')';   return false;
    }
    $response_string = str_replace("\n",'',$response_string);
    $response_string = str_replace("\r",'',$response_string);
    $cart->log_shipping('FedEx Response: '.$response_string);
    if (($http->status != 100) && ($http->status != 200)) {
       $cart->errors['shippingerror'] = true;
       $cart->error = $response_string.' ('.$http->status.')';   return false;
    }
    if (strpos($response_string,'<faultstring>') !== false) {
       $start_pos = strpos($response_string,'<faultstring>');
       $end_pos = strpos($response_string,'</faultstring>');
       $error_message = substr($response_string,$start_pos + 13,
                               $end_pos - $start_pos - 13);
       $cart->errors['shippingerror'] = true;
       $cart->error = $error_message;
       return false;
    }
    if ((strpos($response_string,'<Notifications>') !== false)) {
       $start_pos = strpos($response_string,'<Severity>');
       $end_pos = strpos($response_string,'</Severity>');
       if (($start_pos !== false) && ($end_pos !== false))
          $severity = substr($response_string,$start_pos + 10,
                             $end_pos - $start_pos - 10);
       else $severity = null;
       $start_pos = strpos($response_string,'<Message>');
       $end_pos = strpos($response_string,'</Message>');
       $message = substr($response_string,$start_pos + 9,
                         $end_pos - $start_pos - 9);
       $message = str_replace("\n",' ',$message);
       if ($severity == 'SUCCESS') {}
       else if ($severity == 'WARNING')
          log_error('FedEx Warning: '.$message);
       else if ($severity == 'INFORMATIONAL')
          log_activity('FedEx Information: '.$message);
       else if ($severity == 'NOTE')
          log_activity('FedEx Note: '.$message);
       else {
          $cart->errors['shippingerror'] = true;
          $cart->error = $message;
          return false;
       }
    }

    $services_array = explode('</RateReplyDetails>',$response_string);
    for ($loop = 0;  $loop < count($services_array) - 1;  $loop++) {
       $start_pos = strpos($services_array[$loop],'<ServiceType>');
       $end_pos = strpos($services_array[$loop],'</ServiceType>');
       $fedex_code = substr($services_array[$loop],$start_pos + 13,
                            $end_pos - $start_pos - 13);
       if ($fedex_rate_option == 'list') {
          $offset = strpos($services_array[$loop],'<ListCharges>');
          if ($offset === false) $offset = 0;
       }
       else $offset = 0;
       $start_pos = strpos($services_array[$loop],'<NetCharge>',$offset);
       $end_pos = strpos($services_array[$loop],'</NetCharge>',$offset);
       $net_charge = substr($services_array[$loop],$start_pos + 11,
                            $end_pos - $start_pos - 11);
       $start_pos = strpos($net_charge,'<Amount>');
       $end_pos = strpos($net_charge,'</Amount>');
       $fedex_rate = substr($net_charge,$start_pos + 8,
                            $end_pos - $start_pos - 8);
       foreach ($fedex_option_ids as $index => $label) {
          if ($fedex_code == $label) {
             $fedex_rates[$index] = ($fedex_rate * $qty);   break;
          }
       }
   }

   return true;
}

function init_fedex_rates($num_options)
{
    $rates = array();
    for ($loop = 0;  $loop < $num_options;  $loop++) $rates[$loop] = 0;
    return $rates;
}

function update_fedex_rates(&$fedex_rates,$new_rates,$num_options)
{
    static $first_time;

    if ($num_options == 0) {
       $first_time = true;   return;
    }
    for ($loop = 0;  $loop < $num_options;  $loop++) {
       if ($new_rates[$loop] == 0) $fedex_rates[$loop] = 0;
       else if ((! $first_time) && ($fedex_rates[$loop] == 0)) continue;
       else $fedex_rates[$loop] += $new_rates[$loop];
    }
    $first_time = false;
}

function fedex_load_shipping_options(&$cart,$customer)
{
    global $fedex_options,$fedex_option_ids,$us_territory_states;

    require_once '../cartengine/currency.php';

    $fedex_account = get_cart_config_value('fedex_account');
    if (! $fedex_account) return;
    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];
    $fedex_services = get_cart_config_value('fedex_services');
    $fedex_service_default = get_cart_config_value('fedex_service_default');
    $shipping_country_info = get_country_info($customer->shipping_country,
                                              $cart->db);
    $fedex_handling = $cart->get_handling($shipping_country_info,$customer,
                                          'fedex_handling');
    $default_origin = get_cart_config_value('fedex_origin_zip');
    $default_weight = get_cart_config_value('fedex_weight');
    $from_state = get_cart_config_value('fedex_origin_state');
    $to_zip = $customer->get('ship_zipcode');
    $to_country = $shipping_country_info['code'];
    if ($customer->shipping_country == 1) {
       $to_state = $customer->get('ship_state');
       if (in_array($to_state,$us_territory_states)) $to_country = $to_state;
    }
    else $to_state = $customer->get('ship_province');
    $address_type = get_address_type($customer);
    $num_options = count($fedex_options);
    $fedex_rates = init_fedex_rates($num_options);
    update_fedex_rates($fedex_rates,null,0);
    $max_weight = get_cart_config_value('fedex_max_weight');
    if (empty($max_weight)) $max_weight = FEDEX_MAX_WEIGHT;

    $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                          $customer);
    foreach ($origin_info as $origin_zip => $weight) {
       if (($customer->shipping_country == 1) && (! $to_zip)) continue;
       if ($weight > $max_weight) {
          $num_packages = intval($weight / $max_weight);
          $remaining_weight = $weight - ($num_packages * $max_weight);
          $new_rates = init_fedex_rates($num_options);
          if (! get_fedex_rate($cart,$fedex_option_ids,$new_rates,
                               $from_state,$origin_zip,$to_country,$to_state,
                               $to_zip,$address_type,$max_weight,
                               $num_packages))
             log_error('FedEx Error: '.$cart->error);
          update_fedex_rates($fedex_rates,$new_rates,$num_options);
          if ($remaining_weight) {
             $new_rates = init_fedex_rates($num_options);
             if (! get_fedex_rate($cart,$fedex_option_ids,$new_rates,
                                  $from_state,$origin_zip,$to_country,$to_state,
                                  $to_zip,$address_type,
                                  floatval($remaining_weight),1))
                log_error('FedEx Error: '.$cart->error);
             update_fedex_rates($fedex_rates,$new_rates,$num_options);
          }
       }
       else {
          $new_rates = init_fedex_rates($num_options);
          if (! get_fedex_rate($cart,$fedex_option_ids,$new_rates,
                               $from_state,$origin_zip,$to_country,$to_state,
                               $to_zip,$address_type,floatval($weight),1))
             log_error('FedEx Error: '.$cart->error);
          update_fedex_rates($fedex_rates,$new_rates,$num_options);
       }
    }

    if ($currency != 'USD')
       $exchange_rate = get_exchange_rate('USD',$currency);
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
       $shipping_method = get_custom_shipping_default($cart,
                                                      $fedex_service_default);
    else $shipping_method = $fedex_service_default;
    $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($fedex_options as $index => $label) {
       if (! ($fedex_services & (1 << $index))) continue;
       if ($fedex_rates[$index] == 0) continue;
       $handling = $fedex_handling;
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($fedex_rates[$index] * ($handling/100)),2);
       }
       $rate = $fedex_rates[$index] + $handling;
       if ($exchange_rate != 0.0) $rate = floatval($rate) * $exchange_rate;
       $cart->add_shipping_option('fedex',$index,$rate,'FedEx '.$label,
                                  $shipping_method == $index);
    }
}

function fedex_process_shipping(&$order,$shipping_method)
{
    global $fedex_options;

    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    if (isset($fedex_options[$shipping_info[1]]))
       $order->set('shipping_method',$shipping_info[1].'|' .
                   $fedex_options[$shipping_info[1]]);
    else $order->set('shipping_method','');
}

function fedex_display_shipping_info($dialog,$order)
{
    $shipping_method = get_row_value($order->info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $dialog->add_text_row('Shipping Carrier:','FedEx');
    if (isset($shipping_info[1])) {
       $shipping_method = $shipping_info[1];
       if (strpos($shipping_method,'FedEx') === false)
          $shipping_method = 'FedEx '.$shipping_method;
    }
    else $shipping_method = 'FedEx';
    $dialog->add_text_row('Shipping Method:',$shipping_method,'top');
}

function fedex_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'FedEx';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       $shipping_info = explode('|',$shipping_method);
       if (strpos($shipping_info[1],'FedEx') === false)
          $shipping_info[1] = 'FedEx '.$shipping_info[1];
       return $shipping_info[1];
    }
    return null;
}

function fedex_get_tracking_url($tracking)
{
    $url = 'http://www.fedex.com/Tracking?action=track&tracknumbers=' .
           $tracking;
    return $url;
}

function fedex_all_methods()
{
    global $fedex_options,$fedex_option_ids;

    $methods = array();
    foreach ($fedex_options as $index => $label)
       $methods[$fedex_option_ids[$index]] = $label;
    return $methods;
}

function fedex_available_methods()
{
    global $fedex_options,$fedex_option_ids;

    $fedex_services = get_cart_config_value('fedex_services');
    $methods = array();
    foreach ($fedex_options as $index => $label) {
       if (! ($fedex_services & (1 << $index))) continue;
       $methods[$fedex_option_ids[$index]] = $label;
    }
    return $methods;
}

function fedex_default_weight($db)
{
    return get_cart_config_value('fedex_weight',$db);
}

?>
