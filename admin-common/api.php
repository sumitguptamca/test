<?php
/*
                 Inroads Control Panel/Shopping Cart - API Interface

                        Written 2013-2019 by Randall Severy
                         Copyright 2013-2019 Inroads, LLC
*/

global $shopping_cart;
if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/')) $shopping_cart = true;
   else $shopping_cart = false;
}
if ($shopping_cart) require_once '../cartengine/cartconfig-common.php';
else {
   define('REGULAR_PRICE_PRODUCT',32);
   define('LIST_PRICE_PRODUCT',256);
   define('SALE_PRICE_PRODUCT',1024);
   define('PRODUCT_COST_PRODUCT',524288);
}

class QuikWebAPI {

function QuikWebAPI($module=null,$db=null)
{
    global $db_host,$db_name,$db_user,$db_pass,$db_charset,$encrypt_base;
    global $encrypted_fields,$activity_log,$error_log,$db_log,$login_cookie;
    global $prefs_cookie,$docroot,$prefix,$admin_directory,$admin_path;
    global $base_url,$default_skin,$shopping_cart;

    $this->old_remote_user = getenv('REMOTE_USER');
    putenv('REMOTE_USER=api');
    $this->old_error_reporting = error_reporting();
    $this->old_display_errors = ini_get('display_errors');
    $this->old_track_errors = ini_get('track_errors');
    $this->old_error_log = ini_get('error_log');
    $this->old_log_errors = ini_get('log_errors');

    if (! class_exists('UI')) require_once '../engine/ui.php';
    if (! class_exists('DB')) require_once '../engine/db.php';
    if (file_exists('../admin/custom-config.php'))
       require_once '../admin/custom-config.php';

    $this->module = $module;
    $this->error = null;
    if ($db) {
       $this->db = $db;   $this->newdb = false;
    }
    else {
       $this->db = new DB;   $this->newdb = true;
    }
    $this->shopping_cart = $shopping_cart;
    if ($this->shopping_cart) {
       $query = 'select config_value from cart_config where ' .
                'config_name="features"';
       $row = $this->db->get_record($query);
       if ($row) $this->features = intval($row['config_value']);
       else $this->features = 0;
    }
    else $this->features = 0;
    $this->countries = array();
    $this->states = array();
}

function trim_info(&$info_array)
{
    if (empty($info_array)) return;
    foreach ($info_array as $field_name => $field_value) {
       if (is_array($field_value)) continue;
       $info_array[$field_name] = trim($field_value);
    }
}

function update_address_info(&$info_array)
{
    $country_info = null;   $state_info = null;

    $this->trim_info($info_array);
    if (isset($info_array['country']) && $info_array['country']) {
       $country_id = $info_array['country'];
       foreach ($this->countries as $country) {
          if ($country['id'] == $country_id) {
             $country_info = $country;   break;
          }
       }
       if (! $country_info) {
          $country_query = 'select * from countries where id=?';
          $country_query = $this->db->prepare_query($country_query,
                                                    $country_id);
       }
    }
    else if (isset($info_array['country_code']) &&
             $info_array['country_code']) {
       $country_code = $info_array['country_code'];
       foreach ($this->countries as $country) {
          if ($country['code'] == $country_code) {
             $country_info = $country;   break;
          }
       }
       if (! $country_info) {
          $country_query = 'select * from countries where code=?';
          $country_query = $this->db->prepare_query($country_query,
                                                    $country_code);
       }
    }
    else if (isset($info_array['country_name']) &&
             $info_array['country_name']) {
       $country_name = $info_array['country_name'];
       foreach ($this->countries as $country) {
          if ($country['country'] == $country_name) {
             $country_info = $country;   break;
          }
       }
       if (! $country_info) {
          $country_query = 'select * from countries where country=?';
          $country_query = $this->db->prepare_query($country_query,
                                                    $country_name);
       }
    }
    else $country_query = null;
    if ((! $country_info) && $country_query)
       $country_info = $this->db->get_record($country_query);
    if ($country_info) {
       $info_array['country'] = $country_info['id'];
       $info_array['country_code'] = $country_info['code'];
       $info_array['country_name'] = $country_info['country'];
    }
    else if (! isset($info_array['country'])) $info_array['country'] = 1;

    if ($info_array['country'] != 1) return;

    if (isset($info_array['state']) && $info_array['state']) {
       $state_code = $info_array['state'];
       foreach ($this->states as $state) {
          if ($state['code'] == $state_code) {
             $state_info = $state;   break;
          }
       }
       if (! $state_info) {
          $state_query = 'select * from states where code=?';
          $state_query = $this->db->prepare_query($state_query,$state_code);
       }
    }
    else if (isset($info_array['state_name']) &&
             $info_array['state_name']) {
       $state_name = $info_array['state_name'];
       foreach ($this->states as $state) {
          if ($state['name'] == $state_name) {
             $state_info = $state;   break;
          }
       }
       if (! $state_info) {
          $state_query = 'select * from states where name=?';
          $state_query = $this->db->prepare_query($state_query,$state_name);
       }
    }
    else $state_query = null;
    if ((! $state_info) && $state_query)
       $state_info = $this->db->get_record($state_query);

    if ($state_info) {
       $info_array['state'] = $state_info['code'];
       $info_array['state_name'] = $state_info['name'];
       $info_array['state_tax'] = $state_info['tax'];
    }
}

function user_init()
{
    global $prefs_cookie,$user_pref_names;

    if (! function_exists('user_record_definition'))
       require_once 'adminusers-common.php';
    if (! function_exists('add_user_perm')) require_once 'adminperms.php';
}

function get_users($username=null)
{
    if ($username) {
       $query = 'select * from users where username=?';
       $query = $this->db->prepare_query($query,$username);
    }
    else $query = 'select * from users where (username!="default") ' .
                  'order by lastname,firstname';
    $user_list = $this->db->get_records($query,'username');
    if (! $user_list) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       return null;
    }
    $this->db->decrypt_records('users',$user_list);
    if ((! $username) && function_exists('update_user_list'))
       update_user_list($this->db,$user_list);
    return $user_list;
}

function get_user_record($username)
{
    $query = 'select * from users where username=';
    if ($this->db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $this->db->prepare_query($query,$username);
    $row = $this->db->get_record($query);
    if ($row) $this->db->decrypt_record('users',$row);
    return $row;
}

function add_user($user_info,$user_prefs=null)
{
    $this->user_init();
    $this->trim_info($user_info);
    $user_record = user_record_definition();
    foreach ($user_info as $field_name => $field_value) {
       if (isset($user_record[$field_name]))
          $user_record[$field_name]['value'] = $field_value;
    }
    $username = $user_record['username']['value'];
    if ($username == 'default') {
       $this->error = 'That Admin Username is reserved';   return false;
    }
    $row = $this->get_user_record($username);
    if ($row) {
       $this->error = 'Admin Username Already Exists';   return false;
    }

    if ((! isset($user_info['perms'])) ||
        (! isset($user_info['module_perms'])) ||
        (! isset($user_info['custom_perms']))) {
       $query = 'select * from users where username="default"';
       $default_row = $this->db->get_record($query);
       if ($default_row) {
          if (! isset($user_info['perms']))
             $user_record['perms']['value'] = $default_row['perms'];
          if (! isset($user_info['module_perms']))
             $user_record['module_perms']['value'] = $default_row['module_perms'];
          if (! isset($user_info['custom_perms']))
             $user_record['custom_perms']['value'] = $default_row['custom_perms'];
       }
    }
    $user_record['creation_date']['value'] = time();
    $user_record['modified_date']['value'] = time();
    if (function_exists('custom_update_user')) {
       if (! custom_update_user($this->db,$user_record,ADDRECORD)) return;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_user')) {
       $user_info = $this->db->convert_record_to_array($user_record);
       if (! call_module_event('add_user',array($this->db,$user_info),
                               $this->module,true)) {
          call_module_event('delete_user',array($this->db,$user_info),
                            $this->module,true);
          $this->error = get_module_errors();   return false;
       }
    }
    if (! $this->db->insert('users',$user_record)) {
       $this->error = $this->db->error;   return false;
    }
    if (! $user_prefs) {
       $query = 'select * from user_prefs where username="default"';
       $user_prefs = $this->db->get_records($query,'pref_name','pref_value');
    }
    if ($user_prefs &&
        (! save_user_prefs($this->db,$user_record,ADDRECORD,$error_msg,
                           $user_prefs))) {
       $this->error = $error_msg;   return false;
    }
    if (function_exists('custom_save_user')) {
       if (! custom_save_user($this->db,$user_record,ADDRECORD)) return false;
    }
    log_activity('Added User '.$username.' (API)');
    return true;
}

function update_user($user_info,$user_prefs=null)
{
    $this->user_init();
    $this->trim_info($user_info);
    $user_record = user_record_definition();
    foreach ($user_info as $field_name => $field_value) {
       if (isset($user_record[$field_name]))
          $user_record[$field_name]['value'] = $field_value;
    }
    $username = $user_record['username']['value'];
    $user_record['modified_date']['value'] = time();
    $row = $this->get_user_record($username);
    if (! $row) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'User Not Found';
       return false;
    }
    if (function_exists('custom_update_user')) {
       if (! custom_update_user($this->db,$user_record,UPDATERECORD))
          return false;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_user')) {
       $user_info = $this->db->convert_record_to_array($user_record);
       foreach ($row as $field_name => $field_value)
          if (! isset($user_info[$field_name]))
             $user_info[$field_name] = $field_value;
       if (! call_module_event('update_user',array($this->db,$user_info,$row),
                               $this->module,true)) {
          $this->error = get_module_errors();   return;
       }
    }
    if (! $this->db->update('users',$user_record)) {
       $this->error = $this->db->error;   return false;
    }
    if ($user_prefs && 
        (! save_user_prefs($this->db,$user_record,UPDATERECORD,$error_msg,
                           $user_prefs))) {
       $this->error = $error_msg;   return false;
    }
    if (function_exists('custom_save_user')) {
       if (! custom_save_user($this->db,$user_record,UPDATERECORD))
          return false;
    }
    log_activity('Updated User '.$username.' (API)');
    return true;
}

