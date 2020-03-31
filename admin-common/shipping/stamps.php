<?php
/*
                    Inroads Shopping Cart - Stamps.com API Module

                        Written 2014-2019 by Randall Severy
                         Copyright 2014-2019 Inroads, LLC
*/

global $integration_id,$staging_url,$stamps_debug;

global $stamps_options;
$stamps_options = array('US-FC' => 'First-Class Mail','US-PM' => 'Priority Mail',
   'US-XM' => 'Priority Mail Express','US-CM' => 'Critical Mail',
   'US-PP' => 'Parcel Post','US-PS' => 'Parcel Select','US-MM' => 'Media Mail',
   'US-LM' => 'Library Mail');

global $stamps_intl_options;
$stamps_intl_options = array('US-FCI' => 'First Class Mail International',
   'US-PMI' => 'Priority Mail International',
   'US-EMI' => 'Priority Mail Express International');

$integration_id = 'f5788d9b-2816-43b4-b630-8772f95c8bb1';
$staging_url = 'https://swsim.testing.stamps.com/swsim/swsimv33.asmx?wsdl';
define ('STAMPS_WSDL_URL','../admin/shipping/stamps.wsdl');
define ('STAMPS_MAX_WEIGHT',70);
if (! isset($stamps_debug)) $stamps_debug = false;

function stamps_module_labels(&$module_labels)
{
    $module_labels['stamps'] = 'USPS';
}

function stamps_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['stamps_rates'] = 'Stamps.com Rates';
    $shipping_tabs['stamps_labels'] = 'Stamps.com Labels';
}

