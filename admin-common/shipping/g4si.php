<?php
/*
                      Inroads Shopping Cart - G4SI API Module

                        Written 2010-2019 by Randall Severy
                         Copyright 2010-2019 Inroads, LLC
*/

if (! function_exists('get_server_type')) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require_once 'cartconfig-common.php';
   require_once 'orders-common.php';
   $g4si_setup = true;
}
else $g4si_setup = false;

function g4si_module_labels(&$module_labels)
{
    $module_labels['g4si'] = 'G4SI';
}

function g4si_shipping_tabs(&$shipping_tabs)
{
    $shipping_tabs['g4si_rates'] = 'G4SI Rates';
    $shipping_tabs['g4si_labels'] = 'G4SI Labels';
}

function g4si_shipping_cart_config_section($db,$dialog,$values)
{
    $dialog->start_subtab_content('g4si_rates_content',
                                  $dialog->current_subtab == 'g4si_rates_tab');
    $dialog->set_field_padding(2);
    $dialog->start_field_table('g4si_rates_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('G4SI UserID:','g4si_userid',$values,15);
    $dialog->add_edit_row('G4SI Password:','g4si_password',$values,15);
    $dialog->add_edit_row('G4SI Access Key:','g4si_key',$values,60);
    $dialog->add_edit_row('G4SI Hostname:','g4si_hostname',$values,15);
    $dialog->add_edit_row('G4SI Carrier:','g4si_carrier',$values,15);
    add_handling_field($dialog,'g4si_handling',$values);
    $dialog->add_edit_row('Default Origin Zip Code:','g4si_origin',$values,10);
    $dialog->add_edit_row('Default Weight:','g4si_weight',$values,10,null,' (Lbs)');

    $dialog->end_field_table();
    $dialog->end_subtab_content();

    $dialog->start_subtab_content('g4si_labels_content',false);
    $dialog->set_field_padding(2);
    $dialog->start_field_table('g4si_labels_table');

    $dialog->write("<tr height=\"5px\"><td colspan=\"2\"></td></tr>\n");

    $dialog->add_edit_row('Shipper Attention:','g4si_label_attn',$values,40);
    $dialog->add_edit_row('Shipper Company Name:','g4si_label_company',$values,40);
    $dialog->add_edit_row('Shipper Address #1:','g4si_label_address1',$values,40);
    $dialog->add_edit_row('Shipper Address #2:','g4si_label_address2',$values,40);
    $dialog->add_edit_row('Shipper City:','g4si_label_city',$values,40);
    $dialog->add_edit_row('Shipper State:','g4si_label_state',$values,10);
    $dialog->add_edit_row('Shipper Zip Code:','g4si_label_zip',$values,20);
    $g4si_label_country = get_row_value($values,'g4si_label_country');
    if (empty($g4si_label_country)) $g4si_label_country = 1;
    $dialog->start_row('Shipper Country:','middle');
    $dialog->start_choicelist('g4si_label_country');
    load_country_list($g4si_label_country,false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Shipper Phone Number:','g4si_label_phone',$values,40);
    $dialog->add_edit_row('Shipper E-Mail Address:','g4si_label_email',$values,40);

    $dialog->end_field_table();
    $dialog->end_subtab_content();
}

function g4si_shipping_update_cart_config_fields(&$cart_config_fields)
{
    $fields = array('g4si_userid','g4si_password','g4si_key','g4si_hostname',
       'g4si_carrier','g4si_handling','g4si_origin','g4si_weight',
       'g4si_label_attn','g4si_label_company','g4si_label_address1',
       'g4si_label_address2','g4si_label_city','g4si_label_state',
       'g4si_label_zip','g4si_label_country','g4si_label_phone',
       'g4si_label_email');
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function g4si_shipping_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'g4si_handling')
       $new_field_value = parse_handling_field('g4si_handling');
    else return false;
    return true;
}

global $use_nusoap,$using_nusoap;

if (isset($use_nusoap) || (! class_exists('SoapClient'))) {
   require_once '../engine/nusoap/nusoap.php';
   $using_nusoap = true;
   class G4SISoapClient extends nusoap_client {
      var $soap_defencoding = 'utf-8';
/*
      function getOperationData($operation) {
         $op_data = parent::getOperationData($operation);
         $op_data['soapAction'] = str_replace('WS.G4SI.COM','wsuat.g4si.com',
                                              $op_data['soapAction']);
         return $op_data;
      }
*/
      function getHTTPBody($soapmsg) {
         $soapmsg = str_replace('SOAP-ENV','soap',$soapmsg);
         if (strpos($soapmsg,'<ShipmentRequest>') !== false)
            $soapmsg = str_replace('<G4SIAuthentication>',
                          "<G4SIAuthentication xmlns=\"http://WS.G4SI.COM/\">",
                          $soapmsg);
         else $soapmsg = str_replace('<G4SIAuthentication>',
                             "<G4SIAuthentication xmlns=\"http://tempuri.org/\">",
                             $soapmsg);
         $soapmsg = str_replace('</G4SIAuthentication><CommodityList>',
                       "</G4SIAuthentication><CommodityList xmlns=\"http://WS.G4SI.COM/\">",
                       $soapmsg);
         $soapmsg = str_replace('<ShipmentRequest>',
                       "<ShipmentRequest xmlns=\"http://WS.G4SI.COM/\">",
                       $soapmsg);
         $soapmsg = str_replace('<ShipmentVoidRequest>',
                       "<ShipmentVoidRequest xmlns=\"http://tempuri.org/\">",
                       $soapmsg);
         $soapmsg = str_replace('<AccessKeyRequest>',
                       "<AccessKeyRequest xmlns=\"http://tempuri.org/\">",
                       $soapmsg);
         return $soapmsg;
      }
   }
}
else $using_nusoap = false;

function g4si_load_shipping_options(&$cart,$customer)
{
    $shipping_country_info = get_country_info($customer->shipping_country,$cart->db);
    $handling = $cart->get_handling($shipping_country_info,$customer,'g4si_handling');
    if (function_exists('custom_add_g4si_shipping_option'))
       custom_add_g4si_shipping_option($cart,$customer,$handling);
    else {
       $carrier = get_cart_config_value('g4si_carrier');
       $cart->add_shipping_option('g4si',0,$handling,$carrier.' Shipping',true);
    }
}

function g4si_process_shipping(&$order,$shipping_method)
{
    $shipping_info = explode('|',$shipping_method);
    $order->set('shipping',$shipping_info[2]);
    $order->set('shipping_carrier',$shipping_info[0]);
    $order->set('shipping_method',$shipping_info[1]);
}

function g4si_display_shipping_info($dialog,$order)
{
    $shipping_method = get_row_value($order->info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $carrier = get_cart_config_value('g4si_carrier');
    $dialog->add_text_row('Shipping Carrier:',$carrier.' (G4SI)');
    $dialog->add_text_row('Shipping Method:',$shipping_info[0],'top');
}

function g4si_format_shipping_field($order_info,$field_name)
{
    $carrier = get_cart_config_value('g4si_carrier');
    if ($field_name == 'shipping_carrier') return $carrier.' (G4SI)';
    else if ($field_name == 'shipping_method') {
       $shipping_method = get_row_value($order_info,'shipping_method');
       $shipping_info = explode('|',$shipping_method);
       return $shipping_info[0];
    }
    return null;
}

function g4si_get_tracking_url($tracking)
{
    return null;
}

function g4si_available_methods()
{
    $carrier = get_cart_config_value('g4si_carrier');
    return array(0 => $carrier.' Shipping');
}

function g4si_all_methods()
{
    return g4si_available_methods();
}

function g4si_default_weight($db)
{
    return get_cart_config_value('g4si_weight',$db);
}

function cleanup_phone_number($phone)
{
    $phone = str_replace(' ','',$phone);
    $phone = str_replace('-','',$phone);
    $phone = str_replace('(','',$phone);
    $phone = str_replace(')','',$phone);
    $phone = str_replace('.','',$phone);
    return $phone;
}

class AccessKeyRequest {
  var $UserName;
  var $Password;
  var $CompanyZipCode;
  var $CompanyCityName;
}

class G4SIAuthentication {
  var $Username;
  var $Password;
  var $AccessKey;
}

class ShipmentRequest {
  var $CarrierName;
  var $ShipmentDate;
  var $BillFreightTo;
  var $ServiceLevelName;
  var $PackageTypeName;
  var $NoOfPieces;
  var $DeclaredWeight;
  var $DeclareValue;
  var $WeightType;
  var $Description;
  var $Dimensions;
  var $DimensionUnitType;
  var $DutiesPayor_type;
  var $RecipientAddress;
  var $ShipperAddress;
  var $SalesOrderNumber;
  var $Residential;
  var $SignatureOption;
  var $CommodityCollection;
}

class Address {
  var $Name;
  var $Company;
  var $Address1;
  var $Address2;
  var $City;
  var $State;
  var $CountryCode;
  var $Zip;
  var $Phone;
  var $Email;
}

class CommodityCollection {
  var $CommodityList;
}

class ShipmentCommodities {
  var $CommodityDescription;
  var $HarmonizedCode;
  var $CommodityQuantity;
  var $Measurement;
  var $CustomValue;
  var $CommodityWeight;
  var $CodeCountryOfManufacture;
  var $NameOnLabel;
}

class ShipmentVoidRequest {
  var $TrackingNumber;
  var $ShipmentDate;
  var $CarrierName;
}

function g4si_compressed_bytes_to_string($compressed_string)
{
    global $using_nusoap;

    $g4si_url = 'https://'.get_cart_config_value('g4si_hostname').'/IPSUtilities.asmx?wsdl';
    if ($using_nusoap) {
       $ips_utility = new G4SISoapClient($g4si_url,true);
       $soap_error = $ips_utility->getError();
       if ($soap_error) {
          print 'Soap Error: '.$soap_error."<br>\n";   return;
       }
    }
    else $ips_utility = new SoapClient($g4si_url,array('trace'=>1));

    if ($using_nusoap)
       $result = $ips_utility->call('CompressedBytesToString',
                                    array('CompressedBytes' => $compressed_string));
    else $result = $ips_utility->CompressedBytesToString(array('CompressedBytes' => $compressed_string));

    if ($ips_utility->fault) $soap_error = $result;
    else $soap_error = $ips_utility->getError();
    if ($soap_error) {
       print 'Soap Error: '.$soap_error."<br>\n";
       print 'Result = '.print_r($result,true)."<br>\n";
       print 'Request = '.htmlspecialchars($ips_utility->request,ENT_QUOTES)."<br>\n";
       print 'Response = '.htmlspecialchars($ips_utility->response,ENT_QUOTES)."<br>\n";
       print 'Debug = '.htmlspecialchars($ips_utility->debug_str,ENT_QUOTES)."<br>\n";
    }       

    $uncompressed_string = $result['CompressedBytesToStringResult'];
    return $uncompressed_string;
}

function g4si_utility_function($function)
{
    global $using_nusoap;

    $g4si_url = 'https://'.get_cart_config_value('g4si_hostname') .
                '/IPSUtilities.asmx?wsdl';
    if ($using_nusoap) {
       $ips_utility = new G4SISoapClient($g4si_url,true);
       $soap_error = $ips_utility->getError();
       if ($soap_error) {
          print 'Soap Error: '.$soap_error."<br>\n";   return;
       }
    }
    else $ips_utility = new SoapClient($g4si_url,array('trace'=>1));

    $ips_auth_settings = new G4SIAuthentication();
    $ips_auth_settings->Username = get_cart_config_value('g4si_userid');
    $ips_auth_settings->Password = get_cart_config_value('g4si_password');
    $ips_auth_settings->AccessKey = get_cart_config_value('g4si_key');
    $ips_utility->setHeaders(array('G4SIAuthentication' => $ips_auth_settings));

    if ($using_nusoap)
       $result = $ips_utility->call($function);
    else $result = $ips_utility->$function();

    if ($ips_utility->fault) $soap_error = $result;
    else $soap_error = $ips_utility->getError();
    if ($soap_error) {
       print 'Soap Error: '.$soap_error."<br>\n";
       print 'Result = '.print_r($result,true)."<br>\n";
       print 'Request = '.htmlspecialchars($ips_utility->request,ENT_QUOTES)."<br>\n";
       print 'Response = '.htmlspecialchars($ips_utility->response,ENT_QUOTES)."<br>\n";
       print 'Debug = '.htmlspecialchars($ips_utility->debug_str,ENT_QUOTES)."<br>\n";
    }       

    $response_name = $function.'Result';
    $compressed_string = $result[$response_name];
    $uncompressed_string = g4si_compressed_bytes_to_string($compressed_string);
    return $uncompressed_string;
}

function g4si_verify_shipping_label($db)
{
    $g4si_userid = get_cart_config_value('g4si_userid',$db);
    if (! $g4si_userid) return false;
    return true;
}

function g4si_get_shipping_label_filename($order_id)
{
    global $file_dir;

    return $file_dir.'/labels/'.$order_id.'.gif';
}

function g4si_generate_shipping_label(&$order,&$shipment_info,$outbound,
                                      $label_filename)
{
    global $using_nusoap,$company_name;

    load_cart_config_values($order->db);
    $default_weight = get_cart_config_value('g4si_weight');
    $weight = load_order_weight($order,$default_weight);
    if ($weight == 0) $weight = $default_weight;
    $shipping_method = get_row_value($shipment_info,'shipping_method');
    $shipping_info = explode('|',$shipping_method);
    $service_level_name = $shipping_info[0];
    $country_id = $order->shipping['country'];
    $country_info = get_country_info($country_id,$order->db);

    $g4si_url = 'https://'.get_cart_config_value('g4si_hostname').'/IPSShipping.asmx?wsdl';
    if ($using_nusoap) {
       $ips_shipping = new G4SISoapClient($g4si_url,true);
       $soap_error = $ips_shipping->getError();
       if ($soap_error) {
          $order->error = $soap_error;
          $order->log_shipping('G4SI Error: '.$order->error);
          log_activity('G4SI Error: '.$order->error);   return false;
       }
    }
    else $ips_shipping = new SoapClient($g4si_url,array('trace'=>1));

    $ips_auth_settings = new G4SIAuthentication();
    $ips_auth_settings->Username = get_cart_config_value('g4si_userid');
    $ips_auth_settings->Password = get_cart_config_value('g4si_password');
    $ips_auth_settings->AccessKey = get_cart_config_value('g4si_key');

    $shipment_request = new ShipmentRequest();
    if ((! isset($order->shipping['shipto'])) || ($order->shipping['shipto'] == '')) {
       $recipient_name = $order->info['fname'];
       if (isset($order->info['mname']) && ($order->info['mname'] != ''))
          $recipient_name .= ' '.$order->info['mname'];
       $recipient_name .= ' '.$order->info['lname'];
    }
    else $recipient_name = $order->shipping['shipto'];
    $shipment_request->CarrierName = get_cart_config_value('g4si_carrier');
    $shipment_request->ShipmentDate = date('Y-m-d').'T'.date('H:i:s');
    $shipment_request->BillFreightTo = 'G4SI International';
    $shipment_request->ServiceLevelName = $service_level_name;
    if ($country_id == 1) {
//       $shipment_request->ServiceLevelName = 'Fedex Priority';
       $shipment_request->PackageTypeName = 'FedEx Box(20 lbs max)';
    }
    else {
//       $shipment_request->ServiceLevelName = 'FedEx Intl. Priority';
       $shipment_request->PackageTypeName = 'FedEx 10kg Box';
    }
    $shipment_request->NoOfPieces = 1;
    $shipment_request->DeclaredWeight = $weight;
    $shipment_request->DeclareValue = $order->info['total'];
    $shipment_request->WeightType = 'lb';
    $shipment_request->Description = $company_name.' Shopping Cart Order';
    $shipment_request->Dimensions = '10 x 10 x 10 in';
    $shipment_request->DimensionUnitType = 'in';
    $shipment_request->DutiesPayorType = 'RECIPIENT';
    $shipment_request->RecipientAddress = new Address();
    $shipment_request->RecipientAddress->Name = $recipient_name;
    $shipment_request->RecipientAddress->Company = $order->shipping['company'];
    $shipment_request->RecipientAddress->Address1 = $order->shipping['address1'];
    $shipment_request->RecipientAddress->Address2 = $order->shipping['address2'];
    $shipment_request->RecipientAddress->City = $order->shipping['city'];
    $shipment_request->RecipientAddress->State = $order->shipping['state'];
    $shipment_request->RecipientAddress->Zip = $order->shipping['zipcode'];
    $shipment_request->RecipientAddress->CountryCode = $country_info['code'];
    $shipment_request->RecipientAddress->Phone =
       cleanup_phone_number($order->billing['phone']);
    $shipment_request->RecipientAddress->Email = $order->info['email'];
    $shipment_request->ShipperAddress = new Address();
    $shipment_request->ShipperAddress->Name = get_cart_config_value('g4si_label_attn');
    $shipment_request->ShipperAddress->Company = get_cart_config_value('g4si_label_company');
    $shipment_request->ShipperAddress->Address1 = get_cart_config_value('g4si_label_address1');
    $shipment_request->ShipperAddress->Address2 = get_cart_config_value('g4si_label_address2');
    $shipment_request->ShipperAddress->City = get_cart_config_value('g4si_label_city');
    $shipment_request->ShipperAddress->State = get_cart_config_value('g4si_label_state');
    $shipment_request->ShipperAddress->Zip = get_cart_config_value('g4si_label_zip');
    $country_info = get_country_info(get_cart_config_value('g4si_label_country'),
                                     $order->db);
    $shipment_request->ShipperAddress->CountryCode = $country_info['code'];
    $shipment_request->ShipperAddress->Phone =
       cleanup_phone_number(get_cart_config_value('g4si_label_phone'));
    $shipment_request->ShipperAddress->Email = get_cart_config_value('g4si_label_email');
    $shipment_request->SalesOrderNumber = $order->info['order_number'];
    $shipment_request->Residential = 'true';
    $shipment_request->SignatureOption = 'Direct';

    if ($country_id != 1) {
       $shipment_commodities = new ShipmentCommodities();
       $shipment_commodities->CommodityDescription = $company_name.' Shopping Cart Order';
       $shipment_commodities->HarmonizedCode = '7116201000';
       $shipment_commodities->CommodityQuantity = 1;
       $shipment_commodities->Measurement = '10 x 10 x 10 in / 1lb';
       $shipment_commodities->CustomValue = $order->info['total'];
       $shipment_commodities->CommodityWeight = get_cart_config_value('g4si_weight');
       $shipment_commodities->CodeCountryOfManufacture = 'US';
       $shipment_commodities->NameOnLabel = $recipient_name;
       $commodity_collection = new CommodityCollection();
       $commodity_collection->CommodityList = $shipment_commodities;
       $shipment_request->CommodityCollection = $commodity_collection;
    }

    if (function_exists('update_g4si_data')) update_g4si_data($order,$shipment_request);
    if ($country_id != 1)
       $ips_shipping->setHeaders(array('G4SIAuthentication' => $ips_auth_settings,
                                       'CommodityList' => $shipment_commodities,
                                       'ShipmentRequest' => $shipment_request));
    else $ips_shipping->setHeaders(array('G4SIAuthentication' => $ips_auth_settings,
                                         'ShipmentRequest' => $shipment_request));

    $log_info = unserialize(serialize($shipment_request));
    $log_string = print_r($log_info,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('G4SI Sent: '.$log_string);

    if ($using_nusoap)
       $result = $ips_shipping->call('CreateShipment');
    else $result = $ips_shipping->CreateShipment();

    if ($ips_shipping->fault) $soap_error = $result;
    else $soap_error = $ips_shipping->getError();
    if ($soap_error) {
       $order->error = 'SOAP Fault: '.print_r($soap_error,true);
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }
    $log_string = print_r($result,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('G4SI Response: '.$log_string);
/*
if ($country_id != 1) {
print 'Result = '.print_r($result,true)."<br>\n";
print 'Request = '.htmlspecialchars($ips_shipping->request,ENT_QUOTES)."<br>\n";
print 'Response = '.htmlspecialchars($ips_shipping->response,ENT_QUOTES)."<br>\n";
}
*/
    if ((! isset($result['CreateShipmentResult'])) ||
        (! isset($result['CreateShipmentResult']['Status']))) {
       $order->error = 'Invalid Response from G4SI';
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }
    $response_status = $result['CreateShipmentResult']['Status'];
    if ($response_status == 'false') {
       if ((! isset($result['CreateShipmentResult']['ShipmentResponseErrorCode'])) ||
           (! isset($result['CreateShipmentResult']['ShipmentResponseError']))) {
          $order->error = 'Invalid Response from G4SI';
          $order->log_shipping('G4SI Error: '.$order->error);
          log_activity('G4SI Error: '.$order->error);   return false;
       }
       $error_code = $result['CreateShipmentResult']['ShipmentResponseErrorCode'];
       $response_error = $result['CreateShipmentResult']['ShipmentResponseError'];
       $order->error = $response_error.' ('.$error_code.')';
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }

    if (! isset($result['CreateShipmentResult']['ShipmentTrackingNumber'])) {
       $order->error = 'Unable to find TrackingNumber';   return false;
    }
    $tracking_num = $result['CreateShipmentResult']['ShipmentTrackingNumber'];
    $shipment_info['tracking'] = $tracking_num;

    if (! isset($result['CreateShipmentResult']['ShipmentLabel'])) {
       $order->error = 'Unable to find ShipmentLabel';   return false;
    }
    $graphic_image = $result['CreateShipmentResult']['ShipmentLabel'];
    $image = base64_decode($graphic_image);
    $image_file = fopen($label_filename,'wb');
    if (! $image_file) {
       $order->error = 'Unable to create label image file';   return false;
    }
    fwrite($image_file,$image);
    fclose($image_file);

    return true;
}

function g4si_print_shipping_label($db,$order,$label_filename)
{
    global $company_name,$order_label;

    ini_set('memory_limit','256M');
    $label_type = 'plainpaper';
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
    else {
       $pdf->StartTransform();
       $pdf->Image($label_filename,50,34,537,307,'','','',false,300,'',
                   false,false,0,true);
       $pdf->StopTransform();
    }
    $pdf->Output('shipping_label_'.$id.'.pdf','I');
}

function g4si_cancel_shipment(&$order,$shipment_info)
{
    global $using_nusoap;

    load_cart_config_values($order->db);

    $g4si_url = 'https://'.get_cart_config_value('g4si_hostname').'/IPSShipping.asmx?wsdl';
    if ($using_nusoap) {
       $ips_shipping = new G4SISoapClient($g4si_url,true);
       $soap_error = $ips_shipping->getError();
       if ($soap_error) {
          $order->error = $soap_error;
          $order->log_shipping('G4SI Error: '.$order->error);
          log_activity('G4SI Error: '.$order->error);   return false;
       }
    }
    else $ips_shipping = new SoapClient($g4si_url,array('trace'=>1));

    $ips_auth_settings = new G4SIAuthentication();
    $ips_auth_settings->Username = get_cart_config_value('g4si_userid');
    $ips_auth_settings->Password = get_cart_config_value('g4si_password');
    $ips_auth_settings->AccessKey = get_cart_config_value('g4si_key');

    $void_request = new ShipmentVoidRequest();
    $void_request->TrackingNumber = $shipment_info['tracking'];
    $void_request->CarrierName = get_cart_config_value('g4si_carrier');
    $shipped_date = $order->info['shipped_date'];
    $void_request->ShipmentDate = date('Y-m-d',$shipped_date).'T' .
                                  date('H:i:s',$shipped_date);

    $ips_shipping->setHeaders(array('G4SIAuthentication' => $ips_auth_settings,
                                    'ShipmentVoidRequest' => $void_request));

    $log_info = unserialize(serialize($void_request));
    $log_string = print_r($log_info,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('G4SI Sent: '.$log_string);

    if ($using_nusoap)
       $result = $ips_shipping->call('VoidShipment');
    else $result = $ips_shipping->VoidShipment();

    if ($ips_shipping->fault) $soap_error = $result;
    else $soap_error = $ips_shipping->getError();
    if ($soap_error) {
       $order->error = 'SOAP Fault: '.print_r($soap_error,true);
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }
    $log_string = print_r($result,true);
    $log_string = str_replace("\n",'',$log_string);
    $log_string = str_replace('  ','',$log_string);
    $log_string = str_replace('[',' [',$log_string);
    $order->log_shipping('G4SI Response: '.$log_string);

    if ((! isset($result['VoidShipmentResult'])) ||
        (! isset($result['VoidShipmentResult']['Status']))) {
       $order->error = 'Invalid Response from G4SI';
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }
    $response_status = $result['VoidShipmentResult']['Status'];
    if ($response_status == 'false') {
       if ((! isset($result['VoidShipmentResult']['ErrorCode'])) ||
           (! isset($result['VoidShipmentResult']['ErrorDescription']))) {
          $order->error = 'Invalid Response from G4SI';
          $order->log_shipping('G4SI Error: '.$order->error);
          log_activity('G4SI Error: '.$order->error);   return false;
       }
       $error_code = $result['VoidShipmentResult']['ErrorCode'];
       $response_error = $result['VoidShipmentResult']['ErrorDescription'];
       $order->error = $response_error.' ('.$error_code.')';
       $order->log_shipping('G4SI Error: '.$order->error);
       log_activity('G4SI Error: '.$order->error);   return false;
    }

    return true;
}

if ($g4si_setup) {

   function generate_key()
   {
       global $using_nusoap;
       print "<h1>Generate Access Key</h1>\n";

       $g4si_url = 'https://'.get_cart_config_value('g4si_hostname') .
                   '/IPSUtilities.asmx?wsdl';
       if ($using_nusoap) {
          $ips_utility = new G4SISoapClient($g4si_url,true);
          $soap_error = $ips_utility->getError();
          if ($soap_error) {
             print 'Soap Error: '.$soap_error."<br>\n";   return;
          }
       }
       else $ips_utility = new SoapClient($g4si_url,array('trace'=>1));

       $access_key_request = new AccessKeyRequest();
       $access_key_request->UserName = get_cart_config_value('g4si_userid');
       $access_key_request->Password = get_cart_config_value('g4si_password');
       $access_key_request->CompanyZipCode = get_cart_config_value('g4si_label_zip');
       $access_key_request->CompanyCityName = get_cart_config_value('g4si_label_city');
       $ips_utility->setHeaders(array('AccessKeyRequest' => $access_key_request));

       if ($using_nusoap)
          $result = $ips_utility->call('GenerateAccessKey');
       else $result = $ips_utility->GenerateAccessKey();

       if ($ips_utility->fault) $soap_error = $result;
       else $soap_error = $ips_utility->getError();
       if ($soap_error) {
          print 'Soap Error: '.$soap_error."<br>\n";
          print 'Result = '.print_r($result,true)."<br>\n";
          print 'Request = '.htmlspecialchars($ips_utility->request,ENT_QUOTES)."<br>\n";
          print 'Response = '.htmlspecialchars($ips_utility->response,ENT_QUOTES)."<br>\n";
          print 'Debug = '.htmlspecialchars($ips_utility->debug_str,ENT_QUOTES)."<br>\n";
       }       

       $access_key = $result['GenerateAccessKeyResult']['AccessKey'];
       print 'Access Key = '.$access_key."<br>\n";
   }

   function get_service_levels()
   {
       print "<h1>Service Levels</h1>\n";
       $service_levels = g4si_utility_function('GetSrvcLvls');
       $service_levels = str_replace('>',">\n",$service_levels);
       print '<pre>' . htmlspecialchars($service_levels,ENT_QUOTES) . '</pre>';
   }

   function get_package_types()
   {
       print "<h1>Service Levels</h1>\n";
       $package_types = g4si_utility_function('GetPackageTypes');
       $package_types = str_replace('>',">\n",$package_types);
       print '<pre>' . htmlspecialchars($package_types,ENT_QUOTES) . '</pre>';
   }

   $cmd = get_form_field('cmd');
   if ($cmd == 'generatekey') generate_key();
   else if ($cmd == 'servicelevels') get_service_levels();
   else if ($cmd == 'packagetypes') get_package_types();
   else print "<h1 align=\"center\">You must specify a G4SI function</h1>\n";
}

?>
