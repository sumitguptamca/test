<?php
/*
                 Inroads Shopping Cart - IBIS (First Data) API Module

                        Written 2014-2018 by Randall Severy
                         Copyright 2014-2018 Inroads, LLC
*/

if (! defined('CURL_SSLVERSION_TLSv1')) define('CURL_SSLVERSION_TLSv1',1);

if (isset($argc) && ($argc > 1)) {
   require_once '../engine/db.php';
   require_once 'cart-public.php';
}
require_once 'cartconfig-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

global $ibis_currencies;
$ibis_currencies = array(978=>'EUR',840=>'USD',941=>'RSD',703=>'SKK',440=>'LTL',
                         233=>'EEK',643=>'RUB',891=>'YUM',348=>'HUF');

function ibis_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'IBIS (First Data)','ibis',$values);

    $dialog->add_edit_row('Key File:','ibis_keyfile',$values,50);
    $dialog->add_edit_row('Key Password:','ibis_password',$values,30);
    $dialog->start_row('Currency:','middle');
    $ibis_currency = get_row_value($values,'ibis_currency');
    $dialog->start_choicelist('ibis_currency');
    foreach ($ibis_currencies as $curr_code => $curr_label)
       $dialog->add_list_item($curr_code,$curr_label,
                              ($curr_code == $ibis_currency));
    $dialog->end_choicelist();
    $dialog->end_row();
    $ibis_test = get_row_value($values,'ibis_test');
    $dialog->start_row('Mode:','middle');
    $dialog->add_radio_field('ibis_test','1','Production',$ibis_test == 1);
    $dialog->add_radio_field('ibis_test','0','Test',$ibis_test == 0);
    $dialog->end_row();
}

