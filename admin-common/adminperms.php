<?php
/*
        Inroads Control Panel/Shopping Cart - Admin Permissions Processing

                       Written 2009-2019 by Randall Severy
                        Copyright 2009-2019 Inroads, LLC
*/

if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/adminperms.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if (! isset($catalog_site)) {
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
}
if (! isset($cms_site)) {
   if (isset($db_host)) $cms_site = false;
   else $cms_site = true;
}

define('PERM_HEADER',-1);
define('USER_PERM',0);
define('MODULE_PERM',1);
define('CUSTOM_PERM',2);
define('TAB_CONTAINER',3);

define('CART_CONTAINER',1);
define('CATALOG_CONTAINER',2);
define('MANAGEMENT_CONTAINER',3);
define('MODULES_CONTAINER',4);
define('CONTENT_CONTAINER',5);
define('BI_CONTAINER',6);
define('RESOURCES_CONTAINER',7);
define('SYSTEM_CONTAINER',8);
define('MARKETING_CONTAINER',9);

define('ADMIN_TAB_PERM',8);
define('TEMPLATES_TAB_PERM',1);
define('REPORTS_TAB_PERM',2);
define('FORMS_TAB_PERM',4);

if (isset($prefs_cookie)) {
   define('ADMIN_USERS_TAB_PERM',16);
   define('CONFIG_TAB_PERM',8388608);
   define('DATA_TAB_PERM',16777216);
}
else define('ADMIN_USERS_BUTTON_PERM',16);
define('SYSTEM_CONFIG_BUTTON_PERM',32);
define('WEB_SITES_BUTTON_PERM',8192);
define('CPANEL_BUTTON_PERM',524288);
define('IMPORT_DATA_BUTTON_PERM',131072);
define('EXPORT_DATA_BUTTON_PERM',262144);
define('GOOGLE_BUTTON_PERM',1048576);
define('TICKETS_BUTTON_PERM',2097152);
define('PUBLISH_BUTTON_PERM',33554432);
define('MEDIA_TAB_PERM',4);

if ($shopping_cart || $catalog_site) {
   define('CATEGORIES_TAB_PERM',512);
   define('PRODUCTS_TAB_PERM',1024);
   define('REVIEWS_TAB_PERM',268435456);
}
if ($shopping_cart) {
   define('ORDERS_TAB_PERM',128);
   define('ACCOUNTS_TAB_PERM',64);
   define('CUSTOMERS_TAB_PERM',256);
   define('ATTRIBUTES_TAB_PERM',2048);
   define('COUPONS_TAB_PERM',4096);

   define('ADD_ORDER_BUTTON_PERM',4194304);
   define('EDIT_ORDER_BUTTON_PERM',16384);
   define('DELETE_ORDER_BUTTON_PERM',32768);
   define('RMAS_TAB_PERM',134217728);
   define('VENDORS_TAB_PERM',536870912);
   define('SEARCHES_TAB_PERM',1073741824);
}
if ($shopping_cart || $catalog_site)
   define('CART_CONFIG_BUTTON_PERM',65536);

$event_names = array('module_info','startup','main_tabs','logout','admin_tabs',
   'admin_buttons','add_user_tabs','add_user_fields','user_tabs','user_head',
   'setup_perms','add_user','update_user','change_password','delete_user',
   'change_skin','change_editor','add_account','update_account',
   'delete_account','add_customer','update_customer','delete_customer',
   'add_customer_tabs','customer_tabs',
   'add_shipping','update_shipping','delete_shipping','add_lead',
   'update_lead','delete_lead','add_subscription',
   'add_category','update_category','delete_category','add_product',
   'update_product','delete_product','add_inventory','update_inventory',
   'delete_inventory','add_attribute','update_attribute','delete_attribute',
   'add_attr_option','update_attr_option','delete_attr_option','add_order',
   'update_order','delete_order','add_quote','update_quote','delete_quote',
   'add_invoice','update_invoice','delete_invoice','add_salesorder',
   'update_salesorder','delete_salesorder','add_vendor',
   'update_vendor','delete_vendor','add_config_tabs','config_head',
   'config_tabs','update_config','add_cart_config_tabs','cart_config_head',
   'cart_config_tabs','update_cart_config','set_hostname',
   'set_account_password','load_call_matches','update_head',
   'display_custom_buttons','display_custom_fields','custom_command',
   'update_template_tables','save_view','add_cart_item','get_other_products',
   'report_log_file_info','init_reports','run_report','reports_head',
   'add_report_log_file_type','add_report_rows','update_cart_index_cart',
   'write_checkout_button','start_checkout','write_analytics',
   'customer_logout');

