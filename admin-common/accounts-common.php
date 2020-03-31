<?php
/*
                  Inroads Shopping Cart - Common Account Functions

                        Written 2011-2019 by Randall Severy
                         Copyright 2011-2019 Inroads, LLC
*/

if (file_exists('admin/custom-config.php'))
   require_once 'admin/custom-config.php';
else if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('CREDIT_CARD_TYPE',0);
define('ON_ACCOUNT_TYPE',1);
define('ECHECK_TYPE',2);

global $account_label,$accounts_label;

if (! isset($account_label)) $account_label = 'Account';
if (! isset($accounts_label)) $accounts_label = $account_label.'s';

function account_record_definition()
{
    $account_record = array();
    $account_record['id'] = array('type' => INT_TYPE);
    $account_record['id']['key'] = true;
    $account_record['status'] = array('type' => INT_TYPE);
    $account_record['name'] = array('type' => CHAR_TYPE);
    $account_record['fname'] = array('type' => CHAR_TYPE);
    $account_record['lname'] = array('type' => CHAR_TYPE);
    $account_record['company'] = array('type' => CHAR_TYPE);
    $account_record['address1'] = array('type' => CHAR_TYPE);
    $account_record['address2'] = array('type' => CHAR_TYPE);
    $account_record['city'] = array('type' => CHAR_TYPE);
    $account_record['state'] = array('type' => CHAR_TYPE);
    $account_record['zipcode'] = array('type' => CHAR_TYPE);
    $account_record['country'] = array('type' => INT_TYPE);
    $account_record['phone'] = array('type' => CHAR_TYPE);
    $account_record['email'] = array('type' => CHAR_TYPE);
    $account_record['no_shipping_flag'] = array('type' => INT_TYPE);
    $account_record['no_shipping_flag']['fieldtype'] = CHECKBOX_FIELD;
    $account_record['tax_exempt'] = array('type' => INT_TYPE);
    $account_record['tax_exempt']['fieldtype'] = CHECKBOX_FIELD;
    $account_record['no_coupons'] = array('type' => INT_TYPE);
    $account_record['no_coupons']['fieldtype'] = CHECKBOX_FIELD;
    $account_record['discount_rate'] = array('type' => FLOAT_TYPE);
    $account_record['credit_limit'] = array('type' => FLOAT_TYPE);
    $account_record['payment_options'] = array('type' => INT_TYPE);
    if (function_exists('custom_account_fields'))
       custom_account_fields($account_record);
    return $account_record;
}

function account_product_record_definition()
{
    $account_product_record = array();
    $account_product_record['id'] = array('type' => INT_TYPE);
    $account_product_record['id']['key'] = true;
    $account_product_record['parent'] = array('type' => INT_TYPE);
    $account_product_record['related_id'] = array('type' => INT_TYPE);
    $account_product_record['price'] = array('type' => FLOAT_TYPE);
    $account_product_record['discount'] = array('type' => FLOAT_TYPE);
    $account_product_record['on_account'] = array('type' => INT_TYPE);
    return $account_product_record;
}

function load_accounts($db = null)
{
    if (! $db) $db = new DB;
    $accounts = $db->get_records('select * from accounts order by name','id');
    if ((! $accounts) && isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    return $accounts;
}

function load_user_accounts($username,$db = null)
{
    if (! $db) $db = new DB;
    $query = 'select * from user_accounts where username=?';
    $query = $db->prepare_query($query,$username);
    $rows = $db->get_records($query);
    if (! $rows) return array();
    $user_accounts = array();
    foreach ($rows as $row)
       $user_accounts[$row['account_id']] = true;
    return $user_accounts;
}

function load_customer_accounts($customer_id,$db = null)
{
    if (! $db) $db = new DB;
    $query = 'select * from customer_accounts where customer_id=?';
    $query = $db->prepare_query($query,$customer_id);
    $rows = $db->get_records($query);
    if (! $rows) return array();
    $customer_accounts = array();
    foreach ($rows as $row)
       $customer_accounts[$row['account_id']] = true;
    return $customer_accounts;
}

function load_allowed_accounts(&$accounts_list,$db = null)
{
    global $login_cookie;

    if (! $db) $db = new DB;
    $accounts_list = null;
    $query = 'select all_accounts from users where username=?';
    $query = $db->prepare_query($query,$_COOKIE[$login_cookie]);
    $row = $db->get_record($query);
    if (! $row) return null;
    if (($row['all_accounts'] == 0) || ($row['all_accounts'] == ''))
       return null;

    $query = 'select * from user_accounts where username=?';
    $query = $db->prepare_query($query,$_COOKIE[$login_cookie]);
    $rows = $db->get_records($query);
    if ((! $rows) && isset($db->error)) {
       process_error('Database Error: '.$db->error,-1);   return null;
    }
    $user_accounts = array();   $accounts_list = '';
    foreach ($rows as $row) {
       $user_accounts[$row['account_id']] = true;
       if ($accounts_list != '') $accounts_list .= ',';
       $accounts_list .= $row['account_id'];
    }
    return $user_accounts;
}

function build_payment_types($db,$account_id=null)
{
    global $on_account_payments;

    $echeck_flag = false;
    require_once 'cartconfig-common.php';
    $payment_module = call_payment_event('get_primary_module',
                                         array($db),true,true);
    if ($payment_module &&
        payment_module_event_exists('echecks_enabled',$payment_module)) {
       $echecks_enabled = $payment_module.'_echecks_enabled';
       $echeck_flag = $echecks_enabled($db);
    }
    if ($account_id !== null) {
       $query = 'select payment_options from accounts where id=?';
       $query = $db->prepare_query($query,$account_id);
       $row = $db->get_record($query);
       if (empty($row['payment_options'])) $account_id = 0;
       else $payment_options = $row['payment_options'];
    }

    $payment_types = array(0 => 'Credit Card');
    if ((! empty($on_account_payments)) && ($account_id !== 0))
       $payment_types[1] = 'On Account';
    if ($echeck_flag) $payment_types[2] = 'Online Check';

    if ($account_id) {
       foreach ($payment_types as $index => $label) {
          if (! ($payment_options & (1 << $index)))
             unset($payment_types[$index]);
       }
    }

    if (function_exists('update_payment_types'))
       update_payment_types($payment_types);

    return $payment_types;
}

function update_account_info($db,&$account_info)
{
    if (! $account_info['country']) $account_info['country'] = 1;
    $country_id = $account_info['country'];
    $country_info = get_country_info($country_id,$db);
    if ($country_info) {
       $account_info['country_code'] = $country_info['code'];
       $account_info['country_name'] = $country_info['country'];
    }
    else {
       $account_info['country_code'] = '';
       $account_info['country_name'] = '';
    }
    if (($account_info['country'] == 1) && (! empty($account_info['state']))) {
       $state = $account_info['state'];
       $state_info = get_state_info($state,$db);
       if ($state_info) {
          $account_info['state_name'] = $state_info['name'];
          $account_info['state_tax'] = $state_info['tax'];
       }
       else $account_info['state_name'] = '';
    }
}

?>
