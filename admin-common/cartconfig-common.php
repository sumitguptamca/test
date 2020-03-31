<?php
/*
                Inroads Shopping Cart - Common Cart Config Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once 'utility.php';

/* Cart Option Table IDs */

define('PRODUCT_STATUS',0);
define('CATEGORY_STATUS',1);
define('CUSTOMER_STATUS',2);
define('ORDER_STATUS',3);
define('ACCOUNT_STATUS',4);
define('RMA_STATUS',5);
define('RMA_REASONS',6);
define('ORDER_SOURCES',7);
define('QUOTE_STATUS',8);
define('INVOICE_STATUS',9);
define('SALESORDER_STATUS',10);

/* Cart Config Notification Flags */

define('NOTIFY_NEW_ORDER_ADMIN',1);
define('NOTIFY_NEW_ORDER_CUST',2);
define('NOTIFY_BACK_ORDER',4);
define('NOTIFY_SHIPPED',8);
define('NOTIFY_ORDER_DECLINED',16);
define('NOTIFY_NEW_CUSTOMER',32);
define('NOTIFY_NEW_CUSTOMER_ADMIN',64);
define('NOTIFY_LOW_QUANTITY',128);
define('NOTIFY_NEW_RMA_ADMIN',256);
define('NOTIFY_NEW_RMA_CUST',512);
define('NOTIFY_RMA_APPROVED',1024);
define('NOTIFY_RMA_DENIED',2048);
define('NOTIFY_RMA_COMPLETED',4096);

/* Cart Config Feature Flags */

define('NUM_FEATURES',30);
define('MAINTAIN_INVENTORY',1);
define('DROP_SHIPPING',2);
define('USE_PART_NUMBERS',4);
define('USE_COUPONS',8);
define('ALLOW_REORDERS',16);
define('REGULAR_PRICE_PRODUCT',32);
define('REGULAR_PRICE_INVENTORY',64);
define('REGULAR_PRICE_BREAKS',128);
define('LIST_PRICE_PRODUCT',256);
define('LIST_PRICE_INVENTORY',512);
define('SALE_PRICE_PRODUCT',1024);
define('SALE_PRICE_INVENTORY',2048);
define('WEIGHT_ITEM',4096);
define('WEIGHT_DEFAULT',8192);
define('ORDER_PREFIX',16384);
define('HIDE_OUT_OF_STOCK',32768);
define('ALLOW_BACKORDERS',65536);
define('SUB_PRODUCT_COLLECTION',131072);
define('SUB_PRODUCT_RELATED',262144);
define('PRODUCT_COST_PRODUCT',524288);
define('PRODUCT_COST_INVENTORY',1048576);
define('GIFT_CERTIFICATES',2097152);
define('QTY_DISCOUNTS',4194304);
define('ORDER_PREFIX_ID',8388608);
define('ORDER_BASE_ID',16777216);
define('MIN_ORDER_QTY',33554432);
define('INVENTORY_BACKORDERS',67108864);
define('QTY_PRICING',134217728);
define('MIN_ORDER_QTY_PRODUCT',268435456);
define('MIN_ORDER_QTY_BOTH',536870912);

/* Product Flags */

define('NUM_PRODUCT_FLAGS',12);
define('FEATURED',1);
define('DYNAMIC',2);
define('NOSEARCH',4);
define('UNIQUEURL',8);
define('NO_QUANTITY',16);
define('NEW_PRODUCT',32);
define('ON_SALE',64);
define('REORDER_PRODUCT',128);
define('HIDE_NAME_IN_ORDERS',256);
define('ON_ACCOUNT',512);
define('NO_COUPONS',1024);
define('NO_ACCOUNT_DISCOUNTS',2048);

/* Category Flags */

define('NUM_CATEGORY_FLAGS',5);
define('NO_BREADCRUMBS',1);
define('NO_HYPERLINK',2);
define('HIDE_DESCRIPTION',4);
define('NO_SEO_CATEGORY',8);
define('HIDE_SHOW_HIDE_LINK',16);

/* Order Flags */

define('AUTO_REORDER_FLAG',1);

/* Order Item Flags */

define('EDIT_ORDER_ITEM_NAME',1);
define('EDIT_ORDER_ITEM_PART_NUMBER',2);
define('EDIT_ORDER_ITEM_COST',4);
define('EDIT_ORDER_ITEM_PRICE',8);

/* Coupon Flags */