function add_user_perm(&$perms_choices,$new_perm,$perm_type,$label,$level,
                       $before_perm=null,$before_perm_type=USER_PERM,
                       $add_before=true)
{
    global $cms_available;

    if ($before_perm == null) {
       $new_perms_choices = array();
       $new_perms_choices[] = array($new_perm,$label,$level,$perm_type);
       $perms_choices = array_merge($perms_choices,$new_perms_choices);
       return;
    }
    if (($before_perm == ADMIN_TAB_PERM) && ($before_perm_type == USER_PERM) &&
        isset($cms_available) && $cms_available && ($new_perm != CMS_TAB_PERM)) {
       $before_perm = CMS_TAB_PERM;   $before_perm_type = MODULE_PERM;
    }
    $insert_pos = -1;   $index = 0;   $current_level = -1;
    foreach ($perms_choices as $index => $perm_info) {
       if ($perm_info[2] == $current_level) {
          $insert_pos = $index;   break;
       }
       if (($perm_info[0] == $before_perm) &&
           ($perm_info[3] == $before_perm_type)) {
          if ($add_before) $insert_pos = $index;
          else if ($level > $perm_info[2]) {
             $current_level = $perm_info[2];
             $index++;   continue;
          }
          else $insert_pos = $index + 1;
          break;
       }
       else $index++;
    }
    if ($insert_pos == -1) {
       $new_perms_choices = array();
       $new_perms_choices[] = array($new_perm,$label,$level,$perm_type);
       $perms_choices = array_merge($perms_choices,$new_perms_choices);
       return;
    }
    $remaining_choices = array_splice($perms_choices,$insert_pos);
    $perms_choices[] = array($new_perm,$label,$level,$perm_type);
    $perms_choices = array_merge($perms_choices,$remaining_choices);
}

function remove_user_perm(&$perms_choices,$perm,$perm_type,$label=null)
{
    foreach ($perms_choices as $index => $perm_info) {
       if (($perm_info[0] == $perm) && ($perm_info[3] == $perm_type)) {
          if ($label && ($label != $perm_info[1])) continue;
          array_splice($perms_choices,$index,1);   return;
       }
    }
}

function move_user_perm(&$perms_choices,$perm,$perm_type,$level,
                        $before_perm=null,$before_perm_type=USER_PERM,
                        $add_before=true)
{
    $perm_data = null;
    foreach ($perms_choices as $perm_info) {
       if (($perm_info[0] == $perm) && ($perm_info[3] == $perm_type)) {
          $perm_data = $perm_info;   break;
       }
    }
    if ($perm_data === null) return;
    remove_user_perm($perms_choices,$perm,$perm_type);
    add_user_perm($perms_choices,$perm,$perm_type,$perm_data[1],$level,
                  $before_perm,$before_perm_type,$add_before);
}

