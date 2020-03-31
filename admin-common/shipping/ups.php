<?php
/*
                      Inroads Shopping Cart - UPS API Module

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (! function_exists('get_server_type')) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require_once 'cartconfig-common.php';
   require_once 'orders-common.php';
   $ups_certification = true;
}
else $ups_certification = false;

define ('UPS_MAX_WEIGHT',150);

global $ups_ground_delivery;
if (! isset($ups_ground_delivery)) $ups_ground_delivery = '';

global $ups_options;
$ups_options = array('01' => 'Next Day Air','02' => '2nd Day Air',
   '03' => 'Ground'.$ups_ground_delivery,'07' => 'Worldwide Express','08' => 'Worldwide Expedited',
   '11' => 'Standard','12' => '3 Day Select','13' => 'Next Day Air Saver',
   '14' => 'Next Day Air Early A.M.','54' => 'Worldwide Express Plus',
   '59' => '2nd Day Air A.M.','65' => 'Express Saver');

global $ups_pickup_options;
$ups_pickup_options = array('01' => 'Daily Pickup','03' => 'Customer Counter',
   '06' => 'One time Pickup','07' => 'On Call Air','19' => 'Letter Center',
   '20' => 'Air Service Center');

global $ups_package_codes;
$ups_package_codes = array('01' => 'UPS Letter','02' => 'Customer Supplied Package',
   '03' => 'Tube','04' => 'PAK','21' => 'UPS Express Box','24' => 'UPS 25KG Box',
   '25' => 'UPS 10KG Box','30' => 'Pallet','2a' => 'Small Express Box',
   '2b' => 'Medium Express Box','2c' => 'Large Express Box');

function ups_module_labels(&$module_labels)
{
    $module_labels['ups'] = 'UPS';
}

function ups_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['ups_rates'] = 'UPS Rates';
    $shipping_tabs['ups_labels'] = 'UPS Labels';
}

function ups_shipping_cart_config_section($db,$dialog,$values)
{
    global $ups_options,$ups_pickup_options,$ups_package_codes;

    $dialog->start_subtab_content('ups_rates_content',
                                  $dialog->current_subtab == 'ups_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('ups_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('UPS Shipper Number:','ups_shipper',$values,30);
    $dialog->add_edit_row('UPS User ID:','ups_userid',$values,30);
    $dialog->add_edit_row('UPS Password:','ups_password',$values,30);
    $dialog->add_edit_row('Access Key:','ups_license',$values,30);
    $dialog->add_edit_row('UPS Hostname:','ups_hostname',$values,30);
    add_handling_field($dialog,'ups_handling',$values);
    if (empty($values['ups_max_weight']))
       $values['ups_max_weight'] = UPS_MAX_WEIGHT;
    $dialog->add_edit_row('Max Package Weight:','ups_max_weight',$values,
                          10,null,' (Lbs)');
    $ups_country = get_row_value($values,'ups_country');
    if (empty($ups_country)) $ups_country = 1;
    $dialog->start_row('Default Origin Country:','middle');
    $dialog->start_choicelist('ups_country');
    load_country_list($ups_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Default Origin State:','middle');
    $dialog->start_choicelist('ups_origin_state');
    $ups_origin_state = get_row_value($values,'ups_origin_state');
    $dialog->add_list_item('','',! $ups_origin_state);
    load_state_list($ups_origin_state,false);
    $dialog->end_listbox();
    $dialog->end_row();
    $dialog->add_edit_row('Default Origin Zip/Postal Code:','ups_origin',
                          $values,10);
    $dialog->add_edit_row('Default Weight:','ups_weight',$values,10,null,
                          ' (Lbs)');
    $dialog->start_row('Package Length:','middle');
    $ups_length = get_row_value($values,'ups_length');
    if (! $ups_length) $ups_length = 10;
    $dialog->add_input_field('ups_length',$ups_length,1);
    $dialog->add_inner_prompt('Width:');
    $ups_width = get_row_value($values,'ups_width');
    if (! $ups_width) $ups_width = 10;
    $dialog->add_input_field('ups_width',$ups_width,1);
    $dialog->add_inner_prompt('Height:');
    $ups_height = get_row_value($values,'ups_height');
    if (! $ups_height) $ups_height = 10;
    $dialog->add_input_field('ups_height',$ups_height,1);
    if ($ups_country == 1) $dialog->write(' (Inches)');
    else $dialog->write(' (CM)');
    $dialog->end_row();
    $ups_pickup_type = get_row_value($values,'ups_pickup');
    $dialog->start_row('Pickup Type:','middle');
    $dialog->start_choicelist('ups_pickup');
    $dialog->add_list_item('','',((! isset($ups_pickup_type)) ||
                                  ($ups_pickup_type == '')));
    foreach ($ups_pickup_options as $id => $label)
       $dialog->add_list_item($id,$label,($id == $ups_pickup_type));
    $dialog->end_choicelist();
    $dialog->end_row();
    $ups_rate = get_row_value($values,'ups_rate');
    if (! $ups_rate) $ups_rate = 'list';
    $dialog->start_row('Rate Option:','middle');
    $dialog->add_radio_field('ups_rate','list','List Rates',$ups_rate == 'list');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('ups_rate','negotiated','Negotiated Rates',
                             $ups_rate == 'negotiated');
    $dialog->end_row();

    $ups_services = get_row_value($values,'ups_services');
    $ups_service_default = get_row_value($values,'ups_service_default');
    $dialog->start_row('Available Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('ups_service_default','-1','',
                             $ups_service_default == -1);
    $dialog->write("    </td></tr>\n");
    $index = 0;
    foreach ($ups_options as $id => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('ups_services_'.$index,'',
                                   $ups_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('ups_service_default',$id,'',
                                $id == $ups_service_default,null);
       $dialog->write("    </td></tr>\n");
       $index++;
    }
    $dialog->end_table();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('ups_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('ups_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('UPS Account Number','ups_label_account',$values,20);
    $dialog->add_edit_row('Shipper Company Name:','ups_label_company',$values,40);
    $dialog->add_edit_row('Shipper Attention:','ups_label_attn',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','ups_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','ups_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','ups_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','ups_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','ups_label_zip',$values,20);
    $ups_label_country = get_row_value($values,'ups_label_country');
    if (empty($ups_label_country)) $ups_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('ups_label_country');
    load_country_list($ups_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Phone Number:','ups_label_phone',$values,40);
    $ups_label_package_code = get_row_value($values,'ups_label_package_code');
    $dialog->start_row('UPS Package Code:','middle');
    $dialog->start_choicelist('ups_label_package_code');
    $dialog->add_list_item('','',! $ups_label_package_code);
    foreach ($ups_package_codes as $id => $label)
       $dialog->add_list_item($id,$label,$id == $ups_label_package_code);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Insure Shipment:','middle');
    $dialog->add_checkbox_field('ups_label_insurance','',$values);
    $dialog->end_row();
    $ups_label_type = get_row_value($values,'ups_label_type');
    $dialog->start_row('Label Type:','middle');
    $dialog->add_radio_field('ups_label_type','plainpaper','Plain Paper',
                             $ups_label_type != '4x6');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('ups_label_type','4x6','4x6 Labels',
                             $ups_label_type == '4x6');
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function ups_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('ups_shipper','ups_userid','ups_password','ups_license',
       'ups_hostname','ups_handling','ups_country','ups_origin_state',
       'ups_origin','ups_weight','ups_length','ups_width','ups_height',
       'ups_pickup','ups_rate','ups_services','ups_service_default',
       'ups_label_account','ups_label_company','ups_label_attn',
       'ups_label_address1','ups_label_address2','ups_label_city',
       'ups_label_state','ups_label_zip','ups_label_country',
       'ups_label_phone','ups_label_package_code','ups_label_insurance',
       'ups_label_type','ups_max_weight');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function ups_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    global $ups_options;

    if ($field_name == 'ups_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($ups_options);  $loop++)
          if (get_form_field('ups_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'ups_label_insurance') {
       if (get_form_field('ups_label_insurance') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'ups_handling')
       $new_field_value = parse_handling_field('ups_handling');
    else return false;
    return true;
}

function process_ups_error(&$cart,$error)
{
    global $ignore_shipping_errors;

    if (! empty($ignore_shipping_errors))
       log_error('UPS Shipping Error: '.$error);
    else {
       $cart->errors['shippingerror'] = true;   $cart->error = $error;
    }
}

function build_access_request()
{
    $ups_userid = get_cart_config_value('ups_userid');
    $ups_password = get_cart_config_value('ups_password');
    $ups_license = get_cart_config_value('ups_license');

    $post_string = "<?xml version='1.0'?>";
    $post_string .= "<AccessRequest xml:lang='en-US'>";
    $post_string .= '<AccessLicenseNumber>'.$ups_license.'</AccessLicenseNumber>';
    $post_string .= '<UserId>'.$ups_userid.'</UserId>';
    $post_string .= '<Password>'.$ups_password.'</Password>';
    $post_string .= '</AccessRequest>';
    return $post_string;
}

function call_ups($obj,$path,$post_string,&$error)
{
    $obj->log_shipping('UPS Sent: '.$post_string);
   
    $host = get_cart_config_value('ups_hostname');
    require_once '../engine/http.php';
    $url = 'https://'.$host.$path;
    $http = new HTTP($url);
    $http->set_content_type('text/xml');
    $response_string = $http->call($post_string);
    if (! $response_string) {
       $error = $http->error.' ('.$http->status.')';   return null;
    }
    $response_string = str_replace("\n",'',$response_string);
    $response_string = str_replace("\r",'',$response_string);
    $obj->log_shipping('UPS Response: '.$response_string);
    if (($http->status != 100) && ($http->status != 200)) {
       $error = $response_string.' ('.$http->status.')';   return null;
    }
    return $response_string;
}

function get_ups_rate(&$cart,$ups_options,&$ups_rates,$from_country,
   $from_state,$from_residential,$to_country,$to_state,$to_zip,
   $to_residential,&$ups_currency,$ups_rate_option,$ups_shipper,$package)
{
    $ups_pickup_type = get_cart_config_value('ups_pickup');
    if ($from_country == 'US') {
       $size_measure = 'IN';   $weight_measure = 'LBS';
    }
    else {
       $size_measure = 'CM';   $weight_measure = 'KGS';
       $package['weight'] = $package['weight'] / 2.2;
    }

    $post_string = build_access_request();
    $post_string .= "<?xml version='1.0'?>";
    $post_string .= "<RatingServiceSelectionRequest xml:lang='en-US'>";
    $post_string .= '<Request>';
    $post_string .= '<TransactionReference>';
    $post_string .= '<CustomerContext>Rating and Service</CustomerContext>';
    $post_string .= '<XpciVersion>1.0001</XpciVersion>';
    $post_string .= '</TransactionReference>';
    $post_string .= '<RequestAction>Rate</RequestAction>';
    $post_string .= '<RequestOption>Shop</RequestOption>';
    $post_string .= '</Request>';
    $post_string .= '<PickupType><Code>'.$ups_pickup_type.'</Code></PickupType>';
    $post_string .= '<Shipment>';
    $post_string .= '<Shipper>';
    if ($ups_shipper)
       $post_string .= '<ShipperNumber>'.$ups_shipper.'</ShipperNumber>';
    $post_string .= '<Address>';
    if ($from_state)
       $post_string .= '<StateProvinceCode>'.$from_state.'</StateProvinceCode>';
    $post_string .= '<PostalCode>'.$package['from_zip'].'</PostalCode>';
    $post_string .= '<CountryCode>'.$from_country.'</CountryCode>';
    if ($from_residential) $post_string .= '<ResidentialAddress/>';
    $post_string .= '</Address></Shipper>';
    $post_string .= '<ShipTo><Address>';
    if ($to_state)
       $post_string .= '<StateProvinceCode>'.$to_state.'</StateProvinceCode>';
    $post_string .= '<PostalCode>'.$to_zip.'</PostalCode>';
    $post_string .= '<CountryCode>'.$to_country.'</CountryCode>';
    if ($to_residential) $post_string .= '<ResidentialAddress/>';
    $post_string .= '</Address></ShipTo>';
    $post_string .= '<ShipFrom><Address>';
    if ($from_state)
       $post_string .= '<StateProvinceCode>'.$from_state.'</StateProvinceCode>';
    $post_string .= '<PostalCode>'.$package['from_zip'].'</PostalCode>';
    $post_string .= '<CountryCode>'.$from_country.'</CountryCode>';
    if ($from_residential) $post_string .= '<ResidentialAddress/>';
    $post_string .= '</Address></ShipFrom>';
    $post_string .= '<Service><Code>11</Code></Service>';
    $post_string .= '<Package>';
    $post_string .= '<PackagingType><Code>02</Code></PackagingType>';
    $post_string .= '<Dimensions><UnitOfMeasurement><Code>'.$size_measure .
                    '</Code></UnitOfMeasurement>';
    $post_string .= '<Length>'.$package['length'].'</Length><Width>'.$package['width'] .
                    '</Width><Height>'.$package['height'].'</Height></Dimensions>';
    $post_string .= '<PackageWeight><UnitOfMeasurement><Code>'.$weight_measure .
                    '</Code></UnitOfMeasurement>';
    $post_string .= '<Weight>'.ceil($package['weight']).'</Weight></PackageWeight>';
    $post_string .= '</Package>';
    if ($ups_rate_option == 'negotiated')
       $post_string .= '<RateInformation><NegotiatedRatesIndicator/></RateInformation>';
    $post_string .= '</Shipment>';
    $post_string .= '</RatingServiceSelectionRequest>';

    $response_string = call_ups($cart,'/ups.app/xml/Rate',$post_string,$error);
    if (! $response_string) {
       process_ups_error($cart,$error);   return false;
    }

    $start_pos = strpos($response_string,'<Error>');
    if ($start_pos !== false) {
       $start_pos = strpos($response_string,'<ErrorDescription>',$start_pos);
       $start_pos += 18;
       $end_pos = strpos($response_string,'</ErrorDescription>',$start_pos);
       process_ups_error($cart,substr($response_string,$start_pos,
                                      $end_pos - $start_pos));
       return false;
    }

    $services_array = explode('</RatedShipment>',$response_string);
    for ($loop = 0;  $loop < count($services_array) - 1;  $loop++) {
       $start_pos = strpos($services_array[$loop],'<Service><Code>');
       $end_pos = strpos($services_array[$loop],'</Code></Service>');
       $ups_code = substr($services_array[$loop],$start_pos + 15,$end_pos - $start_pos - 15);
       if ($ups_currency == '') {
          $start_pos = strpos($services_array[$loop],'<CurrencyCode>');
          $end_pos = strpos($services_array[$loop],'</CurrencyCode>');
          $ups_currency = substr($services_array[$loop],$start_pos + 14,$end_pos - $start_pos - 14);
       }
       if ($ups_rate_option == 'negotiated') {
          $start_pos = strpos($services_array[$loop],'<NegotiatedRates>');
          $end_pos = strpos($services_array[$loop],'</NegotiatedRates>');
          $rate_block = substr($services_array[$loop],$start_pos,$end_pos - $start_pos);
          $start_pos = strpos($rate_block,'<MonetaryValue>');
          $end_pos = strpos($rate_block,'</MonetaryValue>');
          $ups_rate = substr($rate_block,$start_pos + 15,$end_pos - $start_pos - 15);
       }
       else {
          $start_pos = strpos($services_array[$loop],'<MonetaryValue>');
          $end_pos = strpos($services_array[$loop],'</MonetaryValue>');
          $ups_rate = substr($services_array[$loop],$start_pos + 15,$end_pos - $start_pos - 15);
       }
       $index = 0;
       foreach ($ups_options as $id => $label) {
          if ($ups_code == $id) $ups_rates[$index] = ($ups_rate * $package['qty']);
          $index++;
       }
   }

   return true;
}

function init_ups_rates($num_options)
{
    $rates = array();
    for ($loop = 0;  $loop < $num_options;  $loop++) $rates[$loop] = 0;
    return $rates;
}

function update_ups_rates(&$ups_rates,$new_rates,$num_options)
{
    static $first_time;

    if ($num_options == 0) {
       $first_time = true;   return;
    }
    for ($loop = 0;  $loop < $num_options;  $loop++) {
       if ($new_rates[$loop] == 0) $ups_rates[$loop] = 0;
       else if ((! $first_time) && ($ups_rates[$loop] == 0)) continue;
       else $ups_rates[$loop] += $new_rates[$loop];
    }
    $first_time = false;
}

function ups_load_shipping_options(&$cart,$customer)
{
    global $ups_options,$us_territory_states;

    require_once '../cartengine/currency.php';

    $ups_shipper = get_cart_config_value('ups_shipper');
    if (! $ups_shipper) return;
    $ups_rate_option = get_cart_config_value('ups_rate');
    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];
    $ups_services = get_cart_config_value('ups_services');
    $ups_service_default = get_cart_config_value('ups_service_default');
    $shipping_country_info = get_country_info($customer->shipping_country,
                                              $cart->db);
    $ups_handling = $cart->get_handling($shipping_country_info,$customer,
                                        'ups_handling');
    $shipping_country = $shipping_country_info['code'];
    $ups_country = get_cart_config_value('ups_country');
    $from_state = get_cart_config_value('ups_origin_state');
    if (($ups_country == 1) && in_array($from_state,$us_territory_states))
       $ups_country = $from_state;
    else {
       $ups_country_info = get_country_info($ups_country,$cart->db);
       $ups_country = $ups_country_info['code'];
    }
    $from_residential = false;
    $default_origin = get_cart_config_value('ups_origin');
    $default_weight = get_cart_config_value('ups_weight');
    $to_zip = $customer->get('ship_zipcode');
    if ($customer->shipping_country == 1) {
       $to_state = $customer->get('ship_state');
       if (in_array($to_state,$us_territory_states))
          $shipping_country = $to_state;
    }
    else $to_state = $customer->get('ship_province');
    $address_type = get_address_type($customer);
    if ($address_type == 2) $to_residential = true;
    else $to_residential = false;
    $num_options = count($ups_options);
    $ups_rates = init_ups_rates($num_options);
    update_ups_rates($ups_rates,null,0);

    $ups_currency = '';
    if (($customer->shipping_country == 1) && (! $to_zip)) $packages = null;
    else if (function_exists('get_custom_shipping_packages'))
       $packages = get_custom_shipping_packages($cart);
    else {
       $max_weight = get_cart_config_value('ups_max_weight');
       if (empty($max_weight)) $max_weight = UPS_MAX_WEIGHT;
       $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                             $customer);
       $packages = array();
       $ups_length = get_cart_config_value('ups_length');
       if (! $ups_length) $ups_length = 10;
       $ups_width = get_cart_config_value('ups_width');
       if (! $ups_width) $ups_width = 10;
       $ups_height = get_cart_config_value('ups_height');
       if (! $ups_height) $ups_height = 10;
       foreach ($origin_info as $origin_zip => $weight) {
          if ($weight > $max_weight) {
             $num_packages = intval($weight / $max_weight);
             $packages[] = array('from_zip' => $origin_zip,
                                 'qty' => $num_packages,
                                 'weight' => $max_weight,
                                 'length' => $ups_length,
                                 'width' => $ups_width,
                                 'height' => $ups_height);
             $remaining_weight = $weight - ($num_packages * $max_weight);
             if ($remaining_weight)
                $packages[] = array('from_zip' => $origin_zip,
                                    'qty' => 1,
                                    'weight' => floatval($remaining_weight),
                                   'length' => $ups_length,
                                    'width' => $ups_width,
                                    'height' => $ups_height);
          }
          else $packages[] = array('from_zip' => $origin_zip,
                                   'qty' => 1,
                                   'weight' => floatval($weight),
                                   'length' => $ups_length,
                                   'width' => $ups_width,
                                   'height' => $ups_height);
       }
    }

    if ($packages) {
       foreach ($packages as $package) {
          $new_rates = init_ups_rates($num_options);
          get_ups_rate($cart,$ups_options,$new_rates,$ups_country,
                      $from_state,$from_residential,$shipping_country,
                      $to_state,$to_zip,$to_residential,$ups_currency,
                      $ups_rate_option,$ups_shipper,$package);
          update_ups_rates($ups_rates,$new_rates,$num_options);
       }
    }
    if ($ups_currency && (($currency != 'USD') || ($ups_currency != 'USD')))
       $exchange_rate = get_exchange_rate($ups_currency,$currency);
    else $exchange_rate = 0.0;

    $shipping_method = get_form_field('shipping_method');
    if ($shipping_method) {
       $shipping_method_info = explode('|',$shipping_method);
       if (count($shipping_method_info) == 2)
          $shipping_method = $shipping_method_info[0];
       else if (isset($shipping_method_info[1]))
          $shipping_method = $shipping_method_info[1];
    }
    else if (isset($cart->info['shipping_method']))
       $shipping_method = $cart->info['shipping_method'];
    else if (function_exists('get_custom_shipping_default'))
       $shipping_method = get_custom_shipping_default($cart,$ups_service_default);
    else $shipping_method = $ups_service_default;
    $index = 0;   $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($ups_options as $id => $label) {
       if (! ($ups_services & (1 << $index))) {   $index++;   continue;   }
       if ($ups_rates[$index] == 0) {   $index++;   continue;   }
       $handling = $ups_handling;
       if ($exchange_rate != 0.0)
          $rate = floatval($ups_rates[$index]) * $exchange_rate;
       else $rate = $ups_rates[$index];
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($rate * ($handling/100)),2);
       }
       $rate += $handling;
       $cart->add_shipping_option('ups',$id,$rate,'UPS '.$label,
                                  $shipping_method == $id);
       $index++;
    }
}

function ups_process_shipping(&$order,$shipping_method)
{
    global $ups_options;

    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    if (! isset($shipping_info[1])) $order->set('shipping_method','');
    else if (isset($ups_options[$shipping_info[1]]))
       $order->set('shipping_method',$shipping_info[1].'|'.$ups_options[$shipping_info[1]]);
    else $order->set('shipping_method','');
}

function ups_display_shipping_info($dialog,$order)
{
    $shipping_method = get_row_value($order->info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $dialog->add_text_row('Shipping Carrier:','UPS');
    if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
    else $shipping_method = $shipping_info[0];
    if (strpos($shipping_method,'UPS') === false)
       $shipping_method = 'UPS '.$shipping_method;
    $dialog->add_text_row('Shipping Method:',$shipping_method,'top');
}

function ups_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'UPS';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       $shipping_info = explode('|',$shipping_method);
       if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
       else $shipping_method = $shipping_info[0];
       if (strpos($shipping_method,'UPS') === false)
          $shipping_method = 'UPS '.$shipping_method;
       return $shipping_method;
    }
    return null;
}

function build_address_xml($address,$db)
{
    global $us_territory_states;

    $post_string = '';
    if (isset($address['company']) && ($address['company'] != ''))
       $post_string .= '<CompanyName>'.encode_xml_data($address['company']).'</CompanyName>';
    if (isset($address['shipto']) && ($address['shipto'] != ''))
       $post_string .= '<AttentionName>'.encode_xml_data($address['shipto']).'</AttentionName>';
    $post_string .= '<Address>';
    if (isset($address['address1']) && ($address['address1'] != ''))
       $post_string .= '<AddressLine1>'.encode_xml_data($address['address1']).'</AddressLine1>';
    if (isset($address['address2']) && ($address['address2'] != ''))
       $post_string .= '<AddressLine2>'.encode_xml_data($address['address2']).'</AddressLine2>';
    if (isset($address['city']) && ($address['city'] != ''))
       $post_string .= '<City>'.encode_xml_data($address['city']).'</City>';
    if (isset($address['state']) && ($address['state'] != '')) {
       $state = $address['state'];
       $post_string .= '<StateProvinceCode>'.encode_xml_data($address['state']).'</StateProvinceCode>';
    }
    else $state = '';
    if (isset($address['zipcode']) && ($address['zipcode'] != ''))
       $post_string .= '<PostalCode>'.encode_xml_data($address['zipcode']).'</PostalCode>';
    if (isset($address['country']) && ($address['country'] != '')) {
       if (($address['country'] == 1) && in_array($state,$us_territory_states))
          $country_code = $state;
       else {
          $country_info = get_country_info($address['country'],$db);
          $country_code = $country_info['code'];
       }
       $post_string .= '<CountryCode>'.encode_xml_data($country_code).'</CountryCode>';
    }
    if (isset($address['address_type']) && ($address['address_type'] == 2))
       $post_string .= '<ResidentialAddress></ResidentialAddress>';
    $post_string .= '</Address>';
    if (isset($address['phone']) && ($address['phone'] != ''))
       $post_string .= '<PhoneNumber>'.encode_xml_data($address['phone']).'</PhoneNumber>';
    if (isset($address['fax']) && ($address['fax'] != ''))
       $post_string .= '<FaxNumber>'.encode_xml_data($address['fax']).'</FaxNumber>';
    if (isset($address['email']) && ($address['email'] != ''))
       $post_string .= '<EMailAddress>'.encode_xml_data($address['email']).'</EMailAddress>';
    return $post_string;
}

function parse_ups_response($response_string,$tag)
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

function ups_verify_shipping_label($db)
{
    $ups_label_account = get_cart_config_value('ups_label_account',$db);
    if (! $ups_label_account) return false;
    return true;
}

function ups_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.gif';
}

function ups_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                     $label_filename)
{
    load_cart_config_values($order->db);
    $ups_label_account = get_cart_config_value('ups_label_account');
    $ups_label_company = get_cart_config_value('ups_label_company');
    $ups_label_package_code = get_cart_config_value('ups_label_package_code');
    $ups_label_insurance = get_cart_config_value('ups_label_insurance');
    $shipper = array();
    $shipper['shipto'] = get_cart_config_value('ups_label_attn');
    $shipper['address1'] = get_cart_config_value('ups_label_address1');
    $shipper['address2'] = get_cart_config_value('ups_label_address2');
    $shipper['city'] = get_cart_config_value('ups_label_city');
    $shipper['state'] = get_cart_config_value('ups_label_state');
    $shipper['zipcode'] = get_cart_config_value('ups_label_zip');
    $shipper['country'] = get_cart_config_value('ups_label_country');
    $shipper['phone'] = get_cart_config_value('ups_label_phone');
    $default_weight = get_cart_config_value('ups_weight');
    $weight = load_order_weight($order,$default_weight);
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $ups_label_service_code = $shipping_info[0];

    $post_string = build_access_request();
    $post_string .= "<?xml version='1.0'?>";
    $post_string .= "<ShipmentConfirmRequest xml:lang='en-US'>";
    $post_string .= '<Request>';
    $post_string .= '<TransactionReference>';
    $post_string .= '<CustomerContext>ShipConfirm</CustomerContext>';
    $post_string .= '<XpciVersion>1.0001</XpciVersion>';
    $post_string .= '</TransactionReference>';
    $post_string .= '<RequestAction>ShipConfirm</RequestAction>';
    $post_string .= '<RequestOption>nonvalidate</RequestOption>';
    $post_string .= '</Request>';
    $post_string .= '<Shipment>';
    $post_string .= '<Shipper><Name>'.encode_xml_data($ups_label_company).'</Name>';
    $post_string .= '<ShipperNumber>'.encode_xml_data($ups_label_account).'</ShipperNumber>';
    $post_string .= build_address_xml($shipper,$order->db).'</Shipper>';

    $customer_address = $order->shipping;
    if ((! isset($customer_address['shipto'])) || ($customer_address['shipto'] == '')) {
       $shipto = $order->info['fname'];
       if (isset($order->info['mname']) && ($order->info['mname'] != ''))
          $shipto .= ' '.$order->info['mname'];
       $shipto .= ' '.$order->info['lname'];
       $customer_address['shipto'] = $shipto;
    }
    if ((! isset($customer_address['company'])) || ($customer_address['company'] == '')) {
       $customer_address['company'] = $customer_address['shipto'];
       unset($customer_address['shipto']);
    }
    $customer_address['email'] = $order->info['email'];
    $customer_address['phone'] = $order->billing['phone'];
    $customer_address['fax'] = $order->billing['fax'];
    $customer_address['address_type'] = get_address_type($order);
    if ($outbound) {
       $post_string .= '<ShipTo>'.build_address_xml($customer_address,$order->db) .
                       '</ShipTo>';
       $from_country = $shipper['country'];
    }
    else {
       $post_string .= '<ShipFrom>'.build_address_xml($customer_address,$order->db) .
                       '</ShipFrom>';
       $shipper['company'] = $ups_label_company;
       $post_string .= '<ShipTo>'.build_address_xml($shipper,$order->db) .
                       '</ShipTo>';
       $from_country = $customer_address['country'];
    }
    if ($from_country == 1) $weight_measure = 'LBS';
    else {
       $weight_measure = 'KGS';   $weight = $weight / 2.2;
    }

    $post_string .= '<PaymentInformation><Prepaid><BillShipper><AccountNumber>' .
                    $ups_label_account.'</AccountNumber></BillShipper></Prepaid>' .
                    '</PaymentInformation>';
    $post_string .= '<Service><Code>'.$ups_label_service_code.'</Code></Service>';
    $post_string .= '<Package>';
    $post_string .= '<PackagingType><Code>'.$ups_label_package_code .
                    '</Code></PackagingType>';
    $post_string .= '<PackageWeight><UnitOfMeasurement><Code>'.$weight_measure .
                    '</Code></UnitOfMeasurement>';
    $post_string .= '<Weight>'.ceil($weight).'</Weight></PackageWeight>';
    if (($ups_label_insurance == 1) && $order->info['total'])
       $post_string .= '<PackageServiceOptions><InsuredValue><MonetaryValue>'.
                       $order->info['total'].'</MonetaryValue></InsuredValue>' .
                       '</PackageServiceOptions>';
    $post_string .= '</Package>';
    $post_string .= '</Shipment>';
    $post_string .= '<LabelSpecification><LabelPrintMethod><Code>GIF</Code>' .
                    '</LabelPrintMethod><HTTPUserAgent>Mozilla/4.5' .
                    '</HTTPUserAgent><LabelImageFormat><Code>GIF</Code>' .
                    '</LabelImageFormat></LabelSpecification>';
    $post_string .= '</ShipmentConfirmRequest>';

    $response_string = call_ups($order,'/ups.app/xml/ShipConfirm',$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    $response_status = parse_ups_response($response_string,'ResponseStatusCode');
    if ($response_status == null) {
       $order->error = 'Unable to find ResponseStatusCode';   return false;
    }
    if ($response_status != 1) {
       $error_code = parse_ups_response($response_string,'ErrorCode');
       if ($error_code == null) {
          $order->error = 'Unable to find ErrorCode';   return false;
       }
       $error_description = parse_ups_response($response_string,'ErrorDescription');
       if ($response_status == null) {
          $order->error = 'Unable to find ErrorDescription';   return false;
       }
       $order->error = $error_description.' ('.$error_code.')';   return false;
    }
    $digest = parse_ups_response($response_string,'ShipmentDigest');
    if ($digest == null) {
       $order->error = 'Unable to find ShipmentDigest';   return false;
    }

    $post_string = build_access_request();
    $post_string .= "<?xml version='1.0'?>";
    $post_string .= "<ShipmentAcceptRequest xml:lang='en-US'>";
    $post_string .= '<Request>';
    $post_string .= '<TransactionReference>';
    $post_string .= '<CustomerContext>ShipAccept</CustomerContext>';
    $post_string .= '<XpciVersion>1.0001</XpciVersion>';
    $post_string .= '</TransactionReference>';
    $post_string .= '<RequestAction>ShipAccept</RequestAction>';
    $post_string .= '</Request>';
    $post_string .= '<ShipmentDigest>'.encode_xml_data($digest).'</ShipmentDigest>';
    $post_string .= '</ShipmentAcceptRequest>';

    $response_string = call_ups($order,'/ups.app/xml/ShipAccept',$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    $response_status = parse_ups_response($response_string,'ResponseStatusCode');
    if ($response_status == null) {
       $order->error = 'Unable to find ResponseStatusCode';   return false;
    }
    if ($response_status != 1) {
       $error_code = parse_ups_response($response_string,'ErrorCode');
       if ($error_code == null) {
          $order->error = 'Unable to find ErrorCode';   return false;
       }
       $error_description = parse_ups_response($response_string,'ErrorDescription');
       if ($response_status == null) {
          $order->error = 'Unable to find ErrorDescription';   return false;
       }
       $order->error = $error_description.' ('.$error_code.')';   return false;
    }

    $tracking_num = parse_ups_response($response_string,'TrackingNumber');
    if ($tracking_num == null) {
       $order->error = 'Unable to find TrackingNumber';   return false;
    }
    $shipment_info['tracking'] = $tracking_num;

    $graphic_image = parse_ups_response($response_string,'GraphicImage');
    if ($graphic_image == null) {
       $order->error = 'Unable to find GraphicImage';   return false;
    }
    $image = base64_decode($graphic_image);
    $image_file = fopen($label_filename,'wb');
    if (! $image_file) {
       $order->error = 'Unable to create label image file';   return false;
    }
    fwrite($image_file,$image);
    fclose($image_file);

    $html_image = parse_ups_response($response_string,'HTMLImage');
    if ($html_image) $order->html_label = base64_decode($html_image);

    $high_value_response = parse_ups_response($response_string,'ControlLogReceipt');
    if ($high_value_response != null) {
       global $file_dir;

       $graphic_image = parse_ups_response($high_value_response,'GraphicImage');
       if ($graphic_image == null) {
          $order->error = 'Unable to find High Value GraphicImage';
          return false;
       }
       $high_value_data = base64_decode($graphic_image);
       $high_value_filename = $file_dir.'/labels/'.$order->id.'-highvalue.';
       $high_value_format = parse_ups_response($high_value_response,'Code');
       if ($high_value_format == 'HTML') $high_value_filename .= 'html';
       else $high_value_filename .= 'gif';
       $high_value_file = fopen($high_value_filename,'wb');
       if (! $high_value_file) {
          $order->error = 'Unable to create high value report file';
          return false;
       }
       fwrite($high_value_file,$high_value_data);
       fclose($high_value_file);
    }

    return true;
}

function ups_print_shipping_label($db,$order,$label_filename)
{
    global $order_label,$company_name;

    ini_set('memory_limit','256M');
    $label_type = get_cart_config_value('ups_label_type',$db);
    if ($label_type != '4x6') $label_type = 'plainpaper';
    require_once('../engine/tcpdf/tcpdf.php');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    $pdf = new TCPDF('P','pt','LETTER',true,'UTF-8',false);
    $pdf->SetCreator($company_name.' Shopping Cart');
    $pdf->SetAuthor($company_name);
    $title = 'Shipping Label for '.$order_label.' #' .
             $order->info['order_number'];
    $pdf->SetTitle($title);
    $pdf->SetSubject($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0,0);
    $pdf->AddPage();
    if ($label_type == '4x6') {
       $pdf->StartTransform();
       $pdf->Rotate(-90);
       $pdf->Image($label_filename,10,-315,537,307,'','','',false,300,'',
                   false,false,0,true);
       $pdf->StopTransform();
    }
    else $pdf->Image($label_filename,50,34,537,307,'','','',false,300,'',
                     false,false,0,true);
    $pdf->Output('shipping_label_'.$order->id.'.pdf','I');
}

function ups_cancel_shipment(&$order,$shipment_info)
{
    $post_string = build_access_request();
    $post_string .= "<?xml version='1.0'?>";
    $post_string .= "<VoidShipmentRequest xml:lang='en-US'>";
    $post_string .= '<Request>';
    $post_string .= '<TransactionReference>';
    $post_string .= '<CustomerContext>VoidShipment</CustomerContext>';
    $post_string .= '<XpciVersion>1.0001</XpciVersion>';
    $post_string .= '</TransactionReference>';
    $post_string .= '<RequestAction>Void</RequestAction>';
    $post_string .= '</Request>';
    $post_string .= '<ShipmentIdentificationNumber>' .
       encode_xml_data($shipment_info['tracking']) .
       '</ShipmentIdentificationNumber>';
    $post_string .= '</VoidShipmentRequest>';

    $response_string = call_ups($order,'/ups.app/xml/Void',$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    $response_status = parse_ups_response($response_string,'ResponseStatusCode');
    if ($response_status == null) {
       $order->error = 'Unable to find ResponseStatusCode';   return false;
    }
    if ($response_status != 1) {
       $error_code = parse_ups_response($response_string,'ErrorCode');
       if ($error_code == null) {
          $order->error = 'Unable to find ErrorCode';   return false;
       }
       $error_description = parse_ups_response($response_string,'ErrorDescription');
       if ($response_status == null) {
          $order->error = 'Unable to find ErrorDescription';   return false;
       }
       $order->error = $error_description.' ('.$error_code.')';   return false;
    }

    return true;
}

function ups_get_tracking_url($tracking)
{
    $url = 'http://wwwapps.ups.com/WebTracking/track?track=yes&' .
           'trackNums='.$tracking;
    return $url;
}

function ups_all_methods()
{
    global $ups_options;

    $methods = array();
    foreach ($ups_options as $id => $label) $methods[$id] = $label;
    return $methods;
}

function ups_available_methods()
{
    global $ups_options;

    $ups_services = get_cart_config_value('ups_services');
    $methods = array();   $index = 0;
    foreach ($ups_options as $id => $label) {
       if (! ($ups_services & (1 << $index))) {
          $index++;   continue;
       }
       $methods[$id] = $label;
       $index++;
    }
    return $methods;
}

function ups_default_weight($db)
{
    return get_cart_config_value('ups_weight',$db);
}

if ($ups_certification) {
   class UPSOrder {

   function log_shipping($msg)
   {
      if (substr($msg,0,10) == 'UPS Sent: ') {
         if (isset($this->confirm_sent)) $this->accept_sent = substr($msg,6);
         else $this->confirm_sent = substr($msg,6);
      }
      else if (substr($msg,0,14) == 'UPS Response: ') {
         if (isset($this->confirm_response))
            $this->accept_response = substr($msg,10);
         else $this->confirm_response = substr($msg,10);
      }
      else $this->log = $msg;
   }

   };

   function generate_cert_label($order,$prefix,$amount)
   {
       global $file_dir;

       $order->info['total'] = $amount;
       $order->id = $prefix;
       $label_filename = $file_dir.'/labels/'.$prefix.'.gif';
       if (isset($confirm_sent)) unset($confirm_sent);
       if (isset($confirm_response)) unset($confirm_response);
       if (isset($accept_sent)) unset($accept_sent);
       if (isset($accept_response)) unset($accept_response);
       if (! ups_generate_shipping_label($order,$order->info,true,
                                         $label_filename)) {
          print "<h1 align=\"center\">Unable to generate shipping label: " .
                $order->error."</h1>\n";
          if (isset($order->confirm_sent))
             print "Confirm Sent:<br>\n".encode_xml_data($order->confirm_sent) .
                   "<br>\n";
          if (isset($order->accept_sent))
             print "Accept Sent:<br>\n".encode_xml_data($order->accept_sent) .
                   "<br>\n";
          exit;
       }

       if ($order->html_label) {
          $html_filename = $file_dir.'/labels/'.$prefix.'-scaling.html';
          $html_file = fopen($html_filename,'wt');
          if ($html_file) {
             fwrite($html_file,$order->html_label);
             fclose($html_file);
          }
       }

       $xml_filename = $file_dir.'/labels/'.$prefix.'-confirm-sent.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->confirm_sent);
       fclose($xml_file);

       $xml_filename = $file_dir.'/labels/'.$prefix.'-confirm-response.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->confirm_response);
       fclose($xml_file);

       $xml_filename = $file_dir.'/labels/'.$prefix.'-accept-sent.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->accept_sent);
       fclose($xml_file);

       $xml_filename = $file_dir.'/labels/'.$prefix.'-accept-response.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->accept_response);
       fclose($xml_file);
   }

   function generate_void($order,$prefix,$tracking)
   {
       global $file_dir;

       if (isset($confirm_sent)) unset($confirm_sent);
       if (isset($confirm_response)) unset($confirm_response);
       $shipment_info = array('tracking' => $tracking);
       ups_cancel_shipment($order,$shipment_info);

       $xml_filename = $file_dir.'/labels/'.$prefix.'-sent.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->confirm_sent);
       fclose($xml_file);

       $xml_filename = $file_dir.'/labels/'.$prefix.'-response.xml';
       $xml_file = fopen($xml_filename,'wt');
       fwrite($xml_file,$order->confirm_response);
       fclose($xml_file);

   }

   function generate_certification()
   {
       global $file_dir;

       $certify_dir = $file_dir.'/certify';
       if (! file_exists($certify_dir)) mkdir($certify_dir);
       $file_dir = $certify_dir;
       if (! file_exists($certify_dir.'/labels'))
          mkdir($certify_dir.'/labels');

       $order = new UPSOrder();
       $order->db = new DB;
       $order->info = array();
       $order->info['shipping_method'] = '03';
       $order->info['email'] = 'customersupport@inroads.us';
       $order->billing = array();
       $order->billing['phone'] = '301-473-9750';
       $order->billing['fax'] = '301-418-6388';
       $order->shipping = array();
       $order->shipping['company'] = 'Inroads, LLC';
       $order->shipping['shipto'] = 'UPS Testing';
       $order->shipping['address1'] = '205-B Broadway Street';
       $order->shipping['city'] = 'Frederick';
       $order->shipping['state'] = 'MD';
       $order->shipping['zipcode'] = '21701';
       $order->shipping['country'] = 1;
       $order->items = null;

       load_cart_config_values($order->db);
       $default_weight = get_cart_config_value('ups_weight');
       if (! $default_weight) {
          print "<h1 align=\"center\">Default Weight is required for certification</h1>\n";
          exit;
       }
       $ups_label_insurance = get_cart_config_value('ups_label_insurance');
       if ($ups_label_insurance != 1) {
          print "<h1 align=\"center\">Insurance is required for certification</h1>\n";
          exit;
       }

       generate_cert_label($order,'test-1',100);
       generate_cert_label($order,'test-2',200);
       generate_cert_label($order,'test-3',300);
       generate_cert_label($order,'test-4',400);
       generate_cert_label($order,'test-5',1000);

       generate_void($order,'void-1','1ZISDE016691676846');
       generate_void($order,'void-2','1Z2220060290602143');
       generate_void($order,'void-3','1Z2220060294314162');
       generate_void($order,'void-4','1Z2220060291994175');

       print "<h1 align=\"center\">Certification Files Generated</h1>\n";
   }

   if (! isset($_COOKIE[$login_cookie])) {
      print "<h1 align=\"center\">You must be logged into the Admin area to use UPS functions</h1>\n";
      exit;
   }
   $cmd = get_form_field('cmd');
   if ($cmd == 'certify') generate_certification();
   else print "<h1 align=\"center\">You must specify a UPS function</h1>\n";
}

?>