define('COUPON_SELECTED_PRODUCTS',1);
define('COUPON_SELECTED_CUSTOMERS',2);
define('COUPON_ADD_TO_SCHEDULE',4);
define('COUPON_ONLY_REGISTERED',8);
define('COUPON_SAME_LOWER',16);
define('COUPON_SPECIAL_OFFER',32);
define('COUPON_FREE_SHIPPING',64);
define('COUPON_EXCLUDE_PRODUCTS',128);

if (! isset($cart_config_tabs)) {
   $cart_config_tabs = array('settings' => 'Settings','features' => 'Features',
      'options' => 'Options','countries' => 'Countries','states' => 'States',
      'shipping' => 'Shipping','payment' => 'Payment','seo' => 'SEO',
      'shopping' => 'Shopping','analytics' => 'Analytics');
}

$cart_config_values = null;

$shipping_module_labels = array('ups'=>'UPS','usps'=>'USPS',
   'endicia'=>'Endicia','fedex'=>'FedEx','g4si'=>'G4SI','dhl'=>'DHL',
   'stamps'=>'USPS','manual_shipping'=>'Shipping','manual'=>'Manual');

$us_territory_states = array('AS','FM','GU','MH','MP','PW','PR','VI');

$canada_provinces = array('AB' => 'Alberta','BC' => 'British Columbia',
   'MB' => 'Manitoba','NB' => 'New Brunswick','NF' => 'Newfoundland',
   'NS' => 'Nova Scotia','NT' => 'Northwest Territories','NU' => 'Nunavut',
   'ON' => 'Ontario','PE' => 'Prince Edward Island','QC' => 'Quebec',
   'SK' => 'Saskatchewan','YT' => 'Yukon Territory');

if (! isset($order_source_table_id)) $order_source_table_id = 7;

if (! isset($available_card_types))
   $available_card_types = array('amex' => 'Amex','visa' => 'Visa',
                                 'master' => 'MasterCard',
                                 'discover' => 'Discover');

if (! isset($custom_account_product_prices))
   $custom_account_product_prices = false;
if (! isset($account_product_prices)) {
   if ($custom_account_product_prices) $account_product_prices = true;
   else $account_product_prices = false;
}

global $shipping_modules,$payment_modules;
$shipping_modules = null;
$payment_modules = null;