function get_admin_perms_choices()
{
    global $shopping_cart,$catalog_site,$cpanel_base,$google_email;
    global $product_label,$products_label,$categories_label,$enable_add_order;
    global $enable_punch_list,$enable_support_requests,$enable_wholesale;
    global $enable_multisite,$prefs_cookie,$admin_directory;
    global $use_development_site,$enable_rmas,$enable_reviews;
    global $enable_vendors,$search_engine,$disable_catalog_config;

    if (! isset($enable_punch_list)) $enable_punch_list = false;
    if (! isset($enable_support_requests)) $enable_support_requests = false;
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    if (! isset($disable_catalog_config)) $disable_catalog_config = false;

    if ($shopping_cart) {
       if (! isset($product_label)) $product_label = 'Product';
       if (! isset($products_label)) $products_label = $product_label.'s';
       if (! isset($categories_label)) $categories_label = 'Categories';
       if (isset($prefs_cookie)) {
          $admin_perms_choices = array(
             array(CART_CONTAINER,'Cart',0,TAB_CONTAINER),
             array(ORDERS_TAB_PERM,'Orders',1,USER_PERM),
             array(ADD_ORDER_BUTTON_PERM,'New Order',2,USER_PERM),
             array(EDIT_ORDER_BUTTON_PERM,'Edit Order',2,USER_PERM),
             array(DELETE_ORDER_BUTTON_PERM,'Delete Order',2,USER_PERM),
             array(RMAS_TAB_PERM,'RMAs',1,USER_PERM),
             array(ACCOUNTS_TAB_PERM,'Accounts',1,USER_PERM),
             array(CUSTOMERS_TAB_PERM,'Customers',1,USER_PERM),
             array(COUPONS_TAB_PERM,'Coupons',1,USER_PERM),
             array(CATALOG_CONTAINER,'Catalog',0,TAB_CONTAINER),
             array(CATEGORIES_TAB_PERM,$categories_label,1,USER_PERM),
             array(PRODUCTS_TAB_PERM,$products_label,1,USER_PERM),
             array(ATTRIBUTES_TAB_PERM,'Attributes',1,USER_PERM),
             array(VENDORS_TAB_PERM,'Vendors',1,USER_PERM),
             array(REVIEWS_TAB_PERM,'Reviews',1,USER_PERM),
             array(SEARCHES_TAB_PERM,'Searches',1,USER_PERM));
       }
       else {
          $admin_perms_choices = array(
             array(ORDERS_TAB_PERM,'Orders',0,USER_PERM),
             array(ADD_ORDER_BUTTON_PERM,'New Order',1,USER_PERM),
             array(EDIT_ORDER_BUTTON_PERM,'Edit Order',1,USER_PERM),
             array(DELETE_ORDER_BUTTON_PERM,'Delete Order',1,USER_PERM),
             array(RMAS_TAB_PERM,'RMAs',0,USER_PERM),
             array(ACCOUNTS_TAB_PERM,'Accounts',0,USER_PERM),
             array(CUSTOMERS_TAB_PERM,'Customers',0,USER_PERM),
             array(CATEGORIES_TAB_PERM,$categories_label,0,USER_PERM),
             array(PRODUCTS_TAB_PERM,$products_label,0,USER_PERM),
             array(ATTRIBUTES_TAB_PERM,'Attributes',0,USER_PERM),
             array(VENDORS_TAB_PERM,'Vendors',0,USER_PERM),
             array(REVIEWS_TAB_PERM,'Reviews',0,USER_PERM),
             array(SEARCHES_TAB_PERM,'Searches',0,USER_PERM));
       }
       if ((! isset($enable_add_order)) || (! $enable_add_order))
          remove_user_perm($admin_perms_choices,ADD_ORDER_BUTTON_PERM,
                           USER_PERM);
       if ((! isset($enable_wholesale)) || (! $enable_wholesale))
          remove_user_perm($admin_perms_choices,ACCOUNTS_TAB_PERM,
                           USER_PERM);
       if ((! isset($enable_rmas)) || (! $enable_rmas))
          remove_user_perm($admin_perms_choices,RMAS_TAB_PERM,
                           USER_PERM);
       if ((! isset($enable_vendors)) || (! $enable_vendors))
          remove_user_perm($admin_perms_choices,VENDORS_TAB_PERM,
                           USER_PERM);
       if ((! isset($enable_reviews)) || (! $enable_reviews))
          remove_user_perm($admin_perms_choices,REVIEWS_TAB_PERM,
                           USER_PERM);
       if (! isset($search_engine))
          remove_user_perm($admin_perms_choices,SEARCHES_TAB_PERM,
                           USER_PERM);
    }
    else if ($catalog_site) {
       if (! isset($product_label)) $product_label = 'Product';
       if (! isset($products_label)) $products_label = $product_label.'s';
       if (! isset($categories_label)) $categories_label = 'Categories';
       if (isset($prefs_cookie))
          $admin_perms_choices = array(
             array(CATALOG_CONTAINER,'Catalog',0,TAB_CONTAINER),
             array(CATEGORIES_TAB_PERM,$categories_label,1,USER_PERM),
             array(PRODUCTS_TAB_PERM,$products_label,1,USER_PERM),
             array(REVIEWS_TAB_PERM,'Reviews',1,USER_PERM));
       else $admin_perms_choices = array(
               array(CATEGORIES_TAB_PERM,$categories_label,0,USER_PERM),
               array(PRODUCTS_TAB_PERM,$products_label,0,USER_PERM),
               array(REVIEWS_TAB_PERM,'Reviews',0,USER_PERM));
       if ((! isset($enable_reviews)) || (! $enable_reviews))
          remove_user_perm($admin_perms_choices,REVIEWS_TAB_PERM,
                           USER_PERM);
    }
    else $admin_perms_choices = array();
    if (! isset($prefs_cookie)) {
       add_user_perm($admin_perms_choices,TEMPLATES_TAB_PERM,USER_PERM,
                     'Templates',0);
       add_user_perm($admin_perms_choices,REPORTS_TAB_PERM,USER_PERM,
                     'Reports',0);
       add_user_perm($admin_perms_choices,FORMS_TAB_PERM,USER_PERM,
                     'Forms',0);
       if ($shopping_cart)
          add_user_perm($admin_perms_choices,COUPONS_TAB_PERM,USER_PERM,
                        'Coupons',0);
    }
    if (isset($prefs_cookie)) {
       add_user_perm($admin_perms_choices,MANAGEMENT_CONTAINER,TAB_CONTAINER,
                     'Management',0);
       add_user_perm($admin_perms_choices,MODULES_CONTAINER,TAB_CONTAINER,
                     'Modules',0);
       add_user_perm($admin_perms_choices,CONTENT_CONTAINER,TAB_CONTAINER,
                     'Content',0);
       add_user_perm($admin_perms_choices,TEMPLATES_TAB_PERM,USER_PERM,
                     'E-Mail Templates',1);
       add_user_perm($admin_perms_choices,FORMS_TAB_PERM,USER_PERM,
                     'Forms',1);
       add_user_perm($admin_perms_choices,MARKETING_CONTAINER,TAB_CONTAINER,
                     'Marketing',0);
       add_user_perm($admin_perms_choices,BI_CONTAINER,TAB_CONTAINER,
                     'Business Intelligence',0);
       add_user_perm($admin_perms_choices,REPORTS_TAB_PERM,USER_PERM,
                     'Reports',1);
       add_user_perm($admin_perms_choices,RESOURCES_CONTAINER,TAB_CONTAINER,
                     'Resources',0);
       add_user_perm($admin_perms_choices,SYSTEM_CONTAINER,TAB_CONTAINER,
                     'System',0);
    }
    else add_user_perm($admin_perms_choices,ADMIN_TAB_PERM,USER_PERM,'Admin',0);
    if (isset($prefs_cookie)) {
       add_user_perm($admin_perms_choices,ADMIN_USERS_TAB_PERM,USER_PERM,
                     'Admin Users',1);
       add_user_perm($admin_perms_choices,IMPORT_DATA_BUTTON_PERM,USER_PERM,
                     'Import Data',1);
       add_user_perm($admin_perms_choices,EXPORT_DATA_BUTTON_PERM,USER_PERM,
                     'Export Data',1);
       add_user_perm($admin_perms_choices,SYSTEM_CONFIG_BUTTON_PERM,USER_PERM,
                     'System Config',1);
       if ($disable_catalog_config) {
          if ($shopping_cart)
             add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                           'Cart Config',1);
       }
       else if ($shopping_cart)
          add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                        'Cart/Catalog Config',1);
       else if ($catalog_site)
          add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                        'Catalog Config',1);
