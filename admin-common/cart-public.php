<?php
/*
                  Inroads Shopping Cart - Public Cart Functions

                       Written 2008-2019 by Randall Severy
                        Copyright 2008-2019 Inroads, LLC
*/

require_once __DIR__.'/../engine/ui.php';
require_once __DIR__.'/../engine/db.php';
if (file_exists(__DIR__.'/../admin/custom-config.php'))
   require_once __DIR__.'/../admin/custom-config.php';
require_once __DIR__.'/customers-common.php';
require_once __DIR__.'/accounts-common.php';
require_once __DIR__.'/orders-common.php';
require_once __DIR__.'/products-common.php';
require_once __DIR__.'/inventory-common.php';
require_once __DIR__.'/cartconfig-common.php';
require_once __DIR__.'/currency.php';
require_once __DIR__.'/utility.php';

/* Customer Status Option Values */

define('NORMAL_STATUS',0);
define('SUSPENDED_STATUS',1);

/* Customer Status Values */

define('NO_ERROR',0);
define('DB_ERROR',1);
define('EMAIL_NOT_FOUND',2);
define('INVALID_PASSWORD',3);
define('SUSPENDED_ACCOUNT',4);
define('ALREADY_REGISTERED',5);
define('ALREADY_LOGGED_IN',6);
define('MISSING_EMAIL',7);
define('EMPTY_PASSWORD',8);

/* Cart Flags */

define('GUEST_CHECKOUT',1);
define('CART_DELETED',2);
define('SAVED_GUEST_INFO',4);

$cart_globals = array(
   'mobile_cart' => get_cookie('mobile_cart'),
   'enhanced_mobile_cart' => false,
   'enable_rewards' => false,
   'enable_rmas' => false,
   'enable_reminders' => false,
   'enable_reorders' => false,
   'enable_auto_reorders' => false,
   'wishlist_name' => 'WishList',
   'cart_table_cellspacing' => 10,
   'cart_table_width' => '600px',
   'cart_inside_table_width' => '580px',
   'cart_body_class' => 'cart_body',
   'cart_2column_class' => 'equal2',
   'cart_3column_class' => 'equal3',
   'use_theme_cart_classes' => false,
   'billing_title' => 'Billing',
   'shipping_title' => 'Shipping',
   'shipto_title' => 'Ship To',
   'comments_title' => 'Comments',
   'include_middle_initial' => true,
   'include_email_confirm' => true,
   'include_password_confirm' => true,
   'include_fax' => true,
   'include_mobile' => true,
   'show_optional_required' => true,
   'guest_mailing_flag' => false,
   'multiple_customer_accounts' => false,
   'enable_wholesale' => false,
   'log_cart_errors_enabled' => false,
   'hide_checkout_coupon_link' => false,
   'enable_edit_cart_item' => false,
   'enable_multisite' => false,
   'auto_reorder_label' => 'Reorder',
   'enable_continue_no_payment' => true,
   'enable_remember_login' => false,
   'checkout_warning' => null,
   'force_save_card' => null,
   'enable_auto_reorders' => false,
   'force_shipping_same' => false,
   'hide_checkout_billing_info' => false,
   'hide_checkout_shipping_info' => false,
   'default_address_type' => null
);
$config_globals = array('ssl_url','cart_cookie','wishlist_cookie',
   'show_part_number_in_cart','continue_shopping_link_classes',
   'add_to_cart_auto_continue','on_account_products','include_purchase_order',
   'enable_partial_shipments','disable_partial_ship_option',
   'payment_status_values','order_type','orders_table','order_label',
   'reorders_first_shipping_only','base_url','cart_upsell_url',
   'checkout_upsell_url','continue_anchor_button_classes',
   'available_card_types','hide_cart_prices');

foreach ($cart_globals as $variable => $value) {
   global $$variable;
   if (isset($$variable)) $cart_globals[$variable] = $$variable;
   else $$variable = $value;
}
foreach ($config_globals as $variable) {
   global $$variable;
   $cart_globals[$variable] = $$variable;
}

if (! $mobile_cart) $enhanced_mobile_cart = false;

global $auto_reorders_label;
if (! isset($auto_reorders_label))
   $auto_reorders_label = $auto_reorder_label.'s';
$cart_globals['auto_reorders_label'] = $auto_reorders_label;

global $enable_client_rmas;
if (! isset($enable_client_rmas)) $enable_client_rmas = $enable_rmas;
$cart_globals['enable_client_rmas'] = $enable_client_rmas;

global $join_required_fields;
if (! isset($join_required_fields))
   $join_required_fields = array('cust_email','cust_fname',
      'cust_lname','bill_address1','bill_city','bill_country',
      'bill_state','bill_canada_province','bill_zipcode',
      'bill_phone','ship_address1','ship_city','ship_country',
      'ship_state','ship_canada_province','ship_zipcode');

if (! function_exists('T')) { function T($text) { return $text; } }

class Cart {

function __construct($db = null,$cart_id = null,$customer_id = null,
                     $object_only = false,$no_cart_cookie = false)
{
    global $cart_cookie,$wishlist_cookie,$user_cookie,$default_currency;
    global $single_cart_per_customer,$system_disabled,$enable_wholesale;
    global $single_wishlist_per_customer,$enable_rewards,$customer;
    global $multiple_customer_accounts,$account_cookie,$enable_multisite;

    if (! isset($default_currency)) $default_currency = 'USD';
    if (! isset($system_disabled)) $system_disabled = false;
    if ($db) $this->db = $db;
    else $this->db = new DB;
    if ((! $this->db) || isset($this->db->error)) {
       if ($this->db) $this->error = $this->db->error;
       $this->system_disabled = true;   $this->flags = 0;   return;
    }
    $this->system_disabled = $system_disabled;
    if ($this instanceof WishList) {
       $this->wishlist = true;
       $this->label = 'WishList';
       $this->cookie = $wishlist_cookie;
       $this->main_table = 'wishlist';
       $this->item_table = 'wishlist_items';
       $this->attr_table = 'wishlist_attributes';
       if (! isset($single_wishlist_per_customer)) $this->single = false;
       else $this->single = $single_wishlist_per_customer;
    }
    else {
       $this->wishlist = false;
       $this->label = 'Cart';
       $this->cookie = $cart_cookie;
       $this->main_table = 'cart';
       $this->item_table = 'cart_items';
       $this->attr_table = 'cart_attributes';
       if (! isset($single_cart_per_customer)) $this->single = false;
       else $this->single = $single_cart_per_customer;
    }
    $this->errors = array();
    $this->features = get_cart_config_value('features',$this->db);
    $this->info = array();
    $this->currency = $default_currency;
    $this->shipping_columns = 5;
    $this->object_only = $object_only;
    $this->no_cart_cookie = $no_cart_cookie;
    $this->wholesale = false;
    $this->account_id = 0;
    $this->discount = 0;
    $this->flags = 0;
    $this->num_payment_divs = 0;
    $this->comments_field_name = 'comments';
    $this->enable_shipping_options = true;
    $this->enable_edit_addresses = true;
    $this->account_required = false;
    $this->enable_echecks = false;
    $this->payment_customer_info = false;
    $this->include_purchase_order = false;
    $this->enable_rewards = $enable_rewards;
    $this->checkout_module = 'checkout.php';
    $this->process_module = 'process-order.php';
    $this->multisite_cookies = null;
    $this->registry = false;
    $this->free_shipping = false;
    $this->free_shipping_items = array();
    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       return;
    }
    if ($enable_multisite) {
       $website_settings = get_website_settings($this->db);
       if ($website_settings & WEBSITE_SHARED_CART) $this->shared_cart = true;
       else $this->shared_cart = false;
       $this->website = get_current_website($this->db);
    }
    else {
       $this->shared_cart = false;
       $this->website = null;
    }
    if ($enable_wholesale) {
       if (! $customer_id) {
          $customer_id = get_cookie($user_cookie);
          if (! is_numeric($customer_id)) $customer_id = null;
       }
       if ((! $customer_id) && (! empty($customer->id)))
          $customer_id = $customer->id;
       if ($customer_id) {
          if ($multiple_customer_accounts) {
             $this->account_id = get_cookie($account_cookie);
             if ((! $this->account_id) && (! empty($customer->account_id)))
                $this->account_id = $customer->account_id;
          }
          else {
             $query = 'select account_id from customers where id=?';
             $query = $this->db->prepare_query($query,$customer_id);
             $row = $this->db->get_record($query);
             if ($row && $row['account_id'])
                $this->account_id = $row['account_id'];
          }
          if ($this->account_id) {
             $this->wholesale = true;
             $query = 'select status,discount_rate from accounts where id=?';
             $query = $this->db->prepare_query($query,$this->account_id);
             $row = $this->db->get_record($query);
             if ($row) {
                if ($row['status'] != 0) $this->wholesale = false;
                else if ($row['discount_rate'])
                   $this->discount = floatval($row['discount_rate']);
             }
          }
       }
    }
    if (! empty($cart_id)) {
       $this->id = $cart_id;
       if ($cart_id != -1) {
          if ((! $object_only) && (! $no_cart_cookie))
             $this->set_cookie($this->cookie,$this->id);
          if ($this->initialize_cart()) return;
       }
    }
    else if (! $no_cart_cookie) {
       $this->id = get_cookie($this->cookie);
       if (! is_numeric($this->id)) $this->id = null;
       else if ($this->initialize_cart()) {
          if (! ($this->flags & CART_DELETED)) return;
       }
    }
    if (! isset($customer_id)) {
       $customer_id = get_cookie($user_cookie);
       if (! is_numeric($customer_id)) $customer_id = null;
    }
    if ($this->single && isset($customer_id)) {
       if (function_exists('custom_cart_cookie_query'))
          $query = custom_cart_cookie_query(null,$customer_id);
       else {
          $query = 'select id from cart where customer_id=?';
          $query = $this->db->prepare_query($query,$customer_id);
       }
       $row = $this->db->get_record($query);
       if ($row) {
          $this->id = $row['id'];
          if (! $object_only) {
             if (! $no_cart_cookie) $this->set_cookie($this->cookie,$this->id);
             set_cart_remote_user($this->id,$customer_id,$this->db);
             $activity = 'Loaded '.$this->label.' #'.$this->id;
             log_activity($activity.' for Customer #'.$customer_id);
             write_customer_activity($activity,$customer_id,$this->db);
          }
          if ($this->initialize_cart()) {
             if (! ($this->flags & CART_DELETED)) return;
          }
       }
       else if (isset($this->db->error)) {
          $this->error = $this->db->error;
          if ((! $object_only) && (! $no_cart_cookie)) {
             $cart_id = get_cookie($this->cookie);
             if ($cart_id) $this->set_cookie($this->cookie,0);
          }
          $this->id = -1;   return;
       }
    }
    $current_time = time();
    if (isset($_SERVER['REMOTE_ADDR'])) $ip_address = $_SERVER['REMOTE_ADDR'];
    else $ip_address = null;
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
       $user_agent = $_SERVER['HTTP_USER_AGENT'];
       if (strlen($user_agent) > 255) $user_agent = substr($user_agent,0,255);
    }
    else $user_agent = null;
    if (! $object_only) {
       $cart_record = cart_record_definition();
       $cart_record['create_date']['value'] = $current_time;
       $cart_record['ip_address']['value'] = $ip_address;
       $cart_record['user_agent']['value'] = $user_agent;
       $cart_record['currency']['value'] = $default_currency;
       $cart_record['website']['value'] = $this->website;
       if (isset($customer_id))
          $cart_record['customer_id']['value'] = $customer_id;
       if (function_exists('custom_init_cart'))
          custom_init_cart($this,$cart_record);
       if (! $this->db->insert($this->main_table,$cart_record)) {
          $this->id = -1;  $this->error = $this->db->error;
          if (! $no_cart_cookie) {
             $cart_id = get_cookie($this->cookie);
             if ($cart_id) $this->set_cookie($this->cookie,0);
          }
          $this->id = -1;   return;
       }
       $this->id = $this->db->insert_id();
    }
    $this->info['currency'] = $default_currency;
    $this->info['create_date'] = $current_time;
    $this->info['ip_address'] = $ip_address;
    $this->info['user_agent'] = $user_agent;
    if (isset($customer_id)) $this->info['customer_id'] = $customer_id;
    $this->num_items = 0;
    if ((! $object_only) && (! $no_cart_cookie))
       $this->set_cookie($this->cookie,$this->id);
    setup_exchange_rate($this);
    if (! $object_only) {
       $activity = 'Created New '.$this->label.' #'.$this->id;
       $log_string = $activity;
       if (isset($customer_id)) {
          $log_string .= ' for Customer #'.$customer_id;
          set_cart_remote_user($this->id,$customer_id,$this->db);
          write_customer_activity($activity,$customer_id,$this->db);
       }
       else set_cart_remote_user($this->id,null,$this->db);
       log_activity($log_string);
    }
}

function Cart($db = null,$cart_id = null,$customer_id = null,
              $object_only = false,$no_cart_cookie = false)
{
    self::__construct($db,$cart_id,$customer_id,$object_only,$no_cart_cookie);
}

function initialize_cart()
{
    global $default_currency;

    if (! $this->id) return true;
    if (! $this->object_only) set_cart_remote_user($this->id,null,$this->db);
    $query = 'select *,(select count(id) from '.$this->item_table .
             ' where parent=c.id) as num_items from '.$this->main_table .
             ' c where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $result = $this->db->query($query);
    if ($result && ($this->db->num_rows($result) > 0)) {
       $this->info = $this->db->fetch_assoc($result);
       $this->db->free_result($result);
       $this->num_items = $this->info['num_items'];
       if (isset($this->info['registry_id']) && ($this->info['registry_id']))
          $this->registry = true;
       else $this->registry = false;
       $this->currency = $this->info['currency'];
       if (($this->num_items == 0) && isset($default_currency) &&
           ($default_currency != $this->currency))
          $this->set_currency($default_currency);
       setup_exchange_rate($this);
       $this->flags = intval($this->info['flags']);
       return true;
    }
    if ($result) $this->db->free_result($result);
    if (isset($this->db->error)) {
       $this->error = $this->db->error;
       if (! $this->object_only) {
          $cart_id = get_cookie($this->cookie);
          if ($cart_id) $this->set_cookie($this->cookie,0);
       }
       $this->id = -1;   return true;
    }
    return false;
}

function get_id()
{
    global $cart_cookie;

    if (isset($this)) {
       if ($this->no_cart_cookie) {
          if (isset($this->id)) return $this->id;
          else return null;
       }
       $cart_id = get_cookie($this->cookie);
       if (! is_numeric($cart_id)) $cart_id = null;
       return $cart_id;
    }
    $cart_id = get_cookie($cart_cookie);
    if (! is_numeric($cart_id)) $cart_id = null;
    if ((! $cart_id) && isset($this) && isset($this->id)) return $this->id;
    return $cart_id;
}

function set_cookie($cookie_name,$cookie_value,$num_days=100)
{
    global $cart_cookie_expiration;

    if (! $cookie_value) {
       if (headers_sent($file,$line)) {
          log_error('Unable to delete cookie '.$cookie_name .
                    ' since headers were already sent by ' .
                    $file.' line '.$line);
          log_request_data();
       }
       else setcookie($cookie_name,$cookie_value,time() - 3600,'/');
    }
    else {
       if (! empty($cart_cookie_expiration))
          $expires = $cart_cookie_expiration;
       else $expires = (86400 * $num_days);
       if (headers_sent($file,$line)) {
          log_error('Unable to set cookie '.$cookie_name.' to '.$cookie_value .
                    ' since headers were already sent by ' .
                    $file.' line '.$line);
          log_request_data();
       }
       else {
          if ($expires) $expire_time = time() + $expires;
          else $expire_time = 0;
          setcookie($cookie_name,$cookie_value,$expire_time,'/');
       }
    }
    if (empty($this->shared_cart)) return;
    if ($this->multisite_cookies) $this->multisite_cookies .= '&';
    if ($cookie_value) $cookie_value .= '|'.$expires;
    $this->multisite_cookies .= urlencode($cookie_name).'=' .
                                urlencode($cookie_value);
}

function set_multisite_cookies($multisite_cookies,$db)
{
    global $website_id,$ssl_url,$prefix;

    if (! $multisite_cookies) return false;
    $query = 'select id,domain from web_sites order by id';
    $web_sites = $db->get_records($query);
    if (! $web_sites) return;
    $url_parts = explode('/',$ssl_url);
    $protocol = $url_parts[0];   $cookies_set = false;
    foreach ($web_sites as $row) {
       if ($row['id'] == $website_id) continue;
       $url = $protocol.'//'.$row['domain'].'/'.$prefix .
              'cartengine/cart-public.php?ajaxcmd=setcookies&'.
              $multisite_cookies;
       print '  <div style="display:none;"><img src="'.$url.'"></div>'."\n";
       $cookies_set = true;
    }
    return $cookies_set;
}

function set_tax_shipping_flag()
{
    print '<script>tax_shipping = true;</script>'."\n";
}

function check_id($method_name)
{
    if ((! isset($this->id)) || (! is_numeric($this->id))) {
       if (get_form_field('SmartView') == 'Yes') return false;
       if (! isset($this->id))
          log_error('Invalid Cart ID (null) in '.$method_name);
       else log_error('Invalid Cart ID ('.$this->id.') in '.$method_name);
       log_request_data();   $this->error = 'Invalid Cart ID';   return false;
    }
    return true;
}

