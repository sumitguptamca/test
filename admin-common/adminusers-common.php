<?php
/*
               Inroads Shopping Cart - Common Admin Users Functions

                       Written 2014-2018 by Randall Severy
                        Copyright 2014-2018 Inroads, LLC
*/

$user_pref_names = array('skin','buttons','editor','layout','preload',
                         'notify');

function user_record_definition()
{
    global $shopping_cart,$enable_alexa;

    $user_record = array();
    $user_record['username'] = array('type' => CHAR_TYPE);
    $user_record['username']['key'] = true;
    $user_record['password'] = array('type' => CHAR_TYPE);
    $user_record['firstname'] = array('type' => CHAR_TYPE);
    $user_record['lastname'] = array('type' => CHAR_TYPE);
    $user_record['email'] = array('type' => CHAR_TYPE);
    $user_record['skype_name'] = array('type' => CHAR_TYPE);
    $user_record['office_phone'] = array('type' => CHAR_TYPE);
    $user_record['mobile_phone'] = array('type' => CHAR_TYPE);
    $user_record['home_phone'] = array('type' => CHAR_TYPE);
    $user_record['other_phone'] = array('type' => CHAR_TYPE);
    $user_record['perms'] = array('type' => INT_TYPE);
    $user_record['module_perms'] = array('type' => INT_TYPE);
    $user_record['custom_perms'] = array('type' => INT_TYPE);
    $user_record['creation_date'] = array('type' => INT_TYPE);
    $user_record['modified_date'] = array('type' => INT_TYPE);
    $user_record['ip_address'] = array('type' => CHAR_TYPE);
    $user_record['last_login'] = array('type' => INT_TYPE);
    $user_record['flags'] = array('type' => INT_TYPE);
    $user_record['all_accounts'] = array('type' => INT_TYPE);
    if ($shopping_cart) {
       $user_record['sales_rep'] = array('type' => INT_TYPE);
       $user_record['sales_rep']['fieldtype'] = CHECKBOX_FIELD;
    }
    if (isset($enable_alexa) && $enable_alexa) {
       $user_record['alexa_app_id'] = array('type' => CHAR_TYPE);
       $user_record['alexa_device_id'] = array('type' => CHAR_TYPE);
    }
    if (function_exists('custom_user_fields'))
       custom_user_fields($user_record);
    return $user_record;
}

function user_pref_record_definition()
{
    $user_pref_record = array();
    $user_pref_record['username'] = array('type' => CHAR_TYPE);
    $user_pref_record['username']['key'] = true;
    $user_pref_record['pref_name'] = array('type' => CHAR_TYPE);
    $user_pref_record['pref_name']['key'] = true;
    $user_pref_record['pref_value'] = array('type' => CHAR_TYPE);
    return $user_pref_record;
}

function user_account_record_definition()
{
    $user_account_record = array();
    $user_account_record['username'] = array('type' => CHAR_TYPE);
    $user_account_record['account_id'] = array('type' => INT_TYPE);
    return $user_account_record;
}

function save_user_prefs($db,$user_record,$edit_type,&$error_msg,
                         $user_prefs=null)
{
    global $user_pref_names,$prefs_cookie;

    if (! isset($prefs_cookie)) return true;
    $username = $user_record['username']['value'];
    if ($edit_type == UPDATERECORD) {
       if ($user_prefs) {
          $query = 'select * from user_prefs where username=?';
          $query = $db->prepare_query($query,$username);
          $old_prefs = $db->get_records($query,'pref_name');
          if (! $old_prefs) {
             if (isset($db->error)) {
                $error_msg = $db->error;   return false;
             }
             $old_prefs = array();
          }
       }
       $query = 'delete from user_prefs where username=?';
       $query = $db->prepare_query($query,$username);
       $db->log_query($query);
       if (! $db->query($query)) {
          $error_msg = $db->error;   return false;
       }
    }
    $user_pref_record = user_pref_record_definition();
    $user_pref_record['username']['value'] = $username;
    foreach ($user_pref_names as $index => $pref_name) {
       if ($user_prefs) {
          if (! isset($user_prefs[$pref_name])) continue;
          $pref_value = $user_prefs[$pref_name];
       }
       else $pref_value = get_form_field($pref_name);
       $user_pref_record['pref_name']['value'] = $pref_name;
       $user_pref_record['pref_value']['value'] = $pref_value;
       if (! $db->insert('user_prefs',$user_pref_record)) {
          $error_msg = $db->error;   return false;
       }
    }
    if ($edit_type == UPDATERECORD) {
       if (! $user_prefs) $old_skin = get_form_field('OldSkin');
       else if (isset($old_prefs['skin'])) $old_skin = $old_prefs['skin'];
       else $old_skin = '';
       if (! $user_prefs) $new_skin = get_form_field('skin');
       else if (isset($user_prefs['skin'])) $new_skin = $user_prefs['skin'];
       else $new_skin = '';
       if ($new_skin != $old_skin) {
          require_once '../engine/modules.php';
          $user_info = $db->convert_record_to_array($user_record);
          if (! call_module_event('change_skin',array($new_skin,$user_info))) {
             $error_msg = get_module_errors();   return false;
          }
       }
       if (! $user_prefs) $old_editor = get_form_field('OldEditor');
       else if (isset($old_prefs['editor']))
          $old_editor = $old_prefs['editor'];
       else $old_editor = '';
       if (! $user_prefs) $new_editor = get_form_field('editor');
       else if (isset($user_prefs['editor']))
          $new_editor = $user_prefs['editor'];
       else $new_editor = '';
       if ($new_editor != $old_editor) {
          require_once '../engine/modules.php';
          $user_info = $db->convert_record_to_array($user_record);
          if (! call_module_event('change_editor',
                                  array($new_editor,$user_info))) {
             $error_msg = get_module_errors();   return false;
          }
       }
    }
    return true;
}

?>
