<?php
/*
                      Inroads Shopping Cart - Zonos API Module

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC
*/

function zonos_payment_cart_config_section($db,$dialog,$values)
{
    $primary_module = call_payment_event('get_primary_module',array($db),
                                         true,true);

    add_payment_section($dialog,'Zonos','zonos',$values);

    $dialog->add_edit_row('Store ID:','zonos_store_id',$values,5);
    $dialog->add_edit_row('API Key:','zonos_api_key',$values,50);
    $dialog->add_edit_row('API Security Token:','zonos_api_token',$values,80);
    $dialog->add_edit_row('Subdomain:','zonos_subdomain',$values,20);
    $dialog->add_edit_row('Hello Site Key:','zonos_hello_key',$values,20);
    $dialog->add_edit_row('Hello Currency Selectors:','zonos_currency_selectors',$values,80);
}

function zonos_payment_update_cart_config_fields(&$cart_config_fields)
{
    global $website_settings;

    $fields = array('zonos_store_id','zonos_api_key','zonos_api_token',
                    'zonos_subdomain','zonos_hello_key',
                    'zonos_currency_selectors');
    if (! empty($website_settings)) $fields[] = 'zonos_active';
    $cart_config_fields = array_merge($cart_config_fields,$fields);
}

function zonos_payment_update_cart_config_field($field_name,
                                                &$new_field_value,$db)
{
    if ($field_name == 'zonos_active') {
       if (get_form_field('zonos_active') == 'on') $new_field_value = '1';
       else $new_field_value = '0';
    }
    else return false;
    return true;
}

function zonos_active($db)
{
    return get_cart_config_value('zonos_active',$db);
}

function log_zonos_activity($activity_msg)
{
    global $activity_log;

    $path_parts = pathinfo($activity_log);
    $zonos_activity_log = $path_parts['dirname'].'/zonos.log';
    $activity_file = @fopen($zonos_activity_log,'at');
    if ($activity_file) {
       fwrite($activity_file,'['.date('D M d Y H:i:s').'] ' .
              $activity_msg."\n");
       fclose($activity_file);
    }
}

function call_zonos($url,$json_obj,$token,&$error)
{
    $json_data = json_encode($json_obj);
    log_zonos_activity('Sent: '.$url.' '.$json_data);

    require_once '../engine/http.php';
    $url = 'https://api.iglobalstores.com'.$url;
    $http = new HTTP($url);
    $http->set_accept('application/json');
    $http->set_content_type('application/json');
    if ($token) $http->set_headers(array('serviceToken: '.$token));
    $response_data = $http->call($json_data);
    if ((! $response_data) && isset($http->response_location)) {
       log_zonos_activity('Redirect: '.$http->response_location);
       $http->set_url($http->response_location);
       $response_data = $http->call($json_data);
    }
    if (! $response_data) {
       $error = 'Zonos Error: '.$http->error.' ('.$http->status.')';
       log_error($error);   log_zonos_activity($error);
       return null;
    }
    $response_data = str_replace("\n",'',$response_data);
    $response_data = str_replace("\r",'',$response_data);

    log_zonos_activity('Response: '.$response_data);
    $zonos_result = json_decode($response_data);
    if (! $zonos_result) $error = 'Invalid Zonos Response: '.$response_data;
    else if (! empty($zonos_result->error))
       $error = 'Zonos Error: '.$zonos_result->error;
    else $error = null;
    if ($error) {
       log_error($error);   log_zonos_activity($error);
       return null;
    }

    return $zonos_result;
}