function delete_user($username)
{
    global $enable_wholesale;

    $this->user_init();
    $row = $this->get_user_record($username);
    if (! $row) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'User Not Found';
       return false;
    }
    require_once '../engine/modules.php';
    if (! call_module_event('delete_user',array($this->db,$row),
                            $this->module,true)) {
       $this->error = get_module_errors();   return false;
    }
    $user_record = user_record_definition();
    $user_record['username']['value'] = $username;
    if (! $this->db->delete('users',$user_record)) {
       $this->error = $this->db->error;   return false;
    }
    $query = 'delete from user_prefs where username=?';
    $query = $this->db->prepare_query($query,$username);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
    if (isset($enable_wholesale) && $enable_wholesale) {
       $query = 'delete from user_accounts where username=?';
       $query = $this->db->prepare_query($query,$username);
       $this->db->log_query($query);
       if (! $this->db->query($query)) {
          $this->error = $this->db->error;   return false;
       }
    }
    if (function_exists('custom_delete_user')) {
       if (! custom_delete_user($this->db,$user_record)) return false;
    }
    log_activity('Deleted User '.$username.' (API)');
    return true;
}

function change_password($username,$password)
{
    $this->user_init();
    $user_record = user_record_definition();
    $user_record['username']['value'] = $username;
    $user_record['password']['value'] = $password;
    $user_record['modified_date']['value'] = time();
    if (function_exists('custom_update_password')) {
       if (! custom_update_password($this->db,$user_record,$error)) {
          if (empty($error))
             $error = 'custom_update_password failed with no error';
          $this->error = $error;   return false;
       }
    }
    $row = $this->get_user_record($username);
    if (! $row) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'User Not Found';
       return false;
    }
    $row['password'] = $password;
    $row['modified_date'] = $user_record['modified_date']['value'];
    if (! function_exists('call_module_event'))
       require_once '../engine/modules.php';
    if (! call_module_event('change_password',array($this->db,$row),
                            $this->module,true)) {
       $this->error = get_module_errors();   return false;
    }
    if (! $this->db->update('users',$user_record)) {
       $this->error = $this->db->error;   return false;
    }
    log_activity('Changed Password for User '.$username.' (API)');
    return true;
}

function category_init()
{
    require_once 'categories-common.php';
    require_once 'image.php';
    require_once 'sublist.php';
    require_once 'utility.php';
    require_once 'seo.php';
}

function add_category($category_info)
{
    global $category_label;

    $this->category_init();
    $this->trim_info($category_info);
    if (empty($category_info['status'])) $category_info['status'] = 0;
    if (empty($category_info['websites'])) $websites = null;
    else $websites = $category_info['websites'];
    if (! empty($category_info['display_name']))
       $name = $category_info['display_name'];
    else $name = $category_info['name'];
    if (empty($category_info['seo_url']))
       $seo_url = create_default_seo_url($this->db,$name,$websites);
    else {
       $seo_url = $category_info['seo_url'];   $base_seo_url = $seo_url;
       $sequence = 2;
       while (seo_url_exists($this->db,$seo_url,$location,$error,$websites)) {
          if ($error) {
             $this->error = $error;   return null;
          }
          $seo_url = $base_seo_url.'-'.$sequence;
          $sequence++;
       }
    }
    $category_info['seo_url'] = $seo_url;
    $category_record = category_record_definition();
    foreach ($category_info as $field_name => $field_value) {
       if (isset($category_record[$field_name]))
          $category_record[$field_name]['value'] = $field_value;
    }
    if (! add_category_record($this->db,$category_record,$category_id,
                              $error_code,$error,true,$this->module)) {
       $this->error = $error;   return null;
    }
    log_activity('Added '.$category_label.' '.$category_record['name']['value'] .
                 ' (#'.$category_id.') (API)');
    return $category_id;
}

function update_category($category_info)
{
    global $category_label;

    $category_id = $category_info['id'];
    $this->category_init();
    $this->trim_info($category_info);
    if (empty($category_info['id'])) {
       $this->error = 'No Category ID specified in update_category';
       return false;
    }
    $category_record = category_record_definition();
    foreach ($category_info as $field_name => $field_value) {
       if (isset($category_record[$field_name]))
          $category_record[$field_name]['value'] = $field_value;
    }
    if (! update_category_record($this->db,$category_record,$error,
                                 $this->module)) {
       $this->error = $error;   return false;
    }
    log_activity('Updated '.$category_label.' ' .
                 $category_record['name']['value'] .
                 ' (#'.$category_record['id']['value'].') (API)');
    return true;
}

function delete_category($category_info)
{
    global $category_label;

    $this->category_init();
    $category_id = $category_info['id'];
    if (! delete_category_record($this->db,$category_id,$category_name,$error,
                                 $this->module)) {
       $this->error = $error;   return false;
    }
    log_activity('Deleted '.$category_label.' #'.$category_id.' (' .
                 $category_name.') (API)');
    return true;
}

function add_subcategory($parent,$related_id)
{
    global $category_label,$subcategory_label,$subcategories_table;

    $this->category_init();
    $sequence = get_next_sublist_sequence($this->db,$subcategories_table,
                                          $parent);
    $sublist_record = sublist_record_definition();
    $sublist_record['parent']['value'] = $parent;
    $sublist_record['related_id']['value'] = $related_id;
    $sublist_record['sequence']['value'] = $sequence;
    if (! $this->db->insert($subcategories_table,$sublist_record)) {
       $this->error = $this->db->error;   return false;
    }
    log_activity('Added '.$subcategory_label.' #'.$related_id .
                 ' (Sequence #'.$sequence.') to '.$category_label.' #' .
                 $parent.' (API)');
    return true;
}

function add_category_product($parent,$related_id)
{
    global $category_label,$product_label,$category_products_table;

    $this->category_init();
    $sequence = get_next_sublist_sequence($this->db,$category_products_table,
                                          $parent);
    $sublist_record = sublist_record_definition();
    $sublist_record['parent']['value'] = $parent;
    $sublist_record['related_id']['value'] = $related_id;
    $sublist_record['sequence']['value'] = $sequence;
    if (! $this->db->insert($category_products_table,$sublist_record)) {
       $this->error = $this->db->error;   return false;
    }
    log_activity('Added '.$product_label.' #'.$related_id .
                 ' (Sequence #'.$sequence.') to '.$category_label.' #' .
                 $parent.' (API)');
    return true;
}

function product_init()
{
    global $shopping_cart;

    require_once 'products-common.php';
    require_once 'inventory.php';
    require_once 'inventory-common.php';
    require_once 'image.php';
    require_once 'sublist.php';
    require_once 'utility.php';
    require_once 'seo.php';
}

function update_product_info(&$product_info,$inventory_info)
{
    $this->trim_info($product_info);
    $this->trim_info($inventory_info);
    if (($this->features & LIST_PRICE_PRODUCT) &&
        isset($inventory_info['list_price']))
       $product_info['list_price'] = $inventory_info['list_price'];
    if (($this->features & REGULAR_PRICE_PRODUCT) &&
        isset($inventory_info['price']))
       $product_info['price'] = $inventory_info['price'];
    if (($this->features & SALE_PRICE_PRODUCT) &&
        isset($inventory_info['sale_price']))
       $product_info['sale_price'] = $inventory_info['sale_price'];
    if (($this->features & PRODUCT_COST_PRODUCT) &&
        isset($inventory_info['cost']))
       $product_info['cost'] = $inventory_info['cost'];
}

function add_product($product_info,&$inventory_info)
{
    global $product_label;

    $this->product_init();
    if (empty($product_info['status'])) $product_info['status'] = 0;
    $this->update_product_info($product_info,$inventory_info);
    if (! empty($product_info['seo_url'])) {
       if (empty($product_info['websites'])) $websites = null;
       else $websites = $product_info['websites'];
       $seo_url = $product_info['seo_url'];   $base_seo_url = $seo_url;
       $sequence = 2;
       while (seo_url_exists($this->db,$seo_url,$location,$error,$websites)) {
          if ($error) {
             $this->error = $error;   return null;
          }
          $seo_url = $base_seo_url.'-'.$sequence;
          $sequence++;
       }
       $product_info['seo_url'] = $seo_url;
    }
    $product_record = product_record_definition();
    foreach ($product_info as $field_name => $field_value) {
       if (isset($product_record[$field_name]))
          $product_record[$field_name]['value'] = $field_value;
    }
    if (! add_product_record($this->db,$product_record,$product_id,$error_code,
                             $error,true,$this->module)) {
       $this->error = $error;   return null;
    }
    $product_info['id'] = $product_id;
    $inventory_record = inventory_record_definition();
    $inventory_record['parent']['value'] = $product_id;
    $inventory_record['sequence']['value'] = 0;
    foreach ($inventory_info as $field_name => $field_value) {
       if (isset($inventory_record[$field_name]))
          $inventory_record[$field_name]['value'] = $field_value;
    }
    if (empty($inventory_record['last_modified']['value']))
       $inventory_record['last_modified']['value'] = time();
    if (! $this->db->insert('product_inventory',$inventory_record)) {
       $this->error = $this->db->error;   return null;
    }
    $inventory_info['id'] = $this->db->insert_id();
    require_once '../engine/modules.php';
    if (module_attached('add_inventory')) {
       if ($this->shopping_cart) {
          set_product_category_info($this->db,$product_info);
          update_inventory_records($this->db,$product_info,$inventory_info);
       }
       if (! call_module_event('add_inventory',
                               array($this->db,$product_info,$inventory_info),
                               $this->module,true)) {
          $this->error = get_module_errors();   return null;
       }
    }
    log_activity('Added '.$product_label.' '.$product_record['name']['value'] .
                 ' (#'.$product_id.') (API)');
    if (array_key_exists('activity',$product_info) &&
        empty($product_info['activity'])) {}
    else if (! empty($product_info['activity']))
       write_product_activity($product_info['activity'],$product_id,$this->db);
    else write_product_activity($product_label.' Added by '.$this->module,
                                $product_id,$this->db);
    return $product_id;
}