function set_currency($currency)
{
    if (! $this->check_id('cart->set_currency')) return false;
    if (($currency != $this->currency) && ($this->num_items > 0)) {
       $this->error = 'You can not add an item to the cart using the '.$currency .
                      ' currency, since the cart already has items using the ' .
                      $this->currency.' currency.';
       return false;
    }
    change_currency($this,$currency);
    $query = 'update '.$this->main_table.' set currency=? where id=?';
    $query = $this->db->prepare_query($query,$currency,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function set_registry_id($registry_id)
{
    if (! $this->check_id('cart->set_registry_id')) return false;
    $this->info['registry_id'] = $registry_id;
    $query = 'update '.$this->main_table.' set registry_id=? where id=?';
    $query = $this->db->prepare_query($query,$registry_id,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function check_auto_reorders()
{
    global $enable_auto_reorders,$cart_cookie;

    if (empty($enable_auto_reorders)) return false;
    if (isset($this) && ($this instanceof Cart)) {
       $db = $this->db;
       if (! isset($this->id)) return false;
       $cart_id = $this->id;
       if (isset($this->items) && is_array($this->items)) {
          foreach ($this->items as $cart_item) {
             if (! empty($cart_item['reorder_frequency'])) return true;
          }
          return false;
       }
    }
    else {
       $db = new DB;
       $cart_id = get_cookie($cart_cookie);
    }
    if (! $cart_id) return false;
    $query = 'select count(id) as num_items from cart_items where (parent=?) ' .
             'and (reorder_frequency!=0) and (not isnull(reorder_frequency))';
    $query = $db->prepare_query($query,$cart_id);
    $row = $db->get_record($query);
    if (empty($row['num_items'])) return false;
    return true;
}

function save_guest_info()
{
    if (! $this->check_id('cart->save_guest_info')) return false;
    $flags = $this->flags|GUEST_CHECKOUT|SAVED_GUEST_INFO;
    $this->info['flags'] = $flags;
    $this->flags = $flags;
    if (isset($_SERVER['REQUEST_METHOD']))
       $method = $_SERVER['REQUEST_METHOD'];
    else $method = 'GET';
    if ($method == 'POST') $cart_data = serialize($_POST);
    else $cart_data = serialize($_GET);
    $query = 'update cart set cart_data=?,flags=? where id=?';
    $query = $this->db->prepare_query($query,$cart_data,$flags,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function load_guest_info()
{
    if (! $this->check_id('cart->load_guest_info')) return false;
    if (isset($_SERVER['REQUEST_METHOD']))
       $method = $_SERVER['REQUEST_METHOD'];
    else $method = 'GET';
    $query = 'select cart_data from cart where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (! $row) {
       log_error('Cart Not Found for Cart ID #'.$this->id);
       $this->error = 'Invalid Cart';   return false;
    }
    if (! $row['cart_data']) {
       log_error('Cart Data Not Found for Cart ID #'.$this->id);
       $this->error = 'Invalid Cart Data';   return false;
    }
    if ($method == 'POST')
       $_POST = array_merge($_POST,unserialize($row['cart_data']));
    else $_GET = array_merge($_GET,unserialize($row['cart_data']));
}

function set_flags($flags)
{
    if (! $this->check_id('cart->set_flags')) return false;
    $this->info['flags'] = $flags;
    $this->flags = $flags;
    $query = 'update '.$this->main_table.' set flags=? where id=?';
    $query = $this->db->prepare_query($query,$flags,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function write_amount($amount,$use_cents_flag=false,$write_output=true)
{
    global $amount_cents_flag,$default_currency;

    if (isset($use_cents_flag) && $use_cents_flag) {
       if (isset($amount_cents_flag) && (! $amount_cents_flag)) $precision = 0;
       else $precision = 2;
    }
    else $precision = 2;
    if (isset($this,$this->currency,$this->exchange_rate)) {
       $currency = $this->currency;   $exchange_rate = $this->exchange_rate;
    }
    else {
       if (! isset($default_currency)) $default_currency = 'USD';
       $currency = $default_currency;   $exchange_rate = 1.0;
    }
    $output = format_amount($amount,$currency,$exchange_rate,$precision);
    if ($write_output) print $output;
    return $output;
}

function load_item_price($product_id,$qty,$attributes,&$price,&$flags,
   $no_options,&$wholesale_discount=null,$account_id=null)
{
    global $enable_wholesale,$user_cookie,$qty_pricing_by_product;
    global $multiple_customer_accounts,$account_cookie;
    global $log_cart_errors_enabled;

    if (! $product_id) {
       $error = 'Product ID not specified in load_item_price';
       if (isset($this)) $this->error = $error;
       log_error($error);   log_request_data();   $price = null;   return;
    }
    if (isset($this)) {
       $db = $this->db;
       $features = $this->features;
       $wholesale = $this->wholesale;
       $account_id = $this->account_id;
       $account_discount = $this->discount;
    }
    else {
       $db = new DB;
       $features = get_cart_config_value('features',$db);
       $wholesale = false;   $account_discount = 0;
       if ($enable_wholesale) {
          if ($account_id !== null) {
             $customer_id = get_cookie($user_cookie);
             if ($customer_id) {
                if ($multiple_customer_accounts)
                   $account_id = get_cookie($account_cookie);
                else {
                   $query = 'select account_id from customers where id=?';
                   $query = $db->prepare_query($query,$customer_id);
                   $row = $db->get_record($query);
                   if ($row && $row['account_id'])
                      $account_id = $row['account_id'];
                }
             }
          }
          if ($account_id) {
             $wholesale = true;
             $query = 'select status,discount_rate from accounts where id=?';
             $query = $db->prepare_query($query,$account_id);
             $row = $db->get_record($query);
             if ($row) {
                if ($row['status'] != 0) $wholesale = false;
                else if ($row['discount_rate'])
                   $account_discount = floatval($row['discount_rate']);
             }
          }
       }
    }
    $wholesale_discount = $account_discount;
    if ((! $db) || isset($db->error)) $db_error = true;
    else $db_error = false;
    if ($wholesale && (! $db_error)) {
       $query = 'select account_discount,flags from products where id=?';
       $query = $db->prepare_query($query,$product_id);
       $row = $db->get_record($query);
       if ((! empty($row['flags'])) && ($row['flags'] & NO_ACCOUNT_DISCOUNTS))
          $wholesale = false;
       else if (! empty($row['account_discount']))
          $wholesale_discount = floatval($row['account_discount']);
    }
    $inv_id = null;
    if ($features & REGULAR_PRICE_BREAKS) {
       $query = 'select price_break_type,price_breaks from products where id=?';
       $query = $db->prepare_query($query,$product_id);
       $row = $db->get_record($query);
       if ($row) {
          if ($row['price_break_type'] == 1) $flags |= QTY_PRICE;
          $price = 0;
          $price_entries = explode('|',$row['price_breaks']);
          $num_entries = count($price_entries);
          for ($loop = 0;  $loop < $num_entries;  $loop++) {
             if ($price_entries[$loop] == '') continue;
             $price_details = explode('-',$price_entries[$loop]);
             if (($qty >= $price_details[0]) && ($qty <= $price_details[1])) {
                $price = $price_details[2];   break;
             }
          }
       }
       else $db_error = true;
    }
    else {
       if ($features & (REGULAR_PRICE_INVENTORY|LIST_PRICE_INVENTORY|
                        SALE_PRICE_INVENTORY)) {
          $query = 'select id,price,list_price,sale_price ' .
                   'from product_inventory where parent=?';
          if ($no_options) $attributes = explode('|',$attributes);
          else $attributes = explode('-',$attributes);
          $lookup_attributes = build_lookup_attributes($db,$product_id,
                                  $attributes,false,$no_options,false);
          if ($lookup_attributes) {
             $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
                $product_id,$no_options,$db);
             if ($lookup_attributes) $query .= ' and attributes=?';
          }
          else if (isset($this) && $this->wishlist) $query .= ' limit 1';
          else $query .= ' and ((attributes="") or isnull(attributes))';
          if ($lookup_attributes)
             $query = $db->prepare_query($query,$product_id,$lookup_attributes);
          else $query = $db->prepare_query($query,$product_id);
          $inv_row = $db->get_record($query);
          if ($inv_row) $inv_id = $inv_row['id'];
          else $db_error = true;
       }
       if ($features & (REGULAR_PRICE_PRODUCT|LIST_PRICE_PRODUCT|
                        SALE_PRICE_PRODUCT)) {
          $query = 'select price,list_price,sale_price from products where id=?';
          $query = $db->prepare_query($query,$product_id);
          $prod_row = $db->get_record($query);
          if (! $prod_row) $db_error = true;
       }
       if (! $db_error) {
          if (($features & SALE_PRICE_INVENTORY) &&
              (! empty($inv_row['sale_price'])) &&
              floatval($inv_row['sale_price'])) {
             $price = $inv_row['sale_price'];   $flags |= SALE_PRICE;
          }
          else if (($features & SALE_PRICE_PRODUCT) &&
                   (! empty($prod_row['sale_price'])) &&
                   floatval($prod_row['sale_price'])) {
             $price = $prod_row['sale_price'];   $flags |= SALE_PRICE;
          }
          else if (($features & REGULAR_PRICE_INVENTORY) &&
                   (! empty($inv_row['price'])) && floatval($inv_row['price']))
             $price = $inv_row['price'];
          else if (($features & REGULAR_PRICE_PRODUCT) &&
                   (! empty($prod_row['price'])) &&
                   floatval($prod_row['price']))
             $price = $prod_row['price'];
          else if (($features & REGULAR_PRICE_PRODUCT) &&
                   ($prod_row['price'] == '0.00')) $price = $prod_row['price'];
          else if (($features & LIST_PRICE_INVENTORY) &&
                   (! empty($inv_row['list_price'])) &&
                   floatval($inv_row['list_price']))
             $price = $inv_row['price'];
          else if (($features & LIST_PRICE_PRODUCT) &&
                   (! empty($prod_row['list_price'])) &&
                   floatval($prod_row['list_price']))
             $price = $prod_row['price'];
          else {
             $price = null;
             if ($log_cart_errors_enabled)
                log_cart_error('Cart Error: No Price found for Product #' .
                               $product_id);
          }
       }
    }
    if ($db_error) {
       if ($db && isset($db->error)) {
          if (isset($this)) $this->error = $db->error;
       }
       $price = null;   return;
    }
    if ($wholesale) {
       $inv_discount = null;
       if ($inv_id) {
          $query = 'select discount from account_inventory where parent=? ' .
                   'and related_id=?';
          $query = $db->prepare_query($query,$account_id,$inv_id);
          $row = $db->get_record($query);
          if (! empty($row['discount']))
             $inv_discount = floatval($row['discount']);
          if ($inv_discount) {
             $wholesale_discount = $inv_discount;
             $factor = (100 - $inv_discount) / 100;
             $price = round($price * $factor,2);
          }
       }
       if (! $inv_discount) {
          $query = 'select price,discount from account_products where ' .
                   'parent=? and related_id=?';
          $query = $db->prepare_query($query,$account_id,$product_id);
          $row = $db->get_record($query);
          $price = get_account_product_price($price,$row,$wholesale_discount);
       }
    }
    if (($features & (QTY_DISCOUNTS|QTY_PRICING)) && ($qty > 1)) {
       if ($wholesale) $discount_type = 1;
       else $discount_type = 0;
       if (! empty($qty_pricing_by_product)) {
          $discount_qty = 0;
          if (empty($this->items)) $discount_qty = $qty;
          else foreach ($this->items as $cart_item) {
             if ($cart_item['product_id'] == $product_id)
                $discount_qty += $cart_item['qty'];
          }
       }
       else $discount_qty = $qty;
       $query = 'select discount from product_discounts where (parent=?) ' .
                'and (discount_type=?) and (start_qty<=?) and ((end_qty>=?) ' .
                'or isnull(end_qty))';
       $query = $db->prepare_query($query,$product_id,$discount_type,
                                   $discount_qty,$discount_qty);
       $row = $db->get_record($query);
       if ($row && $row['discount']) {
          if ($features & QTY_DISCOUNTS) {
             $factor = (100 - $row['discount']) / 100;
             $price = round($price * $factor,2);
          }
          else $price = $row['discount'];
       }
    }
    if (function_exists('custom_update_item_price'))
       custom_update_item_price($db,$product_id,$qty,$attributes,$price,$flags,
                                $no_options,$account_id,$wholesale);
}

function load_item_part_number($product_id,$attributes,&$part_number,
                               $no_options)
{
    if (! $product_id) {
       $error = 'Product ID not specified in load_item_part_number';
       if (isset($this)) $this->error = $error;
       log_error($error);   log_request_data();   $part_number = null;
       return;
    }
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    $query = 'select part_number from product_inventory where parent=?';
    if ($no_options) $attributes = explode('|',$attributes);
    else $attributes = explode('-',$attributes);
    $lookup_attributes = build_lookup_attributes($db,$product_id,
                            $attributes,false,$no_options,false);
    if ($lookup_attributes) {
       $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
          $product_id,$no_options,$db);
       if ($lookup_attributes) $query .= ' and attributes=?';
    }
    else if (isset($this) && $this->wishlist) $query .= ' limit 1';
    else $query .= ' and ((attributes="") or isnull(attributes))';
    if ($lookup_attributes)
       $query = $db->prepare_query($query,$product_id,$lookup_attributes);
    else $query = $db->prepare_query($query,$product_id);
    $inv_row = $db->get_record($query);
    if ($inv_row) {
       $part_number = $inv_row['part_number'];
       if (! $part_number) $part_number = '';
    }
    else if (isset($db->error)) {
       if (isset($this)) $this->error = $db->error;
       $part_number = null;
    }
    else $part_number = '';
}

function validate_product_id($product_id)
{
    if ((! is_numeric($product_id)) || (! $product_id)) {
//       log_error('Invalid Product ID ('.$product_id.')');
       $this->error = 'Invalid Product';   return false;
    }
    $query = 'select id from products where id=?';
    $query = $this->db->prepare_query($query,$product_id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   return false;
       }
       else {
//          log_error('Invalid Product ID ('.$product_id.')');
          $this->error = 'Invalid Product';   return false;
       }
    }
    return true;
}

function validate_quantity($qty)
{
    if ((! is_numeric($qty)) || ($qty <= 0)) {
//       log_error('Invalid Quantity ('.$qty.')');
       $this->error = 'Invalid Quantity';   return false;
    }
    return true;
}

function load_item_details($product_id,$qty,$attributes,$no_options,
                           &$attribute_names,&$attribute_prices,&$price,
                           &$part_number,&$flags,$account_id=null)
{
    global $enable_wholesale;

    if ((! empty($attributes)) && (! $no_options)) {
       if (isset($this)) $db = $this->db;
       else $db = new DB;
       if ((! $db) || isset($db->error)) {
          if ($db && isset($this)) $this->error = $db->error;
          $attribute_names = null;    $price = null;   return false;
       }
       $old_attribute_names = explode('|',$attribute_names);
       $attribute_info = array();   $option_ids = array();
       foreach ($attributes as $id) {
          if (isset($id[0]) && ($id[0] == '*')) continue;
          if (strpos($id,'^') !== false) {
             $ids = explode('^',$id);
             foreach ($ids as $id) {
                if ((! is_numeric($id)) || (! $id)) continue;
                $option_ids[] = $id;
             }
          }
          else {
             if ((! is_numeric($id)) || (! $id)) continue;
             $option_ids[] = $id;
          }
       }

       if (count($option_ids) != 0) {
          $query = 'select o.id,a.name as attr_name,a.display_name as ' .
             'attr_display_name,a.order_name as attr_order_name,' .
             'a.sub_product,a.dynamic,o.name as option_name,o.order_name as ' .
             'option_order_name,o.adjust_type,o.adjustment,' .
             'o.price_break_type,o.price_breaks from attributes a left ' .
             'join attribute_options o on a.id=o.parent where o.id in (?)';
          $query = $db->prepare_query($query,$option_ids);
          $attribute_info = $db->get_records($query,'id');
          if ((! $attribute_info) || (count($attribute_info) == 0)) {
             if (isset($db->error)) {
                if (isset($this)) $this->error = $db->error;
             }
             else log_error('No attribute options found for item attributes');
             $attribute_names = null;    $price = null;   return false;
          }
       }

       foreach ($attributes as $id) {
          if (isset($id[0]) && ($id[0] == '*')) {
             $attr_info = explode('*',$id);
             if (count($attr_info) != 3) continue;
             $query = 'select name as attr_name,display_name as attr_display_name' .
                      ',order_name as attr_order_name,flags as attr_flags ' .
                      'from attributes where id=?';
             $query = $db->prepare_query($query,$attr_info[1]);
             $row = $db->get_record($query);
             if (! $row) continue;
             if ($attr_info[2]) $row['option_name'] = $attr_info[2];
             else if (empty($old_attribute_names)) {
                $field_name = 'attr_'.$index.'_data';
                $row['option_name'] = get_form_field($field_name);
                if ($row['option_name'] === null) {
                   $field_name = 'attr_'.$index.'_'.$product_id.'_data';
                   $row['option_name'] = get_form_field($field_name);
                }
             }
             $row['sub_product'] = 0;
             $attribute_info[$id] = $row;
          }
       }

       $attribute_names = '';   $inventory_attributes = $attributes;
       foreach ($attributes as $index => $id) {
          $name_offset = ($index * 2);   $value_offset = ($index * 2) + 1;
          if ($index > 0) $attribute_names .= '|';
          if (! isset($attribute_info[$id])) {
             if (isset($old_attribute_names[$name_offset]))
                $attribute_names .= $old_attribute_names[$name_offset];
             $attribute_names .= '|';
             if (isset($old_attribute_names[$value_offset]))
                $attribute_names .= $old_attribute_names[$value_offset];
             unset($inventory_attributes[$index]);   continue;
          }
          $names = $attribute_info[$id];
          if (! empty($names['attr_order_name']))
             $attr_name = $names['attr_order_name'];
          else if (! empty($names['attr_display_name']))
             $attr_name = $names['attr_display_name'];
          else $attr_name = $names['attr_name'];
          if ($attr_name) $attribute_names .= $attr_name;
          else if (isset($old_attribute_names[$name_offset]))
             $attribute_names .= $old_attribute_names[$name_offset];
          $attribute_names .= '|';
          if (! empty($names['option_order_name']))
             $attribute_names .= $names['option_order_name'];
          else if (! empty($names['option_name']))
             $attribute_names .= $names['option_name'];
          else if (isset($old_attribute_names[$value_offset]))
             $attribute_names .= $old_attribute_names[$value_offset];
          if (($names['sub_product'] != 1) && ($names['dynamic'] != 1))
             unset($inventory_attributes[$index]);
       }
       $attribute_value = implode('-',$inventory_attributes);
    }
    else if ($no_options && isset($attributes)) {
       if (isset($this)) $db = $this->db;
       else $db = new DB;
       if ((! $db) || isset($db->error)) {
          if ($db && isset($this)) $this->error = $db->error;
          $attribute_names = null;    $price = null;   return false;
       }
       $old_attribute_names = explode('|',$attribute_names);
       $query = 'select a.id,a.name,a.display_name,a.order_name,a.flags,' .
                'a.sub_product,a.dynamic,(select count(id) from ' .
                'attribute_options where parent=a.id) as num_options from ' .
                'product_attributes p join attributes a on a.id=' .
                'p.related_id where p.parent=? order by p.sequence';
       $query = $db->prepare_query($query,$product_id);
       $attribute_rows = $db->get_records($query,'id');
       if (! $attribute_rows) {
          if (isset($db->error)) {
             if (isset($this)) $this->error = $db->error;
          }
          else log_error('No attributes found for item attributes');
          $attribute_names = null;    $price = null;   return false;
       }
       $attribute_names = '';   $index = 0;   $attr_array = array();
       $inventory_attributes = $attributes;   $attribute_info = array();
       foreach ($attribute_rows as $row) {
          if (! isset($attributes[$index])) {
             $index++;   continue;
          }
          $name_offset = ($index * 2);   $value_offset = ($index * 2) + 1;
          if ($index > 0) $attribute_names .= '|';
          if ($attributes[$index] !== '') {
             if ($row['flags'] & 1) $attr_name = '';
             else if ($row['order_name']) $attr_name = $row['order_name'];
             else if ($row['display_name']) $attr_name = $row['display_name'];
             else $attr_name = $row['name'];
             if ($attr_name) $attribute_names .= $attr_name;
             else if (isset($old_attribute_names[$name_offset]))
                $attribute_names .= $old_attribute_names[$name_offset];
          }
          $attribute_names .= '|';
          if ($attributes[$index] === '') {}
          else if ($row['num_options'] == 0) {
             if (! empty($attributes[$index])) {
                $option_name = $attributes[$index];
                if ($attributes[$index][0] == '*') {
                   if ((! empty($old_attribute_names)) &&
                       isset($old_attribute_names[$value_offset]))
                      $option_name = $old_attribute_names[$value_offset];
                   else {
                      $attr_info = explode('*',$attributes[$index]);
                      if (count($attr_info) == 3) {
                         if ($attr_info[2]) $option_name = $attr_info[2];
                         $field_name = 'attr_'.$index.'_data';
                         $option_name = get_form_field($field_name);
                         if ($option_name === null) {
                            $field_name = 'attr_'.$index.'_'.$product_id.'_data';
                            $option_name = get_form_field($field_name);
                         }
                         if (! $option_name) $option_name = $attributes[$index];
                      }
                   }
                }
                $attribute_names .= $option_name;
             }
             else if (isset($old_attribute_names[$value_offset]))
                $attribute_names .= $old_attribute_names[$value_offset];
          }
          else if (isset($attributes[$index]) && is_numeric($attributes[$index])) {
             $query = 'select * from attribute_options where id=?';
             $query = $db->prepare_query($query,$attributes[$index]);
             $options_row = $db->get_record($query);
             if ($options_row) {
                $attribute_info[$attributes[$index]] = $options_row;
                if ($options_row['order_name'])
                   $attribute_names .= $options_row['order_name'];
                else $attribute_names .= $options_row['name'];
             }
             else if (isset($db->error)) {}
             else if (isset($old_attribute_names[$value_offset]))
                $attribute_names .= $old_attribute_names[$value_offset];
          }
          if (($row['sub_product'] == 1) || ($row['dynamic'] == 1))
             $attr_array[$row['id']] = $inventory_attributes[$index];
          $index++;
       }
       ksort($attr_array);
       $attribute_value = implode('|',$attr_array);
    }
    else {
       $attribute_value = '';   $db = null;
    }

    if (isset($this) && method_exists($this,'load_item_price'))
       $this->load_item_price($product_id,$qty,$attribute_value,$price,$flags,
                              $no_options,$wholesale_discount,$account_id);
    else @Cart::load_item_price($product_id,$qty,$attribute_value,$price,$flags,
                                $no_options,$wholesale_discount,$account_id);
    if ($price === null) return false;

    if (isset($this)) {
       $features = $this->features;
       if (! $db) $db = $this->db;
    }
    else {
       if (! $db) $db = new DB;
       $features = get_cart_config_value('features',$db);
    }
    if ($features & USE_PART_NUMBERS) {
       if (isset($this) && method_exists($this,'load_item_part_number'))
          $this->load_item_part_number($product_id,$attribute_value,
                                       $part_number,$no_options);
       else @Cart::load_item_part_number($product_id,$attribute_value,
                                         $part_number,$no_options);
       if ($part_number === null) return false;
       if (function_exists('update_cart_item_part_number'))
          update_cart_item_part_number($db,$part_number,$product_id,
                                       $attributes);
    }
    else $part_number = '';

    if (! empty($attributes)) {
       $old_attribute_prices = explode('|',$attribute_prices);
       $attribute_prices = '';   $base_price = $price;
       foreach ($attributes as $index => $id) {
          if ($index > 0) $attribute_prices .= '|';
          if ((! is_numeric($id)) || (! isset($attribute_info[$id]))) {
             if (isset($old_attribute_prices[$index]))
                $attribute_prices .= $old_attribute_prices[$index];
             else $attribute_prices .= '0';
             continue;
          }
          $option_info = $attribute_info[$id];
          if ($wholesale_discount) $factor = (100 - $wholesale_discount) / 100;
          if (isset($option_info['adjust_type']) &&
              ($option_info['adjust_type'] == 4)) {
             $adjustment = 0;
             $price_entries = explode('|',$option_info['price_breaks']);
             $num_entries = count($price_entries);
             for ($loop = 0;  $loop < $num_entries;  $loop++) {
                if ($price_entries[$loop] == '') continue;
                $price_details = explode('-',$price_entries[$loop]);
                if (($qty >= $price_details[0]) &&
                    ($qty <= $price_details[1])) {
                   if ($option_info['price_break_type'] == 1)
                      $adjustment = $price_details[2];
                   else $adjustment = $price_details[2] * $qty;
                   break;
                }
             }
             if ($adjustment && $wholesale_discount)
                $adjustment = round($adjustment * $factor,2);
          }
          else if (isset($option_info['adjust_type']) &&
              $option_info['adjustment']) {
             if ($option_info['adjust_type'] == 1)
                $adjustment = round($base_price *
                                    ($option_info['adjustment'] / 100),2);
             else $adjustment = $option_info['adjustment'];
             if ($adjustment && $wholesale_discount)
                $adjustment = round($adjustment * $factor,2);
          }
          else if (isset($old_attribute_prices[$index]))
             $adjustment = $old_attribute_prices[$index];
          else $adjustment = 0;
          if (! $adjustment) $adjustment = '0';
          $attribute_prices .= $adjustment;
       }
    }
    return true;
}

function process_uploaded_file($file_array,$index)
{
    global $file_dir,$cart_cookie;

    if (isset($this)) $cart_id = $this->id;
    else $cart_id = get_cookie($cart_cookie);
    $filename = $file_array['name'][$index];
    $temp_name = $file_array['tmp_name'][$index];
    if (isset($file_array['error'][$index]))
       $upload_error = $file_array['error'][$index];
    else $upload_error = null;
    if ($upload_error) {
       switch ($upload_error) {
          case 1:
             $error = 'The uploaded file '.$filename.' for Cart #'.$cart_id .
                      ' exceeds the upload_max_filesize directive';
             break;
          case 3:
             $error = 'The uploaded file '.$filename.' for Cart #'.$cart_id .
                      ' was only partially uploaded';
             break;
          case 4:
             $error = 'No file was uploaded in Upload request';   break;
          case 7:
             $error = 'The uploaded file '.$filename.' for Cart #'.$cart_id .
                      ' could not be written to disk';
             break;
          default:
             $error = 'Unknown Upload Error #'.$upload_error.' for file ' .
                      $filename.' for Cart #'.$cart_id;
       }
       log_error($error);   return null;
    }
    if (! $temp_name) {
       log_error('No Upload File found for file '.$filename.' for Cart #' .
                 $cart_id);
       return null;
    }
    $upload_dir = $file_dir.'/cart/'.$cart_id.'/';
    if (! file_exists($upload_dir)) {
       if (! @mkdir($upload_dir,0777,true)) {
          log_error('Unable to create '.$upload_dir.' directory');
          return null;
       }
    }
    $cart_filename = $filename;   $sequence = 0;
    $path_parts = pathinfo($filename);
    while (file_exists($upload_dir.$cart_filename)) {
       $sequence++;
       $cart_filename = $path_parts['filename'].'_'.$sequence;
       if ($path_parts['extension'])
          $cart_filename .= '.'.$path_parts['extension'];
    }
    if (! move_uploaded_file($temp_name,$upload_dir.$cart_filename)) {
       log_error('Unable to move '.$temp_name.' to ' .
                 $upload_dir.$cart_filename);
       return null;
    }
    log_activity('Uploaded '.$filename.' to '.$cart_filename.' for Cart #' .
                 $cart_id);
    return '{#'.$cart_id.'#}'.$cart_filename;
}

function get_attributes($id=null)
{
    if (isset($id)) $field_name = 'attributes_'.$id;
    else $field_name = 'attributes';
    $attributes = get_form_field($field_name);
    if (isset($attributes)) {
       if ($attributes == '') return array();
       if (strpos($attributes,'|') !== false)
          $attr_array = explode('|',$attributes);
       else $attr_array = explode('-',$attributes);
       return $attr_array;
    }
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    $attr_array = array();   $option_ids = array();
    $form_fields = get_form_fields();
    foreach ($form_fields as $field_name => $field_value) {
       if ((substr($field_name,0,5) == 'attr_') &&
           (substr($field_name,0,10) != 'attr_index')) {
//          if (($field_value === '') || ($field_value === null)) continue;
          if ((substr($field_name,-5) == '_data') ||
              (substr($field_name,-6) == '_price')) continue;
          if ($id !== null) {
             $underscore_pos = strpos($field_name,'_',5);
             if ($underscore_pos !== false) {
                $field_id = intval(substr($field_name,$underscore_pos + 1));
                if ($field_id != $id) continue;
                $field_name = substr($field_name,0,$underscore_pos);
             }
          }
          $attr_index = intval(substr($field_name,5));
          if (is_array($field_value)) $field_value = implode('^',$field_value);
          if (isset($attr_array[$attr_index])) {
             if ($field_value) $attr_array[$attr_index] .= '^'.$field_value;
          }
          else $attr_array[$attr_index] = $field_value;
          if (is_numeric($field_value) && $field_value)
             $option_ids[] = $field_value;
       }
    }

    if (! empty($_FILES)) {
       foreach ($_FILES as $field_name => $file_array) {
          if ((substr($field_name,0,5) != 'attr_') ||
              (substr($field_name,0,10) == 'attr_index')) continue;
          if ((substr($field_name,-5) == '_data') ||
              (substr($field_name,-6) == '_price')) continue;
          if ($id !== null) {
             $underscore_pos = strpos($field_name,'_',5);
             if ($underscore_pos !== false) {
                $field_id = intval(substr($field_name,$underscore_pos + 1));
                if ($field_id != $id) continue;
                $field_name = substr($field_name,0,$underscore_pos);
             }
          }
          $field_value = '';
          foreach ($file_array['name'] as $index => $filename) {
             if (isset($this))
                $filename = $this->process_uploaded_file($file_array,$index);
             else $filename = @Cart::process_uploaded_file($file_array,$index);
             if ($filename) {
                if ($field_value) $field_value .= '^';
                $field_value .= $filename;
             }
          }
          $attr_index = intval(substr($field_name,5));
          if (isset($attr_array[$attr_index])) {
             if ($field_value) $attr_array[$attr_index] .= '^'.$field_value;
          }
          else $attr_array[$attr_index] = $field_value;
       }
    }

/*    If need to sort attributes by attribute id, use the following:

    $query = 'select id,parent from attribute_options o where id in (?) ' .
             'order by parent';
    $query = $db->prepare_query($query,$option_ids);
    $attr_array = $db->get_records($query,null,'id');
*/
    if (! empty($attr_array)) {
       $last_key = max(array_keys($attr_array));
       for ($loop = 0;  $loop <= $last_key;  $loop++)
          if (! isset($attr_array[$loop])) $attr_array[$loop] = '';
       ksort($attr_array);
    }
    if (function_exists('custom_update_attributes')) {
       if (isset($this))
          custom_update_attributes($this,$attr_array);
       else custom_update_attributes(null,$attr_array);
    }
    return $attr_array;
}

function set_cart_item_website(&$item_record)
{
    if (empty($this->shared_cart)) return;
    $website = get_current_website($this->db);
    if ($website) $item_record['website']['value'] = $website;
}

function add_item(&$product_id,&$attributes,$qty,$registry_item=null,
                  $related_id=null,$reorder_frequency=null)
{
    global $multiple_customer_accounts,$account_cookie;
    global $enable_gift_certificates;

    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       return false;
    }
    if (! $this->check_id('cart->add_item')) return false;
    if (function_exists('add_custom_item')) {
       $retval = add_custom_item($this,$product_id,$attributes,$qty,
                                 $registry_item,$related_id);
       if ($retval !== null) return $retval;
    }
    if (! $this->validate_product_id($product_id)) return false;
    if (! $this->validate_quantity($qty)) return false;
    $no_options = check_no_option_attributes($this->db,$product_id);
    $custom_attributes = false;
    if (! empty($attributes)) foreach ($attributes as $id) {
       if (isset($id[0]) && ($id[0] == '*')) {
          $custom_attributes = true;   break;
       }
    }
    if ($this->features & REGULAR_PRICE_BREAKS) $row = null;
//    else if ($related_id) $row = null;    // Not sure why this should force a new cart item
    else if ($custom_attributes) $row = null;
    else {
       $lookup_attributes = build_lookup_attributes($this->db,$product_id,
                               $attributes,false,$no_options);
       $query = 'select * from '.$this->item_table.' where (parent=?) ' .
                'and (product_id=?) and ';
       if ($lookup_attributes) {
          $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
             $product_id,$no_options,$this->db);
          if ($no_options) $orig_attributes = implode('|',$attributes);
          else $orig_attributes = implode('-',$attributes);
          if ($lookup_attributes != $orig_attributes) $query .= '(';
          $query .= '(attributes=cast(? as char))';
          if ($lookup_attributes != $orig_attributes) {
             $query .= ' or (attributes=cast(? as char)))';
             $query = $this->db->prepare_query($query,$this->id,$product_id,
                         $lookup_attributes,$orig_attributes);
          }
          else $query = $this->db->prepare_query($query,$this->id,$product_id,
                                                 $lookup_attributes);
       }
       else {
          $query .= ' ((attributes="") or isnull(attributes))';
          $query = $this->db->prepare_query($query,$this->id,$product_id);
       }
       if ($lookup_attributes === null) $row = null;
       else $row = $this->db->get_record($query);
    }
/*
    if ($row && ($this->features & SUB_PRODUCT_RELATED)) {
       $query = 'select count(id) as num_products from related_products ' .
                'where (related_type=1) and (parent=?)';
       $query = $this->db->prepare_query($query,$product_id);
       $sub_row = $this->db->get_record($query);
       if (! $sub_row) {
          $this->error = $this->db->error;   return false;
       }
       if ($sub_row['num_products'] > 0) $row = null;
    }
*/
    if ($row) {
       if ($this->wishlist) return true;
       $item_id = $row['id'];
       $query = 'update '.$this->item_table.' set qty=qty+?';
       if ($registry_item) {
          $query .= ',registry_item=? where id=?';
          $query = $this->db->prepare_query($query,$qty,$registry_item,
                                            $item_id);
       }
       else {
          $query .= ' where id=?';
          $query = $this->db->prepare_query($query,$qty,$item_id);
       }
       $this->db->log_query($query);
       if (! $this->db->query($query)) {
          $this->error = $this->db->error;   return false;
       }
       $this->last_item_id = $item_id;
       $item_info = $row;
       $item_info['qty'] += $qty;
       log_activity('Added Qty '.$qty.' to '.$this->label.' Item #'.$item_id .
                    ' in '.$this->label.' #'.$this->id);
    }
    else if (isset($this->db->error)) {
       $this->error = $this->db->error;   unset($this->db->error);
       return false;
    }
    else {
       $item_record = item_record_definition();
       $item_record['parent']['value'] = $this->id;
       $item_record['product_id']['value'] = $product_id;
       if (isset($attributes) && (count($attributes) > 0)) {
          if ($no_options)
             $item_record['attributes']['value'] = implode('|',$attributes);
          else $item_record['attributes']['value'] = implode('-',$attributes);
       }
       else $item_record['attributes']['value'] = '';
       $item_record['qty']['value'] = $qty;
       if ($registry_item)
          $item_record['registry_item']['value'] = $registry_item;
       if ($related_id) $item_record['related_id']['value'] = $related_id;
       if ($reorder_frequency)
          $item_record['reorder_frequency']['value'] = $reorder_frequency;
       $query = 'select name,display_name,order_name from products where id=?';
       $query = $this->db->prepare_query($query,$product_id);
       $row = $this->db->get_record($query);
       if ($row) {
          if ($row['order_name']) $product_name = $row['order_name'];
          else if ($row['display_name']) $product_name = $row['display_name'];
          else $product_name = $row['name'];
          if (function_exists('build_product_name'))
             $item_record['product_name']['value'] =
                build_product_name($this,$product_id,$product_name);
          else $item_record['product_name']['value'] = $product_name;
       }
       if (! $this->load_item_details($product_id,$qty,$attributes,$no_options,
                                      $item_record['attribute_names']['value'],
                                      $item_record['attribute_prices']['value'],
                                      $item_record['price']['value'],
                                      $item_record['part_number']['value'],
                                      $item_record['flags']['value'])) {
          $this->error = 'Product is not available';   return false;
       }
       if ($multiple_customer_accounts && (! $this->wishlist))
          $item_record['account_id']['value'] = get_cookie($account_cookie);
       $this->set_cart_item_website($item_record);
       if (! empty($enable_gift_certificates)) {
          require_once '../admin/modules/giftcertificates/cart.php';
          update_gift_certificate_cart_item($this,$item_record);
       }
       if (function_exists('custom_update_add_cart_item'))
          custom_update_add_cart_item($this,$item_record);

       if (! $this->db->insert($this->item_table,$item_record)) {
          $this->error = $this->db->error;   return false;
       }
       $item_id = $this->db->insert_id();
       $this->last_item_id = $item_id;
       if (isset($this->items) && is_array($this->items)) {
          $item = array();
          foreach ($item_record as $field_name => $field_info) {
             if (isset($field_info['value']))
                $item[$field_name] = $field_info['value'];
             else $item[$field_name] = null;
          }
          $attribute_array = build_attribute_array($this->db,'cart',$item);
          $item['attribute_array'] = $attribute_array;
          $this->items[$item_id] = $item;
       }

       if (! empty($attributes)) {
          foreach ($attributes as $index => $id) {
             if (isset($id[0]) && ($id[0] == '*')) {
                $attr_info = explode('*',$id);
                if (count($attr_info) != 3) continue;
                $query = 'select type from attributes where id=?';
                $query = $this->db->prepare_query($query,$attr_info[1]);
                $row = $this->db->get_record($query);
                if (! $row) continue;
                $attributes_record = item_attributes_record_definition();
                $attributes_record['parent']['value'] = $item_id;
                $attributes_record['attribute_id']['value'] = $attr_info[1];
                $attributes_record['attribute_type']['value'] = $row['type'];
                $field_name = 'attr_'.$index.'_price';
                $attr_price = get_form_field($field_name);
                if (! isset($attr_price)) {
                   $field_name = 'attr_'.$index.'_'.$product_id.'_price';
                   $attr_price = get_form_field($field_name);
                }
                if (isset($attr_price))
                   $attributes_record['price']['value'] = floatval($attr_price);
                $field_name = 'attr_'.$index.'_data';
                $attr_data = get_form_field($field_name);
                if (! isset($attr_data)) {
                   $field_name = 'attr_'.$index.'_'.$product_id.'_data';
                   $attr_data = get_form_field($field_name);
                }
                if (isset($attr_data))
                   $attributes_record['data']['value'] = $attr_data;
                if (! $this->db->insert($this->attr_table,$attributes_record)) {
                   $this->error = $this->db->error;   return false;
                }
             }
          }
       }

       $log_string = 'Added '.$this->label.' Item #'.$item_id.' with Product ' .
                     $item_record['product_name']['value'] .
                     ' ('.$product_id.') and';
       if (isset($attributes) && (count($attributes) > 0)) {
          if ($no_options) $attribute_string = implode('|',$attributes);
          else $attribute_string = implode('-',$attributes);
          if (strlen($attribute_string) > 100)
             $attribute_string = substr($attribute_string,0,100).'...';
          $attribute_string = str_replace("\n",'',$attribute_string);
          $log_string .= ' Attributes '.$attribute_string.' and';
       }
       $log_string .= ' Qty '.$qty.' to '.$this->label.' #'.$this->id;
       log_activity($log_string);
       $item_info = $this->db->convert_record_to_array($item_record);
    }
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('add_cart_item')) {
       if (! call_module_event('add_cart_item',
                array($this->db,$this->id,$item_info),
                null,true)) {
          $this->error = 'Unable to add cart item: '.get_module_errors();
          return false;
       }
    }
    return true;
}

function add_item_record($product_id,$attributes,$attribute_names,
                         $attribute_prices,$qty,$price,$flags)
{
    if (! $this->check_id('cart->add_item_record')) return false;
    if (! $this->validate_product_id($product_id)) return false;
    if (! $this->validate_quantity($qty)) return false;
    $item_record = item_record_definition();
    $item_record['parent']['value'] = $this->id;
    $item_record['product_id']['value'] = $product_id;
    $item_record['attributes']['value'] = $attributes;
    $item_record['attribute_names']['value'] = $attribute_names;
    $item_record['attribute_prices']['value'] = $attribute_prices;
    $item_record['qty']['value'] = $qty;
    $item_record['price']['value'] = $price;
    $item_record['flags']['value'] = $flags;
    $query = 'select name,display_name,order_name from products where id=?';
    $query = $this->db->prepare_query($query,$product_id);
    $row = $this->db->get_record($query);
    if ($row) {
       if ($row['order_name']) $product_name = $row['order_name'];
       else if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       if (function_exists('build_product_name'))
          $item_record['product_name']['value'] =
             build_product_name($this,$product_id,$product_name);
       else $item_record['product_name']['value'] = $product_name;
    }
    $this->set_cart_item_website($item_record);
    if (! $this->db->insert($this->item_table,$item_record)) {
       $this->error = $this->db->error;   return false;
    }
    $item_id = $this->db->insert_id();
    $this->last_item_id = $item_id;

    $log_string = 'Added Product '.$item_record['product_name']['value'].' (' .
                  $product_id.') with Qty '.$qty.' and Price ' .
                  number_format($price,2).' to '.$this->label.' # '.$this->id;
    log_activity($log_string);
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('add_cart_item')) {
       $item_info = $this->db->convert_record_to_array($item_record);
       if (! call_module_event('add_cart_item',
                array($this->db,$this->id,$item_info),
                null,true)) {
          $this->error = 'Unable to add cart item: '.get_module_errors();
          return false;
       }
    }
    return true;
}

function add_custom_item($product_name,$qty,$price,$flags,$attributes=null,
                         $attribute_names=null,$attribute_prices=null,
                         $registry_item=null,$related_id=null)
{
    if (! $this->check_id('cart->add_custom_item')) return false;
    if (! $this->validate_quantity($qty)) return false;
    $item_record = item_record_definition();
    $item_record['parent']['value'] = $this->id;
    $item_record['product_name']['value'] = $product_name;
    $item_record['qty']['value'] = $qty;
    $item_record['price']['value'] = $price;
    $item_record['flags']['value'] = $flags;
    if (isset($attributes))
       $item_record['attributes']['value'] = $attributes;
    if (isset($attribute_names))
       $item_record['attribute_names']['value'] = $attribute_names;
    if (isset($attribute_prices))
       $item_record['attribute_prices']['value'] = $attribute_prices;
    if (isset($registry_item))
       $item_record['registry_item']['value'] = $registry_item;
    if (isset($related_id))
       $item_record['related_id']['value'] = $related_id;
    $this->set_cart_item_website($item_record);
    if (! $this->db->insert($this->item_table,$item_record)) {
       $this->error = $this->db->error;   return false;
    }
    $item_id = $this->db->insert_id();
    $this->last_item_id = $item_id;

    $log_string = 'Added Product '.$product_name.' with Qty '.$qty .
                  ' and Price '.number_format($price,2).' to '.$this->label .
                  ' # '.$this->id;
    log_activity($log_string);
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('add_cart_item')) {
       $item_info = $this->db->convert_record_to_array($item_record);
       if (! call_module_event('add_cart_item',
                array($this->db,$this->id,$item_info),
                null,true)) {
          $this->error = 'Unable to add cart item: '.get_module_errors();
          return false;
       }
    }
    return true;
}

function update_item($item_id,$product_id,$attributes,$qty,
                     $reorder_frequency=null)
{
    if (! is_numeric($item_id)) {
       log_error('Invalid Item ID ('.$item_id.')');
       $this->error = 'Invalid Product';   return false;
    }
    if ($product_id === null) {
       if (isset($attributes)) {
          $query = 'select product_id from '.$this->item_table.' where id=?';
          $query = $this->db->prepare_query($query,$item_id);
          $row = $this->db->get_record($query);
          if (! $row) {
             if (isset($this->db->error)) {
                $this->error = $this->db->error;   return false;
             }
          }
          else $product_id = $row['product_id'];
       }
    }
    else if (! $this->validate_product_id($product_id)) return false;
    if (($qty !== null) && (! $this->validate_quantity($qty))) return false;
    if ($product_id)
       $no_options = check_no_option_attributes($this->db,$product_id);
    $item_record = item_record_definition();
    $item_record['id']['value'] = $item_id;
    if ($qty !== null) {
       $item_record['qty']['value'] = $qty;
       if (isset($attributes) && ($product_id !== null)) {
          if ($no_options)
             $item_record['attributes']['value'] = implode('|',$attributes);
          else $item_record['attributes']['value'] = implode('-',$attributes);
          if (! $this->load_item_details($product_id,$qty,$attributes,$no_options,
                                         $item_record['attribute_names']['value'],
                                         $item_record['attribute_prices']['value'],
                                         $item_record['price']['value'],
                                         $item_record['part_number']['value'],
                                         $item_record['flags']['value'])) {
             $this->error = 'Product is not available';   return false;
          }
       }
    }
    if ($reorder_frequency !== null)
       $item_record['reorder_frequency']['value'] = $reorder_frequency;
    if (! $this->db->update($this->item_table,$item_record)) {
       $this->error = $this->db->error;   return false;
    }
    if ($qty === null) 
       log_activity('Updated '.$this->label.' Item #' .
                    $item_id.' in '.$this->label.' #'.$this->id);
    else if (isset($attributes))
       log_activity('Updated Qty '.$qty.' and Attributes ' .
                    $item_record['attributes']['value'].' for '.$this->label .
                    ' Item #'.$item_id.' in '.$this->label.' #'.$this->id);
    else log_activity('Updated Qty '.$qty.' for '.$this->label.' Item #' .
                      $item_id.' in '.$this->label.' #'.$this->id);
    return true;
}

function update_item_record($item_id,$product_id,$attributes,$attribute_names,
                            $attribute_prices,$qty,$price,$flags)
{
    if ($product_id === null) {
       $query = 'select product_id from '.$this->item_table.' where id=?';
       $query = $this->db->prepare_query($query,$item_id);
       $row = $this->db->get_record($query);
       if (! $row) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   return false;
          }
       }
       else $product_id = $row['product_id'];
    }
    else if (! $this->validate_product_id($product_id)) return false;
    if (! $this->validate_quantity($qty)) return false;
    $item_record = item_record_definition();
    $item_record['id']['value'] = $item_id;
    $item_record['product_id']['value'] = $product_id;
    $item_record['attributes']['value'] = $attributes;
    $item_record['attribute_names']['value'] = $attribute_names;
    $item_record['attribute_prices']['value'] = $attribute_prices;
    $item_record['qty']['value'] = $qty;
    $item_record['price']['value'] = $price;
    $item_record['flags']['value'] = $flags;
    $query = 'select name,display_name,order_name from products where id=?';
    $query = $this->db->prepare_query($query,$product_id);
    $row = $this->db->get_record($query);
    if ($row) {
       if ($row['order_name']) $product_name = $row['order_name'];
       else if ($row['display_name']) $product_name = $row['display_name'];
       else $product_name = $row['name'];
       if (function_exists('build_product_name'))
          $item_record['product_name']['value'] =
             build_product_name($this,$product_id,$product_name);
       else $item_record['product_name']['value'] = $product_name;
    }
    if (! $this->db->update($this->item_table,$item_record)) {
       $this->error = $this->db->error;   return false;
    }

    $log_string = 'Updated Product '.$item_record['product_name']['value'].' (' .
                  $product_id.') with Qty '.$qty.' and Price '.$price .
                  ' in '.$this->label.' #'.$this->id;
    log_activity($log_string);
    return true;
}

function delete_item($item_id)
{
    if (! is_numeric($item_id)) {
       log_error('Invalid Item ID ('.$item_id.')');
       $this->error = 'Invalid Product';   return false;
    }
    $item_record = item_record_definition();
    $item_record['id']['value'] = $item_id;
    if (! $this->db->delete($this->item_table,$item_record)) {
       $this->error = $this->db->error;   return false;
    }
    log_activity('Deleted '.$this->label.' Item #'.$item_id.' from ' .
                 $this->label.' #'.$this->id);
    return true;
}

function delete_wishlist_item($item_id)
{
    if (! is_numeric($item_id)) {
       log_error('Invalid Item ID ('.$item_id.')');
       $this->error = 'Invalid Product';   return;
    }
    $item_record = item_record_definition();
    $item_record['id']['value'] = $item_id;
    if (! $this->db->delete('wishlist_items',$item_record)) {
       $this->error = $this->db->error;   return false;
    }
    log_activity('Deleted WishList Item #'.$item_id);
    return true;
}

function get_product_inv_info($product_id,$attributes)
{
    if (! $product_id) {
       $error = 'Product ID not specified in get_product_inv_info';
       $this->error = $error;   log_error($error);   log_request_data();
       return null;
    }
    $query = 'select * from product_inventory where parent=?';
    if ($attributes) {
       $attr_query = 'select a.sub_product,a.dynamic from product_attributes ' .
                     'p join attributes a on a.id=p.related_id ' .
                     'where p.parent=? order by p.sequence';
       $attr_query = $this->db->prepare_query($attr_query,$product_id);
       $rows = $this->db->get_records($attr_query);
       if (! $rows) {
          if (isset($this->db->error)) $this->error = $this->db->error;
          return null;
       }
       foreach ($rows as $index => $row) {
          if (($row['sub_product'] != 1) && ($row['dynamic'] != 1))
             unset($attributes[$index]);
       }
       $no_options = check_no_option_attributes($this->db,$product_id);
       $lookup_attributes = build_lookup_attributes($this->db,$product_id,
                               $attributes,true,$no_options);
    }
    else $lookup_attributes = null;
    if ($lookup_attributes) {
       $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
          $product_id,$no_options,$this->db);
       $query .= ' and attributes=?';
       $query = $this->db->prepare_query($query,$product_id,$lookup_attributes);
    }
    else {
       $query .= ' and ((attributes="") or isnull(attributes))';
       $query = $this->db->prepare_query($query,$product_id);
    }
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       return null;
    }
    return $row;
}

function get_item_inv_info($item_id)
{
    if (! is_numeric($item_id)) {
       log_error('Invalid Item ID ('.$item_id.')');
       $this->error = 'Invalid Product';   return null;
    }
    $query = 'select product_id,attributes from '.$this->item_table .
             ' where id=?';
    $query = $this->db->prepare_query($query,$item_id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($db->error)) $this->error = $db->error;
       return null;
    }
    if (! $row['product_id']) return null;
    if ((! isset($row['attributes'])) || ($row['attributes'] == ''))
       $attributes = null;
    else {
       $no_options = check_no_option_attributes($this->db,$row['product_id']);
       if ($no_options) $attributes = explode('|',$row['attributes']);
       else $attributes = explode('-',$row['attributes']);
    }
    $inv_info = $this->get_product_inv_info($row['product_id'],$attributes);
    return $inv_info;
}

function get_item_quantity($item_id)
{
    $inv_info = $this->get_item_inv_info($item_id);
    if ($inv_info) return $inv_info['qty'];
    return 0;
}

function check_quantity($item_id,$product_qty,$qty)
{
    global $log_cart_errors_enabled;

    if ($product_qty === null) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Product Quantity for Item ID #' .
                         $item_id.' is Null');
       $this->errors['overqty_'.$item_id] = T('That product is not available');
       return;
    }
    if (! is_numeric($item_id)) return;
    if (! is_numeric($product_qty)) return;
    if (! is_numeric($qty)) return;
    if ($product_qty < $qty) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Product Quantity for Item ID #' .
                         $item_id.' is '.$product_qty .
                         ', Requested Quantity is '.$qty);
       if (function_exists('get_out_of_stock_message'))
          $error_msg = get_out_of_stock_message($this,$item_id,$product_qty,$qty);
       else if ($product_qty < 1) $error_msg =
          T('There are no more products of that type in stock, ' .
            'please remove it from your order');
       else if ($product_qty == 1) $error_msg =
          T('There is only 1 of that product remaining in stock, ' .
            'please reduce the quantity of your order');
       else $error_msg =
          T('There are only').' '.$product_qty.' ' .
          T('of that product remaining in stock, ' .
            'please reduce the quantity of your order');
       $this->errors['overqty_'.$item_id] = $error_msg;
    }
}

function load_cart_item_status_values()
{
    $ids = array();
    foreach ($this->items as $cart_item) {
       if ($cart_item['product_id'] &&
           (! in_array($cart_item['product_id'],$ids)))
          $ids[] = $cart_item['product_id'];
    }
    if (count($ids) == 0) return;
    $query = 'select id,status from products where id in (?)';
    $query = $this->db->prepare_query($query,$ids);
    $status_values = $this->db->get_records($query,'id');
    if (! $status_values) return;
    $ids = array();
    foreach ($this->items as $id => $cart_item) {
       if ($cart_item['product_id'] &&
           isset($status_values[$cart_item['product_id']]))
          $this->items[$id]['status'] =
             $status_values[$cart_item['product_id']]['status'];
       else $this->items[$id]['status'] = 0;
    }
}

function load_cart_item_inventory_values()
{
    if (empty($this->items)) return;
    foreach ($this->items as $item_id => $cart_item) {
       if (empty($cart_item['product_id'])) {
          $this->items[$item_id]['min_order_qty'] = null;
          $this->items[$item_id]['available'] = 1;
          $this->items[$item_id]['backorder'] = 1;   continue;
       }
       $product_id = $cart_item['product_id'];
       $query = 'select * from product_inventory where parent=?';
       if ((! isset($cart_item['attributes'])) ||
           ($cart_item['attributes'] == ''))
          $lookup_attributes = null;
       else {
          $no_options = check_no_option_attributes($this->db,$product_id);
          if ($no_options) $attributes = explode('|',$cart_item['attributes']);
          else $attributes = explode('-',$cart_item['attributes']);
          $lookup_attributes = build_lookup_attributes($this->db,
             $product_id,$attributes,true,$no_options);
       }
       if ($lookup_attributes) {
          $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
             $product_id,$no_options,$this->db);
          $query .= ' and attributes=?';
          $query = $this->db->prepare_query($query,$product_id,
                                            $lookup_attributes);
       }
       else {
          $query .= ' and ((attributes="") or isnull(attributes))';
          $query = $this->db->prepare_query($query,$product_id);
       }
       $row = $this->db->get_record($query);
       if (! $row) {
          $this->items[$item_id]['min_order_qty'] = 0;
          $this->items[$item_id]['available'] = 0;
          $this->items[$item_id]['backorder'] = 0;
       }
       else {
          $this->items[$item_id]['min_order_qty'] = $row['min_order_qty'];
          $this->items[$item_id]['available'] = $row['available'];
          $this->items[$item_id]['backorder'] = $row['backorder'];
       }
    }
}

function check_required_attributes($cart_item,&$error)
{
    $query = 'select a.id,a.name,a.display_name,a.required from ' .
             'product_attributes pa join attributes a on a.id=pa.related_id ' .
             'where pa.parent=?';
    $query = $this->db->prepare_query($query,$cart_item['product_id']);
    $attributes = $this->db->get_records($query,'id');
    $required = false;
    foreach ($attributes as $attr_info) {
       if ($attr_info['required']) {
          $required = true;   break;
       }
    }
    if (! $required) return true;

    $option_ids = array();
    if (! empty($cart_item['attribute_array'])) {
       foreach ($cart_item['attribute_array'] as $option) {
          if (empty($option['id'])) continue;
          $option_ids[] = $option['id'];
       }
    }
    if (! empty($option_ids)) {
       $query = 'select id,parent from attribute_options where id in (?)';
       $query = $this->db->prepare_query($query,$option_ids);
       $options = $this->db->get_records($query,'parent','id');
    }
    $error = '';   $ret_value = true;
    foreach ($attributes as $attr_info) {
       if ($attr_info['required']) {
          if (! isset($options[$attr_info['id']])) {
             if (! empty($attr_info['display_name']))
                $name = $attr_info['display_name'];
             else $name = $attr_info['name'];
             if ($error) $error .= ', ';
             $error .= $name.' is required';
             $ret_value = false;
          }
       }
    }

    return $ret_value;
}

function need_inventory_info()
{
    global $enable_inventory_available;

    if ((! ($this->features & MAINTAIN_INVENTORY)) ||
        ($this->features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH)) ||
        ($this->features & INVENTORY_BACKORDERS) ||
        (! empty($enable_inventory_available))) return true;
    return false;
}

function backorderable($inv_info)
{
    if (($this->features & ALLOW_BACKORDERS) &&
        (! ($this->features & INVENTORY_BACKORDERS)) ||
         (! empty($inv_info['backorder']))) return true;
    else if ((! ($this->features & ALLOW_BACKORDERS)) &&
             ($this->features & INVENTORY_BACKORDERS) &&
             (! empty($inv_info['backorder']))) return true;
    return false;
}

function check_product_min_qty()
{
    $product_ids = array();
    foreach ($this->items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if ($product_id) {
          if (! in_array($product_id,$product_ids))
             $product_ids[] = $product_id;
       }
    }
    if (count($product_ids) == 0) return;
    $query = 'select id,min_order_qty from products where id in (?)';
    $query = $this->db->prepare_query($query,$product_ids);
    $min_qtys = $this->db->get_records($query,'id','min_order_qty');
    $item_totals = array();
    foreach ($this->items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if (! isset($item_totals[$product_id]))
          $item_totals[$product_id] = $cart_item['qty'];
       else $item_totals[$product_id] += $cart_item['qty'];
    }
    foreach ($this->items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if (empty($min_qtys[$product_id])) continue;
       if ($item_totals[$product_id] >= $min_qtys[$product_id]) continue;
       if ($cart_item['min_order_qty'] > $min_qtys[$product_id]) continue;
       $this->items[$id]['min_order_qty_product'] = $min_qtys[$product_id];
    }
}