function build_zonos_items($cart,$checkout=true)
{
    global $ssl_url;

    $db = $cart->db;
    load_order_weight($cart,0);
    load_order_mpns($cart);
    load_order_item_images($cart,'original');
    if ($checkout) load_order_item_urls($cart);

    $index = 1;
    $items = array();
    if (! empty($cart->items)) foreach ($cart->items as $id => $cart_item) {
       $item_description = get_html_product_name($cart_item['product_name'],
                                                 GET_PROD_PAYMENT_GATEWAY,
                                                 $cart,$cart_item);
       $attr_array = $cart_item['attribute_array'];
       if (isset($attr_array) && (count($attr_array) > 0)) {
          $item_description .= ' - ';
          foreach ($attr_array as $attr_index => $attribute) {
             if ($attr_index > 0) $item_description .= ', ';
             $item_description .= $attribute['attr'].': '.$attribute['option'];
          }
       }
       if (empty($cart_item['image'])) $image_url = null;
       else $image_url = $ssl_url.$cart_item['image'];
       $product_id = $cart_item['product_id'];
       $categories = '';   $brand = '';
       if ($product_id) {
          $query = 'select c.name from category_products p left join ' .
                   'categories c on p.parent=c.id where p.related_id=?';
          $query = $db->prepare_query($query,$product_id);
          $rows = $db->get_records($query);
          if ($rows) foreach ($rows as $row) if ($row['name']) {
             if ($categories) $categories .= '|';
             $categories .= $row['name'];
          }
          $query = 'select shopping_brand from products where id=?';
          $query = $db->prepare_query($query,$product_id);
          $row = $db->get_record($query);
          if (! empty($row['shopping_brand'])) $brand = $row['shopping_brand'];
       }

       $item = new StdClass();
       if (! $checkout) $item->cartItemId = $index++;
       if ($checkout) $item->description = $item_description;
       else $item->detailedDescription = $item_description;
       $item->quantity = $cart_item['qty'];
       $item->unitPrice = $cart_item['price'];
       if ($image_url) $item->imageURL = $image_url;
       $item->sku = $cart_item['part_number'];
       if ($checkout) $item->productID = $product_id;
       else $item->productId = $product_id;
       if ($brand) {
          if ($checkout) $item->itemBrand = $brand;
          else $item->brandName = $brand;
       }
       if ($categories) {
          if ($checkout) $item->itemCategory = $categories;
          else $item->category = $categories;
       }
       if ($cart_item['weight']) $item->weight = $cart_item['weight'];
       if ($checkout && $cart_item['url'])
          $item->itemURL = $cart_item['url'];
       $items[] = $item;
    }
    if (isset($cart->info['coupon_amount'],$cart->info['coupon_code']) &&
        $cart->info['coupon_amount']) {
       $description = 'Coupon '.$cart->info['coupon_code'];
       $coupon_amount = $cart->info['coupon_amount'];
       if ($coupon_amount < 0) $coupon_amount = abs($coupon_amount);
       $item = new StdClass();
       if ($checkout) $item->description = $description;
       else $item->detailedDescription = $description;
       $item->unitPrice = -$coupon_amount;
       $item->quantity = 1;
       $item->nonShippable = true;
       $items[] = $item;
    }
    return $items;
}

