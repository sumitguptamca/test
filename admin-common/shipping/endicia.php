<?php
/*
                      Inroads Shopping Cart - Endicia API Module

                         Written 2010-2019 by Randall Severy
                          Copyright 2010-2019 Inroads, LLC
*/

if (! function_exists('get_server_type')) {
   chdir('..');
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require_once '../cartengine/cartconfig-common.php';
   require_once '../cartengine/orders-common.php';
   $endicia_setup = true;
}
else $endicia_setup = false;

define('ENDICIA_MAX_WEIGHT',70);
define('ENDICIA_TEST_URL',
       'https://elstestserver.endicia.com/LabelService/EwsLabelService.asmx/');
define('ENDICIA_PRODUCTION_URL',
       'https://labelserver.endicia.com/LabelService/EwsLabelService.asmx/');
define('ENDICIA_SERVICES_URL',
       'https://www.endicia.com/ELS/ELSServices.cfc?wsdl');

global $endicia_options;
$endicia_options = array('Express Mail','First-Class Mail','Library Mail',
   'Media Mail','Parcel Post (6-10 days)','Parcel Select',
   'Priority Mail (2-3 days)','Standard Mail');

global $endicia_option_values;
$endicia_option_values = array('Express','First','LibraryMail','MediaMail',
   'ParcelPost','ParcelSelect','Priority','StandardMail');

global $endicia_intl_options;
$endicia_intl_options = array('Express Mail International',
   'First-Class Mail International','Priority Mail International');

global $endicia_intl_option_values;
$endicia_intl_option_values = array('ExpressMailInternational',
   'FirstClassMailInternational','PriorityMailInternational');

function endicia_module_labels(&$module_labels)
{
    $module_labels['endicia'] = 'Endicia';
}

function endicia_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['endicia_rates'] = 'Endicia Rates';
    $shipping_tabs['endicia_labels'] = 'Endicia Labels';
}