function ibis_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('ibis_keyfile','ibis_password','ibis_currency','ibis_test');
    if (! empty($website_settings)) $fields[] = 'ibis_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function ibis_payment_update_cart_config_field($field_name,
                                               &$new_field_value,$db)
{
    if ($field_name == 'ibis_active') {
       if (get_form_field('ibis_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function ibis_active($db)
{
    return get_cart_config_value('ibis_active',$db);
}

function ibis_get_primary_module($db)
{
    return 'ibis';
}

class Merchant {
   var $url;
   var $client_url;
   var $keystore;
   var $keystorepassword;
   var $verbose;

function Merchant($keystore,$keystorepassword,$verbose=false)
{
    $ibis_test = get_cart_config_value('ibis_test');
    if ($ibis_test == 1) {
       $this->url = 'https://secureshop.firstdata.lv:8443/ecomm/MerchantHandler';
       $this->client_url = 'https://secureshop.firstdata.lv/ecomm/ClientHandler';
    }
    else {
       $this->url = 'https://secureshop-test.firstdata.lv:8443/ecomm/MerchantHandler';
       $this->client_url = 'https://secureshop-test.firstdata.lv/ecomm/ClientHandler';
    }
    $this->keystore = $keystore;
    $this->keystorepassword = $keystorepassword;
    $this->verbose = $verbose;
}

function sentPost($params)
{
    if (! file_exists($this->keystore)) {
       $result = 'file ' . $this->keystore . ' not exists';
       error_log($result);
       return $result;
    }
    if (! is_readable($this->keystore)) {
       $result = "Please check CHMOD for file \"" . $this->keystore . "\"! It must be readable!";
       error_log($result);
       return $result;
    }
    $post = '';
    foreach ($params as $key => $value) {
       if ($post) $post .= '&';
       $post .= $key.'='.$value;
    }
    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$post);
    else log_payment('Sent: '.$post);
    $curl = curl_init();
    if ($this->verbose) curl_setopt($curl, CURLOPT_VERBOSE, TRUE);

    curl_setopt($curl,CURLOPT_URL,$this->url);
    curl_setopt($curl,CURLOPT_HEADER,0);
    curl_setopt($curl,CURLOPT_POST,true);
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,true);
    //curl_setopt($curl,CURLOPT_SSLVERSION,2);
    curl_setopt($curl,CURLOPT_SSLVERSION,CURL_SSLVERSION_TLSv1);
    curl_setopt($curl,CURLOPT_SSLCERT,$this->keystore);
    if (file_exists(dirname($this->keystore).'/cacert.pem'))
       curl_setopt($curl,CURLOPT_CAINFO,dirname($this->keystore).'/cacert.pem');
    else curl_setopt($curl,CURLOPT_CAINFO,$this->keystore);
    curl_setopt($curl,CURLOPT_SSLKEYPASSWD,$this->keystorepassword);
    curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    $result = curl_exec($curl);

    if (curl_error($curl)) {
       $result = curl_error($curl);
       error_log($result);
    }
    curl_close($curl);
    if (class_exists('Order'))
       @Order::log_payment('Response: '.str_replace("\n",'; ',trim($result)));
    else log_payment('Response: '.str_replace("\n",'; ',trim($result)));
    return $result;
}

function startSMSTrans($amount,$currency,$ip,$desc,$language)
{
    $params = array(
       'command' => 'v',
       'amount'  => $amount,
       'currency'=> $currency,
       'client_ip_addr'      => $ip,
       'description'    => $desc,
       'language'=> $language     
    );
    return $this->sentPost($params);
}

function startDMSAuth($amount,$currency,$ip,$desc,$language)
{
    $params = array(
       'command' => 'a',
       'msg_type'=> 'DMS',
       'amount'  => $amount,
       'currency'=> $currency,
       'client_ip_addr'      => $ip,
       'description'    => $desc,
       'language'    => $language,  
    );
    return $this->sentPost($params);
}

function makeDMSTrans($auth_id,$amount,$currency,$ip,$desc,$language)
{
    $params = array(
       'command' => 't',
       'msg_type'=> 'DMS',
       'trans_id' => $auth_id, 
       'amount'  => $amount,
       'currency'=> $currency,
       'client_ip_addr' => $ip   
    );
    return $this->sentPost($params);
}

function getTransResult($trans_id,$ip)
{
    $params = array(
       'command' => 'c',
       'trans_id' => $trans_id, 
       'client_ip_addr'      => $ip
    );
    return $this->sentPost($params);
}

function reverse($trans_id,$amount)
{
    $params = array(
       'command' => 'r',
       'trans_id' => $trans_id, 
       'amount'      => $amount
    );
    return $this->sentPost($params);
}

function closeDay()
{
    $params = array(
       'command' => 'b',
    );
    return $this->sentPost($params);
}

}

function process_ibis_return(&$order)
{
    global $cart_cookie;

    if ((! isset($_COOKIE[$cart_cookie])) ||
        (! is_numeric($_COOKIE[$cart_cookie]))) {
       log_error('Invalid Cart Cookie returned from IBIS');
       $order->error = 'Invalid Cart';   return false;
    }
    $cart_id = $_COOKIE[$cart_cookie];

    $query = 'select cart_data from cart where id=?';
    $query = $order->db->prepare_query($query,$cart_id);
    $row = $order->db->get_record($query);
    if ((! $row) || (! $row['cart_data'])) {
       log_error('Cart Not Found for Cart ID ('.$cart_id.') from IBIS');
       $order->error = 'Invalid Cart';   return false;
    }

    $order->ibis_trans_id = get_form_field('trans_id');
    if (! $order->ibis_trans_id) {
       log_activity('IBIS Unknown Return');
       $order->error = 'Unknown Payment Return';   return false;
    }
    $order->ibis_confirm = get_form_field('Ucaf_Cardholder_Confirm');

    $_POST = unserialize($row['cart_data']);
    $order->ibis_return = true;
    return true;
}

function complete_ibis_payment(&$order)
{
    $ibis_keyfile = get_cart_config_value('ibis_keyfile');
    $ibis_password = get_cart_config_value('ibis_password');
    $ip = $_SERVER['REMOTE_ADDR'];
    $merchant = new Merchant($ibis_keyfile,$ibis_password);
    $response = $merchant->getTransResult(urlencode($order->ibis_trans_id),$ip);
    $response = trim($response);
    $response_data = explode("\n",$response);
    $response_info = array();
    foreach ($response_data as $response_line) {
       $line_array = explode(':',$response_line);
       if (count($line_array) != 2) continue;
       $response_info[trim($line_array[0])] = trim($line_array[1]);
    }
    if ($response_info['RESULT'] != 'OK') {
       $error_msg = $response_info['RESULT'];
       if (isset($response_info['RESULT_CODE']))
          $error_msg .= ' ('.$response_info['RESULT_CODE'].')';
       log_activity('IBIS Declined: '.$error_msg);
       $order->error = 'Card Declined: '.$error_msg;   return false;
    }

    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $order->ibis_trans_id;
    $order->payment['payment_code'] = $response_info['APPROVAL_CODE'];
    $order->payment['payment_ref'] = $response_info['RRN'];
    $order->credit_card['number'] = $response_info['CARD_NUMBER'];
    log_activity('IBIS Payment Accepted with Approval Code ' .
                 $order->payment['payment_code'].' and Reference Number ' .
                 $order->payment['payment_ref']);
    return true;
}

function ibis_process_payment(&$order)
{
    global $ibis_currencies;

    if (isset($order->ibis_return)) return complete_ibis_payment($order);

    $ibis_keyfile = get_cart_config_value('ibis_keyfile');
    $ibis_password = get_cart_config_value('ibis_password');
    if (function_exists('get_ibis_currency'))
       $ibis_currency = get_ibis_currency($order);
    else $ibis_currency = get_cart_config_value('ibis_currency');
    if (function_exists('get_ibis_language'))
       $ibis_language = get_ibis_language($order);
    else $ibis_language = 'en';
    $ip = $_SERVER['REMOTE_ADDR'];
    $site_name = get_cart_config_value('companyname');
    $description = $site_name.' Shopping Cart Order';
    $merchant = new Merchant($ibis_keyfile,$ibis_password);
    if ($order->info['currency'] != $ibis_currencies[$ibis_currency]) {
       $exchange_rate = get_exchange_rate($order->info['currency'],
                                          $ibis_currencies[$ibis_currency]);
       $payment_amount = round($order->payment['payment_amount'] *
                               $exchange_rate,2);
    }
    else $payment_amount = $order->payment['payment_amount'];
    if ($ibis_currency == 840) $amount = $payment_amount * 100;
    else $amount = round($payment_amount) * 100;
    $response = $merchant->startSMSTrans($amount,$ibis_currency,$ip,
                   urlencode($description),$ibis_language);
    $response = trim($response);
    if (substr($response,0,14) == 'TRANSACTION_ID') {
       $trans_id = substr($response,16,28);
       $cart_data = serialize($_POST);
       $query = 'update cart set cart_data=? where id=?';
       $query = $order->db->prepare_query($query,$cart_data,$order->cart->id);
       $order->db->log_query($query);
       if (! $order->db->query($query)) {
          $order->error = $order->db->error;   return false;
       }
       $url = $merchant->client_url.'?trans_id='.urlencode($trans_id);
       header('Location: '.$url);   exit;
    }
    else if (substr($response,0,7) == 'error: ') {
       $error_msg = substr($response,7);
       log_activity('IBIS Declined: '.$error_msg);
       $order->error = $error_msg;   return false;
    }
    log_activity('IBIS Unknown Error: '.$response);
    $order->error = 'Unknown Payment Error: '.$response;
    return false;
}

function ibis_cancel_payment($db,$payment_info,$refund_amount,&$cancel_info,
                             &$error)
{
    $ibis_keyfile = get_cart_config_value('ibis_keyfile');
    $ibis_password = get_cart_config_value('ibis_password');
    $trans_id = $payment_info['payment_id'];
    $payment_amount = $refund_amount * 100;
    $merchant = new Merchant($ibis_keyfile,$ibis_password);
    $response = trim($merchant->reverse(urlencode($trans_id),$payment_amount));
    if (substr($response,0,10) == 'RESULT: OK') return true;
    else if (substr($response,0,7) == 'error: ') {
       $error_msg = substr($response,7);
       $error = 'IBIS Reverse Error: '.$error_msg;
    }
    else $error = 'IBIS Unknown Reverse Error: '.$response;
    return false;
}

function close_ibis_day()
{
    $ibis_keyfile = get_cart_config_value('ibis_keyfile');
    $ibis_password = get_cart_config_value('ibis_password');
    $merchant = new Merchant($ibis_keyfile,$ibis_password);
    $response = trim($merchant->closeDay());
    if (substr($response,0,10) != 'RESULT: OK')
       print 'IBIS Close Day Error = '.$response."\n";
}

function ibis_setup_process_order(&$order)
{
    if (($order->payment_module == 'ibis') &&
        (getenv('PATH_INFO') == '/ibis')) {
       if (! process_ibis_return($order)) {
          require 'checkout.php';   exit;
       }
       $guest_checkout = get_form_field('GuestCheckout');
       if ($guest_checkout) {
          $order->customer_id = 0;
          set_remote_user(get_form_field('cust_email'));
          $order->customer->parse();
          $order->customer->setup_guest();
       }
    }
}

function ibis_process_order_button(&$order)
{
    return true;
}

if (isset($argc) && ($argc == 2) && ($argv[1] == 'closeday')) {
   close_ibis_day();   DB::close_all();   exit(0);
}

?>
