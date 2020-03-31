<?php
/*
                      Inroads Shopping Cart - USPS API Module

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

define ('USPS_MAX_WEIGHT',70);

global $usps_first_class_delivery;
if (! isset($usps_first_class_delivery)) $usps_first_class_delivery = '';

global $usps_options;
$usps_options = array('0' => 'First-Class Mail'.$usps_first_class_delivery,
   '1' => 'Priority Mail (2-3 days)',
   '2' => 'Express Mail Hold for Pickup','3' => 'Express Mail - Next Day',
   '4' => 'Parcel Post','5' => 'Bound Printed Matter','6' => 'Media Mail',
   '12' => 'First-Class Postcard Stamped',
   '13' => 'Express Mail Flat-Rate Envelope','16' => 'Priority Mail Flat-Rate Envelope',
   '17' => 'Priority Mail Medium Flat-Rate Box','22' => 'Priority Mail Large Flat-Rate Box',
   '23' => 'Express Mail Sunday/Holiday','28' => 'Priority Mail Small Flat-Rate Box',
   '29' => 'Priority Mail 2-Day Padded Flat Rate Envelope',
   '42' => 'Priority Mail 2-Day Flat Rate Envelope');

global $usps_label_options;
$usps_label_options = array('0' => 'First Class','1' => 'Priority',
   '2' => 'Express Mail Hold for Pickup','3' => 'Express Mail - Next Day',
   '4' => 'Standard Post','5' => 'Library Mail','6' => 'Media Mail',
   '12' => 'First Class',
   '13' => 'Express Mail Flat-Rate Envelope','16' => 'Priority Mail Flat-Rate Envelope',
   '17' => 'Priority Mail Flat-Rate Box','22' => 'Priority Mail Large Flat-Rate Box',
   '23' => 'Express Mail Sunday/Holiday','28' => 'Priority Mail Small Flat-Rate Box',
   '29' => 'Priority Mail 2-Day Padded Flat Rate Envelope',
   '42' => 'Priority Mail 2-Day Flat Rate Envelope');

global $usps_intl_options;
$usps_intl_options = array('1' => 'Express Mail International','2' => 'Priority Mail International',
   '4' => 'Global Express Guaranteed','5' => 'Global Express Grntd (Doc)',
   '6' => 'Global Express Grntd (Non-Doc Rect)',
   '7' => 'Global Express Grntd (Non-Doc Non-Rect)',
   '8' => 'Priority Mail Flat Rate Envelope',
   '9' => 'Priority Mail Flat Rate Box','10' => 'Express Mail International Flat Rate Envelope',
   '11' => 'Priority Mail Large Flat Rate Box','12' => 'Global Express Guaranteed Envelope',
   '13' => 'First Class Mail International Letters','14' => 'First Class Mail International Flats',
   '15' => 'First Class Mail International Parcels','21' => 'PostCards');

function usps_module_labels(&$module_labels)
{
    $module_labels['usps'] = 'USPS';
}

function usps_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['usps_rates'] = 'USPS Rates';
    $shipping_tabs['usps_labels'] = 'USPS Labels';
}

function usps_shipping_cart_config_section($db,$dialog,$values)
{
    global $usps_options,$usps_label_options,$usps_intl_options;

    $dialog->start_subtab_content('usps_rates_content',
                                  $dialog->current_subtab == 'usps_rates_tab');
    $dialog->start_field_table('fieldtable','fieldtable" style="width:100%;',
                               0,0);
    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");
    $dialog->write('<tr valign="top"><td width="50%">');
    $dialog->start_table(null,null,2);

    $dialog->add_edit_row('USPS User ID:','usps_userid',$values,30);
    $dialog->add_edit_row('USPS Hostname:','usps_hostname',$values,30);
    $dialog->add_edit_row('USPS URL:','usps_url',$values,30);
    add_handling_field($dialog,'usps_handling',$values);
    if (empty($values['usps_max_weight']))
       $values['usps_max_weight'] = USPS_MAX_WEIGHT;
    $dialog->add_edit_row('Max Package Weight:','usps_max_weight',$values,
                          10,null,' (Lbs)');
    $usps_country = get_row_value($values,'usps_country');
    if (empty($usps_country)) $usps_country = 1;
    $dialog->start_row('Default Origin Country:','middle');
    $dialog->start_choicelist('usps_country');
    load_country_list($usps_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Default Origin Zip Code:','usps_origin',$values,10);
    $dialog->add_edit_row('Default Weight:','usps_weight',$values,10,
                          null,' (Lbs)');
    $usps_rate = get_row_value($values,'usps_rate');
    if (! $usps_rate) $usps_rate = 'ALL';
    $dialog->start_row('Rate Option:','middle');
    $dialog->add_radio_field('usps_rate','ALL','Standard Rates',
                             $usps_rate == 'ALL');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('usps_rate','ONLINE','Online (Commercial) Rates',
                             $usps_rate == 'ONLINE');
    $dialog->end_row();

    $dialog->end_table();
    $dialog->write('</td><td width="50%">');
    $dialog->start_table(null,null,2);

    $usps_services = get_row_value($values,'usps_services');
    $usps_service_default = get_row_value($values,'usps_service_default');
    $dialog->start_row('Available<br>Domestic<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('usps_service_default','-1','',
                             $usps_service_default == -1,null);
    $dialog->end_row();
    $index = 0;
    foreach ($usps_options as $id => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('usps_services_'.$index,'',
                                   $usps_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('usps_service_default',$id,'',
                                $id == $usps_service_default,null);
       $dialog->write("    </td></tr>\n");
       $index++;
    }
    $dialog->end_table();
    $dialog->end_row();
    $usps_services = get_row_value($values,'usps_intl_services');
    $usps_service_default = get_row_value($values,'usps_intl_service_default');
    $dialog->start_row('Available<br>International<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('usps_intl_service_default','-1','',
                             $usps_service_default == -1,null);
    $dialog->end_row();
    $index = 0;
    foreach ($usps_intl_options as $id => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('usps_intl_services_'.$index,'',
                                   $usps_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('usps_intl_service_default',$id,'',
                                $id == $usps_service_default,null);
       $dialog->write("      </td></tr>\n");
       $index++;
    }
    $dialog->end_table();
    $dialog->end_row();

    $dialog->end_table();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('usps_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('usps_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('USPS Label Hostname:','usps_label_hostname',$values,30);
    $dialog->add_edit_row('Shipper First Name:','usps_label_firstname',$values,40);
    $dialog->add_edit_row('Shipper Last Name:','usps_label_lastname',$values,40);
    $dialog->add_edit_row('Shipper Company Name:','usps_label_company',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','usps_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','usps_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','usps_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','usps_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','usps_label_zip',$values,20);
    $usps_label_country = get_row_value($values,'usps_label_country');
    if (empty($usps_label_country)) $usps_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('usps_label_country');
    load_country_list($usps_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Phone Number:','usps_label_phone',$values,40);
    $dialog->start_row('Use Signature Confirmation:','middle');
    $dialog->add_checkbox_field('usps_label_signature','',$values);
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function usps_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('usps_userid','usps_hostname','usps_url','usps_handling',
       'usps_country','usps_origin','usps_weight','usps_rate','usps_services',
       'usps_service_default','usps_intl_services','usps_intl_service_default',
       'usps_label_hostname','usps_label_firstname','usps_label_lastname',
       'usps_label_company','usps_label_address1','usps_label_address2',
       'usps_label_city','usps_label_state','usps_label_zip',
       'usps_label_country','usps_label_phone','usps_label_signature',
       'usps_max_weight');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function usps_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    global $usps_options,$usps_intl_options;

    if ($field_name == 'usps_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($usps_options);  $loop++)
          if (get_form_field('usps_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'usps_intl_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($usps_intl_options);  $loop++)
          if (get_form_field('usps_intl_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'usps_label_signature') {
       if (get_form_field('usps_label_signature') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'usps_handling')
       $new_field_value = parse_handling_field('usps_handling');
    else return false;
    return true;
}

function parse_usps_response($response_string,$tag)
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

function call_usps($obj,$label_flag,$post_string,&$error)
{
    if ($label_flag)
       $url = 'https://'.get_cart_config_value('usps_label_hostname');
    else $url = 'http://'.get_cart_config_value('usps_hostname');
    $path = get_cart_config_value('usps_url');
    $url .= $path;
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $response_string = $http->call($post_string);
    if (! $response_string) {
       $error = $http->error.' ('.$http->status.')';   return null;
    }
    $response_string = str_replace("\n",'',$response_string);
    $response_string = str_replace("\r",'',$response_string);
    $obj->log_shipping('USPS Response: '.$response_string);
    if (($http->status != 100) && ($http->status != 200)) {
       $error = $response_string.' ('.$http->status.')';   return null;
    }
    return $response_string;
}

function get_usps_rate(&$cart,$usps_options,&$usps_rates,$from_zip,$to_zip,
                       $shipping_country,$weight,$qty)
{
    $usps_userid = get_cart_config_value('usps_userid');
    $pound_weight = floor($weight);
    $ounces_weight = floor(($weight - $pound_weight) * 16);
    if (($pound_weight == 0) && ($ounces_weight == 0)) $ounces_weight = 1;
    $usps_rate = get_cart_config_value('usps_rate');
    if (! $usps_rate) $usps_rate = 'ALL';

    if ($shipping_country) $request_name = 'IntlRateRequest';
    else $request_name = 'RateV4Request';
    $xml_string = '<'.$request_name." USERID=\"".$usps_userid."\">";
    $xml_string .= "<Package ID=\"package\">";
    if ($shipping_country) {
       $xml_string .= '<Pounds>'.$pound_weight.'</Pounds>';
       $xml_string .= '<Ounces>'.$ounces_weight.'</Ounces>';
       $xml_string .= '<Machinable>true</Machinable>';
       $xml_string .= '<MailType>Package</MailType>';
       $xml_string .= '<Country>'.$shipping_country.'</Country>';
    }
    else {
       $xml_string .= '<Service>'.$usps_rate.'</Service>';
       $xml_string .= '<FirstClassMailType>PARCEL</FirstClassMailType>';
       $xml_string .= '<ZipOrigination>'.$from_zip.'</ZipOrigination>';
       $xml_string .= '<ZipDestination>'.$to_zip.'</ZipDestination>';
       $xml_string .= '<Pounds>'.$pound_weight.'</Pounds>';
       $xml_string .= '<Ounces>'.$ounces_weight.'</Ounces>';
       $xml_string .= '<Container/>';
       $xml_string .= '<Size>REGULAR</Size>';
       $xml_string .= '<Machinable>true</Machinable>';
    }
    $xml_string .= '</Package>';
    $xml_string .= '</'.$request_name.'>';

    if ($shipping_country) $rate_api = 'IntlRate';
    else $rate_api = 'RateV4';
    $post_string = 'API='.$rate_api.'&XML='.urlencode($xml_string);
    $log_string = 'API='.$rate_api.'&XML='.$xml_string;
    
    $cart->log_shipping('USPS Sent: '.$log_string);
    $response_string = call_usps($cart,false,$post_string,$error);
    if (! $response_string) {
       $cart->errors['shippingerror'] = true;
       $cart->error = $error;   return false;
    }
   
    if (strpos($response_string,'<Error>') !== false) {
       $cart->errors['shippingerror'] = true;
       $cart->error = parse_usps_response($response_string,'Description');
       return false;
    }

    if ($shipping_country)
       $services_array = explode('</Service>',$response_string);
    else $services_array = explode('</Postage>',$response_string);
    for ($loop = 0;  $loop < count($services_array) - 1;  $loop++) {
       if ($shipping_country) {
          $start_pos = strpos($services_array[$loop],'<Service');
          $end_pos = strpos($services_array[$loop],"\"><Pounds>");
          $usps_code = substr($services_array[$loop],$start_pos + 13,$end_pos - $start_pos - 13);
          $start_pos = strpos($services_array[$loop],'<Postage>');
          $end_pos = strpos($services_array[$loop],'</Postage>');
          $usps_rate = substr($services_array[$loop],$start_pos + 9,$end_pos - $start_pos - 9);
       }
       else {
          $start_pos = strpos($services_array[$loop],'<Postage');
          $end_pos = strpos($services_array[$loop],"\"><MailService>");
          $usps_code = substr($services_array[$loop],$start_pos + 18,$end_pos - $start_pos - 18);
          $start_pos = $end_pos + 15;
          $end_pos = strpos($services_array[$loop],'</MailService>');
          $mail_service = substr($services_array[$loop],$start_pos,$end_pos - $start_pos);
          if ($mail_service == 'First-Class Mail Flat') continue;
          if ($usps_rate == 'ONLINE') {
             $start_pos = strpos($services_array[$loop],'<CommercialRate>');
             $start_pos += 16;
             $end_pos = strpos($services_array[$loop],'</CommercialRate>');
             $usps_rate = substr($services_array[$loop],$start_pos,
                                 $end_pos - $start_pos);
          }
          else {
             $start_pos = strpos($services_array[$loop],'<Rate>');
             $start_pos += 6;
             $end_pos = strpos($services_array[$loop],'</Rate>');
             $usps_rate = substr($services_array[$loop],$start_pos,
                                 $end_pos - $start_pos);
          }
       }
       if ($shipping_country) $index = 100;
       else $index = 0;
       foreach ($usps_options as $id => $label) {
          if ($usps_code == $id) {
             $usps_rates[$index] = ($usps_rate * $qty);   break;
          }
          $index++;
       }
    }

    return true;
}

function cleanup_usps_country_name($shipping_country_info)
{
   switch ($shipping_country_info['id']) {
      case 236: return 'British Virgin Islands';
      case 84: return 'Georgia, Republic of';
      case 5: return 'Great Britain and Northern Ireland';
      case 106: return 'Iran';
      case 118: return 'Korea, Republic of (South Korea)';
      case 146: return 'Monaco (France)';
      case 151: return 'Myanmar (Burma)';
      case 182: return 'Russia';
      case 194: return 'Serbia-Montenegro';
      case 198: return 'Slovak Republic';
    }
    return $shipping_country_info['country'];
}

function init_usps_rates($num_options,$intl)
{
    $rates = array();
    for ($loop = 0;  $loop < $num_options;  $loop++) {
       if ($intl) $index = $loop + 100;
       else $index = $loop;
       $rates[$index] = 0;
    }
    return $rates;
}

function update_usps_rates(&$usps_rates,$new_rates,$num_options,$intl)
{
    static $first_time;

    if ($num_options == 0) {
       $first_time = true;   return;
    }
    for ($loop = 0;  $loop < $num_options;  $loop++) {
       if ($intl) $index = $loop + 100;
       else $index = $loop;
       if ($new_rates[$index] == 0) $usps_rates[$index] = 0;
       else if ((! $first_time) && ($usps_rates[$index] == 0)) continue;
       else $usps_rates[$index] += $new_rates[$index];
    }
    $first_time = false;
}

function usps_load_shipping_options(&$cart,$customer)
{
    global $usps_options,$usps_label_options,$usps_intl_options;

    require_once '../cartengine/currency.php';

    load_cart_config_values($cart->db);
    $usps_userid = get_cart_config_value('usps_userid');
    if (! $usps_userid) return;
    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];
    $usps_country = get_cart_config_value('usps_country');
/*     Puerto Rico (178) is a *state* for USPS   */
    if (empty($usps_country)) $usps_country = 1;
    else if ($usps_country == 178) $usps_country = 1;
    $shipping_country = $customer->shipping_country;
    if ($shipping_country == 178) $shipping_country = 1;

    $shipping_country_info = get_country_info($shipping_country,$cart->db);
    if (($usps_country != 1) || ($shipping_country != 1)) {
       $usps_services = get_cart_config_value('usps_intl_services');
       $usps_service_default = get_cart_config_value('usps_intl_service_default');
       if ($usps_service_default !== null) $usps_service_default += 100;
       $usps_country_info = get_country_info($usps_country,$cart->db);
       $usps_country = cleanup_usps_country_name($usps_country_info);
       $shipping_country_name = cleanup_usps_country_name($shipping_country_info);
       $options_list = $usps_intl_options;
    }
    else {
       $usps_services = get_cart_config_value('usps_services');
       $usps_service_default = get_cart_config_value('usps_service_default');
       $shipping_country_name = null;   $options_list = $usps_options;
    }
    if ($shipping_country_name) $intl = true;
    else $intl = false;
    $usps_handling = $cart->get_handling($shipping_country_info,$customer,
                                         'usps_handling');
    $default_origin = get_cart_config_value('usps_origin');
    $default_weight = get_cart_config_value('usps_weight');
    $to_zip = $customer->get('ship_zipcode');
    if (strlen($to_zip) > 5) $to_zip = substr($to_zip,0,5);
    $num_options = count($options_list);
    $usps_rates = init_usps_rates($num_options,$intl);
    update_usps_rates($usps_rates,null,0,$intl);
    $max_weight = get_cart_config_value('usps_max_weight');
    if (empty($max_weight)) $max_weight = USPS_MAX_WEIGHT;

    $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                          $customer);
    foreach ($origin_info as $origin_zip => $weight) {
       if (strlen($origin_zip) > 5) $origin_zip = substr($origin_zip,0,5);
       if (($shipping_country == 1) && (! $to_zip)) continue;
       if ($weight > $max_weight) {
          $num_packages = intval($weight / $max_weight);
          $remaining_weight = $weight - ($num_packages * $max_weight);
          $new_rates = init_usps_rates($num_options,$intl);
          if (! get_usps_rate($cart,$options_list,$new_rates,$origin_zip,
                              $to_zip,$shipping_country_name,$max_weight,
                              $num_packages))
             log_error('USPS Error: '.$cart->error);
          update_usps_rates($usps_rates,$new_rates,$num_options,$intl);
          if ($remaining_weight) {
             $new_rates = init_usps_rates($num_options,$intl);
             if (! get_usps_rate($cart,$options_list,$new_rates,$origin_zip,
                                 $to_zip,$shipping_country_name,
                                 floatval($remaining_weight),1))
                log_error('USPS Error: '.$cart->error);
             update_usps_rates($usps_rates,$new_rates,$num_options,$intl);
          }
       }
       else {
          $new_rates = init_usps_rates($num_options,$intl);
          if (! get_usps_rate($cart,$options_list,$new_rates,$origin_zip,
                              $to_zip,$shipping_country_name,floatval($weight),1))
             log_error('USPS Error: '.$cart->error);
          update_usps_rates($usps_rates,$new_rates,$num_options,$intl);
       }
    }

    if ($currency != 'USD') $exchange_rate = get_exchange_rate('USD',$currency);
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
       $shipping_method = get_custom_shipping_default($cart,$usps_service_default);
    else $shipping_method = $usps_service_default;
    $index = 0;   $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($options_list as $id => $label) {
       if (! ($usps_services & (1 << $index))) {   $index++;   continue;   }
       if ($shipping_country_name) $rate_index = $index + 100;
       else $rate_index = $index;
       if ($usps_rates[$rate_index] == 0) {   $index++;   continue;   }
       $handling = $usps_handling;
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($usps_rates[$rate_index] * ($handling/100)),2);
       }
       $rate = $usps_rates[$rate_index] + $handling;
       if ($exchange_rate != 0.0) $rate = floatval($rate) * $exchange_rate;
       if ($shipping_country_name) $rate_id = intval($id) + 100;
       else $rate_id = $id;
       $cart->add_shipping_option('usps',$rate_id,$rate,'USPS '.$label,
                                  $shipping_method == $rate_id);
       $index++;
    }
}

