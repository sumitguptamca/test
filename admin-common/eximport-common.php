<?php
/*
         Inroads Control Panel/Shopping Cart - Common Export/Import Data Functions

                         Written 2012-2018 by Randall Severy
                          Copyright 2012-2018 Inroads, LLC
*/

$convert_date_fields = array(
   'users' => array('creation_date','modified_date','last_login'),
   'cart' => array('create_date'),
   'wishlist' => array('create_date'),
   'customers' => array('create_date'),
   'orders' => array('order_date','updated_date'),
   'order_payments' => array('payment_date'),
   'order_shipments' => array('shipped_date'),
   'categories' => array('last_modified'),
   'products' => array('last_modified'),
   'coupons' => array('start_date','end_date'),
   'attributes' => array('last_modified'),
   'attribute_options' => array('last_modified'),
   'forms' => array('creation_date'),
   'registry' => array('event_date','create_date'),
   'tickets' => array('submitted','assigned','verified'),
   'ticket_attachments' => array('upload_date')
);

function sort_module_info($a,$b)
{
    $retval = strcasecmp($a['name'],$b['name']);
    if ($retval != 0) return $retval;
    return 1;
}

function load_module_dbs()
{
    global $db_host,$db_name;

    require_once '../engine/modules.php';

    $modules = array();
    call_module_event('module_info',array(&$modules));
    usort($modules,'sort_module_info');

    $module_dbs = array();
    foreach ($modules as $module) {
       if (((! empty($module['db_host'])) &&
            ($module['db_host'] != $db_host)) ||
           ((! empty($module['db_name'])) &&
            ($module['db_name'] != $db_name)))
          $module_dbs[$module['modulename']] = $module['name'];
    }

    return $module_dbs;
}

function add_module_row($dialog)
{
    $module_dbs = load_module_dbs();
    if (count($module_dbs) > 0) {
       $dialog->start_row('Module:','middle');
       $dialog->start_choicelist('Module','select_module();');
       $dialog->add_list_item('','Main System',true);
       while (list($module,$name) = each($module_dbs))
          $dialog->add_list_item($module,$name,false);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
}

function set_module_db_info($module)
{
    global $admin_directory;

    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    require_once $admin_directory.'modules/'.$module.'.php';
    $function_name = $module.'_module_info';
    $modules = array();
    $function_name($modules);
    $module_info = $modules[0];
    save_db_info();
    set_db_info($module_info['db_host'],$module_info['db_name'],
                $module_info['db_user'],$module_info['db_pass']);
}

?>