function endicia_shipping_cart_config_section($db,$dialog,$values)
{
    global $endicia_options,$endicia_intl_options;

    $dialog->start_subtab_content('endicia_rates_content',
                                  $dialog->current_subtab == 'endicia_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('endicia_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Endicia Partner ID:','endicia_partnerid',$values,20);
    $dialog->add_edit_row('Endicia Account ID:','endicia_account',$values,20);
    $dialog->add_edit_row('Pass Phrase:','endicia_passphrase',$values,20);
    add_handling_field($dialog,'endicia_handling',$values);
    if (empty($values['endicia_max_weight']))
       $values['endicia_max_weight'] = ENDICIA_MAX_WEIGHT;
    $dialog->add_edit_row('Max Package Weight:','endicia_max_weight',$values,
                          10,null,' (Lbs)');
    $dialog->add_edit_row('Default Origin Zip Code:','endicia_origin',$values,10);
    $dialog->add_edit_row('Default Weight:','endicia_weight',$values,10,null,' (Lbs)');
    $endicia_services = get_row_value($values,'endicia_services');
    $endicia_service_default = get_row_value($values,'endicia_service_default');
    $dialog->start_row('Available<br>Domestic<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('endicia_service_default','-1','',
                             $endicia_service_default == -1);
    $dialog->end_row();
    foreach ($endicia_options as $index => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('endicia_services_'.$index,'',
                                   $endicia_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('endicia_service_default',$index,'',
                                $index == $endicia_service_default);
       $dialog->end_row();
    }
    $dialog->end_table();
    $dialog->end_row();
    $endicia_services = get_row_value($values,'endicia_intl_services');
    $endicia_service_default = get_row_value($values,'endicia_intl_service_default');
    $dialog->start_row('Available<br>International<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('endicia_intl_service_default','-1','',
                             $endicia_service_default == -1);
    $dialog->end_row();
    foreach ($endicia_intl_options as $index => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('endicia_intl_services_'.$index,'',
                                   $endicia_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('endicia_intl_service_default',$index,'',
                                $index == $endicia_service_default);
       $dialog->end_row();
    }
    $dialog->end_table();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('endicia_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('endicia_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Web Password:','endicia_label_web_password',$values,20,null,
       '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="../admin/shipping/endicia.php"' .
       ' target="_blank">Endicia Account Functions</a>');
    $dialog->add_edit_row('Shipper Company Name:','endicia_label_company',$values,40);
    $dialog->add_edit_row('Shipper Attention:','endicia_label_name',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','endicia_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','endicia_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','endicia_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','endicia_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','endicia_label_zip',$values,20);
    $endicia_label_country = get_row_value($values,'endicia_label_country');
    if (empty($endicia_label_country)) $endicia_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('endicia_label_country');
    load_country_list($endicia_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Phone Number:','endicia_label_phone',$values,40);
    $dialog->add_edit_row('Shipper Email:','endicia_label_email',$values,40);
    $dialog->start_row('Use Test Server:','middle');
    $dialog->add_checkbox_field('endicia_label_testing','',$values);
    $dialog->end_row();
    $endicia_label_type = get_row_value($values,'endicia_label_type');
    $dialog->start_row('Label Type:','middle');
    $dialog->add_radio_field('endicia_label_type','plainpaper','Plain Paper',
                             $endicia_label_type != '4x6');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('endicia_label_type','4x6','4x6 Labels',
                             $endicia_label_type == '4x6');
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function endicia_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('endicia_partnerid','endicia_account','endicia_passphrase',
       'endicia_handling','endicia_origin','endicia_weight','endicia_services',
       'endicia_max_weight','endicia_service_default','endicia_intl_services',
       'endicia_intl_service_default','endicia_label_web_password',
       'endicia_label_name','endicia_label_company','endicia_label_address1',
       'endicia_label_address2','endicia_label_city','endicia_label_state',
       'endicia_label_zip','endicia_label_country','endicia_label_phone',
       'endicia_label_email','endicia_label_testing','endicia_label_type');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function endicia_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    global $endicia_options,$endicia_intl_options;

    if ($field_name == 'endicia_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($endicia_options);  $loop++)
          if (get_form_field('endicia_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'endicia_intl_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($endicia_intl_options);  $loop++)
          if (get_form_field('endicia_intl_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'endicia_label_testing') {
       if (get_form_field('endicia_label_testing') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'endicia_handling')
       $new_field_value = parse_handling_field('endicia_handling');
    else return false;
    return true;
}

function process_endicia_error(&$cart,$error)
{
    global $ignore_shipping_errors;

    if (! empty($ignore_shipping_errors))
       log_error('Endicia Shipping Error: '.$error);
    else {
       $cart->errors['shippingerror'] = true;   $cart->error = $error;
    }
}

function call_endicia($order,$url,$post_string,&$error)
{
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $response_string = $http->call($post_string);
    if (! $response_string) {
       $error = $http->error.' ('.$http->status.')';   return null;
    }
    $response_string = str_replace("\n",'',$response_string);
    $response_string = str_replace("\r",'',$response_string);
    $order->log_shipping('Endicia Response: '.$response_string);
    if (($http->status != 100) && ($http->status != 200)) {
       $error = $response_string.' ('.$http->status.')';   return null;
    }
    return $response_string;
}

function parse_endicia_response($response_string,$tag)
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

function get_endicia_rate(&$cart,$mail_class,$from_zip,$to_zip,$to_country,$weight)
{
    if (strlen($from_zip) > 5) $from_zip = substr($from_zip,0,5);
    if (strlen($to_zip) > 5) $to_zip = substr($to_zip,0,5);
    $xml_string = '<PostageRateRequest>';
    $xml_string .= '<RequesterID>'.encode_xml_data(get_cart_config_value('endicia_partnerid')) .
                   '</RequesterID>';
    $xml_string .= '<CertifiedIntermediary>';
    $xml_string .= '<AccountID>'.encode_xml_data(get_cart_config_value('endicia_account')) .
                   '</AccountID>';
    $xml_string .= '<PassPhrase>'.encode_xml_data(get_cart_config_value('endicia_passphrase')) .
                   '</PassPhrase>';
    $xml_string .= '</CertifiedIntermediary>';
    $xml_string .= '<MailClass>'.$mail_class.'</MailClass>';
    $xml_string .= '<WeightOz>'.round($weight * 16,1).'</WeightOz>';
    $xml_string .= '<MailpieceShape>Parcel</MailpieceShape>';
    $xml_string .= '<Machinable>True</Machinable>';
    $xml_string .= '<Services DeliveryConfirmation="OFF" SignatureConfirmation="OFF" />';
    $xml_string .= '<FromPostalCode>'.get_cart_config_value('endicia_origin') .
                   '</FromPostalCode>';
    $xml_string .= '<ToPostalCode>'.$to_zip.'</ToPostalCode>';
    if ($to_country) $xml_string .= '<ToCountry>'.$to_country.'</ToCountry>';
    $xml_string .= '<ResponseOptions PostagePrice="TRUE" />';
    $xml_string .= '<Value>'.$cart->info['total'].'</Value>';
    $xml_string .= '</PostageRateRequest>';
    $post_string = 'postageRateRequestXML='.urlencode($xml_string);
    $cart->log_shipping('Endicia Sent: postageRateRequestXML=<?xml version="1.0" encoding="utf-8"?>' .
                        $xml_string);
    $endicia_label_testing = get_cart_config_value('endicia_label_testing');
    if ($endicia_label_testing == 1) $url = ENDICIA_TEST_URL.'CalculatePostageRateXML';
    else $url = ENDICIA_PRODUCTION_URL.'CalculatePostageRateXML';
    $response_string = call_endicia($cart,$url,$post_string,$error);
    if (! $response_string) {
       process_endicia_error($cart,$error);   return null;
    }

    $response_status = parse_endicia_response($response_string,'Status');
    if ($response_status == null) {
       process_endicia_error($cart,'Unable to find Response Status');
       return null;
    }
    if ($response_status != 0) {
       $error_message = parse_endicia_response($response_string,'ErrorMessage');
       if ($error_message == null)
          process_endicia_error($cart,'Unable to find ErrorMessage');
       else process_endicia_error($cart,$error_message.' (' .
                                  $response_status.')');
       return null;
    }

    $postage_price = parse_endicia_response($response_string,'PostagePrice');
    $start_pos = strpos($response_string,'<PostagePrice ');
    if ($start_pos === false) $postage_price = null;
    else {
       $start_pos = strpos($response_string,'TotalAmount="',$start_pos);
       if ($start_pos === false) $postage_price = null;
       else {
          $start_pos += 13;
          $end_pos = strpos($response_string,'>',$start_pos);
          if ($end_pos === false) $postage_price = null;
          else $postage_price = substr($response_string,$start_pos,
                                       $end_pos - $start_pos - 1);
       }
    }
    if ($postage_price == null) {
       process_endicia_error($cart,'Unable to find PostagePrice');
       return null;
    }
    return $postage_price;
}

function cleanup_endicia_country_name($shipping_country_info)
{
   switch ($shipping_country_info['id']) {
      case 5: return 'England';
    }
    return $shipping_country_info['country'];
}

function endicia_load_shipping_options(&$cart,$customer)
{
    global $endicia_options,$endicia_intl_options,$endicia_option_values;
    global $endicia_intl_option_values;

    require_once '../cartengine/currency.php';

    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];
    $shipping_country_info = get_country_info($customer->shipping_country,$cart->db);
    if ($customer->shipping_country != 1) {
       $endicia_services = get_cart_config_value('endicia_intl_services');
       $endicia_service_default = get_cart_config_value('endicia_intl_service_default');
       if ($endicia_service_default !== null) $endicia_service_default += 100;
       $shipping_country = cleanup_endicia_country_name($shipping_country_info);
       $options_list = $endicia_intl_options;
       $values_list = $endicia_intl_option_values;
    }
    else {
       $endicia_services = get_cart_config_value('endicia_services');
       $endicia_service_default = get_cart_config_value('endicia_service_default');
       $shipping_country = null;   $options_list = $endicia_options;
       $values_list = $endicia_option_values;
    }
    $endicia_handling = $cart->get_handling($shipping_country_info,$customer,
                                            'endicia_handling');
    $default_origin = get_cart_config_value('endicia_origin');
    $default_weight = get_cart_config_value('endicia_weight');
    $to_zip = $customer->get('ship_zipcode');
    $num_options = count($options_list);
    $endicia_rates = array($num_options);
    $first_package = true;
    $max_weight = get_cart_config_value('endicia_max_weight');
    if (empty($max_weight)) $max_weight = ENDICIA_MAX_WEIGHT;

    $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                          $customer);

    foreach ($values_list as $index => $mail_class) {
       if ($customer->shipping_country != 1) $rate_index = $index + 100;
       else $rate_index = $index;
       $endicia_rates[$rate_index] = 0;
       if (! ($endicia_services & (1 << $index))) continue;
       foreach ($origin_info as $origin_zip => $weight) {
          if (($customer->shipping_country == 1) && (! $to_zip)) continue;
          if (($weight >= 1) && (substr($mail_class,0,5) == 'First')) continue;
          if ($weight > $max_weight) {
             $num_packages = intval($weight / $max_weight);
             $remaining_weight = floatval($weight - ($num_packages * $max_weight));
             $rate = get_endicia_rate($cart,$mail_class,$origin_zip,
                                      $to_zip,$shipping_country,$max_weight);
             if (($rate !== null) &&
                 ($first_package || ($endicia_rates[$rate_index] != 0)))
                $endicia_rates[$rate_index] += ($rate * $num_packages);
             if ($remaining_weight) {
                $rate = get_endicia_rate($cart,$mail_class,$origin_zip,
                                         $to_zip,$shipping_country,
                                         $remaining_weight);
                if (($rate !== null) && ($endicia_rates[$rate_index] != 0))
                   $endicia_rates[$rate_index] += $rate;
             }
          }
          else {
             $rate = get_endicia_rate($cart,$mail_class,$origin_zip,
                                      $to_zip,$shipping_country,floatval($weight));
             if (($rate !== null) &&
                 ($first_package || ($endicia_rates[$rate_index] != 0)))
                $endicia_rates[$rate_index] += $rate;
          }
          $first_package = false;
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
       $shipping_method = get_custom_shipping_default($cart,$endicia_service_default);
    else $shipping_method = $endicia_service_default;
    $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($options_list as $index => $label) {
       if (! ($endicia_services & (1 << $index))) continue;
       if ($customer->shipping_country != 1) $rate_index = $index + 100;
       else $rate_index = $index;
       if ($endicia_rates[$rate_index] == 0) {   $index++;   continue;   }
       $handling = $endicia_handling;
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($endicia_rates[$rate_index] * ($handling/100)),2);
       }
       $rate = $endicia_rates[$rate_index] + $handling;
       if ($exchange_rate != 0.0) $rate = floatval($rate) * $exchange_rate;
       $cart->add_shipping_option('endicia',$rate_index,$rate,'USPS '.$label,
                                  $shipping_method == $rate_index);
    }
}

function endicia_process_shipping(&$order,$shipping_method)
{
    global $endicia_options,$endicia_intl_options;

    if (! isset($order->customer->shipping['country']))
       $options_list = $endicia_options;
    else if ($order->customer->shipping['country'] != 1)
       $options_list = $endicia_intl_options;
    else $options_list = $endicia_options;
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

function endicia_display_shipping_info($dialog,$order)
{
    $dialog->add_text_row('Shipping Carrier:','Endicia');
    $shipping_method = get_row_value($order->info,'shipping_method');
    if ($shipping_method == '') $method = 'Unknown';
    else {
       $shipping_info = explode('|',$shipping_method);
       if (count($shipping_info) < 2) $method = 'Unknown';
       $method = $shipping_info[1];
    }
    if (strpos($method,'USPS') === false) $method = 'USPS '.$method;
    $dialog->add_text_row('Shipping Method:',$method,'top');
}

function endicia_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'Endicia';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       if ($shipping_method == '') return 'Unknown';
       else {
          $shipping_info = explode('|',$shipping_method);
          if (count($shipping_info) < 2) return 'Unknown';
          $method = $shipping_info[1];
          if (strpos($method,'USPS') === false) $method = 'USPS '.$method;
          return $method;
       }
    }
    return null;
}

function endicia_get_tracking_url($tracking)
{
    $url = 'https://tools.usps.com/go/TrackConfirmAction.action?' .
           'tRef=fullpage&tLc=1&tLabels='.$tracking;
    return $url;
}

function endicia_all_methods()
{
    global $endicia_options,$endicia_intl_options;

    $methods = array();
    foreach ($endicia_options as $index => $label) $methods[$index] = $label;
    foreach ($endicia_intl_options as $index => $label)
       $methods[$index + 100] = $label;
    return $methods;
}

function endicia_available_methods()
{
    global $endicia_options,$endicia_intl_options;

    $endicia_services = get_cart_config_value('endicia_services');
    $endicia_intl_services = get_cart_config_value('endicia_intl_services');
    $methods = array();
    foreach ($endicia_options as $index => $label) {
       if (! ($endicia_services & (1 << $index))) continue;
       $methods[$index] = $label;
    }
    foreach ($endicia_intl_options as $index => $label) {
       if (! ($endicia_intl_services & (1 << $index))) continue;
       $methods[$index + 100] = $label;
    }
    return $methods;
}

function endicia_default_weight($db)
{
    return get_cart_config_value('endicia_weight',$db);
}

function cleanup_endicia_phone_number($phone)
{
    $phone = str_replace(' ','',$phone);
    $phone = str_replace('-','',$phone);
    $phone = str_replace('(','',$phone);
    $phone = str_replace(')','',$phone);
    $phone = str_replace('.','',$phone);
    return $phone;
}

function endicia_verify_shipping_label($db)
{
    $endicia_label_web_password = get_cart_config_value(
       'endicia_label_web_password',$db);
    if (! $endicia_label_web_password) return false;
    return true;
}

function endicia_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.gif';
}

function endicia_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                         $label_filename)
{
    global $company_name,$endicia_option_values,$endicia_intl_option_values;

    if ($order->shipping['country'] != 1)
       $values_list = $endicia_intl_option_values;
    else $values_list = $endicia_option_values;

    load_cart_config_values($order->db);
    $default_weight = get_cart_config_value('endicia_weight');
    $weight = load_order_weight($order,$default_weight);
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $endicia_label_service_code = $shipping_info[0];
    $endicia_label_testing = get_cart_config_value('endicia_label_testing');
    $from_zip = get_cart_config_value('endicia_label_zip');
    if (strlen($from_zip) > 5) $from_zip = substr($from_zip,0,5);
    $to_zip = $order->shipping['zipcode'];
    if (strlen($to_zip) > 5) $to_zip = substr($to_zip,0,5);

    $xml_string = '<LabelRequest ImageFormat="GIF"';
    if ($endicia_label_testing == 1) $xml_string .= ' Test="YES"';
    $xml_string .= '>';
    $xml_string .= '<RequesterID>'.encode_xml_data(get_cart_config_value('endicia_partnerid')) .
                   '</RequesterID>';
    $xml_string .= '<AccountID>'.encode_xml_data(get_cart_config_value('endicia_account')) .
                   '</AccountID>';
    $xml_string .= '<PassPhrase>'.encode_xml_data(get_cart_config_value('endicia_passphrase')) .
                   '</PassPhrase>';
    $xml_string .= '<MailClass>'.$values_list[$endicia_label_service_code].'</MailClass>';
    $xml_string .= '<DateAdvance>0</DateAdvance>';
    $xml_string .= '<WeightOz>'.round($weight * 16,1).'</WeightOz>';
    $xml_string .= '<Stealth>TRUE</Stealth>';
    $xml_string .= '<Services InsuredMail="OFF" SignatureConfirmation="OFF" />';
    $xml_string .= '<Value>'.$order->info['total'].'</Value>';
    $xml_string .= '<Description>'.encode_xml_data($company_name) .
                   ' Shopping Cart Order</Description>';
    $xml_string .= '<RubberStamp1>Invoice #'.$order->info['order_number'] .
                   '</RubberStamp1>';
    $xml_string .= '<RubberStamp2>'.get_cart_config_value('contactphone').'</RubberStamp2>';
    $shipped_date = get_row_value($shipment_info,'shipped_date');
    if (! $shipped_date) $shipped_date = time();
    $xml_string .= '<RubberStamp3>Shipped: '.date('n/j/y',$shipped_date).'</RubberStamp3>';
    $xml_string .= '<PartnerCustomerID>'.$order->info['customer_id'].'</PartnerCustomerID>';
    $xml_string .= '<PartnerTransactionID>'.$order->id.'</PartnerTransactionID>';
    if ((! isset($order->shipping['shipto'])) || ($order->shipping['shipto'] == '')) {
       $shipto = $order->info['fname'];
       if (isset($order->info['mname']) && ($order->info['mname'] != ''))
          $shipto .= ' '.$order->info['mname'];
       $shipto .= ' '.$order->info['lname'];
    }
    else $shipto = $order->shipping['shipto'];
    $xml_string .= '<ToName>'.encode_xml_data($shipto).'</ToName>';
    $xml_string .= '<ToCompany>'.encode_xml_data($order->shipping['company']) .
                   '</ToCompany>';
    $xml_string .= '<ToAddress1>'.encode_xml_data($order->shipping['address1']) .
                   '</ToAddress1>';
    $xml_string .= '<ToAddress2>'.encode_xml_data($order->shipping['address2']) .
                   '</ToAddress2>';
    $xml_string .= '<ToCity>'.encode_xml_data($order->shipping['city']) .
                   '</ToCity>';
    $xml_string .= '<ToState>'.encode_xml_data($order->shipping['state']) .
                   '</ToState>';
    $xml_string .= '<ToPostalCode>'.$to_zip.'</ToPostalCode>';
    $xml_string .= '<ToZIP4></ToZIP4>';
    $country_info = get_country_info($order->shipping['country'],$order->db);
    $xml_string .= '<ToCountryCode>'.encode_xml_data($country_info['code']).'</ToCountryCode>';
    $xml_string .= '<ToPhone>'.encode_xml_data(cleanup_endicia_phone_number($order->billing['phone'])) .
                   '</ToPhone>';
    $xml_string .= '<ToEmail>'.encode_xml_data($order->info['email']) .
                   '</ToEmail>';
    $xml_string .= '<FromName>'.encode_xml_data(get_cart_config_value('endicia_label_name')) .
                   '</FromName>';
    $xml_string .= '<FromCompany>'.encode_xml_data(get_cart_config_value('endicia_label_company')) .
                   '</FromCompany>';
    $xml_string .= '<ReturnAddress1>'.encode_xml_data(get_cart_config_value('endicia_label_address1')) .
                   '</ReturnAddress1>';
    $xml_string .= '<ReturnAddress2>'.encode_xml_data(get_cart_config_value('endicia_label_address2')) .
                   '</ReturnAddress2>';
    $xml_string .= '<FromCity>'.encode_xml_data(get_cart_config_value('endicia_label_city')) .
                   '</FromCity>';
    $xml_string .= '<FromState>'.encode_xml_data(get_cart_config_value('endicia_label_state')) .
                   '</FromState>';
    $xml_string .= '<FromPostalCode>'.$from_zip.'</FromPostalCode>';
    $xml_string .= '<FromZIP4></FromZIP4>';
    $country_info = get_country_info(get_cart_config_value('endicia_label_country'),$order->db);
    $xml_string .= '<FromCountry>'.encode_xml_data($country_info['code']) .
                   '</FromCountry>';
    $xml_string .= '<FromPhone>'.encode_xml_data(cleanup_endicia_phone_number(get_cart_config_value('endicia_label_phone'))) .
                   '</FromPhone>';
    $xml_string .= '<FromEmail>'.encode_xml_data(get_cart_config_value('endicia_label_email')) .
                   '</FromEmail>';
    $xml_string .= '</LabelRequest>';

    $post_string = 'labelRequestXML='.urlencode($xml_string);
    
    $order->log_shipping('Endicia Sent: labelRequestXML=<?xml version="1.0" encoding="utf-8"?>' .
                         $xml_string);
    if ($endicia_label_testing == 1) $url = ENDICIA_TEST_URL.'GetPostageLabelXML';
    else $url = ENDICIA_PRODUCTION_URL.'GetPostageLabelXML';
    $response_string = call_endicia($order,$url,$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    $response_status = parse_endicia_response($response_string,'Status');
    if ($response_status == null) {
       $order->error = 'Unable to find Response Status';   return false;
    }
    if ($response_status != 0) {
       $error_message = parse_endicia_response($response_string,'ErrorMessage');
       if ($error_message == null) {
          $order->error = 'Unable to find ErrorMessage';   return false;
       }
       $order->error = $error_message.' ('.$response_status.')';   return false;
    }

    $base64_label_image = parse_endicia_response($response_string,'Base64LabelImage');
    if ($base64_label_image == null) {
       $order->error = 'Unable to find Base64LabelImage';   return false;
    }
    $tracking_num = parse_endicia_response($response_string,'TrackingNumber');
    if ($tracking_num == null) {
       $order->error = 'Unable to find TrackingNumber';   return false;
    }
    $shipment_info['tracking'] = $tracking_num;

    $image = base64_decode($base64_label_image);
    $image_file = fopen($label_filename,'wb');
    if (! $image_file) {
       $order->error = 'Unable to create label image file';   return false;
    }
    fwrite($image_file,$image);
    fclose($image_file);

    return true;
}

function endicia_print_shipping_label($db,$order,$label_filename)
{
    global $company_name,$order_label;

    ini_set('memory_limit','256M');
    $label_type = get_cart_config_value('endicia_label_type',$db);
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
       $pdf->Image($label_filename,0,0,756,432,'','','',false,300,'',
                   false,false,0,true);
       $pdf->StopTransform();
    }
    else {
       $pdf->StartTransform();
       $pdf->Rotate(-90);
       $pdf->Image($label_filename,60,-530,756,432,'','','',false,300,'',
                   false,false,0,true);
       $pdf->StopTransform();
    }
    $pdf->Output('shipping_label_'.$id.'.pdf','I');
}

function endicia_cancel_shipment(&$order,$shipment_info)
{
    load_cart_config_values($order->db);
    $endicia_label_testing = get_cart_config_value('endicia_label_testing');

    $xml_string = '<RefundRequest>';
    $xml_string .= '<RequesterID>'.encode_xml_data(get_cart_config_value('endicia_partnerid')) .
                   '</RequesterID>';
    $xml_string .= '<RequestID>'.time().'</RequestID>';
    $xml_string .= '<CertifiedIntermediary>';
    $xml_string .= '<AccountID>'.encode_xml_data(get_cart_config_value('endicia_account')) .
                   '</AccountID>';
    $xml_string .= '<PassPhrase>'.encode_xml_data(get_cart_config_value('endicia_passphrase')) .
                   '</PassPhrase>';
    $xml_string .= '</CertifiedIntermediary>';
    $xml_string .= '<PicNumbers><PICNumber>' .
                   encode_xml_data($shipment_info['tracking']).'</PICNumber></PicNumbers>';
    $xml_string .= '</RefundRequest>';

    $post_string = 'refundRequestXML='.urlencode($xml_string);
    
    $order->log_shipping('Endicia Sent: refundRequestXML=<?xml version="1.0" encoding="utf-8"?>' .
                         $xml_string);
    if ($endicia_label_testing == 1) $url = ENDICIA_TEST_URL.'GetRefundXML';
    else $url = ENDICIA_PRODUCTION_URL.'GetRefundXML';
    $response_string = call_endicia($order,$url,$post_string,$error);
    if (! $response_string) {
       $order->error = $error;   return false;
    }

    $refund_status = parse_endicia_response($response_string,'RefundStatus');
    if (! $refund_status) {
       $error_message = parse_endicia_response($response_string,
                                               'ErrorMessage');
       if (! $error_message) {
          $order->error = 'Unable to find ErrorMessage';   return false;
       }
       $order->error = 'Unable to find Refund Status';   return false;
    }
    if ($refund_status == 'Approved') return true;

    $status_message = parse_endicia_response($response_string,
                                             'RefundStatusMessage');
    if (! $status_message) {
       $error_message = parse_endicia_response($response_string,
                                               'ErrorMessage');
       if (! $error_message) {
          $order->error = 'Unable to find ErrorMessage';   return false;
       }
       $order->error = $error_message;   return false;
    }
    $order->error = $status_message;
    return false;
}

if ($endicia_setup) {

   function display_endicia_setup($status_msg=null,$error_msg=null)
   {
?>
<html>
  <head>
    <title>Endicia Account Functions</title>
    <style type="text/css">
      h1 {
        font-family: Arial,Helvetica,sans-serif;
      }
      body,td {
        font-family: Arial,Helvetica,sans-serif;
        font-size: 12px;
        font-weight: bold;
      }
      form { margin: 0px; }
      fieldset {
        -moz-border-radius: 5px;
        margin: 0px 2px 5px 2px;
        padding: 0px 7px 9px 7px;
      }
      legend {
        font-size: 14px;
        font-style: italic;
      }
      table {
         margin-top: 10px;
         margin-bottom: 10px;
      }
      p {
        text-align: center;
        margin: 0px;
      }
      p.error {
         margin: 30px;
         color: #FF0000;
         font-size: 14px;
      }
      p.status {
         margin: 30px;
         color: #00AA00;
         font-size: 14px;
      }
    </style>
  </head>
  <body>
    <h1 align="center">Endicia Account Functions</h1>
<?
   if ($status_msg) print '    <p class="status">'.$status_msg."</p>\n";
   if ($error_msg) print '    <p class="error">'.$error_msg."</p>\n";
?>    <form method="POST" action="endicia.php" name="EndiciaAccount">
    <fieldset style="width: 350px; margin: auto;"><legend>Change Pass Phrase</legend>
    <table border="0" cellpadding="2" cellspacing="0" align="center">
      <tr valign=bottom><td>New Pass Phrase:</td><td>
        <input type=text name="new_pass_phrase" size=30 value=""></td></tr>
    </table>
    <p><input type="submit" name="ChangePassPhrase" value="Change">
    </fieldset>
    <br><br>
    <fieldset style="width: 350px; margin: auto;"><legend>Buy Postage</legend>
    <table border="0" cellpadding="2" cellspacing="0" align="center">
      <tr valign=bottom><td>Postage Amount:</td><td>
        <input type=text name="postage_amount" size=30 value=""></td></tr>
    </table>
    <p><input type="submit" name="BuyPostage" value="Buy">
    </fieldset>
<!--    <br><br>
    <p><input type="submit" name="ReturnToAdmin" value="Return to Admin">-->
    </form>
  </body>
</html>
<?
   }

   function change_pass_phrase()
   {
      $new_pass_phrase = get_form_field('new_pass_phrase');
      if (! $new_pass_phrase) {
         display_endicia_setup(null,'You must enter a New Pass Phrase');   return;
      }

      $db = new DB;
      load_cart_config_values($db);
      $order = new OrderInfo();

      $xml_string = '<ChangePassPhraseRequest>';
      $xml_string .= '<RequesterID>'.encode_xml_data(get_cart_config_value('endicia_partnerid')) .
                     '</RequesterID>';
      $xml_string .= '<RequestID>'.time().'</RequestID>';
      $xml_string .= '<CertifiedIntermediary>';
      $xml_string .= '<AccountID>'.encode_xml_data(get_cart_config_value('endicia_account')) .
                     '</AccountID>';
      $xml_string .= '<PassPhrase>'.encode_xml_data(get_cart_config_value('endicia_passphrase')) .
                     '</PassPhrase>';
      $xml_string .= '</CertifiedIntermediary>';
      $xml_string .= '<NewPassPhrase>'.encode_xml_data($new_pass_phrase).'</NewPassPhrase>';
      $xml_string .= '</ChangePassPhraseRequest>';

      $post_string = 'changePassPhraseRequestXML='.urlencode($xml_string);
    
      $order->log_shipping('Endicia Sent: changePassPhraseRequestXML=' .
         '<?xml version="1.0" encoding="utf-8"?>'.$xml_string);
      $endicia_label_testing = get_cart_config_value('endicia_label_testing');
      if ($endicia_label_testing == 1) $url = ENDICIA_TEST_URL.'ChangePassPhraseXML';
      else $url = ENDICIA_PRODUCTION_URL.'ChangePassPhraseXML';
      $response_string = call_endicia($order,$url,$post_string,$error);
      if (! $response_string) {
         display_endicia_setup(null,$error);   return;
      }

      $response_status = parse_endicia_response($response_string,'Status');
      if ($response_status == null) {
         display_endicia_setup(null,'Unable to find Response Status');   return;
      }
      if ($response_status != 0) {
         $error_message = parse_endicia_response($response_string,'ErrorMessage');
         if ($error_message == null) $error_message = 'Unable to find ErrorMessage';
         display_endicia_setup(null,$error_message);   return;
      }

      $query = 'update cart_config set config_value=? ' .
               'where config_name="endicia_passphrase"';
      $query = $db->prepare_query($query,$new_pass_phrase);
      $db->log_query($query);
      if (! $db->query($query)) {
         display_endicia_setup(null,$db->error);   return;
      }

      display_endicia_setup('Your Pass Phrase has been changed');
   }

   function buy_postage()
   {
      $postage_amount = get_form_field('postage_amount');
      if (! $postage_amount) {
         display_endicia_setup(null,'You must enter a Postage Amount');   return;
      }

      $db = new DB;
      load_cart_config_values($db);
      $order = new OrderInfo();

      $xml_string = '<RecreditRequest>';
      $xml_string .= '<RequesterID>'.encode_xml_data(get_cart_config_value('endicia_partnerid')) .
                     '</RequesterID>';
      $xml_string .= '<RequestID>'.time().'</RequestID>';
      $xml_string .= '<CertifiedIntermediary>';
      $xml_string .= '<AccountID>'.encode_xml_data(get_cart_config_value('endicia_account')) .
                     '</AccountID>';
      $xml_string .= '<PassPhrase>'.encode_xml_data(get_cart_config_value('endicia_passphrase')) .
                     '</PassPhrase>';
      $xml_string .= '</CertifiedIntermediary>';
      $xml_string .= '<RecreditAmount>'.encode_xml_data($postage_amount).'</RecreditAmount>';
      $xml_string .= '</RecreditRequest>';

      $post_string = 'recreditRequestXML='.urlencode($xml_string);
    
      $order->log_shipping('Endicia Sent: recreditRequestXML=' .
         '<?xml version="1.0" encoding="utf-8"?>'.$xml_string);
      $endicia_label_testing = get_cart_config_value('endicia_label_testing');
      if ($endicia_label_testing == 1) $url = ENDICIA_TEST_URL.'BuyPostageXML';
      else $url = ENDICIA_PRODUCTION_URL.'BuyPostageXML';
      $response_string = call_endicia($order,$url,$post_string,$error);
      if (! $response_string) {
         display_endicia_setup(null,$error);   return;
      }

      $response_status = parse_endicia_response($response_string,'Status');
      if ($response_status == null) {
         display_endicia_setup(null,'Unable to find Response Status');   return;
      }
      if ($response_status != 0) {
         $error_message = parse_endicia_response($response_string,'ErrorMessage');
         if ($error_message == null) $error_message = 'Unable to find ErrorMessage';
         display_endicia_setup(null,$error_message);   return;
      }

      $amount_printed = parse_endicia_response($response_string,'AscendingBalance');
      $new_balance = parse_endicia_response($response_string,'PostageBalance');
      display_endicia_setup('Your New Postage Balance is '.$new_balance .
                            ', your total postage printed to date is '.$amount_printed);
   }

   if (! check_login_cookie('../admin/index.php')) exit;

   if (button_pressed('ChangePassPhrase')) change_pass_phrase();
   else if (button_pressed('BuyPostage')) buy_postage();
   else if (button_pressed('ReturnToAdmin'))
      header('Location: ../admin/index.php');
   else display_endicia_setup();
   DB::close_all();
}

?>