function update_product($product_info,$inventory_info)
{
    global $product_label;

    $product_id = $product_info['id'];
    $this->product_init();
    $this->update_product_info($product_info,$inventory_info);
    if (! empty($inventory_info['id'])) {
       $inventory_record = inventory_record_definition();
       foreach ($inventory_info as $field_name => $field_value) {
          if (isset($inventory_record[$field_name]))
             $inventory_record[$field_name]['value'] = $field_value;
       }
       if (empty($inventory_info['last_modified']))
          $inventory_record['last_modified']['value'] = time();
       if (! $this->db->update('product_inventory',$inventory_record)) {
          $this->error = $this->db->error;   return false;
       }
       if (! empty($inventory_info['qty'])) $qty = $inventory_info['qty'];
       else $qty = 0;
       if (! empty($inventory_info['attributes']))
          $attributes = $inventory_info['attributes'];
       else $attributes = null;
       if (using_linked_inventory($this->db))
          update_linked_inventory($this->db,$inventory_info['id'],$qty,
                                  $product_id,$attributes);
    }
    if (empty($product_info['id'])) {
       $this->error = 'No Product ID specified in update_product';
       return false;
    }
    if (! empty($product_info['seo_url'])) {
       $product_id = $product_info['id'];
       $query = 'select seo_url from products where id=?';
       $query = $this->db->prepare_query($query,$product_id);
       $seo_info = $this->db->get_record($query);
       if (! empty($seo_info['seo_url'])) $old_seo_url = $seo_info['seo_url'];
       else $old_seo_url = '';
       $seo_url = $product_info['seo_url'];
       if ($seo_url != $old_seo_url) {
          if (empty($product_info['websites'])) $websites = null;
          else $websites = $product_info['websites'];
          $base_seo_url = $seo_url;   $sequence = 2;
          while (seo_url_exists($this->db,$seo_url,$location,$error,$websites,
                                null,$product_id)) {
             if ($error) {
                $this->error = $error;   return null;
             }
             $seo_url = $base_seo_url.'-'.$sequence;
             $sequence++;
          }
          $product_info['seo_url'] = $seo_url;
       }
    }
    $product_record = product_record_definition();
    foreach ($product_info as $field_name => $field_value) {
       if (isset($product_record[$field_name]))
          $product_record[$field_name]['value'] = $field_value;
    }
    if (! update_product_record($this->db,$product_record,$error,
                                $this->module)) {
       $this->error = $error;   return false;
    }
    log_activity('Updated '.$product_label.' ' .$product_info['name'] .
                 ' (#'.$product_info['id'].') (API)');
    if (array_key_exists('activity',$product_info) &&
        empty($product_info['activity'])) {}
    else if (! empty($product_info['activity']))
       write_product_activity($product_info['activity'],$product_id,$this->db);
    else write_product_activity($product_label.' Updated by '.$this->module,
                                $product_id,$this->db);
    return true;
}

function delete_product($product_info,$inventory_info)
{
    global $product_label,$cache_catalog_pages;

    $this->product_init();
    $this->trim_info($product_info);   $this->trim_info($inventory_info);
    $product_id = $product_info['id'];
    if (! empty($cache_catalog_pages))
       $cached_categories = load_category_pages(array(),$this->db,$product_id);
    if (! delete_product_record($this->db,$product_id,$product_name,$error,
                                $this->module)) {
       $this->error = $error;   return false;
    }
    log_activity('Deleted '.$product_label.' #'.$product_id.' (' .
                 $product_name.') (API)');
    if (! empty($cache_catalog_pages))
       update_cached_category_pages($this->db,$cached_categories);
    return true;
}

function add_inventory($product_info,&$inventory_info)
{
    global $product_label;

    $this->product_init();
    $this->update_product_info($product_info,$inventory_info);
    $product_id = $product_info['id'];
    $inventory_record = inventory_record_definition();
    $inventory_record['parent']['value'] = $product_id;
    $inventory_record['sequence']['value'] = 0;
    foreach ($inventory_info as $field_name => $field_value) {
       if (isset($inventory_record[$field_name]))
          $inventory_record[$field_name]['value'] = $field_value;
    }
    if (empty($inventory_info['last_modified']))
       $inventory_record['last_modified']['value'] = time();
    if (! $this->db->insert('product_inventory',$inventory_record)) {
       $this->error = $this->db->error;   return null;
    }
    $inventory_id = $this->db->insert_id();
    $inventory_info['id'] = $inventory_id;
    require_once '../engine/modules.php';
    if (module_attached('add_inventory')) {
       if ($this->shopping_cart) {
          set_product_category_info($this->db,$product_info);
          update_inventory_records($this->db,$product_info,$inventory_info);
       }
       if (! call_module_event('add_inventory',
                               array($this->db,$product_info,$inventory_info),
                               $this->module,true)) {
          $this->error = get_module_errors();   return null;
       }
    }
    log_activity('Added Inventory Record #'.$inventory_id.' to ' .
                 $product_label.' #'.$product_id.' (API)');
    if (array_key_exists('activity',$product_info) &&
        empty($product_info['activity'])) {}
    else if (! empty($product_info['activity']))
       write_product_activity($product_info['activity'],$product_id,$this->db);
    else write_product_activity('Added Inventory Record by '.$this->module,
                                $product_id,$this->db);
    return $inventory_id;
}

function update_inventory($product_info,$inventory_info)
{
    global $product_label;

    $product_id = $product_info['id'];
    $this->product_init();
    $this->update_product_info($product_info,$inventory_info);
    if (! empty($inventory_info['id'])) {
       $inventory_record = inventory_record_definition();
       foreach ($inventory_info as $field_name => $field_value) {
          if (isset($inventory_record[$field_name]))
             $inventory_record[$field_name]['value'] = $field_value;
       }
       if (empty($inventory_info['last_modified']))
          $inventory_record['last_modified']['value'] = time();
       if (! $this->db->update('product_inventory',$inventory_record)) {
          $this->error = $this->db->error;   return false;
       }
       if (! empty($inventory_info['qty'])) $qty = $inventory_info['qty'];
       else $qty = 0;
       if (! empty($inventory_info['attributes']))
          $attributes = $inventory_info['attributes'];
       else $attributes = null;
       if (using_linked_inventory($this->db))
          update_linked_inventory($this->db,$inventory_info['id'],$qty,
                                  $product_id,$attributes);
       $inventory_label = ' #'.$inventory_info['id'];
    }
    else $inventory_label = '';
    $product_record = product_record_definition();
    foreach ($product_info as $field_name => $field_value) {
       if (isset($product_record[$field_name]))
          $product_record[$field_name]['value'] = $field_value;
    }
    if (! update_product_record($this->db,$product_record,$error,
                                $this->module)) {
       $this->error = $error;   return false;
    }
    log_activity('Updated Inventory Record'.$inventory_label.' for ' .
                 $product_label.' #'.$product_id.' (API)');
    if (array_key_exists('activity',$product_info) &&
        empty($product_info['activity'])) {}
    else if (! empty($product_info['activity']))
       write_product_activity($product_info['activity'],$product_id,$this->db);
    else write_product_activity('Updated Inventory Record by '.$this->module,
                                $product_id,$this->db);
    return true;
}

function delete_inventory($product_info,$inventory_info)
{
    global $product_label;

    $this->product_init();
    $this->trim_info($product_info);   $this->trim_info($inventory_info);
    $product_id = $product_info['id'];
    $query = 'select count(id) as num_records from product_inventory ' .
             'where parent=?';
    $query = $this->db->prepare_query($query,$product_id);
    $row = $this->db->get_record($query);
    if (! $row) {
       $this->error = $this->db->error;   return false;
    }
    $num_records = intval($row['num_records']);
    if ($num_records == 1) {
       if (! delete_product_record($this->db,$product_id,$product_info['name'],
                                   $error,$this->module)) {
          $this->error = $error;   return false;
       }
       log_activity('Deleted '.$product_label.' #'.$product_id .
                    ' ('.$product_info['name'].') (API)');
       return true;
    }
    $inventory_id = $inventory_info['id'];
    $query = 'delete from product_inventory where id=?';
    $query = $this->db->prepare_query($query,$inventory_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
    if (using_linked_inventory($this->db))
       delete_linked_inventory($this->db,$inventory_id);
    require_once '../engine/modules.php';
    if (module_attached('delete_inventory')) {
       if (! call_module_event('delete_inventory',
                               array($this->db,$product_info,$inventory_info),
                               $this->module,true)) {
          $this->error = get_module_errors();   return false;
       }
    }
    log_activity('Deleted Inventory Record #'.$inventory_id.' from ' .
                 $product_label.' #'.$product_id.' (API)');
    if (array_key_exists('activity',$product_info) &&
        empty($product_info['activity'])) {}
    else if (! empty($product_info['activity']))
       write_product_activity($product_info['activity'],$product_id,$this->db);
    else write_product_activity('Deleted Inventory Record by '.$this->module,
                                $product_id,$this->db);
    return true;
}

function account_init()
{
    require_once 'accounts-common.php';
    require_once '../engine/modules.php';
}

function add_account($account_info)
{
    $this->account_init();
    if (empty($account_info['status'])) $account_info['status'] = 0;
    $this->update_address_info($account_info);
    $account_record = account_record_definition();
    foreach ($account_info as $field_name => $field_value) {
       if (isset($account_record[$field_name]))
          $account_record[$field_name]['value'] = $field_value;
    }
    if (! $this->db->insert('accounts',$account_record)) {
       $this->error = $this->db->error;   return null;
    }
    $account_id = $this->db->insert_id();
    $account_record['id']['value'] = $account_id;

    if (module_attached('add_account')) {
       update_account_info($this->db,$account_info);
       if (! call_module_event('add_account',array($this->db,$account_info),
                               $this->module,true)) {
          $this->error = 'Unable to add account: '.get_module_errors();
          log_error($this->error);   return null;
       }
    }

    log_activity('Added Account '.$account_info['name'].' (#'.$account_id .
                 ') (API)');
    return $account_id;
}

function update_account($account_info)
{
    $this->account_init();
    $this->update_address_info($account_info);
    $account_id = $account_info['id'];
    if (! $account_id) {
       $this->error = 'Account ID is required in update_account event';
       return false;
    }
    $query = 'select id from accounts where id=?';
    $query = $this->db->prepare_query($query,$account_id);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = 'Account #'.$account_id .
                           ' Not Found in update_account event';
       return false;
    }
    $account_record = account_record_definition();
    foreach ($account_info as $field_name => $field_value) {
       if (isset($account_record[$field_name]))
          $account_record[$field_name]['value'] = $field_value;
    }
    if (! $this->db->update('accounts',$account_record)) {
       $this->error = $this->db->error;   return null;
    }

    if (module_attached('update_account')) {
       update_account_info($this->db,$account_info);
       if (! call_module_event('update_account',array($this->db,$account_info),
                               $this->module,true)) {
          $this->error = 'Unable to update account: '.get_module_errors();
          log_error($this->error);   return false;
       }
    }

    log_activity('Updated Account '.$account_info['name'].' (API)');
    return true;
}

