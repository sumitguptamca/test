<?php
/*
                Inroads Shopping Cart - Chase Paymentech API Module

                        Written 2009-2018 by Randall Severy
                         Copyright 2009-2018 Inroads, LLC
*/

if (! function_exists('get_server_type')) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require_once 'cartconfig-common.php';
   $chase_setup = true;
}
else $chase_setup = false;

define('CHASE_WSDL_URL','https://ws.paymentech.net/PaymentechGateway/wsdl/PaymentechGateway.wsdl');
define('CHASE_SERVICE_ENDPOINT','https://cws-01.ipcommerce.com:443/2.0/SvcInfo/');
define('CHASE_TRANS_ENDPOINT','https://cws-01.ipcommerce.com:443/2.0/Txn/');
define('CHASE_TRANS_SCHEMA','http://schemas.ipcommerce.com/CWS/v2.0/Transactions/Bankcard');

function chase_payment_cart_config_head(&$dialog,$db)
{
    $dialog->add_script_file('../admin/payment/chase.js');
}

function chase_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'Chase Paymentech','chase',$values);

    $chase_interface = get_row_value($values,'chase_interface');
    $dialog->start_row('Interface:','middle');
    $dialog->add_radio_field('chase_interface','0','Chase Direct',
                             $chase_interface == 0,'select_chase();');
    $dialog->add_radio_field('chase_interface','1','Commerce Web Services',
                             $chase_interface == 1,'select_chase();');
    $dialog->end_row();
    if ($chase_interface == 0) $hidden = false;
    else $hidden = true;
    $dialog->start_hidden_row('Industry Type:','chase_0_0',$hidden);
    $dialog->add_input_field('chase_industry',$values,10);
    $dialog->end_row();
    $dialog->start_hidden_row('Transaction Type:','chase_0_1',$hidden);
    $dialog->add_input_field('chase_transtype',$values,10);
    $dialog->end_row();
    $dialog->start_hidden_row('Bin Number:','chase_0_2',$hidden);
    $dialog->add_input_field('chase_bin',$values,10);
    $dialog->end_row();
    $dialog->start_hidden_row('Merchant ID:','chase_0_3',$hidden);
    $dialog->add_input_field('chase_merchant',$values,30);
    $dialog->end_row();
    $dialog->start_hidden_row('Terminal ID:','chase_0_4',$hidden);
    $dialog->add_input_field('chase_terminal',$values,10);
    $dialog->end_row();
    if ($chase_interface == 0) $hidden = true;
    else $hidden = false;
    $dialog->start_hidden_row('Socket ID:','chase_1_0',$hidden);
    $dialog->add_input_field('chase_socket',$values,30);
    $dialog->end_row();
    $dialog->start_hidden_row('Service ID:','chase_1_1',$hidden);
    $dialog->add_input_field('chase_service_id',$values,30);
    $dialog->end_row();
    $dialog->start_hidden_row('Application Profile ID:','chase_1_2',$hidden);
    $dialog->add_input_field('chase_app_id',$values,30);
    $dialog->end_row();
    $dialog->start_hidden_row('Merchant Profile ID:','chase_1_3',$hidden);
    $dialog->add_input_field('chase_merchant_profile_id',$values,30);
    $dialog->end_row();
    $dialog->start_hidden_row('Identity Token:','chase_1_4',$hidden,'top');
    $dialog->start_textarea_field('chase_token',20,45,WRAP_SOFT);
    if (file_exists('../admin/payment/chase.token')) {
       $chase_token = file_get_contents('../admin/payment/chase.token');
       write_form_value($chase_token);
    }
    $dialog->end_textarea_field();
    $dialog->end_row();
}