function stamps_shipping_cart_config_section($db,$dialog,$values)
{
    global $stamps_options,$stamps_intl_options;

    $dialog->start_subtab_content('stamps_rates_content',
                                  $dialog->current_subtab == 'stamps_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('stamps_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $stamps_username = get_row_value($values,'stamps_username');
    $stamps_password = get_row_value($values,'stamps_password');
    $stamps_account_url = null;   $stamps_postage_url = null;
    if ($stamps_username && $stamps_password) {
       $stamps = new Stamps;
       if ($stamps->error) log_error('Stamps.com Error: '.$stamps->error);
       else {
          $stamps_account_url = $stamps->get_url('StoreAccount');
          $stamps_postage_url = $stamps->get_url('StoreBuyPostage');
       }
    }
    if ($stamps_account_url)
       $suffix = "<a href=\"".$stamps_account_url."\" target=\"_blank\" " .
                 "style=\"padding-left:10px;\">My Account</a>";
    else $suffix = null;
    $dialog->add_edit_row('Stamps.com Username:','stamps_username',$values,30,null,$suffix);
    if ($stamps_postage_url)
       $suffix = "<a href=\"".$stamps_postage_url."\" target=\"_blank\" " .
                 "style=\"padding-left:10px;\">Buy Postage</a>";
    else $suffix = null;
    $dialog->add_edit_row('Stamps.com Password:','stamps_password',$values,30,null,$suffix);
    add_handling_field($dialog,'stamps_handling',$values);
    if (empty($values['stamps_max_weight']))
       $values['stamps_max_weight'] = STAMPS_MAX_WEIGHT;
    $dialog->add_edit_row('Max Package Weight:','stamps_max_weight',$values,
                          10,null,' (Lbs)');
    $dialog->add_edit_row('Default Origin Zip Code:','stamps_origin',$values,10);
    $dialog->add_edit_row('Default Weight:','stamps_weight',$values,10,null,' (Lbs)');
    $stamps_services = get_row_value($values,'stamps_services');
    $stamps_service_default = get_row_value($values,'stamps_service_default');
    $dialog->start_row('Available<br>Domestic<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('stamps_service_default','-1','',
                             $stamps_service_default == -1);
    $dialog->end_row();
    $index = 0;
    foreach ($stamps_options as $id => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('stamps_services_'.$index,'',
                                   $stamps_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('stamps_service_default',$id,'',
                                $id == $stamps_service_default);
       $dialog->end_row();
       $index++;
    }
    $dialog->end_table();
    $dialog->end_row();
    $stamps_services = get_row_value($values,'stamps_intl_services');
    $stamps_service_default = get_row_value($values,'stamps_intl_service_default');
    $dialog->start_row('Available<br>International<br>Services:','top');
    $dialog->start_table(null,null,0,1);
    $dialog->write("    <tr><td class=\"fieldprompt\" style=\"text-align:left;\">Service</td>\n");
    $dialog->write("      <td></td><td class=\"fieldprompt\" style=\"text-align:center;\">Default</td>\n");
    $dialog->write("    </tr>\n");
    $dialog->write("    <tr><td colspan=\"2\">None</td><td align=\"center\">");
    $dialog->add_radio_field('stamps_intl_service_default','-1','',
                             $stamps_service_default == -1);
    $dialog->end_row();
    $index = 0;
    foreach ($stamps_intl_options as $id => $label) {
       $dialog->write('    <tr><td>'.$label.'</td><td>');
       $dialog->add_checkbox_field('stamps_intl_services_'.$index,'',
                                   $stamps_services & (1 << $index));
       $dialog->write("      </td><td align=\"center\">");
       $dialog->add_radio_field('stamps_intl_service_default',$id,'',
                                $id == $stamps_service_default);
       $dialog->write("      </td></tr>\n");
       $index++;
    }
    $dialog->end_table();
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('stamps_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('stamps_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Shipper First Name:','stamps_label_firstname',$values,40);
    $dialog->add_edit_row('Shipper Last Name','stamps_label_lastname',$values,40);
    $dialog->add_edit_row('Shipper Company Name:','stamps_label_company',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','stamps_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','stamps_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','stamps_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','stamps_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','stamps_label_zip',$values,20);
    $dialog->add_edit_row('Shipper Phone Number:','stamps_label_phone',$values,40);
    $stamps_label_type = get_row_value($values,'stamps_label_type');
    $dialog->start_row('Label Type:','middle');
    $dialog->add_radio_field('stamps_label_type','plainpaper','Plain Paper',
                             $stamps_label_type != '4x6');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('stamps_label_type','4x6','4x6 Labels',
                             $stamps_label_type == '4x6');
    $dialog->end_row();

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function stamps_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('stamps_username','stamps_password','stamps_handling',
       'stamps_max_weight','stamps_origin','stamps_weight','stamps_services',
       'stamps_service_default','stamps_intl_services',
       'stamps_intl_service_default','stamps_label_firstname',
       'stamps_label_lastname','stamps_label_company',
       'stamps_label_address1','stamps_label_address2','stamps_label_city',
       'stamps_label_state','stamps_label_zip','stamps_label_phone',
       'stamps_label_type');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function stamps_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    global $stamps_options,$stamps_intl_options;

    if ($field_name == 'stamps_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($stamps_options);  $loop++)
          if (get_form_field('stamps_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'stamps_intl_services') {
       $new_field_value = 0;
       for ($loop = 0;  $loop < count($stamps_intl_options);  $loop++)
          if (get_form_field('stamps_intl_services_'.$loop) == 'on')
             $new_field_value |= (1 << $loop);
    }
    else if ($field_name == 'stamps_handling')
       $new_field_value = parse_handling_field('stamp_handling');
    else return false;
    return true;
}

class Credentials {
  public $IntegrationID;
  public $Username;
  public $Password;
}

class GetAccountInfo {
  public $Authenticator;
}

class GetURL {
  public $Authenticator;
  public $URLType;
  public $ApplicationContext;
}

class Rate {
  public $FromZIPCode;
  public $ToZIPCode;
  public $ToCountry;
  public $WeightLb;
  public $WeightOz;
  public $ShipDate;
  public $PackageType;
  public $ServiceType;
  public $AddOns;
}

class AddOn {
  public $AddOnType;
}

class GetRates {
  public $Authenticator;
  public $Rate;
}

class Address {
  public $FullName;
  public $Company;
  public $Address1;
  public $Address2;
  public $City;
  public $State;
  public $ZIPCode;
  public $PhoneNumber;
}

class CleanseAddress {
  public $Authenticator;
  public $Address;
}

class CreateIndicium {
  public $Authenticator;
  public $IntegratorTxID;
  public $Rate;
  public $From;
  public $To;
}

class CancelIndicium {
  public $Authenticator;
  public $StampsTxID;
}

class Stamps {

function Stamps($obj=null)
{
    global $integration_id,$stamps_debug,$staging_url;

    $this->exception = null;   $this->error = null;   $this->obj = $obj;
    if ($stamps_debug) {
       $wsdl_url = $staging_url;   $options = array('trace' => true);
    }
    else {
       $wsdl_url = STAMPS_WSDL_URL;   $options = array();
    }
    $options['connection_timeout'] = 10;
    ini_set('default_socket_timeout',10);
    try {
       $this->ws = new SoapClient($wsdl_url,$options);
    } catch (SoapFault $exception) {
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return;
    }
    $stamps_username = get_cart_config_value('stamps_username');
    $stamps_password = get_cart_config_value('stamps_password');
    $credentials = new Credentials();
    $credentials->IntegrationID = $integration_id;
    $credentials->Username = $stamps_username;
    $credentials->Password = $stamps_password;
    $authenticate_info = array();
    $authenticate_info['Credentials'] = $credentials;
    $params = array('Credentials'=>$credentials);
    try {
       $result = $this->ws->AuthenticateUser($params);
    } catch (SoapFault $exception) {
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return;
    }
    if (! isset($result->Authenticator)) {
       $this->error = 'No Authenticator found in Result';   return;
    }
    $this->authenticator = $result->Authenticator;
}

function log_format($obj)
{
    $text = print_r($obj,true);
    $text = str_replace("\n",'',$text);
    $text = str_replace("\t",'',$text);
    $text = str_replace('  ','',$text);
    return $text;
}

function call($method,$params)
{
    if ($this->obj)
       $this->obj->log_shipping('Stamps Sent: '.$method.': ' .
                                $this->log_format($params));
    $params->Authenticator = $this->authenticator;
    try {
       $result = $this->ws->$method($params);
    } catch (SoapFault $exception) {
       if ($this->obj)
          $this->obj->log_shipping('Stamps Response: '.$this->log_format($exception));
       $this->exception = $exception;   $this->error = $exception->faultstring;
       return null;
    }
    if ($this->obj)
       $this->obj->log_shipping('Stamps Response: '.$this->log_format($result));
    if (! isset($result->Authenticator)) {
       $this->error = 'No Authenticator found in Result';   return null;
    }
    $this->authenticator = $result->Authenticator;
    return $result;
}

function get_account_info()
{
    $params = new GetAccountInfo();
    $result = $this->call('GetAccountInfo',$params);
    if (! $result) return null;
    if (! isset($result->AccountInfo)) return null;
    return $result->AccountInfo;
}

function get_url($url_type)
{
    $params = new GetURL();
    $params->URLType = $url_type;
    $result = $this->call('GetURL',$params);
    if (! $result) return null;
    if (! isset($result->URL)) return null;
    return $result->URL;
}

function get_rates($from_zip,$to_zip,$to_country,$weight,$signature_flag)
{
    $pound_weight = floor($weight);
    $ounces_weight = floor(($weight - $pound_weight) * 16);
    $rate = new Rate();
    $rate->FromZIPCode = $from_zip;
    $rate->ToZIPCode = $to_zip;
    $rate->ToCountry = $to_country;
    $rate->WeightLb = $pound_weight;
    $rate->WeightOz = $ounces_weight;
    $rate->ShipDate = date('Y-m-d',time() + 86400);
    $rate->PackageType = 'Package';
    $params = new GetRates();
    $params->Rate = $rate;
    $result = $this->call('GetRates',$params);
    if (! $result) return null;
    if (! isset($result->Rates->Rate)) return null;
    $rates = array();
    $rates_array = $result->Rates->Rate;
    foreach ($rates_array as $rate) {
       if (! isset($rate->ServiceType)) continue;
       $rates[$rate->ServiceType] = $rate->Amount;
       if ($signature_flag) {
          foreach ($rate->AddOns->AddOnV4 as $addon) {
             if ($addon->AddOnType == 'US-A-SC')
                $rates[$rate->ServiceType] += $addon->Amount;
          }
       }
    }
    return $rates;
}

function cleanse_address(&$address_info)
{
    $address = new Address();
    foreach ($address_info as $field_name => $field_value)
       $address->$field_name = $field_value;
    if (strlen($address->ZIPCode) > 5)
       $address->ZIPCode = substr($address->ZIPCode,0,5);
    $params = new CleanseAddress();
    $params->Address = $address;
    $result = $this->call('CleanseAddress',$params);
    if (! $result) return false;
    if ((! isset($result->CityStateZipOK)) ||
        ($result->CityStateZipOK != 'true')) {
       $this->error = 'Invalid City, State, and Zip Code Combination';
       return false;
    }
    $address = $result->Address;
    $address_info = array();
    foreach ($address as $field_name=>$field_value)
       $address_info[$field_name] = $field_value;
    return true;
}

function create_indicium($order_id,$rate_info,$from_address,$to_address,
                         $signature_flag,$image_type)
{
    global $stamps_debug;

    $rate = new Rate();
    foreach ($rate_info as $field_name => $field_value) {
       if ($field_name == 'weight') {
          $pound_weight = floor($field_value);
          $ounces_weight = floor(($field_value - $pound_weight) * 16);
          $rate->WeightLb = $pound_weight;
          $rate->WeightOz = $ounces_weight;
       }
       $rate->$field_name = $field_value;
    }
    $rate->ShipDate = date('Y-m-d',time() + 86400);
    $rate->PackageType = 'Package';
    if ($signature_flag) {
       $addon = new AddOn();
       $addon->AddOnType = 'US-A-SC';
       $rate->AddOns = array($addon);
    }
    $from = new Address();
    foreach ($from_address as $field_name => $field_value)
       $from->$field_name = $field_value;
    $to = new Address();
    foreach ($to_address as $field_name => $field_value)
       $to->$field_name = $field_value;
    $params = new CreateIndicium();
    $params->IntegratorTxID = $order_id;
    $params->Rate = $rate;
    $params->From = $from;
    $params->To = $to;
    if ($stamps_debug) $params->SampleOnly = 'true';
    $params->ImageType = $image_type;
    $params->memo = 'Order #'.$order_id;
    $result = $this->call('CreateIndicium',$params);
    if (! $result) return null;
    if (! isset($result->TrackingNumber)) return null;
    $label = array();
    $label['tracking_number'] = $result->TrackingNumber;
    $label['trans_id'] = $result->StampsTxID;
    $label['url'] = $result->URL;
    return $label;
}

function cancel_indicium($transaction_id)
{
    $params = new CancelIndicium();
    $params->StampsTxID = $transaction_id;
    $result = $this->call('CancelIndicium',$params);
    if (! $result) return false;
    return true;
}

};

function get_stamps_rate($stamps,&$cart,$stamps_options,&$stamps_rates,
                         $from_zip,$to_zip,$shipping_country,$weight,$qty,
                         $signature_flag)
{
    $rates = $stamps->get_rates($from_zip,$to_zip,$shipping_country,$weight,
                                $signature_flag);
    if (! $rates) {
       $cart->errors['shippingerror'] = true;
       $cart->error = $stamps->error;   return false;
    }
    foreach ($rates as $service_type => $shipping_rate) {
       $index = 0;
       foreach ($stamps_options as $id => $label) {
          if ($service_type == $id) {
             $stamps_rates[$index] = ($shipping_rate * $qty);   break;
          }
          $index++;
       }
    }
    return true;
}

function init_stamps_rates($num_options)
{
    $rates = array();
    for ($loop = 0;  $loop < $num_options;  $loop++) $rates[$loop] = 0;
    return $rates;
}

function update_stamps_rates(&$stamps_rates,$new_rates,$num_options)
{
    static $first_time;

    if ($num_options == 0) {
       $first_time = true;   return;
    }
    for ($loop = 0;  $loop < $num_options;  $loop++) {
       if ($new_rates[$loop] == 0) $stamps_rates[$loop] = 0;
       else if ((! $first_time) && ($stamps_rates[$loop] == 0)) continue;
       else $stamps_rates[$loop] += $new_rates[$loop];
    }
    $first_time = false;
}

function stamps_load_shipping_options(&$cart,$customer)
{
    global $stamps_options,$stamps_intl_options;

    require_once '../cartengine/currency.php';

    load_cart_config_values($cart->db);
    if (empty($cart->info['currency'])) $currency = 'USD';
    else $currency = $cart->info['currency'];

    $shipping_country_info = get_country_info($customer->shipping_country,
                                              $cart->db);
    $shipping_country = $shipping_country_info['code'];
    if (($customer->shipping_country != 1)) {
       $stamps_services = get_cart_config_value('stamps_intl_services');
       $stamps_service_default = get_cart_config_value('stamps_intl_service_default');
       $options_list = $stamps_intl_options;
    }
    else {
       $stamps_services = get_cart_config_value('stamps_services');
       $stamps_service_default = get_cart_config_value('stamps_service_default');
       $options_list = $stamps_options;
    }
    $stamps_handling = $cart->get_handling($shipping_country_info,$customer,
                                           'stamps_handling');
    $default_origin = get_cart_config_value('stamps_origin');
    $default_weight = get_cart_config_value('stamps_weight');
    $to_zip = $customer->get('ship_zipcode');
    $num_options = count($options_list);
    $stamps_rates = init_stamps_rates($num_options);
    update_stamps_rates($stamps_rates,null,0);
    $shipping_flags = get_form_field('ShippingFlags');
    if ($shipping_flags & 1) $signature_flag = true;
    else $signature_flag = false;

    $stamps = new Stamps($cart);
    if ($stamps->error) {
       log_activity('Stamps Error: '.$stamps->error);
       $cart->errors['shippingerror'] = true;
       $cart->error = $stamps->error;   return false;
    }
    $max_weight = get_cart_config_value('stamps_max_weight');
    if (empty($max_weight)) $max_weight = STAMPS_MAX_WEIGHT;
    $origin_info = $cart->get_origin_info($default_origin,$default_weight,
                                          $customer);
    foreach ($origin_info as $origin_zip => $weight) {
       if (($customer->shipping_country == 1) && (! $to_zip)) continue;
       if ($weight > $max_weight) {
          $num_packages = intval($weight / $max_weight);
          $remaining_weight = $weight - ($num_packages * $max_weight);
          $new_rates = init_stamps_rates($num_options);
          if (! get_stamps_rate($stamps,$cart,$options_list,$new_rates,
                                $origin_zip,$to_zip,$shipping_country,
                                $max_weight,$num_packages,$signature_flag))
             log_activity('Stamps Error: '.$cart->error);
          update_stamps_rates($stamps_rates,$new_rates,$num_options);
          if ($remaining_weight) {
             $new_rates = init_stamps_rates($num_options);
             if (! get_stamps_rate($stamps,$cart,$options_list,$new_rates,
                                   $origin_zip,$to_zip,$shipping_country,
                                   floatval($remaining_weight),1,
                                   $signature_flag))
                log_activity('Stamps Error: '.$cart->error);
             update_stamps_rates($stamps_rates,$new_rates,$num_options);
          }
       }
       else {
          $new_rates = init_stamps_rates($num_options);
          if (! get_stamps_rate($stamps,$cart,$options_list,$new_rates,
                                $origin_zip,$to_zip,$shipping_country,
                                floatval($weight),1,$signature_flag))
             log_activity('Stamps Error: '.$cart->error);
          update_stamps_rates($stamps_rates,$new_rates,$num_options);
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
       $shipping_method = get_custom_shipping_default($cart,$stamps_service_default);
    else $shipping_method = $stamps_service_default;
    $index = 0;   $default_rate = 0;
    if (function_exists('add_custom_shipping'))
       add_custom_shipping($cart,$shipping_method,$default_rate);
    foreach ($options_list as $id => $label) {
       if (! ($stamps_services & (1 << $index))) {   $index++;   continue;   }
       if ($stamps_rates[$index] == 0) {   $index++;   continue;   }
       $handling = $stamps_handling;
       if (substr($handling,-1) == '%') {
          $handling = substr($handling,0,-1);
          $handling = round(($stamps_rates[$index] * ($handling/100)),2);
       }
       $rate = $stamps_rates[$index] + $handling;
       if ($exchange_rate != 0.0) $rate = floatval($rate) * $exchange_rate;
       $cart->add_shipping_option('stamps',$id,$rate,'USPS '.$label,
                                  $shipping_method == $id);
       $index++;
    }
}

function stamps_process_shipping(&$order,$shipping_method)
{
    global $stamps_options,$stamps_intl_options;

    if ($order->customer->shipping['country'] != 1)
       $options_list = $stamps_intl_options;
    else $options_list = $stamps_options;
    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    if (isset($options_list[$shipping_info[1]]))
       $order->set('shipping_method',$shipping_info[1].'|' .
                   $options_list[$shipping_info[1]]);
    else $order->set('shipping_method','');
}

function stamps_display_shipping_info($dialog,$order)
{
    $dialog->add_text_row('Shipping Carrier:','USPS (Stamps.com)');
    $shipping_method = get_row_value($order->info,'shipping_method');
    if ($shipping_method == '') $method_info = 'Unknown';
    else {
       $shipping_info = explode('|',$shipping_method);
       $method_info = $shipping_info[1];
    }
    if (strpos($method_info,'USPS') === false)
       $method_info = 'USPS '.$method_info;
    $dialog->add_text_row('Shipping Method:',$method_info,'top');
}

function stamps_format_shipping_field($order_info,$field_name)
{
    if ($field_name == 'shipping_carrier') return 'USPS';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       if ($shipping_method == '') return 'Unknown';
       else {
          $shipping_info = explode('|',$shipping_method);
          if (strpos($shipping_info[1],'USPS') === false)
             $shipping_info[1] = 'USPS '.$shipping_info[1];
          return $shipping_info[1];
       }
    }
    return null;
}

function stamps_get_tracking_url($tracking)
{
    $url = 'https://tools.usps.com/go/TrackConfirmAction.action?' .
           'tRef=fullpage&tLc=1&tLabels='.$tracking;
    return $url;
}

function stamps_all_methods()
{
    global $stamps_options,$stamps_intl_options;

    $methods = array();
    foreach ($stamps_options as $id => $label) $methods[$id] = $label;
    foreach ($stamps_intl_options as $id => $label) $methods[$id] = $label;
    return $methods;
}

function stamps_available_methods()
{
    global $stamps_options,$stamps_intl_options;

    $stamps_services = get_cart_config_value('stamps_services');
    $stamps_intl_services = get_cart_config_value('stamps_intl_services');
    $methods = array();   $index = 0;
    foreach ($stamps_options as $id => $label) {
       if (! ($stamps_services & (1 << $index))) {
          $index++;   continue;
       }
       $methods[$id] = $label;   $index++;
    }
    foreach ($stamps_intl_options as $id => $label) {
       if (! ($stamps_intl_services & (1 << $index))) {
          $index++;   continue;
       }
       $methods[$id] = $label;   $index++;
    }
    return $methods;
}

function stamps_default_weight($db)
{
    return get_cart_config_value('stamps_weight',$db);
}

function stamps_verify_shipping_label($db)
{
    $stamps_username = get_cart_config_value('stamps_username',$db);
    if (! $stamps_username) return false;
    return true;
}

function stamps_get_shipping_label_filename($order_id)
{
    global $file_dir;

    $label_type = get_cart_config_value('stamps_label_type');
    if ($label_type != '4x6') $label_type = 'plainpaper';
    if ($label_type == 'plainpaper')
       $label_filename = $file_dir.'/labels/'.$id.'.pdf';
    else $label_filename = $file_dir.'/labels/'.$id.'.gif';
    return $label_filename;
}

function stamps_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                        $label_filename)
{
    require 'stamps-common.php';

    load_cart_config_values($order->db);

    $stamps = new Stamps($order);
    if ($stamps->error) {
       $order->error = $stamps->error;   return false;
    }

    $from_address = array();
    $from_address['FullName'] = get_cart_config_value('stamps_label_firstname').' ' .
                                get_cart_config_value('stamps_label_lastname');
    $from_address['Company'] = get_cart_config_value('stamps_label_company');
    $from_address['Address1'] = get_cart_config_value('stamps_label_address1');
    $from_address['Address2'] = get_cart_config_value('stamps_label_address2');
    $from_address['City'] = get_cart_config_value('stamps_label_city');
    $from_address['State'] = get_cart_config_value('stamps_label_state');
    $from_address['ZIPCode'] = get_cart_config_value('stamps_label_zip');
    $from_address['PhoneNumber'] = get_cart_config_value('stamps_label_phone');
    $to_address = array();
    $to_address['FullName'] = $order->info['fname'].' '.$order->info['lname'];
    $to_address['Company'] = $order->shipping['company'];
    $to_address['Address1'] = $order->shipping['address1'];
    $to_address['Address2'] = $order->shipping['address2'];
    $to_address['City'] = $order->shipping['city'];
    $to_address['State'] = $order->shipping['state'];
    $to_address['ZIPCode'] = $order->shipping['zipcode'];
    $to_address['PhoneNumber'] = $order->billing['phone'];
    if (! $stamps->cleanse_address($from_address)) {
       $order->error = $stamps->error;   return false;
    }
    if (! $stamps->cleanse_address($to_address)) {
       $order->error = $stamps->error;   return false;
    }
    $default_weight = get_cart_config_value('stamps_weight');
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $service_type = $shipping_info[0];
    $shipping_flags = $order->info['shipping_flags'];
    if ($shipping_flags & 1) $signature_flag = true;
    else $signature_flag = false;
    $rate = array();
    $rate['weight'] = load_order_weight($order,$default_weight);
    $rate['ServiceType'] = $service_type;
    $label_type = get_cart_config_value('stamps_label_type');
    if ($label_type == '4x6') $image_type = 'Gif';
    else $image_type = 'Pdf';
    if ($outbound) {
       $rate['FromZIPCode'] = $from_address['ZIPCode'];
       $rate['ToZIPCode'] = $to_address['ZIPCode'];
          $shipping_country_info = get_country_info($order->shipping['country'],
                                                    $order->db);
       $rate['ToCountry'] = $shipping_country_info['code'];
       $label = $stamps->create_indicium($order->id,$rate,$from_address,
                                         $to_address,$signature_flag,
                                         $image_type);
    }
    else {
       $rate['FromZIPCode'] = $to_address['ZIPCode'];
       $rate['ToZIPCode'] = $from_address['ZIPCode'];
       $rate['ToCountry'] = 'US';
       $label = $stamps->create_indicium($order->id,$rate,$to_address,
                                         $from_address,$signature_flag,
                                         $image_type);
    }
    if (! $label) {
       $order->error = $stamps->error;   return false;
    }
    $shipment_info['tracking'] = $label['tracking_number'];
    $shipment_info['shipping_trans'] = $label['trans_id'];
    $image = file_get_contents($label['url']);
    $image_file = fopen($label_filename,'wb');
    if (! $image_file) {
       $order->error = 'Unable to create label image file';   return false;
    }
    fwrite($image_file,$image);
    fclose($image_file);

    return true;
}

function stamps_print_shipping_label($db,$order,$label_filename)
{
    global $company_name,$order_label;

    ini_set('memory_limit','256M');
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
       $pdf->SetAutoPageBreak(false,0);
       $pdf->Image($label_filename,20,0,571,900,'','','',2,300,'',
                   false,false,0,true);
       $pdf->StopTransform();
    }
    else $pdf->Image($label_filename,50,34,537,307,'','','',false,300,'',
                     false,false,0,true);
    $pdf->Output('shipping_label_'.$id.'.pdf','I');
}

function stamps_cancel_shipment(&$order,$shipment_info)
{
    if (! $shipment_info['shipping_trans']) {
       $order->error = 'No Transaction ID found to cancel';   return false;
    }
    $stamps = new Stamps($order);
    if ($stamps->error) {
       $order->error = $stamps->error;   return false;
    }
    if (! $stamps->cancel_indicium($shipment_info['shipping_trans'])) {
       $order->error = $stamps->error;   return false;
    }
    return true;
}

?>
