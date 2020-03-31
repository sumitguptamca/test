<?php
/*
                 Inroads Shopping Cart - Common Customer Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (file_exists('admin/custom-config.php'))
   require_once 'admin/custom-config.php';
else if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

function customers_record_definition()
{
    $customers_record = array();
    $customers_record['id'] = array('type' => INT_TYPE,'key' => true);
    $customers_record['email'] = array('type' => CHAR_TYPE);
    $customers_record['password'] = array('type' => CHAR_TYPE);
    $customers_record['fname'] = array('type' => CHAR_TYPE);
    $customers_record['mname'] = array('type' => CHAR_TYPE);
    $customers_record['lname'] = array('type' => CHAR_TYPE);
    $customers_record['company'] = array('type' => CHAR_TYPE);
    $customers_record['create_date'] = array('type' => INT_TYPE);
    $customers_record['ip_address'] = array('type' => CHAR_TYPE);
    $customers_record['status'] = array('type' => INT_TYPE);
    $customers_record['mailing'] = array('type' => INT_TYPE);
    $customers_record['mailing']['fieldtype'] = CHECKBOX_FIELD;
    $customers_record['reminders'] = array('type' => INT_TYPE);
    $customers_record['reminders']['fieldtype'] = CHECKBOX_FIELD;
    $customers_record['tax_exempt'] = array('type' => INT_TYPE);
    $customers_record['tax_exempt']['fieldtype'] = CHECKBOX_FIELD;
    $customers_record['account_id'] = array('type' => CHAR_TYPE);
    $customers_record['sales_rep'] = array('type' => CHAR_TYPE);
    $customers_record['rewards'] = array('type' => FLOAT_TYPE);
    $customers_record['profile_id'] = array('type' => CHAR_TYPE);
    $customers_record['credit_balance'] = array('type' => FLOAT_TYPE);
    $customers_record['last_modified'] = array('type' => INT_TYPE);
    if (function_exists('custom_customer_fields'))
       custom_customer_fields($customers_record);
    return $customers_record;
}

function billing_record_definition()
{
    $billing_record = array();
    $billing_record['id'] = array('type' => INT_TYPE,'key' => true);
    $billing_record['parent_type'] = array('type' => INT_TYPE);
    $billing_record['parent'] = array('type' => INT_TYPE);
    $billing_record['address1'] = array('type' => CHAR_TYPE);
    $billing_record['address2'] = array('type' => CHAR_TYPE);
    $billing_record['city'] = array('type' => CHAR_TYPE);
    $billing_record['state'] = array('type' => CHAR_TYPE);
    $billing_record['zipcode'] = array('type' => CHAR_TYPE);
    $billing_record['country'] = array('type' => INT_TYPE);
    $billing_record['phone'] = array('type' => CHAR_TYPE);
    $billing_record['fax'] = array('type' => CHAR_TYPE);
    $billing_record['mobile'] = array('type' => CHAR_TYPE);
    return $billing_record;
}

function shipping_record_definition()
{
    $shipping_record = array();
    $shipping_record['id'] = array('type' => INT_TYPE,'key' => true);
    $shipping_record['parent_type'] = array('type' => INT_TYPE);
    $shipping_record['parent'] = array('type' => INT_TYPE);
    $shipping_record['profilename'] = array('type' => CHAR_TYPE);
    $shipping_record['shipto'] = array('type' => CHAR_TYPE);
    $shipping_record['company'] = array('type' => CHAR_TYPE);
    $shipping_record['address1'] = array('type' => CHAR_TYPE);
    $shipping_record['address2'] = array('type' => CHAR_TYPE);
    $shipping_record['city'] = array('type' => CHAR_TYPE);
    $shipping_record['state'] = array('type' => CHAR_TYPE);
    $shipping_record['zipcode'] = array('type' => CHAR_TYPE);
    $shipping_record['country'] = array('type' => INT_TYPE);
    $shipping_record['address_type'] = array('type' => INT_TYPE);
    $shipping_record['default_flag'] = array('type' => INT_TYPE);
    $shipping_record['default_flag']['fieldtype'] = CHECKBOX_FIELD;
    if (function_exists('custom_customer_shipping_fields'))
       custom_customer_shipping_fields($shipping_record);
    return $shipping_record;
}

function customer_account_record_definition()
{
    $customer_account_record = array();
    $customer_account_record['customer_id'] = array('type' => INT_TYPE);
    $customer_account_record['account_id'] = array('type' => INT_TYPE);
    return $customer_account_record;
}

function customer_activity_record_definition()
{
    $activity_record = array();
    $activity_record['id'] = array('type' => INT_TYPE,'key' => true);
    $activity_record['parent'] = array('type' => INT_TYPE);
    $activity_record['activity_date'] = array('type' => INT_TYPE);
    $activity_record['activity'] = array('type' => CHAR_TYPE);
    return $activity_record;
}

function notices_record_definition()
{
    $notices_record = array();
    $notices_record['id'] = array('type' => INT_TYPE,'key' => true);
    $notices_record['parent'] = array('type' => INT_TYPE);
    $notices_record['email'] = array('type' => CHAR_TYPE);
    $notices_record['fname'] = array('type' => CHAR_TYPE);
    $notices_record['lname'] = array('type' => CHAR_TYPE);
    $notices_record['product_id'] = array('type' => INT_TYPE);
    $notices_record['attributes'] = array('type' => CHAR_TYPE);
    $notices_record['followup'] = array('type' => INT_TYPE);
    $notices_record['create_date'] = array('type' => INT_TYPE);
    $notices_record['notify_date'] = array('type' => INT_TYPE);
    return $notices_record;
}

class CustomerInfo {

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
       if ($field_name == 'state') {
          if ($this->billing_country != 1) return;
       }
       else if ($field_name == 'province') {
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
       if ($field_name == 'state') {
          if ($this->shipping_country != 1) return;
       }
       else if ($field_name == 'province') {
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
       if ($field_name == 'state') {
          if ($this->billing_country != 1) return '';
       }
       else if ($field_name == 'province') {
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
       if ($field_name == 'state') {
          if ($this->shipping_country != 1) return '';
       }
       else if ($field_name == 'province') {
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

};

function load_customer(&$db,$id,&$error_msg,$shipping_profile_id = null)
{
    $customer = new CustomerInfo();
    $customer->db = $db;
    $customer->id = $id;
    $customer->billing_country = 1;
    $customer->shipping_country = 1;
    $customer->customers_record = customers_record_definition();
    $customer->info_changed = false;
    $customer->billing_record = billing_record_definition();
    $customer->billing_changed = false;
    $customer->shipping_record = shipping_record_definition();
    $customer->shipping_changed = false;

    $query = 'select * from customers where id=?';
    $query = $db->prepare_query($query,$id);
    $customer->info = $db->get_record($query);
    if (! $customer->info) {
       if (isset($db->error)) $error_msg = $db->error;
       else $error_msg = 'Customer not found';
       return null;
    }
    $db->decrypt_record('customers',$customer->info);

    $query = 'select * from billing_information where parent=?';
    $query = $db->prepare_query($query,$id);
    $customer->billing = $db->get_record($query);
    if (! $customer->billing) {
       if (isset($db->error)) $error_msg = $db->error;
       else $error_msg = 'Billing Information not found';
       return null;
    }
    $db->decrypt_record('billing_information',$customer->billing);
    if (isset($customer->billing['country']))
       $customer->billing_country = $customer->billing['country'];

    if (! empty($shipping_profile_id)) {
       $query = 'select * from shipping_information where id=?';
       $query = $db->prepare_query($query,$shipping_profile_id);
    }
    else {
       $query = 'select * from shipping_information where (parent=?) and ' .
                '(default_flag=1)';
       $query = $db->prepare_query($query,$id);
    }
    $customer->shipping = $db->get_record($query);
    if (! $customer->shipping) {
       if (isset($db->error)) {
          $error_msg = $db->error;   return null;
       }
       else if (! empty($shipping_profile_id)) {
          $error_msg = 'Shipping Information not found';   return null;
       }
    }
    else {
       $db->decrypt_record('shipping_information',$customer->shipping);
       if (isset($customer->shipping['country']))
          $customer->shipping_country = $customer->shipping['country'];
    }

    return $customer;
}

function update_customer_info($db,&$customer_info,&$billing_info,
                              &$shipping_info=null)
{
    global $enable_wholesale;

    static $tax_rates = array();
    static $account_status = array();

    if ($billing_info) {
       if (! $billing_info['country']) $billing_info['country'] = 1;
       $country_id = $billing_info['country'];
       $country_info = get_country_info($country_id,$db);
       if ($country_info) {
          $billing_info['country_code'] = $country_info['code'];
          $billing_info['country_name'] = $country_info['country'];
       }
       else {
          $billing_info['country_code'] = '';
          $billing_info['country_name'] = '';
       }
       if (($billing_info['country'] == 1) && (! empty($billing_info['state']))) {
          $state = $billing_info['state'];
          $state_info = get_state_info($state,$db);
          if ($state_info) {
             $billing_info['state_name'] = $state_info['name'];
             $billing_info['state_tax'] = $state_info['tax'];
          }
          else $billing_info['state_name'] = '';
       }
       else {
          $state = null;   $state_info = null;
       }
    }
    else {
       $state = null;   $state_info = null;
    }

    if ($shipping_info) {
       if (! $shipping_info['country']) $shipping_info['country'] = 1;
       if ((! isset($country_id)) ||
           ($shipping_info['country'] != $country_id))
          $country_info = get_country_info($shipping_info['country'],$db);
       if ($country_info) {
          $shipping_info['country_code'] = $country_info['code'];
          $shipping_info['country_name'] = $country_info['country'];
       }
       else {
          $shipping_info['country_code'] = '';
          $shipping_info['country_name'] = '';
       }
       if ($shipping_info['country'] == 1) {
          if ($shipping_info['state'] && ($shipping_info['state'] != $state))
             $state_info = get_state_info($shipping_info['state'],$db);
          if ($state_info) {
             $shipping_info['state_name'] = $state_info['name'];
             $shipping_info['state_tax'] = $state_info['tax'];
          }
          else $shipping_info['state_name'] = '';
       }
    }

    if ((! isset($customer_info['tax_rate'])) && $shipping_info) {
       if (isset($shipping_info['state_tax']))
          $customer_info['tax_rate'] = $shipping_info['state_tax'];
       else {
          $tax_rate = 0;
          $country = get_row_value($shipping_info,'country');
          if (! $country) $country = 1;
          if ($country == 1) {
             $state = get_row_value($shipping_info,'state');
             if ($state) {
                if (! isset($tax_rates[$state])) {
                   $query = 'select tax from states where code=?';
                   $query = $db->prepare_query($query,$state);
                   $row = $db->get_record($query);
                   if ($row) $tax_rates[$state] = $row['tax'];
                   else $tax_rates[$state] = 0;
                }
                $tax_rate = $tax_rates[$state];
             }
          }
          $customer_info['tax_rate'] = $tax_rate;
       }
    }
    else $customer_info['tax_rate'] = 0;

    if (empty($enable_wholesale)) {}
    else if (! isset($customer_info['account_id'])) {
       if (! empty($customer_info['id'])) {
          $query = 'select account_id from customers where id=?';
          $query = $db->prepare_query($query,$customer_info['id']);
          $row = $db->get_record($query);
          if (! empty($row['account_id'])) {
             $account_id = $row['account_id'];
             $customer_info['account_id'] = $account_id;
             $account_status[$account_id] = true;
          }
          else $customer_info['account_id'] = null;
       }
       else $customer_info['account_id'] = null;
    }
    else if ($customer_info['account_id']) {
       $account_id = $customer_info['account_id'];
       if (! isset($account_status[$account_id])) {
          $query = 'select id from accounts where id=?';
          $query = $db->prepare_query($query,$account_id);
          $row = $db->get_record($query);
          if (! empty($row['id'])) $account_status[$account_id] = true;
          else $account_status[$account_id] = false;
       }
       if (! $account_status[$account_id]) $customer_info['account_id'] = null;
    }
}

function build_billing_record($db,$customer_id)
{
    $billing_record = billing_record_definition();
    $db->parse_form_fields($billing_record);
    unset($billing_record['id']['value']);
    $billing_record['parent']['value'] = $customer_id;
    $billing_record['parent_type']['value'] = 0;
    if (! isset($billing_record['country']['value']))
       $billing_record['country']['value'] = 1;
    if ($billing_record['country']['value'] == 43)
       $billing_record['state']['value'] = get_form_field('canada_province');
    else if ($billing_record['country']['value'] != 1)
       $billing_record['state']['value'] = get_form_field('province');
    return $billing_record;
}

function validate_customer_email($email)
{
    if (! preg_match('/^[^@]{1,64}@[^@]{1,255}$/',$email)) return false;
    $email_array = explode('@',$email);
    $local_array = explode('.',$email_array[0]);
    for ($loop = 0;  $loop < sizeof($local_array);  $loop++)
       if (! preg_match("/^(([A-Za-z0-9!#$%&'*+\/=?^_`{|}~-][A-Za-z0-9!#$%&'*+\/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$/",
                        $local_array[$loop])) return false;
    if (! preg_match('/^\[?[0-9\.]+\]?$/',$email_array[1])) { 
       $domain_array = explode('.',$email_array[1]);
       if (sizeof($domain_array) < 2) return false;
       for ($loop = 0;  $loop < sizeof($domain_array);  $loop++)
          if (! preg_match('/^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$/',
                           $domain_array[$loop])) return false;
    }  
    return true;
}

function get_customer_changes($db,$customer_info,$billing_info,$shipping_info)
{
    $changes = array();
    if (empty($customer_info['id'])) {
       $changes[] = 'id';   return $changes;
    }
    $customer_id = $customer_info['id'];
    $query = 'select * from customers where id=?';
    $query = $db->prepare_query($query,$customer_id);
    $row = $db->get_record($query);
    if (! $row) {
       $changes = array_keys($customer_info);   return $changes;
    }
    foreach ($customer_info as $field => $value) {
       if ($field == 'last_modified') continue;
       if (array_key_exists($field,$row) && ($row[$field] != $value)) {
          $changes[] = $field;
       }
    }
    if (! empty($billing_info)) {
       if (! empty($billing_info['id'])) {
          $query = 'select * from billing_information where id=?';
          $query = $db->prepare_query($query,$billing_info['id']);
       }
       else {
          $query = 'select * from billing_information where parent=?';
          $query = $db->prepare_query($query,$customer_id);
       }
       $row = $db->get_record($query);
       if (! $row) {
          $changes[] = 'billing_information';   return $changes;
       }
       foreach ($billing_info as $field => $value) {
          if (array_key_exists($field,$row) && ($row[$field] != $value))
             $changes[] = 'billing:'.$field;
       }
    }
    if (! empty($shipping_info)) {
       if (! empty($shipping_info['id'])) {
          $query = 'select * from shipping_information where id=?';
          $query = $db->prepare_query($query,$shipping_info['id']);
       }
       else {
          $query = 'select * from shipping_information where (parent=?)';
          if (! empty($shipping_info['profilename'])) {
             $query .= ' and (profilename=?)';
             $query = $db->prepare_query($query,$customer_id,
                                         $shipping_info['profilename']);
          }
          else {
             $query .= ' and (default_flag=1)';
             $query = $db->prepare_query($query,$customer_id);
          }
       }
       $row = $db->get_record($query);
       if (! $row) {
          $changes[] = 'shipping_information';   return $changes;
       }
       foreach ($shipping_info as $field => $value) {
          if (array_key_exists($field,$row) && ($row[$field] != $value))
             $changes[] = 'shipping:'.$field;
       }
    }
    return $changes;
}

function get_customer_activity_user($db=null)
{
    global $login_cookie,$auto_reorder_label;

    $admin_user = get_cookie($login_cookie);
    if (! $admin_user) $admin_user = getenv('REMOTE_USER');
    if (! $admin_user) return '';
    if ($admin_user == 'api') return 'API';
    if ($admin_user == 'reorders') {
       if (! isset($auto_reorder_label)) $auto_reorder_label = 'Reorder';
       return $auto_reorder_label.' Module';
    }
    if (! $db) $db = new DB;
    $full_name = get_user_name($db,$admin_user);
    if ($full_name) return $full_name.' ('.$admin_user.')';
    return $admin_user;
}

function write_customer_activity($activity,$customer_id=null,$db=null)
{
    global $user_cookie;

    if (empty($activity)) return false;
    if ((! $customer_id) && (! empty($user_cookie)))
       $customer_id = get_cookie($user_cookie);
    if (! $customer_id) return false;
    if (! $db) $db = new DB;
    if (strlen($activity) > 255) $activity = substr($activity,0,255);
    $activity_record = customer_activity_record_definition();
    $activity_record['parent']['value'] = $customer_id;
    $activity_record['activity_date']['value'] = time();
    $activity_record['activity']['value'] = $activity;
    if (! $db->insert('customer_activity',$activity_record)) return false;
    return true;
}

?>