function usps_process_shipping(&$order,$shipping_method)
{
    global $usps_options,$usps_intl_options;

    if ($order->customer->shipping['country'] != 1)
       $options_list = $usps_intl_options;
    else $options_list = $usps_options;
    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    $option_index = $shipping_info[1];
    if (isset($order->customer->shipping['country']) &&
        ($order->customer->shipping['country'] != 1)) $option_index -= 100;
    if (isset($options_list[$option_index]))
       $order->set('shipping_method',$shipping_info[1].'|' .
                   $options_list[$option_index]);
    else $order->set('shipping_method','');
}

function usps_display_shipping_info($dialog,$order)
{
    $dialog->add_text_row('Shipping Carrier:','USPS');
    $shipping_method = get_row_value($order->info,'shipping_method');
    if ($shipping_method == '') $shipping_method = 'Unknown';
    else {
       $shipping_info = explode('|',$shipping_method);
       if (isset($shipping_info[1])) $shipping_method = $shipping_info[1];
       else $shipping_method = $shipping_info[0];
    }
    if (strpos($shipping_method,'USPS') === false)
       $shipping_method = 'USPS '.$shipping_method;
    $dialog->add_text_row('Shipping Method:',$shipping_method,'top');
}

function usps_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'USPS';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       if ($shipping_method == '') return 'Unknown';
       else {
          $shipping_info = explode('|',$shipping_method);
          if (count($shipping_info) < 2) return 'USPS';
          if (isset($shipping_info[1]))
             $shipping_method = $shipping_info[1];
          else $shipping_method = $shipping_info[0];
          if (strpos($shipping_method,'USPS') === false)
             $shipping_method = 'USPS '.$shipping_method;
          return $shipping_method;
       }
    }
    return null;
}