function delete_account($id)
{
    $this->account_init();
    if (module_attached('delete_account')) {
       $query = 'select * from accounts where id=?';
       $query = $this->db->prepare_query($query,$id);
       $account_info = $this->db->get_record($query);
    }
    $account_record = account_record_definition();
    $account_record['id']['value'] = $id;
    if (! $this->db->delete('accounts',$account_record)) {
       $this->error = $this->db->error;   return false;
    }
    if (module_attached('delete_account')) {
       update_account_info($this->db,$account_info);
       $this->update_address_info($account_info);
       if (! call_module_event('delete_account',array($this->db,$account_info),
                               $this->module,true)) {
          $this->error = 'Unable to delete account: '.get_module_errors();
          log_error($this->error);   return false;
       }
    }
    log_activity('Deleted Account #'.$id.' (API)');
    return true;
}

function customer_init()
{
    if ($this->shopping_cart) require_once 'customers-common.php';
    require_once '../engine/modules.php';
}

function get_customer_name($customer_info)
{
    if (empty($customer_info['fname'])) $name = '';
    else $name = $customer_info['fname'];
    if (! empty($customer_info['lname'])) {
       if ($name) $name .= ' ';
       $name .= $customer_info['lname'];
    }
    if (! empty($customer_info['company'])) {
       if ($name) $name .= ' ('.$customer_info['company'].')';
       else $name = $customer_info['company'];
    }
    if (isset($customer_info['id'])) {
       if ($name) $name .= ' [#'.$customer_info['id'].']';
       else $name = '#'.$customer_info['id'];
    }
    return $name;
}

function add_customer($customer_info,$billing_info,$shipping_info)
{
    $this->customer_init();
    $this->trim_info($customer_info);
    $current_time = time();
    if (empty($customer_info['status'])) $customer_info['status'] = 0;
    if (! isset($customer_info['create_date']))
       $customer_info['create_date'] = $current_time;
    if (! isset($customer_info['last_modified']))
       $customer_info['last_modified'] = $current_time;
    if ($this->shopping_cart) {
       $customer_record = customers_record_definition();
       foreach ($customer_info as $field_name => $field_value) {
          if (isset($customer_record[$field_name]))
             $customer_record[$field_name]['value'] = $field_value;
       }
       if (function_exists('custom_update_customer')) {
          if (! custom_update_customer($this->db,$customer_record,ADDRECORD))
             return null;
       }
       if (! $this->db->insert('customers',$customer_record)) {
          $this->error = $this->db->error;   return null;
       }
       $customer_id = $this->db->insert_id();
       $customer_record['id']['value'] = $customer_id;
       $customer_info['id'] = $customer_id;
    }
    else {
       $api_object = new APIObject($this->db);
       if (isset($customer_info['id'])) {
          $customer_id = $customer_info['id'];
          if (! $api_object->add(CUSTOMER_API_OBJECT,$lead_id,
                                 'Customer #'.$customer_id)) {
             $this->error = 'Unable to add customer (1): '.$api_object->error;
             return null;
          }
       }
       else {
          $customer_id = $api_object->add(CUSTOMER_API_OBJECT,null,null);
          if (! $customer_id) {
             $this->error = 'Unable to add customer (2): '.$api_object->error;
             return null;
          }
          if (! $api_object->update(CUSTOMER_API_OBJECT,$customer_id,
                                    'Customer #'.$customer_id)) {
             $this->error = 'Unable to add customer (3): '.$api_object->error;
             return null;
          }
          $customer_info['id'] = $customer_id;
       }
    }
    $this->update_address_info($billing_info);
    if ($this->shopping_cart) {
       $billing_record = billing_record_definition();
       $billing_record['parent']['value'] = $customer_id;
       $billing_record['parent_type']['value'] = 0;
       foreach ($billing_info as $field_name => $field_value) {
          if (isset($billing_record[$field_name]))
             $billing_record[$field_name]['value'] = $field_value;
       }
       if (! $this->db->insert('billing_information',$billing_record)) {
          $this->error = $this->db->error;   return null;
       }
    }

    $this->update_address_info($shipping_info);
    if ($this->shopping_cart) {
       $shipping_record = shipping_record_definition();
       $shipping_record['parent']['value'] = $customer_id;
       $shipping_record['parent_type']['value'] = 0;
       $shipping_record['profilename']['value'] = 'Default';
       $shipping_record['default_flag']['value'] = 1;
       foreach ($shipping_info as $field_name => $field_value) {
          if (isset($shipping_record[$field_name]))
             $shipping_record[$field_name]['value'] = $field_value;
       }
       if (! $this->db->insert('shipping_information',$shipping_record)) {
          $this->error = $this->db->error;   return null;
       }
    }

    if (module_attached('add_customer')) {
       if ($this->shopping_cart) {
          $customer_info = $this->db->convert_record_to_array($customer_record);
          $billing_info = $this->db->convert_record_to_array($billing_record);
          $shipping_info = $this->db->convert_record_to_array($shipping_record);
          update_customer_info($this->db,$customer_info,$billing_info,
                              $shipping_info);
       }
       if (! call_module_event('add_customer',
                array($this->db,$customer_info,$billing_info,$shipping_info),
                $this->module,true)) {
          $this->error = 'Unable to add customer: '.get_module_errors();
          log_error($this->error);   return null;
       }
    }

    log_activity('Added Customer '.$this->get_customer_name($customer_info) .
                 ' (API)');
    if ($this->shopping_cart) {
       if (empty($customer_info['activity']))
          $activity = 'Customer Added by API';
       else $activity = $customer_info['activity'];
       write_customer_activity($activity,$customer_id,$this->db);
    }
    return $customer_id;
}

function update_customer($customer_info,$billing_info,$shipping_info)
{
    $this->customer_init();
    $this->trim_info($customer_info);
    $customer_id = $customer_info['id'];
    if (! $customer_id) {
       $this->error = 'Customer ID is required in update_customer event';
       return false;
    }
    if ($this->shopping_cart) {
       $query = 'select id from customers where id=?';
       $query = $this->db->prepare_query($query,$customer_id);
       $row = $this->db->get_record($query);
       if (! $row) {
          if (isset($this->db->error)) $this->error = $this->db->error;
          else $this->error = 'Customer #'.$customer_id .
                              ' Not Found in update_customer event';
          return false;
       }
    }
    if ($this->shopping_cart) {
       $customer_record = customers_record_definition();
       foreach ($customer_info as $field_name => $field_value) {
          if (isset($customer_record[$field_name]))
             $customer_record[$field_name]['value'] = $field_value;
       }
       if (function_exists('custom_update_customer')) {
          if (! custom_update_customer($this->db,$customer_record,UPDATERECORD))
             return false;
       }
    }

    if ($billing_info) {
       $this->update_address_info($billing_info);
       if ($this->shopping_cart) {
          $billing_record = billing_record_definition();
          $billing_record['parent']['value'] = $customer_id;
          $billing_record['parent']['key'] = true;
          foreach ($billing_info as $field_name => $field_value) {
             if (isset($billing_record[$field_name]))
                $billing_record[$field_name]['value'] = $field_value;
          }
       }
    }

    if ($shipping_info) {
       $this->update_address_info($shipping_info);
       if ($this->shopping_cart) {
          $shipping_record = shipping_record_definition();
          $shipping_record['parent']['value'] = $customer_id;
          $shipping_record['parent']['key'] = true;
          $shipping_record['parent_type']['value'] = 0;
          $shipping_record['default_flag']['value'] = 1;
          $shipping_record['default_flag']['key'] = true;
          foreach ($shipping_info as $field_name => $field_value) {
             if (isset($shipping_record[$field_name]))
                $shipping_record[$field_name]['value'] = $field_value;
          }
       }
    }

    if ($this->shopping_cart) {
       update_customer_info($this->db,$customer_info,$billing_info,
                            $shipping_info);
       $compare_customer_info =
          $this->db->convert_record_to_array($customer_record);
       if ($billing_info)
          $compare_billing_info =
             $this->db->convert_record_to_array($billing_record);
       else $compare_billing_info = null;
       if ($shipping_info)
          $compare_shipping_info =
             $this->db->convert_record_to_array($shipping_record);
       else $compare_shipping_info = null;
       $changes = get_customer_changes($this->db,$compare_customer_info,
                     $compare_billing_info,$compare_shipping_info);
       if (empty($changes)) return true;
    }

    if (! isset($customer_info['last_modified']))
       $customer_info['last_modified'] = time();
    if ($this->shopping_cart) {
       if (! $this->db->update('customers',$customer_record)) {
          $this->error = $this->db->error;   return null;
       }
       if ($billing_info) {
          if (! $this->db->update('billing_information',$billing_record)) {
             $this->error = $this->db->error;   return null;
          }
       }
       if ($shipping_info) {
          if (! $this->db->update('shipping_information',$shipping_record)) {
             $this->error = $this->db->error;   return null;
          }
       }
    }

    if (module_attached('update_customer')) {
       if (! call_module_event('update_customer',
                array($this->db,$customer_info,$billing_info,$shipping_info),
                $this->module,true)) {
          $this->error = 'Unable to update customer: '.get_module_errors();
          log_error($this->error);   return false;
       }
    }

    log_activity('Updated Customer '.$this->get_customer_name($customer_info) .
                 ' (API)');
    if ($this->shopping_cart) {
       if (empty($customer_info['activity']))
          $activity = 'Customer Updated by API ['.implode(',',$changes).']';
       else $activity = $customer_info['activity'];
       write_customer_activity($activity,$customer_id,$this->db);
    }
    return true;
}

