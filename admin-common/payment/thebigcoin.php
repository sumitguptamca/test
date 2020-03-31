<?php
/*
                     Inroads Shopping Cart - TheBigCoin API Module

                            Written 2018 by Randall Severy
                             Copyright 2018 Inroads, LLC
*/


function thebigcoin_payment_cart_config_section($db,$dialog,$values)
{
    add_payment_section($dialog,'TheBigCoin','thebigcoin',$values);

    $dialog->add_edit_row('Project ID:','thebigcoin_project',$values,5);
    $dialog->add_edit_row('API Key:','thebigcoin_key',$values,60);
    $dialog->add_edit_row('API Secret:','thebigcoin_secret',$values,60);
    $dialog->start_row('Test Mode:','middle');
    $dialog->add_checkbox_field('thebigcoin_test','',$values);
    $dialog->end_row();
}

function thebigcoin_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('thebigcoin_project','thebigcoin_key','thebigcoin_secret',
       'thebigcoin_test');
    if (! empty($website_settings)) $fields[] = 'thebigcoin_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function thebigcoin_payment_update_cart_config_field($field_name,&$new_field_value,$db)
{
    if ($field_name == 'thebigcoin_test') {
       if (get_form_field('thebigcoin_test') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else if ($field_name == 'thebigcoin_active') {
       if (get_form_field('thebigcoin_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function thebigcoin_active($db)
{
    return get_cart_config_value('thebigcoin_active',$db);
}

class TheBigCoin {

function __construct($db=null)
{
    global $cart_config_values;

    if (! $cart_config_values) load_cart_config_values($db);
    $this->project_id = get_cart_config_value('thebigcoin_project');
    $this->api_key = get_cart_config_value('thebigcoin_key');
    $this->api_secret = get_cart_config_value('thebigcoin_secret');
    $this->test_mode = get_cart_config_value('thebigcoin_test');
    $this->error = null;
}

function TheBigCoin($db=null)
{
    self::__construct($db);
}

function call($path,$post_array=null)
{
    if ($post_array) {
       $post_string = '';
       foreach ($post_array as $key => $value) {
          if ($post_string) $post_string .= '&';
          $post_string .= $key.'='.urlencode($value);
       }
       $method = 'POST';
    }
    else {
       $post_string = null;   $method = 'GET';
    }
    if ($this->test_mode) $url = 'https://api-test.thebigcoin.io';
    else $url = 'https://api.thebigcoin.io';
    $url .= $path;
    if (class_exists('Order')) {
       @Order::log_payment('TheBigCoin URL: '.$url);
       if ($post_string) @Order::log_payment('TheBigCoin Sent: '.$post_string);
    }
    else {
       log_payment('TheBigCoin URL: '.$url);
       if ($post_string) log_payment('TheBigCoin Sent: '.$post_string);
    }
    $timestamp = (int) microtime(true);
    $message = $timestamp.$this->project_id.$this->api_key;
    $signature = hash_hmac('sha256',$message,$this->api_secret);

    require_once '../engine/http.php';
    $http = new HTTP($url,$method);
    $http->set_accept('*/*');
    $http->set_charset(null);
    $http->set_headers(array('TBC-ACCESS-KEY: '.$this->api_key,
                             'TBC-ACCESS-SIGN: '.$signature,
                             'TBC-ACCESS-TIMESTAMP: '.$timestamp));
    $response_data = $http->call($post_string);
    if (! $response_data) {
       $error = $http->error.' ('.$http->status.')';
       log_error('TheBigCoin Error: '.$error);
       if (class_exists('Order'))
          @Order::log_payment('TheBigCoin Error: '.$error);
       else log_payment('TheBigCoin Error: '.$error);
       return null;
    }
    if (class_exists('Order'))
       @Order::log_payment('TheBigCoin Response: '.$response_data);
    else log_payment('TheBigCoin Response: '.$response_data);
    return $response_data;
}

function get_errors($response)
{
    if (empty($response->status_code)) return null;
    $errors = '';
    if (isset($response->errors)) {
       foreach ($response->errors as $field => $error) {
          if ($errors) $errors .= ', ';
          $errors .= $field.': '.$error[0];
       }
    }
    else if (isset($response->message)) $errors = $response->message;
    return $errors;
}

}

function thebigcoin_setup_checkout(&$cart)
{
    global $order,$coupon_code,$using_paypal_express;
    global $require_paypal_express_account;

    if (button_pressed('CancelTheBigCoin')) {
       if (isset($cart->info['coupon_code']))
          $coupon_code = $cart->info['coupon_code'];
       else $coupon_code = null;
       if (! $cart->load_cart_data()) $cart->errors['error'] = true;
       $cart->read_hidden_fields();
    }
}

function thebigcoin_write_payment_logos(&$cart)
{
    print '<img src="https://app.thebigcoin.io/img/pay/pay-small.png" ' .
          'alt="TheBigCoin" class="avail_card_logo" height="44">' .
          "\n";
}

function thebigcoin_write_checkout_payment_button(&$cart)
{
    global $continue_no_payment,$include_direct_payment;

    if (! empty($cart->single_payment_button)) return;
    $cart->num_payment_divs++;
    print '    <div style="float: right; line-height: 47px;';
    if ($continue_no_payment) print ' display:none;';
    print '" id="payment_div_'.$cart->num_payment_divs.'">';
    if ($include_direct_payment || ($cart->num_payment_divs > 1))
       print '<strong>&nbsp;&nbsp;'.T('OR')."&nbsp;&nbsp;&nbsp;</strong>\n";
    print '      <input type="image" name="TheBigCoinCheckout" ' .
          'id="TheBigCoinCheckout" style="vertical-align: middle; ' .
          'height: 47px;"'."\n" .
          '       src="https://app.thebigcoin.io/img/pay/pay-small.png">'."\n";
    print "</div>\n";
}

function thebigcoin_start_process_order($paying_balance)
{
    if ($paying_balance) {
       $order_id = get_form_field('TheBigCoinOrder');
       if (! $order_id) return;
       if (isset($_SERVER['REQUEST_METHOD']))
          $method = $_SERVER['REQUEST_METHOD'];
       else $method = 'GET';
       if ($method == 'POST') $_POST['id'] = $order_id;
       else $_GET['id'] = $order_id;
    }
    else {
       $cart_id = get_form_field('TheBigCoinCart');
       if (! $cart_id) return;
       $cart = new Cart(null,$cart_id,null,true,true);
       $cart->load_cart_data();
    }
}

function process_thebigcoin_checkout(&$order,$paying_balance)
{
    global $ssl_url;

    if ($paying_balance) $order_id = $order->id;
    else {
       $order_id = $order->cart->id;
       if (! $order->cart->save_cart_data()) {
          $order->error = $order->db->error;
          $order->errors['CardFailed'] = true;
          return;
       }
    }
    if (function_exists('get_custom_cart_prefix'))
       $cart_prefix = get_custom_cart_prefix();
    else $cart_prefix = '';
    $complete_url = $ssl_url.$cart_prefix.'cart/' .
                    $order->process_module.'?FinishTheBigCoin=Go';
    if ($paying_balance) $complete_url .= '&TheBigCoinOrder='.$order_id;
    else $complete_url .= '&TheBigCoinCart='.$order_id;
    $cancel_url = $ssl_url.$cart_prefix.'cart/'.$order->checkout_module .
                 '?CancelTheBigCoin=Go';
    $site_name = get_cart_config_value('companyname');
    $payment_amount = $order->payment['payment_amount'];
    $discount_amount = 0;
    if (! empty($order->info['coupon_amount']))
       $discount_amount += floatval($order->info['coupon_amount']);
    if (! empty($order->info['gift_amount']))
       $discount_amount += floatval($order->info['gift_amount']);
    if (! empty($order->info['discount_amount']))
       $discount_amount += floatval($order->info['discount_amount']);
    if (isset($order->customer->info['email']))
       $order_email = $order->customer->info['email'];
    else $order_email = '';
    $items = array();   $index = 0;
    foreach ($order->items as $id => $cart_item) {
       $item_description = get_html_product_name($cart_item['product_name'],
                                                 GET_PROD_PAYMENT_GATEWAY,
                                                 $order,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       if (isset($attr_array) && (count($attr_array) > 0)) {
          $item_description .= ' - ';
          foreach ($attr_array as $attr_index => $attribute) {
             if ($attr_index > 0) $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       $items['items['.$index.'][name]'] = $item_description;
       $items['items['.$index.'][qty]'] = $cart_item['qty'];
       $items['items['.$index.'][amount]'] = $cart_item['price'];
       $index++;
    }
    $post_array = array('order_id' => $order_id,
                        'currency' => $order->info['currency'],
                        'amount' => $payment_amount,
                        'items_amount' => $order->info['subtotal'],
                        'shipping_amount' => $order->info['shipping'],
                        'discount_amount' => $discount_amount,
                        'payment_description' => $site_name .
                                                 ' Shopping Cart Order',
                        'buyer_email' => $order_email,
                        'complete_url' => $complete_url,
                        'cancel_url' => $cancel_url);
    $post_array = array_merge($post_array,$items);

    $thebigcoin = new TheBigCoin($order->db);
    $response_data = $thebigcoin->call('/v1/payments',$post_array);
    if (! $response_data) {
       $order->error = $thebigcoin->error;
       $order->errors['CardFailed'] = true;   return;
    }
    $response = json_decode($response_data);
    $errors = $thebigcoin->get_errors($response);
    if ($errors) {
       $order->error = 'TheBigCoin Errors: '.$errors;
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return;
    }
    if (empty($response->data)) {
       $order->error = 'Invalid TheBigCoin Response: '.$response_data;
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return;
    }
    if ($paying_balance)
       $query = 'update orders set payment_data=? where id=?';
    else $query = 'update cart set payment_data=? where id=?';
    $query = $order->db->prepare_query($query,$response_data,$order_id);
    $order->db->log_query($query);
    if (! $order->db->query($query)) {
       $order->error = $order->db->error;
       $order->errors['CardFailed'] = true;
       return;
    }
    $payment_url = $response->data->payment_url;
    header('Location: '.$payment_url);
    exit;
}

function finish_thebigcoin_checkout(&$order,$paying_balance)
{
    if ($paying_balance) {
       $order_id = get_form_field('TheBigCoinOrder');
       $query = 'select payment_data from orders where id=?';
    }
    else {
       $order_id = get_form_field('TheBigCoinCart');
       $query = 'select payment_data from cart where id=?';
    }
    $query = $order->db->prepare_query($query,$order_id);
    $row = $order->db->get_record($query);
    if (empty($row['payment_data'])) {
       if (isset($order->db->error)) $order->error = $order->db->error;
       else $order->error = 'Payment Data not Found';
       $order->errors['CardFailed'] = true;
       $order->errors['error'] = true;   return false;
    }
    $payment_data = json_decode($row['payment_data']);
    $payment_id = $payment_data->data->id;

    $thebigcoin = new TheBigCoin($order->db);
    $path = '/v1/payments/'.$payment_id;
    $response_data = $thebigcoin->call($path);
    if (! $response_data) {
       $order->error = $thebigcoin->error;
       $order->errors['CardFailed'] = true;   return false;
    }
    $response = json_decode($response_data);
    $errors = $thebigcoin->get_errors($response);
    if ($errors) {
       $order->error = 'TheBigCoin Errors: '.$errors;
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return false;
    }
    if (empty($response->data) || empty($response->data->payment_status)) {
       $order->error = 'Invalid TheBigCoin Response: '.$response_data;
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return false;
    }
    $payment_status = $response->data->payment_status;
    if (($payment_status != 'confirmed') && ($payment_status != 'completed')) {
       $order->error = 'Unable to process payment, status = '.$payment_status;
       log_error($order->error);   $order->errors['CardFailed'] = true;
       return false;
    }
    $order->payment['payment_amount'] = $response->data->amount;
    $order->payment['payment_date'] = time();
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    $order->payment['payment_id'] = $response->data->id;
    $order->payment['payment_code'] = $response->data->coin.':' .
                                      $response->data->coin_received_amount;
    $order->payment['payment_ref'] = $response->data->payment_address;
    $order->payment['payment_method'] = 'TheBigCoin';
    log_activity('TheBigCoin Payment Accepted with Payment ID #' .
                 $order->payment['payment_id']);
    return true;
}

function thebigcoin_process_order_button(&$order,$paying_balance)
{
    if (button_pressed('FinishTheBigCoin')) {
       $order->payment_module = 'thebigcoin';
       if (! finish_thebigcoin_checkout($order,$paying_balance)) {
          if ($paying_balance) require 'pay-balance.php';
          else require 'checkout.php';
          exit;
       }
       return true;
    }
    if (button_pressed('TheBigCoinCheckout')) {
       $order->payment_module = 'thebigcoin';
       process_thebigcoin_checkout($order,$paying_balance);
       $order->errors['error'] = true;
       if ($paying_balance) require 'pay-balance.php';
       else require 'checkout.php';
       exit;
    }
    return false;
}

?>