function usps_get_tracking_url($tracking)
{
    $url = 'https://tools.usps.com/go/TrackConfirmAction.action?' .
           'tRef=fullpage&tLc=1&tLabels='.$tracking;
    return $url;
}

function usps_all_methods()
{
    global $usps_options,$usps_intl_options;

    $methods = array();
    foreach ($usps_options as $id => $label) $methods[$id] = $label;
    foreach ($usps_intl_options as $id => $label)
       $methods[intval($id) + 100] = $label;
    return $methods;
}

function usps_available_methods()
{
    global $usps_options,$usps_intl_options;

    $usps_services = get_cart_config_value('usps_services');
    $usps_intl_services = get_cart_config_value('usps_intl_services');
    $methods = array();   $index = 0;
    foreach ($usps_options as $id => $label) {
       if (! ($usps_services & (1 << $index))) {
          $index++;   continue;
       }
       $methods[$id] = $label;   $index++;
    }
    foreach ($usps_intl_options as $id => $label) {
       if (! ($usps_intl_services & (1 << $index))) {
          $index++;   continue;
       }
       $methods[intval($id) + 100] = $label;   $index++;
    }
    return $methods;
}

function usps_default_weight($db)
{
    return get_cart_config_value('usps_weight',$db);
}

function usps_verify_shipping_label($db)
{
    $usps_label_company = get_cart_config_value('usps_label_company',$db);
    if (! $usps_label_company) return false;
    return true;
}