function delete_customer($id)
{
    global $multiple_customer_accounts,$enable_customer_notices;

    if (! $id) {
       $this->error = 'No Customer ID specified in delete_customer API call';
       return false;
    }
    $this->customer_init();
    if ($this->shopping_cart) {
       if (module_attached('delete_customer')) {
          $query = 'select * from customers where id=?';
          $query = $this->db->prepare_query($query,$id);
          $customer_info = $this->db->get_record($query);
          $query = 'select * from billing_information where parent=?';
          $query = $this->db->prepare_query($query,$id);
          $billing_info = $this->db->get_record($query);
          $query = 'select * from shipping_information where (parent=?) and ' .
                   '(default_flag=1)';
          $query = $this->db->prepare_query($query,$id);
          $shipping_info = $this->db->get_record($query);
       }
       $query = 'delete from billing_information where parent=?';
       $query = $this->db->prepare_query($query,$id);
       $this->db->log_query($query);
       if (! $this->db->query($query)) {
          $this->error = $this->db->error;   return false;
       }
       $query = 'delete from shipping_information where parent=?';
       $query = $this->db->prepare_query($query,$id);
       $this->db->log_query($query);
       if (! $this->db->query($query)) {
          $this->error = $this->db->error;   return false;
       }
       if (get_saved_cards_flag(null,$this->db)) {
          require_once 'savedcards.php';
          if (! delete_customer_cards($this->db,$id)) return false;
       }
       if (! empty($multiple_customer_accounts)) {
          $query = 'delete from customer_accounts where customer_id=?';
          $query = $this->db->prepare_query($query,$id);
          $this->db->log_query($query);
          if (! $this->db->query($query)) {
             $this->error = $this->db->error;   return false;
          }
       }
       if (! empty($enable_customer_notices)) {
          $query = 'delete from customer_notices where parent=?';
          $query = $this->db->prepare_query($query,$id);
          $this->db->log_query($query);
          if (! $this->db->query($query)) {
             $this->error = $this->db->error;   return false;
          }
       }
       $customer_record = customers_record_definition();
       $customer_record['id']['value'] = $id;
       if (! $this->db->delete('customers',$customer_record)) {
          $this->error = $this->db->error;   return false;
       }
    }
    else {
       $api_object = new APIObject($this->db);
       if (! $api_object->delete(CUSTOMER_API_OBJECT,$id)) {
          $this->error = 'Unable to delete customer: '.$api_object->error;
          return false;
       }
    }
    if (module_attached('delete_customer')) {
       if ($this->shopping_cart)
          update_customer_info($this->db,$customer_info,$billing_info,
                               $shipping_info);
       else {
          $customer_info = array('id' => $id);   $billing_info = array();
          $shipping_info = array();
       }
       if (! call_module_event('delete_customer',
                array($this->db,$customer_info,$billing_info,$shipping_info),
                $this->module,true)) {
          $this->error = 'Unable to delete customer: '.get_module_errors();
          log_error($this->error);   return false;
       }
    }
    log_activity('Deleted Customer #'.$id.' (API)');
    return true;
}

function get_lead_name($lead_info)
{
    if (empty($lead_info['fname'])) $name = '';
    else $name = $lead_info['fname'];
    if (! empty($lead_info['lname'])) {
       if ($name) $name .= ' ';
       $name .= $lead_info['lname'];
    }
    if (! empty($lead_info['company'])) {
       if ($name) $name .= ' ('.$lead_info['company'].')';
       else $name = $lead_info['company'];
    }
    if ($name) $name .= ' [#'.$lead_info['id'].']';
    else $name = '#'.$lead_info['id'];
    return $name;
}

function add_lead($lead_info)
{
    if (file_exists('engine/modules.php')) require_once 'engine/modules.php';
    else require_once '../engine/modules.php';
    $api_object = new APIObject($this->db);
    $this->customer_init();
    $this->trim_info($lead_info);
    if (isset($lead_info['id'])) {
       $lead_id = $lead_info['id'];
       if (! $api_object->add(LEAD_API_OBJECT,$lead_id,'Lead #'.$lead_id)) {
          $this->error = 'Unable to add lead (1): '.$api_object->error;
          return null;
       }
    }
    else {
       $lead_id = $api_object->add(LEAD_API_OBJECT,null,null);
       if (! $lead_id) {
          $this->error = 'Unable to add lead (2): '.$api_object->error;
          return null;
       }
       if (! $api_object->update(LEAD_API_OBJECT,$lead_id,
                                 'Lead #'.$lead_id)) {
          $this->error = 'Unable to add lead (3): '.$api_object->error;
          return null;
       }
       $lead_info['id'] = $lead_id;
    }
    $current_time = time();
    if (! isset($lead_info['create_date']))
       $lead_info['create_date'] = $current_time;
    if (! isset($lead_info['last_modified']))
       $lead_info['last_modified'] = $current_time;
    $this->update_address_info($lead_info);

    $customer_id = $this->db->insert_id();
    $customer_record['id']['value'] = $customer_id;

    if (module_attached('add_lead')) {
       if (! call_module_event('add_lead',array($this->db,$lead_info),
                               $this->module,true)) {
          $this->error = 'Unable to add lead (4): '.get_module_errors();
          log_error($this->error);   return null;
       }
    }

    log_activity('Added Lead '.$this->get_lead_name($lead_info).' (API)');
    return $lead_id;
}

function update_lead($lead_info)
{
    $this->customer_init();
    $this->trim_info($lead_info);
    $lead_id = $lead_info['id'];
    if (! isset($lead_info['last_modified']))
       $lead_info['last_modified'] = time();
    $this->update_address_info($lead_info);

    if (module_attached('update_lead')) {
       if (! call_module_event('update_lead',
                array($this->db,$lead_info),$this->module,true)) {
          $this->error = 'Unable to update lead: '.get_module_errors();
          log_error($this->error);   return false;
       }
    }

    log_activity('Updated Lead '.$this->get_lead_name($lead_info).' (API)');
    return true;
}

function delete_lead($lead_info)
{
    $lead_id = $lead_info['id'];
    $this->customer_init();
    $this->trim_info($lead_info);
    if (! call_module_event('delete_lead',array($this->db,$lead_info),
                            $this->module,true)) {
       $this->error = 'Unable to delete lead: '.get_module_errors();
       log_error($this->error);   return false;
    }
    $api_object = new APIObject($this->db);
    if (! $api_object->delete(LEAD_API_OBJECT,$lead_id)) {
       $this->error = 'Unable to delete lead: '.$api_object->error;
       return false;
    }
    log_activity('Deleted Lead #'.$lead_id.' (API)');
    return true;
}

function order_init()
{
    if (file_exists('../admin/custom-config.php'))
       require_once '../admin/custom-config.php';
    if ($this->shopping_cart) {
       require_once 'orders-common.php';
       require_once 'customers-common.php';
       require_once 'inventory-common.php';
    }
    else {
       define('ORDER_TYPE',0);
       define('QUOTE_TYPE',1);
       define('INVOICE_TYPE',2);
       define('SALESORDER_TYPE',3);
    }
    require_once '../engine/modules.php';

    return true;
}

function get_order_customer_id($order_id)
{
    $query = 'select customer_id from orders where id=?';
    $query = $this->db->prepare_query($query,$order_id);
    $row = $this->db->get_record($query);
    if (! empty($row['customer_id'])) $customer_id = $row['customer_id'];
    else $customer_id = 0;
    return $customer_id;
}

function get_order_customer_name($order_info,$customer_id)
{
    if (empty($order_info['fname'])) $name = '';
    else $name = $order_info['fname'];
    if (! empty($order_info['lname'])) {
       if ($name) $name .= ' ';
       $name .= $order_info['lname'];
    }
    if (! empty($order_info['company'])) {
       if ($name) $name .= ' ('.$order_info['company'].')';
       else $name = $order_info['company'];
    }
    if ($name) $name .= ' [#'.$customer_id.']';
    else $name = 'Customer #'.$customer_id;
    return $name;
}

function update_payments_and_shipments($order_info,$order_items,
                                       &$order_payments,&$order_shipments)
{
    if (empty($order_payments)) {
       if ((! empty($order_info['payment_type'])) ||
           (! empty($order_info['payment_method']))) {
          $payment_info = array();
          $payment_record = payment_record_definition();
          foreach ($payment_record as $field_name => $field_def) {
             if (($field_name == 'id') || ($field_name == 'parent_type') ||
                 ($field_name == 'parent')) continue;
             if (! empty($order_info[$field_name]))
                $payment_info[$field_name] = $order_info[$field_name];
          }
          $order_payments = array($payment_info);
       }
    }
    if ($order_payments === null) $order_payments = array();
    foreach ($order_payments as $index => $payment_info) {
       if ((! empty($payment_info['payment_date'])) &&
           (! isset($payment_info['payment_status'])))
          $order_payments[$index]['payment_status'] = PAYMENT_CAPTURED;
    }
    if (empty($order_shipments)) {
       if (! empty($order_info['shipped_date'])) {
          $shipment_info = array();
          $shipment_record = shipment_record_definition();
          foreach ($shipment_record as $field_name => $field_def) {
             if (($field_name == 'id') || ($field_name == 'parent_type') ||
                 ($field_name == 'parent')) continue;
             if (! empty($order_info[$field_name]))
                $shipment_info[$field_name] = $order_info[$field_name];
          }
          if (! empty($order_items)) {
             $shipment_items = array();
             foreach ($order_items as $index => $item_info)
                $shipment_items[] = array('index' => $index,
                                          'qty' => $item_info['qty']);
             $shipment_info['items'] = $shipment_items;
          }
          $order_shipments = array($shipment_info);
       }
    }
    if ($order_shipments === null) $order_shipments = array();
}