/*
       if (isset($cpanel_base))
          add_user_perm($admin_perms_choices,CPANEL_BUTTON_PERM,USER_PERM,
                        'Site Config',1);
*/
       if (isset($enable_multisite) && $enable_multisite)
          add_user_perm($admin_perms_choices,WEB_SITES_BUTTON_PERM,USER_PERM,
                        'Web Sites',1);
       add_user_perm($admin_perms_choices,MEDIA_TAB_PERM,USER_PERM,
                     'Media Libraries',1);
       if (isset($google_email))
          add_user_perm($admin_perms_choices,GOOGLE_BUTTON_PERM,USER_PERM,
                        'Google Analytics',1);
       if ($enable_punch_list)
          add_user_perm($admin_perms_choices,TICKETS_BUTTON_PERM,USER_PERM,
                        'Punch List',1);
       else if ($enable_support_requests)
          add_user_perm($admin_perms_choices,TICKETS_BUTTON_PERM,USER_PERM,
                        'Support Requests',1);
       if (isset($use_development_site) && $use_development_site)
          add_user_perm($admin_perms_choices,PUBLISH_BUTTON_PERM,USER_PERM,
                        'Publish Live Site',1);
    }
    else {
       add_user_perm($admin_perms_choices,ADMIN_USERS_BUTTON_PERM,USER_PERM,
                     'Admin Users',1);
       add_user_perm($admin_perms_choices,SYSTEM_CONFIG_BUTTON_PERM,USER_PERM,
                     'System Config',1);
       if ($disable_catalog_config) {
          if ($shopping_cart)
             add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                           'Cart Config',1);
       }
       else if ($shopping_cart)
          add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                        'Cart/Catalog Config',1);
       else if ($catalog_site)
          add_user_perm($admin_perms_choices,CART_CONFIG_BUTTON_PERM,USER_PERM,
                        'Catalog Config',1);
       if (isset($enable_multisite) && $enable_multisite)
          add_user_perm($admin_perms_choices,WEB_SITES_BUTTON_PERM,USER_PERM,
                        'Web Sites',1);
       add_user_perm($admin_perms_choices,IMPORT_DATA_BUTTON_PERM,USER_PERM,
                     'Import Data',1);
       add_user_perm($admin_perms_choices,EXPORT_DATA_BUTTON_PERM,USER_PERM,
                     'Export Data',1);
