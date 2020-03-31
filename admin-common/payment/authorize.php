<?php
/*
                 Inroads Shopping Cart - Authorize.Net API Module

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

// define('CARDKNOX_IFIELDS_VERSION','2.4.1812.1101');

global $log_cart_errors_enabled;
if (! function_exists('log_cart_error')) $log_cart_errors_enabled = false;

function authorize_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'Authorize.Net','authorize',$values);

    $dialog->add_edit_row('API Login ID:','authorize_username',$values,30);
    $dialog->add_edit_row('Transaction Key:','authorize_key',$values,30);
    $dialog->add_edit_row('Hostname/URL:','authorize_hostname',$values,50);
/*
    $dialog->add_edit_row('Cardknox iFields Key:','authorize_ifields_key',
                          $values,50);
*/
    $dialog->add_edit_row('Transaction Mode:','authorize_mode',$values,10);
    if (empty($values['authorize_type']))
       $values['authorize_type'] = 'AUTH_CAPTURE';
    $dialog->start_row('Transaction Type:','middle');
    $dialog->add_radio_field('authorize_type','AUTH_CAPTURE',
                             'Authorize and Capture',$values);
    $dialog->write('&nbsp;&nbsp;&nbsp;');
    $dialog->add_radio_field('authorize_type','AUTH_ONLY','Authorize Only',
                             $values);
    $dialog->end_row();
    $dialog->start_row('Test Mode:','middle');
    $dialog->add_checkbox_field('authorize_test','',$values);
    $dialog->end_row();
    $dialog->start_row('Customer Notifications:','middle');
    $dialog->add_checkbox_field('authorize_notify','',$values);
    $dialog->end_row();
    $dialog->start_row('Include eChecks:','middle');
    $dialog->add_checkbox_field('authorize_echeck','',$values);
    $dialog->end_row();
    if (! empty($enable_saved_cards)) {
       $dialog->start_row('Enable Saved Credit Cards:','middle');
       $dialog->add_checkbox_field('authorize_saved_cards','',$values);
       $dialog->end_row();
    }
}

function authorize_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('authorize_username','authorize_key','authorize_hostname',
       'authorize_mode','authorize_test','authorize_notify','authorize_type',
       'authorize_echeck','authorize_saved_cards','authorize_ifields_key');
    if (! empty($website_settings)) $fields[] = 'authorize_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function authorize_payment_update_cart_config_field($field_name,
                                                    &$new_field_value,$db)
{
    $check_fields = array('authorize_notify','authorize_test',
       'authorize_echeck','authorize_saved_cards','authorize_active');

    foreach ($check_fields as $field) {
       if ($field_name == $field) {
          if (get_form_field($field) == 'on') $new_field_value = '1';
          else $new_field_value = '0';
          return true;
       }
    }
    return false;
}

function authorize_active($db)
{
    return get_cart_config_value('authorize_active',$db);
}

function authorize_get_primary_module($db)
{
    return 'authorize';
}

function authorize_saved_cards_enabled($db)
{
    $saved_flag = get_cart_config_value('authorize_saved_cards',$db);
    if ($saved_flag == '1') return true;
    return false;
}

function authorize_echecks_enabled($db)
{
    $echeck_flag = get_cart_config_value('authorize_echeck',$db);
    if ($echeck_flag) return true;
    return false;
}

function call_authorize_net($post_array,$item_string,&$response_data,&$error)
{
    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string) $post_string .= '&';
       $post_string .= $key.'='.urlencode($value);
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if ($log_string) $log_string .= '&';
       if (($key == 'x_card_num') || ($key == 'x_exp_date') ||
           ($key == 'x_card_code') || ($key == 'x_bank_aba_code') ||
           ($key == 'x_bank_acct_num') || ($key == 'x_login') ||
           ($key == 'x_tran_key'))
          $log_string .= $key.'='.str_repeat('X',strlen($value));
       else $log_string .= $key.'='.urlencode($value);
    }

    if ($item_string) {
       $post_string .= $item_string;   $log_string .= $item_string;
    }

    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    $host = get_cart_config_value('authorize_hostname');

    require_once '../engine/http.php';
    if (substr($host,0,4) == 'http') $url = $host;
    else $url = 'https://'.$host.'/gateway/transact.dll';
    $http = new HTTP($url);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Authorize.Net Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Authorize.Net Error: '.$error);
       else log_payment('Authorize.Net Error: '.$error);
       return null;
    }

    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_data);
    else log_payment('Response: '.$response_data);
    $separator = $response_data[1];
    $auth_result = explode($separator,$response_data);
    return $auth_result;
}