function add_order($order_info,$billing_info,$shipping_info,&$order_items,
                   &$order_payments,&$order_shipments,$type=0)
{
    global $order_type,$base_order_number;

    $order_type = $type;
    $this->order_init();
    $this->trim_info($order_info);
    switch ($order_type) {
       case ORDER_TYPE:
          $order_label = 'Order';   $table = 'orders';
          $event = 'add_order';   break;
       case QUOTE_TYPE:
          $order_label = 'Quote';   $table = 'quotes';
          $event = 'add_quote';   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   $table = 'invoices';
          $event = 'add_invoice';   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   $table = 'sales_orders';
          $event = 'add_salesorder';   break;
    }
    $this->update_address_info($billing_info);
    $this->update_address_info($shipping_info);
    if ($this->shopping_cart)
       update_order_info($this->db,$order_info,$billing_info,$shipping_info,
                         $order_items,$order_payments,$order_shipments);
    if ($this->shopping_cart)
       $this->update_payments_and_shipments($order_info,$order_items,
                                            $order_payments,$order_shipments);
    if (empty($order_info['status'])) $order_info['status'] = 0;
    if (! empty($order_info['customer_id']))
       $customer_id = $order_info['customer_id'];
    else {
       $order_info['customer_id'] = 0;
       $customer_id = 0;   $order_number_is_id = true;
    }
    if ($this->shopping_cart) {
       if ($order_type == ORDER_TYPE) {
          $features = get_cart_config_value('features',$this->db);
          if (isset($order_info['update_inventory']))
             $maintain_inventory = $order_info['update_inventory'];
          else if ($features & MAINTAIN_INVENTORY) $maintain_inventory = true;
          else $maintain_inventory = false;
          if ($features & ORDER_PREFIX) $order_number_is_id = false;
          else $order_number_is_id = true;
          if (! $order_number_is_id)
             $order_info['order_number'] =
                get_cart_config_value('orderprefix').$customer_id.'-'.time();
       }
       else $maintain_inventory = false;
       switch ($order_type) {
          case ORDER_TYPE: $order_record = orders_record_definition();   break;
          case QUOTE_TYPE: $order_record = quotes_record_definition();   break;
          case INVOICE_TYPE:
             $order_record = invoices_record_definition();   break;
          case SALESORDER_TYPE:
             $order_record = salesorders_record_definition();   break;
       }
       foreach ($order_info as $field_name => $field_value) {
          if (isset($order_record[$field_name]))
             $order_record[$field_name]['value'] = $field_value;
       }
       if (! $this->db->insert($table,$order_record)) {
          $this->error = $this->db->error;   return null;
       }
       $order_id = $this->db->insert_id();
       $order_info['id'] = $order_id;
       if (($order_type == ORDER_TYPE) && $order_number_is_id) {
          if ($features & ORDER_BASE_ID)
             $order_number = intval(get_cart_config_value('orderprefix')) +
                             $order_id;
          else if (empty($base_order_number)) $order_number = $order_id;
          else $order_number = $base_order_number + $order_id;
          if ($features & ORDER_PREFIX_ID)
             $order_number = get_cart_config_value('orderprefix').$order_number;
          $query = 'update orders set order_number=? where id=?';
          $query = $this->db->prepare_query($query,$order_number,$order_id);
          $this->db->log_query($query);
          if (! $this->db->query($query)) {
             $this->error = $this->db->error;   return null;
          }
          $order_info['order_number'] = $order_id;
       }

       $billing_info['parent'] = $order_id;
       $billing_record = billing_record_definition();
       $billing_record['parent_type']['value'] = $order_type;
       foreach ($billing_info as $field_name => $field_value) {
          if (isset($billing_record[$field_name]))
             $billing_record[$field_name]['value'] = $field_value;
       }
       if (! $this->db->insert('order_billing',$billing_record)) {
          $this->error = $this->db->error;   return null;
       }

       $shipping_info['parent'] = $order_id;
       $shipping_record = shipping_record_definition();
       $shipping_record['parent_type']['value'] = $order_type;
       foreach ($shipping_info as $field_name => $field_value) {
          if (isset($shipping_record[$field_name]))
             $shipping_record[$field_name]['value'] = $field_value;
       }
       if (! $this->db->insert('order_shipping',$shipping_record)) {
          $this->error = $this->db->error;   return null;
       }

       if (! empty($order_items)) {
          $item_record = item_record_definition();
          $item_record['parent_type']['value'] = $order_type;
          foreach ($order_items as $index => $order_item) {
             $order_items[$index]['parent'] = $order_id;
             $order_item['parent'] = $order_id;
             foreach ($order_item as $field_name => $field_value) {
                if (isset($item_record[$field_name]))
                   $item_record[$field_name]['value'] = $field_value;
             }
             if (! $this->db->insert('order_items',$item_record)) {
                $this->error = $this->db->error;   return null;
             }
             $order_items[$index]['id'] = $this->db->insert_id();
          }
       }

       if (! empty($order_payments)) {
          $payment_record = payment_record_definition();
          $payment_record['parent_type']['value'] = $order_type;
          foreach ($order_payments as $index => $payment_info) {
             $order_payments[$index]['parent'] = $order_id;
             $payment_info['parent'] = $order_id;
             foreach ($payment_info as $field_name => $field_value) {
                if (isset($payment_record[$field_name]))
                   $payment_record[$field_name]['value'] = $field_value;
             }
             if (! $this->db->insert('order_payments',$payment_record)) {
                $this->error = $this->db->error;   return null;
             }
             $order_payments[$index]['id'] = $this->db->insert_id();
          }
       }

       if (! empty($order_shipments)) {
          $shipment_record = shipment_record_definition();
          $shipment_record['parent_type']['value'] = $order_type;
          foreach ($order_shipments as $index => $shipment_info) {
             $order_shipments[$index]['parent'] = $order_id;
             $shipment_info['parent'] = $order_id;
             foreach ($shipment_info as $field_name => $field_value) {
                if (isset($shipment_record[$field_name]))
                   $shipment_record[$field_name]['value'] = $field_value;
             }
             if (! $this->db->insert('order_shipments',$shipment_record)) {
                $this->error = $this->db->error;   return null;
             }
             $shipment_id = $this->db->insert_id();
             $order_shipments[$index]['id'] = $shipment_id;
             if (! empty($shipment_info['items'])) {
                $item_record = shipment_item_record_definition();
                $item_record['parent']['value'] = $shipment_id;
                foreach ($shipment_info['items'] as $index => $shipment_item) {
                   $shipment_info['items']['parent'] = $shipment_id;
                   if (isset($shipment_item['index'])) {
                      $item_index = $shipment_item['index'];
                      if (! isset($order_items[$item_index])) continue;
                      $item_id = $order_items[$item_index]['id'];
                   }
                   else if (! empty($shipment_item['item_id']))
                      $item_id = $shipment_item['item_id'];
                   else continue;
                   $item_record['item_id']['value'] = $item_id;
                   $item_record['qty']['value'] = $shipment_item['qty'];
                   if (! $this->db->insert('order_shipment_items',
                                           $item_record)) {
                      $this->error = $this->db->error;   return null;
                   }
                }
             }
             else foreach ($order_items as $index => $order_item) {
                $item_record = shipment_item_record_definition();
                $item_record['parent']['value'] = $shipment_id;
                $item_record['item_id']['value'] = $order_item['id'];
                $item_record['qty']['value'] = $order_item['qty'];
                if (! $this->db->insert('order_shipment_items',
                                        $item_record)) {
                   $this->error = $this->db->error;   return null;
                }
             }
          }
       }

       if ($maintain_inventory && (! empty($order_items))) {
          foreach ($order_items as $index => $order_item) {
             if (empty($order_item['product_id'])) continue;
             $qty = intval($order_item['qty']);
             $query = 'update product_inventory set qty=ifnull(qty-?,-?) ' .
                      'where (parent=?)';
             if (! empty($order_item['attributes'])) {
                $query .= ' and (attributes=?)';
                $query = $this->db->prepare_query($query,$qty,$qty,
                            $order_item['product_id'],$order_item['attributes']);
             }
             else {
                $query .= ' and ((attributes="") or isnull(attributes))';
                $query = $this->db->prepare_query($query,
                            $qty,$qty,$order_item['product_id']);
             }
             $this->db->log_query($query);
             if (! $this->db->query($query)) {
                $this->error = $this->db->error;   return null;
             }
             if (using_linked_inventory($this->db,$features))
                update_linked_inventory($this->db,null,null,
                   $order_item['product_id'],$order_item['attributes']);
          }
       }
    }

    if (file_exists('engine/modules.php')) require_once 'engine/modules.php';
    else require_once '../engine/modules.php';
    if (module_attached($event)) {
       if (! call_module_event($event,array($this->db,$order_info,
                $billing_info,$shipping_info,$order_items,$order_payments,
                $order_shipments),$this->module,true)) {
          $this->error = 'Unable to add '.$order_label.': ' .
                         get_module_errors();
          return null;
       }
    }

    $activity = 'Added '.$order_label.' #'.$order_id;
    log_activity($activity.' for ' .
                 $this->get_order_customer_name($order_info,$customer_id) .
                 ' (API)');
    if ($this->shopping_cart)
       write_customer_activity($activity.' by API',$customer_id,$this->db);

    return $order_id;
}

function send_order_notifications($order_id,$customer_notify=true)
{
    $this->order_init();
    $order = load_order($this->db,$order_id,$error_msg);
    if (! $order) {
       if (isset($this->db->error)) $this->error = $this->db->error;
       else $this->error = $error_msg;
       return false;
    }
    if (function_exists('custom_order_notifications'))
       custom_order_notifications($order);
    else {
       $notify_flags = get_cart_config_value('notifications',$this->db);
       if (($notify_flags & NOTIFY_NEW_ORDER_CUST) ||
           ($notify_flags & NOTIFY_NEW_ORDER_ADMIN)) {
          require_once '../engine/email.php';
          if ($customer_notify && ($notify_flags & NOTIFY_NEW_ORDER_CUST)) {
             $email = new Email(NEW_ORDER_CUST_EMAIL,
                                array('order' => 'obj','order_obj' => $order));
             if (! $email->send()) log_error($email->error);
             if (! empty($order->customer_id))
                write_customer_activity($email->activity,$order->customer_id,
                                        $order->db);
          }
          if ($notify_flags & NOTIFY_NEW_ORDER_ADMIN) {
             $email = new Email(NEW_ORDER_ADMIN_EMAIL,
                                array('order' => 'obj','order_obj' => $order));
             if (! $email->send()) log_error($email->error);
          }
       }
    }
    return true;
}