function check_cart_quantities()
{
    global $off_sale_option,$sold_out_option,$enable_inventory_available;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($sold_out_option)) $sold_out_option = 2;
    $this->load_cart_item_status_values();
    if ($this->need_inventory_info()) $this->load_cart_item_inventory_values();
    if ($this->features & (MIN_ORDER_QTY_PRODUCT|MIN_ORDER_QTY_BOTH))
       $this->check_product_min_qty();
    foreach ($this->items as $id => $cart_item) {
       if (! $cart_item['product_id']) continue;
       if (! isset($cart_item['status'])) continue;
       if ($cart_item['status'] == $off_sale_option)
          $this->errors['overqty_'.$id] =
             T('That product is Off Sale, please remove it from your order');
       else if ($cart_item['status'] == $sold_out_option)
          $this->errors['overqty_'.$id] =
             T('That product is Sold Out, please remove it from your order');
       else if ($this->backorderable($cart_item)) {}
       else if ((! empty($enable_inventory_available)) &&
                ($cart_item['available'] == 1)) {}
       else if ($this->features & MAINTAIN_INVENTORY) {
          if (empty($enable_inventory_available) ||
              empty($cart_item['available'])) {
             $item_qty = $this->get_item_quantity($id);
             if ($item_qty !== null)
                $this->check_quantity($id,$item_qty,$cart_item['qty']);
          }
       }
       else if ($cart_item['available'] != 1)
          $this->errors['overqty_'.$id] =
             T('That product is not available, ' .
               'please remove it from your order');

       if (! isset($this->errors['overqty_'.$id])) {
          if ($this->features & (MIN_ORDER_QTY_PRODUCT|MIN_ORDER_QTY_BOTH) &&
              (! empty($cart_item['min_order_qty_product'])))
             $this->errors['overqty_'.$id] = 'You must order at least ' .
                $cart_item['min_order_qty_product'].' of all items of this product';
          else if (($this->features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH)) &&
                   (! empty($cart_item['min_order_qty']))) {
             if ($cart_item['qty'] < $cart_item['min_order_qty'])
                $this->errors['overqty_'.$id] = 'You must order at least ' .
                   $cart_item['min_order_qty'].' of this item';
          }
       }

       if (! $this->check_required_attributes($cart_item,$error))
          $this->errors['item_'.$id] = $error;
    }
    if (count($this->errors) > 0) return false;
    return true;
}

function update_cart_record($field_name,$cart_item,$item_info,&$item_record)
{
    if ($cart_item[$field_name] == $item_info[$field_name]) {
       unset($item_record[$field_name]['value']);   return false;
    }
    $item_record[$field_name]['value'] = $item_info[$field_name];
    return true;
}

function update_cart_items()
{
    $item_fields = array('attribute_names','attribute_prices','price',
                         'part_number','flags');
    $cart_updated = false;
    foreach ($this->items as $id => $cart_item) {
       if (! $cart_item['product_id']) continue;
       $product_id = $cart_item['product_id'];
       $no_options = check_no_option_attributes($this->db,$product_id);
       $qty = $cart_item['qty'];
       if ($cart_item['attributes'] == '') $attributes = array();
       else if ($no_options)
          $attributes = explode('|',$cart_item['attributes']);
       else $attributes = explode('-',$cart_item['attributes']);
       $item_info = array();
       foreach ($item_fields as $field_name) {
          if (($field_name == 'attribute_names') ||
              ($field_name == 'attribute_prices'))
             $item_info[$field_name] = $cart_item[$field_name];
          else $item_info[$field_name] = null;
       }
       if (! $this->load_item_details($product_id,$qty,$attributes,$no_options,
                $item_info['attribute_names'],$item_info['attribute_prices'],
                $item_info['price'],$item_info['part_number'],
                $item_info['flags'])) {
          $this->errors['overqty_'.$id] = 'Product is no longer available';
          return $this->items;
       }
       if (function_exists('custom_update_cart_item'))
          custom_update_cart_item($this,$cart_item,$item_info);
       $update_item = false;   $item_record = item_record_definition();
       foreach ($item_fields as $field_name) {
          if ($this->update_cart_record($field_name,$cart_item,$item_info,
                                        $item_record)) $update_item = true;
       }
       if ($update_item) {
          $item_record['id']['value'] = $cart_item['id'];
          if (! $this->db->update($this->item_table,$item_record)) {
             $this->error = $this->db->error;   return $this->items;
          }
          log_activity('Updated '.$this->label.' Item #' .
                       $cart_item['id'].' in '.$this->label.' #'.$this->id);
          $cart_updated = true;
       }
    }
    if ($cart_updated) return $this->load();
    return $this->items;
}

function add_reorder($order_id)
{
    if (! is_numeric($order_id)) {
       log_error('Invalid Order ID ('.$order_id.')');
       log_request_data();   $this->error = 'Invalid Order ID';   return false;
    }
    $query = 'select * from order_items where parent=? order by id';
    $query = $this->db->prepare_query($query,$order_id);
    $result = $this->db->query($query);
    if ((! $result) || ($this->db->num_rows($result) == 0)) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'Order Items not found';
       return false;
    }
    $this->items = array();
    while ($item_row = $this->db->fetch_assoc($result)) {
       $product_id = $item_row['product_id'];
       $attributes = $item_row['attributes'];
       $qty = $item_row['qty'];
       $item_record = item_record_definition();
       $item_record['parent']['value'] = $this->id;
       $item_record['product_id']['value'] = $product_id;
       $item_record['attributes']['value'] = $attributes;
       $item_record['attribute_names']['value'] = $item_row['attribute_names'];
       $item_record['attribute_prices']['value'] = $item_row['attribute_prices'];
       $item_record['qty']['value'] = $qty;
       if ($product_id) {
          $query = 'select name,display_name,order_name from products where id=?';
          $query = $this->db->prepare_query($query,$product_id);
          $row = $this->db->get_record($query);
          if ($row) {
             if ($row['order_name']) $product_name = $row['order_name'];
             else if ($row['display_name'])
                $product_name = $row['display_name'];
             else $product_name = $row['name'];
             if (function_exists('build_product_name'))
                $item_record['product_name']['value'] =
                   build_product_name($this,$product_id,$product_name);
             else $item_record['product_name']['value'] = $product_name;
          }
          $no_options = check_no_option_attributes($this->db,$product_id);
          if ($no_options) $attribute_array = explode('|',$attributes);
          else $attribute_array = explode('-',$attributes);
          if (! $this->load_item_details($product_id,$qty,$attribute_array,$no_options,
                                         $item_record['attribute_names']['value'],
                                         $item_record['attribute_prices']['value'],
                                         $item_record['price']['value'],
                                         $item_record['part_number']['value'],
                                         $item_record['flags']['value'])) {
             $this->error = 'Product is not available';   return false;
          }
       }
       else {
          $item_record['product_name']['value'] = $item_row['product_name'];
          $item_record['price']['value'] = $item_row['price'];
          $item_record['flags']['value'] = $item_row['flags'];
       }
       if (! $this->db->insert($this->item_table,$item_record)) {
          $this->error = $this->db->error;   return false;
       }
    }
    $this->db->free_result($result);

    $query = 'update cart set reorder_id=? where id=?';
    $query = $this->db->prepare_query($query,$order_id,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }

    $log_string = 'Added Reorder for Order #'.$order_id.' to '.$this->label .
                  ' #'.$this->id;
    log_activity($log_string);
    return true;
}

function write_reorder_info($cart_item)
{
    global $enable_reorders,$enable_auto_reorders,$auto_reorder_label;

    if (empty($enable_reorders)) return;
    if (empty($cart_item['reorder_frequency'])) return;

    if (! empty($enable_auto_reorders)) {
       if (! empty($auto_reorder_label)) $reorder_label = $auto_reorder_label;
       else $reorder_label = 'Auto-Reorder';
    }
    else $reorder_label = 'Reorder';
    $page = basename($_SERVER['PHP_SELF']);
    if ($page != 'index.php')
       print "<br>\n".$reorder_label.' Every '.$cart_item['reorder_frequency'] .
             ' Months';
    if (empty($this->info['customer_id'])) return;
    if (empty($cart_item['product_id'])) return;
    $query = 'select count(id) as num_items from order_items where parent ' .
             'in (select id from orders where customer_id=?) and product_id=? ' .
             'and not isnull(reorder_frequency) and (reorder_frequency!=0)';
    $query = $this->db->prepare_query($query,$this->info['customer_id'],
                                      $cart_item['product_id']);
    $row = $this->db->get_record($query);
    if (empty($row['num_items'])) return;
    if (! empty($enable_auto_reorders)) $prefix = 'an';
    else $prefix = 'a';
    print "<br>\n".'<span class="cart_error">Warning: You already have ' .
          $prefix.' '.$reorder_label.' for this item.';
    if ($page != 'index.php')
       print '  <a href="cart/index.php">Click Here</a> to change the ' .
             $reorder_label.' for this item';
    print '</span>';
}

function load()
{
    global $amount_cents_flag;

    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       return null;
    }
    if (! $this->check_id('cart->load')) return false;
    if (isset($amount_cents_flag) && (! $amount_cents_flag))
       $round_cents = true;
    else $round_cents = false;

    $query = 'select * from '.$this->item_table.' where parent=? order by id';
    $query = $this->db->prepare_query($query,$this->id);
    $item_rows = $this->db->get_records($query);
    if (! $item_rows) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       return null;
    }
    $sub_total = 0;
    $cart_items = array();
    foreach ($item_rows as $row) {
       $item_id = $row['id'];
       if ($round_cents) {
          if (($this->currency != 'USD') && ($this->exchange_rate !== null) &&
              ($this->exchange_rate != 0.0)) {
             $price = floatval($row['price']) * $this->exchange_rate;
             $price = floor($price);
             $row['price'] = round($price / $this->exchange_rate,2);
          }
          else $row['price'] = floor($row['price']);
       }
       $row['qty'] = format_qty($row['qty']);
       $cart_items[$item_id] = $row;
       $qty = $row['qty'];
       $price = $row['price'];
       if ($row['flags'] & QTY_PRICE) $sub_total += $price;
       else $sub_total += ($price * $qty);
       $attribute_array = build_attribute_array($this->db,'cart',$row);
       $cart_items[$item_id]['attribute_array'] = $attribute_array;

/* removed because attribute price is already added in load_item_price
   and then put back in because it broke econoenvelope.  I don't know why
   it needed to be removed --  Randall 1/27/12 */

       if (isset($attribute_array)) {
          foreach ($attribute_array as $attribute) {
             if ($row['flags'] & QTY_PRICE) $sub_total += $attribute['price'];
             else $sub_total += ($attribute['price'] * $qty);
          }
       }
    }

    $this->items = $cart_items;
    $this->num_items = count($cart_items);
    $this->set('subtotal',$sub_total);
    $total = $sub_total;
    if (isset($this->info['discount_name']) && $this->info['discount_name'])
       $total -= floatval($this->info['discount_amount']);
    $this->set('total',$total);
    if (isset($this->info['coupon_code']) && $this->info['coupon_code'])
       $this->process_coupon($this->info['coupon_code']);
    return $cart_items;
}

function get_cart_item_lookup_attributes($db,$cart_item)
{
    if (! $cart_item['attributes']) return null;
    $product_id = $cart_item['product_id'];
    $no_options = check_no_option_attributes($db,$product_id);
    if (! is_array($cart_item['attributes'])) {
       if ($no_options)
          $cart_item['attributes'] = explode('|',$cart_item['attributes']);
       else $cart_item['attributes'] = explode('-',$cart_item['attributes']);
    }
    if (isset($cart_item['attributes'][0]) &&
        ($cart_item['attributes'][0] == '*')) return null;
    $lookup_attributes = build_lookup_attributes($db,
          $product_id,$cart_item['attributes'],true,$no_options);
    if ($lookup_attributes)
       $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
          $product_id,$no_options,$db);
    return $lookup_attributes;
}

function load_part_numbers($cart_items = null)
{
    if (! $cart_items) {
       if (! isset($this->items)) return null;
       $cart_items = $this->items;
    }
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    foreach ($cart_items as $id => $cart_item) {
       if ($cart_item['part_number']) continue;
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if (isset($this)) $lookup_attributes =
          $this->get_cart_item_lookup_attributes($db,$cart_item);
       else $lookup_attributes =
          Cart::get_cart_item_lookup_attributes($db,$cart_item);
       $query = 'select part_number from product_inventory where parent=? and ';
       if ($lookup_attributes) {
          $query .= 'attributes=?';
          $query = $db->prepare_query($query,$product_id,$lookup_attributes);
       }
       else {
          $query .= '(isnull(attributes) or (attributes=""))';
          $query = $db->prepare_query($query,$product_id);
       }
       $row = $db->get_record($query);
       if ($row) {
          if (isset($this))
             $this->items[$id]['part_number'] = $row['part_number'];
          else $cart_items[$id]['part_number'] = $row['part_number'];
       }
       else if (isset($db->error)) {
          if (isset($this)) $this->error = $db->error;
       }
    }
    if (isset($this)) return $this->items;
    else return $cart_items;
}

function load_images($cart_items = null,$size = 'medium')
{
    global $use_dynamic_images,$image_subdir_prefix,$dynamic_image_url;

    if (! $cart_items) {
       if (! isset($this->items)) return null;
       $cart_items = $this->items;
    }
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    if (isset($this) && (! empty($this->shared_cart))) {
       $query = 'select id,icon from web_sites';
       $website_icons = $db->get_records($query,'id','icon');
    }
    foreach ($cart_items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if (isset($this)) $lookup_attributes =
          $this->get_cart_item_lookup_attributes($db,$cart_item);
       else $lookup_attributes =
          Cart::get_cart_item_lookup_attributes($db,$cart_item);
       $query = 'select image from product_inventory where parent=? and ';
       if ($lookup_attributes) {
          $query .= 'attributes=?';
          $query = $db->prepare_query($query,$product_id,$lookup_attributes);
       }
       else {
          $query .= '(isnull(attributes) or (attributes=""))';
          $query = $db->prepare_query($query,$product_id);
       }
       $row = $db->get_record($query);
       if ($row && $row['image']) $image_filename = $row['image'];
       else {
          $query = 'select filename from images where parent_type=1 and ' .
                   'parent=? order by sequence limit 1';
          $query = $db->prepare_query($query,$product_id);
          $image_row = $db->get_record($query);
          if (! $image_row) continue;
          $image_filename = $image_row['filename'];
       }
       if ($use_dynamic_images) {
          if (isset($dynamic_image_url))
             $image_url = $dynamic_image_url.'?cmd=loadimage&filename=' .
                          rawurlencode($image_filename).'&size='.$size;
          else $image_url = 'cartengine/image.php?cmd=loadimage&filename=' .
                            rawurlencode($image_filename).'&size='.$size;
       }
       else if ($image_subdir_prefix)
          $image_url = 'images/medium/' .
             substr($image_filename,0,$image_subdir_prefix).'/' .
             rawurlencode($image_filename);
       else $image_url = 'images/'.$size.'/'.rawurlencode($image_filename);
       if (isset($this) && (! empty($this->shared_cart)) &&
           $cart_item['website'] &&
           isset($website_icons[$cart_item['website']]))
          $website_icon = $website_icons[$cart_item['website']];
       else $website_icon = null;
       if (isset($this)) {
          $this->items[$id]['image'] = $image_url;
          $this->items[$id]['image_filename'] = $image_filename;
          if ($website_icon) $this->items[$id]['website_icon'] = $website_icon;
       }
       else {
          $cart_items[$id]['image'] = $image_url;
          $cart_items[$id]['image_filename'] = $image_filename;
          if ($website_icon) $cart_items[$id]['website_icon'] = $website_icon;
       }
    }
    if (isset($this)) return $this->items;
    else return $cart_items;
}

function load_product_urls($cart_items = null)
{
    global $enable_multisite,$website_id;

    require_once 'catalog-public.php';

    if (! $cart_items) {
       if (! isset($this->items)) return null;
       $cart_items = $this->items;
    }
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    $product_ids = array();
    foreach ($cart_items as $cart_item) {
       $product_id = $cart_item['product_id'];
       if ($product_id) {
          if (! in_array($product_id,$product_ids))
             $product_ids[] = $product_id;
       }
    }
    if (count($product_ids) == 0) {
       if (isset($this)) return $this->items;
       else return $cart_items;
    }
    $query = 'select id,seo_category,seo_url,flags from products where ' .
             'id in (?)';
    $query = $db->prepare_query($query,$product_ids);
    $product_info = $db->get_records($query,'id');
    foreach ($cart_items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if (! isset($product_info[$product_id])) continue;
       $url = @Catalog::get_product_url_string(null,$product_info[$product_id],
                                               null,true,false,$db);
       if ((! empty($enable_multisite)) &&
           ($cart_item['website'] != $website_id)) {
          $query = 'select base_href from web_sites where id=?';
          $query = $db->prepare_query($query,$cart_item['website']);
          $row = $db->get_record($query);
          if (! empty($row['base_href'])) $url = $row['base_href'].$url;
       }
       if (isset($this)) {
          $this->items[$id]['url'] = $url;
          $this->items[$id]['product_flags'] =
             $product_info[$product_id]['flags'];
       }
       else {
          $cart_items[$id]['url'] = $url;
          $cart_items[$id]['product_flags'] =
             $product_info[$product_id]['flags'];
       }
    }
    if (isset($this)) return $this->items;
    else return $cart_items;
}

function load_on_account_products($cart_items = null)
{
    global $user_cookie,$customer;

    if (! $cart_items) {
       if (! isset($this->items)) return null;
       $cart_items = $this->items;
    }
    if (isset($customer->id)) $customer_id = $customer->id;
    else $customer_id = get_cookie($user_cookie);
    if (! $customer_id) return $cart_items;
    if (isset($this)) $db = $this->db;
    else $db = new DB;

    $query = 'select account_id from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $row = $db->get_record($query);
    if (empty($row['account_id'])) return $cart_items;

    $account_id = $row['account_id'];
    $query = 'select related_id from account_products where (parent=?) and ' .
             '((not isnull(on_account)) and (on_account=1))';
    $query = $db->prepare_query($query,$account_id);
    $on_account_products = $db->get_records($query,'related_id');

    $product_ids = array();
    foreach ($cart_items as $cart_item) {
       $product_id = $cart_item['product_id'];
       if ($product_id) {
          if (! in_array($product_id,$product_ids))
             $product_ids[] = $product_id;
       }
    }
    $query = 'select id,flags from products where id in (?) and (flags&512)';
    $query = $db->prepare_query($query,$product_ids);
    $product_info = $db->get_records($query,'id');

    if (empty($on_account_products) && empty($product_info))
       return $cart_items;

    foreach ($cart_items as $id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (! $product_id) continue;
       if ((! isset($on_account_products[$product_id])) &&
           (! isset($product_info[$product_id]))) continue;
       if (isset($this)) $this->items[$id]['on_account'] = true;
       else $cart_items[$id]['on_account'] = true;
       if (get_form_field('on_account_'.$id)) {
          if (! isset($cart_items[$id]['flags']))
             $cart_items[$id]['flags'] = 0;
          $cart_items[$id]['flags'] |= ON_ACCOUNT_ITEM;
          if (isset($this))
             $this->items[$id]['flags'] = $cart_items[$id]['flags'];
       }
    }

    if (isset($this)) return $this->items;
    else return $cart_items;
}

function clear()
{
    $query = 'delete from '.$this->attr_table.' where parent in ' .
             '(select id from '.$this->item_table.' where parent=?)';
    $query = $this->db->prepare_query($query,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }

    $query = 'delete from '.$this->item_table.' where parent=?';
    $query = $this->db->prepare_query($query,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }

/* use CART_DELETED flag until MySQL Bug #199 is fixed
            (http://bugs.mysql.com/bug.php?id=199)
    $query = 'delete from cart where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
*/
    if (! $this->set_flags($this->flags|CART_DELETED)) return false;

    $this->num_items = 0;
    log_activity('Deleted All Items in '.$this->label.' #'.$this->id);
    if ((! $this->object_only) && (! $this->no_cart_cookie)) {
       $cart_id = get_cookie($this->cookie);
       if ($cart_id) $this->set_cookie($this->cookie,0);
    }
    return true;
}

function set($field_name,$field_value)
{
    $this->info[$field_name] = $field_value;
}

function get($field_name)
{
    if (isset($this->info) && isset($this->info[$field_name]))
       return $this->info[$field_name];
    return '';
}

function get_taxable_subtotal($obj)
{
    global $amount_cents_flag,$taxable_attributes;

    if (isset($amount_cents_flag) && (! $amount_cents_flag))
       $round_cents = true;
    else $round_cents = false;
    if (! isset($taxable_attributes)) $taxable_attributes = false;
    load_order_taxable_flags($obj);
    if (! isset($obj->items)) return 0;
    $items = $obj->items;
    $sub_total = 0;
    foreach ($items as $row) {
       $qty = $row['qty'];
       if ($row['taxable'] !== '0') {
          if ($round_cents) {
             if (($obj->currency != 'USD') && ($obj->exchange_rate !== null) &&
                 ($obj->exchange_rate != 0.0)) {
                $price = floatval($row['price']) * $obj->exchange_rate;
                $price = floor($price);
                $row['price'] = round($price / $obj->exchange_rate,2);
             }
             else $row['price'] = floor($row['price']);
          }
          $price = $row['price'];
          if ($row['flags'] & QTY_PRICE) $sub_total += $price;
          else $sub_total += ($price * $qty);
       }
       $attribute_array = build_attribute_array($this->db,'cart',$row);
       if (isset($attribute_array)) {
          $attr_ids = array();   $option_ids = array();
          $attr_options = array();
          foreach ($attribute_array as $index => $attribute) {
             $option_id = $attribute['id'];
             if (isset($option_id[0]) && ($option_id[0] == '*')) {
                $attr_info = explode('*',$option_id);
                if (count($attr_info) != 3) continue;
                $attr_id = $attr_info[1];   $attr_ids[] = $attr_id;
                $attribute_array[$index]['attr_id'] = $attr_id;
             }
             else {
                if (is_numeric($option_id) && $option_id)
                   $option_ids[] = $option_id;
                $attribute_array[$index]['option_id'] = $option_id;
             }
          }
          if (count($option_ids) > 0) {
             $query = 'select id,parent from attribute_options where id in (?)';
             $query = $obj->db->prepare_query($query,$option_ids);
             $result = $obj->db->query($query);
             if ($result) {
                while ($option_row = $this->db->fetch_assoc($result)) {
                   foreach ($attribute_array as $index => $attribute) {
                      if (isset($attribute_array[$index]['option_id']) &&
                          ($attribute_array[$index]['option_id'] ==
                           $option_row['id'])) {
                         $attribute_array[$index]['attr_id'] =
                            $option_row['parent'];
                         $attr_ids[] = $option_row['parent'];   break;
                      }
                   }
                }
                $this->db->free_result($result);
             }
          }
          if ($taxable_attributes) {
             if (count($attr_ids) > 0) {
                $query = 'select id,taxable from attributes where id in (?)';
                $query = $obj->db->prepare_query($query,$attr_ids);
                $result = $obj->db->query($query);
                if ($result) {
                   while ($attr_row = $this->db->fetch_assoc($result)) {
                      foreach ($attribute_array as $index => $attribute) {
                         if (isset($attribute_array[$index]['attr_id']) &&
                             ($attribute_array[$index]['attr_id'] ==
                              $attr_row['id'])) {
                            $attribute_array[$index]['taxable'] =
                               $attr_row['taxable'];
                            break;
                         }
                      }
                   }
                   $this->db->free_result($result);
                }
             }
          }
          foreach ($attribute_array as $attribute) {
             if ($taxable_attributes && isset($attribute['taxable']) &&
                 ($attribute['taxable'] === '0')) continue;
             if ($row['flags'] & QTY_PRICE) $sub_total += $attribute['price'];
             else $sub_total += ($attribute['price'] * $qty);
          }
       }
    }
    if (function_exists('custom_update_taxable_subtotal'))
       custom_update_taxable_subtotal($obj,$sub_total);
    return $sub_total;
}

function calculate_tax_amount($obj,$customer)
{
    global $taxcloud_api_id,$tax_round,$account_cookie;
    global $multiple_customer_accounts,$avalara_all_states;

    if ($customer->shipping_country == 1) {
       if ($customer->get('cust_tax_exempt') == 1) return;
       if ($multiple_customer_accounts)
          $account_id = get_cookie($account_cookie);
       else $account_id = $customer->get('cust_account_id');
       if ($account_id) {
          $query = 'select tax_exempt from accounts where id=?';
          $query = $customer->db->prepare_query($query,$account_id);
          $row = $customer->db->get_record($query);
          if ($row && $row['tax_exempt']) return;
       }
       $avalara_api_type = -1;
       if (file_exists('../admin/modules/avalara.php')) {
          require_once '../admin/modules/avalara.php';
          if (get_avatax_certificate($obj,$customer)) return;
          $avalara_api_type = get_avalara_api_type($obj);
          if (($avalara_api_type != -1) &&
              ($avalara_api_type != AVATAX_TRANSACTION)) {
             $tax_rate = load_state_tax($customer->get('ship_state'),$obj->db);
             if ($tax_rate || (! empty($avalara_all_states)))
                $tax_rate = get_avalara_tax_rate($obj,$customer,$tax_rate);
          }
       }
       else if (! isset($taxcloud_api_id))
          $tax_rate = load_state_tax($customer->get('ship_state'),$obj->db);
       if (isset($taxcloud_api_id) || ($avalara_api_type == 1) ||
           (! empty($tax_rate))) {
          if ($obj instanceof Order) $prefix = 'order_';
          else if ($obj instanceof OrderInfo) $prefix = 'order_';
          else $prefix = '';
          $subtotal = $obj->get($prefix.'subtotal');
          $coupon_amount = $obj->get($prefix.'coupon_amount');
          $discount_amount = $obj->get($prefix.'discount_amount');
          $gift_amount = $obj->get($prefix.'gift_amount');
          $fee_amount = $obj->get($prefix.'fee_amount');
          if ($coupon_amount) $subtotal -= $coupon_amount;
          if ($discount_amount) $subtotal -= $discount_amount;
          if (isset($taxcloud_api_id)) {
             require_once 'taxcloud.php';
             $tax = get_taxcloud_sales_tax($obj,$customer);
          }
          else if ($avalara_api_type == 1)
             $tax = get_avalara_sales_tax($obj,$customer);
          else {
             $taxable_subtotal = @Cart::get_taxable_subtotal($obj);
             if ($coupon_amount) $taxable_subtotal -= $coupon_amount;
             if ($discount_amount) $taxable_subtotal -= $discount_amount;
             if ($taxable_subtotal < 0) $taxable_subtotal = 0;
             if (is_array($tax_rate)) {
                $tax = 0;
                foreach ($tax_rate as $rate) {
                   if (isset($tax_round) && ($tax_round !== true)) {
                      if ($tax_round === 'floor')
                         $tax += (floor($taxable_subtotal * $rate) / 100);
                      else $tax += (ceil($taxable_subtotal * $rate) / 100);
                   }
                   else $tax += (round($taxable_subtotal * $rate) / 100);
                }
             }
             else if (isset($tax_round) && ($tax_round !== true)) {
                if ($tax_round === 'floor')
                   $tax = floor($taxable_subtotal * $tax_rate) / 100;
                else $tax = ceil($taxable_subtotal * $tax_rate) / 100;
             }
             else $tax = round($taxable_subtotal * $tax_rate) / 100;
          }
          $total = $subtotal + $tax;
          $obj->set('tax',$tax);
          if ($gift_amount) $total -= $gift_amount;
          if ($fee_amount) $total += $fee_amount;
          $obj->set('total',$total);
       }
    }
    if (function_exists('custom_calculate_tax'))
       custom_calculate_tax($obj,$customer);
}

function calculate_tax($customer)
{
    $this->calculate_tax_amount($this,$customer);
}

function check_coupon_products($coupon_id,&$coupon_products,&$product_total,
                               $flags)
{
    global $enable_coupon_inventory;

    $this->coupon_cart_items = array();
    if (! isset($this->items)) return false;
    $starting_product_total = $product_total;
    if (! isset($enable_coupon_inventory)) $enable_coupon_inventory = false;
    else if (! ($flags & COUPON_SELECTED_PRODUCTS))
       $enable_coupon_inventory = false;
    $query = 'select id from products where flags&'.NO_COUPONS;
    $no_coupon_products = $this->db->get_records($query,'id');
    if ($no_coupon_products === null) $no_coupon_products = array();
    if ($flags & COUPON_SELECTED_PRODUCTS) {
       $query = 'select related_id from coupon_products where parent=?';
       $query = $this->db->prepare_query($query,$coupon_id);
       $coupon_products = $this->db->get_records($query,null,'related_id');
       if (! $coupon_products) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;
          }
          return false;
       }
    }
    if ($flags & COUPON_EXCLUDE_PRODUCTS) {
       $query = 'select related_id from coupon_excluded_products ' .
                'where parent=?';
       $query = $this->db->prepare_query($query,$coupon_id);
       $excluded_products = $this->db->get_records($query,null,'related_id');
       if (! $excluded_products) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;
          }
          $excluded_products = array();
       }
    }
    if ($enable_coupon_inventory) {
       $query = 'select related_id from coupon_inventory where parent=?';
       $query = $this->db->prepare_query($query,$coupon_id);
       $coupon_inventory = $this->db->get_records($query,null,'related_id');
       if (! $coupon_inventory) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;
          }
          return false;
       }
    }
    $product_found = false;   $product_total = 0;   $num_found = 0;
    $cart_items = $this->items;   $num_cart_items = count($cart_items);
    foreach ($cart_items as $item_id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (isset($no_coupon_products[$product_id])) continue;
       if (($flags & COUPON_EXCLUDE_PRODUCTS) &&
           in_array($product_id,$excluded_products)) continue;
       if ((! ($flags & COUPON_SELECTED_PRODUCTS)) ||
           in_array($product_id,$coupon_products)) {
          if ($enable_coupon_inventory) {
             $attributes = $cart_item['attributes'];
             $no_options = check_no_option_attributes($this->db,$product_id);
             if ($no_options) $attributes = explode('|',$attributes);
             else $attributes = explode('-',$attributes);
             $lookup_attributes = build_lookup_attributes($this->db,
                $product_id,$attributes,false,$no_options);
             if ($lookup_attributes) {
                $lookup_attributes = reorder_attributes_by_id(
                   $lookup_attributes,$product_id,$no_options,$this->db);
                $query = 'select id from product_inventory where parent=? ' .
                         'and attributes=?';
                $query = $this->db->prepare_query($query,$product_id,
                                                  $lookup_attributes);
             }
             else {
                $query = 'select id from product_inventory where parent=? ' .
                         'and (isnull(attributes) or (attributes=""))';
                $query = $this->db->prepare_query($query,$product_id);
             }
             $row = $this->db->get_record($query);
             if (! $row) continue;
             if (! in_array($row['id'],$coupon_inventory)) continue;
          }
          $product_found = true;
          $product_total += ($cart_item['price'] * $cart_item['qty']);
          $this->coupon_cart_items[] = $item_id;
          $num_found++;
       }
    }
    if (($num_found == $num_cart_items) ||
        ($product_total > $starting_product_total))
       $product_total = $starting_product_total;
    if (! empty($no_coupon_products))
       $this->no_coupon_products = $no_coupon_products;
    if (! empty($coupon_products))
       $this->coupon_products = $coupon_products;
    if (! empty($excluded_products))
       $this->excluded_products = $excluded_products;
    if ($product_found) return true;
    else return false;
}

function check_no_coupon_products(&$product_total)
{
    $this->coupon_cart_items = array();
    if (! isset($this->items)) return false;
    $starting_product_total = $product_total;
    $query = 'select id from products where flags&'.NO_COUPONS;
    $no_coupon_products = $this->db->get_records($query,'id');
    $product_found = false;   $product_total = 0;   $num_found = 0;
    $cart_items = $this->items;   $num_cart_items = count($cart_items);
    foreach ($cart_items as $item_id => $cart_item) {
       $product_id = $cart_item['product_id'];
       if (isset($no_coupon_products[$product_id])) continue;
       $product_found = true;
       $product_total += ($cart_item['price'] * $cart_item['qty']);
       $this->coupon_cart_items[] = $item_id;
       $num_found++;
    }
    if (($num_found == $num_cart_items) ||
        ($product_total > $starting_product_total))
       $product_total = $starting_product_total;
    if ($product_found) return true;
    else return false;
}

function check_coupon_customer($coupon_id)
{
    if (! isset($this->info['customer_id'])) return false;
    $query = 'select related_id from coupon_customers where parent=?';
    $query = $this->db->prepare_query($query,$coupon_id);
    $rows = $this->db->get_records($query);
    if (! $rows) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;
       }
       return false;
    }
    $customer_id = $this->info['customer_id'];
    $customer_found = false;
    foreach ($rows as $row)
       if ($row['related_id'] == $customer_id) $customer_found = true;
    return $customer_found;
}

function check_coupon_websites($websites,&$product_total)
{
    global $website_id;

    $websites = explode(',',$websites);
    if (empty($this->shared_cart)) return in_array($website_id,$websites);
    if (empty($this->items)) return false;

    $starting_product_total = $product_total;
    $product_found = false;   $product_total = 0;   $num_found = 0;
    $cart_items = $this->items;   $num_cart_items = count($cart_items);
    foreach ($cart_items as $cart_item) {
       if (in_array($cart_item['website'],$websites)) {
          $product_found = true;
          $product_total += ($cart_item['price'] * $cart_item['qty']);
          $num_found++;
       }
    }
    if (($num_found == $num_cart_items) ||
        ($product_total > $starting_product_total))
       $product_total = $starting_product_total;
    if ($product_found) return true;
    else return false;
}

function midnight($timestamp)
{
    return strtotime('0:00',$timestamp);
}

function verify_coupon($row,&$product_total)
{
    global $log_cart_errors_enabled,$enable_multisite;

    $coupon_id = $row['id'];
    $coupon_code = $row['coupon_code'];
    $today = time();
    if ($row['start_date'] && ($today < $this->midnight($row['start_date']))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' is not yet valid');
       $this->errors['CouponNotYet'] = true;   return false;
    }
    if ($row['end_date'] &&
        ($today > ($this->midnight($row['end_date']) + 86400))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code.' has expired ' .
            '(today='.$today.', end_date='.$row['end_date'].', end_time=' .
             ($this->midnight($row['end_date']) + 86400).')');
       $this->errors['ExpiredCoupon'] = true;   return false;
    }
    $max_qty = $row['max_qty'];
    if ($max_qty == '') $max_qty = 0;
    $qty_used = $row['qty_used'];
    if ($qty_used == '') $qty_used = 0;
    if (($max_qty != 0) && ($qty_used >= $max_qty)) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: No more coupons for '.$coupon_code .
                         ' are available');
       $this->errors['NoMoreCoupons'] = true;   return false;
    }
    $max_qty = $row['max_qty_per_cust'];
    if ($max_qty == '') $max_qty = 0;
    if (($max_qty != 0) && isset($this->info['customer_id']) &&
        $this->info['customer_id']) {
       $customer_id = $this->info['customer_id'];
       $query = 'select count(id) as qty_used from orders where ' .
                'customer_id=? and coupon_id=?';
       $query = $this->db->prepare_query($query,$customer_id,$row['id']);
       $qty_row = $this->db->get_record($query);
       if ($qty_row) $qty_used = intval($qty_row['qty_used']);
       else $qty_used = 0;
       if ($qty_used >= $max_qty) {
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: No more coupons for '.$coupon_code .
                            ' are available for this customer');
          $this->errors['NoMoreCoupons'] = true;   return false;
       }
    }
    $flags = $row['flags'];
    if (! $flags) $flags = 0;

    if (((($flags & COUPON_SELECTED_PRODUCTS) ||
          ($flags & COUPON_EXCLUDE_PRODUCTS)) &&
         (! $this->check_coupon_products($coupon_id,$product_ids,
                                         $product_total,$flags))) ||
        (! $this->check_no_coupon_products($product_total))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' is not available for products in cart');
       $this->errors['CouponNoProduct'] = true;   return false;
    }
    if (($flags & COUPON_SELECTED_CUSTOMERS) &&
        (! $this->check_coupon_customer($coupon_id))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' is not available for this customer');
       $this->errors['CouponNoCustomer'] = true;   return false;
    }
    if ((! empty($enable_multisite)) &&
        (! $this->check_coupon_websites($row['websites'],$product_total))) {
       global $website_id;
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' is not available for web site #'.$website_id);
       $this->errors['CouponNoWebSite'] = true;   return false;
    }
    if (! empty($this->account_id)) {
       $query = 'select no_coupons from accounts where id=?';
       $query = $this->db->prepare_query($query,$this->account_id);
       $account_row = $this->db->get_record($query);
       if (! empty($account_row['no_coupons'])) {
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Account #'.$this->account_id .
                            ' does not allow coupons');
          $this->errors['CouponNoCustomer'] = true;   return false;
       }
    }
    if (($flags & COUPON_ONLY_REGISTERED) &&
        ((! isset($this->info['customer_id'])) ||
         (! $this->info['customer_id']))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' is not available for guest customers');
       $this->errors['CouponRegOnly'] = true;   return false;
    }
    $min_amount = $row['min_amount'];
    if ($min_amount == '') $min_amount = 0;
    if ($product_total < $min_amount) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code .
                         ' has a minimum amount of '.$min_amount);
       $this->errors['CouponMinAmount'] = true;
       $this->info['coupon_min_amount'] = $min_amount;   return false;
    }
    return true;
}