function authorize_process_payment(&$order)
{
    global $use_state_tax_table,$log_cart_errors_enabled;

    if (! isset($order->customer->info)) {
       $order->error = 'Missing Customer Information';
       log_error($order->error.' for Authorize.Net');
       return false;
    }
    if (! isset($use_state_tax_table)) $use_state_tax_table = true;
    $address = $order->customer->billing['address1'];
    $address2 = $order->customer->billing['address2'];
    if (isset($address2) && ($address2 != ''))
       $address .= ' '.$address2;
    $ship_address = $order->customer->shipping['address1'];
    $ship_address2 = $order->customer->shipping['address2'];
    if (isset($ship_address2) && ($ship_address2 != ''))
       $ship_address .= ' '.$ship_address2;
    if (get_cart_config_value('authorize_notify') == '1')
       $email_customer = 'TRUE';
    else $email_customer = 'FALSE';
    $site_name = get_cart_config_value('companyname',$order->db);
    if (isset($order->info['tax'])) $tax = $order->info['tax'];
    else $tax = 0;
    if (isset($order->info['shipping'])) $shipping = $order->info['shipping'];
    else $shipping = 0;
    $bill_state = $order->customer->get('bill_state');
    $ship_state = $order->customer->get('ship_state');
    $billing_phone = $order->customer->billing['phone'];
    $billing_phone = str_replace('+','',$billing_phone);
    $billing_phone = str_replace(' ','',$billing_phone);
    $billing_phone = str_replace('-','',$billing_phone);
    $billing_phone = str_replace(',','',$billing_phone);
    if (strlen($billing_phone) > 25)
       $billing_phone = substr($billing_phone,0,25);
    $billing_fax = $order->customer->billing['fax'];
    $billing_fax = str_replace('+','',$billing_fax);
    $billing_fax = str_replace(' ','',$billing_fax);
    $billing_fax = str_replace('-','',$billing_fax);
    $billing_fax = str_replace(',','',$billing_fax);
    if (strlen($billing_fax) > 25) $billing_fax = substr($billing_fax,0,25);
    $auth_type = get_cart_config_value('authorize_type');
    if (empty($_SERVER['REMOTE_ADDR'])) $remote_addr = '';
    else $remote_addr = $_SERVER['REMOTE_ADDR'];
    $test_mode = get_cart_config_value('authorize_test',$order->db);

    $post_array = array('x_login' =>
                        get_cart_config_value('authorize_username',$order->db),
                        'x_tran_key' =>
                        get_cart_config_value('authorize_key',$order->db),
                        'x_version' => '3.1',
                        'x_delim_data' => 'TRUE',
                        'x_delim_char' => '|',
                        'x_relay_response' => 'FALSE',
                        'x_cust_id' => $order->customer->info['id'],
                        'x_first_name' => trim($order->customer->info['fname']),
                        'x_last_name' => trim($order->customer->info['lname']),
                        'x_company' => trim($order->customer->info['company']),
                        'x_address' => trim($address),
                        'x_city' => trim($order->customer->billing['city']),
                        'x_state' => trim($bill_state),
                        'x_zip' => trim($order->customer->billing['zipcode']),
                        'x_country' => $order->get('bill_country_code'),
                        'x_phone' => $billing_phone,
                        'x_fax' => $billing_fax,
                        'x_email' => trim($order->customer->info['email']),
                        'x_email_customer' => $email_customer,
                        'x_ship_to_first_name' => trim($order->customer->info['fname']),
                        'x_ship_to_last_name' => trim($order->customer->info['lname']),
                        'x_ship_to_company' => trim($order->customer->shipping['shipto']),
                        'x_ship_to_address' => trim($ship_address),
                        'x_ship_to_city' => trim($order->customer->shipping['city']),
                        'x_ship_to_state' => trim($ship_state),
                        'x_ship_to_zip' => trim($order->customer->shipping['zipcode']),
                        'x_ship_to_country' => $order->get('ship_country_code'),
                        'x_invoice_num' => $order->info['order_number'],
                        'x_description' => $site_name.' Shopping Cart Order',
                        'x_tax' => $tax,
                        'x_freight' => $shipping,
                        'x_amount' => $order->payment['payment_amount'],
                        'x_currency_code' => $order->info['currency'],
                        'x_type' => $auth_type,
                        'x_customer_ip' => $remote_addr);

    if (isset($order->echeck)) {
       $post_array['x_method'] = 'ECHECK';
       $post_array['x_bank_aba_code'] = $order->echeck['routing_number'];
       $post_array['x_bank_acct_num'] = $order->echeck['account_number'];
       if ($order->echeck['account_type'] == 0)
          $post_array['x_bank_acct_type'] = 'CHECKING';
       else if ($order->echeck['account_type'] == 1)
          $post_array['x_bank_acct_type'] = 'SAVINGS';
       else if ($order->echeck['account_type'] == 2)
          $post_array['x_bank_acct_type'] = 'BUSINESSCHECKING';
       $post_array['x_bank_name'] = $order->echeck['bank_name'];
       $post_array['x_bank_acct_name'] = $order->echeck['account_name'];
       $post_array['x_echeck_type'] = 'WEB';
       $post_array['x_recurring_billing'] = 'FALSE';
    }
    else if (! isset($order->saved_card)) {
       $exp_date = $order->credit_card['month'].$order->credit_card['year'];
       $post_array['x_method'] = get_cart_config_value('authorize_mode');
       $post_array['x_card_num'] = $order->credit_card['number'];
       $post_array['x_exp_date'] = $exp_date;
       $post_array['x_card_code'] = $order->credit_card['cvv'];
       if ($test_mode && ($post_array['x_card_num'] == '4111111111111111')) {
          $post_array['x_test_request'] = 'true';
          $post_array['x_amount'] = .01;
       }
    }
    if (function_exists('update_authorize_post_array'))
       update_authorize_post_array($order,$post_array);

    if (isset($order->saved_card)) $item_array = array();
    else $item_string = '';
    foreach ($order->items as $id => $cart_item) {
       $item_name = get_html_product_name($cart_item['product_name'],
                                          GET_PROD_PAYMENT_GATEWAY,
                                          $order,$cart_item);
       if (strlen($item_name) > 31) $item_name = substr($item_name,0,31);
       $item_name = str_replace("\t",' ',$item_name);
       if (isset($cart_item['attribute_array']))
          $attr_array = $cart_item['attribute_array'];
       else $attr_array = null;
       $item_description = '';
       if (isset($attr_array) && (count($attr_array) > 0)) {
          foreach ($attr_array as $index => $attribute) {
             if ($index > 0) $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       if (strlen($item_description) > 255)
          $item_description = substr($item_description,0,255);
       $item_description = str_replace("\t",' ',$item_description);
       $product_id = $cart_item['product_id'];
       if (! $product_id) $product_id = 0;
       $item_total = get_item_total($cart_item,false);
       if ($item_total < 0) $item_total = abs($item_total);
       if (isset($order->saved_card)) {
          if ($tax != 0) $taxable = 'true';
          else $taxable = 'false';
          $item_array[] = array('itemId'=>$product_id,
                                'name'=>$item_name,
                                'description'=>$item_description,
                                'quantity'=>$cart_item['qty'],
                                'unitPrice'=>$item_total,
                                'taxable'=>$taxable);
       }
       else {
          $item_string .= '&x_line_item='.$product_id.'<|>' .
                          urlencode($item_name).'<|>'.urlencode($item_description) .
                          '<|>'.$cart_item['qty'].'<|>' .
                          $item_total.'<|>';
          if ($tax != 0) $item_string .= 'Y';
          else $item_string .= 'N';
       }
    }
    if (isset($order->info['coupon_amount'],$order->info['coupon_code']) &&
        $order->info['coupon_amount']) {
       $item_name = 'Coupon '.$order->info['coupon_code'];
       if (strlen($item_name) > 31) $item_name = substr($item_name,0,31);
       $coupon_amount = $order->info['coupon_amount'];
       if ($coupon_amount < 0) $coupon_amount = abs($coupon_amount);
       $item_description = $item_name.': -$' .
                           number_format($coupon_amount,2,'.','');
       if (isset($order->saved_card))
          $item_array[] = array('itemId'=>$order->info['coupon_id'],
                                'name'=>$item_name,
                                'description'=>$item_description,
                                'quantity'=>1,
                                'unitPrice'=>$coupon_amount,
                                'taxable'=>'false');
       else $item_string .= '&x_line_item='.$order->info['coupon_id'].'<|>' .
                            urlencode($item_name).'<|>'.urlencode($item_description) .
                            '<|>1<|>'.$coupon_amount.'<|>N';
    }
    if (isset($order->info['gift_amount']) && $order->info['gift_amount']) {
       $item_name = 'Gift Certificate '.$order->info['gift_code'];
       if (strlen($item_name) > 31) $item_name = substr($item_name,0,31);
       $gift_amount = $order->info['gift_amount'];
       if ($gift_amount < 0) $gift_amount = abs($gift_amount);
       $item_description = $item_name.': -$' .
                           number_format($gift_amount,2,'.','');
       if (isset($order->saved_card))
          $item_array[] = array('itemId'=>$order->info['gift_id'],
                                'name'=>$item_name,
                                'description'=>$item_description,
                                'quantity'=>1,
                                'unitPrice'=>$gift_amount,
                                'taxable'=>'false');
       else $item_string .= '&x_line_item='.$order->info['gift_id'].'<|>' .
                            urlencode($item_name).'<|>'.urlencode($item_description) .
                            '<|>1<|>'.$gift_amount.'<|>N';
    }
    if (isset($order->info['discount_amount']) &&
        $order->info['discount_amount']) {
       $item_name = $order->info['discount_name'];
       if (strlen($item_name) > 31) $item_name = substr($item_name,0,31);
       else if (! $item_name) $item_name = 'Discount';
       $discount_amount = $order->info['discount_amount'];
       if ($discount_amount < 0) $discount_amount = abs($discount_amount);
       $item_description = $item_name.': -$' .
                           number_format($discount_amount,2,'.','');
       if (isset($order->saved_card))
          $item_array[] = array('itemId'=>0,
                                'name'=>$item_name,
                                'description'=>$item_description,
                                'quantity'=>1,
                                'unitPrice'=>$discount_amount,
                                'taxable'=>'false');
       else $item_string .= '&x_line_item=0<|>' .
                            urlencode($item_name).'<|>'.urlencode($item_description) .
                            '<|>1<|>'.$discount_amount.'<|>N';
    }
    if (isset($order->info['fee_amount']) && $order->info['fee_amount']) {
       $item_name = $order->info['fee_name'];
       if (strlen($item_name) > 31) $item_name = substr($item_name,0,31);
       else if (! $item_name) $item_name = 'Fee';
       $fee_amount = $order->info['fee_amount'];
       if ($fee_amount < 0) $fee_amount = abs($fee_amount);
       if (isset($order->saved_card))
          $item_array[] = array('itemId'=>0,
                                'name'=>$item_name,
                                'description'=>$item_name,
                                'quantity'=>1,
                                'unitPrice'=>$fee_amount,
                                'taxable'=>'false');
       else $item_string .= '&x_line_item=0<|>' .
                            urlencode($item_name).'<|>'.urlencode($item_name) .
                            '<|>1<|>'.$fee_amount.'<|>N';
    }

    if (isset($order->saved_card))
       return process_saved_card_payment($order,$post_array,$item_array);
//    if ($order->credit_card['type'] == 'Cardknox') $item_string = null;

    $auth_result = call_authorize_net($post_array,$item_string,$response_data,
                                      $error);
    if (! $auth_result) {
       $order->error = $error;   return false;
    }

    if (($auth_result[0] != '1') && ($auth_result[0] != '4')) {
       if (isset($auth_result[3])) $error = $auth_result[3];
       else $error = $response_data;
       if (! empty($log_cart_errors_enabled))
          log_cart_error('Authorize.Net Declined: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Authorize.Net Declined: '.$error);
       else log_payment('Authorize.Net Declined: '.$error);
       if (isset($order->echeck)) $order->error = 'Check Declined: '.$error;
       else $order->error = 'Card Declined: '.$error;
       return false;
    }

/*
    if ($order->credit_card['type'] == 'Cardknox') {
       $semi_pos = strpos($order->credit_card['number'],';');
       if ($semi_pos !== false)
          $order->credit_card['number'] =
             substr($order->credit_card['number'],0,$semi_pos);
       $firstchar = substr($order->credit_card['number'],0,1);
       $card_type = $order->credit_card['type'];
       switch ($firstchar) {
          case '3':
             $secondchar = substr($order->credit_card['number'],1,1);
             switch ($secondchar) {
                case '5': $card_type = 'JCB';   break;
                case '7': $card_type = 'amex';   break;
                case '6':
                case '8': $card_type = 'diners';   break;
             }
             break;
          case '4': $card_type = 'visa';   break;
          case '5': $card_type = 'master';   break;
          case '6': $card_type = 'discover';   break;
       }
       $order->credit_card['type'] = $card_type;
    }
*/
    $order->payment['payment_id'] = $auth_result[6];
    $order->payment['payment_code'] = $auth_result[4];
    $order->payment['payment_data'] = $response_data;
    if ($auth_type == 'AUTH_ONLY') {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Authorize.Net Authorization Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Authorize.Net Payment Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    return true;
}

function authorize_capture_payment($db,$payment_info,&$error)
{
    global $order_label;

    $payment_id = $payment_info['payment_id'];
    $post_array = array('x_login' => get_cart_config_value('authorize_username'),
                        'x_tran_key' => get_cart_config_value('authorize_key'),
                        'x_version' => '3.1',
                        'x_delim_data' => 'TRUE',
                        'x_delim_char' => '|',
                        'x_relay_response' => 'FALSE',
                        'x_trans_id' => $payment_id,
                        'x_type' => 'PRIOR_AUTH_CAPTURE');

    $test_mode = get_cart_config_value('authorize_test',$db);
    if ($test_mode && ($payment_info['card_number'] == '411111XXXXXX1111'))
       $post_array['x_test_request'] = 'true';

    $auth_result = call_authorize_net($post_array,null,$response_data,$error);
    if (! $auth_result) return false;

    if (($auth_result[0] != '1') && ($auth_result[0] != '4')) {
       if (isset($auth_result[3])) $error = $auth_result[3];
       else $error = $response_data;
       $error = 'Authorize.Net Capture Declined: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    $capture_id = $auth_result[6];
    $payment_code = $auth_result[4];

    $order_id = $payment_info['parent'];
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_info['id'];
    $payment_record['payment_status']['value'] = PAYMENT_CAPTURED;
    $payment_record['payment_id']['value'] = $capture_id;
    $payment_record['payment_date']['value'] = time();
    if (! $db->update('order_payments',$payment_record)) {
       $error = $db->error;   return false;
    }
    log_activity('Updated Payment #'.$payment_info['id'].' for ' .
                 $order_label.' #'.$order_id);

    log_activity('Captured Authorize.Net Transaction #'.$payment_id .
                 ' with Transaction ID #'.$capture_id.' and Authorization Code ' .
                 $payment_code.' for Order #'.$order_id);
    return true;
}

function authorize_cancel_payment($db,$payment_info,$refund_amount,
                                  &$cancel_info,&$error)
{
    $order_id = $payment_info['parent'];
    $payment_status = $payment_info['payment_status'];
    if ($payment_status == PAYMENT_AUTHORIZED) {
       $trans_type = 'VOID';   $description = 'Void';
    }
    else {
       $trans_type = 'CREDIT';   $description = 'Refund';
    }
    $description .= ' for Order #'.$order_id;
    $payment_id = $payment_info['payment_id'];
    $post_array = array('x_login' => get_cart_config_value('authorize_username'),
                        'x_tran_key' => get_cart_config_value('authorize_key'),
                        'x_version' => '3.1',
                        'x_delim_data' => 'TRUE',
                        'x_delim_char' => '|',
                        'x_relay_response' => 'FALSE',
                        'x_trans_id' => $payment_id,
                        'x_ref_trans_id' => $payment_id,
                        'x_description' => $description,
                        'x_amount' => $refund_amount,
                        'x_card_num' => substr($payment_info['card_number'],-4),
                        'x_type' => $trans_type);

    $test_mode = get_cart_config_value('authorize_test',$db);
    if ($test_mode && ($payment_info['card_number'] == '411111XXXXXX1111'))
       $post_array['x_test_request'] = 'true';

    $auth_result = call_authorize_net($post_array,null,$response_data,$error);
    if (! $auth_result) return false;

    if (($auth_result[0] != '1') && ($auth_result[0] != '4')) {
       if (isset($auth_result[3])) $error = $auth_result[3];
       else $error = $response_data;
       $error = 'Authorize.Net '.$trans_type.' Error: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
       return false;
    }

    $cancel_info['payment_id'] = $auth_result[6];
    $cancel_info['payment_code'] = $auth_result[4];
    $cancel_info['payment_data'] = $response_data;
    if ($trans_type == 'VOID')
       log_activity('Voided Authorize.Net Transaction #'.$payment_id .
                    ' for Order #'.$order_id);
    else log_activity('Refunded Authorize.Net Transaction #'.$payment_id .
                      ' for Order #'.$order_id);
    return true;
}

define ('AUTHORIZE_CIM_HOSTNAME','api2.authorize.net');
define ('AUTHORIZE_CIM_TEST_HOSTNAME','apitest.authorize.net');
define ('AUTHORIZE_CIM_PATH','/xml/v1/request.api');

function append_authorize_xml_data($xml_array,&$xml_string,&$log_string)
{
    foreach ($xml_array as $index => $value) {
       if (is_array($value)) {
          reset($value);   $first_index = key($value);
          if (is_integer($first_index)) {
             foreach ($value as $sub_value) {
                $xml_string .= '<'.$index.'>';   $log_string .= '<'.$index.'>';
                if (is_array($sub_value))
                   append_authorize_xml_data($sub_value,$xml_string,
                                             $log_string);
                else {
                   $xml_string .= encode_xml_data($sub_value);
                   $log_string .= encode_xml_data($sub_value);
                }
                $xml_string .= '</'.$index.'>';
                $log_string .= '</'.$index.'>';
             }
             continue;
          }
       }
       $xml_string .= '<'.$index.'>';   $log_string .= '<'.$index.'>';
       if (is_array($value))
          append_authorize_xml_data($value,$xml_string,$log_string);
       else {
          $xml_string .= encode_xml_data($value);
          if (($index == 'cardNumber') || ($index == 'expirationDate') ||
              ($index == 'cardCode')) $value = str_repeat('X',strlen($value));
          $log_string .= encode_xml_data($value);
       }
       $xml_string .= '</'.$index.'>';   $log_string .= '</'.$index.'>';
    }
}

function call_authorize_cim($db,$function_name,$xml_array,&$response_data,
                            &$error)
{
    global $log_cart_errors_enabled;

    $login_id = get_cart_config_value('authorize_username',$db);
    $auth_key = get_cart_config_value('authorize_key',$db);
    $test_mode = get_cart_config_value('authorize_test');
    if (isset($xml_array['testMode'])) {
       unset($xml_array['testMode']);   $test_mode = true;
    }
    $xml_string = '<?xml version="1.0" encoding="utf-8"?><'.$function_name .
       ' xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">' .
       '<merchantAuthentication><name>'.encode_xml_data($login_id) .
       '</name><transactionKey>'.encode_xml_data($auth_key) .
       '</transactionKey></merchantAuthentication>';
    $log_string = $xml_string;
    append_authorize_xml_data($xml_array,$xml_string,$log_string);
    if (strpos($xml_string,'paymentProfile') !== false) {
       $xml = '<validationMode>';
       if ($test_mode) $xml .= 'testMode';
       else $xml .= 'none';
       $xml .= '</validationMode>';
       $xml_string .= $xml;   $log_string .= $xml;
    }
    $xml_string .= '</'.$function_name.'>';
    $log_string .= '</'.$function_name.'>';

    if (class_exists('Order'))
       @Order::log_payment('Sent: '.$log_string);
    else log_payment('Sent: '.$log_string);

    require_once '../engine/http.php';
    if ($test_mode)
       $url = 'https://'.AUTHORIZE_CIM_TEST_HOSTNAME.AUTHORIZE_CIM_PATH;
    else $url = 'https://'.AUTHORIZE_CIM_HOSTNAME.AUTHORIZE_CIM_PATH;
    $http = new HTTP($url);
    $response_data = $http->call($xml_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Authorize.Net CIM Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Authorize.Net CIM Error: '.$error);
       else log_payment('Authorize.Net CIM Error: '.$error);
       return null;
    }

    if (class_exists('Order'))
       @Order::log_payment('Response: '.$response_data);
    else log_payment('Response: '.$response_data);
    $messages = parse_xml_tag($response_data,'messages');
    $code = null;
    if (! $messages) {
       if (substr($response_data,0,3) == pack('CCC',0xEF,0xBB,0xBF))
          $response_data = substr($response_data, 3);
       $data = json_decode($response_data);
       if ($data && isset($data->messages)) {
          $auth_result = $data->messages->resultCode;
          if ($auth_result != 'Ok') {
             $error = '';
             foreach ($data->messages->message as $message) {
                if ($error) $error .= '; ';
                $error .= $message->text.' ('.$message->code.')';
                $code = $message->code;
             }
          }
          else $error = null;
       }
       else {
          $auth_result = null;   $error = 'Unknown Response';
       }
    }
    else {
       $auth_result = parse_xml_tag($messages,'resultCode');
       if (! $auth_result) $error = 'Missing resultCode';
       else if ($auth_result != 'Ok') {
          $text = parse_xml_tag($messages,'text');
          $code = parse_xml_tag($messages,'code');
          $error = $text.' ('.$code.')';
       }
       else $error = null;
    }
    if ($error) {
       if (! empty($log_cart_errors_enabled))
          log_cart_error('Authorize.Net CIM Error: '.$error);
       $do_not_log_errors = array('E00027','E00013');
       if (! in_array($code,$do_not_log_errors))
          log_error('Authorize.Net CIM Error: '.$error);
       $error = 'Authorize.Net CIM Error: '.$error;
       if (class_exists('Order')) @Order::log_payment($error);
       else log_payment($error);
    }
    return $auth_result;
}

function authorize_create_saved_profile($db,$customer_id,&$error)
{
    $xml_array = array('profile'=>array('merchantCustomerId'=>$customer_id));
    $query = 'select fname,lname,email from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $customer_info = $db->get_record($query);
    if ($customer_info) {
       $full_name = $customer_info['fname'].' '.$customer_info['lname'];
       $email = $customer_info['email'];
       $xml_array['profile']['description'] = 'Profile for '.$full_name;
       if ($email) $xml_array['profile']['email'] = $email;
    }
    $auth_result = call_authorize_cim($db,'createCustomerProfileRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') return null;
    $profile_id = parse_xml_tag($response_data,'customerProfileId');
    if (! $profile_id) $error = 'Missing Profile ID from Response';
    return $profile_id;
}

function authorize_delete_saved_profile($db,$profile_id,&$error)
{
    $xml_array = array('customerProfileId'=>$profile_id);
    $auth_result = call_authorize_cim($db,'deleteCustomerProfileRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') return false;
    return true;
}

function authorize_create_saved_card($db,$profile_id,$payment_info,&$error)
{
    $country_info = get_country_info($payment_info['country'],$db);
    $country_code = $country_info['code'];
    $expiration_date = $payment_info['card_year'].'-' .
                       $payment_info['card_month'];
    if (isset($payment_info['fax'])) $fax_number = $payment_info['fax'];
    else $fax_number = '';
    $test_mode = get_cart_config_value('authorize_test',$db);
    $xml_array = array('customerProfileId'=>$profile_id,
       'paymentProfile'=>array(
          'billTo'=>array('firstName'=>$payment_info['fname'],
                          'lastName'=>$payment_info['lname'],
                          'company'=>$payment_info['company'],
                          'address'=>$payment_info['address1'],
                          'city'=>$payment_info['city'],
                          'state'=>$payment_info['state'],
                          'zip'=>$payment_info['zipcode'],
                          'country'=>$country_code,
                          'phoneNumber'=>$payment_info['phone'],
                          'faxNumber'=>$fax_number),
          'payment'=>array('creditCard'=>array(
             'cardNumber'=>$payment_info['card_number'],
             'expirationDate'=>$expiration_date,
             'cardCode'=>$payment_info['card_cvv']))));
    if ($test_mode && ($payment_info['card_number'] == '4111111111111111'))
       $xml_array['testMode'] = true;
    $auth_result = call_authorize_cim($db,'createCustomerPaymentProfileRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') return null;
    $payment_id = parse_xml_tag($response_data,'customerPaymentProfileId');
    if (! $payment_id) $error = 'Missing Payment Profile ID from Response';
    return $payment_id;
}

function authorize_update_saved_card($db,$profile_id,$payment_id,$payment_info,
                                     &$error)
{
    $country_info = get_country_info($payment_info['country'],$db);
    $country_code = $country_info['code'];
    $expiration_date = $payment_info['card_year'].'-' .
                       $payment_info['card_month'];
    $xml_array = array('customerProfileId'=>$profile_id,
       'paymentProfile'=>array(
          'billTo'=>array('firstName'=>$payment_info['fname'],
                          'lastName'=>$payment_info['lname'],
                          'company'=>$payment_info['company'],
                          'address'=>$payment_info['address1'],
                          'city'=>$payment_info['city'],
                          'state'=>$payment_info['state'],
                          'zip'=>$payment_info['zipcode'],
                          'country'=>$country_code,
                          'phoneNumber'=>$payment_info['phone'],
                          'faxNumber'=>$payment_info['fax']),
          'payment'=>array('creditCard'=>array(
             'cardNumber'=>$payment_info['card_number'],
             'expirationDate'=>$expiration_date,
             'cardCode'=>$payment_info['card_cvv'])),
          'customerPaymentProfileId'=>$payment_id));
    $test_mode = get_cart_config_value('authorize_test',$db);
    if ($test_mode && ($payment_info['card_number'] == '4111111111111111'))
       $xml_array['testMode'] = true;
    $auth_result = call_authorize_cim($db,'updateCustomerPaymentProfileRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') return false;
    return true;
}

function authorize_delete_saved_card($db,$profile_id,$payment_id,&$error)
{
    $xml_array = array('customerProfileId'=>$profile_id,
                       'customerPaymentProfileId'=>$payment_id);
    $auth_result = call_authorize_cim($db,'deleteCustomerPaymentProfileRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') return false;
    return true;
}

function process_saved_card_payment(&$order,$post_array,$item_array)
{
    global $log_cart_errors_enabled;

    $payment_id = $order->saved_card;
    $query = 'select * from saved_cards where profile_id=?';
    $query = $order->db->prepare_query($query,$payment_id);
    $card_info = $order->db->get_record($query);
    if (! $card_info) {
       if (isset($db->error)) $order->error = $db->error;
       else $order->error = 'Saved Card #'.$payment_id.' Not Found';
       return false;
    }       
    if ($order->customer_id) {
       $query = 'select profile_id from customers where id=?';
       $query = $order->db->prepare_query($query,$order->customer_id);
       $customer_info = $order->db->get_record($query);
       if (! $customer_info) {
          if (isset($db->error)) $order->error = $db->error;
          else $order->error = 'Customer #'.$order->customer_id .
                               ' Not Found for Saved Card Payment';
          return false;
       }
       $profile_id = $customer_info['profile_id'];
    }
    else if ($card_info) $profile_id = -$card_info['parent'];
    else $profile_id = null;
    $auth_type = $post_array['x_type'];
    if ($auth_type == 'AUTH_ONLY') $trans_type = 'profileTransAuthOnly';
    else $trans_type = 'profileTransAuthCapture';
    $trans_data = array();
    $trans_data['amount'] = $post_array['x_amount'];
    if ($post_array['x_tax'])
       $trans_data['tax'] = array('amount'=>$post_array['x_tax']);
    if ($post_array['x_freight'])
       $trans_data['shipping'] = array('amount'=>$post_array['x_freight']);
    $trans_data['lineItems'] = $item_array;
    $trans_data['customerProfileId'] = $profile_id;
    $trans_data['customerPaymentProfileId'] = $payment_id;
    $trans_data['order'] = array('invoiceNumber'=>$post_array['x_invoice_num']);

    $xml_array = array('transaction'=>array($trans_type=>$trans_data));
    $auth_result = call_authorize_cim($order->db,
                                      'createCustomerProfileTransactionRequest',
                                      $xml_array,$response_data,$error);
    if ($auth_result != 'Ok') {
       if (isset($order->echeck)) $order->error = 'Check Declined: '.$error;
       else $order->error = 'Card Declined: '.$error;
       return false;
    }
    $direct_response = parse_xml_tag($response_data,'directResponse');
    if (! $direct_response) {
       $error = 'Authorize.Net Error: Missing Direct Response';
       log_error($error);   $order->error = $error;   return false;
    }
    $separator = $direct_response[1];
    $auth_result = explode($separator,$direct_response);
    $order->payment['payment_id'] = $auth_result[6];
    $order->payment['payment_code'] = $auth_result[4];
    $order->payment['payment_data'] = $direct_response;
    if (! isset($order->credit_card)) $order->credit_card = array();
    $order->credit_card['type'] = $card_info['card_type'];
    $order->credit_card['name'] = $card_info['card_name'];
    $order->credit_card['number'] = $card_info['card_number'];
    $order->credit_card['month'] = $card_info['card_month'];
    $order->credit_card['year'] = $card_info['card_year'];
    $order->credit_card['cvv'] = $card_info['card_cvv'];
    if ($auth_type == 'AUTH_ONLY') {
       $order->payment['payment_status'] = PAYMENT_AUTHORIZED;
       log_activity('Authorize.Net Authorization Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    else {
       $order->payment['payment_status'] = PAYMENT_CAPTURED;
       log_activity('Authorize.Net Payment Accepted with Transaction ID #' .
                    $order->payment['payment_id'].' and Authorization Code ' .
                    $order->payment['payment_code']);
    }
    return true;
}

/*
function authorize_setup_order_dialog($db,&$dialog,$edit_type)
{
    $ifields_key = get_cart_config_value('authorize_ifields_key',$db);
    $dialog->ifields_key = $ifields_key;
    if (! $ifields_key) return;
    $head_line = '<script>var ifields_key = \''.$ifields_key.'\';</script>';
    $dialog->add_head_line($head_line);
    $dialog->add_script_file('../admin/payment/authorize-ifields.js');
    $dialog->add_script_file('https://cdn.cardknox.com/ifields/' .
                             CARDKNOX_IFIELDS_VERSION.'/ifields.min.js',null);
    if ($dialog->onload_function)
       $onload_function = $dialog->onload_function .
                          ' authorize_ifields_onload();';
    else $onload_function = 'authorize_ifields_onload();';
    $dialog->set_onload_function($onload_function);
}

function authorize_write_card_dialog_field(&$dialog,&$order,$field_name)
{
    if (! $dialog->ifields_key) return false;
    if (($field_name != 'card_number') && ($field_name != 'card_cvv'))
       return false;
    ob_start();
    authorize_write_card_field($dialog,$field_name);
    $field = ob_get_contents();
    ob_end_clean();
    $dialog->write($field);
    return true;
}
*/

function authorize_configure_checkout(&$cart)
{
    if ($cart->payment_module == 'authorize.net')
       $cart->enable_echecks = get_cart_config_value('authorize_echeck',
                                                     $cart->db);
}

/*
function authorize_write_checkout_form(&$cart)
{
    $cart->ifields_key = get_cart_config_value('authorize_ifields_key',
                                               $cart->db);
    if (! $cart->ifields_key) return false;
    print '<script>var ifields_key = \''.$cart->ifields_key."';</script>\n";
    print '<script src="admin/payment/authorize-ifields.js?v=' .
          filemtime('../admin/payment/authorize-ifields.js').'"></script>'."\n";
    print '<script src="https://cdn.cardknox.com/ifields/' .
          CARDKNOX_IFIELDS_VERSION.'/ifields.min.js">' .
          '</script>'."\n";
    print '<script>add_onload(authorize_ifields_onload);</script>'."\n";
    return false;
}

function authorize_write_card_field(&$cart,$field_name)
{
    if (! $cart->ifields_key) return false;
    if ($field_name == 'card_number') {
       $mobile_cart = get_cookie('mobile_cart');
       if ($mobile_cart) $width = 100;
       else $width = 300;
       print '<iframe data-ifields-id="card-number" data-ifields-placeholder="" ' .
             'frameborder="0" height="20" id="ifields_card_number_iframe" ' .
             'scrolling="no" width="'.$width.'" src="' .
             'https://cdn.cardknox.com/ifields/ifield.htm"></iframe>'."\n";
       print '<input data-ifields-id="card-number-token" name="card_number" ' .
             'type="hidden"></input>'."\n";
       print '<input type="hidden" name="card_type" id="card_type" ' .
             'value="Cardknox">'."\n";
       print '<label class="cart_error" id="ifields_error" style="display: block;"' .
             'data-ifields-id="card-data-error"></label>'."\n";
       return true;
    }
    else if ($field_name == 'card_cvv') {
       print '<iframe data-ifields-id="cvv" data-ifields-placeholder="" ' .
             'frameborder="0" height="20" style="display: inline; width: 65px;" ' .
             'id="ifields_cvv_iframe" scrolling="no" src="' .
             'https://cdn.cardknox.com/ifields/ifield.htm"></iframe>'."\n";
       print '<input data-ifields-id="cvv-token" name="card_cvv" ' .
             'type="hidden"></input>'."\n";
       return true;
    }
    return false;
}

function authorize_validate_credit_card(&$cart)
{
    if ($cart->credit_card['type'] == 'Cardknox') {
       $cart->credit_card['number'] = trim(get_form_field('card_number'));
       if (isset($cart->errors['card_number']))
          unset($cart->errors['card_number']);
    }
}
*/

?>
