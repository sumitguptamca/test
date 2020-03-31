<?php
/*
            Inroads Control Panel/Shopping Cart - Public Forms Functions

                        Written 2008-2018 by Randall Severy
                         Copyright 2008-2018 Inroads, LLC
*/

if (file_exists('admin/forms-config.php')) {
   require_once 'engine/ui.php';
   require_once 'engine/db.php';
   require 'admin/forms-config.php';
}
else if (file_exists('../admin/forms-config.php')) {
   require_once '../engine/ui.php';
   require_once '../engine/db.php';
   require '../admin/forms-config.php';
}
else if (file_exists('../../admin/forms-config.php')) {
   require_once '../../engine/ui.php';
   require_once '../../engine/db.php';
   require '../../admin/forms-config.php';
}

function get_form_info($info_values = null)
{
    static $form_info;

    if (isset($info_values)) $form_info = $info_values;
    else return $form_info;
}
get_form_info($form_info);

function forms_record_definition()
{
    $forms_record = array();
    $forms_record['form_id'] = array('type' => CHAR_TYPE);
    $forms_record['form_id']['key'] = true;
    $forms_record['form_fields'] = array('type' => CHAR_TYPE);
    $forms_record['creation_date'] = array('type' => INT_TYPE);
    return $forms_record;
}

function add_form_subscription($db,$form_id,$form_fields)
{
    if (file_exists('engine/modules.php')) require_once 'engine/modules.php';
    else require_once '../engine/modules.php';
    if (module_attached('add_subscription')) {
       if (! call_module_event('add_subscription',
                array($db,$form_id,$form_fields),null,true)) {
          log_error('Unable to add subscription: '.get_module_errors());
          return false;
       }
    }
    return true;
}

function save_form_fields($form_id,&$error_msg)
{
    global $forms_record_id;

    $form_info = get_form_info();
    $db = new DB;
    $forms_record = forms_record_definition();
    $forms_record['form_id']['value'] = $form_id;
    $forms_record['creation_date']['value'] = time();
    $form_fields = get_form_fields();
    $fields = '';
    if (isset($form_info[$form_id]['skip']))
       $skip_array = $form_info[$form_id]['skip'];
    else $skip_array = null;
    foreach ($form_fields as $field_name => $field_value) {
       if (isset($skip_array) && in_array($field_name,$skip_array)) continue;
       if (is_array($field_value)) $field_value = implode(',',$field_value);
       $field_value = str_replace('|',' ',$field_value);
       $field_value = str_replace("\n",' ',$field_value);
       $field_value = str_replace("\r",' ',$field_value);
       if ($fields != '') $fields .= '|';
       $fields .= $field_name.'|'.$field_value;
    }
    $forms_record['form_fields']['value'] = $fields;

    if (! $db->insert('forms',$forms_record)) {
       log_error($db->error);   $error_msg = $db->error;   return false;
    }

    add_form_subscription($db,$form_id,$form_fields);

    log_activity('Added Form Data for Form '.$form_id);
    $forms_record_id = $db->insert_id();
    return $forms_record_id;
}

?>