function apply_free_product($coupon_code,$free_product,$attributes)
{
    global $log_cart_errors_enabled;

    if (isset($this->items)) {
       $free_product_found = false;   $other_product_found = false;
       foreach ($this->items as $item) {
          if (($item['product_id'] == $free_product) &&
              ($attributes == $item['attributes'])) {
             if ($free_product_found) $other_product_found = true;
             else $free_product_found = true;
             if ($item['qty'] > 1) $other_product_found = true;
             $coupon_amount = $item['price'];
          }
          else $other_product_found = true;
       }
       if ($free_product_found) {
          if ($other_product_found) {
             $this->info['coupon_amount'] = $coupon_amount;
             return true;
          }
          else {
             if ($log_cart_errors_enabled)
                log_cart_error('Cart Error: Coupon '.$coupon_code .
                               ' can not be used for the free product');
             $this->errors['CouponNoProduct'] = true;   return false;
          }
       }
    }
    $attributes = explode('-',$attributes);
    if (! $this->add_item($free_product,$attributes,1)) return false;
    $this->info['coupon_amount'] = $this->items[$this->last_item_id]['price'];
    return true;
}

function get_coupon_quantity_discount($flags)
{
    $coupon_amount = 0;   $coupon_id = $this->info['coupon_id'];
    $query = 'select * from coupon_discounts where parent=?';
    $query = $this->db->prepare_query($query,$coupon_id);
    $coupon_discounts = $this->db->get_records($query);
    if (! $coupon_discounts) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
       return 0;
    }
    if ($flags & COUPON_SELECTED_PRODUCTS) {
       $query = 'select related_id from coupon_products where parent=?';
       $query = $this->db->prepare_query($query,$coupon_id);
       $coupon_products = $this->db->get_records($query,null,'related_id');
       if (! $coupon_products) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
          return 0;
       }
    }
    else $coupon_products = null;
    $query = 'select id from products where flags&'.NO_COUPONS;
    $no_coupon_products = $this->db->get_records($query,'id');
    $cart_items = $this->items;
    foreach ($cart_items as $cart_item) {
       $product_id = $cart_item['product_id'];
       if ($coupon_products && (! in_array($product_id,$coupon_products)))
          continue;
       if (isset($no_coupon_products[$product_id])) continue;
       $qty = $cart_item['qty'];
       foreach ($coupon_discounts as $discount) {
          if (! $discount['discount']) continue;
          if ($qty < $discount['start_qty']) continue;
          if ($discount['end_qty'] && ($qty > $discount['end_qty']))
             continue;
          $factor = $discount['discount'] / 100;
          $item_total = $cart_item['price'] * $qty;
          $coupon_amount += round($item_total * $factor,2);
          break;
       }
    }
    return $coupon_amount;
}

function check_coupon_product($cart_item,$flags)
{
    if (empty($cart_item['product_id'])) $product_id = 0;
    else $product_id = $cart_item['product_id'];
    if ((! empty($this->no_coupon_products)) &&
        isset($this->no_coupon_products[$product_id])) return false;
    if (($flags & COUPON_EXCLUDE_PRODUCTS) &&
        (! empty($this->excluded_products)) &&
        in_array($product_id,$this->excluded_products)) return false;
    if ($flags & COUPON_SELECTED_PRODUCTS) {
       if ((! empty($this->coupon_products)) &&
           in_array($product_id,$this->coupon_products)) return true;
       else return false;
    }
    return true;
}

function process_coupon($coupon_code,$row=null)
{
    global $log_cart_errors_enabled;

    if (! $row) {
       $query = 'select * from coupons where coupon_code=?';
       $query = $this->db->prepare_query($query,$coupon_code);
       $row = $this->db->get_record($query);
       if ((! $row) && isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }
    if ((! $row) || ($row['coupon_type'] == 4)) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Coupon '.$coupon_code.' not found');
       $this->errors['InvalidCoupon'] = true;  return false;
    }
    if (isset($this->info['subtotal'])) $subtotal = $this->info['subtotal'];
    else $subtotal = 0;
    $product_total = $subtotal;
    if (function_exists('custom_process_coupon') &&
        (! custom_process_coupon($row,$this))) return false;
    if (! $this->verify_coupon($row,$product_total)) return false;
    $coupon_type = $row['coupon_type'];
    $flags = $row['flags'];
    $this->info['coupon_id'] = $row['id'];
    $this->info['coupon_code'] = $coupon_code;
    $this->info['coupon_type'] = $coupon_type;
    if (isset($this->info['total'])) $total = $this->info['total'];
    else $total = 0;
    if (function_exists('custom_process_coupon_amount') &&
        custom_process_coupon_amount($row,$this)) {}
    else if ($coupon_type == 1) {   // Percentage Off
       if ($this->currency == 'HUF')
          $this->info['coupon_amount'] = floor(($product_total *
                                               ($row['amount']/100)));
       else $this->info['coupon_amount'] = round(($product_total *
                                                 ($row['amount']/100)),2);
    }
    else if ($coupon_type == 2) {   //  Amount Off
       if ($row['amount'] > $product_total)
          $this->info['coupon_amount'] = $product_total;
       else $this->info['coupon_amount'] = $row['amount'];
    }
    else if ($coupon_type == 3) {   // Free Shipping
       if (! empty($this->coupon_cart_items))
          $this->free_shipping_items = $this->coupon_cart_items;
       else $this->free_shipping = true;
    }
    else if ($coupon_type == 5) {   // Free Order
       if ($flags & COUPON_SELECTED_PRODUCTS) {
          $this->info['coupon_amount'] = $product_total;
          if (! empty($this->customer))
             $this->calculate_tax($this->customer);
       }
       else {
          $this->info['coupon_amount'] = $product_total;
          if (isset($this->info['tax'])) $tax = $this->info['tax'];
          else $tax = 0;
          if ($tax && isset($this->info['total']))
             $this->info['total'] -= $tax;
          $this->set('tax',0);
       }
    }
    else if ($coupon_type == 6) {   // Free Product
       $free_product = $row['free_product'];
       if (! $free_product) $this->info['coupon_amount'] = 0;
       else if (! $this->apply_free_product($coupon_code,$free_product,
                                            $row['free_prod_attrs']))
          return false;
    }
    else if ($coupon_type == 7) {   // Buy 1 Get 1 at x% Off
       $cart_items = $this->items;   $product_total = 0;
       if ($flags & COUPON_SAME_LOWER) {
          $highest_price = 0;   $highest_id = -1;
          foreach ($cart_items as $cart_item) {
             if (! $this->check_coupon_product($cart_item,$flags)) continue;
             if ($cart_item['price'] > $highest_price) {
                $highest_price = $cart_item['price'];
                $highest_id = $cart_item['id'];
             }
          }
          foreach ($cart_items as $cart_item) {
             if (! $this->check_coupon_product($cart_item,$flags)) continue;
             if ($cart_item['price'] <= $highest_price) {
                if ($cart_item['id'] != $highest_id) {
                   $product_total = $cart_item['price'];   break;
                }
                if ($cart_item['qty'] > 1) {
                   $num_off = floor($cart_item['qty'] / 2);
                   $product_total += ($cart_item['price'] * $num_off);
               }
             }
          }
       }
       else {
          foreach ($cart_items as $cart_item) {
             if (! $this->check_coupon_product($cart_item,$flags)) continue;
             if ($cart_item['qty'] > 1) {
                $num_off = floor($cart_item['qty'] / 2);
                $product_total += ($cart_item['price'] * $num_off);
             }
          }
       }
       if ($product_total == 0) $coupon_amount = 0;
       else $coupon_amount = round(($product_total * ($row['amount']/100)),2);
       $this->info['coupon_amount'] = $coupon_amount;
    }
    else if ($coupon_type == 8) {   // Quantity Discount
       $coupon_amount = $this->get_coupon_quantity_discount($flags);
       if ($coupon_amount === false) return false;
       $this->info['coupon_amount'] = $coupon_amount;
    }
    else $this->info['coupon_amount'] = 0;
    if (isset($this->info['coupon_amount'])) {
       if ($this->info['coupon_amount'] > $total)
          $this->info['coupon_amount'] = $total;
       if (isset($this->info['total']))
          $this->info['total'] -= $this->info['coupon_amount'];
    }
    else $this->info['coupon_amount'] = 0;
    if ($flags & COUPON_FREE_SHIPPING) {
       if (! empty($this->coupon_cart_items))
          $this->free_shipping_items = $this->coupon_cart_items;
       else $this->free_shipping = true;
    }

    return true;
}

function save_coupon($coupon_code)
{
    $query = 'update '.$this->main_table.' set coupon_code=? where id=?';
    $query = $this->db->prepare_query($query,$coupon_code,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function delete_coupon()
{
    global $cart_cookie;

    if (isset($this)) {
       if ((! isset($this->info['coupon_code'])) ||
           (! $this->info['coupon_code'])) return;
       $this->info['coupon_code'] = null;
       $cart_id = $this->id;   $db = $this->db;
    }
    else {
       $cart_id = get_cookie($cart_cookie);
       if (! is_numeric($cart_id)) return;
       $db = new DB;
       $query = 'select coupon_code from cart where id=?';
       $query = $db->prepare_query($query,$cart_id);
       $row = $db->get_record($query);
       if ((! $row) || (! $row['coupon_code'])) return;
    }
    $query = 'update cart set coupon_code=null where id=?';
    $query = $db->prepare_query($query,$cart_id);
    $db->log_query($query);
    $db->query($query);
}

function process_special_offers()
{
    global $log_cart_errors_enabled;

    if (isset($this->error)) return;
    $query = 'select * from coupons where flags&'.COUPON_SPECIAL_OFFER;
    $rows = $this->db->get_records($query,'id');
    if ((! $rows) || count($rows) == 0) return;
    $original_info = $this->info;   $original_errors = $this->errors;
    $highest_amount = 0;   $highest_id = -1;

    $orig_log_errors = $log_cart_errors_enabled;
    $log_cart_errors_enabled = false;
    foreach ($rows as $row) {
       $coupon_id = $row['id'];
       if ($this->process_coupon('~'.$coupon_id,$row)) {
          if ($this->info['coupon_amount'] > $highest_amount) {
             $highest_amount = $this->info['coupon_amount'];
             $highest_id = $row['id'];
          }
          if (($row['coupon_type'] == 3) ||
              ($row['flags'] & COUPON_FREE_SHIPPING)) {
             if (! empty($this->coupon_cart_items))
                $this->free_shipping_items = $this->coupon_cart_items;
             else $this->free_shipping = true;
          }
       }
       $this->info = $original_info;   $this->errors = $original_errors;
       if (isset($this->error)) unset($this->error);
    }
    $log_cart_errors_enabled = $orig_log_errors;
    if ($highest_id == -1) return;
    $this->process_coupon('~'.$highest_id,$rows[$highest_id]);
}

function process_gift_certificate($gift_code)
{
    global $log_cart_errors_enabled;

    $query = 'select id,coupon_type,amount,balance,gift_customer,' .
             'start_date,end_date,flags from coupons where coupon_code=?';
    $query = $this->db->prepare_query($query,$gift_code);
    $row = $this->db->get_record($query);
    if ((! $row) && isset($this->db->error)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }

    if ((! $row) || ($row['coupon_type'] != 4)) {
       if ($log_cart_errors_enabled)
          log_cart_error('Error: Gift Certificate '.$gift_code.' not found');
       $this->errors['InvalidGiftCert'] = true;  return false;
    }
    $today = time();
    if ($row['start_date'] && ($today < $this->midnight($row['start_date']))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$gift_code .
                         ' is not yet valid');
       $this->errors['GiftCertNotYet'] = true;   return false;
    }
    if ($row['end_date'] &&
        ($today > ($this->midnight($row['end_date']) + 86400))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$gift_code .
                         ' has expired');
       $this->errors['ExpiredGiftCert'] = true;   return false;
    }
    $coupon_id = $row['id'];
    $flags = $row['flags'];
    if (! $flags) $flags = 0;
    if ((($flags & COUPON_SELECTED_PRODUCTS) ||
         ($flags & COUPON_EXCLUDE_PRODUCTS)) &&
        (! $this->check_coupon_products($coupon_id,$product_ids,
                                        $product_total))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$coupon_code .
                         ' is not available for products in cart');
       $this->errors['GiftNoProduct'] = true;   return false;
    }
    if (($flags & COUPON_SELECTED_CUSTOMERS) &&
        (! $this->check_coupon_customer($coupon_id))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$coupon_code .
                         ' is not available for this customer');
       $this->errors['GiftNoCustomer'] = true;   return false;
    }
    if ($row['gift_customer'] &&
        ((! isset($this->info['customer_id'])) ||
         ($this->info['customer_id'] != $row['gift_customer']))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$gift_code .
                         ' has already been used');
       $this->errors['UsedGiftCert'] = true;   return false;
    }
    if (intval($row['balance']) <= 0) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Gift Certificate '.$gift_code .
                         ' has already been used');
       $this->errors['UsedGiftCert'] = true;   return false;
    }
    $this->info['gift_id'] = $row['id'];
    $this->info['gift_code'] = $gift_code;
    $this->info['gift_balance'] = $row['balance'];
    if (($flags & COUPON_SELECTED_PRODUCTS) && ($product_total != -1)) {
       if ($row['balance'] > $product_total)
          $this->info['gift_amount'] = $product_total;
       else $this->info['gift_amount'] = $row['balance'];
    }
    else if ($row['balance'] > $this->info['total'])
       $this->info['gift_amount'] = $this->info['total'];
    else $this->info['gift_amount'] = $row['balance'];
    $this->info['total'] -= $this->info['gift_amount'];
    return true;
}

function process_rewards($rewards)
{
    global $log_cart_errors_enabled;

    if (! is_numeric($rewards)) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Invalid Rewards Amount: '.$rewards);
       $this->errors['InvalidRewards'] = true;  return false;
    }
    $rewards = floatval($rewards);
    if ($rewards == 0) return true;
    $available_rewards = floatval($this->customer->info['rewards']);
    if ($available_rewards == 0) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Customer has no rewards for requested ' .
                         'rewards: '.$rewards);
       $this->errors['NoRewards'] = true;  return false;
    }
    if ($rewards > $available_rewards) {
       if ($log_cart_errors_enabled)
          log_cart_error('Error: Customer has insufficient rewards (' .
                         $available_rewards.') for requested rewards: ' .
                         $rewards);
       $this->errors['TooManyRewards'] = true;  return false;
    }
    $this->info['rewards'] = $rewards;
    $this->info['total'] -= $this->info['rewards'];
    return true;
}

function read_hidden_fields()
{
    $this->set('subtotal',get_form_field('subtotal'));
    $this->set('tax',get_form_field('tax'));
    $this->set('tax_zone',get_form_field('tax_zone'));
    $this->set('tax_rate',get_form_field('tax_rate'));
    $this->set('coupon_id',get_form_field('coupon_id'));
    $this->set('coupon_code',get_form_field('coupon_code'));
    $this->set('coupon_type',get_form_field('coupon_type'));
    $this->set('coupon_amount',get_form_field('coupon_amount'));
    if ($this->enable_rewards) $this->set('rewards',get_form_field('rewards'));
    if ($this->features & GIFT_CERTIFICATES) {
       $this->set('gift_id',get_form_field('gift_id'));
       $this->set('gift_code',get_form_field('gift_code'));
       $this->set('gift_amount',get_form_field('gift_amount'));
       $this->set('gift_balance',get_form_field('gift_balance'));
    }
    $fee_name = get_form_field('fee_name');
    if ($fee_name) {
       $this->set('fee_name',$fee_name);
       $this->set('fee_amount',get_form_field('fee_amount'));
    }
    if ((! $this->enable_rewards) || (! $this->info['rewards'])) {
       $discount_name = get_form_field('discount_name');
       if ($discount_name) {
          $this->set('discount_name',$discount_name);
          $this->set('discount_amount',get_form_field('discount_amount'));
       }
    }
    $payment_method = get_form_field('payment_method');
    if ($payment_method) $this->set('payment_method',$payment_method);
    $total = $this->info['subtotal'] + $this->info['tax'] -
             $this->info['coupon_amount'];
    if ($this->features & GIFT_CERTIFICATES)
       $total -= $this->info['gift_amount'];
    if ($fee_name) $total += $this->info['fee_amount'];
    if ($this->enable_rewards && $this->info['rewards'])
       $total -= $this->info['rewards'];
    else if ($discount_name) $total -= $this->info['discount_amount'];
    $this->set('total',$total);
}