/*
       if (isset($cpanel_base))
          add_user_perm($admin_perms_choices,CPANEL_BUTTON_PERM,USER_PERM,
                        'Site Config',1);
*/
       add_user_perm($admin_perms_choices,MEDIA_TAB_PERM,USER_PERM,
                     'Media Libraries',1);
       if (isset($google_email))
          add_user_perm($admin_perms_choices,GOOGLE_BUTTON_PERM,USER_PERM,
                        'Google Analytics',1);
       if ($enable_punch_list)
          add_user_perm($admin_perms_choices,TICKETS_BUTTON_PERM,USER_PERM,
                        'Punch List',1);
       else if ($enable_support_requests)
          add_user_perm($admin_perms_choices,TICKETS_BUTTON_PERM,USER_PERM,
                        'Support Requests',1);
    }
    require_once '../engine/modules.php';
    call_module_event('setup_perms',array(&$admin_perms_choices));
    return $admin_perms_choices;
}

function set_user_perm_label(&$perms_choices,$perm,$perm_type,$label)
{
    foreach ($perms_choices as $index => $perm_info) {
       if (($perm_info[0] == $perm) && ($perm_info[3] == $perm_type)) {
          $perms_choices[$index][1] = $label;   return;
       }
    }
}

function get_user_perms(&$user_perms,&$module_perms,&$custom_perms,$db=null)
{
    global $login_cookie,$cms_site;

    $admin_user = get_cookie($login_cookie);
    if ($admin_user === null) {
       $user_perms = 0;   $module_perms = 0;   $custom_perms = 0;
       return false;
    }
    if ($cms_site) {
       $user_perms = ADMIN_TAB_PERM|CPANEL_BUTTON_PERM|GOOGLE_BUTTON_PERM;
       $module_perms = 1;   $custom_perms = 0;   return true;
    }
    if (! isset($db)) $db = new DB;
    $query = 'select perms,module_perms,custom_perms from users where username=';
    if ($db->check_encrypted_field('users','username')) $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $db->prepare_query($query,$admin_user);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       return false;
    }
    $user_perms = $row['perms'];
    $module_perms = $row['module_perms'];
    $custom_perms = $row['custom_perms'];
    return true;
}