function zonos_configure_checkout(&$cart)
{
    global $base_url,$ssl_url,$cart_prefix,$continue_shopping_url,$order;
    global $checkout_warning,$canada_provinces;

    if (empty($cart->customer->billing['country'])) return;
    if ($cart->customer->billing['country'] == 1) return;
    if (empty($cart->customer->shipping['country'])) return;
    if ($cart->customer->shipping['country'] == 1) return;

    $db = $cart->db;
    $subdomain = get_cart_config_value('zonos_subdomain',$db);
    if (! $subdomain) {
       $cart->error = 'Missing Zonos Subdomain';   require 'index.php';   exit;
    }
    if ((! empty($cart->errors)) || (! empty($cart->error))) {
       require 'index.php';   exit;
    }
    if ((! empty($order->errors)) || (! empty($order->error))) {
       $cart->errors = $order->errors;   $cart->error = $order->error;
       require 'index.php';   exit;
    }
    if (button_pressed('FinishZonos')) {
       if (! empty($checkout_warning)) $cart->error = $checkout_warning;
       else $cart->error = 'Unknown Zonos Error';
       require 'index.php';   exit;
    }

    require_once '../cartengine/catalog-public.php';

    if (function_exists('get_custom_url_prefix'))
       $url_prefix = get_custom_url_prefix();
    else $url_prefix = '';
    if (function_exists('get_custom_cart_prefix'))
       $cart_prefix = get_custom_cart_prefix();
    else $cart_prefix = '';
    $catalog = new Catalog;
    $catalog->db = $db;
    $current_category_info = $catalog->get_current_category_info();
    if ($current_category_info) {
       $seo_url = $current_category_info['seo_url'];
       if ((! $seo_url) || ($seo_url == ''))
          $seo_url = $current_category_info['id'];
       $continue_url = $base_url.$url_prefix.$seo_url.'/';
    }
    else if (isset($continue_shopping_url))
       $continue_url = $continue_shopping_url;
    else $continue_url = $base_url.$url_prefix.'index.html';
    $confirm_url = $ssl_url.$cart_prefix.'cart/' .
                   $cart->process_module.'?FinishZonos=Go';

    $json_obj = new StdClass();
    $json_obj->storeId = get_cart_config_value('zonos_store_id',$db);
    $json_obj->referenceId = $cart->id;
    $json_obj->contShoppingURL = $continue_url;
    $json_obj->externalConfirmationPageURL = $confirm_url;
    if (! empty($cart->payment_types[1])) $json_obj->misc2 = '55';
    $json_obj->items = build_zonos_items($cart);

    $zonos_result = call_zonos('/v1/createTempCart',$json_obj,null,$error);
    if (! $zonos_result) {
       $cart->error = $error;   require 'index.php';   exit;
    }
    if (empty($zonos_result->tempCartUUID)) {
       $cart->error = 'Missing Zonos Cart UUID';   require 'index.php';   exit;
    }
    $cart_uuid = $zonos_result->tempCartUUID;
    $customer = $cart->customer;
    $country_info = get_country_info($customer->shipping['country'],$db);
    $country_code = $country_info['code'];
    $name = $customer->info['fname'].' '.$customer->info['lname'];

    $zonos_url = 'https://'.$subdomain.'.iglobalstores.com/?tempCartUUID=' .
                 $cart_uuid.'&country='.$country_code.'&customerName=' .
                 urlencode($name).'&customerEmail=' .
                 urlencode($customer->info['email']);
    if (! empty($customer->shipping['company']))
       $zonos_url .= '&customerCompany=' .
                     urlencode($customer->shipping['company']);
    else if (! empty($customer->info['company']))
       $zonos_url .= '&customerCompany='.urlencode($customer->info['company']);
    if (! empty($customer->billing['phone']))
       $zonos_url .= '&customerPhone='.urlencode($customer->billing['phone']);
    if (! empty($customer->billing['mobile']))
       $zonos_url .= '&customerAltPhone=' .
                     urlencode($customer->billing['mobile']);
    if (! empty($customer->shipping['address1']))
       $zonos_url .= '&customerAddress1=' .
                     urlencode($customer->shipping['address1']);
    if (! empty($customer->shipping['address2']))
       $zonos_url .= '&customerAddress2=' .
                     urlencode($customer->shipping['address2']);
    if (! empty($customer->shipping['city']))
       $zonos_url .= '&customerCity='.urlencode($customer->shipping['city']);
    if (! empty($customer->shipping['state'])) {
       $state = $customer->shipping['state'];
       if ($state && ($customer->shipping['country'] == 43) &&
           (! empty($canada_provinces[$state])))
          $state = $canada_provinces[$state];
    }
    else $state = null;
    if ($state)
       $zonos_url .= '&customerState='.urlencode($state);
    if (! empty($customer->shipping['zipcode']))
       $zonos_url .= '&customerZip='.urlencode($customer->shipping['zipcode']);

    $zonos_url .= '&billingName='.urlencode($name);
    if (! empty($customer->info['company']))
       $zonos_url .= '&billingCompany='.urlencode($customer->info['company']);
    $zonos_url .= '&billingEmail='.urlencode($customer->info['email']);
    if (! empty($customer->billing['phone']))
       $zonos_url .= '&billingPhone='.urlencode($customer->billing['phone']);
    if (! empty($customer->billing['mobile']))
       $zonos_url .= '&billingAltPhone=' .
                     urlencode($customer->billing['mobile']);
    if (! empty($customer->billing['address1']))
       $zonos_url .= '&billingAddress1=' .
                     urlencode($customer->billing['address1']);
    if (! empty($customer->billing['address2']))
       $zonos_url .= '&billingAddress2='.
                     urlencode($customer->billing['address2']);
    if (! empty($customer->billing['city']))
       $zonos_url .= '&billingCity='.urlencode($customer->billing['city']);
    if (! empty($customer->billing['state'])) {
       $state = $customer->billing['state'];
       if ($state && ($customer->billing['country'] == 43) &&
           (! empty($canada_provinces[$state])))
          $state = $canada_provinces[$state];
    }
    else $state = null;
    if ($state)
       $zonos_url .= '&billingState='.urlencode($state);
    if (! empty($customer->billing['zipcode']))
       $zonos_url .= '&billingZip='.urlencode($customer->billing['zipcode']);
    $country_info = get_country_info($customer->billing['country'],$db);
    $country_code = $country_info['code'];
    $zonos_url .= '&billingCountry='.$country_code;

    header('Location: '.$zonos_url);
    log_zonos_activity('Redirect: '.$zonos_url);
    exit;
}