function write_hidden_fields($customer)
{
    if ((! isset($customer->id)) || ($customer->id === 0)) {
       print "<input type=\"hidden\" name=\"cust_email\" value=\"". 
             $customer->get('cust_email')."\">\n";
       print "<input type=\"hidden\" name=\"cust_fname\" value=\"". 
             $customer->get('cust_fname')."\">\n";
       print "<input type=\"hidden\" name=\"cust_mname\" value=\"". 
             $customer->get('cust_mname')."\">\n";
       print "<input type=\"hidden\" name=\"cust_lname\" value=\"". 
             $customer->get('cust_lname')."\">\n";
       print "<input type=\"hidden\" name=\"cust_company\" value=\"". 
             $customer->get('cust_company')."\">\n";
       print "<input type=\"hidden\" name=\"cust_mailing\" value=\"". 
             $customer->get('cust_mailing')."\">\n";
       if (function_exists('custom_customer_fields')) {
          $customers_record = array();
          custom_customer_fields($customers_record);
          foreach ($customers_record as $field_name => $field_info)
             print "<input type=\"hidden\" name=\"cust_".$field_name .
                   "\" value=\"".$customer->get('cust_'.$field_name)."\">\n";
       }
       print "<input type=\"hidden\" name=\"bill_address1\" value=\"". 
             $customer->get('bill_address1')."\">\n";
       print "<input type=\"hidden\" name=\"bill_address2\" value=\"". 
             $customer->get('bill_address2')."\">\n";
       print "<input type=\"hidden\" name=\"bill_city\" value=\"". 
             $customer->get('bill_city')."\">\n";
       print "<input type=\"hidden\" name=\"bill_state\" value=\"". 
             $customer->get('bill_state')."\">\n";
       print "<input type=\"hidden\" name=\"bill_zipcode\" value=\"". 
             $customer->get('bill_zipcode')."\">\n";
       print "<input type=\"hidden\" name=\"bill_country\" value=\"". 
             $customer->get('bill_country')."\">\n";
       print "<input type=\"hidden\" name=\"bill_phone\" value=\"". 
             $customer->get('bill_phone')."\">\n";
       if (function_exists('custom_customer_billing_fields')) {
          $billing_record = array();
          custom_customer_billing_fields($billing_record);
          foreach ($billing_record as $field_name => $field_info)
             print "<input type=\"hidden\" name=\"bill_".$field_name .
                   "\" value=\"".$customer->get('bill_'.$field_name)."\">\n";
       }
       print "<input type=\"hidden\" name=\"ship_shipto\" value=\"". 
             $customer->get('ship_shipto')."\">\n";
       print "<input type=\"hidden\" name=\"ship_company\" value=\"". 
             $customer->get('ship_company')."\">\n";
       print "<input type=\"hidden\" name=\"ship_address1\" value=\"". 
             $customer->get('ship_address1')."\">\n";
       print "<input type=\"hidden\" name=\"ship_address2\" value=\"". 
             $customer->get('ship_address2')."\">\n";
       print "<input type=\"hidden\" name=\"ship_city\" value=\"". 
             $customer->get('ship_city')."\">\n";
       print "<input type=\"hidden\" name=\"ship_state\" value=\"". 
             $customer->get('ship_state')."\">\n";
       print "<input type=\"hidden\" name=\"ship_province\" value=\"". 
             $customer->get('ship_province')."\">\n";
       print "<input type=\"hidden\" name=\"ship_canada_province\" value=\"". 
             $customer->get('ship_canada_province')."\">\n";
       print "<input type=\"hidden\" name=\"ship_zipcode\" value=\"". 
             $customer->get('ship_zipcode')."\">\n";
       print "<input type=\"hidden\" name=\"ship_country\" value=\"". 
             $customer->get('ship_country')."\">\n";
       print "<input type=\"hidden\" name=\"ship_address_type\" value=\"". 
             $customer->get('ship_address_type')."\">\n";
       if (function_exists('custom_customer_shipping_fields')) {
          $shipping_record = array();
          custom_customer_shipping_fields($shipping_record);
          foreach ($shipping_record as $field_name => $field_info)
             print "<input type=\"hidden\" name=\"ship_".$field_name .
                   "\" value=\"".$customer->get('ship_'.$field_name)."\">\n";
       }
    }
    if (! empty($customer->prevent_change_shipping)) 
       print "<input type=\"hidden\" name=\"PreventChangeShipping\" value=\"". 
             $customer->prevent_change_shipping."\">\n";

    print "<input type=\"hidden\" name=\"subtotal\" value=\"";
    if (isset($this->info['subtotal'])) print $this->info['subtotal'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"tax\" value=\"";
    if (isset($this->info['tax'])) print $this->info['tax'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"tax_zone\" value=\"";
    if (isset($this->info['tax_zone'])) print $this->info['tax_zone'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"tax_rate\" value=\"";
    if (isset($this->info['tax_rate'])) print $this->info['tax_rate'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_id\" value=\"";
    if (isset($this->info['coupon_id'])) print $this->info['coupon_id'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_code\" value=\"";
    if (isset($this->info['coupon_code'])) print $this->info['coupon_code'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_type\" value=\"";
    if (isset($this->info['coupon_type'])) print $this->info['coupon_type'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_amount\" value=\"";
    if (isset($this->info['coupon_amount'])) print $this->info['coupon_amount'];
    print "\">\n";
    if ($this->enable_rewards) {
       print "<input type=\"hidden\" name=\"rewards\" value=\"";
       if (isset($this->info['rewards'])) print $this->info['rewards'];
       print "\">\n";
    }
    if ($this->features & GIFT_CERTIFICATES) {
       print "<input type=\"hidden\" name=\"gift_id\" value=\"";
       if (isset($this->info['gift_id'])) print $this->info['gift_id'];
       print "\">\n";
       print "<input type=\"hidden\" name=\"gift_code\" value=\"";
       if (isset($this->info['gift_code'])) print $this->info['gift_code'];
       print "\">\n";
       print "<input type=\"hidden\" name=\"gift_balance\" value=\"";
       if (isset($this->info['gift_balance'])) print $this->info['gift_balance'];
       print "\">\n";
    }
    print "<input type=\"hidden\" name=\"fee_name\" value=\"";
    if (isset($this->info['fee_name'])) print $this->info['fee_name'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"fee_amount\" value=\"";
    if (isset($this->info['fee_amount'])) print $this->info['fee_amount'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"discount_name\" value=\"";
    if (isset($this->info['discount_name'])) print $this->info['discount_name'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"discount_amount\" value=\"";
    if (isset($this->info['discount_amount'])) print $this->info['discount_amount'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"payment_method\" value=\"";
    if (isset($this->info['payment_method'])) print $this->info['payment_method'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"currency\" value=\"";
    if (isset($this->currency)) print $this->currency;
    print "\">\n";
    if (! empty($this->item_checksum))
       print "<input type=\"hidden\" name=\"item_checksum\" value=\"" .
             $this->item_checksum."\">\n";
}

function write_shipping_calc_fields()
{
    print "<input type=\"hidden\" name=\"subtotal\" value=\"";
    if (isset($this->info['subtotal'])) print $this->info['subtotal'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"tax\" value=\"";
    if (isset($this->info['tax'])) print $this->info['tax'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_id\" value=\"";
    if (isset($this->info['coupon_id'])) print $this->info['coupon_id'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_code\" value=\"";
    if (isset($this->info['coupon_code'])) print $this->info['coupon_code'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_type\" value=\"";
    if (isset($this->info['coupon_type'])) print $this->info['coupon_type'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"coupon_amount\" value=\"";
    if (isset($this->info['coupon_amount'])) print $this->info['coupon_amount'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"fee_name\" value=\"";
    if (isset($this->info['fee_name'])) print $this->info['fee_name'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"fee_amount\" value=\"";
    if (isset($this->info['fee_amount'])) print $this->info['fee_amount'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"discount_name\" value=\"";
    if (isset($this->info['discount_name'])) print $this->info['discount_name'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"discount_amount\" value=\"";
    if (isset($this->info['discount_amount'])) print $this->info['discount_amount'];
    print "\">\n";
    print "<input type=\"hidden\" name=\"currency\" value=\"";
    if (isset($this->currency)) print $this->currency;
    print "\">\n";
}

function get_handling($shipping_country_info,$customer,$config_name)
{
    if ($customer->shipping_country == 1) {
       $state = $customer->get('ship_state');
       $state_info = get_state_info($state,$this->db);
       if (isset($state_info['handling']) && ($state_info['handling'] != ''))
          return $state_info['handling'];
    }
    if (isset($shipping_country_info['handling']) &&
        ($shipping_country_info['handling'] != ''))
       return $shipping_country_info['handling'];
    if ($config_name) return get_cart_config_value($config_name,$this->db);
    return 0;
}

function get_shipping_info()
{
    global $custom_attributes_only;

    if (! isset($this->items)) return array();
    if (isset($this->internal_cart)) $cart_items = $this->items;
    else {
       $ids = array();
       foreach ($this->items as $id => $cart_item) $ids[] = $id;
       if (count($ids) == 0) return array();
       $query = 'select c.id,c.product_id,c.qty,c.attributes,c.flags,p.status ' .
                'from '.$this->item_table.' c left join products p on ' .
                'p.id=c.product_id where c.id in (?)';
       $query = $this->db->prepare_query($query,$ids);
       $cart_items = $this->db->get_records($query,'id');
       if ((! $cart_items) || (count($cart_items) == 0)) {
          if (isset($this->db->error)) $this->error = $this->db->error;
          return array();
       }
    }
    $shipping_info = array();
    foreach ($cart_items as $item_id => $row) {
       if ($row['product_id']) {
          $inv_query = 'select weight';
          if ($this->features & DROP_SHIPPING) $inv_query .= ',origin_zip';
          $inv_query .= ' from product_inventory where parent=?';
          if ((! isset($row['attributes'])) || ($row['attributes'] == ''))
             $lookup_attributes = null;
          else if (isset($custom_attributes_only) && $custom_attributes_only)
             $lookup_attributes = null;
          else {
             $no_options = check_no_option_attributes($this->db,$row['product_id']);
             if ($no_options) $attributes = explode('|',$row['attributes']);
             else $attributes = explode('-',$row['attributes']);
             $lookup_attributes = build_lookup_attributes($this->db,
                $row['product_id'],$attributes,true,$no_options);
          }
          if ($lookup_attributes) {
             $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
                $row['product_id'],$no_options,$this->db);
             $inv_query .= ' and attributes=?';
             $inv_query = $this->db->prepare_query($inv_query,
                             $row['product_id'],$lookup_attributes);
          }
          else {
             $inv_query .= ' and ((attributes="") or isnull(attributes))';
             $inv_query = $this->db->prepare_query($inv_query,
                                                   $row['product_id']);
          }
          $inv_row = $this->db->get_record($inv_query);
          if (! $inv_row) {
             $inv_row = array('weight'=>0);
             if ($this->features & DROP_SHIPPING) $inv_row['origin_zip'] = '';
          }
          $row = array_merge($row,$inv_row);
       }
       $shipping_info[$item_id] = $row;
    }
    return $shipping_info;
}

function get_origin_info($default_origin,$default_weight,$customer)
{
    global $free_shipping_option;

    if (! isset($free_shipping_option)) $free_shipping_option = 3;
    $shipping_info = $this->get_shipping_info();
    if (! $shipping_info) return array();

    foreach ($shipping_info as $item_id => $item_info) {
       if (($this->free_shipping !== true) && isset($item_info['status'])) {
          if ((is_array($free_shipping_option) &&
               in_array($item_info['status'],$free_shipping_option)) ||
              ($item_info['status'] == $free_shipping_option)) {
             if (! is_array($this->free_shipping))
                $this->free_shipping = array();
             if (! in_array($item_id,$this->free_shipping))
                $this->free_shipping[] = $item_id;
          }
       }
       if (($this->free_shipping !== true) &&
            (! empty($this->free_shipping_items)) &&
            in_array($item_id,$this->free_shipping_items)) {
          if (! is_array($this->free_shipping))
             $this->free_shipping = array();
          if (! in_array($item_id,$this->free_shipping))
             $this->free_shipping[] = $item_id;
       }
    }
    if (is_array($this->free_shipping) &&
        (count($this->free_shipping) == count($shipping_info)))
       $this->free_shipping = true;
    if (function_exists('update_origin_free_shipping'))
       update_origin_free_shipping($this,$shipping_info,$customer);

    $origin_info = array();
    foreach ($shipping_info as $item_id => $item_info) {
       if (($this->features & DROP_SHIPPING) && isset($item_info['origin_zip']))
          $origin_zip = $item_info['origin_zip'];
       else $origin_zip = null;
       if ((! $origin_zip) || ($origin_zip == '')) $origin_zip = $default_origin;
       if (function_exists('calculate_item_weight'))
          $weight = calculate_item_weight($item_info);
       else {
          if (isset($item_info['weight'])) $weight = $item_info['weight'];
          else $weight = null;
          if ((! $weight) || ($weight == '')) $weight = $default_weight;
          if (! ($item_info['flags'] & QTY_PRICE))
             $weight *= $item_info['qty'];
       }
       if (is_array($this->free_shipping) &&
           in_array($item_id,$this->free_shipping)) $weight = 0;
       if (isset($origin_info[$origin_zip]))
          $origin_info[$origin_zip] += $weight;
       else $origin_info[$origin_zip] = $weight;
    }
    foreach ($origin_info as $origin_zip => $weight) {
       if (! $weight) $origin_info[$origin_zip] = 0.1;
    }
    return $origin_info;
}

function only_manual_shipping()
{
    global $shipping_modules;

    if (! isset($shipping_modules)) load_shipping_modules();
    if (empty($shipping_modules)) return false;
    if (count($shipping_modules) > 1) return false;
    if ($shipping_modules[0] == 'manual') return true;
    return false;
}

function sort_shipping_options($a,$b)
{
    if ($a[2] < $b[2]) return -1;
    return 1;
}

function unset_default_shipping()
{
    if (empty($this->shipping_default_set)) return;
    foreach ($this->shipping_options as $index => $shipping_option) {
       if ($shipping_option[4]) $this->shipping_options[$index][4] = false;
    }
    unset($this->shipping_default_set);
}

function load_shipping_options($customer)
{
    global $shipping_modules,$multiple_customer_accounts,$account_cookie;

    $this->shipping_options = array();
    if ((! isset($this->info)) || (! isset($this->info['total']))) return;

    if (function_exists('init_shipping_options'))
       init_shipping_options($this,$customer);
    if ($multiple_customer_accounts) $account_id = get_cookie($account_cookie);
    else $account_id = $customer->get('cust_account_id');
    if ($account_id) {
       $query = 'select no_shipping_flag from accounts where id=?';
       $query = $customer->db->prepare_query($query,$account_id);
       $row = $customer->db->get_record($query);
       if (! empty($row['no_shipping_flag'])) return;
    }

    call_shipping_event('load_shipping_options',array(&$this,$customer));
    if (count($shipping_modules) == 0) {
       $shipping_country_info = get_country_info($customer->shipping_country,
                                                 $this->db);
       $handling = $this->get_handling($shipping_country_info,$customer,null);
       if ($handling)
          $this->add_shipping_option('manual',0,$handling,'',true);
    }
    if (($this->shipping_columns == 5) && (! isset($this->shipping_suffix)) &&
        (count($this->shipping_options) == 1)) $this->shipping_columns = 4;
    if (count($this->shipping_options) > 1)
       usort($this->shipping_options,array($this,'sort_shipping_options'));
    if (($this->free_shipping === true) && isset($this->shipping_options[0])) {
       $this->unset_default_shipping();
       $this->shipping_options[0][2] = 0;
       $this->shipping_options[0][4] = true;
       $this->shipping_default_set = true;
       if (isset($this->shipping_options[0][3]) &&
           (strpos($this->shipping_options[0][3],'Free') === false))
          $this->shipping_options[0][3] .= ' (Free)';
    }
    if (function_exists('update_shipping_options'))
       update_shipping_options($this,$customer);
}

function add_shipping_option($module_id,$option_id,$rate,$label,$default_rate)
{
    if ($default_rate) {
       if (isset($this->shipping_default_set)) $default_rate = false;
       else $this->shipping_default_set = true;
    }
    $this->shipping_options[] = array($module_id,$option_id,$rate,$label,
                                      $default_rate);
}

function set_shipping_amount()
{
    if ((! isset($this->shipping_options)) ||
        (count($this->shipping_options) == 0)) return;
    $default_rate = 0;
    if (count($this->shipping_options) == 1) {
       $shipping_option = reset($this->shipping_options);
       $default_rate = $shipping_option[2];
    }
    else {
       $selected_shipping_method = get_form_field('shipping_method');
       if ((! $selected_shipping_method) &&
            isset($this->info['shipping_method']))
          $selected_shipping_method = $this->info['shipping_method'];
       if ($selected_shipping_method)
          $selected_shipping_method = explode('|',$selected_shipping_method);
       $first_option = true;
       foreach ($this->shipping_options as $shipping_option) {
          if ($first_option) {
             $first_option = false;   $default_rate = $shipping_option[2];
          }
          $selected = false;
          if ($selected_shipping_method) {
             if (($selected_shipping_method[0] == $shipping_option[0]) &&
                 ($selected_shipping_method[1] == $shipping_option[1]))
                $selected = true;
          }
          else if ($shipping_option[4]) $selected = true;
          if ($selected) $default_rate = $shipping_option[2];
       }
    }
    $this->info['shipping'] = $default_rate;
}

function display_shipping_options($customer)
{
    global $show_part_number_in_cart,$guest_checkout,$mobile_cart;
    global $hide_cart_prices;

    if ((! isset($this->info)) || (! isset($this->info['total']))) return;
    if ((! isset($this->shipping_options)) ||
        (count($this->shipping_options) == 0)) return;
    $selected_shipping_method = get_form_field('shipping_method');
    if ((! $selected_shipping_method) && isset($this->info['shipping_method']))
       $selected_shipping_method = $this->info['shipping_method'];
    if ($selected_shipping_method)
       $selected_shipping_method = explode('|',$selected_shipping_method);
    if (isset($show_part_number_in_cart)) $compare_columns = 5;
    else $compare_columns = 4;
    $default_rate = 0;
    if (count($this->shipping_options) == 1) {
       $shipping_option = reset($this->shipping_options);
       print "\n        <input type=\"hidden\" name=\"shipping_method\" value=\"" .
             $shipping_option[0].'|'.$shipping_option[1].'|'.$shipping_option[2] .
             '|'.$shipping_option[3]."\">";
       if (isset($this->shipping_suffix)) print $this->shipping_suffix;
       else {
          if ($shipping_option[3]) {
             print $shipping_option[3];
             if ($shipping_option[1] != -1) print ' Shipping';
          }
          else print T('SHIPPING METHOD');
          if ((! $mobile_cart) && ($this->shipping_columns == $compare_columns) &&
              empty($hide_cart_prices))
             print "</td>\n        <td align=\"right\">";
          else print ' ';
       }
       if (empty($hide_cart_prices)) $this->write_amount($shipping_option[2]);
       $default_rate = $shipping_option[2];
    }
    else {
       $lowest_rate = -1;   $lowest_option = -1;
       foreach ($this->shipping_options as $index => $shipping_option) {
          if ($shipping_option[4]) {   $lowest_option = -1;   break;   }
          if (($lowest_rate == -1) || ($shipping_option[2] < $lowest_rate)) {
             $lowest_rate = $shipping_option[2];   $lowest_option = $index;
          }
       }
       if ($lowest_option != -1)
          $this->shipping_options[$lowest_option][4] = true;
       print "\n        <select name=\"shipping_method\" class=\"cart_field\" " .
             "onChange=\"select_shipping(this);\">\n";
       $first_option = true;
       foreach ($this->shipping_options as $shipping_option) {
          if ($first_option) {
             $first_option = false;   $default_rate = $shipping_option[2];
          }
          $option_value = $shipping_option[0].'|'.$shipping_option[1].'|' .
                          $shipping_option[2].'|'.$shipping_option[3];
          $selected = false;
          if ($selected_shipping_method) {
             if (($selected_shipping_method[0] == $shipping_option[0]) &&
                 ($selected_shipping_method[1] == $shipping_option[1]))
                $selected = true;
          }
          else if ($shipping_option[4]) $selected = true;
          print "            <option value=\"".$shipping_option[0].'|' .
                $shipping_option[1].'|'.$shipping_option[2].'|' .
                $shipping_option[3]."\"";
          if ($selected) {
             print ' selected';   $default_rate = $shipping_option[2];
          }
          print '>';
          if (empty($hide_cart_prices)) {
             if ($shipping_option[3] != '') print $shipping_option[3].' : ';
             $this->write_amount($shipping_option[2]);
          }
          print "</option>\n";
       }
       print '        </select>';
    }
    $this->info['shipping'] = $default_rate;
    if ($this->features & GIFT_CERTIFICATES) {
       if (isset($this->info['gift_id']) && ($this->info['gift_id'] != '')) {
          if (isset($this->info['tax'])) $tax = $this->info['tax'];
          else $tax = 0;
          if (isset($this->info['coupon_amount']))
             $coupon_amount = $this->info['coupon_amount'];
          else $coupon_amount = 0;
          $total = $this->info['subtotal'] + $tax - $coupon_amount;
          $remaining_gift = $this->info['gift_balance'] - $total;
          if ($remaining_gift < 0) $gift_amount = $this->info['gift_balance'];
          else if ($remaining_gift > $default_rate)
             $gift_amount = $total + $default_rate;
          else $gift_amount = $this->info['gift_balance'];
          $this->info['gift_amount'] = $gift_amount;
          $this->info['total'] = $total - $gift_amount;
       }
    }
    $this->info['total'] += $default_rate;
}

function update_free_shipping_label(&$shipping_method)
{
    if (! empty($this->info['shipping'])) return;
    if (empty($shipping_method)) return;
    if (strpos($shipping_method,'Free') !== false) return;
    $shipping_method .= ' (Free)';
}

function log_shipping($msg)
{
    global $shipping_log,$login_cookie;

    $shipping_file = fopen($shipping_log,'at');
    if ($shipping_file) {
       $remote_user = getenv('REMOTE_USER');
       if (! $remote_user) $remote_user = get_cookie($login_cookie);
       if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($shipping_file,$remote_user.' ['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($shipping_file);
    }
}

function get_item_checksum()
{
    $items = array();
    foreach ($this->items as $item)
       $items[] = array(
          'parent'=>$item['parent'],
          'product_id'=>$item['product_id'],
          'attributes'=>$item['attributes'],
          'part_number'=>$item['part_number'],
          'qty'=>$item['qty'],
          'price'=>$item['price']);
    return md5(json_encode($items));
}

function init_checkout()
{
    if (empty($this->items)) $this->item_checksum = 0;
    else $this->item_checksum = $this->get_item_checksum();
}

function write_cart_items($new_cart_items)
{
    write_javascript_header();
    $json = get_form_field('json');
    if (! $json) {
       $namespace = get_form_field('namespace');
       if ($namespace) print $namespace.'.';
       else print 'var cart_';
    }
    if ((! isset($this->items)) || (! $this->items) ||
        (count($new_cart_items) == 0)) {
       print 'error="';
       if (isset($this->error) && $this->error) print $this->error;
       else print 'No cart items';
       print '";';
       DB::close_all();   return;
    }
    if ($json) $q = '"';
    else {
       print 'data=';   $q = '';
    }
    print '{';
    $first_item = true;
    if ($this->items) foreach ($this->items as $id => $cart_item) {
       if (! in_array($id,$new_cart_items)) continue;
       if ($first_item) $first_item = false;
       else print ',';
       print $q.$id.$q.':{';
       print $q.'id'.$q.':'.$id.',';
       print $q.'product_id'.$q.':'.$cart_item['product_id'].',';
       $product_name = get_html_product_name($cart_item['product_name'],
          GET_PROD_CART,$this,$cart_item);
       $product_name = str_replace('"',"\\\"",$product_name);
       $product_name = preg_replace('/[\x00-\x1F\x80-\xFF]/','',$product_name);
       print $q.'product_name'.$q.':"'.$product_name.'",';
       print $q.'image'.$q.':"'.(isset($cart_item['image_filename'])?
                                 $cart_item['image_filename']:'').'",';
       print $q.'url'.$q.':"'.(isset($cart_item['url'])?
                               $cart_item['url']:'').'",';
       $attributes = get_html_attributes($cart_item['attribute_array'],
          GET_ATTR_CART,$this,$cart_item);
       $attributes = str_replace('"',"\\\"",$attributes);
       $attributes = preg_replace('/[\x00-\x1F\x80-\xFF]/','',$attributes);
       print $q.'attributes'.$q.':"'.$attributes.'",';
       print $q.'related_id'.$q.':';
       if ($cart_item['related_id'] !== null)
          print $cart_item['related_id'];
       else print "\"\"";
       print ',';
       if (! empty($cart_item['reorder_frequency']))
          print $q.'reorder_frequency'.$q.':' .
                $cart_item['reorder_frequency'].',';
       $item_total = $cart_item['price'];
       if (isset($cart_item['attribute_array'])) {
          $attribute_array = $cart_item['attribute_array'];
          foreach ($attribute_array as $attribute)
             $item_total += $attribute['price'];
       }
       print $q.'price'.$q.':'.$item_total.',';
       print $q.'qty'.$q.':'.$cart_item['qty'].',';
       print $q.'total'.$q.':'.get_item_total($cart_item).',';
       print $q.'flags'.$q.':';
       if ($cart_item['flags'] !== null) print $cart_item['flags'];
       else print '0';
       print '}';
    }
    print '}';
    DB::close_all();
}

function save_cart_data()
{
    if (isset($_SERVER['REQUEST_METHOD']))
       $method = $_SERVER['REQUEST_METHOD'];
    else $method = 'GET';
    if ($method == 'POST') $cart_data = serialize($_POST);
    else $cart_data = serialize($_GET);
    $query = 'update cart set cart_data=? where id=?';
    $query = $this->db->prepare_query($query,$cart_data,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) return false;
    log_activity('Saved Cart Data for Cart ID #'.$this->id);
    return true;
}

function load_cart_data()
{
    if ((! isset($this->id)) || (! is_numeric($this->id))) {
       if (! isset($this->id))
          log_error('Invalid Cart ID (null) in load_cart_data');
       else log_error('Invalid Cart ID #'.$this->id.' in load_cart_data');
       log_request_data();   $this->error = 'Invalid Cart ID';   return false;
    }
    $query = 'select cart_data from cart where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (! $row) {
       log_error('Cart Not Found for Cart ID #'.$this->id .
                 ' in load_cart_data');
       $this->error = 'Invalid Cart';   return false;
    }
    if (! $row['cart_data']) return true;
    if (isset($_SERVER['REQUEST_METHOD']))
       $method = $_SERVER['REQUEST_METHOD'];
    else $method = 'GET';
    if ($method == 'POST')
       $_POST = array_merge(unserialize($row['cart_data']),$_POST);
    else $_GET = array_merge(unserialize($row['cart_data']),$_GET);
    return true;
}

};

class WishList extends Cart {

function __construct($db = null,$wishlist_id = null,$customer_id = null,
                     $object_only = false,$no_cart_cookie = false)
{
    parent::__construct($db,$wishlist_id,$customer_id,$object_only,
                        $no_cart_cookie);
}

function WishList($db = null,$wishlist_id = null,$customer_id = null,
                  $object_only = false,$no_cart_cookie = false)
{
    self::__construct($db,$wishlist_id,$customer_id,$object_only,
                      $no_cart_cookie);
}

};

class Customer {

function __construct($db = null,$customer_id = null,$object_only = false)
{
    global $user_cookie,$cart_cookie,$wishlist_cookie,$system_disabled;
    global $single_cart_per_customer,$single_wishlist_per_customer;
    global $enable_multisite;

    if (! isset($system_disabled)) $system_disabled = false;
    if (isset($customer_id)) $this->id = $customer_id;
    else if (! $object_only) {
       $this->id = get_cookie($user_cookie);
       if (! is_numeric($this->id)) $this->id = null;
       $customer_id = $this->id;
    }
    else {
       $customer_id = null;   $this->id = null;
    }
    $this->object_only = $object_only;
    if (! $object_only) {
       $this->cart_id = get_cookie($cart_cookie);
       if (! is_numeric($this->cart_id)) $this->cart_id = null;
       $cart_id = $this->cart_id;
    }
    else $cart_id = null;
    if ((! $object_only) && isset($wishlist_cookie)) {
       $this->wishlist_id = get_cookie($wishlist_cookie);
       if (! is_numeric($this->wishlist_id)) $this->wishlist_id = null;
    }
    $this->errors = array();
    $this->status = NO_ERROR;
    if ($db) $this->db = $db;
    else $this->db = new DB;
    if ((! $this->db) || isset($this->db->error)) {
       $this->status = DB_ERROR;
       if ($this->db) $this->error = $this->db->error;
       return;
    }
    if ($enable_multisite) {
       $website_settings = get_website_settings($this->db);
       if ($website_settings & WEBSITE_SHARED_CART) $this->shared_cart = true;
       else $this->shared_cart = false;
    }
    else $this->shared_cart = false;
    $this->multisite_cookies = null;
    $this->system_disabled = $system_disabled;
    if (! isset($single_cart_per_customer)) $this->single_cart = false;
    else $this->single_cart = $single_cart_per_customer;
    if (! isset($single_wishlist_per_customer)) $this->single_wishlist = false;
    else $this->single_wishlist = $single_wishlist_per_customer;
    if (! $object_only) set_cart_remote_user($cart_id,$customer_id,$this->db);
    if ($this->single_cart && isset($this->id) && (! $object_only))
       $this->set_cart_cookie(false);
    if ($this->single_wishlist && isset($this->id) && (! $object_only))
       $this->set_wishlist_cookie(false);
    $this->billing_country = get_default_country(true,$this->db);
    $this->shipping_country = get_default_country(false,$this->db);
    if (($this->billing_country == 0) || ($this->shipping_country == 0)) {
       $country_info = get_country_info(1,$this->db);
       $available = $country_info['available'];
       if (($this->billing_country == 0) && ($available & 1))
          $this->billing_country = 1;
       if (($this->shipping_country == 0) && ($available & 2))
          $this->shipping_country = 1;
    }
}

function Customer($db = null,$customer_id = null,$object_only = false)
{
    self::__construct($db,$customer_id,$object_only);
}

function row($prefix,$visible)
{
    if (! isset($this->row_numbers)) $this->row_numbers = array();
    if (! isset($this->row_numbers[$prefix])) $this->row_numbers[$prefix] = 1;
    else $this->row_numbers[$prefix]++;
    $row_info = ' id="'.$prefix.'_row_'.$this->row_numbers[$prefix].'"';
    if (! $visible) $row_info .= ' style="display:none;"';
    return $row_info;
}

function get_id()
{
    global $user_cookie;

    $customer_id = get_cookie($user_cookie);
    if (! is_numeric($customer_id)) $customer_id = null;
    return $customer_id;
}

function set_cookie($cookie_name,$cookie_value,$num_days=100)
{
    global $user_cookie_expiration;

    if (! $cookie_value) {
       if (headers_sent($file,$line)) {
          log_error('Unable to delete cookie '.$cookie_name .
                    ' since headers were already sent by ' .
                    $file.' line '.$line);
          log_request_data();
       }
       else setcookie($cookie_name,$cookie_value,time() - 3600,'/');
       $user_cookie_expiration = null;
    }
    else {
       if (! empty($user_cookie_expiration))
          $expires = $user_cookie_expiration;
       else $expires = (86400 * $num_days);
       if (headers_sent($file,$line)) {
          log_error('Unable to set cookie '.$cookie_name.' to '.$cookie_value .
                    ' since headers were already sent by ' .
                    $file.' line '.$line);
          log_request_data();
       }
       else {
          if ($expires) $expire_time = time() + $expires;
          else $expire_time = 0;
          setcookie($cookie_name,$cookie_value,$expire_time,'/');
       }
    }
    if (empty($this->shared_cart)) return;
    if ($this->multisite_cookies) $this->multisite_cookies .= '&';
    if ($cookie_value) $cookie_value .= '|'.$expires;
    $this->multisite_cookies .= urlencode($cookie_name).'=' .
                                urlencode($cookie_value);
}

function get_email($db = null)
{
    if (isset($this)) $customer_id = $this->get_id();
    else $customer_id = Customer::get_id();
    if (! $customer_id) return null;
    if (! $db) $db = new DB;
    if ((! $db) || isset($db->error)) return null;
    $query = 'select email from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $row = $db->get_record($query);
    if (! $row) return null;
    $db->decrypt_record('customers',$row);
    return $row['email'];
}

function update_cart()
{
    $query = 'update cart set customer_id=? where id=?';
    $query = $this->db->prepare_query($query,$this->id,$this->cart_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function update_wishlist()
{
    $query = 'update wishlist set customer_id=? where id=?';
    $query = $this->db->prepare_query($query,$this->id,$this->wishlist_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function set_cart_cookie($validating)
{
    global $cart_cookie;

    if (function_exists('custom_cart_cookie_query'))
       $query = custom_cart_cookie_query($this,$this->id);
    else {
       $query = 'select id from cart where customer_id=?';
       $query = $this->db->prepare_query($query,$this->id);
    }
    $row = $this->db->get_record($query);
    if ($row) {
       $this->cart_id = $row['id'];
       $this->set_cookie($cart_cookie,$this->cart_id);
       if ($validating) {
          $activity = 'Loaded Cart #'.$this->cart_id;
          log_activity($activity.' for Customer #'.$this->id);
          write_customer_activity($activity,$this->id,$this->db);
       }
    }
    else {
       $this->cart_id = -1;
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
       }
    }
}

function set_wishlist_cookie($validating)
{
    global $wishlist_cookie;

    if (function_exists('custom_wishlist_cookie_query'))
       $query = custom_wishlist_cookie_query($this,$this->id);
    else {
       $query = 'select id from wishlist where customer_id=?';
       $query = $this->db->prepare_query($query,$this->id);
    }
    $row = $this->db->get_record($query);
    if ($row) {
       $this->wishlist_id = $row['id'];
       $this->set_cookie($wishlist_cookie,$this->wishlist_id);
       if ($validating) {
          $activity = 'Loaded WishList #'.$this->wishlist_id;
          log_activity($activity.' for Customer #'.$this->id);
          write_customer_activity($activity,$this->id,$this->db);
       }
    }
    else {
       $this->wishlist_id = -1;
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
       }
    }
}

function parse($init_arrays = true)
{
    $this->info_changed = false;
    $this->billing_changed = false;
    $this->shipping_changed = false;
    if ($init_arrays) {
       $this->customers_record = customers_record_definition();
       $this->info = array();
       $this->billing_record = billing_record_definition();
       $this->billing = array();
       $this->shipping_record = shipping_record_definition();
       $this->shipping = array();
    }

    $form_fields = get_form_fields();
    if (isset($form_fields)) {
       $billing_country = get_form_field('bill_country');
       $shipping_country = get_form_field('ship_country');
       foreach ($form_fields as $field_name => $field_value) {
          $field_value = trim($field_value);
          if (! strncmp($field_name,'cust_',5)) {
             $field_name = substr($field_name,5);
             if ($field_name == 'new_password') {
                if (get_form_field('create_new_account') == 'on')
                   $field_name = 'password';
                else continue;
             }
             if (! isset($this->customers_record[$field_name])) continue;
             if (isset($this->customers_record[$field_name]['fieldtype']) &&
                 ($this->customers_record[$field_name]['fieldtype'] ==
                  CHECKBOX_FIELD)) {
                if ($field_value = 'on') $field_value = 1;
                else $field_value = 0;
             }
             $this->info[$field_name] = $field_value;
             $this->customers_record[$field_name]['value'] = $field_value;
             $this->info_changed = true;
          }
          else if (! strncmp($field_name,'bill_',5)) {
             $field_name = substr($field_name,5);
             if ($field_name == 'province') {
                if ($billing_country == 43) continue;
                else if ($billing_country != 1) $field_name = 'state';
                else continue;
             }
             else if ($field_name == 'canada_province') {
                if ($billing_country == 43) $field_name = 'state';
                else continue;
             }
             $this->billing[$field_name] = $field_value;
             $this->billing_record[$field_name]['value'] = $field_value;
             $this->billing_changed = true;
             if ($field_name == 'country') $this->billing_country = $field_value;
          }
          else if (! strncmp($field_name,'ship_',5)) {
             $field_name = substr($field_name,5);
             if ($field_name == 'province') {
                if ($shipping_country == 43) continue;
                else if ($shipping_country != 1) $field_name = 'state';
                else continue;
             }
             else if ($field_name == 'canada_province') {
                if ($shipping_country == 43) $field_name = 'state';
                else continue;
             }
             $this->shipping[$field_name] = $field_value;
             $this->shipping_record[$field_name]['value'] = $field_value;
             $this->shipping_changed = true;
             if ($field_name == 'country') $this->shipping_country = $field_value;
          }
       }
    }
}

function setup_guest()
{
    $this->id = 0;
    $this->info['id'] = 0;
    $this->info['account_id'] = null;
    $this->billing['fax'] = '';
}

function load_saved_guest_info()
{
    global $cart_cookie;

    $cart_id = get_cookie($cart_cookie);
    if ((! $cart_id) || (! is_numeric($cart_id))) return;
    $query = 'select * from cart where id=?';
    $query = $this->db->prepare_query($query,$cart_id);
    $row = $this->db->get_record($query);
    if ((! $row) || (! $row['cart_data'])) return;
    if (isset($_SERVER['REQUEST_METHOD']))
       $method = $_SERVER['REQUEST_METHOD'];
    else $method = 'GET';
    if ($method == 'POST')
       $_POST = @array_merge($_POST,@unserialize($row['cart_data']));
    else $_GET = @array_merge($_GET,@unserialize($row['cart_data']));
    $this->parse();
}

function validate_email()
{
    if ((! isset($this->info)) || (! isset($this->info['email'])) ||
        ($this->info['email'] == '')) {
       $this->errors['InvalidEmail'] = true;   return;
    }
    $email = $this->info['email'];
    if (! validate_customer_email($email)) {
       $this->errors['InvalidEmail'] = true;   return;
    }
}

function lookup_zip_state($zipcode,$billing_flag)
{
    global $log_cart_errors_enabled;

    $zip_length = strlen($zipcode);
    if ($zip_length != 5) {
       if (($zip_length != 10) || (substr($zipcode,5,1) != '-')) {
          if ($billing_flag) $this->errors['InvalidBillingZipCodeFormat'] = true;
          else $this->errors['InvalidShippingZipCodeFormat'] = true;
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Invalid Zip Code Format: '.$zipcode .
                            ' ('.$zip_length.')');
          return null;
       }
       $zipcode = substr($zipcode,0,5);
    }
    $url = 'http://www.ziptasticapi.com/'.$zipcode;
    $result = @file_get_contents($url);
    if (! $result) {
       $status_code = null;
       if (isset($http_response_header)) {
          foreach ($http_response_header as $header) {
             if (substr($header,0,5) == 'HTTP/') {
                $status_code = substr($header,9);   break;
             }
          }
       }
       if ($status_code) log_error('Ziptastic Error: '.$status_code);
       else log_error('No Response from '.$url);
       return null;
    }
    $data = json_decode($result,true);
    if (isset($data['error'])) {
       if ($data['error'] == 'Zip Code not found!') {
          if ($billing_flag) $this->errors['InvalidBillingZipCode'] = true;
          else $this->errors['InvalidShippingZipCode'] = true;
       }
       log_error('Error from '.$url.': '.$data['error']);   return null;
    }
    if (! isset($data['state'])) {
       log_error('Missing State from '.$url);   return null;
    }
    return($data['state']);
}

function validate_address()
{
    global $log_cart_errors_enabled;

    if (file_exists('../admin/modules/smartystreets/verify.php')) {
       require_once '../admin/modules/smartystreets/verify.php';
       if (! verify_smartystreets_zipcode($this)) return;
       verify_smartystreets_address($this);   return;
    }

    if (isset($this->billing_country) && ($this->billing_country == 1) &&
        (! empty($this->billing['zipcode'])) &&
        (! empty($this->billing['state']))) {
       $state = $this->billing['state'];
       $zip_state = $this->lookup_zip_state($this->billing['zipcode'],true);
       if ($zip_state && ($state != $zip_state)) {
          $this->errors['InvalidBillingZipCode'] = true;
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Billing Zip Code = ' .
                $this->billing['zipcode'].', Billing State = '.$state .
                ', Zip Code State = '.$zip_state);
       }
    }
    if (isset($this->shipping_country) && ($this->shipping_country == 1) &&
        (! empty($this->shipping['zipcode'])) &&
        (! empty($this->shipping['state']))) {
       $state = $this->shipping['state'];
       $zip_state = $this->lookup_zip_state($this->shipping['zipcode'],false);
       if ($zip_state && ($state != $zip_state)) {
          $this->errors['InvalidShippingZipCode'] = true;
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Shipping Zip Code = ' .
                $this->shipping['zipcode'].', Shipping State = '.$state .
                ', Zip Code State = '.$zip_state);
       }
    }
}

function build_address_array($prefix)
{
    $address = array();
    if ($prefix == 'bill') $obj = $this->billing;
    else $obj = $this->shipping;
    if (empty($obj['address1'])) $address['address1'] = '';
    else $address['address1'] = $obj['address1'];
    if (empty($obj['address2'])) $address['address2'] = '';
    else $address['address2'] = $obj['address2'];
    if (empty($obj['city'])) $address['city'] = '';
    else $address['city'] = $obj['city'];
    if (empty($obj['state'])) $address['state'] = '';
    else $address['state'] = $obj['state'];
    if (empty($obj['zipcode'])) $address['zipcode'] = '';
    else $address['zipcode'] = $obj['zipcode'];
    return $address;
}

function process_address_errors()
{
   if (! empty($this->errors['BillAddress']))
      print "    add_onload(function() { show_invalid_address('bill','" .
            json_encode($this->build_address_array('bill'))."'); });\n";
   if (! empty($this->errors['ShipAddress']))
      print "    add_onload(function() { show_invalid_address('ship','" .
            json_encode($this->build_address_array('ship'))."'); });\n";
   if (! empty($this->match_bill_address))
      print "    add_onload(function() { show_address_option('bill','" .
            json_encode($this->match_bill_address)."'); });\n";
   if (! empty($this->match_ship_address))
      print "    add_onload(function() { show_address_option('ship','" .
            json_encode($this->match_ship_address)."'); });\n";
}

function check_required_fields($field_list)
{
    foreach ($field_list as $field_name) {
       if (! strncmp($field_name,'cust_',5)) {
          $short_field_name = substr($field_name,5);
          if ((! isset($this->info)) ||
              (! isset($this->info[$short_field_name])) ||
              ($this->info[$short_field_name] == ''))
             $this->errors[$field_name] = true;
       }
       else if (! strncmp($field_name,'bill_',5)) {
          $short_field_name = substr($field_name,5);
          if ($short_field_name == 'state') {
             if ($this->billing_country != 1) continue;
          }
          else if ($short_field_name == 'province') {
             if (($this->billing_country == 1) || ($this->billing_country == 29) ||
                 ($this->billing_country == 43))
                continue;
             else $short_field_name = 'state';
          }
          else if ($short_field_name == 'canada_province') {
             if ($this->billing_country != 43) continue;
             else $short_field_name = 'state';
          }
          else if ($short_field_name == 'zipcode') {
             if ($this->shipping_country != 1) continue;
          }
          if ((! isset($this->billing)) ||
              (! isset($this->billing[$short_field_name])) ||
              ($this->billing[$short_field_name] == ''))
             $this->errors[$field_name] = true;
       }
       else if (! strncmp($field_name,'ship_',5)) {
          $short_field_name = substr($field_name,5);
          if ($short_field_name == 'state') {
             if ($this->shipping_country != 1) continue;
          }
          else if ($short_field_name == 'province') {
             if (($this->shipping_country == 1) || ($this->shipping_country == 29) ||
                 ($this->shipping_country == 43))
                continue;
             else $short_field_name = 'state';
          }
          else if ($short_field_name == 'canada_province') {
             if ($this->shipping_country != 43) continue;
             else $short_field_name = 'state';
          }
          else if ($short_field_name == 'zipcode') {
             if ($this->shipping_country != 1) continue;
          }
          if ((! isset($this->shipping)) ||
              (! isset($this->shipping[$short_field_name])) ||
              ($this->shipping[$short_field_name] == ''))
             $this->errors[$field_name] = true;
       }
    }
}

function process_errors($field_list,$template,$prefix=null,$visible=null)
{
    foreach ($field_list as $field_name => $error_msg) {
       if (isset($this->errors[$field_name])) {
          $html = str_replace('[msg]',$error_msg,$template);
          if (isset($this->error)) $html = str_replace('[error]',$this->error,$html);
          if ($prefix)
             $html = str_replace('[row]',$this->row($prefix,$visible),$html);
          print $html;
       }
    }
}

function check_existing()
{
    global $log_cart_errors_enabled,$guest_customer_status;

    $email = $this->customers_record['email']['value'];
    if ($email) {
       if (! $this->object_only) set_remote_user($email);
       $query = 'select id,status from customers where email=';
       if ($this->db->check_encrypted_field('customers','email'))
          $query .= '%ENCRYPT%(?)';
       else $query .= '?';
       $query = $this->db->prepare_query($query,$email);
       $row = $this->db->get_record($query);
       if (! empty($row['id'])) {
          if ((! empty($guest_customer_status)) &&
              ($row['status'] == $guest_customer_status)) {
             $this->guest_id = $row['id'];   return true;
          }
          $this->status = ALREADY_REGISTERED;
          if (isset($this->id) && isset($this->cart_id)) {
             $query = 'select customer_id from cart where id=?';
             $query = $this->db->prepare_query($query,$this->cart_id);
             $row = $this->db->get_record($query);
             if ($row && $row['customer_id']) {
                $customer_id = $row['customer_id'];
                if ($this->id == $customer_id) {
                   if ($log_cart_errors_enabled)
                      log_cart_error('Cart Error: Customer is Already Logged In');
                   $this->status = ALREADY_LOGGED_IN;
                }
             }
             else if (isset($this->db->error)) {
                $this->error = $this->db->error;   $this->status = DB_ERROR;
                $this->errors['dberror'] = true;
             }
          }
          else if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Customer is Already Registered');
          return false;
       }
       else if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }
    return true;
}

function save_cart_email()
{
    if ((! isset($this->info['email'])) || (! $this->info['email']))
       return true;
    if ((! isset($this->cart_id)) || (! $this->cart_id)) return true;
    $query = 'update cart set email=? where id=?';
    $query = $this->db->prepare_query($query,$this->info['email'],
                                      $this->cart_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function create($creating_guest=false)
{
    global $user_cookie,$log_cart_errors_enabled,$user_cookie_expiration;
    global $guest_customer_status;

    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       $this->status = DB_ERROR;   $this->errors['dberror'] = true;
       return;
    }
    if ((! isset($this->quote_module)) &&
        ((! isset($this->customers_record['email']['value'])) ||
         ($this->customers_record['email']['value'] == ''))) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Missing Customer E-Mail Address');
       $this->status = MISSING_EMAIL;   return false;
    }
    if (! $this->check_existing()) return false;
    $current_time = time();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    if (isset($this->customers_record['password']['value']) &&
        $this->customers_record['password']['value']) {
       $encrypted_password =
          crypt($this->customers_record['password']['value'],$ip_address);
       $this->customers_record['password']['value'] = $encrypted_password;
    }
    $this->customers_record['create_date']['value'] = $current_time;
    $this->customers_record['last_modified']['value'] = $current_time;
    $this->customers_record['ip_address']['value'] = $ip_address;
    if (! isset($this->customers_record['status']['value'])) {
       if ($creating_guest)
          $this->customers_record['status']['value'] = $guest_customer_status;
       else $this->customers_record['status']['value'] = 0;
    }

    if (! empty($this->guest_id)) {
       $this->id = $this->guest_id;
       $this->customers_record['id']['value'] = $this->id;
       if (! $this->db->update('customers',$this->customers_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }
    else {
       if (! $this->db->insert('customers',$this->customers_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
       $this->id = $this->db->insert_id();
       $this->customers_record['id']['value'] = $this->id;
    }

    $this->billing_record['parent']['value'] = $this->id;
    if (! empty($this->guest_id)) {
       $this->billing_record['parent']['key'] = true;
       if (! $this->db->update('billing_information',$this->billing_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }
    else if (! $this->db->insert('billing_information',$this->billing_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $query = 'delete from customers where id=?';
       $query = $this->db->prepare_query($query,$customer_id);
       $this->db->query($query);
       $this->errors['dberror'] = true;   return false;
    }

    $this->shipping_record['parent']['value'] = $this->id;
    $this->shipping_record['default_flag']['value'] = 1;
    if (! empty($this->guest_id)) {
       $this->shipping_record['parent']['key'] = true;
       if (! $this->db->update('shipping_information',$this->shipping_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }
    else if (! $this->db->insert('shipping_information',$this->shipping_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $query = 'delete from billing_information where parent=?';
       $query = $this->db->prepare_query($query,$customer_id);
       $this->db->query($query);
       $query = 'delete from customers where id=?';
       $query = $this->db->prepare_query($query,$customer_id);
       $this->db->query($query);
       $this->errors['dberror'] = true;   return false;
    }
    require_once __DIR__.'/../engine/modules.php';
    if (! empty($this->guest_id)) $module_event = 'update_customer';
    else $module_event = 'add_customer';
    if (module_attached($module_event)) {
       $customer_info = $this->db->convert_record_to_array($this->customers_record);
       $billing_info = $this->db->convert_record_to_array($this->billing_record);
       $shipping_info = $this->db->convert_record_to_array($this->shipping_record);
       if (function_exists('update_customer_info'))
          update_customer_info($this->db,$customer_info,$billing_info,
                               $shipping_info);
       if (! call_module_event($module_event,
                array($this->db,$customer_info,$billing_info,$shipping_info),
                null,true)) {
          $this->error = 'Unable to add/update customer: '.get_module_errors();
          $this->errors['dberror'] = true;   return false;
       }
    }
    if (! $creating_guest) {
       if (isset($this->cart_id) && (! $this->update_cart())) return;
       if (! $this->object_only) {
          $this->set_cookie($user_cookie,$this->id,1);
          $user_cookie_expiration = null;
       }
    }
    if (function_exists('custom_finish_customer_create'))
       custom_finish_customer_create($this);

    if (empty($this->guest_id)) $action = 'Added';
    else $action = 'Updated';
    if ($creating_guest) $type = 'Guest Customer';
    else $type = 'Customer';
    log_activity($action.' '.$type.' #'.$this->id.' ' .
                 $this->customers_record['email']['value'] .
                 ' ('.$this->customers_record['fname']['value'].' ' .
                 $this->customers_record['lname']['value'] .
                 ') with IP Address '.$ip_address);
    $remote_user = getenv('REMOTE_USER');
    if ($remote_user == $this->customers_record['email']['value']) {
       if (empty($this->guest_id)) $action = 'Registered';
       else $action = 'Updated';
       $activity = $type.' '.$action.' with IP Address '.$ip_address;
    }
    else $activity = $type.' '.$action.' by ' .
                     get_customer_activity_user($this->db);
    write_customer_activity($activity,$this->id,$this->db);
    if (get_form_field('UseOwnAddress'))
       write_customer_activity('Customer Used Entered Address',$this->id,
                               $this->db);
    $this->status = NO_ERROR;
    return true;
}

function add_subscription($type)
{
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('add_subscription')) {
       $customer_info = $this->info;
       if (isset($this->billing_info)) {
          $billing_info = $this->billing_info;
          unset($billing_info['id']);
          $customer_info = array_merge($customer_info,$billing_info);
       }
       if (! call_module_event('add_subscription',
                array($this->db,$type,$customer_info),null,true)) {
          $this->error = 'Unable to add subscription: '.get_module_errors();
          $this->errors['dberror'] = true;   return false;
       }
    }
    return true;
}

function validate($email,$password)
{
    global $user_cookie,$account_cookie,$multiple_customer_accounts;
    global $user_cookie_expiration,$enable_remember_login;

    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       $this->status = DB_ERROR;   $this->errors['dberror'] = true;
       return;
    }
    if (! $this->object_only) set_remote_user($email);
    $query = 'select id,password,ip_address,status,account_id from ' .
             'customers where email=';
    if ($this->db->check_encrypted_field('customers','email'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $this->db->prepare_query($query,$email);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;
       }
       else $this->status = EMAIL_NOT_FOUND;
       return false;
    }
    $this->db->decrypt_record('customers',$row);
    if (empty($row['password'])) {
       $this->status = EMPTY_PASSWORD;   return false;
    }
    if ($row['password'] != crypt($password,$row['ip_address'])) {
       $this->status = INVALID_PASSWORD;   return false;
    }
    if ($row['status'] == SUSPENDED_STATUS) {
       $this->status = SUSPENDED_ACCOUNT;   return false;
    }
    $this->id = $row['id'];
    if ($this->single_cart) $this->set_cart_cookie(true);
    else if (isset($this->cart_id) && (! $this->update_cart())) return;
    if ($this->single_wishlist) $this->set_wishlist_cookie(true);
    else if (isset($this->wishlist_id) && (! $this->update_wishlist())) return;
    if (! $this->object_only) {
       if ($enable_remember_login && (get_form_field('remember_login') == ''))
          $num_days = 0;
       else $num_days = 1;
       $this->set_cookie($user_cookie,$this->id,$num_days);
       $user_cookie_expiration = null;
       if (isset($account_cookie)) {
          if ($multiple_customer_accounts) {
             $account_id = get_form_field('account');
             if ($account_id === null) $this->set_cookie($account_cookie,0);
             else {
                if ($account_id) {
                   $query = 'select customer_id from customer_accounts ' .
                            'where customer_id=? and account_id=?';
                   $query = $this->db->prepare_query($query,$this->id,
                                                     $account_id);
                   $accounts_row = $this->db->get_record($query);
                   if ((! $accounts_row) || (! $accounts_row['customer_id']))
                      $account_id = 0;
                }
                $this->set_cookie($account_cookie,$account_id,$num_days);
                $this->account_id = $account_id;
             }
          }
          else if ($row['account_id'])
             $this->set_cookie($account_cookie,$row['account_id'],0);
       }
    }

    log_activity('Valid Login for Customer #'.$this->id.' '.$email);
    write_customer_activity('Logged In',$this->id,$this->db);
    $this->status = $row['status'];
    return true;
}

function check_email($email)
{
    $query = 'select id from customers where email=';
    if ($this->db->check_encrypted_field('customers','email'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $this->db->prepare_query($query,$email);
    $row = $this->db->get_record($query);
    if (! empty($row['id'])) {
       $this->errors['DuplicateEmail'] = true;   return false;
    }
    else if (isset($this->db->error)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function check_email_change()
{
    $new_email = $this->get('cust_email');
    $query = 'select email from customers where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
          return false;
       }
       return true;
    }
    $this->db->decrypt_record('customers',$row);
    if (empty($row['email'])) return true;
    if (strcasecmp($row['email'],$new_email) == 0) return true;
    return $this->check_email($new_email);
}

function get_password($email)
{
    $query = 'select id,password from customers where email=';
    if ($this->db->check_encrypted_field('customers','email'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $this->db->prepare_query($query,$email);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
          $this->status = DB_ERROR;
       }
       else {
          $this->status = EMAIL_NOT_FOUND;
          $this->errors['EmailNotFound'] = true;
       }
       return null;
    }
    $this->db->decrypt_record('customers',$row);
    $this->id = $row['id'];
    if (! isset($this->info)) $this->info = array();
    $this->info['email'] = $email;
    $this->info['password'] = $row['password'];
    $this->status = NO_ERROR;
    if (! $row['password']) return '';
    else return $row['password'];
}

function change_password($old_password,$new_password)
{
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }
    $query = 'select password,ip_address from customers where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
       }
       else $this->error = 'Customer not found';
       $this->errors['dberror'] = true;   return false;
    }
    $this->db->decrypt_record('customers',$row);
    if ($row['password'] != crypt($old_password,$row['ip_address'])) {
       $this->errors['OldPassword'] = true;
       $this->status = INVALID_PASSWORD;   return false;
    }
    $encrypted_password = crypt($new_password,$row['ip_address']);
    $this->set('cust_password',$encrypted_password);
    $this->status = NO_ERROR;
}

function check_password_strength($new_password)
{
    global $password_min_length,$password_upper_required;
    global $password_lower_required,$password_number_required;
    global $password_symbol_required,$password_number_or_symbol_required;
    $special_characters = '^$%&*()}{@#~?><>=+';

    $weak_errors = '';
    if (! empty($password_min_length)) {
       if (strlen($new_password) < $password_min_length) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .= 'Password must be at least '.$password_min_length .
                          ' characters long';
       }
    }
    if (! empty($password_upper_required)) {
       if (! preg_match('/[A-Z]/',$new_password)) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .=
             'Password must contain at least one uppercase character';
       }
    }
    if (! empty($password_lower_required)) {
       if (! preg_match('/[a-z]/',$new_password)) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .=
             'Password must contain at least one lowercase character';
       }
    }
    if (! empty($password_number_required)) {
       if (! preg_match('/[0-9]/',$new_password)) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .= 'Password must contain at least one number';
       }
    }
    if (! empty($password_symbol_required)) {
       if (! preg_match('/['.preg_quote($special_characters).']/',
                        $new_password)) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .= 'Password must contain at least one special ' .
                          'character ('.$special_characters.')';
       }
    }
    if (! empty($password_number_or_symbol_required)) {
       if ((! preg_match('/[0-9]/',$new_password)) &&
           (! preg_match('/['.preg_quote($special_characters).']/',
                         $new_password))) {
          if ($weak_errors) $weak_errors .= ', ';
          $weak_errors .= 'Password must contain at least one number or ' .
                          'special character ('.$special_characters.')';
       }
    }
    return $weak_errors;
}

function reset_password($new_password)
{
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $encrypted_password = crypt($new_password,$ip_address);
    $encrypted_fields = false;
    $query = 'update customers set password=';
    if ($this->db->check_encrypted_field('customers','password'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query .= ',ip_address=';
    if ($this->db->check_encrypted_field('customers','ip_address'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query .= ' where id=?';
    $query = $this->db->prepare_query($query,$encrypted_password,$ip_address,
                                      $this->id);
    $this->db->log_query($query);
    $result = $this->db->query($query);
    if (! $result) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       return false;
    }
    log_activity('Reset Password for Customer #'.$this->id);
    write_customer_activity('Reset Password',$this->id,$this->db);
    $this->status = NO_ERROR;
    return true;
}

function logout()
{
    global $user_cookie,$account_cookie,$multiple_customer_accounts;

    log_activity('Logout for Customer #'.$this->id);
    write_customer_activity('Logged Out',$this->id,$this->db);
    $this->id = null;
    if (! $this->object_only) {
       $this->set_cookie($user_cookie,0);
       if (isset($account_cookie)) $this->set_cookie($account_cookie,0);
    }
}

function load($shipping_profile_id = null)
{
    global $reorders_first_shipping_only,$auto_reorders_label;

    $this->billing_country = 1;
    $this->shipping_country = 1;

    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'Missing Customer ID';   return false;
    }

    if ((! $shipping_profile_id) && (! empty($reorders_first_shipping_only)) &&
        @Cart::check_auto_reorders()) {
       $query = 'select min(id) as profile_id from shipping_information ' .
                'where parent=?';
       $query = $this->db->prepare_query($query,$this->id);
       $row = $this->db->get_record($query);
       if (! empty($row['profile_id'])) {
          $shipping_profile_id = $row['profile_id'];
          $this->prevent_change_shipping = $auto_reorders_label .
             ' can only be shipped to the Primary Address in this account.';
       }
    }
    $customer = load_customer($this->db,$this->id,$error_msg,
                              $shipping_profile_id);
    if (! $customer) {
       $this->error = $error_msg;   $this->errors['dberror'] = true;
       return false;
    }

    $this->customers_record = $customer->customers_record;
    $this->info_changed = false;
    $this->billing_record = $customer->billing_record;
    $this->billing_changed = false;
    $this->shipping_record = $customer->shipping_record;
    $this->shipping_changed = false;
    $this->info = $customer->info;
    $this->billing = $customer->billing;
    $this->shipping = $customer->shipping;
    if (isset($this->billing['country']))
       $this->billing_country = $this->billing['country'];
    if (isset($this->shipping['country']))
       $this->shipping_country = $this->shipping['country'];
    return true;
}

function set($field_name,$field_value)
{
    if (! strncmp($field_name,'cust_',5)) {
       $field_name = substr($field_name,5);
       $this->info[$field_name] = $field_value;
       $this->customers_record[$field_name]['value'] = $field_value;
       $this->info_changed = true;
    }
    else if (! strncmp($field_name,'bill_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->billing_country == 1) || ($this->billing_country == 29) ||
              ($this->billing_country == 43)) return;
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->billing_country != 43) return;
          else $field_name = 'state';
       }
       else if ($field_name == 'country') $this->billing_country = $field_value;
       $this->billing[$field_name] = $field_value;
       $this->billing_record[$field_name]['value'] = $field_value;
       $this->billing_changed = true;
    }
    else if (! strncmp($field_name,'ship_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->shipping_country == 1) || ($this->shipping_country == 29) ||
              ($this->shipping_country == 43)) return;
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->shipping_country != 43) return;
          else $field_name = 'state';
       }
       else if ($field_name == 'country') $this->shipping_country = $field_value;
       $this->shipping[$field_name] = $field_value;
       $this->shipping_record[$field_name]['value'] = $field_value;
       $this->shipping_changed = true;
    }
}

function get($field_name)
{
    if (! strncmp($field_name,'cust_',5)) {
       $field_name = substr($field_name,5);
       if (isset($this->info) && isset($this->info[$field_name]))
          return $this->info[$field_name];
    }
    else if (! strncmp($field_name,'bill_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->billing_country == 1) || ($this->billing_country == 29) ||
              ($this->billing_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->billing_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->billing_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       if (isset($this->billing) && isset($this->billing[$field_name]))
          return $this->billing[$field_name];
    }
    else if (! strncmp($field_name,'ship_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->shipping_country == 1) || ($this->shipping_country == 29) ||
              ($this->shipping_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->shipping_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->shipping_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       if (isset($this->shipping) && isset($this->shipping[$field_name]))
          return $this->shipping[$field_name];
    }
    return '';
}

function update()
{
    if ((! $this->info_changed) && (! $this->billing_changed) &&
        (! $this->shipping_changed)) {
       $this->status = NO_ERROR;
       return true;
    }
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }


    if ($this->info_changed) {
       $this->customers_record['id']['value'] = $this->id;
       $compare_customer_info =
          $this->db->convert_record_to_array($this->customers_record);
    }
    else $compare_customer_info = null;

    if ($this->billing_changed) {
       $this->billing_record['parent']['value'] = $this->id;
       $this->billing_record['parent']['key'] = true;
       $compare_billing_info =
          $this->db->convert_record_to_array($this->billing_record);
    }
    else $compare_billing_info = null;

    if ($this->shipping_changed) {
       $this->shipping_record['parent']['value'] = $this->id;
       $this->shipping_record['parent']['key'] = true;
       $compare_shipping_info =
          $this->db->convert_record_to_array($this->shipping_record);
    }
    else $compare_shipping_info = null;

    $changes = get_customer_changes($this->db,$compare_customer_info,
                  $compare_billing_info,$compare_shipping_info);

    if ($this->info_changed) {
       if (! $this->db->update('customers',$this->customers_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }

    if ($this->billing_changed) {
       if (! $this->db->update('billing_information',$this->billing_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }

    if ($this->shipping_changed) {
       if (in_array('shipping_information',$changes)) {
          $this->shipping_record['default_flag']['value'] = 1;
          if (! $this->db->insert('shipping_information',
                                  $this->shipping_record)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
       }
       else if (! $this->db->update('shipping_information',
                                    $this->shipping_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }

    if (isset($this->info['email'])) $cust_email = $this->info['email'];
    if (isset($this->info['fname'])) $cust_fname = $this->info['fname'];
    if (isset($this->info['lname'])) $cust_lname = $this->info['lname'];
    if (empty($cust_email) || empty($cust_fname) || empty($cust_lname)) {
       $query = 'select email,fname,lname from customers where id=?';
       $query = $this->db->prepare_query($query,$this->id);
       $cust_info = $this->db->get_record($query);
       if (! $cust_info) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
          return;
       }
       $this->db->decrypt_record('customers',$cust_info);
       $cust_email = $cust_info['email'];
       $cust_fname = $cust_info['fname'];
       $cust_lname = $cust_info['lname'];
    }
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('update_customer')) {
       update_customer_info($this->db,$this->info,$this->billing,
                            $this->shipping);
       if (! call_module_event('update_customer',
                array($this->db,$this->info,$this->billing,$this->shipping),
                null,true)) {
          $this->error = 'Unable to update customer: '.get_module_errors();
          $this->errors['dberror'] = true;   return false;
       }
    }
    log_activity('Updated Customer #'.$this->id.' '.$cust_email.' (' .
                 $cust_fname.' '.$cust_lname.')');
    $activity = 'Customer Updated in '.basename($_SERVER['PHP_SELF']) .
                ' ['.implode(',',$changes).']';
    write_customer_activity($activity,$this->id,$this->db);
    $this->status = NO_ERROR;
    return true;
}

function get_shipping_profiles()
{
    if (! isset($this->id)) return null;
    $query = 'select id,profilename from shipping_information where ' .
             'parent=? order by profilename';
    $query = $this->db->prepare_query($query,$this->id);
    $profiles = $this->db->get_records($query,'id','profilename');
    if ((! $profiles) || (count($profiles) == 0)) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
       }
       return null;
    }
    return $profiles;
}

function check_profile($profile_id)
{
    $profile = $this->shipping['profilename'];
    if ($profile_id) {
       $query = 'select id from shipping_information where parent=? and ' .
                'profilename=? and id!=?';
       $query = $this->db->prepare_query($query,$this->id,$profile,$profile_id);
    }
    else {
       $query = 'select id from shipping_information where parent=? and ' .
                'profilename=?';
       $query = $this->db->prepare_query($query,$this->id,$profile);
    }
    $result = $this->db->query($query);
    if ($result && ($this->db->num_rows($result) > 0)) {
       $this->errors['DuplicateProfile'] = true;   return false;
    }
    else if (isset($this->db->error)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function reset_shipping_defaults()
{
    $query = 'update shipping_information set default_flag=0 where parent=?';
    $query = $this->db->prepare_query($query,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    return true;
}

function add_shipping($default_flag)
{
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }
    if (! $this->check_profile(null)) return false;
    if (($default_flag == 1) && (! $this->reset_shipping_defaults())) return false;

    $this->shipping_record['parent']['value'] = $this->id;
    $this->shipping_record['default_flag']['value'] = $default_flag;
    if (! $this->db->insert('shipping_information',$this->shipping_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }

    $activity = 'Added Shipping Profile ' .
                $this->shipping_record['profilename']['value'];
    log_activity($activity.' to Customer #'.$this->id);
    write_customer_activity($activity,$this->id,$this->db);
    return true;
}

function update_shipping($profile_id,$default_flag)
{
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }
    if (! $this->check_profile($profile_id)) return false;
    if (($default_flag == 1) && (! $this->reset_shipping_defaults())) return false;

    $this->shipping_record['id']['value'] = $profile_id;
    $this->shipping_record['default_flag']['value'] = $default_flag;
    if (! $this->db->update('shipping_information',$this->shipping_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }

    $activity = 'Updated Shipping Profile ' .
                $this->shipping_record['profilename']['value'];
    log_activity($activity.' for Customer #'.$this->id);
    write_customer_activity($activity,$this->id,$this->db);
    if (get_form_field('UseOwnAddress'))
       write_customer_activity('Customer Used Entered Address',$this->id,
                               $this->db);
    $this->status = NO_ERROR;
    return true;
}

function delete_shipping($profile_id)
{
    if ((! isset($this->id)) || (! $this->id)) {
       $this->error = 'You are not logged in';   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }
    $query = 'delete from shipping_information where id=?';
    $query = $this->db->prepare_query($query,$profile_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->status = DB_ERROR;   $this->error = $this->db->error;
       $this->errors['dberror'] = true;   return false;
    }
    log_activity('Deleted Shipping Profile #'.$profile_id .
                 ' from Customer #'.$this->id);
    $this->status = NO_ERROR;
    return true;
}

function load_saved_cards()
{
    $query = 'select * from saved_cards where parent=? order by ' .
             'card_year desc,card_month desc';
    $query = $this->db->prepare_query($query,$this->id);
    $saved_cards = $this->db->get_records($query,'id');
    return $saved_cards;
}

function write_saved_card_addresses($saved_cards,$selected_card)
{
    print '<script type="text/javascript">'."\n";
    print '  saved_card_addresses[\'\'] = {\'name\':\'';
    $name = $this->get('cust_fname');
    $mname = $this->get('cust_mname');
    if ($mname) $mname .= ' '.$mname;
    $name .= ' '.$this->get('cust_lname');
    print str_replace("'","\\'",$name);
    print '\',\'company\':\'';
    print str_replace("'","\\'",$this->get('cust_company'));
    print '\',\'address1\':\'';
    print str_replace("'","\\'",$this->get('bill_address1'));
    print '\',\'address2\':\'';
    print str_replace("'","\\'",$this->get('bill_address2'));
    print '\',\'location\':\'';
    $location = $this->get('bill_city');
    if ($this->billing_country != 29) $location .= ', ';
    if ($this->billing_country == 1) $location.= $this->get('bill_state');
    else if ($this->billing_country == 43)
    $location .= $this->get('bill_canada_province');
    else $location .= $this->get('bill_province');
    $location .= '&nbsp;&nbsp;'.$this->get('bill_zipcode').' ';
    $location .= $this->get('bill_country_name');
    print str_replace("'","\\'",$location);
    print '\',\'phone\':\'';
    print str_replace("'","\\'",$this->get('bill_phone'));
    print "'};\n";
    foreach ($saved_cards as $saved_card) {
       print '  saved_card_addresses['.$saved_card['profile_id'] .
             '] = {\'name\':\'';
       $name = $saved_card['fname'];
       if ($saved_card['mname']) {
          if ($name) $name .= ' ';
          $name .= $saved_card['mname'];
       }
       if ($saved_card['lname']) {
          if ($name) $name .= ' ';
          $name .= $saved_card['lname'];
       }
       print str_replace("'","\\'",$name);
       print '\',\'company\':\'';
       print str_replace("'","\\'",$saved_card['company']);
       print '\',\'address1\':\'';
       print str_replace("'","\\'",$saved_card['address1']);
       print '\',\'address2\':\'';
       print str_replace("'","\\'",$saved_card['address2']);
       print '\',\'location\':\'';
       $location = $saved_card['city'].', '.$saved_card['state'] .
                   '&nbsp;&nbsp;'.$saved_card['zipcode'];
       $country_name = get_country_name($saved_card['country'],$this->db);
       $location .= ' '.$country_name;
       print str_replace("'","\\'",$location);
       print '\',\'phone\':\'';
       print str_replace("'","\\'",$saved_card['phone']);
       print "'};\n";
    }
    print '</script>'."\n";
}

function display_saved_cards($saved_cards,$selected_card)
{
    print '<option value=""';
    if (! $selected_card) print ' selected';
    print '>Use new card:</option>'."\n";
    foreach ($saved_cards as $saved_card) {
       print '<option value="'.$saved_card['profile_id'].'"';
       if ($selected_card == $saved_card['profile_id']) print ' selected';
       print '>'.$saved_card['card_number'].' (Expires ' .
             $saved_card['card_month'].'-20'.$saved_card['card_year'] .
             ")</option>\n";
    }
}

function validate_saved_card($card_info)
{
    if (! $card_info['card_number']) $this->errors['card_number'] = true;
    if (! $card_info['card_month']) $this->errors['card_month'] = true;
    if (! $card_info['card_year']) $this->errors['card_year'] = true;
    if (! $card_info['card_cvv']) $this->errors['card_cvv'] = true;
    if (count($this->errors) > 0) return false;
    return true;
}

function get_customer_profile_id()
{
    $query = 'select profile_id from customers where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $customer_info = $this->db->get_record($query);
    if (! $customer_info) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'Customer Not Found';
       $this->errors['dberror'] = true;   return false;
    }
    $profile_id = $customer_info['profile_id'];
    return $profile_id;
}

function add_saved_card()
{
    require_once __DIR__.'/savedcards.php';
    $card_record = card_record_definition();
    $card_record['parent']['value'] = $this->id;
    parse_card_fields($this->db,$card_record,ADDRECORD);
    $card_info = $this->db->convert_record_to_array($card_record);
    if (! $this->validate_saved_card($card_info)) return $card_info;
    $card_id = add_card_record($this->db,$card_record,$error);
    if (! $card_id) {
       $this->error = $error;   $this->errors['dberror'] = true;
       return $card_info;
    }
    $activity = 'Added Saved Credit Card #'.$card_id;
    log_activity($activity.' to Customer #'.$this->id);
    write_customer_activity($activity,$this->id,$this->db);
    return $card_info;
}

function load_saved_card($card_id)
{
    $query = 'select * from saved_cards where id=?';
    $query = $this->db->prepare_query($query,$card_id);
    $card_info = $this->db->get_record($query);
    if (! $card_info) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'Saved Credit Card Not Found';
       $this->errors['dberror'] = true;
    }
    return $card_info;
}

function update_saved_card()
{
    require_once __DIR__.'/savedcards.php';
    $card_record = card_record_definition();
    $card_record['parent']['value'] = $this->id;
    parse_card_fields($this->db,$card_record,UPDATERECORD);
    $card_info = $this->db->convert_record_to_array($card_record);
    if (! $this->validate_saved_card($card_info)) return $card_info;
    if (! update_card_record($this->db,$card_record,$error)) {
       $this->error = $error;   $this->errors['dberror'] = true;
       return $card_info;
    }
    $activity = 'Updated Saved Credit Card #'.$card_record['id']['value'];
    log_activity($activity.' for Customer #'.$this->id);
    write_customer_activity($activity,$this->id,$this->db);
}

function delete_saved_card($card_id)
{
    require_once __DIR__.'/savedcards.php';
    if (! delete_card_record($this->db,$card_id,$this->id,$error)) {
       $this->error = $error;   $this->errors['dberror'] = true;   return;
    }
    $activity = 'Deleted Saved Credit Card #'.$card_id;
    log_activity($activity.' for Customer #'.$this->id);
    write_customer_activity($activity,$this->id,$this->db);
}

function load_payment_types()
{
    if (! empty($this->info['account_id']))
       $account_id = $this->info['account_id'];
    else $account_id = 0;
    return build_payment_types($this->db,$account_id);
}

function get_credit_balance()
{
    global $enable_credit_balance;

    if (empty($enable_credit_balance)) return 0;
    if (isset($this->info)) {
       if (empty($this->info['credit_balance'])) return 0;
       return $this->info['credit_balance'];
    }
    $query = 'select credit_balance from customers where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (empty($row['credit_balance'])) return 0;
    return $row['credit_balance'];
}

};

class Order {

function __construct($customer = null,$db = null,$object_only = false)
{
    global $user_cookie,$default_currency,$enable_rewards,$system_disabled;
    global $order_type,$order_label;

    if (! isset($system_disabled)) $system_disabled = false;
    if ($customer) {
       $this->customer = $customer;
       $this->customer_id = $customer->id;
       $this->db = $customer->db;
    }
    else {
       if ($db) $this->db = $db;
       $this->db = new DB;
       if ((! $this->db) || isset($this->db->error)) return;
       if (! $object_only) {
          $this->customer_id = get_cookie($user_cookie);
          if (! is_numeric($this->customer_id)) $this->customer_id = null;
       }
    }
    $this->errors = array();
    $this->info = array();
    $this->credit_card = array();
    $this->payment = array();
    $this->features = get_cart_config_value('features',$this->db);
    $this->enable_rewards = $enable_rewards;
    if (! isset($default_currency)) $default_currency = 'USD';
    $this->currency = $default_currency;
    $this->info['currency'] = $default_currency;
    setup_exchange_rate($this);
    $this->checkout_module = 'checkout.php';
    $this->process_module = 'process-order.php';
    $this->system_disabled = $system_disabled;
    $this->partial_payment = false;
    $this->balance_payment = false;
    $this->num_payment_divs = 0;
    $this->enable_echecks = false;
    $this->include_purchase_order = false;
    $this->comments_field_name = 'comments';
    $this->save_card_available = true;
    if (isset($order_type)) $this->order_type = $order_type;
    else $this->order_type = $order_type = ORDER_TYPE;
    switch ($this->order_type) {
       case ORDER_TYPE:
          if (isset($order_label)) $this->label = $order_label;
          else $this->label = 'Order';
          $this->table = 'orders';   break;
       case QUOTE_TYPE:
          $this->label = 'Quote';   $this->table = 'quotes';   break;
       case INVOICE_TYPE:
          $this->label = 'Invoice';   $this->table = 'invoices';   break;
       case SALESORDER_TYPE:
          $this->label = 'Sales Order';   $this->table = 'sales_orders';
          break;
    }
}

function Order($customer = null,$db = null,$object_only = false)
{
    self::__construct($customer,$db,$object_only);
}

function set($field_name,$field_value)
{
    $this->info[$field_name] = $field_value;
}

function calculate_tax($customer)
{
    @Cart::calculate_tax_amount($this,$customer);
}

function update_free_shipping_label(&$shipping_method)
{
    if (! empty($this->info['shipping'])) return;
    if (empty($shipping_method)) return;
    if (strpos($shipping_method,'Free') !== false) return;
    $shipping_method .= ' (Free)';
}

function process_shipping()
{
    $shipping_method = get_form_field('shipping_method');
    if (! $shipping_method) {
       $this->set('shipping',0);   return;
    }
    $shipping_info = explode('|',$shipping_method);
    $shipping_module = $shipping_info[0];
    if (! shipping_module_event_exists('process_shipping',$shipping_module)) {
       $this->set('shipping',0);   return;
    }
    $process_shipping = $shipping_module.'_process_shipping';
    $process_shipping($this,$shipping_method);
    $this->update_free_shipping_label($this->info['shipping_method']);
}

function format_shipping_field($field_name)
{
    if (! isset($this->info['shipping_carrier'])) return '';
    $shipping_module = $this->info['shipping_carrier'];
    if (! $shipping_module) return '';
    if (! shipping_module_event_exists('format_shipping_field',
                                       $shipping_module))
       return 'Invalid Shipping Carrier ('.$shipping_module.')';
    $format_shipping_field = $shipping_module.'_format_shipping_field';
    $result = $format_shipping_field($this->info,$field_name);
    if ($field_name == 'shipping_method')
       $this->update_free_shipping_label($result);
    return $result;
}

function read_hidden_fields()
{
    $subtotal = get_form_field('subtotal');
    if ($subtotal === null) {
       $this->errors['dberror'] = true;
       $this->error = 'No checkout information found';
       $this->status = DB_ERROR;   return false;
    }
    $this->set('subtotal',get_form_field('subtotal'));
    $this->set('tax',get_form_field('tax'));
    $this->set('tax_zone',get_form_field('tax_zone'));
    $this->set('tax_rate',get_form_field('tax_rate'));
    $this->set('coupon_id',get_form_field('coupon_id'));
    $this->set('coupon_code',get_form_field('coupon_code'));
    $this->set('coupon_amount',get_form_field('coupon_amount'));
    if ($this->enable_rewards) $this->set('rewards',get_form_field('rewards'));
    if ($this->features & GIFT_CERTIFICATES) {
       $this->set('gift_id',get_form_field('gift_id'));
       $this->set('gift_code',get_form_field('gift_code'));
       $this->set('gift_amount',get_form_field('gift_amount'));
       $this->set('gift_balance',get_form_field('gift_balance'));
    }
    $fee_name = get_form_field('fee_name');
    if ($fee_name) {
       $this->set('fee_name',$fee_name);
       $this->set('fee_amount',get_form_field('fee_amount'));
    }
    if ((! $this->enable_rewards) || (! $this->info['rewards'])) {
       $discount_name = get_form_field('discount_name');
       if ($discount_name) {
          $this->set('discount_name',$discount_name);
          $this->set('discount_amount',get_form_field('discount_amount'));
       }
    }
    $payment_method = get_form_field('payment_method');
    if ($payment_method) $this->payment['payment_method'] = $payment_method;
/* If amounts are being shown in the currency of the cart, this shouldn't be necessary.
    if ($this->exchange_rate) {
       $this->info['subtotal'] = round(floatval($this->info['subtotal']) *
                                       $this->exchange_rate,2);
       $this->info['tax'] = round(floatval($this->info['tax']) *
                                  $this->exchange_rate,2);
    }
*/
    if (isset($this->info['shipping'])) $shipping = $this->info['shipping'];
    else $shipping = 0;
    $total = $this->info['subtotal'] + $this->info['tax'] + $shipping -
             $this->info['coupon_amount'];
    if ($this->features & GIFT_CERTIFICATES)
       $total -= $this->info['gift_amount'];
    if ($fee_name) $total += $this->info['fee_amount'];
    if ($this->enable_rewards && $this->info['rewards'])
       $total -= $this->info['rewards'];
    else if ($discount_name) $total -= $this->info['discount_amount'];
    $this->set('total',$total);
    $this->item_checksum = get_form_field('item_checksum');
    if (! $this->item_checksum) $this->item_checksum = 0;
    if (isset($this->cart) && isset($this->cart->info['comments']))
       $comments = $this->cart->info['comments'];
    else $comments = null;
    $new_comments = trim(get_form_field('comments'));
    if ($comments && $new_comments) $comments .= "\n".$new_comments;
    else if (! $comments) $comments = $new_comments;
    $this->info['comments'] = $comments;
    return true;
}

function check_if_cart_changed()
{
    global $log_cart_errors_enabled,$checkout_warning;

    if (empty($this->items)) $item_checksum = 0;
    else $item_checksum = $this->cart->get_item_checksum();
    if ($item_checksum != $this->item_checksum) {
       $checkout_warning = 'Cart Items have changed since this page was ' .
          'loaded.  Please review the order items below and resubmit the ' .
          'payment request';
       if ($log_cart_errors_enabled) log_cart_error('Cart Items have changed');
       return true;
    }
    return false;
}

function process_on_account_products()
{
    $payment_amount = $this->info['total'];
    foreach ($this->items as $item_id => $order_item) {
       if (get_form_field('on_account_'.$item_id)) {
          if (! isset($this->items[$item_id]['flags']))
             $this->items[$item_id]['flags'] = 0;
          $this->items[$item_id]['flags'] |= ON_ACCOUNT_ITEM;
          $item_total = get_item_total($order_item,true);
          $payment_amount -= $item_total;
       }
    }
    $this->payment['payment_amount'] = $payment_amount;
}

function set_order_number()
{
    global $base_order_number;

    if ($this->order_type != ORDER_TYPE) return true;
    if ($this->features & ORDER_PREFIX) {
       $this->info['order_number'] =
          get_cart_config_value('orderprefix',$this->db) .
          $this->customer->id.'-'.time();
       if (function_exists('custom_update_order_number'))
          custom_update_order_number($this,$this->info['order_number']);
    }
    else {
       $this->orders_record = orders_record_definition();
       $this->orders_record['status']['value'] = '0';
       $this->orders_record['customer_id']['value'] = $this->customer->id;
       $this->orders_record['email']['value'] = $this->customer->info['email'];
       if (! $this->db->insert($this->table,$this->orders_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
       $this->id = $this->db->insert_id();
       if ($this->features & ORDER_BASE_ID)
          $order_number = intval(get_cart_config_value('orderprefix',$this->db)) +
                          $this->id;
       else if (empty($base_order_number)) $order_number = $this->id;
       else $order_number = $base_order_number + $this->id;
       if ($this->features & ORDER_PREFIX_ID)
          $order_number = get_cart_config_value('orderprefix').$order_number;
       if (function_exists('custom_update_order_number'))
          custom_update_order_number($this,$order_number);
       $this->info['order_number'] = $order_number;
       $activity = 'Created Pending Order #'.$this->id.' (' .
                    $order_number.')';
       log_activity($activity);
       $activity_user = get_customer_activity_user($this->db);
       if ($activity_user) $activity .= ' by '.$activity_user;
       write_customer_activity($activity,$this->customer->id,$this->db);
    }
    return true;
}

function cleanup_order()
{
    if ($this->order_type != ORDER_TYPE) return;
    if (empty($this->id)) return;
    if (isset($this->db->error)) {
       $db_error = $this->db->error;   unset($this->db->error);
    }
    else $db_error = null;
    if (! empty($this->error)) $error = $this->error;
    else if ($db_error) $error = $db_error;
    else {
       $e = new \Exception;
       $error = 'Unknown Error in ' .
                str_replace("\n",', ',$e->getTraceAsString());
    }

    $query = 'select count(id) from order_payments as num_payments ' .
             'where parent=? and parent_type=?';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $row = $this->db->get_record($query);
    if (! empty($row['num_payments'])) {
       $activity = 'Skipping deletion of Pending Order #'.$this->id.' (' .
          $this->info['order_number'].') ['.$error.'] since payments exist';
       log_error($activity);
    }

    $query = 'delete from '.$this->table.' where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $this->db->log_query($query);
    $this->db->query($query);
    $query = 'alter table '.$this->table.' auto_increment=0';
    $this->db->query($query);
    $query = 'delete from order_billing where parent=? and parent_type=?';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->log_query($query);
    $this->db->query($query);
    $query = 'delete from order_shipping where parent=? and parent_type=?';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->log_query($query);
    $this->db->query($query);
    $query = 'delete from order_items where parent=? and parent_type=?';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->log_query($query);
    $this->db->query($query);

    if ($db_error) $this->db->error = $db_error;
    $activity = 'Deleted Pending Order #'.$this->id.' (' .
                 $this->info['order_number'].') ['.$error.']';
    log_activity($activity);
    if (! empty($this->customer->id)) {
       $activity_user = get_customer_activity_user($this->db);
       if ($activity_user) $activity .= ' by '.$activity_user;
       write_customer_activity($activity,$this->customer->id,$this->db);
    }
}

function parse_card_fields()
{
    if (empty($this->payment['payment_amount'])) return;
    if (button_pressed('ContinueNoPayment')) return;
    $saved_card = get_form_field('SavedCard');
    if ($saved_card) {
       $this->saved_card = $saved_card;   return;
    }
    if ($this->enable_echecks) {
       $payment_type = get_form_field('payment_type');
       if ($payment_type == 1) {
          $this->echeck = array();
          $this->echeck['bank_name'] = trim(get_form_field('bank_name'));
          $this->echeck['routing_number'] = trim(get_form_field('routing_number'));
          $this->echeck['account_number'] = trim(get_form_field('account_number'));
          $this->echeck['account_name'] = trim(get_form_field('account_name'));
          $this->echeck['account_type'] = trim(get_form_field('account_type'));
          return;
       }
    }

    $this->credit_card['type'] = trim(get_form_field('card_type'));
    $this->credit_card['name'] = trim(get_form_field('card_name'));
    $this->credit_card['number'] = trim(preg_replace('/[^0-9]/','',
                                         get_form_field('card_number')));
    $this->credit_card['cvv'] = trim(get_form_field('card_cvv'));
    $this->credit_card['month'] = trim(get_form_field('card_month'));
    $this->credit_card['year'] = trim(get_form_field('card_year'));
    if ($this->include_purchase_order)
       $this->set('purchase_order',trim(get_form_field('purchase_order')));
}

function validate_credit_card()
{
    global $log_cart_errors_enabled;

    if (empty($this->payment['payment_amount'])) return true;
    if (button_pressed('ContinueNoPayment')) return true;
    if (isset($this->saved_card)) return true;
    if (isset($this->echeck)) {
       if ((! isset($this->echeck['bank_name'])) ||
           ($this->echeck['bank_name'] == ''))
          $this->errors['bank_name'] = true;
       if ((! isset($this->echeck['routing_number'])) ||
           ($this->echeck['routing_number'] == ''))
          $this->errors['routing_number'] = true;
       if ((! isset($this->echeck['account_number'])) ||
           ($this->echeck['account_number'] == ''))
          $this->errors['account_number'] = true;
       if ((! isset($this->echeck['account_name'])) ||
           ($this->echeck['account_name'] == ''))
          $this->errors['account_name'] = true;
       if ((! isset($this->echeck['account_type'])) ||
           ($this->echeck['account_type'] == ''))
          $this->errors['account_type'] = true;
       if (count($this->errors) > 0) return false;
       return true;
    }

    if ((! isset($this->credit_card['name'])) ||
        ($this->credit_card['name'] == '')) $this->errors['card_name'] = true;
    if ((! isset($this->credit_card['number'])) ||
        ($this->credit_card['number'] == '')) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Empty Card Number');
       $this->errors['card_number'] = true;
    }
    if ((! isset($this->credit_card['cvv'])) ||
        ($this->credit_card['cvv'] == '')) $this->errors['card_cvv'] = true;
    if ((! isset($this->credit_card['type'])) ||
        ($this->credit_card['type'] == '')) {
       if ($log_cart_errors_enabled)
          log_cart_error('Cart Error: Missing Card Type');
       $this->errors['card_number'] = true;
    }
    if (! isset($this->errors['card_number'])) {
       switch ($this->credit_card['type']) {
          case 'amex': $reg_exp = '/^3[47][0-9]{13}$/';   break;
          case 'visa': $reg_exp = '/^4[0-9]{12}([0-9]{3})?$/';   break;
          case 'master': $reg_exp = '/^5[0-5][0-9]{14}$/';   break;
          case 'discover': $reg_exp = '/^6011[0-9]{12}$/';   break;
          case 'diners': $reg_exp = '/^3(0[0-5]|[68][0-9])[0-9]{11}$/';   break;
          case 'JCB':  $reg_exp = '/^(3[0-9]{4}|2131|1800)[0-9]{11}$/';   break;
          default: $reg_exp = '';
       }
       if ($reg_exp && (! preg_match($reg_exp,$this->credit_card['number']))) {
          if ($log_cart_errors_enabled)
             log_cart_error('Cart Error: Invalid Card Number for Card Type');
          $this->errors['card_number'] = true;
       }
    }
    $checksum_failed = false;
    if (! isset($this->errors['card_number'])) {
       $card_number = strrev($this->credit_card['number']);
       $total_sum = 0;
       for ($loop = 0;  $loop < strlen($card_number);  $loop++) {
          $current_num = substr($card_number,$loop,1);
          if (($loop % 2) == 1) $current_num *= 2;
          if ($current_num > 9) {
             $first_num = $current_num % 10;
             $second_num = ($current_num - $first_num) / 10;
             $current_num = $first_num + $second_num;
          }
          $total_sum += $current_num;
       }
       if (($total_sum % 10) != 0) {
          $this->errors['card_number'] = true;   $checksum_failed = true;
       }
    }
    $month = date('n');
    $year = date('y');
    if (($this->credit_card['year'] < $year) ||
        (($this->credit_card['year'] == $year) &&
         ($this->credit_card['month'] < $month)))
       $this->errors['card_expired'] = true;
    call_payment_event('validate_credit_card',array(&$this));
    if (count($this->errors) > 0) {
       if ($checksum_failed && $log_cart_errors_enabled &&
           isset($this->errors['card_number']))
          log_cart_error('Cart Error: Card Number Checksum Failed');
       return false;
    }
    return true;
}

function load_cart($cart_id = null)
{
    $this->cart = new Cart($this->db,$cart_id,null,true);
    if ($this->cart->flags & CART_DELETED) {
       $this->errors['error'] = true;
       $this->error = 'The order has already been processed';
       $this->status = DB_ERROR;   return false;
    }
    if (! empty($this->cart->id)) $this->items = $this->cart->load();
    else $this->items = null;
    if (! $this->items) {
       $this->errors['dberror'] = true;
       if (isset($this->cart->error))
          $this->error = 'Unable to load cart items: '.$this->cart->error;
       else $this->error = 'No cart items found';
       $this->status = DB_ERROR;   return false;
    }
    $this->info['currency'] = $this->cart->info['currency'];
    change_currency($this,$this->info['currency']);
    $this->info['reorder_id'] = $this->cart->info['reorder_id'];
    if (isset($this->cart->info['coupon_id'],$this->cart->info['coupon_code']) &&
        ($this->cart->info['coupon_code'] != '')) {
       $this->info['coupon_id'] = $this->cart->info['coupon_id'];
       $this->info['coupon_code'] = $this->cart->info['coupon_code'];
       $this->info['coupon_amount'] = $this->cart->info['coupon_amount'];
    }
    if (isset($this->cart->info['gift_id']) &&
        ($this->cart->info['gift_id'] != '')) {
       $this->info['gift_id'] = $this->cart->info['gift_id'];
       $this->info['gift_amount'] = $this->cart->info['gift_amount'];
    }
    if (isset($this->cart->info['discount_name']) &&
        ($this->cart->info['discount_name'] != '')) {
       $this->info['discount_name'] = $this->cart->info['discount_name'];
       $this->info['discount_amount'] = $this->cart->info['discount_amount'];
    }
    if (! isset($this->customer_id)) {
       if (isset($this->cart->info['customer_id']))
          $this->customer_id = $this->cart->info['customer_id'];
       else $this->customer_id = null;
    }
    if (! isset($this->customer))
       $this->customer = new Customer($this->db,$this->customer_id,true);
    
    if (! $this->customer_id) {
       $this->customer->parse();
       $this->customer->setup_guest();
    }
    else $this->customer->load();
    if (count($this->customer->errors) > 0) {
       $this->error = $this->customer->error;
       $this->errors = $this->customer->errors;
       $this->status = DB_ERROR;   return false;
    }
    return true;
}

function write_amount($amount,$use_cents_flag=false,$write_output=true)
{
    global $amount_cents_flag;

    if ($use_cents_flag) {
       if (isset($amount_cents_flag) && (! $amount_cents_flag)) $precision = 0;
       else $precision = 2;
    }
    else $precision = 2;
    if ((! isset($this->currency)) && (! $this->exchange_rate)) return;
    $output = format_amount($amount,$this->currency,$this->exchange_rate,
                            $precision);
    if ($write_output) print $output;
    return $output;
}

function write_reorder_info($order_item)
{
    global $enable_reorders,$enable_auto_reorders,$auto_reorder_label;

    if (empty($enable_reorders)) return;
    if (empty($order_item['reorder_frequency'])) return;

    if (! empty($enable_auto_reorders)) {
       if (! empty($auto_reorder_label)) $reorder_label = $auto_reorder_label;
       else $reorder_label = 'Auto-Reorder';
    }
    else $reorder_label = 'Reorder';
    print "<br>\n".$reorder_label.' Every '.$order_item['reorder_frequency'] .
          ' Months';
}

function log_payment($msg)
{
    global $payment_log,$login_cookie;

    $payment_file = fopen($payment_log,'at');
    if ($payment_file) {
       $remote_user = getenv('REMOTE_USER');
       if (! $remote_user) $remote_user = get_cookie($login_cookie);
       if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($payment_file,$remote_user.' ['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($payment_file);
    }
}

function log_shipping($msg)
{
    global $shipping_log,$login_cookie;

    $shipping_file = fopen($shipping_log,'at');
    if ($shipping_file) {
       $remote_user = getenv('REMOTE_USER');
       if (! $remote_user) $remote_user = get_cookie($login_cookie);
       if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($shipping_file,$remote_user.' ['.date('D M d Y H:i:s').'] '.$msg."\n");
       fclose($shipping_file);
    }
}

function process_payment()
{
    global $log_cart_errors_enabled,$enable_continue_no_payment;

    if (empty($this->payment['payment_amount'])) {
       if ($log_cart_errors_enabled)
          log_cart_error('Payment Amount is zero, skipping ' .
                         'payment processing');
       $this->payment['payment_amount'] = 0.00;
       if (function_exists('custom_process_payment'))
          custom_process_payment($this);
       return true;
    }
    if (button_pressed('ContinueNoPayment')) {
       if (! $enable_continue_no_payment) {
          $this->error = 'ContinueNoPayment is disabled';
          log_cart_error($this->error);
          $this->errors['error'] = true;   return false;
       }
       if ($log_cart_errors_enabled)
          log_cart_error('ContinueNoPayment clicked, skipping payment ' .
                         'processing');
       $this->payment['payment_amount'] = 0.00;
       if (function_exists('custom_process_payment'))
          custom_process_payment($this);
       return true;
    }
    if (function_exists('custom_process_payment'))
       custom_process_payment($this);

    if (! payment_module_event_exists('process_payment',
                                      $this->payment_module)) {
       $this->errors['error'] = true;
       $this->error = 'Payment Module '.$this->payment_module .
                      ' is not able to process payments';
       return false;
    }
    $process_payment = $this->payment_module.'_process_payment';
    if (empty($this->info['order_number'])) {
       if (! $this->set_order_number()) return false;
    }
    if (isset($this->customer->billing['country']))
       $this->billing_country = $this->customer->billing['country'];
    else $this->billing_country = 1;
    if (isset($this->customer->shipping['country']))
       $this->shipping_country = $this->customer->shipping['country'];
    else $this->shipping_country = 1;
    if (! $process_payment($this)) {
       $this->errors['CardFailed'] = true;   $this->cleanup_order();
       return false;
    }
    return true;
}

function get_masked_card_number()
{
    if (! isset($this->credit_card['number'])) return '';
    $card_number = $this->credit_card['number'];
    return substr($card_number,0,6).str_pad('',strlen($card_number) - 10,'X') .
           substr($card_number,-4);
}

function update_payment_field($field_name,&$payment_record)
{
    if (empty($payment_record[$field_name]['value']) &&
        (! empty($this->payment[$field_name])))
       $payment_record[$field_name]['value'] = $this->payment[$field_name];
}

function copy_payment_data(&$payment_record,&$payment_info)
{
    global $login_cookie;

    if (empty($this->payment['payment_amount']))
       $payment_record['payment_amount']['value'] = 0;
    else $payment_record['payment_amount']['value'] =
       $this->payment['payment_amount'];
    $payment_record['payment_date']['value'] = time();
    $admin_user = get_cookie($login_cookie);
    if (isset($this->payment['payment_status']))
       $payment_record['payment_status']['value'] =
          $this->payment['payment_status'];
    if ($admin_user) $payment_record['payment_user']['value'] = $admin_user;
    if (! empty($this->payment['payment_method']))
       $payment_record['payment_method']['value'] =
          $this->payment['payment_method'];
    if (isset($this->payment_module)) {
       $payment_record['payment_type']['value'] = $this->payment_module;
       if ($this->payment_module == 'manual') {
          $payment_record['card_type']['value'] = $this->credit_card['type'];
          $payment_record['card_name']['value'] = $this->credit_card['name'];
          $payment_record['card_number']['value'] =
             $this->credit_card['number'];
          $payment_record['card_cvv']['value'] = $this->credit_card['cvv'];
          $payment_record['card_month']['value'] = $this->credit_card['month'];
          $payment_record['card_year']['value'] =
             '20'.$this->credit_card['year'];
          if ($payment_info !== null) {
             $payment_info['card_type'] = $this->credit_card['type'];
             $payment_info['card_name'] = $this->credit_card['name'];
             $payment_info['card_number'] = $this->credit_card['number'];
             $payment_info['card_cvv'] = $this->credit_card['cvv'];
             $payment_info['card_month'] = $this->credit_card['month'];
             $payment_info['card_year'] = '20'.$this->credit_card['year'];
          }
       }
       else {
          if (isset($this->credit_card['type']))
             $payment_record['card_type']['value'] =
                $this->credit_card['type'];
          if (isset($this->credit_card['name']))
             $payment_record['card_name']['value'] =
                $this->credit_card['name'];
          if (isset($this->save_all_card_data)) {
             $payment_record['card_number']['value'] =
                $this->credit_card['number'];
             $payment_record['card_cvv']['value'] = $this->credit_card['cvv'];
             $payment_record['card_month']['value'] =
                $this->credit_card['month'];
             $payment_record['card_year']['value'] =
                '20'.$this->credit_card['year'];
          }
          else if (isset($this->credit_card['number']))
             $payment_record['card_number']['value'] =
                $this->get_masked_card_number();
          if ($payment_info !== null) {
             if (isset($this->credit_card['type']))
                $payment_info['card_type'] = $this->credit_card['type'];
             if (isset($this->credit_card['name']))
                $payment_info['card_name'] = $this->credit_card['name'];
             if (isset($this->save_all_card_data)) {
                $payment_info['card_number'] = $this->credit_card['number'];
                $payment_info['card_cvv'] = $this->credit_card['cvv'];
                $payment_info['card_month'] = $this->credit_card['month'];
                $payment_info['card_year'] = '20'.$this->credit_card['year'];
             }
             else if (isset($this->credit_card['number']))
                $payment_info['card_number'] = $this->get_masked_card_number();
          }
       }
    }
    $this->update_payment_field('card_type',$payment_record);
    $this->update_payment_field('card_name',$payment_record);
    $this->update_payment_field('card_number',$payment_record);
    $this->update_payment_field('card_month',$payment_record);
    $this->update_payment_field('card_year',$payment_record);
    $this->update_payment_field('card_cvv',$payment_record);
    $this->update_payment_field('check_number',$payment_record);
    $this->update_payment_field('payment_id',$payment_record);
    $this->update_payment_field('payment_code',$payment_record);
    $this->update_payment_field('payment_ref',$payment_record);
    $this->update_payment_field('payment_data',$payment_record);
}

function load_product_costs()
{
    foreach ($this->items as $item_id => $order_item) {
       if (! $order_item['product_id'])
          $this->items[$item_id]['cost'] = $order_item['price'];
       else if ($this->features & PRODUCT_COST_PRODUCT) {
          $query = 'select cost from products where id=?';
          $query = $this->db->prepare_query($query,$order_item['product_id']);
          $row = $this->db->get_record($query);
          if ($row) $this->items[$item_id]['cost'] = $row['cost'];
          else $this->items[$item_id]['cost'] = $order_item['price'];
       }
       else if ($this->features & PRODUCT_COST_INVENTORY) {
          $query = 'select cost from product_inventory where parent=?';
          if ((! isset($order_item['attributes'])) ||
              ($order_item['attributes'] == ''))
             $lookup_attributes = null;
          else {
             $no_options = check_no_option_attributes($this->db,
                              $order_item['product_id']);
             if ($no_options)
                $attributes = explode('|',$order_item['attributes']);
             else $attributes = explode('-',$order_item['attributes']);
             $lookup_attributes = build_lookup_attributes($this->db,
                $order_item['product_id'],$attributes,true,$no_options);
          }
          if ($lookup_attributes) {
             $query .= ' and attributes=?';
             $query = $this->db->prepare_query($query,$order_item['product_id'],
                                               $lookup_attributes);
          }
          else {
             $query .= ' and ((attributes="") or isnull(attributes))';
             $query = $this->db->prepare_query($query,$order_item['product_id']);
          }
          $row = $this->db->get_record($query);
          if ($row) $this->items[$item_id]['cost'] = $row['cost'];
          else $this->items[$item_id]['cost'] = $order_item['price'];
       }
       else $this->items[$item_id]['cost'] = $order_item['price'];
    }
}

function save_credit_card()
{
    global $save_card_in_order;

    if (! isset($save_card_in_order)) $save_card_in_order = false;
    require_once __DIR__.'/savedcards.php';
    $card_record = card_record_definition();
    $card_record['country']['value'] = $this->billing_country;
    parse_card_fields($this->db,$card_record,ADDRECORD);
    if (! isset($card_record['card_number']['value'])) return true;

    if ($this->customer->id) {
       $full_card_number = $card_record['card_number']['value'];
       mask_card_number($card_record);
       $masked_number = $card_record['card_number']['value'];
       $card_record['card_number']['value'] = $full_card_number;
       $query = 'select id from saved_cards where parent=? and card_number=? ' .
                'and card_month=? and card_year=? and card_cvv=?';
       $query = $this->db->prepare_query($query,$this->customer->id,
                   $masked_number,$card_record['card_month']['value'],
                   $card_record['card_year']['value'],
                   $card_record['card_cvv']['value']);
       $row = $this->db->get_record($query);
       if ($row && $row['id']) {
          $this->payment['saved_card_id'] = $row['id'];
          $this->payment_record['saved_card_id']['value'] = $row['id'];
          return true;
       }
    }

    $card_record['parent']['value'] = $this->customer->id;
    if (isset($this->info['fname'])) {
       $card_record['fname']['value'] = $this->info['fname'];
       $card_record['mname']['value'] = $this->info['mname'];
       $card_record['lname']['value'] = $this->info['lname'];
       $card_record['company']['value'] = $this->info['company'];
    }
    else {
       $card_record['fname']['value'] = $this->customer->info['fname'];
       $card_record['mname']['value'] = $this->customer->info['mname'];
       $card_record['lname']['value'] = $this->customer->info['lname'];
       $card_record['company']['value'] = $this->customer->info['company'];
    }
    if (isset($this->billing)) $billing = $this->billing;
    else $billing = $this->customer->billing;
    foreach ($billing as $field_name => $field_value) {
       if (($field_name == 'id') || ($field_name == 'parent')) continue;
       if (isset($card_record[$field_name]))
          $card_record[$field_name]['value'] = $billing[$field_name];
    }
    $card_id = add_card_record($this->db,$card_record,$error);
    if (! $card_id) {
       $this->error = $error;   $this->errors['dberror'] = true;
       return false;
    }
    if ($save_card_in_order) {
       $this->payment['saved_card_id'] = $card_id;
       $this->payment_record['saved_card_id']['value'] = $card_id;
    }
    $activity = 'Added Saved Credit Card #'.$card_id;
    log_activity($activity.' to Customer #'.$this->customer->id);
    write_customer_activity($activity,$this->customer->id,$this->db);
    return true;
}

function copy_saved_card_billing($saved_cards)
{
    if (empty($saved_cards)) {
       $query = 'select * from saved_cards where profile_id=?';
       $query = $this->db->prepare_query($query,$this->saved_card);
       $saved_cards = $this->db->get_records($query);
       if (! $saved_cards) return;
    }
    $card = null;
    foreach ($saved_cards as $saved_card) {
       if ($saved_card['profile_id'] == $this->saved_card) {
          $card = $saved_card;   break;
       }
    }
    if (! $card) return;

    foreach ($this->billing_record as $field_name => $field_def) {
       if ($field_name == 'id') {}
       else if ($field_name == 'parent') {}
       else if ($field_name == 'parent_type') {}
       else if (array_key_exists($field_name,$card)) {
          $this->billing_record[$field_name]['value'] = $card[$field_name];
          $this->billing[$field_name] = $card[$field_name];
       }
    }
    if (isset($this->billing['country']))
       $this->billing_country = $this->billing['country'];
    else $this->billing_country = 1;
}

function cleanup_partial_order()
{
    if (isset($this->db->error)) {
       $db_error = $this->db->error;   unset($this->db->error);
    }
    else $db_error = null;
    $query = 'delete from '.$this->table.' where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $this->log_query($query);
    $this->db->query($query);
    $query = 'delete from order_items where (parent=?) and (parent_type=?)';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->query($query);
    $query = 'delete from order_billing where (parent=?) and (parent_type=?)';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->query($query);
    $query = 'delete from order_shipping where (parent=?) and (parent_type=?)';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->query($query);
    $query = 'delete from order_payments where (parent=?) and (parent_type=?)';
    $query = $this->db->prepare_query($query,$this->id,$this->order_type);
    $this->db->query($query);
    $query = 'alter table '.$this->table.' auto_increment=0';
    $this->db->query($query);
    if ($db_error) {
       $this->error = $db_error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;
    }
    $activity = 'Cleaned up Partial Order #'.$this->id.' (' .
                 $this->info['order_number'].') ['.$db_error.']';
    log_activity($activity);
    if (! empty($this->customer->id)) {
       $activity_user = get_customer_activity_user($this->db);
       if ($activity_user) $activity .= ' by '.$activity_user;
       write_customer_activity($activity,$this->customer->id,$this->db);
    }
}

function check_credit_limit()
{
    global $account_credit_limit;

    if (empty($account_credit_limit)) return true;
    if (empty($this->payment['payment_method'])) return true;
    if ($this->payment['payment_method'] != 'On Account') return true;
    if (empty($this->info['total'])) return true;
    $total = floatval($this->info['total']);
    if (empty($this->customer->id)) {
       $this->errors['error'] = true;   $this->status = DB_ERROR;
       $this->error = 'Guest Customers can not pay On Account';
       return false;
    }
    $query = 'select credit_limit from accounts where id=' .
             '(select account_id from customers where id=?)';
    $query = $this->db->prepare_query($query,$this->customer->id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;
       }
       else {
          $this->errors['error'] = true;   $this->status = DB_ERROR;
          $this->error = 'You do not have a valid account to pay On Account';
       }
       return false;
    }
    if ($row['credit_limit'] == '') return true;
    $credit_limit = floatval($row['credit_limit']);
    if ($total > $credit_limit) {
       $this->errors['error'] = true;   $this->status = DB_ERROR;
       $this->error = 'The Order total is higher than your credit limit of ' .
          $this->write_amount($credit_limit,true,false);
       return false;
    }
    return true;
}

function create()
{
    global $enable_multisite,$cache_catalog_pages,$enable_sales_reps;
    global $guest_mailing_flag,$taxcloud_api_id,$save_card_in_order;
    global $enable_partial_shipments,$disable_partial_ship_option;
    global $enable_vendors,$enable_auto_reorders,$guest_customer_status;

    if (! isset($cache_catalog_pages)) $cache_catalog_pages = false;
    if (! isset($enable_sales_reps)) $enable_sales_reps = false;
    if (! isset($save_card_in_order)) $save_card_in_order = false;
    $this->features = get_cart_config_value('features',$this->db);
    if ($this->features & MAINTAIN_INVENTORY) $maintain_inventory = true;
    else $maintain_inventory = false;
    $current_time = time();
    if (empty($this->info['order_number'])) {
       if (! $this->set_order_number()) return false;
    }
    switch ($this->order_type) {
       case ORDER_TYPE:
          $this->orders_record = orders_record_definition();   break;
       case QUOTE_TYPE:
          $this->orders_record = quotes_record_definition();   break;
       case INVOICE_TYPE:
          $this->orders_record = invoices_record_definition();   break;
       case SALESORDER_TYPE:
          $this->orders_record = salesorders_record_definition();   break;
    }
    $this->info['customer_id'] = $this->customer->id;
    $this->orders_record['customer_id']['value'] = $this->info['customer_id'];
    if ($this->order_type == ORDER_TYPE) {
       if (! empty($_SERVER['REMOTE_ADDR']))
          $this->info['ip_address'] = $_SERVER['REMOTE_ADDR'];
       else if (! empty($this->customer->info['ip_address']))
          $this->info['ip_address'] = $this->customer->info['ip_address'];
       if (! empty($this->info['ip_address']))
          $this->orders_record['ip_address']['value'] =
             $this->info['ip_address'];
       if (isset($_SERVER['HTTP_USER_AGENT'])) {
          $this->info['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
          if (strlen($this->info['user_agent']) > 255)
             $this->info['user_agent'] =
                substr($this->info['user_agent'],0,255);
       }
       else if (isset($this->cart->info['user_agent']))
          $this->info['user_agent'] = $this->cart->info['user_agent'];
       if (isset($this->info['user_agent']))
          $this->orders_record['user_agent']['value'] =
             $this->info['user_agent'];
    }
    if (empty($this->info['email']))
       $this->info['email'] = $this->customer->info['email'];
    $this->orders_record['email']['value'] = $this->info['email'];
    if (empty($this->info['fname']))
       $this->info['fname'] = $this->customer->info['fname'];
    $this->orders_record['fname']['value'] = $this->info['fname'];
    if (empty($this->info['mname']))
       $this->info['mname'] = $this->customer->info['mname'];
    $this->orders_record['mname']['value'] = $this->info['mname'];
    if (empty($this->info['lname']))
       $this->info['lname'] = $this->customer->info['lname'];
    $this->orders_record['lname']['value'] = $this->info['lname'];
    if (empty($this->info['company']))
       $this->info['company'] = $this->customer->info['company'];
    $this->orders_record['company']['value'] = $this->info['company'];
    if (($this->info['customer_id'] == 0) && $guest_mailing_flag) {
       $this->info['mailing'] = $this->customer->info['mailing'];
       $this->orders_record['mailing']['value'] = $this->info['mailing'];
    }
    if ($this->order_type == ORDER_TYPE)
       $this->orders_record['order_number']['value'] =
          $this->info['order_number'];
    $this->orders_record['currency']['value'] = $this->info['currency'];
       if (isset($this->info['subtotal']))
       $this->orders_record['subtotal']['value'] = $this->info['subtotal'];
    if (isset($this->info['tax']))
       $this->orders_record['tax']['value'] = $this->info['tax'];
    if ($this->order_type == ORDER_TYPE) {
       if (! empty($this->info['tax_zone']))
          $this->orders_record['tax_zone']['value'] = $this->info['tax_zone'];
       if (! empty($this->info['tax_rate']))
          $this->orders_record['tax_rate']['value'] = $this->info['tax_rate'];
    }
    if (! empty($this->info['coupon_id'])) {
       if ($this->info['coupon_id'][0] == '~')
          $this->info['coupon_id'] =
             intval(substr($this->info['coupon_id'],1));
       $this->orders_record['coupon_id']['value'] = $this->info['coupon_id'];
       $this->orders_record['coupon_amount']['value'] =
          $this->info['coupon_amount'];
    }
    if ($this->features & GIFT_CERTIFICATES) {
       if (! empty($this->info['gift_id'])) {
          $this->orders_record['gift_id']['value'] = $this->info['gift_id'];
          $this->orders_record['gift_amount']['value'] =
             $this->info['gift_amount'];
       }
    }
    if (! empty($this->info['fee_name'])) {
       $this->orders_record['fee_name']['value'] = $this->info['fee_name'];
       $this->orders_record['fee_amount']['value'] = $this->info['fee_amount'];
    }
    if (! empty($this->info['discount_name'])) {
       $this->orders_record['discount_name']['value'] =
          $this->info['discount_name'];
       $this->orders_record['discount_amount']['value'] =
          $this->info['discount_amount'];
    }
    else if ($this->enable_rewards && $this->info['rewards']) {
       $this->orders_record['discount_name']['value'] = 'Rewards';
       $this->orders_record['discount_amount']['value'] =
          $this->info['rewards'];
    }
    if (isset($this->info['total']))
       $this->orders_record['total']['value'] = $this->info['total'];
    if (isset($this->info['shipping']))
       $this->orders_record['shipping']['value'] = $this->info['shipping'];
    if ($this->order_type != INVOICE_TYPE) {
       if (isset($this->info['shipping_method']))
          $this->orders_record['shipping_method']['value'] =
             $this->info['shipping_method'];
       if (isset($this->info['shipping_carrier']))
          $this->orders_record['shipping_carrier']['value'] =
             $this->info['shipping_carrier'];
    }
    if ($this->order_type == ORDER_TYPE) {
       if (! $this->check_credit_limit()) {
          $this->cleanup_order();   return false;
       }
       if ($enable_partial_shipments && (! $disable_partial_ship_option)) {
          $partial_ship = get_form_field('partial_ship');
          if ($partial_ship == 'on') $partial_ship = 1;
          else $partial_ship = 0;
          $this->info['partial_ship'] = $partial_ship;
          $this->orders_record['partial_ship']['value'] = $partial_ship;
       }
       if (! empty($this->payment['payment_amount'])) {
          $payment_amount = floatval($this->payment['payment_amount']);
          $total_amount = floatval($this->info['total']);
          if ($payment_amount < $total_amount) {
             $balance_due = $total_amount - $payment_amount;
             $this->info['balance_due'] = $balance_due;
             $this->orders_record['balance_due']['value'] = $balance_due;
          }
       }
       else $this->orders_record['balance_due']['value'] =
               floatval($this->info['total']);
    }
    if (isset($this->info['purchase_order']))
       $this->orders_record['purchase_order']['value'] = $this->info['purchase_order'];
    if (! isset($this->info['status'])) $this->info['status'] = '0';
    $this->orders_record['status']['value'] = $this->info['status'];
    switch ($this->order_type) {
       case ORDER_TYPE: $date_field = 'order_date';   break;
       case QUOTE_TYPE: $date_field = 'quote_date';   break;
       case INVOICE_TYPE: $date_field = 'invoice_date';   break;
       case SALESORDER_TYPE: $date_field = 'order_date';   break;
    }
    $this->info[$date_field] = $current_time;
    $this->orders_record[$date_field]['value'] = $this->info[$date_field];
    if (! empty($this->info['comments']))
       $this->orders_record['comments']['value'] = $this->info['comments'];
    if (! empty($this->info['notes']))
       $this->orders_record['notes']['value'] = $this->info['notes'];
    if (! empty($this->info['external_source']))
       $this->orders_record['external_source']['value'] =
          $this->info['external_source'];
    if (! empty($this->info['external_id']))
       $this->orders_record['external_id']['value'] =
          $this->info['external_id'];
    if (! empty($this->info['phone_order']))
       $this->orders_record['phone_order']['value'] =
          $this->info['phone_order'];
    if (isset($this->cart) && $this->cart->registry) {
       $this->info['registry_id'] = $this->cart->info['registry_id'];
       $this->orders_record['registry_id']['value'] = $this->info['registry_id'];
       $this->info['gift_message'] = trim(get_form_field('gift_message'));
       $this->orders_record['gift_message']['value'] = $this->info['gift_message'];
    }
    if (! empty($this->info['reorder_id']))
       $this->orders_record['reorder_id']['value'] = $this->info['reorder_id'];
    if (isset($this->info['flags']))
       $this->orders_record['flags']['value'] = $this->info['flags'];
    if ($enable_multisite) {
       if (! empty($this->info['website'])) $website = $this->info['website'];
       else $website = get_current_website($this->db);
       if ($website) $this->orders_record['website']['value'] = $website;
    }
    if ($enable_sales_reps) {
       $sales_rep = get_form_field('sales_rep');
       if (! $sales_rep) {
          if (! empty($this->customer->info['sales_rep']))
             $sales_rep = $this->customer->info['sales_rep'];
          else if ($this->customer->id) {
             $query = 'select sales_rep from customers where id=?';
             $query = $this->db->prepare_query($query,$this->customer->id);
             $row = $this->db->get_record($query);
             if (! empty($row['sales_rep'])) $sales_rep = $row['sales_rep'];
          }
       }
       if ($sales_rep) {
          $this->info['sales_rep'] = $sales_rep;
          $this->orders_record['sales_rep']['value'] = $sales_rep;
       }
    }

    $this->billing_record = billing_record_definition();
    $this->billing_record['parent_type']['value'] = $this->order_type;
    $this->billing = array();
    foreach ($this->billing_record as $field_name => $field_def) {
       if ($field_name == 'id') {}
       else if ($field_name == 'parent') {}
       else if ($field_name == 'parent_type') {}
       else if (isset($this->customer->billing[$field_name])) {
          $this->billing_record[$field_name]['value'] =
             $this->customer->billing[$field_name];
          $this->billing[$field_name] =
             $this->billing_record[$field_name]['value'];
       }
    }
    if (isset($this->billing['country']))
       $this->billing_country = $this->billing['country'];
    else $this->billing_country = 1;

    $this->shipping_record = shipping_record_definition();
    $this->shipping_record['parent_type']['value'] = $this->order_type;
    $this->shipping = array();
    foreach ($this->shipping_record as $field_name => $field_def) {
       if ($field_name == 'id') {}
       else if ($field_name == 'parent') {}
       else if ($field_name == 'parent_type') {}
       else if (isset($this->customer->shipping[$field_name])) {
          $this->shipping_record[$field_name]['value'] =
             $this->customer->shipping[$field_name];
          $this->shipping[$field_name] =
             $this->shipping_record[$field_name]['value'];
       }
    }
    if (isset($this->shipping['country']))
       $this->shipping_country = $this->shipping['country'];
    else $this->shipping_country = 1;

    if (! empty($this->payment['payment_amount'])) {
       $this->payment_record = payment_record_definition();
       $this->payment_record['parent_type']['value'] = $this->order_type;
       $this->copy_payment_data($this->payment_record,$this->payment);
    }

    if (function_exists('update_custom_order_fields'))
       update_custom_order_fields($this);
    if (function_exists('custom_validate_order') &&
        (! custom_validate_order($this))) {
       $this->cleanup_order();   return false;
    }

    if (($this->customer->id == 0) && (! empty($guest_customer_status))) {
       if (! $this->customer->create(true)) {
          $this->error = $this->customer->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   $this->cleanup_order();
          return false;
       }
       $this->info['customer_id'] = $this->customer->id;
       $this->orders_record['customer_id']['value'] = $this->info['customer_id'];
    }

    $auto_save_card = false;   $saved_cards = null;
    if ((! empty($enable_auto_reorders)) && (! empty($this->items))) {
       foreach ($this->items as $id => $cart_item) {
          if (! empty($cart_item['reorder_frequency'])) {
             $auto_save_card = true;   break;
          }
       }
       if ($auto_save_card) {
          $saved_cards = $this->customer->load_saved_cards();
          if (! empty($saved_cards)) $auto_save_card = false;
       }
    }
    if (! empty($this->saved_card))
       $this->copy_saved_card_billing($saved_cards);

    if (($save_card_in_order || $auto_save_card ||
         (get_form_field('SaveCard') == 'on')) &&
        (! empty($this->payment_module)) && $this->save_card_available &&
        (! isset($this->saved_card)) &&
        (! button_pressed('ContinueNoPayment'))) {
       if ((! $this->save_credit_card()) && isset($this->db->error)) {
          $this->cleanup_order();   return false;
       }
    }

    if (empty($this->id)) {
       if (! $this->db->insert($this->table,$this->orders_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
       $this->id = $this->db->insert_id();
    }
    else {
       $this->orders_record['id']['value'] = $this->id;
       if (! $this->db->update($this->table,$this->orders_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   $this->cleanup_order();
          return false;
       }
    }
    $this->info['id'] = $this->id;
    $this->orders_record['id']['value'] = $this->id;
    $this->billing_record['parent']['value'] = $this->id;
    $this->billing['parent'] = $this->id;
    $this->shipping_record['parent']['value'] = $this->id;
    $this->shipping['parent'] = $this->id;

    if (isset($this->items)) {
       $this->load_product_costs();
       foreach ($this->items as $id => $cart_item) {
          $item_record = item_record_definition();
          $item_record['parent_type']['value'] = $this->order_type;
          foreach ($item_record as $field_name => $field_def) {
             if ($field_name == 'id') {}
             else if ($field_name == 'parent')
                $item_record['parent']['value'] = $this->id;
             else if ($field_name == 'parent_type') {}
             else if (isset($cart_item[$field_name]))
                $item_record[$field_name]['value'] = $cart_item[$field_name];

          }
          if ($this->exchange_rate) {
             $item_record['price']['value'] =
                round(floatval($item_record['price']['value']) *
                      $this->exchange_rate,2);
             $this->items[$id]['price'] = $item_record['price']['value'];
          }
          if (! $this->db->insert('order_items',$item_record)) {
             $this->cleanup_partial_order();   return false;
          }
          $item_id = $this->db->insert_id();
          $this->items[$id]['id'] = $item_id;
          $this->items[$id]['parent'] = $this->id;
          $this->items[$id]['parent_type'] = $this->order_type;
          $query = 'select * from cart_attributes where parent=?';
          $query = $this->db->prepare_query($query,$id);
          $rows = $this->db->get_records($query);
          if (! $rows) {
             if (isset($this->db->error)) {
                $this->cleanup_partial_order();   return false;
             }
             continue;
          }
          foreach ($rows as $row) {
             $attributes_record = item_attributes_record_definition();
             foreach ($row as $field_name => $field_value) {
                if ($field_name == 'id') {}
                else if ($field_name == 'parent')
                   $attributes_record['parent']['value'] = $item_id;
                else $attributes_record[$field_name]['value'] = $field_value;
             }
             if (! $this->db->insert('order_attributes',$attributes_record)) {
                $this->cleanup_partial_order();   return false;
             }
          }
       }
    }

    if (! $this->db->insert('order_billing',$this->billing_record)) {
       $this->cleanup_partial_order();   return false;
    }

    if (! $this->db->insert('order_shipping',$this->shipping_record)) {
       $this->cleanup_partial_order();   return false;
    }

    if (! empty($this->payment['payment_amount'])) {
       $this->payment_record['parent']['value'] = $this->id;
       if (! $this->db->insert('order_payments',$this->payment_record)) {
          $this->cleanup_partial_order();   return false;
       }
    }

    if (($this->order_type == ORDER_TYPE) &&
        (! empty($this->info['coupon_id']))) {
       $query = 'update coupons set qty_used=ifnull(qty_used+1,1) where id=?';
       $query = $this->db->prepare_query($query,$this->info['coupon_id']);
       $this->db->log_query($query);
       $result = $this->db->query($query);
       if (! $result) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }

    if (($this->order_type == ORDER_TYPE) &&
        ($this->features & GIFT_CERTIFICATES) &&
        (! empty($this->info['gift_id']))) {
       $query = 'update coupons set qty_used=1,gift_customer=?,' .
                'balance=balance-? where id=?';
       $query = $this->db->prepare_query($query,$this->customer->id,
                   $this->info['gift_amount'],$this->info['gift_id']);
       $this->db->log_query($query);
       $result = $this->db->query($query);
       if (! $result) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
    }

    if ($this->enable_rewards) {
       $rewards_factor = get_cart_config_value('rewards_factor',$this->db);
       $new_rewards = round((floatval($rewards_factor) / 100) *
                            $this->info['total'],2);
       if ($this->info['rewards'])
          $new_rewards -= floatval($this->info['rewards']);
       if ($new_rewards) {
          $query = 'update customers set rewards=rewards+? where id=?';
          $query = $this->db->prepare_query($query,$new_rewards,
                                            $this->customer->id);
          $this->db->log_query($query);
          $result = $this->db->query($query);
          if (! $result) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
       }
    }

    if (($this->order_type == ORDER_TYPE) && $maintain_inventory) {
       foreach ($this->items as $id => $cart_item) {
          if (! $cart_item['product_id']) continue;
          $query = 'update product_inventory set qty=ifnull(qty-?,-?) ';
          $where = 'where (parent=?)';
          if (empty($cart_item['attributes'])) $lookup_attributes = null;
          else {
             $no_options = check_no_option_attributes($this->db,
                                                      $cart_item['product_id']);
             if ($no_options) $attributes = explode('|',$cart_item['attributes']);
             else $attributes = explode('-',$cart_item['attributes']);
             $lookup_attributes = build_lookup_attributes($this->db,
                $cart_item['product_id'],$attributes,true,$no_options);
          }
          if ($lookup_attributes) {
             $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
                $cart_item['product_id'],$no_options,$this->db);
             $where .= ' and (attributes=?)';
             $query = $this->db->prepare_query($query.$where,$cart_item['qty'],
                         $cart_item['qty'],$cart_item['product_id'],
                         $lookup_attributes);
          }
          else {
             $query = $this->db->prepare_query($query.$where,$cart_item['qty'],
                         $cart_item['qty'],$cart_item['product_id']);
             $query .= ' and ((attributes="") or isnull(attributes))';
          }
          if ($cache_catalog_pages) {
             $qty_query = 'select qty from product_inventory '.$where;
             if ($lookup_attributes)
                $qty_query = $this->db->prepare_query($qty_query,
                                $cart_item['product_id'],$lookup_attributes);
             else $qty_query = $this->db->prepare_query($qty_query,
                                  $cart_item['product_id']);
             $qty_row = $this->db->get_record($qty_query);
             if ($qty_row) $previous_qty = $qty_row['qty'];
             else $previous_qty = 1;
          }
          $this->db->log_query($query);
          $result = $this->db->query($query);
          if (! $result) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
          if (using_linked_inventory($this->db,$this->features))
             update_linked_inventory($this->db,null,null,
                $cart_item['product_id'],$lookup_attributes);
          if ($cache_catalog_pages && ($previous_qty > 0)) {
             $qty_row = $this->db->get_record($qty_query);
             if ($qty_row && ($qty_row['qty'] <= 0))
                spawn_program('../cartengine/products.php updatecache ' .
                              $cart_item['product_id']);
          }
       }
    }

    if (($this->order_type == ORDER_TYPE) && isset($this->cart) &&
        $this->cart->registry) {
       foreach ($this->items as $id => $cart_item) {
          $query = 'update registry_items set qty_ordered=' .
                   'ifnull(qty_ordered+?,?) where (id=?)';
          $query = $this->db->prepare_query($query,$cart_item['qty'],
                      $cart_item['qty'],$cart_item['registry_item']);
          $this->db->log_query($query);
          $result = $this->db->query($query);
          if (! $result) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
       }
    }

    if (isset($this->cart) && ($this->cart->id != -1) &&
        (! $this->cart->clear())) {
       $this->cleanup_partial_order();   return false;
    }
    if (! isset($this->items)) $this->items = null;
    require_once __DIR__.'/../engine/modules.php';
    switch ($this->order_type) {
       case ORDER_TYPE: $event = 'add_order';   break;
       case QUOTE_TYPE: $event = 'add_quote';   break;
       case INVOICE_TYPE: $event = 'add_invoice';   break;
       case SALESORDER_TYPE: $event = 'add_salesorder';   break;
    }
    if (module_attached($event)) {
       $order_payments = load_order_payments($this);
       $order_shipments = load_order_shipments($this);
       $order_info = $this->db->convert_record_to_array($this->orders_record);
       $billing_info = $this->db->convert_record_to_array($this->billing_record);
       $shipping_info = $this->db->convert_record_to_array($this->shipping_record);
       update_order_info($this->db,$order_info,$billing_info,$shipping_info,
                         $this->items,$order_payments,$order_shipments);
       if (! call_module_event($event,array($this->db,$order_info,
                $billing_info,$shipping_info,$this->items,$order_payments,
                $order_shipments),null,true)) {
          $this->error = 'Unable to add order: '.get_module_errors();
          $this->status = DB_ERROR;   $this->errors['dberror'] = true;
          log_error($this->error);   return false;
       }
    }
    if (($this->order_type == ORDER_TYPE) && (! empty($taxcloud_api_id))) {
       require_once 'taxcloud.php';
       send_taxcloud_order($this);
    }
    if (! empty($enable_vendors)) send_to_vendors($this->id);
    if ((! empty($this->info['discount_amount'])) && $this->customer->id &&
        ($this->info['discount_name'] == 'Credit Balance')) {
       $query = 'update customers set credit_balance=credit_balance-? ' .
                'where id=?';
       $query = $this->db->prepare_query($query,$this->info['discount_amount'],
                                         $this->customer->id);
       $this->db->log_query($query);
       $this->db->query($query);
    }
    if (function_exists('custom_complete_order')) custom_complete_order($this);

    if ((! empty($this->items)) && $this->customer->id) {
       foreach ($this->items as $id => $cart_item) {
          if (! empty($cart_item['reorder_frequency'])) {
             $activity = $this->label.' Item #'.$id.' added to '.$this->label .
                         ' #'.$this->id.' with Frequency ' .
                         $cart_item['reorder_frequency'];
             write_customer_activity($activity,$this->customer->id,$this->db);
          }
       }
    }

    $activity = 'Added '.$this->label.' #'.$this->id;
    $activity_user = get_customer_activity_user($this->db);
    if ($activity_user) $activity .= ' by '.$activity_user;
    log_activity($activity.' for Customer #'.$this->customer->id.' (' .
                 $this->customer->info['fname'].' ' .
                 $this->customer->info['lname'].')');
    if ($this->customer->id)
       write_customer_activity($activity,$this->customer->id,$this->db);
    $this->status = NO_ERROR;
    return true;
}

function process_balance()
{
    $this->orders_record = orders_record_definition();
    $this->orders_record['id']['value'] = $this->id;
    $new_comments = trim(get_form_field('comments'));
    if ($new_comments) {
       if (! empty($this->info['comments']))
          $comments = $this->info['comments'];
       else $comments = '';
       if ($comments) $comments .= "\n" . $new_comments;
       else $comments = $new_comments;
       $this->orders_record['comments']['value'] = $comments;
    }
    $this->orders_record['balance_due']['value'] =
       intval($this->info['balance_due']) - intval($this->info['total']);

    $payment_record = payment_record_definition();
    $payment_record['parent']['value'] = $this->id;
    $payment_record['parent_type']['value'] = $this->order_type;
    $no_payment_info = null;
    $this->copy_payment_data($payment_record,$no_payment_info);
    if (! $this->db->insert('order_payments',$payment_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }

    if (! $this->db->update($this->table,$this->orders_record)) {
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       $this->errors['dberror'] = true;   return false;
    }

    $activity = 'Processed Balance Payment for '.$this->label .
                ' #'.$this->id;
    log_activity($activity.' for Customer #'.$this->customer_id.' (' .
                 $this->info['fname'].' '.$this->info['lname'].')');
    write_customer_activity($activity,$this->customer->id,$this->db);
    $this->status = NO_ERROR;
    return true;
}

function change_status($new_status)
{
    $query = 'update '.$this->table.' set status=? where id=?';
    $query = $this->db->prepare_query($query,$new_status,$this->id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    $this->info['status'] = $new_status;
    log_activity('Changed Status for '.$this->label.' #'.$this->id .
                 ' to '.$new_status);
    return true;
}

function load_all($limit=null)
{
    if (! isset($this->customer_id)) return null;
    $query = 'select * from '.$this->table.' where customer_id=? ' .
             'order by id desc';
    if ($limit) $query .= ' limit '.$limit;
    $query = $this->db->prepare_query($query,$this->customer_id);
    $orders = $this->db->get_records($query,'id');
    if (! $orders) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       return null;
    }
    $this->db->decrypt_records($this->table,$orders);
    $this->orders = $orders;
    return $orders;
}

function load($order_id)
{
    $this->billing_country = 1;
    $this->shipping_country = 1;

    if (empty($order_id)) {
       $this->error = 'Invalid '.$this->label.' ID';
       $this->errors['dberror'] = true;   return false;
    }

    $order = load_order($this->db,$order_id,$error_msg);
    if (! $order) {
       $this->error = $error_msg;   $this->errors['dberror'] = true;
       return false;
    }
    $this->id = $order_id;
    $this->info = $order->info;
    $this->items = $order->items;
    $this->billing = $order->billing;
    $this->shipping = $order->shipping;
    if (isset($this->billing['country']))
       $this->billing_country = $this->billing['country'];
    if (isset($this->shipping['country']))
       $this->shipping_country = $this->shipping['country'];
    $this->currency = $order->currency;
    $this->customer_id = $order->customer_id;
    $this->exchange_rate = $order->exchange_rate;
    return true;
}

function verify_access()
{
    global $login_cookie,$user_cookie;

    $admin_user = get_cookie($login_cookie);
    if ($admin_user) return;
    if (isset($this->customer_id)) {
       if ($this->customer_id == 0) {
          $referrer = getenv('HTTP_REFERER');
          if (strpos($referrer,'process-order.php') !== false) return;
       }
       $customer_id = get_cookie($user_cookie);
       if ($customer_id == $this->customer_id) return;
    }

    $this->errors['dberror'] = true;
    $this->error = 'Access Denied';
    $this->info = array();
    $this->items = array();
    $this->billing = array();
    $this->shipping = array();
}

function load_last_order()
{
    if (! isset($this->customer_id)) {
       $this->errors['dberror'] = true;
       $this->error = 'No customer information found';
       $this->status = DB_ERROR;   return false;
    }
    $query = 'select id from orders where customer_id=? ' .
             'order by id desc limit 1';
    $query = $this->db->prepare_query($query,$this->customer_id);
    $row = $this->db->get_record($query);
    if ($row) $order_id = $row['id'];
    else if (isset($this->db->error)) {
       $this->errors['dberror'] = true;
       $this->error = $this->db->error;   $this->status = DB_ERROR;
       return false;
    }
    else {
       $this->errors['dberror'] = true;
       $this->error = 'No orders found';
       $this->status = DB_ERROR;   return false;
    }
    $this->load($order_id);
    return true;
}

function get($field_name)
{
    if (! strncmp($field_name,'order_',6)) {
       $field_name = substr($field_name,6);
       if (($field_name == 'shipped_date') || ($field_name == 'weight') ||
           (substr($field_name,0,8) == 'shipping') ||
           (substr($field_name,0,8) == 'tracking')) {
          if (! isset($this->shipments))
             $this->shipments = load_order_shipments($this);
          if (! empty($this->shipments)) {
             $shipment_info = reset($this->shipments);
             if (isset($shipment_info[$field_name]))
                return $shipment_info[$field_name];
          }
       }
       else if ((substr($field_name,0,7) == 'payment') ||
                (substr($field_name,0,4) == 'card') ||
                (substr($field_name,0,5) == 'check')) {
          if (! isset($this->payments))
             $this->payments = load_order_payments($this);
          if (! empty($this->payments)) {
             $payment_info = reset($this->payments);
             if (isset($payment_info[$field_name]))
                return $payment_info[$field_name];
          }
       }
       if (isset($this->info[$field_name])) return $this->info[$field_name];
    }
    else if (! strncmp($field_name,'bill_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->billing_country == 1) || ($this->billing_country == 29) ||
              ($this->billing_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->billing_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->billing_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       else if ($field_name == 'country_code') {
          $country_info = get_country_info($this->billing_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_info['code'];
       }
       if (isset($this->billing) && isset($this->billing[$field_name]))
          return $this->billing[$field_name];
    }
    else if (! strncmp($field_name,'ship_',5)) {
       $field_name = substr($field_name,5);
       if ($field_name == 'province') {
          if (($this->shipping_country == 1) || ($this->shipping_country == 29) ||
              ($this->shipping_country == 43)) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->shipping_country != 43) return '';
          else $field_name = 'state';
       }
       else if ($field_name == 'country_name') {
          $country_name = get_country_name($this->shipping_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_name;
       }
       else if ($field_name == 'country_code') {
          $country_info = get_country_info($this->shipping_country,$this->db);
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return '';
          }
          return $country_info['code'];
       }
       if (isset($this->shipping[$field_name]))
          return $this->shipping[$field_name];
    }
    return '';
}

function delete()
{
    $query = 'select * from '.$this->table.' where id=?';
    $query = $this->db->prepare_query($query,$this->id);
    $order_info = $this->db->get_record($query);
    if (! delete_order_record($this->db,$order_info,$error,null,
                              $this->order_type)) {
       $this->error = $error;   $this->errors['dberror'] = true;
       return false;
    }
    log_activity('Deleted '.$this->label.' #' .
                 $order_record['id']['value']);
    return true;
}

function reprocess($cart)
{
    $features = get_cart_config_value('features',$this->db);
    foreach ($this->items as $id => $order_item) {
       $item_record = item_record_definition();
       foreach ($item_record as $field_name => $field_def) {
          if ($field_name == 'id') {}
          else if ($field_name == 'parent')
             $item_record['parent']['value'] = $cart->id;
          else $item_record[$field_name]['value'] = $order_item[$field_name];
       }
       if (! $this->db->insert($this->item_table,$item_record)) {
          $this->error = $this->db->error;   $this->status = DB_ERROR;
          $this->errors['dberror'] = true;   return false;
       }
       $item_id = $this->db->insert_id();
       $query = 'select * from order_attributes where parent=?';
       $query = $this->db->prepare_query($query,$id);
       $result = $this->db->query($query);
       if (! $result) {
          if (isset($this->db->error)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
          continue;
       }
       if ($this->db->num_rows($result) == 0) continue;
       while ($row = $this->db->fetch_assoc($result)) {
          $attributes_record = item_attributes_record_definition();
          foreach ($row as $field_name => $field_value) {
             if ($field_name == 'id') {}
             else if ($field_name == 'parent')
                $attributes_record['parent']['value'] = $item_id;
             else $attributes_record[$field_name]['value'] = $field_value;
          }
          if (! $this->db->insert('cart_attributes',$attributes_record)) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
       }
       $this->db->free_result($result);
    }

    if ($features & MAINTAIN_INVENTORY) {
       foreach ($this->items as $id => $order_item) {
          if (! $order_item['product_id']) continue;
          $query = 'update product_inventory set qty=ifnull(qty+?,+?) ' .
                   'where (parent=?)';
          if ((! isset($order_item['attributes'])) || ($order_item['attributes'] == ''))
             $lookup_attributes = null;
          else {
             $no_options = check_no_option_attributes($this->db,$order_item['product_id']);
             if ($no_options) $attributes = explode('|',$order_item['attributes']);
             else $attributes = explode('-',$order_item['attributes']);
             $lookup_attributes = build_lookup_attributes($this->db,
                $order_item['product_id'],$attributes,true,$no_options);
          }
          if ($lookup_attributes) {
             $lookup_attributes = reorder_attributes_by_id($lookup_attributes,
                $order_item['product_id'],$no_options,$this->db);
             $query .= ' and (attributes=?)';
             $query = $this->db->prepare_query($query,$order_item['qty'],
                         $order_item['qty'],$order_item['product_id'],
                         $lookup_attributes);
          }
          else {
             $query .= ' and ((attributes="") or isnull(attributes))';
             $query = $this->db->prepare_query($query,$order_item['qty'],
                         $order_item['qty'],$order_item['product_id']);
          }
          $this->db->log_query($query);
          $result = $this->db->query($query);
          if (! $result) {
             $this->error = $this->db->error;   $this->status = DB_ERROR;
             $this->errors['dberror'] = true;   return false;
          }
          if (using_linked_inventory($this->db,$features))
             update_linked_inventory($this->db,null,null,
                $order_item['product_id'],$lookup_attributes);
       }
    }

    if (! $this->delete()) return false;

    return true;
}

function get_rma_field($field_name,$order_field_name)
{
    $field_value = get_form_field($field_name);
    if ($field_value !== null) return trim($field_value);
    return $this->get($order_field_name);
}

function parse_rma_fields($init_arrays = true)
{
    require_once 'rmas-common.php';

    if ($init_arrays) {
       $this->rma_record = rma_record_definition();
       $this->rma_info = array();
       $this->rma_item_record = rma_item_record_definition();
       $this->rma_items = array();
    }

    $form_fields = get_form_fields();
    if (isset($form_fields)) {
       $country = get_form_field('country');
       $this->rma_country = $country;
       foreach ($form_fields as $field_name => $field_value) {
          $field_value = trim($field_value);
          if ($field_name == 'opened') {
             if ($field_value = 'on') $field_value = 1;
             else $field_value = 0;
          }
          if ($field_name == 'id') $field_name = 'order_id';
          if (substr($field_name,0,8) == 'item_id_') continue;
          if (substr($field_name,0,9) == 'item_qty_') continue;
          if (substr($field_name,0,11) == 'return_qty_') continue;
          if (substr($field_name,0,7) == 'return_') {
             if ($field_value != 'on') continue;
             $index = substr($field_name,7);
             $item_id = get_form_field('item_id_'.$index);
             $return_qty = intval(get_form_field('return_qty_'.$index));
             $this->rma_items[$item_id] = $return_qty;
             continue;
          }
          if (! isset($this->rma_record[$field_name])) continue;
          $this->rma_info[$field_name] = $field_value;
          $this->rma_record[$field_name]['value'] = $field_value;
       }
    }
}

function check_required_rma_fields($field_list)
{
    foreach ($field_list as $field_name) {
       if ($field_name == 'province') {
          if (($this->rma_country == 1) || ($this->rma_country == 29) ||
              ($this->rma_country == 43))
             continue;
          else $field_name = 'state';
       }
       else if ($field_name == 'canada_province') {
          if ($this->rma_country != 43) continue;
          else $field_name = 'state';
       }
       else if ($field_name == 'zipcode') {
          if ($this->rma_country != 1) continue;
       }
       if ((! isset($this->rma_info)) ||
           (! isset($this->rma_info[$field_name])) ||
           ($this->rma_info[$field_name] == ''))
          $this->errors[$field_name] = true;
    }
    if (isset($this->items)) {
       $index = 0;
       foreach ($this->items as $item_id => $order_item) {
          if (isset($this->rma_items[$item_id])) {
             $return_qty = $this->rma_items[$item_id];
             if (($return_qty <= 0) || ($return_qty > $order_item['qty']))
                $this->errors['return_qty_'.$index] = true;
          }
          $index++;
       }
    }
}

function create_rma()
{
    if ($this->system_disabled) {
       $this->error = 'System is temporarily unavailable, please try again later';
       $this->errors['dberror'] = true;   return false;
    }
    $email = $this->rma_info['email'];
    set_remote_user($email);
    $this->rma_record['status']['value'] = (string) 0;
    $this->rma_record['request_date']['value'] = time();
    if (! $this->db->insert('rmas',$this->rma_record)) {
       $this->error = $this->db->error;   $this->errors['dberror'] = true;
       return false;
    }
    $this->rma_id = $this->db->insert_id();
    $this->rma_item_record['parent']['value'] = $this->rma_id;
    foreach ($this->rma_items as $item_id => $return_qty) {
       $this->rma_item_record['item_id']['value'] = $item_id;
       $this->rma_item_record['qty']['value'] = $return_qty;
       if (! $this->db->insert('rma_items',$this->rma_item_record)) {
          $this->error = $this->db->error;   $this->errors['dberror'] = true;
          return false;
       }
    }
    log_activity('Created New RMA #'.$this->rma_id.' for ' .
                 $this->label.' #' .
                 $this->rma_record['order_id']['value'] .
                 $this->rma_record['email']['value'] .
                 ' ('.$this->rma_record['fname']['value'].' ' .
                 $this->rma_record['lname']['value'].')');
    return true;
}

function process_errors($field_list,$template)
{
    foreach ($field_list as $field_name => $error_msg) {
       if (isset($this->errors[$field_name])) {
          $html = str_replace('[msg]',$error_msg,$template);
          if (isset($this->error))
             $html = str_replace('[error]',$this->error,$html);
          print $html;
       }
    }
}

function write_process_order_footer_text()
{
    global $eye4fraud_siteid;

    if (isset($eye4fraud_siteid)) {  /* Eye4Fraud Device Fingerprinting Code */
       print '<iframe width="1" height="1" src="https://ssl.kaptcha.com/' .
             'logo.htm?m=200300&s='.$eye4fraud_siteid.$this->id .
             '" style="display:none;">'."\n";
       print '<img width="1" height="1" src="https://ssl.kaptcha.com/' .
             'logo.gif?m=200300&s='.$eye4fraud_siteid.$this->id.'">'."\n";
       print "</iframe>\n";
    }
    if (function_exists('write_process_order_footer_text'))
       write_process_order_footer_text($this);
}

};

function send_cache_headers()
{
    session_cache_limiter('private_no_expire, must-revalidate');
}

function is_validator()
{
    $user_agent = getenv('HTTP_USER_AGENT');
    if (! $user_agent) return false;
    if (substr($user_agent,0,13) == 'W3C_Validator') return true;
    return false;
}

function is_crawler()
{
    $user_agent = getenv('HTTP_USER_AGENT');
    if (! $user_agent) return false;
    if (preg_match('/bot|crawl|slurp|spider/i',$user_agent)) return true;
    return false;
}

function crawler_noindex()
{
    header('X-Robots-Tag: noindex');
    print '<html><head><meta name="robots" content="noindex" /></head>';
    print '<body></body></html>';
}

function clear_guest_cart()
{
    global $cart_cookie;

    $cart_id = get_cookie($cart_cookie);
    if ((! $cart_id) || (! is_numeric($cart_id))) return;
    $db = new DB;
    $query = 'select flags from cart where id=?';
    $query = $db->prepare_query($query,$cart_id);
    $row = $db->get_record($query);
    if (! $row) return;
    $flags = $row['flags'];
    if (! ($flags & GUEST_CHECKOUT)) return;
    $flags &= ~(GUEST_CHECKOUT|SAVED_GUEST_INFO);
    $query = 'update cart set flags=?,cart_data=null where id=?';
    $query = $db->prepare_query($query,$flags,$cart_id);
    $db->log_query($query);
    $db->query($query);
}

function save_cart_variables()
{
    global $cart_cookie;

    $shipping_method = get_form_field('shipping_method');
    if (! $shipping_method) return;

    $cart_id = get_cookie($cart_cookie);
    if (! is_numeric($cart_id)) return;
    $db = new DB;
    $query = 'update cart set shipping_method=? where id=?';
    $query = $db->prepare_query($query,$shipping_method,$cart_id);
    $db->log_query($query);
    $db->query($query);
}

function write_cart_variables()
{
    global $enable_guest_checkout,$checking_out,$guest_checkout;
    global $guest_mailing_flag,$mailchimp_lists;

    print "  <script type=\"text/javascript\">\n";
    print "    city_label = \"".T('City')."\";\n";
    print "    parish_label = \"".T('Parish')."\";\n";
    print "    zip_label = \"".T('Zip Code')."\";\n";
    print "    postal_label = \"".T('Postal Code')."\";\n";
    if (isset($enable_guest_checkout) && $enable_guest_checkout)
       print "    enable_guest_checkout = true;\n";
    if (isset($checking_out) && $checking_out)
       print "    checking_out = true;\n";
    if (isset($guest_checkout) && $guest_checkout)
       print "    guest_checkout_flag = true;\n";
    if (isset($guest_mailing_flag) && $guest_mailing_flag)
       print "    guest_mailing_flag = true;\n";
    else if (isset($mailchimp_lists,$mailchimp_lists['guest_mailing_list']))
       print "    guest_mailing_flag = true;\n";
    print "  </script>\n";
}

function set_cart_remote_user($cart_id,$customer_id,$db)
{
    global $user_cookie;
    global $cart_cookie;

    if ($customer_id == null) $customer_id = get_cookie($user_cookie);
    if (! is_numeric($customer_id)) $customer_id = null;
    if ($customer_id == null) {
       if ($cart_id == null) {
          $cart_id = get_cookie($cart_cookie);
          if (! is_numeric($cart_id)) $cart_id = null;
       }
       if ($cart_id == null) return;
       $query = 'select customer_id from cart where id=?';
       $query = $db->prepare_query($query,$cart_id);
       $row = $db->get_record($query);
       if (! $row) return;
       $customer_id = $row['customer_id'];
    }
    if (! $customer_id) return;
    $query = 'select email from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $row = $db->get_record($query);
    if (! $row) {
       set_remote_user($customer_id);   return;
    }
    $db->decrypt_record('customers',$row);
    set_remote_user($row['email']);
}

function get_cart_info($db=null)
{
    global $cart_cookie,$user_cookie,$hide_cart_prices;

    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.';
    else print 'var cart_';
    print 'info={';
    $cart_id = get_cookie($cart_cookie);
    if ($cart_id) {
       if (! $db) $db = new DB;
       $cart = new Cart($db,null,null,true);
       if (! empty($cart->id)) $cart_items = $cart->load();
       else $cart_items = null;
       $customer_id = get_cookie($user_cookie);
       if (! $customer_id) $customer_id = 0;
       $customer = new Customer($db,$customer_id,true);
       $customer->load();
       if (function_exists('check_shipping_module')) {
          $null_order = null;
          check_shipping_module($cart,$customer,$null_order);
       }
       $cart->load_shipping_options($customer);
       $shipping_method = get_form_field('shipping_method');
       if ($shipping_method) $shipping_method = explode('|',$shipping_method);
       $shipping_amount = -1;
       foreach ($cart->shipping_options as $shipping_option) {
          if ($shipping_method && ($shipping_method[0] == $shipping_option[0])
              && ($shipping_method[1] == $shipping_option[1])) {
             $shipping_amount = floatval($shipping_option[2]);   break;
          }
          else if ($shipping_amount == -1)
             $shipping_amount = floatval($shipping_option[2]);
          else if ($shipping_option[4]) {
             $shipping_amount = floatval($shipping_option[2]);   break;
          }
       }
       $cart->set('shipping',$shipping_amount);
       $cart->calculate_tax($customer);
       if (! empty($cart->info['currency']))
          $currency = $cart->info['currency'];
       else $currency = 'USD';
       print 'currency:"'.$currency.'"';
       if (isset($cart->info['subtotal'])) {
          $subtotal = floatval($cart->info['subtotal']);
          $total = $subtotal;
          if (empty($hide_cart_prices)) print ',subtotal:'.$subtotal;
          else print ',subtotal:\'\'';
       }
       else $total = 0;
       if (isset($cart->info['tax'])) {
          $tax = floatval($cart->info['tax']);
          $total += $tax;
          if (empty($hide_cart_prices)) print ',tax:'.$tax;
          else print ',tax:\'\'';
       }
       if (isset($cart->info['coupon_amount'])) {
          if (isset($cart->info['coupon_code']))
             print ',coupon_code:"'.$cart->info['coupon_code'].'"';
          $coupon_amount = floatval($cart->info['coupon_amount']);
          $total -= $coupon_amount;
          if (empty($hide_cart_prices)) print ',coupon_amount:'.$coupon_amount;
          else print ',coupon_amount:\'\'';
       }
       if (isset($cart->info['gift_amount'])) {
          if (isset($cart->info['gift_code']))
             print ',gift_code:"'.$cart->info['gift_code'].'"';
          $gift_amount = floatval($cart->info['gift_amount']);
          $total -= $gift_amount;
          if (empty($hide_cart_prices)) print ',gift_amount:'.$gift_amount;
          else print ',gift_amount:\'\'';
       }
       if (isset($cart->info['discount_amount'])) {
          if (isset($cart->info['discount_name']))
             print ',discount_name:"'.$cart->info['discount_name'].'"';
          $discount_amount = floatval($cart->info['discount_amount']);
          $total -= $discount_amount;
          if (empty($hide_cart_prices))
             print ',discount_amount:'.$discount_amount;
          else print ',discount_amount:\'\'';
       }
       if (isset($cart->info['fee_amount'])) {
          if (isset($cart->info['fee_name']))
             print 'fee_name:"'.$cart->info['fee_name'].'"';
          $fee_amount = floatval($cart->info['fee_amount']);
          $total += $fee_amount;
          if (empty($hide_cart_prices)) print ',fee_amount:'.$fee_amount;
          else print ',fee_amount:\'\'';
       }
       if ($shipping_amount != -1) {
          $cart->info['shipping'] = $shipping_amount;
          $total += $shipping_amount;
          if (empty($hide_cart_prices)) print ',shipping:'.$shipping_amount;
          else print ',shipping:\'\'';
       }
       if (empty($hide_cart_prices)) print ',total:'.$total;
       else print ',total:\'\'';
    }
    print '};';
}

function get_cart_items($db=null)
{
    global $cart_cookie;

    $cart_id = get_cookie($cart_cookie);
    if ($cart_id) {
       if (! $db) $db = new DB;
       if ((! $db) || isset($db->error)) $num_items = 0;
       else {
          if (! is_numeric($cart_id)) return;
          $query = 'select qty,flags from cart_items where parent=?';
          $query = $db->prepare_query($query,$cart_id);
          $rows = $db->get_records($query);
          $num_items = 0;
          if ($rows) foreach ($rows as $row) {
             if ($row['flags'] & 1) $num_items++;
             else $num_items += $row['qty'];
          }
       }
    }
    else $num_items = 0;
    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.numItems';
    else print 'var num_items';
    print '='.$num_items.';';
}

function get_cart_list($db=null)
{
    global $cart_cookie,$hide_cart_prices;

    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.';
    else print 'var cart_';
    print 'data={';
    $cart_id = get_cookie($cart_cookie);
    if ($cart_id) {
       if (! $db) $db = new DB;
       $cart = new Cart($db,null,null,true);
       if (! empty($cart->id)) $cart_items = $cart->load();
       else $cart_items = null;
       $first_item = true;
       if ($cart_items) $cart_items = $cart->load_images();
       if ($cart_items) $cart_items = $cart->load_product_urls();
       if ($cart_items) foreach ($cart_items as $id => $cart_item) {
          if ($first_item) $first_item = false;
          else print ',';
          print $id.':{id:'.$id.',';
          print 'product_id:'.$cart_item['product_id'].',';
          $product_name = get_html_product_name($cart_item['product_name'],
             GET_PROD_CART,$cart,$cart_item);
          $product_name = str_replace("'","\\'",$product_name);
          $product_name = preg_replace('/[\x00-\x1F\x80-\xFF]/','',$product_name);
          print "product_name:'".$product_name."',";
          print "image:'".(isset($cart_item['image_filename'])?
                           $cart_item['image_filename']:'no-image-found.jpg') .
                           "',";
          print "url:'".(isset($cart_item['url'])?
                             $cart_item['url']:'')."',";
          $attributes = get_html_attributes($cart_item['attribute_array'],
             GET_ATTR_CART,$cart,$cart_item);
          $attributes = str_replace("'","\\'",$attributes);
          $attributes = preg_replace('/[\x00-\x1F\x80-\xFF]/','',$attributes);
          print "attributes:'".$attributes."',";
          print 'related_id:';
          if ($cart_item['related_id'] !== null)
             print $cart_item['related_id'];
          else print "''";
          print ',';
          if (! empty($cart_item['reorder_frequency']))
             print 'reorder_frequency:'.$cart_item['reorder_frequency'].',';
          $item_total = $cart_item['price'];
          if (isset($cart_item['attribute_array'])) {
             $attribute_array = $cart_item['attribute_array'];
             foreach ($attribute_array as $attribute)
                $item_total += $attribute['price'];
          }
          if (empty($hide_cart_prices)) print 'price:'.$item_total.',';
          else print 'price:\'\',';
          print 'qty:'.$cart_item['qty'].',';
          if (empty($hide_cart_prices))
             print 'total:'.get_item_total($cart_item).',';
          else print 'total:\'\',';
          print 'flags:';
          if ($cart_item['flags'] !== null) print $cart_item['flags'];
          else print '0';
          print '}';
       }
    }
    print '};';
}

function get_login_status()
{
    global $user_cookie;

    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.loginStatus';
    else print 'var login_status';
    $customer_id = get_cookie($user_cookie);
    if ($customer_id) print '=true;';
    else print '=false;';
}

function get_cart_cookie()
{
    global $cart_cookie;

    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.';
    else print 'var cart_';
    print 'cookie="'.$cart_cookie.'";';
}

function get_price_symbol($db=null)
{
    global $cart_cookie,$default_currency;

    $cart_id = get_cookie($cart_cookie);
    if ($cart_id) {
       $cart = new Cart($db,null,null,true);
       $currency = $cart->currency;
    }
    else if (isset($default_currency)) $currency = $default_currency;
    else $currency = 'USD';
    if ($currency == 'EUR') {
       if (get_form_field('html')) $symbol = '&#8364;';
       else $symbol = "\xE2\x82\xAc";
    }
    else if ($currency == 'CAD') $symbol = 'Can$';
    else if ($currency == 'HUF') $symbol = 'Ft';
    else $symbol = '$';
    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.priceSymbol';
    else print 'var price_symbol';
    print '="'.$symbol.'";';
}

function get_cart_features($db=null)
{
    global $enable_inventory_available,$hide_off_sale_inventory;

    $features = get_cart_config_value('features',$db);
    $namespace = get_form_field('namespace');
    if ($namespace) print $namespace.'.cartFeatures';
    else print 'var cartFeatures';
    $features = $features * 1;
    print '='.$features.';';
    if ($namespace) print $namespace.'.inventoryAvailable';
    else print 'var inventoryAvailable';
    if (! empty($enable_inventory_available)) print '=true;';
    else print '=false;';
    if ($namespace) print $namespace.'.hideOffSaleInventory';
    else print 'var hideOffSaleInventory';
    if (! empty($hide_off_sale_inventory)) print '=true;';
    else print '=false;';
}

function write_cart_button($name,$image,$label,$onclick=null,$styles=null,
                           $class=null)
{
    global $cart_button_extension,$cart_button_classes;

    if (! isset($cart_button_extension)) $cart_button_extension = 'gif';
    if ($cart_button_extension == 'css') {
       if ($name) {
          print '<input type="submit" class="';
          if (isset($cart_button_classes)) {
             if (is_array($cart_button_classes)) {
                if (isset($cart_button_classes[$name]))
                   $button_class = $cart_button_classes[$name];
                else if (isset($cart_button_classes['']))
                   $button_class = $cart_button_classes[''];
                else $button_class = '';
             }
             else $button_class = $cart_button_classes;
             print $button_class;
          }
          else print 'cart_button';
          if ($class) print ' '.$class;
          print '" id="'.$name.'" name="'.$name.'" value="'.T($label).'"';
          if ($styles) print ' style="'.$styles.'"';
          if ($onclick) print ' onClick="'.$onclick.'"';
          print '>';
       }
       else {
          print '<span class="';
          if ($class) print $class;
          else print 'cart_button';
          print '"';
          if ($styles) print ' style="'.$styles.'"';
          if ($onclick) print ' onClick="'.$onclick.'"';
          print '>'.T($label).'</span>';
       }
    }
    else if ($name) {
       print '<input type="image" id="'.$name.'" name="'.$name .
             '" src="cartimages/'.$image.'.'.$cart_button_extension.'"';
       if ($class) print ' class="'.$class.'"';
       if ($styles) print ' style="'.$styles.'"';
       if ($onclick) print ' onClick="'.$onclick.'"';
       print '>';
    }
    else {
       print '<img src="cartimages/'.$image.'.'.$cart_button_extension .
             '" border="0" alt="'.$label.'" title="'.$label.'"';
       if ($class) print ' class="'.$class.'"';
       if ($styles) print ' style="'.$styles.'"';
       if ($onclick) print ' onClick="'.$onclick.'"';
       print '>';
    }
}

function write_cart_anchor_classes($class=null,$silent=false)
{
    global $cart_anchor_button_classes;

    $output = '';
    if (! empty($cart_anchor_button_classes)) {
       $output .= $cart_anchor_button_classes;
       if (! empty($class)) $output .= ' ';
    }
    if (! empty($class)) $output .= $class;
    if (! $silent) print $output;
    return $output;
}

function process_update_cart_index_cart(&$cart,&$cart_items)
{
   if (function_exists('update_cart_index_cart'))
      update_cart_index_cart($cart,$cart_items);
    require_once __DIR__.'/../engine/modules.php';
    call_module_event('update_cart_index_cart',array(&$cart,&$cart_items));
}

function write_checkout_button($cart,$continue_checkout_label)
{
    require_once __DIR__.'/../engine/modules.php';
    if (! call_module_event('write_checkout_button',
             array(&$cart,$continue_checkout_label),null,true,true))
       write_cart_button('Checkout','continue-checkout',
                         $continue_checkout_label);
}

function process_start_checkout()
{
    call_payment_event('start_checkout');
    require_once __DIR__.'/../engine/modules.php';
    call_module_event('start_checkout',array());
}

function process_customer_logout(&$customer)
{
    if (function_exists('custom_customer_logout'))
       custom_customer_logout();
    require_once __DIR__.'/../engine/modules.php';
    call_module_event('customer_logout',array(&$customer));
}

function log_cart_error($error_msg)
{
    global $activity_log,$login_cookie;

    $path_parts = pathinfo($activity_log);
    $cart_error_log = $path_parts['dirname'].'/checkout.log';
    $error_file = @fopen($cart_error_log,'at');
    if ($error_file) {
       $remote_user = getenv('REMOTE_USER');
       if (! $remote_user) $remote_user = get_cookie($login_cookie);
       if ((! $remote_user) && isset($_SERVER['REMOTE_ADDR']))
          $remote_user = $_SERVER['REMOTE_ADDR'];
       fwrite($error_file,$remote_user.' ['.date('D M d Y H:i:s').'] ' .
              $error_msg."\n");
       fclose($error_file);
    }
}

function log_cart_errors($errors,$error=null)
{
    global $log_cart_errors_enabled;

    if (! $log_cart_errors_enabled) return;
    if (! $errors) return;
    if (count($errors) == 0) return;
    foreach ($errors as $error_code => $error_value) {
       $error_msg = 'Cart Error: '.$error_code.' = ';
       if ($error_value === true) {
          if ($error) $error_msg .= $error;
          else $error_msg .= 'true';
       }
       else $error_msg .= $error_value;
       log_cart_error($error_msg);
    }
}

function get_admin_user()
{
    global $login_cookie;

    $admin_user = get_cookie($login_cookie);
    if (! $admin_user) $admin_user = '';
    return $admin_user;
}

function load_login_accounts()
{
    global $account_label,$user_cookie;

    if (! isset($account_label)) $account_label = 'Account';
    $db = new DB;
    $email = get_form_field('email');
    if ($email) {
       $query = 'select id from customers where email=?';
       $query = $db->prepare_query($query,$email);
       $row = $db->get_record($query);
       if ((! $row) || (! $row['id'])) {
          if (isset($db->error))
             http_response(422,'Database Error: '.$db->error);
          return;
       }
       $customer_id = $row['id'];
    }
    else {
       $customer_id = get_cookie($user_cookie);
       if (! $customer_id) return;
    }
    $query = 'select a.id,a.name,a.company from accounts a join ' .
             'customer_accounts ca on ca.account_id=a.id where ' .
             'ca.customer_id=? order by a.name';
    $query = $db->prepare_query($query,$customer_id);
    $accounts = $db->get_records($query);
    $no_account = array('id'=>0,'name'=>'No '.$account_label,'company'=>'');
    $accounts = array_merge(array($no_account),$accounts);
    print json_encode($accounts);
}

function get_coupon_info()
{
    $coupon_code = get_form_field('code');
    if (! $coupon_code) {
       http_response(406,'Coupon Code is required');   return;
    }
    $db = new DB;
    $query = 'select * from coupons where coupon_code=?';
    $query = $db->prepare_query($query,$coupon_code);
    $row = $db->get_record($query);
    if ((! $row) || ($row['coupon_type'] == 4)) {
       if (isset($db->error))
          http_response(422,'Database Error: '.$db->error);
       else http_response(410,'Coupon Code not found');
       return;
    }
    print json_encode($row);
}

function set_multisite_cookies()
{
    $form_fields = get_form_fields();
    foreach ($form_fields as $cookie_name => $cookie_value) {
       if ($cookie_name == 'ajaxcmd') continue;
       if (! $cookie_value) {
          if (headers_sent($file,$line)) {
             log_error('Unable to delete cookie '.$cookie_name .
                       ' since headers were already sent by ' .
                       $file.' line '.$line);
             log_request_data();
          }
          else setcookie($cookie_name,0,time() - 3600,'/');
       }
       else {
          $value_parts = explode('|',$cookie_value);
          if (count($value_parts) != 2) continue;
          $cookie_value = $value_parts[0];   $expires = $value_parts[1];
          if (headers_sent($file,$line)) {
             log_error('Unable to set cookie '.$cookie_name.' to ' .
                       $cookie_value.' since headers were already sent by ' .
                       $file.' line '.$line);
             log_request_data();
          }
          else setcookie($cookie_name,$cookie_value,time() + $expires,'/');
       }
    }
    $image_filename = '../cartengine/images/blank.gif';
    $image_file = @fopen($image_filename,'rb');
    if ($image_file) {
       $image = fread($image_file,filesize($image_filename));
       fclose($image_file);
    }
    else $image = null;
    if (headers_sent($file,$line)) {
       log_error('Unable to send Content-Type header since headers were ' .
                 'already sent by '.$file.' line '.$line);
       log_request_data();
    }
    else header('Content-Type: image/gif');
    print $image;
}

function write_upsell_content()
{
    global $base_url,$cart_cookie,$cart_upsell_url,$checkout_upsell_url;

    $page = basename($_SERVER['PHP_SELF']);
    if ($page == 'index.php') {
       if (! isset($cart_upsell_url)) return;
       $url = $cart_upsell_url;
    }
    else if ($page == 'checkout.php') {
       if (! isset($checkout_upsell_url)) return;
       $url = $checkout_upsell_url;
    }
    else return;
    $cart_id = get_cookie($cart_cookie);
    if (! $cart_id) return;
    if (substr($url,0,4) != 'http') $url = $base_url.$url;
    $url .= '&CartID='.$cart_id;
    require_once '../engine/http.php';
    $http = new HTTP($url,'GET');
    print $http->call();
}

function init_cart_page($cart_page)
{
    global $user_cookie,$account_cookie,$user_cookie_expiration;

    if (headers_sent($file,$line)) {
       log_error('Unable to send initial cart headers since headers were ' .
                 'already sent by '.$file.' line '.$line);
       log_request_data();
    }
    else {
       header('Cache-Control: no-cache,no-store');
       header('Expires: 0');
       header('Pragma: no-cache');
    }

    if (! empty($user_cookie_expiration)) {
       $cookie_value = get_cookie($user_cookie);
       if ($cookie_value) {
          if (headers_sent($file,$line)) {
             log_error('Unable to refresh cookie '.$cookie_name .
                       ' since headers were already sent by ' .
                       $file.' line '.$line);
             log_request_data();
          }
          else setcookie($user_cookie,$cookie_value,time() +
                         $user_cookie_expiration,'/');
       }
       if (! empty($account_cookie)) {
          $cookie_value = get_cookie($account_cookie);
          if ($cookie_value) {
             if (headers_sent($file,$line)) {
                log_error('Unable to refresh cookie '.$cookie_name .
                          ' since headers were already sent by ' .
                          $file.' line '.$line);
                log_request_data();
             }
             else setcookie($account_cookie,$cookie_value,time() +
                            $user_cookie_expiration,'/');
          }
       }
    }

    if (function_exists('custom_init_cart_page'))
       custom_init_cart_page($cart_page);
}

if (! function_exists('inroads_debug')) {
   function inroads_debug()
   {
       $admin_user = get_admin_user();
       if ($admin_user == 'severy') return true;
       if ($admin_user == 'patel') return true;
       return false;
   }
}

$ajaxcmd = get_form_field('ajaxcmd');
if ($ajaxcmd == 'getcartinfo') {
   get_cart_info();   DB::close_all();
}
else if ($ajaxcmd == 'getcartitems') {
   get_cart_items();   DB::close_all();
}
else if ($ajaxcmd == 'getcartlist') {
   get_cart_list();   DB::close_all();
}
else if ($ajaxcmd == 'cartcookie') {
   get_cart_cookie();   DB::close_all();
}
else if ($ajaxcmd == 'loadaccounts') {
   load_login_accounts();   DB::close_all();
}
else if ($ajaxcmd == 'getcoupon') {
   get_coupon_info();   DB::close_all();
}
else if ($ajaxcmd == 'setcookies') {
   set_multisite_cookies();   DB::close_all();
}

$jscmd = get_form_field('jscmd');
if ($jscmd == 'getcartinfo') {
   write_javascript_header();
   get_cart_info();
   DB::close_all();
}
else if ($jscmd == 'getcartitems') {
   write_javascript_header();
   get_cart_items();
   DB::close_all();
}
else if ($jscmd == 'getcartlist') {
   write_javascript_header();
   get_cart_list();
   DB::close_all();
}
else if ($jscmd == 'cartcookie') {
   write_javascript_header();
   get_cart_cookie();
   DB::close_all();
}
else if ($jscmd == 'loginstatus') {
   write_javascript_header();
   get_login_status();
   DB::close_all();
}
else if ($jscmd == 'pricesymbol') {
   write_javascript_header();
   get_price_symbol();
   DB::close_all();
}
else if ($jscmd == 'cartfeatures') {
   write_javascript_header();
   get_cart_features();
   DB::close_all();
}
else if ($jscmd && (strpos($jscmd,'|') !== false)) {
   $jscmds = explode('|',$jscmd);
   $db = new DB;
   write_javascript_header();
   foreach ($jscmds as $jscmd) {
      switch ($jscmd) {
         case 'getcartinfo': get_cart_info($db);   break;
         case 'getcartitems': get_cart_items($db);   break;
         case 'getcartlist': get_cart_list($db);   break;
         case 'cartcookie': get_cart_cookie();   break;
         case 'loginstatus': get_login_status();   break;
         case 'pricesymbol': get_price_symbol($db);   break;
         case 'cartfeatures': get_cart_features($db);   break;
      }
   }
   $db->close();
}

?>
