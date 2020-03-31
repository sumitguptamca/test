<?php
/*
           Inroads Shopping Cart - First Data Global Gateway e4 API Module

                         Written 2013-2018 by Randall Severy
                          Copyright 2013-2018 Inroads, LLC
*/

define('USE_SOAP',false);

define('FIRSTDATA_WSDL_URL','/transaction/v12/wsdl');
define('FIRSTDATA_SERVICE_ENDPOINT','/transaction/v12');

function firstdata_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'First Data Global Gateway','firstdata',$values);

    $dialog->add_edit_row('Gateway ID:','firstdata_gateway',$values,30);
    $dialog->add_edit_row('Terminal Password:','firstdata_password',$values,50);
    $dialog->add_edit_row('HMAC Key:','firstdata_hmac_key',$values,50);
    $dialog->add_edit_row('Key ID:','firstdata_key_id',$values,30);
    $dialog->add_edit_row('Hostname:','firstdata_hostname',$values,50);
    $dialog->start_row('Transaction Type:','middle');
    $firstdata_type = get_row_value($values,'firstdata_type');
    if (! $firstdata_type) $firstdata_type = '00';
    $dialog->add_radio_field('firstdata_type','00',
       'Authorize and Capture',$firstdata_type == '00');
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('firstdata_type','01',
       'Authorize Only',$firstdata_type == '01');
    $dialog->end_row();
    if (! empty($enable_saved_cards)) {
       $dialog->start_row('Enable Saved Credit Cards:','middle');
       $dialog->add_checkbox_field('firstdata_saved_cards','',$values);
       $dialog->end_row();
    }
}