function update_order($order_info,$billing_info,$shipping_info,&$order_items,
                      &$order_payments,&$order_shipments,$type=0)
{
    global $order_type;

    $order_type = $type;
    $this->order_init();
    $this->trim_info($order_info);
    switch ($order_type) {
       case ORDER_TYPE:
          $order_label = 'Order';   $table = 'orders';
          $event = 'update_order';   break;
       case QUOTE_TYPE:
          $order_label = 'Quote';   $table = 'quotes';
          $event = 'update_quote';   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   $table = 'invoices';
          $event = 'update_invoice';   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   $table = 'sales_orders';
          $event = 'update_salesorder';   break;
    }
    if (! empty($billing_info)) $this->update_address_info($billing_info);
    if (! empty($shipping_info)) $this->update_address_info($shipping_info);
    if ($this->shopping_cart)
       update_order_info($this->db,$order_info,$billing_info,$shipping_info,
                         $order_items,$order_payments,$order_shipments);
    if ($this->shopping_cart)
       $this->update_payments_and_shipments($order_info,$order_items,
                                            $order_payments,$order_shipments);
    if (empty($order_info['id'])) {
       $this->error = $order_label.' ID is Required for update_order event';
       return false;
    }
    $order_id = $order_info['id'];
    if (! array_key_exists('customer_id',$order_info)) {
       $query = 'select customer_id from '.$table.' where id=?';
       $query = $this->db->prepare_query($query,$order_id);
       $row = $this->db->get_record($query);
       if (! $row) {
          if (isset($this->db->error)) $this->error = $this->db->error;
          else $this->error = $order_label.' Not Found in update_order event';
          return false;
       }
       $customer_id = $row['customer_id'];
    }
    else {
       $query = 'select id from '.$table.' where id=?';
       $query = $this->db->prepare_query($query,$order_id);
       $row = $this->db->get_record($query);
       if (! $row) {
          if (isset($this->db->error)) $this->error = $this->db->error;
          else $this->error = $order_label.' Not Found in update_order event';
          return false;
       }
       if (empty($order_info['customer_id'])) $customer_id = 0;
       else $customer_id = $order_info['customer_id'];
    }
    if (! empty($billing_info)) {
       $billing_info['parent'] = $order_id;
       $billing_info['parent_type'] = $order_type;
    }
    if (! empty($shipping_info)) {
       $shipping_info['parent'] = $order_id;
       $shipping_info['parent_type'] = $order_type;
    }
    if (! empty($order_items)) {
       foreach ($order_items as $index => $order_item) {
          $order_items[$index]['parent'] = $order_id;
          $order_items[$index]['parent_type'] = $order_type;
       }
    }
    if ($this->shopping_cart) {
       $features = get_cart_config_value('features',$this->db);
       switch ($order_type) {
          case ORDER_TYPE: $order_record = orders_record_definition();   break;
          case QUOTE_TYPE: $order_record = quotes_record_definition();   break;
          case INVOICE_TYPE:
             $order_record = invoices_record_definition();   break;
          case SALESORDER_TYPE:
             $order_record = salesorders_record_definition();   break;
       }
       if (count($order_info) > 1) {
          foreach ($order_info as $field_name => $field_value) {
             if (isset($order_record[$field_name]))
                $order_record[$field_name]['value'] = $field_value;
          }
          if (! $this->db->update($table,$order_record)) {
             $this->error = $this->db->error;   return false;
          }
       }

       if (! empty($billing_info)) {
          $billing_record = billing_record_definition();
          $billing_record['parent']['key'] = true;
          foreach ($billing_info as $field_name => $field_value) {
             if (isset($billing_record[$field_name]))
                $billing_record[$field_name]['value'] = $field_value;
          }
          if (! $this->db->update('order_billing',$billing_record)) {
             $this->error = $this->db->error;   return false;
          }
       }

       if (! empty($shipping_info)) {
          $shipping_record = shipping_record_definition();
          $shipping_record['parent']['key'] = true;
          foreach ($shipping_info as $field_name => $field_value) {
             if (isset($shipping_record[$field_name]))
                $shipping_record[$field_name]['value'] = $field_value;
          }
          if (! $this->db->update('order_shipping',$shipping_record)) {
             $this->error = $this->db->error;   return false;
          }
       }

       $query = 'select * from order_items where (parent=?) and ' .
                '(parent_type=?)';
       $query = $this->db->prepare_query($query,$order_id,$order_type);
       $old_order_items = $this->db->get_records($query);
       if (! empty($order_items)) {
          foreach ($order_items as $index => $order_item) {
             if (! isset($order_item['product_id']))
                $order_item['product_id'] = null;
             $item_id = 0;
             if ($old_order_items) {
                foreach ($old_order_items as $old_index => $old_item) {
                   if (($old_item['product_id'] == $order_item['product_id']) &&
                       ($old_item['attributes'] == $order_item['attributes']) &&
                       ($old_item['part_number'] == $order_item['part_number'])) {
                      $item_id = $old_item['id'];
                      unset($old_order_items[$old_index]);   break;
                   }
                }
             }
             $order_items[$index]['id'] = $item_id;
          }
          $item_record = item_record_definition();
          foreach ($order_items as $index => $order_item) {
             foreach ($order_item as $field_name => $field_value) {
                if (isset($item_record[$field_name]))
                   $item_record[$field_name]['value'] = $field_value;
             }
             if (! $order_item['id']) {
                unset($item_record['id']['value']);
                if (! $this->db->insert('order_items',$item_record)) {
                   $this->error = $this->db->error;   return false;
                }
                $order_items[$index]['id'] = $this->db->insert_id();
             }
             else if (! $this->db->update('order_items',$item_record)) {
                $this->error = $this->db->error;   return false;
             }
          }
       }
       if ((! empty($old_order_items)) &&
           (! empty($order_info['delete_old_items']))) {
          $item_record = item_record_definition();
          foreach ($old_order_items as $old_item) {
             $item_record['id']['value'] = $old_item['id'];
             if (! $this->db->delete('order_items',$item_record)) {
                $this->error = $this->db->error;   return false;
             }
          }
       }

       $query = 'select * from order_payments where (parent=?) and ' .
                '(parent_type=?)';
       $query = $this->db->prepare_query($query,$order_id,$order_type);
       $old_payments = $this->db->get_records($query);
       if (! empty($order_payments)) {
          foreach ($order_payments as $index => $payment) {
             if (! isset($payment['payment_date']))
                $payment['payment_date'] = null;
             $payment_id = 0;
             if ($old_payments) {
                foreach ($old_payments as $old_index => $old_payment) {
                   if ($old_payment['payment_date'] == $payment['payment_date']) {
                      $payment_id = $old_payment['id'];
                      unset($old_payments[$old_index]);   break;
                   }
                }
             }
             $order_payments[$index]['id'] = $payment_id;
          }
          $payment_record = payment_record_definition();
          $payment_record['parent']['value'] = $order_id;
          $payment_record['parent_type']['value'] = $order_type;
          foreach ($order_payments as $index => $payment) {
             foreach ($payment as $field_name => $field_value) {
                if (isset($payment_record[$field_name]))
                   $payment_record[$field_name]['value'] = $field_value;
             }
             if (! $payment['id']) {
                unset($payment_record['id']['value']);
                if (! $this->db->insert('order_payments',$payment_record)) {
                   $this->error = $this->db->error;   return false;
                }
                $order_payments[$index]['id'] = $this->db->insert_id();
             }
             else if (! $this->db->update('order_payments',$payment_record)) {
                $this->error = $this->db->error;   return false;
             }
          }
       }
       if ((! empty($old_payments)) &&
           (! empty($order_info['delete_old_payments']))) {
          $payment_record = payment_record_definition();
          foreach ($old_payments as $old_payment) {
             $payment_record['id']['value'] = $old_payment['id'];
             if (! $this->db->delete('order_payments',$payment_record)) {
                $this->error = $this->db->error;   return false;
             }
          }
       }

       $query = 'select * from order_shipments where (parent=?) and ' .
                '(parent_type=?)';
       $query = $this->db->prepare_query($query,$order_id,$order_type);
       $old_shipments = $this->db->get_records($query);
       if (! empty($order_shipments)) {
          foreach ($order_shipments as $index => $shipment) {
             if (! isset($shipment['shipped_date']))
                $shipment['shipped_date'] = null;
             $shipment_id = 0;
             if ($old_shipments) {
                foreach ($old_shipments as $old_index => $old_shipment) {
                   if ($old_shipment['shipped_date'] ==
                       $shipment['shipped_date']) {
                      $shipment_id = $old_shipment['id'];
                      unset($old_shipments[$old_index]);   break;
                   }
                }
             }
             $order_shipments[$index]['id'] = $shipment_id;
          }
          if (empty($order_items) && (! empty($old_order_items)))
             $order_items = $old_order_items;
          $shipment_record = shipment_record_definition();
          $shipment_record['parent']['value'] = $order_id;
          $shipment_record['parent_type']['value'] = $order_type;
          foreach ($order_shipments as $index => $shipment) {
             foreach ($shipment as $field_name => $field_value) {
                if (isset($shipment_record[$field_name]))
                   $shipment_record[$field_name]['value'] = $field_value;
             }
             if (! $shipment['id']) {
                unset($shipment_record['id']['value']);
                if (! $this->db->insert('order_shipments',$shipment_record)) {
                   $this->error = $this->db->error;   return false;
                }
                $shipment_id = $this->db->insert_id();
                $order_shipments[$index]['id'] = $shipment_id;
                $new_shipment = true;
             }
             else {
                $shipment_id = $shipment['id'];
                if (! $this->db->update('order_shipments',$shipment_record)) {
                   $this->error = $this->db->error;   return false;
                }
                if ((! empty($shipment['items'])) ||
                    (! empty($order_info['delete_old_shipments']))) {
                   $query = 'delete from order_shipment_items where parent=?';
                   $query = $this->db->prepare_query($query,$shipment_id);
                   $this->db->log_query($query);
                   if (! $this->db->query($query)) {
                      $this->error = $this->db->error;   return false;
                   }
                }
                $new_shipment = false;
             }
             if ($new_shipment || (! empty($shipment['items'])) ||
                 (! empty($order_info['delete_old_shipments']))) {
                if (! empty($shipment['items'])) {
                   $item_record = shipment_item_record_definition();
                   $item_record['parent']['value'] = $shipment_id;
                   foreach ($shipment['items'] as $item_index => $shipment_item) {
                      $order_shipments[$index]['items'][$item_index]['parent'] =
                         $shipment_id;
                      if (isset($shipment_item['index'])) {
                         if (! isset($order_items[$shipment_item['index']]))
                            continue;
                         $item_id = $order_items[$shipment_item['index']]['id'];
                      }
                      else if (! empty($shipment_item['item_id']))
                         $item_id = $shipment_item['item_id'];
                      else continue;
                      $item_record['item_id']['value'] = $item_id;
                      $item_record['qty']['value'] = $shipment_item['qty'];
                      if (! $this->db->insert('order_shipment_items',
                                              $item_record)) {
                         $this->error = $this->db->error;   return null;
                      }
                   }
                }
                else foreach ($order_items as $index => $order_item) {
                   $item_record = shipment_item_record_definition();
                   $item_record['parent']['value'] = $shipment_id;
                   $item_record['item_id']['value'] = $order_item['id'];
                   $item_record['qty']['value'] = $order_item['qty'];
                   if (! $this->db->insert('order_shipment_items',
                                           $item_record)) {
                      $this->error = $this->db->error;   return null;
                   }
                }
             }
          }
       }
       if ((! empty($old_shipments)) &&
           (! empty($order_info['delete_old_shipments']))) {
          $shipment_record = shipment_record_definition();
          foreach ($old_shipments as $old_shipment) {
             $shipment_record['id']['value'] = $old_shipment['id'];
             $query = 'delete from order_shipment_items where parent=?';
             $query = $this->db->prepare_query($query,$old_shipment['id']);
             $this->db->log_query($query);
             if (! $this->db->query($query)) {
                $this->error = $this->db->error;   return false;
             }
             if (! $this->db->delete('order_shipments',$shipment_record)) {
                $this->error = $this->db->error;   return false;
             }
          }
       }
    }

    if (file_exists('engine/modules.php')) require_once 'engine/modules.php';
    else require_once '../engine/modules.php';
    if (module_attached($event)) {
       $order = load_order($this->db,$order_id,$error_msg);
       if (! $order) {
          $this->error = $error_msg;   return false;
       }
       $order_payments = load_order_payments($order);
       $order_shipments = load_order_shipments($order);
       update_order_info($this->db,$order->info,$order->billing,
                         $order->shipping,$order->items,$order_payments,
                         $order_shipments);
       if (! call_module_event($event,array($this->db,$order->info,
                $order->billing,$order->shipping,$order->items,$order_payments,
                $order_shipments),$this->module,true)) {
          $this->error = 'Unable to update '.$order_label.': ' .
                         get_module_errors();
          return false;
       }
       $order_info = $order->info;
    }
    else if (count($order_info) == 1) {
       $query = 'select * from '.$table.' where id=?';
       $query = $this->db->prepare_query($query,$order_id);
       $order_info = $this->db->get_record($query);
    }

    $activity = 'Updated '.$order_label.' #'.$order_id;
    log_activity($activity.' for ' .
                 $this->get_order_customer_name($order_info,$customer_id) .
                 ' (API)');
    if ($this->shopping_cart)
       write_customer_activity($activity.' by API',$customer_id,$this->db);

    return true;
}