function finish_zonos_checkout(&$order)
{
    $order_id = get_form_field('orderId');

    $db = $order->db;
    $json_obj = new StdClass();
    $json_obj->store = get_cart_config_value('zonos_store_id',$db);
    $json_obj->secret = get_cart_config_value('zonos_api_key',$db);
    $json_obj->orderId = $order_id;
    $zonos_result = call_zonos('/v2/orderDetail',$json_obj,null,$error);
    if (! $zonos_result) {
       $order->error = $error;   log_error($order->error);
       $order->errors['CardFailed'] = true;   return false;
    }
    if (empty($zonos_result->order)) {
       $order->error = 'Missing Zonos Order';   log_error($order->error);
       $order->errors['CardFailed'] = true;   return false;
    }

    $zonos_order = $zonos_result->order;

    if (! empty($zonos_order->billingCompany))
       $order->info['company'] = $zonos_order->billingCompany;
    else if (! empty($zonos_order->company))
       $order->info['company'] = $zonos_order->company;
    if (! empty($zonos_order->email)) 
       $order->info['email'] = $zonos_order->email;
    else if (! empty($zonos_order->billingEmail))
       $order->info['email'] = $zonos_order->billingEmail;
    if (! empty($zonos_order->name)) $name = $zonos_order->name;
    else if (! empty($zonos_order->billingName))
       $name = $zonos_order->billingName;
    else $name = null;
    if ($name) {
       $name_parts = explode(' ',$name);
       if (count($name_parts) == 1) $order->info['lname'] = $name;
       else {
          $order->info['fname'] = $name_parts[0];
          if (count($name_parts) == 2) $order->info['lname'] = $name_parts[1];
          else {
             unset($name_parts[0]);
             $order->info['lname'] = implode(' ',$name_parts);
          }
       }
    }
    if (! empty($zonos_order->foreignCurrencyCode))
       $order->info['currency'] = $zonos_order->foreignCurrencyCode;
    $order->info['subtotal'] = $zonos_order->itemsTotal;
    $order->info['shipping'] = $zonos_order->shippingTotal;
    $order->info['tax'] = $zonos_order->dutyTaxesTotal;
    $order->info['total'] = $zonos_order->grandTotal;
    if (! empty($zonos_order->promoCode)) {
       $order->info['discount_name'] = $zonos_order->promoCode->code;
       $order->info['discount_amount'] = $zonos_order->promoCode->amount;
       $order->info['total'] -= floatval($zonos_order->promoCode->amount);
    }
    if (! empty($zonos_order->shippingCarrierServiceLevel)) {
       switch ($zonos_order->shippingCarrierServiceLevel) {
          case 'UPS_EXPEDITED':
          case 'UPS_WWD_EXP':
             $order->info['shipping_carrier'] = 'ups';
             $order->info['shipping_method'] = '08|UPS Expedited';
             break;
          case 'UPS_EXPRESS_SAVER':
          case 'UPS_WWD_SAVER':
          case 'UPS_WWD_SVR':
             $order->info['shipping_carrier'] = 'ups';
             $order->info['shipping_method'] = '65|UPS Express Saver';
             break;
          case 'UPS_GROUND':
             $order->info['shipping_carrier'] = 'ups';
             $order->info['shipping_method'] = '03|UPS Ground';
             break;
          case 'USPS_PRIORITY_DOMESTIC':
             $order->info['shipping_carrier'] = 'usps';
             $order->info['shipping_method'] = '1|USPS Priority Mail';
             break;
       }
    }
    if (! empty($zonos_order->name))
       $order->customer->shipping['shipto'] = $zonos_order->name;
    if (! empty($zonos_order->company))
       $order->customer->shipping['company'] = $zonos_order->company;
    $country_info = find_country_info($zonos_order->countryCode,$db);
    if ($country_info) {
       $order->customer->shipping['country'] = $country_info['id'];
       $order->shipping_country = $country_info['id'];
    }
    $order->customer->shipping['address1'] = $zonos_order->address1;
    if (! empty($zonos_order->address2))
       $order->customer->shipping['address2'] = $zonos_order->address2;
    $order->customer->shipping['city'] = $zonos_order->city;
    if (! empty($zonos_order->state)) {
       $order->customer->shipping['state'] = $zonos_order->state;
       $order->customer->set('ship_state',$zonos_order->state);
    }
    $order->customer->shipping['zipcode'] = $zonos_order->zip;

    if (! empty($zonos_order->billingPhone))
       $order->customer->billing['phone'] = $zonos_order->billingPhone;
    if (! empty($zonos_order->billingAltPhone))
       $order->customer->billing['mobile'] = $zonos_order->billingAltPhone;
    $country_info = find_country_info($zonos_order->billingCountryCode,$db);
    if ($country_info) {
       $order->customer->billing['country'] = $country_info['id'];
       $order->billing_country = $country_info['id'];
    }
    $order->customer->billing['address1'] = $zonos_order->billingAddress1;
    if (! empty($zonos_order->billingAddress2))
       $order->customer->billing['address2'] = $zonos_order->billingAddress2;
    $order->customer->billing['city'] = $zonos_order->billingCity;
    if (! empty($zonos_order->billingState)) {
       $order->customer->billing['state'] = $zonos_order->billingState;
       $order->customer->set('bill_state',$zonos_order->billingState);
    }
    $order->customer->billing['zipcode'] = $zonos_order->billingZip;

    if (! empty($zonos_order->poNumber)) {
       $order->info['purchase_order'] = $zonos_order->poNumber;
       $order->info['payment_type'] = 1;
    }
    if (! empty($zonos_order->paymentProcessing->paymentGateway))
       $order->payment['payment_method'] =
          $zonos_order->paymentProcessing->paymentGateway;
    else if (! empty($zonos_order->paymentProcessing->paymentProcessor))
       $order->payment['payment_method'] =
          $zonos_order->paymentProcessing->paymentProcessor;
    if (! empty($zonos_order->paymentProcessing->cardType))
       $order->payment['card_type'] = $zonos_order->paymentProcessing->cardType;
    $order->payment['payment_amount'] = $zonos_order->grandTotal;
    $order->payment['payment_date'] = time();
    $order->payment['payment_status'] = PAYMENT_CAPTURED;
    if (! empty($zonos_order->paymentProcessing->transactionId))
       $order->payment['payment_id'] =
          $zonos_order->paymentProcessing->transactionId;
    else $order->payment['payment_id'] = '';
    if (function_exists('update_zonos_order_object') &&
       (! update_zonos_order_object($order,$zonos_order))) return false;
    log_activity('Zonos Payment Accepted with Payment ID #' .
                 $order->payment['payment_id']);
    return true;
}