function validate_admin_user()
{
    global $cms_site,$cms_module,$cms_program,$cms_base_url,$cms_use_http;
    global $ldap_host;

    $username = get_form_field('username');
    if ($username == 'default') {
       http_response(422,'Invalid Username');   return;
    }
    $password = get_form_field('password');

    if ($cms_site) {
       require_once $cms_module;
       $wsd = new WSD($cms_program,$username,$cms_base_url,$password);
       if (isset($cms_use_http) && $cms_use_http) $wsd->use_http(true);
       $status = $wsd->login($username,$password,$_SERVER['REMOTE_ADDR']);
       if ($status != 200) {
          http_response($status,$wsd->error);   return;
       }
    }
    else {
       $db = new DB;
       if ((! $db) || isset($db->error)) {
          if ($db) http_response(422,$db->error);
          else http_response(422,'Unable to open database');
          return;
       }
       $ldap_user = false;
       $query = 'select username,password from users where username=';
       if ($db->check_encrypted_field('users','username'))
          $query .= '%ENCRYPT%(?)';
       else $query .= '?';
       $query = $db->prepare_query($query,$username);
       $row = $db->get_record($query);
       if ((! $row) && isset($db->error)) {
          http_response(422,$db->error.' (Username='.$username.')');
          return;
       }
       if ((! $row) && (! empty($ldap_host))) {
          require_once 'ldap.php';
          $row = lookup_ldap_user($username,$password);
          if ($row) $ldap_user = true;
       }
       if (! $row) {
          http_response(404,'Invalid Username or Password');
          return;
       }
       if (! $ldap_user) $db->decrypt_record('users',$row);
       if ($password != $row['password']) {
          http_response(404,'Invalid Username or Password');   return;
       }
       if (! get_form_field('validateonly')) {
          $ip_address = $_SERVER['REMOTE_ADDR'];
          $query = 'update users set last_login=?,ip_address=? where username=';
          if ($db->check_encrypted_field('users','username'))
             $query .= '%ENCRYPT%(?)';
          else $query .= '?';
          $query = $db->prepare_query($query,time(),$ip_address,$username);
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,$db->error);   return;
          }
       }
    }
    http_response(201,'Validation Successful');
}

function set_admin_cookies($user_perms,$module_perms,$custom_perms)
{
    global $login_cookie,$admin_cookie_domain;

    $admin_user = get_cookie($login_cookie);
    if ($admin_user === null) return;
    if (! isset($admin_cookie_domain)) $admin_cookie_domain = null;
    setcookie($login_cookie,$admin_user,time() + 86400,'/',$admin_cookie_domain);
}

function get_user_prefs($db=null,$prefs_user=null,$pref_name=null)
{
    global $login_cookie;

    if ($prefs_user === null) $prefs_user = get_cookie($login_cookie);
    if ($prefs_user === null) {
       if ($pref_name) return null;
       return array();
    }
    if (! isset($db)) $db = new DB;
    if ($db === null) return array();
    $query = 'select pref_name,pref_value from user_prefs where (username=?)';
    if ($pref_name) {
       $query .= ' and (pref_name=?)';
       $query = $db->prepare_query($query,$prefs_user,$pref_name);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error)) log_error('Database Error: '.$db->error);
          return null;
       }
       return $row['pref_value'];
    }
    $query = $db->prepare_query($query,$prefs_user);
    $user_prefs = $db->get_records($query,'pref_name','pref_value');
    if (! $user_prefs) {
       if (isset($db->error)) log_error('Database Error: '.$db->error);
       return array();
    }
    return $user_prefs;
}

?>