function load_cart_config_values($db=null,$show_error=true)
{
    global $enable_multisite,$website_id,$cart_config_values;

    if (! $db) $db = new DB;
    $cart_config_values = $db->get_records('select * from cart_config',
                                           'config_name','config_value');
    if ((! $cart_config_values) && isset($db->error)) {
       if ($show_error) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    if (! empty($enable_multisite)) {
       $query = 'select config_companyname from web_sites where id=?';
       $query = $db->prepare_query($query,$website_id);
       $website_info = $db->get_record($query);
       if ($website_info)
          $cart_config_values['companyname'] =
             $website_info['config_companyname'];
       $query = 'select * from web_site_config where parent=?';
       $query = $db->prepare_query($query,$website_id);
       $website_config = $db->get_records($query,'config_name',
                                          'config_value');
       if ($website_config) {
          foreach ($website_config as $field_name => $field_value)
             $cart_config_values[$field_name] = $field_value;
       }
       else if (isset($db->error)) {
          if ($show_error) process_error('Database Error: '.$db->error,-1);
          return null;
       }
    }
    return $cart_config_values;
}

function get_cart_config_value($config_name,$db=null,$show_error=true)
{
    global $enable_multisite,$website_id,$cart_config_values;

    if ($cart_config_values) {
       if (isset($cart_config_values[$config_name]))
          return $cart_config_values[$config_name];
       else return '';
    }

    if (! $db) $db = new DB;
    $query = 'select config_value from cart_config where config_name=?';
    $query = $db->prepare_query($query,$config_name);
    $row = $db->get_record($query);
    if ((! $row) && isset($db->error)) {
       if ($show_error) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    if ($row) $config_value = $row['config_value'];
    else $config_value = '';

    if (! empty($enable_multisite)) {
       if ($config_name == 'companyname') {
          $query = 'select config_companyname from web_sites where id=?';
          $query = $db->prepare_query($query,$website_id);
          $website_info = $db->get_record($query);
          if ($website_info)
             $config_value = $website_info['config_companyname'];
       }
       else {
          $query = 'select config_value from web_site_config where parent=? ' .
                   'and config_name=?';
          $query = $db->prepare_query($query,$website_id,$config_name);
          $row = $db->get_record($query);
          if ((! $row) && isset($db->error)) {
             if ($show_error) process_error('Database Error: '.$db->error,-1);
             return null;
          }
          if ($row) $config_value = $row['config_value'];
       }
    }

    return $config_value;
}

function get_cart_option_labels()
{
    global $cart_option_labels,$enable_wholesale,$enable_rmas;
    global $enable_quotes,$enable_invoices,$enable_salesorders;

    if (isset($cart_option_labels)) return $cart_option_labels;

    $cart_option_labels = array('Product Status','Category Status',
       'Customer Status','Order Status','Account Status','RMA Status',
       'RMA Reasons','Order Sources','Quote Status','Invoice Status',
       'Sales Order Status');
    if (empty($enable_wholesale)) $cart_option_labels[4] = '';
    if (empty($enable_rmas)) {
       $cart_option_labels[5] = '';   $cart_option_labels[6] = '';
    }
    if (empty($enable_quotes)) $cart_option_labels[8] = '';
    if (empty($enable_invoices)) $cart_option_labels[9] = '';
    if (empty($enable_salesorders)) $cart_option_labels[10] = '';
    return $cart_option_labels;
}

function load_cart_options($table_id,$db=null)
{
    if (! $db) $db = new DB;
    $query = 'select * from cart_options where table_id=? ' .
             'order by sequence,id';
    $query = $db->prepare_query($query,$table_id);
    $options_list = $db->get_records($query,'id','label');
    if ((! $options_list) && isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    return $options_list;
}

function load_all_cart_options($db=null)
{
    if (! $db) $db = new DB;
    $query = 'select * from cart_options order by table_id,sequence,id';
    $options_list = $db->get_records($query);
    if ((! $options_list) && isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    return $options_list;
}

function get_state_array($billing_flag=true,$db=null)
{
    if (! $db) $db = new DB;
    if ($billing_flag)
       $query = 'select * from states where (available&1) order by code';
    else $query = 'select * from states where (available&2) order by code';
    $states = $db->get_records($query);
    return $states;
}

function get_state_list($selected_state,$billing_flag=true,$db=null)
{
    $states = get_state_array($billing_flag,$db);
    if (! $states) return '';
    $html = '';
    foreach ($states as $row) {
       $state_code = $row['code'];
       $html .= "<option value=\"".$state_code."\"";
       if ($state_code == $selected_state) $html .= ' selected';
       $html .= '>'.$state_code."</option>\n";
    }
    return $html;
}

function load_state_list($selected_state,$billing_flag=true,$db=null)
{
    print get_state_list($selected_state,$billing_flag,$db);
}

function get_state_info($state_code,$db=null)
{
    static $states = array();

    if (! $state_code) return null;
    if (isset($states[$state_code])) return $states[$state_code];
    if (! $db) $db = new DB;
    $query = 'select * from states where code=?';
    $query = $db->prepare_query($query,$state_code);
    $states[$state_code] = $db->get_record($query);
    return $states[$state_code];
}

function load_state_tax($state_code,$db=null)
{
    static $taxes = array();

    if (! $state_code) return 0;
    if (isset($taxes[$state_code])) return $taxes[$state_code];
    if (! $db) $db = new DB;
    $query = 'select tax from states where code=?';
    $query = $db->prepare_query($query,$state_code);
    $row = $db->get_record($query);
    if (! $row) $taxes[$state_code] = 0;
    else if (isset($row['tax']) && $row['tax'])
       $taxes[$state_code] = $row['tax'];
    else $taxes[$state_code] = 0;
    return $taxes[$state_code];
}

function get_country_array($billing_flag=true,$db=null)
{
    if (! $db) $db = new DB;
    if ($billing_flag)
       $query = 'select * from countries where (available&1) order by id';
    else $query = 'select * from countries where (available&2) order by id';
    $countries = $db->get_records($query);
    return $countries;
}

function get_country_list($selected_country,$billing_flag=true,$db=null)
{
    $countries = get_country_array($billing_flag,$db);
    if (! $countries) return '';
    $html = '';
    foreach ($countries as $row) {
       $html .= "<option value=\"".$row['id']."\"";
       if ($row['id'] == $selected_country) $html .= ' selected';
       $html .= '>'.$row['country']."</option>\n";
    }
    return $html;
}

function load_country_list($selected_country,$billing_flag=true,$db=null)
{
    print get_country_list($selected_country,$billing_flag,$db);
}

function get_country_name($country_id,$db=null)
{
    static $countries = array();

    if (! $country_id) return '';
    if (isset($countries[$country_id])) return $countries[$country_id];
    if (! $db) $db = new DB;
    $query = 'select country from countries where id=?';
    $query = $db->prepare_query($query,$country_id);
    $row = $db->get_record($query);
    if (! $row) $countries[$country_id] = '';
    else $countries[$country_id] = $row['country'];
    return $countries[$country_id];
}

function get_country_info($country_id,$db=null)
{
    static $countries = array();

    if (! $country_id) return null;
    if (isset($countries[$country_id])) return $countries[$country_id];
    if (! $db) $db = new DB;
    $query = 'select * from countries where id=?';
    $query = $db->prepare_query($query,$country_id);
    $countries[$country_id] = $db->get_record($query);
    return $countries[$country_id];
}

function find_country_info($country_code,$db=null)
{
    static $countries = array();

    if (isset($countries[$country_code])) return $countries[$country_code];
    if (! $db) $db = new DB;
    $query = 'select * from countries where code=?';
    $query = $db->prepare_query($query,$country_code);
    $countries[$country_code] = $db->get_record($query);
    return $countries[$country_code];
}

function get_default_country($billing_flag,$db=null)
{
    global $default_country;
    static $cached_default_country = -1;

    if ($cached_default_country != -1) return $cached_default_country;
    if (! isset($default_country)) $default_country = 0;
    if (! $db) $db = new DB;
    if ($billing_flag)
       $query = 'select id from countries where (available&1) order by id';
    else $query = 'select id from countries where (available&2) order by id';
    $rows = $db->get_records($query);
    if (! $rows) $cached_default_country = 0;
    else if (count($rows) != 1) $cached_default_country = $default_country;
    else $cached_default_country = $rows[0]['id'];
    return $cached_default_country;
}

function load_canada_province_list($selected_province)
{
    global $canada_provinces;

    foreach ($canada_provinces as $code => $label) {
       print "<option value=\"".$code."\"";
       if ($code == $selected_province) print ' selected';
       print '>'.$label."</option>\n";
    }
}

if (! function_exists('load_config_values')) {
   function load_config_values($db=null)
   {
       if (! $db) $db = new DB;
   
       $config_values = $db->get_records('select * from config','config_name',
                                         'config_value');
       if ((! $config_values) && isset($db->error))
          process_error('Database Error: '.$db->error,0);
       return $config_values;
   }
}

function load_shipping_modules()
{
    global $admin_directory,$shipping_modules;

    $shipping_modules = array();
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $modules_dir = @opendir($admin_directory.'shipping/');
    if (! $modules_dir) return;
    while (($module = readdir($modules_dir)) !== false) {
       if (substr($module,-4) == '.php') {
          $module_name = substr($module,0,-4);
          $module_file = $admin_directory.'shipping/'.$module;
          require_once $module_file;
          $shipping_modules[] = $module_name;
       }
    }
    sort($shipping_modules);
}

function shipping_module_event_exists($event,$shipping_module=null)
{
    global $shipping_modules;

    if ($shipping_modules === null) load_shipping_modules();
    if (empty($shipping_modules)) return false;
    foreach ($shipping_modules as $module) {
       if ($shipping_module && ($module != $shipping_module)) continue;
       $function_name = $module.'_'.$event;
       if (function_exists($function_name)) return true;
    }
    return false;
}

function call_shipping_event($event,$parameters,$continue_on_false=true,
                             $return_first_true=false)
{
    global $shipping_modules;

    if ($shipping_modules === null) load_shipping_modules();
    if (empty($shipping_modules)) {
       if ($return_first_true) return false;
       return true;
    }
    foreach ($shipping_modules as $module) {
       $function_name = $module.'_'.$event;
       if (! function_exists($function_name)) continue;
       $ret_value = call_user_func_array($function_name,$parameters);
       if (($ret_value === false) && (! $continue_on_false)) return false;
       if ($ret_value && $return_first_true) return $ret_value;
    }
    if ($return_first_true) return false;
    return true;
}

function load_payment_modules($db=null,$load_all=false)
{
    global $admin_directory,$payment_modules,$enable_multisite;

    $payment_modules = array();
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $modules_dir = @opendir($admin_directory.'payment/');
    if (! $modules_dir) return;
    $separate_payment = false;
    if ((! empty($enable_multisite)) && (! $load_all)) {
       $website_settings = get_website_settings($db);
       if ($website_settings & WEBSITE_SEPARATE_PAYMENT)
          $separate_payment = true;
    }
    while (($module = readdir($modules_dir)) !== false) {
       if (substr($module,-4) == '.php') {
          $module_name = substr($module,0,-4);
          $module_file = $admin_directory.'payment/'.$module;
          require_once $module_file;
          if ($separate_payment) {
             $function_name = $module_name.'_active';
             if (function_exists($function_name)) {
                $active_flag = $function_name($db);
                if (! $active_flag) continue;
             }
          }
          $payment_modules[] = $module_name;
       }
    }
    sort($payment_modules);
}

function payment_module_event_exists($event,$payment_module=null)
{
    global $payment_modules;

    if ($payment_modules === null) load_payment_modules();
    if (empty($payment_modules)) return false;
    foreach ($payment_modules as $module) {
       if ($payment_module && ($module != $payment_module)) continue;
       $function_name = $module.'_'.$event;
       if (function_exists($function_name)) return true;
    }
    return false;
}

function call_payment_event($event,$parameters=null,$continue_on_false=true,
                            $return_first_true=false)
{
    global $payment_modules;

    if ($payment_modules === null) load_payment_modules();
    if (empty($payment_modules)) {
       if ($return_first_true) return false;
       return true;
    }
    if (! $parameters) $parameters = array();
    foreach ($payment_modules as $module) {
       $function_name = $module.'_'.$event;
       if (! function_exists($function_name)) continue;
       $ret_value = call_user_func_array($function_name,$parameters);
       if (($ret_value === false) && (! $continue_on_false)) return false;
       if ($ret_value && $return_first_true) return $ret_value;
    }
    if ($return_first_true) return false;
    return true;
}

function get_saved_cards_flag($payment_module=null,$db=null)
{
    global $enable_saved_cards;

    if (empty($enable_saved_cards)) return false;
    if (! isset($payment_module)) {
       if (function_exists('get_custom_payment_module'))
          $payment_module = get_custom_payment_module(null,null);
       else $payment_module = call_payment_event('get_primary_module',
                                                 array($db),true,true);
       if (! $payment_module) return false;
    }
    if (! payment_module_event_exists('saved_cards_enabled',$payment_module))
       return false;
    $saved_cards_enabled = $payment_module.'_saved_cards_enabled';
    return $saved_cards_enabled($db);
}

function get_address_type($obj=null)
{
    global $force_ship_address_type,$default_ship_address_type;

    if (isset($force_ship_address_type)) return $force_ship_address_type;
    $address_type = '';
    if ($obj) {
       if (method_exists($obj,'get'))
          $address_type = $obj->get('ship_address_type');
       else if (isset($obj->shipping['address_type']))
          $address_type = $obj->shipping['address_type'];
    }
    if ($address_type !== '') return $address_type;
    if (isset($default_ship_address_type)) return $default_ship_address_type;
    return 2;
}

function add_free_shipping_option(&$cart,$carrier,$shipping_method)
{
    $free_label = get_cart_config_value('manual_free_label',$cart->db);
    if (! $free_label) $free_label = 'Free Shipping';
    if ($shipping_method == -1) $cart->unset_default_shipping();
    $cart->add_shipping_option($carrier,-1,0,$free_label,
                               $shipping_method == -1);
}

function get_account_product_price($price,$row,&$discount,$price_breaks=false)
{
    global $account_product_prices,$custom_account_product_prices;

    $price = floatval($price);
    if ($custom_account_product_prices) return $price;
    if (empty($row['discount']) && empty($row['price'])) {
       if ($discount) {
          $factor = (100 - $discount) / 100;
          $price = round($price * $factor,2);
       }
       return $price;
    }
    if (! empty($row['discount'])) $discount = floatval($row['discount']);
    if (empty($row['price'])) $account_price = null;
    else $account_price = floatval($row['price']);
    if ($account_product_prices === 'both') {
       if ($account_price) $price = $account_price;
       else {
          $factor = (100 - $discount) / 100;
          $price = round($price * $factor,2);
       }
    }
    else if ($account_product_prices && (! $price_breaks) &&
             (! empty($row['discount']))) $price = $discount;
    else {
       $factor = (100 - $discount) / 100;
       $price = round($price * $factor,2);
    }
    return $price;
}

?>