function delete_order($order_info,$type=0)
{
    global $order_type;

    $order_type = $type;
    $this->order_init();
    $this->trim_info($order_info);
    switch ($order_type) {
       case ORDER_TYPE:
          $order_label = 'Order';   $event = 'delete_order';   break;
       case QUOTE_TYPE:
          $order_label = 'Quote';   $event = 'delete_quote';   break;
       case INVOICE_TYPE:
          $order_label = 'Invoice';   $event = 'delete_invoice';   break;
       case SALESORDER_TYPE:
          $order_label = 'Sales Order';   $event = 'delete_salesorder';
          break;
    }
    $order_id = $order_info['id'];
    if (empty($order_info['to_invoice'])) {
       $activity = 'Deleted '.$order_label.' #'.$order_id;
       if ($this->shopping_cart) {
          $customer_id = $this->get_order_customer_id($order_id);
          if (! delete_order_record($this->db,$order_info,$error,
                                    $this->module,$order_type)) {
             $this->error = $error;   return false;
          }
          if ($customer_id)
             write_customer_activity($activity.' by API',$customer_id,
                                     $this->db);

       }
       log_activity($activity.' (API)');
    }
    else {
       if (file_exists('engine/modules.php'))
          require_once 'engine/modules.php';
       else require_once '../engine/modules.php';
       if (module_attached($event)) {
          if (! call_module_event($event,
                   array($this->db,$order_info,null,null),
                   $this->module,true)) {
             $this->error = get_module_errors();   return false;
          }
       }
    }
    return true;
}

function add_quote($quote_info,$billing_info,$shipping_info,&$quote_items)
{
    $quote_payments = null;   $quote_shipments = null;
    return $this->add_order($quote_info,$billing_info,$shipping_info,
                            $quote_items,$quote_payments,$quote_shipments,1);
}

function update_quote($quote_info,$billing_info,$shipping_info,
                      &$quote_items)
{
    $quote_payments = null;   $quote_shipments = null;
    return $this->update_order($quote_info,$billing_info,$shipping_info,
              $quote_items,$quote_payments,$quote_shipments,1);
}

function delete_quote($quote_info)
{
    return $this->delete_order($quote_info,1);
}

function add_invoice($invoice_info,$billing_info,$shipping_info,$invoice_items,
   $invoice_payments=null,$invoice_shipments=null)
{
    return $this->add_order($invoice_info,$billing_info,$shipping_info,
                     $invoice_items,$invoice_payments,$invoice_shipments,2);
}

function update_invoice($invoice_info,$billing_info,$shipping_info,
   $invoice_items,$invoice_payments=null,$invoice_shipments=null)
{
    return $this->update_order($invoice_info,$billing_info,$shipping_info,
                     $invoice_items,$invoice_payments,$invoice_shipments,2);
}

function delete_invoice($invoice_info)
{
    return $this->delete_order($invoice_info,2);
}

function add_salesorder($order_info,$billing_info,$shipping_info,$order_items)
{
    $order_payments = null;   $order_shipments = null;
    return $this->add_order($order_info,$billing_info,$shipping_info,
                     $order_items,$order_payments,$order_shipments,3);
}

function update_salesorder($order_info,$billing_info,$shipping_info,
                           $order_items)
{
    $order_payments = null;   $order_shipments = null;
    return $this->update_order($order_info,$billing_info,$shipping_info,
                     $order_items,$order_payments,$order_shipments,3);
}

function delete_salesorder($order_info)
{
    return $this->delete_order($order_info,3);
}

function add_payment($order_id,$payment_info,&$first_payment)
{
    $this->order_init();
    $this->trim_info($payment_info);
    $payment_record = payment_record_definition();
    $payment_record['parent']['value'] = $order_id;
    foreach ($payment_info as $field_name => $field_value) {
       if (isset($payment_record[$field_name]))
          $payment_record[$field_name]['value'] = $field_value;
    }
    if (! $this->db->insert('order_payments',$payment_record)) {
       $this->error = $this->db->error;   return null;
    }
    $payment_id = $this->db->insert_id();
    $activity = 'Added Payment #'.$payment_id.' to Order #'.$order_id;
    log_activity($activity.' (API)');
    $customer_id = $this->get_order_customer_id($order_id);
    if ($customer_id)
       write_customer_activity($activity.' by API',$customer_id,$this->db);

    return $payment_id;
}

function update_payment($order_id,$payment_id,$payment_info)
{
    $this->order_init();
    $this->trim_info($payment_info);
    $payment_record = payment_record_definition();
    $payment_record['id']['value'] = $payment_id;
    foreach ($payment_info as $field_name => $field_value) {
       if (isset($payment_record[$field_name]))
          $payment_record[$field_name]['value'] = $field_value;
    }
    if (! $this->db->update('order_payments',$payment_record)) {
       $this->error = $this->db->error;   return false;
    }
    $activity = 'Updated Payment #'.$payment_id.' for Order #'.$order_id;
    log_activity($activity.' (API)');
    $customer_id = $this->get_order_customer_id($order_id);
    if ($customer_id)
       write_customer_activity($activity.' by API',$customer_id,$this->db);
    return true;
}

function delete_payment($order_id,$payment_id)
{
    $this->order_init();
    $query = 'delete from order_payments where id=?';
    $query = $this->db->prepare_query($query,$payment_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
    $activity = 'Deleted Payment #'.$payment_id.' from Order #'.$order_id;
    log_activity($activity.' (API)');
    $customer_id = $this->get_order_customer_id($order_id);
    if ($customer_id)
       write_customer_activity($activity.' by API',$customer_id,$this->db);
    return true;
}

function vendor_init()
{
    require_once 'vendors-common.php';
}

function add_vendor($vendor_info)
{
    $this->vendor_init();
    $this->update_address_info($vendor_info);
    $vendor_record = vendor_record_definition();
    foreach ($vendor_info as $field_name => $field_value) {
       if (isset($vendor_record[$field_name]))
          $vendor_record[$field_name]['value'] = $field_value;
    }
    if (! isset($vendor_info['last_modified']))
       $vendor_record['last_modified']['value'] = time();
    if (! $this->db->insert('vendors',$vendor_record)) {
       $this->error = $this->db->error;   return null;
    }
    $vendor_id = $this->db->insert_id();
    require_once '../engine/modules.php';
    if (module_attached('add_vendor')) {
       if (! call_module_event('add_vendor',array($this->db,$vendor_info),
                               $this->module)) {
          $this->error = get_module_errors();   return null;
       }
    }
    log_activity('Added Vendor '.$vendor_record['name']['value'] .
                 ' (#'.$vendor_id.') (API)');
    return $vendor_id;
}

function update_vendor($vendor_info)
{
    $this->vendor_init();
    $this->update_address_info($vendor_info);
    $vendor_record = vendor_record_definition();
    foreach ($vendor_info as $field_name => $field_value) {
       if (isset($vendor_record[$field_name]))
          $vendor_record[$field_name]['value'] = $field_value;
    }
    if (! isset($vendor_info['last_modified']))
       $vendor_record['last_modified']['value'] = time();
    if (! $this->db->update('vendors',$vendor_record)) {
       $this->error = $this->db->error;   return false;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_vendor')) {
       if (! call_module_event('update_vendor',array($this->db,$vendor_info),
                               $this->module)) {
          $this->error = get_module_errors();   return false;
       }
    }
    log_activity('Updated Vendor '.$vendor_record['name']['value'] .
                 ' (#'.$vendor_record['id']['value'].') (API)');
    return true;
}

function delete_vendor($vendor_id)
{
    $this->vendor_init();
    $query = 'delete from vendor_imports where parent=?';
    $query = $this->db->prepare_query($query,$vendor_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
    $query = 'delete from vendor_markups where parent=?';
    $query = $this->db->prepare_query($query,$vendor_id);
    $this->db->log_query($query);
    if (! $this->db->query($query)) {
       $this->error = $this->db->error;   return false;
    }
    require_once '../engine/modules.php';
    if (module_attached('delete_vendor')) {
       $query = 'select * from vendors where id=?';
       $query = $this->db->prepare_query($query,$vendor_id);
       $vendor_info = $this->db->get_record($query);
    }
    $vendor_record = vendor_record_definition();
    $vendor_record['id']['value'] = $vendor_id;
    if (! $this->db->delete('vendors',$vendor_record)) {
       $this->error = $this->db->error;   return false;
    }
    if (module_attached('delete_vendor')) {
       if (! call_module_event('delete_vendor',array($this->db,$vendor_info),
                               $this->module)) {
          $this->error = get_module_errors();   return false;
       }
    }
    log_activity('Deleted Vendor #'.$vendor_id.' (API)');
    return true;
}

function close()
{
    if ($this->newdb) $this->db->close();
    putenv('REMOTE_USER='.$this->old_remote_user);
    error_reporting($this->old_error_reporting); 
    ini_set('display_errors',$this->old_display_errors);
    ini_set('track_errors',$this->old_track_errors);
    ini_set('error_log',$this->old_error_log);
    ini_set('log_errors',$this->old_log_errors);
}

}

?>