function usps_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.pdf';
}

function usps_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                      $label_filename)
{
    global $usps_options,$usps_label_options,$usps_intl_options;

    load_cart_config_values($order->db);

    $default_weight = get_cart_config_value('ups_weight');
    $weight = load_order_weight($order,$default_weight);
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $usps_label_service_code = $shipping_info[0];
    $express_mail = in_array($usps_label_service_code,array(2,3,23));
    if (! $express_mail)
       $usps_label_signature = get_cart_config_value('usps_label_signature');

    if ($express_mail) {
       $xml_string = '<ExpressMailLabelRequest USERID="' .
                     get_cart_config_value('usps_userid').'">';
       $xml_string .= '<Option /><EMCAAccount /><EMCAPassword />';
       $xml_string .= '<ImageParameters></ImageParameters>';
       $xml_string .= '<FromFirstName>'.encode_xml_data(get_cart_config_value(
                      'usps_label_firstname'),38).'</FromFirstName>';
       $xml_string .= '<FromLastName>'.encode_xml_data(get_cart_config_value(
                      'usps_label_lastname'),38).'</FromLastName>';
    }
    else {
       if ($usps_label_signature == 1)
          $xml_string = '<SignatureConfirmationV4.0Request USERID="' .
                        get_cart_config_value('usps_userid').'">';
       else $xml_string = '<DeliveryConfirmationV4.0Request USERID="' .
                          get_cart_config_value('usps_userid').'">';
       $from_name = get_cart_config_value('usps_label_firstname').' ' .
                    get_cart_config_value('usps_label_lastname');
       $xml_string .= '<FromName>'.encode_xml_data($from_name,38) .
                      '</FromName>';
    }
    $xml_string .= '<FromFirm>'.encode_xml_data(get_cart_config_value(
                   'usps_label_company'),38).'</FromFirm>';
    $xml_string .= '<FromAddress1>'.encode_xml_data(get_cart_config_value(
                   'usps_label_address2'),38).'</FromAddress1>';
    $xml_string .= '<FromAddress2>'.encode_xml_data(get_cart_config_value(
                   'usps_label_address1'),38).'</FromAddress2>';
    $xml_string .= '<FromCity>'.encode_xml_data(get_cart_config_value(
                   'usps_label_city'),21).'</FromCity>';
    $xml_string .= '<FromState>'.encode_xml_data(get_cart_config_value(
                   'usps_label_state')).'</FromState>';
    $xml_string .= '<FromZip5>'.encode_xml_data(get_cart_config_value(
                   'usps_label_zip')).'</FromZip5><FromZip4></FromZip4>';
    if ($express_mail) {
       $phone = get_cart_config_value('usps_label_phone');
       $phone = str_replace('-','',$phone);
       $phone = str_replace('.','',$phone);
       $phone = str_replace(' ','',$phone);
       $xml_string .= '<FromPhone>'.encode_xml_data($phone).'</FromPhone>';
    }

    if ($express_mail) {
       $xml_string .= '<ToFirstName>'.encode_xml_data($order->info['fname'],38) .
                      '</ToFirstName>';
       $xml_string .= '<ToLastName>'.encode_xml_data($order->info['lname'],38) .
                      '</ToLastName>';
    }
    else {
       if (isset($order->shipping['shipto']) && $order->shipping['shipto'])
          $to_name = $order->shipping['shipto'];
       else {
          $to_name = $order->info['fname'];
          if (isset($order->info['mname']) && ($order->info['mname'] != ''))
             $to_name .= ' '.$order->info['mname'];
          $to_name .= ' '.$order->info['lname'];
       }
       $xml_string .= '<ToName>'.encode_xml_data($to_name,38).'</ToName>';
    }
    $xml_string .= '<ToFirm>'.encode_xml_data($order->shipping['company'],38) .
                   '</ToFirm>';
    $xml_string .= '<ToAddress1>'.encode_xml_data($order->shipping['address2'],38) .
                   '</ToAddress1>';
    $xml_string .= '<ToAddress2>'.encode_xml_data($order->shipping['address1'],38) .
                   '</ToAddress2>';
    $xml_string .= '<ToCity>'.encode_xml_data($order->shipping['city'],21) .
                   '</ToCity>';
    $xml_string .= '<ToState>'.encode_xml_data($order->shipping['state']) .
                   '</ToState>';
    $xml_string .= '<ToZip5>'.encode_xml_data($order->shipping['zipcode']) .
                   '</ToZip5><ToZip4></ToZip4>';
    if ($express_mail) {
       $phone = str_replace('-','',$order->billing['phone']);
       $phone = str_replace('.','',$phone);
       $phone = str_replace(' ','',$phone);
       $xml_string .= '<ToPhone>'.encode_xml_data($phone).'</ToPhone>';
    }
    $xml_string .= '<WeightInOunces>'.($weight * 16).'</WeightInOunces>';
    if ($express_mail) {
       $xml_string .= '<ShipDate /><FlatRate /><SundayHolidayDelivery />' .
                      '<StandardizeAddress /><WaiverOfSignature /><NoHoliday />' .
                      '<NoWeekend /><SeparateReceiptPage />';
       $xml_string .= '<POZipCode>'.encode_xml_data(get_cart_config_value(
                      'usps_label_zip')).'</POZipCode>';
    }
    else $xml_string .= '<ServiceType>' .
                        $usps_label_options[$usps_label_service_code] .
                        '</ServiceType>';
    $xml_string .= '<ImageType>PDF</ImageType>';
    $xml_string .= '<CustomerRefNo>'.$order->id.'</CustomerRefNo>';
    if ($express_mail)
       $xml_string .= '</ExpressMailLabelRequest>';
    else if ($usps_label_signature == 1)
       $xml_string .= '</SignatureConfirmationV4.0Request>';
    else $xml_string .= '</DeliveryConfirmationV4.0Request>';

    if ($express_mail) {
       $post_string = 'API=ExpressMailLabel&XML='.urlencode($xml_string);
       $log_string = 'API=ExpressMailLabel&XML='.$xml_string;
    }
    else if ($usps_label_signature == 1) {
       $post_string = 'API=SignatureConfirmationV4&XML='.urlencode($xml_string);
       $log_string = 'API=SignatureConfirmationV4&XML='.$xml_string;
    }
    else {
       $post_string = 'API=DeliveryConfirmationV4&XML='.urlencode($xml_string);
       $log_string = 'API=DeliveryConfirmationV4&XML='.$xml_string;
    }

    $order->log_shipping('USPS Sent: '.$log_string);
    $response_string = call_usps($order,true,$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    if (strpos($response_string,'<Error>') !== false) {
       $order->error = parse_usps_response($response_string,'Description');
       return false;
    }

    if ($express_mail) {
       $tracking_name = 'EMConfirmationNumber';
       $label_name = 'EMLabel';
    }
    else if ($usps_label_signature == 1) {
       $tracking_name = 'SignatureConfirmationNumber';
       $label_name = 'SignatureConfirmationLabel';
    }
    else {
       $tracking_name = 'DeliveryConfirmationNumber';
       $label_name = 'DeliveryConfirmationLabel';
    }
    $tracking_num = parse_usps_response($response_string,$tracking_name);
    if ($tracking_num == null) {
       $order->error = 'Unable to find '.$tracking_name;
       return false;
    }
    $shipment_info['tracking'] = $tracking_num;
    $graphic_image = parse_usps_response($response_string,$label_name);
    if ($graphic_image == null) {
       $order->error = 'Unable to find '.$label_name;
       return false;
    }
    $image = base64_decode($graphic_image);
    $image_file = fopen($label_filename,'wb');
    if (! $image_file) {
       $order->error = 'Unable to create label image file';   return false;
    }
    fwrite($image_file,$image);
    fclose($image_file);

    return true;
}

function usps_print_shipping_label($db,$order,$label_filename)
{
    header('Content-Type: application/pdf');
    header('Cache-Control: no-cache');
    header('Expires: -1441');
    print file_get_contents($label_filename);
}

?>