function zonos_setup_process_order(&$order)
{
    if (button_pressed('FinishZonos')) {
       $_GET['subtotal'] = 0;
       $item_checksum = $order->cart->get_item_checksum();
       $_GET['item_checksum'] = $item_checksum;
    }
}

function zonos_process_order_button(&$order,$paying_balance)
{
    if (button_pressed('FinishZonos')) {
       $order->payment_module = 'zonos';
       if (! finish_zonos_checkout($order)) {
          log_error('Zonos Error: '.$order->error);
          if ($paying_balance) require 'pay-balance.php';
          else require 'index.php';
          exit;
       }
       return true;
    }
    return false;
}

function get_zonos_shipping_quotes($cart,$shipping_address)
{
    global $canada_provinces;

    $db = $cart->db;
    $api_token = get_cart_config_value('zonos_api_token',$db);
    $json_obj = new StdClass();
    $ship_address = new StdClass();
    $ship_address->name = $shipping_address['shipto'];
    $ship_address->address1 = $shipping_address['address1'];
    if (! empty($shipping_address['address2']))
       $ship_address->address2 = $shipping_address['address2'];
    $ship_address->city = $shipping_address['city'];
    $state_code = $shipping_address['state'];
    if ($shipping_address['country_code'] == 'CA') {
       foreach ($canada_provinces as $code => $label) {
          if ($state_code == $label) {
             $state_code = $code;   break;
          }
       }
    }
    $ship_address->stateCode = $state_code;
    $ship_address->postalCode = $shipping_address['zipcode'];
    $ship_address->countryCode = $shipping_address['country_code'];
    $json_obj->shipToAddress = $ship_address;
    $json_obj->items = build_zonos_items($cart,false);

    $zonos_result = call_zonos('/2.0/shipping-quotes',$json_obj,$api_token,
                               $error);
    if (! $zonos_result) {
       log_error($error);   return null;
    }
    return $zonos_result;
}

?>
