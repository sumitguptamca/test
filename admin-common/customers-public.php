<?php
/*
                 Inroads Shopping Cart - Public Customer Functions

                        Written 2009-2010 by Randall Severy
                         Copyright 2009-2010 Inroads, LLC
*/

if (file_exists("engine/ui.php")) {
   require_once 'engine/ui.php';
   require_once 'engine/db.php';
   if (file_exists("admin/custom-config.php"))
      require_once 'admin/custom-config.php';
   require_once 'cartengine/customers-common.php';
}
else {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   if (file_exists("../admin/custom-config.php"))
      require_once '../admin/custom-config.php';
   require_once '../cartengine/customers-common.php';
}

class Customer {

function Customer($db = null,$customer_id = null)
{
    global $user_cookie;

    if (isset($customer_id)) $this->id = $customer_id;
    else if (isset($_COOKIE[$user_cookie]))
       $this->id = $customer_id = $_COOKIE[$user_cookie];
    else $customer_id = null;
    $this->errors = array();
    if ($db) $this->db = $db;
    else $this->db = new DB;
}

function load($shipping_profile_id = null)
{
    $this->billing_country = 1;
    $this->shipping_country = 1;

    if (! isset($this->id)) return;

    $customer = load_customer($this->db,$this->id,$error_msg);
    if (! $customer) {
       $this->error = $error_msg;   $this->errors['dberror'] = true;   return;
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
}

};

?>
