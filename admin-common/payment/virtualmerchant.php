<?php
/*
                Inroads Shopping Cart - Virtual Merchant API Module

                       Written 2009-2018 by Randall Severy
                        Copyright 2009-2018 Inroads, LLC
*/

function virtualmerchant_payment_cart_config_section($db,$dialog,$values)
{
    global $enable_saved_cards;

    add_payment_section($dialog,'Virtual Merchant','vm',$values);

    $dialog->add_edit_row('Merchant ID:','vm_merchantid',$values,30);
    $dialog->add_edit_row('User ID:','vm_userid',$values,30);
    $dialog->add_edit_row('PIN:','vm_pin',$values,30);
    $dialog->add_edit_row('Hostname:','vm_hostname',$values,30);
    $dialog->add_edit_row('URL Path:','vm_path',$values,30);
}

function virtualmerchant_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('vm_merchantid','vm_userid','vm_pin','vm_hostname',
                    'vm_path');
    if (! empty($website_settings)) $fields[] = 'vm_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function virtualmerchant_payment_update_cart_config_field($field_name,
                                                          &$new_field_value,$db)
{
    if ($field_name == 'vm_active') {
       if (get_form_field('vm_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function virtualmerchant_active($db)
{
    return get_cart_config_value('vm_active',$db);
}

function virtualmerchant_get_primary_module($db)
{
    return 'virtualmerchant';
}

function virtualmerchant_process_payment(&$order)
{
    $exp_date = $order->credit_card['month'].$order->credit_card['year'];
    $site_name = get_cart_config_value('companyname');
    $billing_country_info = get_country_info($order->billing_country,$order->db);
    $shipping_country_info = get_country_info($order->shipping_country,$order->db);
    if (isset($order->info['tax']) && ($order->info['tax'] != ''))
       $tax = $order->info['tax'];
    else $tax = 0;

    $post_array = array('ssl_transaction_type' => 'ccsale',
                        'ssl_merchant_id' => get_cart_config_value('vm_merchantid'),
                        'ssl_pin' => get_cart_config_value('vm_pin'),
                        'ssl_user_id' => get_cart_config_value('vm_userid'),
                        'ssl_amount' => $order->payment['payment_amount'],
                        'ssl_salestax' => $tax,
                        'ssl_card_number' => $order->credit_card['number'],
                        'ssl_exp_date' => $exp_date,
                        'ssl_cvv2cvc2_indicator' => '1',
                        'ssl_cvv2cvc2' => $order->credit_card['cvv'],
                        'ssl_description' => $site_name.' Shopping Cart Order',
                        'ssl_invoice_number' => $order->info['order_number'],
                        'ssl_customer_code' => $order->customer->info['id'],
                        'ssl_company' => substr($order->customer->info['company'],0,50),
                        'ssl_first_name' => substr($order->customer->info['fname'],0,20),
                        'ssl_last_name' => substr($order->customer->info['lname'],0,30),
                        'ssl_avs_address' => substr($order->customer->billing['address1'],0,20),
                        'ssl_address2' => substr($order->customer->billing['address2'],0,30),
                        'ssl_city' => substr($order->customer->billing['city'],0,30),
                        'ssl_state' => $order->customer->billing['state'],
                        'ssl_avs_zip' => str_replace('-','',$order->customer->billing['zipcode']),
                        'ssl_country' => $billing_country_info['code'],
                        'ssl_phone' => substr($order->customer->billing['phone'],0,20),
                        'ssl_email' => substr($order->customer->info['email'],0,100),
                        'ssl_ship_to_company' => substr($order->customer->shipping['shipto'],0,50),
                        'ssl_ship_to_first_name' => substr($order->customer->info['fname'],0,20),
                        'ssl_ship_to_last_name' => substr($order->customer->info['lname'],0,30),
                        'ssl_ship_to_address1' => substr($order->customer->shipping['address1'],0,30),
                        'ssl_ship_to_saddress2' => substr($order->customer->shipping['address2'],0,30),
                        'ssl_ship_to_city' => substr($order->customer->shipping['city'],0,30),
                        'ssl_ship_to_state' => $order->customer->shipping['state'],
                        'ssl_ship_to_zip' => $order->customer->shipping['zipcode'],
                        'ssl_ship_to_country' => $shipping_country_info['code'],
                        'ssl_show_form' => 'false',
                        'ssl_result_format' => 'ASCII');

    if (function_exists('update_virtual_merchant_data'))
       update_virtual_merchant_data($order,$post_array);

    $post_string = '';
    foreach ($post_array as $key => $value) {
       if ($post_string != '') $post_string .= '&';
       $post_string .= $key.'='.urlencode($value);
    }

    $log_string = '';
    foreach ($post_array as $key => $value) {
       if (($key == 'ssl_card_number') || ($key == 'ssl_exp_date') ||
           ($key == 'ssl_cvv2cvc2')) continue;
       if ($log_string != '') $log_string .= '&';
       $log_string .= $key.'='.urlencode($value);
    }

    $order->log_payment('Sent: '.$log_string);

    $host = get_cart_config_value('vm_hostname');
    $path = get_cart_config_value('vm_path');
    $url = 'https://'.$host.$path;
    require_once '../engine/http.php';
    $http = new HTTP($url);
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('Virtual Merchant Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('Virtual Merchant Error: '.$error);
       else log_payment('Virtual Merchant Error: '.$error);
       return null;
    }

    $response_data = str_replace("\n",'|',$response_data);
    $order->log_payment('Response: '.$response_data);
    $response_array = explode('|',$response_data);
    $result_array = array();
    foreach ($response_array as $fieldpair) {
       $fieldpair_array = explode('=',$fieldpair);
       if (count($fieldpair_array) < 2) continue;
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
       $order->error = $error_message;   return false;
    }
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $result_array['ssl_txn_id'];
    $order->payment['payment_code'] = $result_array['ssl_approval_code'];
    log_activity('Virtual Merchant Payment Accepted with Transaction ID #' .
                 $order->payment['payment_id'].' and Approval Code ' .
                 $order->payment['payment_code']);
    return true;
}

?>