function chase_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('chase_interface','chase_industry','chase_transtype',
       'chase_bin','chase_merchant','chase_terminal','chase_socket',
       'chase_service_id','chase_app_id','chase_merchant_profile_id',
       'chase_token');
    if (! empty($website_settings)) $fields[] = 'chase_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function chase_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'chase_token') {
       $chase_interface = get_form_field('chase_interface');
       if ($chase_interface != 1) continue;
       $chase_token = get_form_field('chase_token');
       $token_file = fopen('../admin/payment/chase.token','wt');
       if (! $token_file) {
          http_response(422,'Unable to open chase.token file');   return;
       }
       fwrite($token_file,$chase_token);
       fclose($token_file);
       $new_field_value = null;
    }
    else if ($field_name == 'chase_active') {
       if (get_form_field('chase_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function chase_active($db)
{
    return get_cart_config_value('chase_active',$db);
}

function chase_get_primary_module($db)
{
    return 'chase';
}

class BankcardTransaction {
  public $TenderData; // BankcardTenderData
  public $TransactionData; // BankcardTransactionData
}

class BankcardTenderData {
  public $CardData; // CardData
  public $CardSecurityData; // CardSecurityData
}

class CardData {
  public $CardType; // TypeCardType
  public $CardholderName; // string
  public $PAN; // string
  public $Expire; // string
  public $Track1Data; // string
  public $Track2Data; // string
}

class CardSecurityData {
  public $AVSData; // AVSData
  public $CVDataProvided; // CVDataProvided
  public $CVData; // string
  public $KeySerialNumber; // string
  public $PIN; // string
}

class AVSData {
  public $CardholderName; // string
  public $Street; // string
  public $City; // string
  public $StateProvince; // TypeStateProvince
  public $PostalCode; // string
  public $Country; // TypeISOCountryCodeA3
  public $Phone; // string
}

class BankcardTransactionData {
  public $AccountType; // AccountType
  public $ApprovalCode; // string
  public $CashBackAmount; // decimal
  public $CustomerPresent; // CustomerPresent
  public $EmployeeId; // string
  public $EntryMode; // EntryMode
  public $GoodsType; // GoodsType
  public $IndustryType; // IndustryType
  public $InternetTransactionData; // InternetTransactionData
  public $InvoiceNumber; // string
  public $OrderNumber; // string
  public $SignatureCaptured; // boolean
  public $TerminalId; // string
  public $TipAmount; // decimal
}

function cleanup_chase_phone_number($phone)
{
    $phone = str_replace(' ','',$phone);
    $phone = str_replace('-','',$phone);
    $phone = str_replace('(','',$phone);
    $phone = str_replace(')','',$phone);
    $phone = str_replace('.','',$phone);
    if (strlen($phone) == 10) $phone = substr($phone,0,3).' '.substr($phone,3);
    return $phone;
}

function cleanup_chase_country($country)
{
    if ($country == 1) return 'USA';
    else if ($country == 43) return 'CAN';
    return '';
}

function chase_process_payment(&$order)
{
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);

    $chase_interface = get_cart_config_value('chase_interface');

    if ($chase_interface == 0) {
       $exp_date = $order->credit_card['year'].$order->credit_card['month'];
       $chase_ws = new SoapClient(CHASE_WSDL_URL);
       $soap_array = array('industryType' => get_cart_config_value('chase_industry'),
                           'transType' => get_cart_config_value('chase_transtype'),
                           'bin' => get_cart_config_value('chase_bin'),
                           'merchantID' => get_cart_config_value('chase_merchant'),
                           'terminalID' => get_cart_config_value('chase_terminal'),
                           'orderID' => $order->info['order_number'],
                           'amount' => $order->payment['payment_amount'],
                           'avsName' => $order->credit_card['name'],
                           'ccAccountNum' => $order->credit_card['number'],
                           'ccExp' => $exp_date,
                           'ccCardVerifyNum' => $order->credit_card['cvv']);
       if (function_exists('update_chase_data'))
          update_chase_data($order,$soap_array,$chase_interface);
       $log_string = '';
       foreach ($soap_array as $key => $value) {
          if (($key == 'ccAccountNum') || ($key == 'ccExp') ||
              ($key == 'ccCardVerifyNum')) continue;
          if ($log_string != '') $log_string .= '&';
          $log_string .= $key.'='.urlencode($value);
       }
       $order->log_payment('Sent: '.$log_string);
       try {
          $result = $chase_ws->NewOrder(array('newOrderRequest'=>$soap_array));
       } catch (SoapFault $exception) {
          $order->error = $exception;
          $order->log_payment('Chase Paymentech Error: '.$order->error);
          log_activity('Chase Paymentech Error: '.$order->error);   return false;
       }
       print 'Chase Result = '.print_r($result,true)."<br>\n";
       return false;
       $response_data = str_replace("\n",'|',$response_data);
       $order->log_payment('Response: '.$response_data);
       $response_array = explode('|',$response_data);
       $result_array = array();
       foreach ($response_array as $index => $fieldpair) {
          $fieldpair_array = explode('=',$fieldpair);
          $result_array[$fieldpair_array[0]] = $fieldpair_array[1];
       }
       if ((! isset($result_array['ssl_result'])) ||
           ($result_array['ssl_result'] != '0')) {
          if (isset($result_array['ssl_result_message']))
             $error_message = $result_array['ssl_result_message'];
          else if (isset($result_array['errorMessage']))
             $error_message = $result_array['errorMessage'];
          else $error_message = 'Unknown Virtual Merchant Error';
          log_activity('Virtual Merchant Declined: '.$error_message);
          $order->error = 'Card Declined';   return false;
       }
       $order->payment['payment_id'] = $result_array['ssl_txn_id'];
       $order->payment['payment_code'] = $result_array['ssl_approval_code'];
    }
    else {
       if (! file_exists('../admin/chase.token')) {
          $order->error = 'Chase Token File Missing';   return false;
       }
       $chase_token = file_get_contents('../admin/chase.token');
       $exp_date = $order->credit_card['month'].$order->credit_card['year'];
       $chase_socket = get_cart_config_value('chase_socket');
       $wsdl_url = CHASE_SERVICE_ENDPOINT.$chase_socket.'?wsdl';
       $chase_service_ws = new SoapClient($wsdl_url);
       $wsdl_url = CHASE_TRANS_ENDPOINT.$chase_socket.'?wsdl';
       $chase_trans_ws = new SoapClient($wsdl_url,array('trace'=>1));
       try {
          $result = $chase_service_ws->SignOnWithToken(array('identityToken'=>$chase_token));
       } catch (SoapFault $exception) {
          $order->error = $exception;
          $order->log_payment('Chase Paymentech Error: '.$order->error);
          log_activity('Chase Paymentech Error: '.$order->error);   return false;
       }
       $session_token = $result->SignOnWithTokenResult;

       $service_id = get_cart_config_value('chase_service_id');
       $application_id = get_cart_config_value('chase_app_id');
       $merchant_profile_id = get_cart_config_value('chase_merchant_profile_id');

       switch ($order->credit_card['type']) {
          case 'amex': $credit_card_type = 'AmericanExpress';   break;
          case 'visa': $credit_card_type = 'Visa';   break;
          case 'master': $credit_card_type = 'MasterCard';   break;
          case 'discover': $credit_card_type = 'Discover';   break;
       }
       $trans_object = new BankcardTransaction();
       $trans_object->TenderData = new BankcardTenderData();
       $trans_object->TenderData->CardData = new CardData();
       $trans_object->TenderData->CardData->CardType = $credit_card_type;
       $trans_object->TenderData->CardData->CardholderName = $order->credit_card['name'];
       $trans_object->TenderData->CardData->PAN = $order->credit_card['number'];
       $trans_object->TenderData->CardData->Expire = $exp_date;
       $trans_object->TenderData->CardSecurityData = new CardSecurityData();
       $trans_object->TenderData->CardSecurityData->AVSData = new AVSData();
       $trans_object->TenderData->CardSecurityData->AVSData->CardholderName =
          $order->credit_card['name'];
       $trans_object->TenderData->CardSecurityData->AVSData->Street =
          $order->customer->billing['address1'];
       $trans_object->TenderData->CardSecurityData->AVSData->City =
          $order->customer->billing['city'];
       $trans_object->TenderData->CardSecurityData->AVSData->StateProvince =
          $order->customer->billing['state'];
       $trans_object->TenderData->CardSecurityData->AVSData->PostalCode =
          $order->customer->billing['zipcode'];
       $trans_object->TenderData->CardSecurityData->AVSData->Country =
          cleanup_chase_country($order->billing_country);
       $trans_object->TenderData->CardSecurityData->AVSData->Phone =
          cleanup_chase_phone_number($order->customer->billing['phone']);
       $trans_object->TenderData->CardSecurityData->CVDataProvided = 'Provided';
       $trans_object->TenderData->CardSecurityData->CVData =
          $order->credit_card['cvv'];
       $trans_object->TransactionData = new BankcardTransactionData();
       $trans_object->TransactionData->Amount =
          number_format($order->payment['payment_amount'],2,'.','');
       $trans_object->TransactionData->CurrencyCode = $order->info['currency'];
       $trans_object->TransactionData->EntryMode = 'Keyed';
       $trans_object->TransactionData->IndustryType = 'Ecommerce';
       $trans_object->TransactionData->OrderNumber =
          str_replace('-','',$order->info['order_number']);

       $authorize_info = array();
       $authorize_info['transaction'] = new SoapVar($trans_object,
          SOAP_ENC_OBJECT,'BankcardTransaction',CHASE_TRANS_SCHEMA);
       $authorize_info['applicationProfileId'] = $application_id;
       $authorize_info['merchantProfileId'] = $merchant_profile_id;
       $authorize_info['serviceId'] = $service_id;
       $authorize_info['sessionToken'] = $session_token;
       if (function_exists('update_chase_data'))
          update_chase_data($order,$authorize_info,$chase_interface);
       $log_info = $authorize_info;
       $log_info['transaction'] = unserialize(serialize($trans_object));
       unset($log_info['transaction']->TenderData->CardData->PAN);
       unset($log_info['transaction']->TenderData->CardData->Expire);
       unset($log_info['transaction']->TenderData->CardSecurityData->CVData);
       unset($log_info['sessionToken']);
       $log_string = print_r($log_info,true);
       $log_string = str_replace("\n",'',$log_string);
       $log_string = str_replace('  ','',$log_string);
       $order->log_payment('Sent: '.$log_string);

       try {
          $result = $chase_trans_ws->AuthorizeAndCapture($authorize_info);
       } catch (SoapFault $exception) {
          $order->log_payment('Exception: '.$exception);
          $response = $chase_trans_ws->__getLastResponse();
          $order->log_payment('Response: '.$response);
          $start_pos = strpos($response,'<RuleMessage>');
          if ($start_pos !== false) {
             $start_pos += 13;
             $end_pos = strpos($response,'</RuleMessage>');
             $order->error = substr($response,$start_pos,$end_pos - $start_pos);
          }
          else {
             $start_pos = strpos($response,'<ProblemType>');
             if ($start_pos !== false) {
                $start_pos += 13;
                $end_pos = strpos($response,'</ProblemType>');
                $order->error = substr($response,$start_pos,$end_pos - $start_pos);
             }
             else $order->error = $exception;
          }
          $order->log_payment('Chase Paymentech Error: '.$order->error);
          log_activity('Chase Paymentech Error: '.$order->error);   return false;
       }
       $log_string = print_r($result->AuthorizeAndCaptureResult,true);
       $log_string = str_replace("\n",'',$log_string);
       $log_string = str_replace('  ','',$log_string);
       $order->log_payment('Response: '.$log_string);
       if ($result->AuthorizeAndCaptureResult->Status == 'Successful') {
          $order->payment['payment_id'] = $result->AuthorizeAndCaptureResult->TransactionId;
          $order->payment['payment_code'] = $result->AuthorizeAndCaptureResult->ApprovalCode;
       }
       else {
          $order->error = $result->AuthorizeAndCaptureResult->StatusMessage.' (' .
                          $result->AuthorizeAndCaptureResult->StatusCode.')';
          $order->log_payment('Chase Paymentech Error: '.$order->error);
          log_activity('Chase Paymentech Error: '.$order->error);   return false;
       }
    }
    $order->payment['payment_status'] = PAYMENT_CAPTURED;

    log_activity('Chase Payment Accepted with Transaction ID #' .
                 $order->payment['payment_id'].' and Approval Code ' .
                 $order->payment['payment_code']);
    return true;
}

if ($chase_setup) {
   function setup_chase()
   {
       print "<h3>Chase Paymentech Setup</h3>\n";
       $db = new DB;
       $chase_socket = get_cart_config_value('chase_socket',$db);
       $wsdl_url = CHASE_SERVICE_ENDPOINT.$chase_socket.'?wsdl';
       if (! file_exists('../admin/chase.token')) {
          print "Chase Token File Missing<br>\n";   return;
       }
       $chase_token = file_get_contents('../admin/chase.token');
       $chase_ws = new SoapClient($wsdl_url,array('trace'=>1));
       try {
          $result = $chase_ws->SignOnWithToken(array('identityToken'=>$chase_token));
       } catch (SoapFault $exception) {
          print 'Chase Paymentech Error: '.$exception."<br>\n";   return;
       }
       $session_token = $result->SignOnWithTokenResult;

       try {
          $result = $chase_ws->GetServiceInformation(array('sessionToken'=>$session_token));
       } catch (SoapFault $exception) {
          print 'Chase Paymentech Error: '.$exception."<br>\n";   return;
       }
       $service_id = $result->GetServiceInformationResult->BankcardServices->BankcardService->ServiceId;
       print 'Service ID = '.$service_id."<br>\n";

       $application_data = array();
       $application_data['ApplicationAttended'] = false;
       $application_data['ApplicationLocation'] = 'HomeInternet';
       $application_data['ApplicationName'] = 'Inroads Shopping Cart';
       $application_data['HardwareType'] = 'PC';
       $application_data['PINCapability'] = 'PINNotSupported';
       $application_data['PTLSSocketId'] = get_form_field('socket_id');
       $application_data['ReadCapability'] = 'NoMSR';
       $application_data['SerialNumber'] = get_form_field('serial_number');
       $application_data['SoftwareVersion'] = '1.0';
       $application_data['SoftwareVersionDate'] = date('Y-m-d').'T'.date('H:i:sP');
       try {
          $result = $chase_ws->SaveApplicationData(array('sessionToken'=>$session_token,
                                                         'applicationData'=>$application_data));
       } catch (SoapFault $exception) {
          print 'Chase Paymentech Error: '.$exception."<br>\n";   return;
       }
       $application_id = $result->SaveApplicationDataResult;
       print 'Application Profile ID = '.$application_id."<br>\n";

       $address_info = array();
       $address_info['Street1'] = get_form_field('street1');
       $address_info['Street2'] = '';
       $address_info['City'] = get_form_field('city');
       $address_info['StateProvince'] = get_form_field('state');
       $address_info['PostalCode'] = get_form_field('zipcode');
       $address_info['CountryCode'] = get_form_field('country');

       $bankcard_merchant_data = array();
       $bankcard_merchant_data['SIC'] = get_form_field('sic');
       $bankcard_merchant_data['TerminalId'] = get_form_field('terminal_id');

       $merchant_data = array();
       $merchant_data['Address'] = $address_info;
       $merchant_data['CustomerServicePhone'] = get_form_field('phone');
       $merchant_data['Language'] = 'ENG';
       $merchant_data['MerchantId'] = get_form_field('merchant_id');
       $merchant_data['Name'] = get_form_field('company_name');
       $merchant_data['Phone'] = $merchant_data['CustomerServicePhone'];
       $merchant_data['BankcardMerchantData'] = $bankcard_merchant_data;

       $bankcard_transaction_data_defaults = array();
       $bankcard_transaction_data_defaults['CurrencyCode'] = get_form_field('currency');
       $bankcard_transaction_data_defaults['CustomerPresent'] = 'Ecommerce';

       $transaction_data = array();
       $transaction_data['BankcardTransactionDataDefaults'] = $bankcard_transaction_data_defaults;

       $merchant_profile = array();
       $merchant_profile['ProfileId'] = get_form_field('merchant_profile_id');
       $merchant_profile['LastUpdated'] = date('Y-m-d').'T'.date('H:i:sP');
       $merchant_profile['MerchantData'] = $merchant_data;
       $merchant_profile['TransactionData'] = $transaction_data;
       try {
          $result = $chase_ws->SaveMerchantProfiles(array('sessionToken'=>$session_token,
                                                          'serviceId'=>$service_id,
                                                          'tenderType'=>'Credit',
                                                          'merchantProfiles'=>array($merchant_profile)));
       } catch (SoapFault $exception) {
          print 'Chase Paymentech Error: '.$exception."<br>\n";
          print "<pre>\n";
          print "Request :\n".htmlspecialchars($chase_ws->__getLastRequest()) ."\n";
          print "Response:\n".htmlspecialchars($chase_ws->__getLastResponse())."\n";
          print '</pre>'; 
          return;
       }
       print "Account Setup Complete<br>\n";
   }

   function display_chase_setup()
   {
?>
<html>
  <head>
    <title>Chase Paymentech Account Setup</title>
  </head>
  <body>
    <h1 align="center">Chase Paymentech Account Setup</h1>
    <form method="POST" action="chase.php" name="ChaseSetup">
    <input type="hidden" name="cmd" value="setupchase">
    <table border="0" cellpadding="2" cellspacing="0" align="center">
      <tr valign=bottom><td>Application Serial #:</td><td>
        <input type=text name="serial_number" size=30 value=""></td></tr>
      <tr valign=bottom><td>Merchant Profile ID:</td><td>
        <input type=text name="merchant_profile_id" size=30 value=""></td></tr>
      <tr valign=bottom><td>Merchant ID:</td><td>
        <input type=text name="merchant_id" size=30 value=""></td></tr>
      <tr valign=bottom><td>Company Name:</td><td>
        <input type=text name="company_name" size=30 value=""></td></tr>
      <tr valign=bottom><td>Street:</td><td>
        <input type=text name="street1" size=30 value=""></td></tr>
      <tr valign=bottom><td>City:</td><td>
        <input type=text name="city" size=30 value=""></td></tr>
      <tr valign=bottom><td>State:</td><td>
        <input type=text name="state" size=30 value=""></td></tr>
      <tr valign=bottom><td>PostalCode:</td><td>
        <input type=text name="zipcode" size=30 value=""></td></tr>
      <tr valign=bottom><td>Country Code:</td><td>
        <input type=text name="country" size=30 value=""></td></tr>
      <tr valign=bottom><td>Phone #:</td><td>
        <input type=text name="phone" size=30 value=""></td></tr>
      <tr valign=bottom><td>SIC:</td><td>
        <input type=text name="sic" size=30 value=""></td></tr>
      <tr valign=bottom><td>Terminal Id:</td><td>
        <input type=text name="terminal_id" size=30 value=""></td></tr>
      <tr valign=bottom><td>Currency:</td><td>
        <input type=text name="currency" size=30 value=""></td></tr>
      <tr valign=bottom><td>PTLS Socket Id:</td><td>
         <textarea name="socket_id" rows=20 cols=80 wrap=soft></textarea></td></tr>
    </table>
    <p align="center"><input type="submit" name="Submit" value="Submit">
    </form>
  </body>
</html>
<?
   }

   if (! check_login_cookie()) exit;
   $cmd = get_form_field('cmd');
   if ($cmd == 'setupchase') setup_chase();
   else display_chase_setup();
   DB::close_all();
}

?>