function firstdata_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('firstdata_gateway','firstdata_password',
       'firstdata_hostname','firstdata_hmac_key','firstdata_key_id',
       'firstdata_type','firstdata_saved_cards');
    if (! empty($website_settings)) $fields[] = 'firstdata_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function firstdata_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'firstdata_saved_cards') {
       if (get_form_field('firstdata_saved_cards') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'firstdata_active') {
       if (get_form_field('firstdata_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function firstdata_active($db)
{
    return get_cart_config_value('firstdata_active',$db);
}

function firstdata_get_primary_module($db)
{
    return 'firstdata';
}

function firstdata_saved_cards_enabled($db)
{
    $saved_flag = get_cart_config_value('firstdata_saved_cards',$db);
    if ($saved_flag == '1') return true;
    return false;
}

class SoapClientHMAC extends SoapClient
{
    public function __doRequest($request,$location,$action,$version,
                                $one_way=NULL)
    {
       global $soap_context,$firstdata_hmac_key,$firstdata_key_id;

       $hmackey = $firstdata_hmac_key;
       $keyid = $firstdata_key_id;
       $hashtime = date('c');
       $hashstr = "POST\ntext/xml; charset=utf-8\n".sha1($request)."\n" .
                  $hashtime."\n".parse_url($location,PHP_URL_PATH);
       $authstr = base64_encode(hash_hmac('sha1',$hashstr,$hmackey,TRUE));
       if (version_compare(PHP_VERSION, '5.3.11') == -1)
          ini_set('user_agent','PHP-SOAP/'.PHP_VERSION."\r\nAuthorization: GGE4_API " .
                  $keyid.":".$authstr."\r\nx-gge4-date: ".$hashtime .
                  "\r\nx-gge4-content-sha1: ".sha1($request));
       else stream_context_set_option($soap_context,array('http'=>
                array('header'=>'authorization: GGE4_API '.$keyid.':'.$authstr .
                      "\r\nx-gge4-date: ".$hashtime."\r\nx-gge4-content-sha1: " .
                      sha1($request))));
       return parent::__doRequest($request,$location,$action,$version,$one_way);
    }
  
    public function SoapClientHMAC($wsdl,$options=NULL)
    {
       global $soap_context;

       $soap_context = stream_context_create();
       $options['stream_context'] = $soap_context;
       return parent::SoapClient($wsdl,$options);
    }
}

function call_firstdata_api($url,$post_data,&$error)
{
    global $firstdata_hmac_key,$firstdata_key_id;

    $content = json_encode($post_data);
    $method = 'POST';
    $content_type = 'application/json';
    $content_digest = sha1($content);
    $gge4Date = strftime('%Y-%m-%dT%H:%M:%S',time() -
                         (int) substr(date('O'),0,3)*60*60).'Z';
    $url_info = parse_url($url);
    if (isset($url_info['path'])) $path = $url_info['path'];
    else $path = '/';
    if (isset($url_info['query'])) $path .= '?'.$url_info['query'];
    if (isset($url_info['fragment'])) $path .= '#'.$url_info['fragment'];
    $hashstr = $method."\n".$content_type."\n".$content_digest."\n" .
               $gge4Date."\n".$path;
    $authstr = 'GGE4_API '.$firstdata_key_id.':' .
               base64_encode(hash_hmac('sha1',$hashstr,
                                       $firstdata_hmac_key,true));

    require_once '../engine/http.php';
    $http = new HTTP($url);
    $headers = array('X-GGe4-Content-SHA1: '.$content_digest,
                     'X-GGe4-Date: '.$gge4Date,
                     'Authorization: '.$authstr,
                     'Accept: '.$content_type);
    $http->set_headers($headers);
    $http->set_content_type($content_type);
    $http->set_charset(null);
    $response = $http->call($content);
    if (! $response) {
       $error = 'First Data Error: '.$http->error.' ('.$http->status.')';
       log_error($error);
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return null;
    }

    $response_data = json_decode($response);
    if (! $response_data) {
       $error = 'First Data Error: '.$response;   log_error($error);
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return null;
    }
    return $response_data;
}

function call_firstdata($post_data,&$error)
{
    global $firstdata_hmac_key,$firstdata_key_id;

    $gateway_id = get_cart_config_value('firstdata_gateway');
    $password = get_cart_config_value('firstdata_password');
    $hostname = get_cart_config_value('firstdata_hostname');
    $firstdata_hmac_key = get_cart_config_value('firstdata_hmac_key');
    $firstdata_key_id = get_cart_config_value('firstdata_key_id');
    if (USE_SOAP) $firstdata_url = 'https://'.$hostname.FIRSTDATA_WSDL_URL;
    else $firstdata_url = 'https://'.$hostname.FIRSTDATA_SERVICE_ENDPOINT;
    $auth_data = array(
       'gateway_id' => $gateway_id,
       'password' => $password,
    );
    $post_data = array_merge($auth_data,$post_data);
    if ((! empty($post_data['cc_number'])) &&
        ($post_data['cc_number'] == '4111111111111111')) {
       $post_data['testMode'] = true;
       $post_data['amount'] = .01;
    }

    $log_string = '';
    foreach ($post_data as $key => $value) {
       if (($key == 'gateway_id') || ($key == 'password') ||
           ($key == 'cc_number') || ($key == 'cc_expiry') ||
           ($key == 'cc_verification_str2')) continue;
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }
    if (class_exists('Order')) @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    if (USE_SOAP) {
       $client = new SoapClientHMAC($firstdata_url);
       try {
          $response_data = $client->SendAndCommit($post_data);
       } catch (SoapFault $exception) {
          $response = $client->__getLastResponse();
       }
    }
    else {
       $response_data = call_firstdata_api($firstdata_url,$post_data,$error);
       if (! $response_data) return null;
    }

    $response_string = str_replace("\n",' ',print_r($response_data,true));
    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_string);
    else log_payment('Response: '.$response_string);
    return $response_data;
}

function firstdata_process_payment(&$order)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($order->db);
    $transaction_type = get_cart_config_value('firstdata_type');
    if (! $transaction_type) $transaction_type = '00';
    $address = $order->customer->billing['address1'].'|' .
       $order->customer->billing['zipcode'].'|' .
       $order->customer->billing['city'].'|' .
       $order->customer->get('bill_state').'|' .
       $order->get('bill_country_name');
    if (isset($order->cart)) $reference_no = $order->cart->id;
    else if (isset($order->id)) $reference_no = $order->id;
    else $reference_no = time();
    $post_data = array(
       'transaction_type' => $transaction_type,
       'amount' => $order->payment['payment_amount'],
       'cc_verification_str1' => $address,
       'cvd_presence_ind' => '1',
       'reference_no' => $reference_no,
       'zip_code' => $order->customer->billing['zipcode'],
       'tax1_amount' => $order->info['tax'],
       'customer_ref' => $order->customer->info['id'],
       'language' => 'en',
       'client_ip' => $_SERVER['REMOTE_ADDR'],
       'client_email' => $order->customer->info['email'],
       'currency_code' => $order->info['currency'],
       'partial_redemption' => false,
       'ecommerce_flag' => '7'
    );
    if (isset($order->saved_card)) {
       $query = 'select * from saved_cards where profile_id=?';
       $query = $order->db->prepare_query($query,$order->saved_card);
       $card_info = $order->db->get_record($query);
       if (! $card_info) {
          if (isset($order->db->error)) $order->error = $order->db->error;
          else $order->error = 'Saved Credit Card not found';
          return false;
       }
       $exp_date = $card_info['card_month'].$card_info['card_year'];
       $post_data['cc_expiry'] = $exp_date;
       $post_data['cardholder_name'] = $card_info['card_name'];
       $post_data['transarmor_token'] = $order->saved_card;
       $post_data['credit_card_type'] = $card_info['card_type'];
    }
    else {
       $exp_date = $order->credit_card['month'].$order->credit_card['year'];
       $post_data['cc_number'] = $order->credit_card['number'];
       $post_data['cc_expiry'] = $exp_date;
       $post_data['cardholder_name'] = $order->credit_card['name'];
       $post_data['cc_verification_str2'] = $order->credit_card['cvv'];
    }

    $response_data = call_firstdata($post_data,$error);
    if (! $response_data) {
       $order->error = $error;   return false;
    }

    if ((! isset($response_data->transaction_approved)) ||
        ($response_data->transaction_approved != 1)) {
       if (isset($response_data->error_description))
          $error_msg = $response_data->error_description;
       else if (isset($response_data->bank_message))
          $error_msg = $response_data->bank_message;
       else $error_msg = json_encode($response_data);
       log_error('First Data Declined: '.$error_msg);
       $order->error = 'Card Declined';
       return false;
    }
    $order->payment['payment_id'] = $response_data->transaction_tag;
    $order->payment['payment_code'] = $response_data->authorization_num;
    $order->payment['payment_data'] = json_encode($response_data);
    if ($transaction_type == '01') {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('First Data Global Gateway e4 Authorization Accepted ' .
                    'with Transaction ID #'.$order->payment['payment_id'] .
                    ' and Authorization Code '.$order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('First Data Global Gateway e4 Payment Accepted with ' .
                    'Transaction ID #'.$order->payment['payment_id'] .
                    ' and Authorization Code '.$order->payment['payment_code']);
    }

    return true;
}

function firstdata_capture_payment($db,$payment_info,&$error)
{
    global $cart_config_values;

    $order_id = $payment_info['parent'];

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $payment_id = $payment_info['payment_id'];
    $payment_code = $payment_info['payment_code'];
    $post_data = array(
       'transaction_type' => '32',
       'transaction_tag' => $payment_id,
       'authorization_num' => $payment_code,
       'amount' => $payment_info['payment_amount'],
       'reference_no' => $payment_info['parent']
    );

    $response_data = call_firstdata($post_data,$error);
    if (! $response_data) return false;

    if ((! isset($response_data->transaction_approved)) ||
        ($response_data->transaction_approved != 1)) {
       if (isset($response_data->error_description))
          $error_msg = $response_data->error_description;
       else if (isset($response_data->bank_message))
          $error_msg = $response_data->bank_message;
       else $error_msg = $response_string;
       log_error('First Data Capture Error: '.$error_msg);
       return false;
    }

    $capture_id = $response_data->transaction_tag;
    $payment_code = $response_data->authorization_num;

    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_id']['value'] = $capture_id;
    $payment_record['payment_code']['value'] = $payment_code;
    $payment_record['payment_data']['value'] = json_encode($response_data);
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured First Data Global Gateway e4 Transaction #' .
                 $payment_id.' with Transaction ID #'.$capture_id .
                 ' and Authorization Code '.$payment_code.' for Order #' .
                 $order_id);
    return true;
}

function firstdata_cancel_payment($db,$payment_info,$refund_amount,
                                  &$cancel_info,&$error)
{
    global $cart_config_values;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    $order_id = $payment_info['parent'];
    $payment_status = $payment_info['payment_status'];
    if ($payment_status == PAYMENT_AUTHORIZED) {
       $transaction_type = '33';   $description = 'Void';
    }
    else {
       $transaction_type = '34';   $description = 'Refund';
    }
    $description .= ' for Order #'.$order_id;
    $payment_id = $payment_info['payment_id'];
    $payment_code = $payment_info['payment_code'];
    $post_data = array(
       'transaction_type' => $transaction_type,
       'transaction_tag' => $payment_id,
       'authorization_num' => $payment_code,
       'amount' => $refund_amount
    );

    $response_data = call_firstdata($post_data,$error);
    if (! $response_data) return false;

    if ((! isset($response_data->transaction_approved)) ||
        ($response_data->transaction_approved != 1)) {
       if (isset($response_data->error_description))
          $error_msg = $response_data->error_description;
       else if (isset($response_data->bank_message))
          $error_msg = $response_data->bank_message;
       else $error_msg = $response_string;
       log_error('First Data Void Error: '.$error_msg);
       return false;
    }
    $cancel_info['payment_id'] = $response_data->transaction_tag;
    $cancel_info['payment_code'] = $response_data->authorization_num;
    $cancel_info['payment_data'] = json_encode($response_data);

    if ($transaction_type == '33')
       log_activity('Voided First Data Global Gateway e4 Transaction #' .
                    $payment_id.' for Order #'.$order_id);
    else log_activity('Refunded First Data Global Gateway e4 Transaction #' .
                      $payment_id.' for Order #'.$order_id);
    return true;
}

function firstdata_create_saved_profile($db,$customer_id,&$error)
{
/*  First Data doesn't use Profiles, so use Customer ID as the Profile ID */
    return $customer_id;
}

function firstdata_delete_saved_profile($db,$profile_id,&$error)
{
/*  First Data doesn't use Profiles, so just pretend to delete the profile */
    return true;
}

function firstdata_create_saved_card($db,$profile_id,$payment_info,&$error)
{
    global $cart_config_values,$default_currency;

    if (! isset($cart_config_values)) load_cart_config_values($db);
    if (! isset($default_currency)) $default_currency = 'USD';
    $query = 'select b.*,c.country from billing_information b join ' .
             'countries c on c.id=b.country where b.parent=?';
    $query = $db->prepare_query($query,$profile_id);
    $billing = $db->get_record($query);
    if (! $billing) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Customer Not Found';
       return null;
    }
    $exp_date = $payment_info['card_month'].$payment_info['card_year'];
    $address = $billing['address1'].'|'.$billing['zipcode'].'|' .
       $billing['city'].'|'.$billing['state'].'|'.$billing['country'];
    $post_data = array(
       'transaction_type' => '05',
       'amount' => 0,
       'cc_number' => $payment_info['card_number'],
       'cc_expiry' => $exp_date,
       'cardholder_name' => $payment_info['card_name'],
       'cc_verification_str1' => $address,
       'cc_verification_str2' => $payment_info['card_cvv'],
       'cvd_presence_ind' => '1',
       'reference_no' => $profile_id,
       'zip_code' => $billing['zipcode'],
       'customer_ref' => $profile_id,
       'language' => 'en',
       'client_ip' => $_SERVER['REMOTE_ADDR'],
       'currency_code' => $default_currency,
       'partial_redemption' => false,
       'ecommerce_flag' => '7'
    );


    $response_data = call_firstdata($post_data,$error);
    if (! $response_data) return null;

    if ((! isset($response_data->transaction_approved)) ||
        ($response_data->transaction_approved != 1)) {
       if (isset($response_data->error_description))
          $error_msg = $response_data->error_description;
       else if (isset($response_data->bank_message))
          $error_msg = $response_data->bank_message;
       else $error_msg = $response_string;
       $error = 'First Data Error: '.$error_msg;
       log_error($error);   return false;
    }
    if (isset($response_data->transarmor_token))
       $payment_id = $response_data->transarmor_token;
    else {
       $error = 'Missing TransArmor Token in Response';
       $payment_id = null;
    }
    return $payment_id;
}

function firstdata_update_saved_card($db,&$profile_id,$payment_id,
                                     $payment_info,&$error)
{
    $profile_id = create_saved_card($db,$profile_id,$payment_info,$error);
    if ($profile_id) return true;
    else return false;
}

function firstdata_delete_saved_card($db,$profile_id,$payment_id,&$error)
{
/*  First Data doesn't support deleting saved cards, so just pretend to
    delete the saved card */
    return true;
}

?>
