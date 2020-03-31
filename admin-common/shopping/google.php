<?php
/*
                 Inroads Shopping Cart - Google Shopping Module

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

define('GOOGLE_SHOPPING_FLAG',1);

function google_module_info(&$modules)
{
    $modules[] = array('modulename'=>'google','name'=>'Google Shopping',
                       'flag'=>GOOGLE_SHOPPING_FLAG);
}

function google_update_cart_config_fields(&$cart_config_fields)
{
    require_once 'google/config.php';
    google_update_config_fields($cart_config_fields);
}

function google_cart_config_head(&$dialog,$db)
{
    require_once 'google/config.php';
    google_config_head($dialog,$db);
}

function google_cart_config_section($db,$dialog,$config_values)
{
    require_once 'google/config.php';
    google_config_section($db,$dialog,$config_values);
}

function google_update_cart_config_field($field_name,&$new_field_value,$db)
{
    require_once 'google/config.php';
    return google_update_config_field($field_name,$new_field_value,$db);
}

function google_products_head(&$dialog,$db,$edit_type)
{
    $dialog->add_script_file('../admin/shopping/google/admin.js');
}

function google_add_shopping_fields($db,&$dialog,$row)
{
    require_once 'google/admin.php';
    add_google_shopping_fields($db,$dialog,$row);
}

function google_setup_select_field($field,&$field_info)
{
    if (($field != 'google_shopping_cat') &&
        ($field != 'google_shopping_type')) return;
    $field_info['use_listbox'] = true;
}

function google_load_select_field_list(&$dialog,$field,$value)
{
    if (($field != 'google_shopping_cat') &&
        ($field != 'google_shopping_type')) return;
    require_once 'google/admin.php';
    load_google_product_types($dialog,$value);
}

function google_add_hidden_product_fields(&$dialog,$edit_type,$row,$db)
{
    if ($edit_type == UPDATERECORD) {
       $google_shopping_id = get_row_value($row,'google_shopping_id');
       if ($google_shopping_id)
          $dialog->add_hidden_field('google_shopping_id',$google_shopping_id);
    }
}

function google_copy_product_record($db,&$product_record)
{
    if ($product_record['google_shopping_id']['value'])
       $product_record['google_shopping_id']['value'] = '';
}

function google_update_product_id($db,$row,$old_id,$new_id,$cgi)
{
    require_once 'google/admin.php';
    update_google_product_id($db,$row,$old_id,$new_id,$cgi);
}

function google_add_product($db,$product_record,&$error)
{
    $shopping_flags = get_shopping_flags($product_record,2);
    $product_status = get_product_status($product_record);
    if (check_shopping_status($product_status) && ($shopping_flags & 2)) {
       require_once 'google/admin.php';
       return add_google_product($db,$product_record,$error);
    }
    return true;
}

function google_update_product($db,$product_record,$old_product_info,&$error,
                               $bulk_flag)
{
    $shopping_flags = get_shopping_flags($product_record,2);
    if ($shopping_flags & 2) {
       require_once 'google/admin.php';
       return update_google_product($db,$product_record,$old_product_info,
                                    $error,$bulk_flag);
    }
    return true;
}

function google_delete_product($db,$row,&$error)
{
    if ($row['google_shopping_id']) {
       require_once 'google/admin.php';
       return delete_google_product($db,$row,$error);
    }
    return true;
}

function google_add_product_fields(&$product_record)
{
    require_once 'google/admin.php';
    add_google_product_fields($product_record);
}

function google_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.google_shopping_id,p.google_shopping_cat,' .
              'p.google_shopping_type,p.google_adwords';
}

function google_update_product_status($db,$product_id,$old_status,$new_status,
                                      &$error)
{
    require_once 'google/admin.php';
    return update_google_product_status($db,$product_id,$new_status,$error);
}

function google_init_reports($db,&$report_ids,&$report_titles)
{
    $report_ids[] = 'Google';
    $report_titles[] = 'Google Shopping Reports';
}

function google_reports_head(&$screen,$db)
{
    $screen->add_script_file('../admin/shopping/google/reports.js');
}

function google_add_report_log_file_type(&$screen)
{
    $screen->add_list_item('Google','Google',false);
}

function google_report_log_file_info($report_type,&$log_filename,&$title)
{
    if ($report_type != 'Google') return false;
    $log_filename = '../admin/google.log';   $title = 'Google Log File';
    return true;
}

function google_add_report_rows(&$screen,$db)
{
    require_once 'google/reports.php';
    add_google_report_rows($screen,$db);
}

function google_run_report($report,&$report_data)
{
    require_once 'google/reports.php';
    return run_google_report($report,$report_data);
}

function google_init_vendors($db)
{
    $import_fields = array(array('name'=>'google_shopping_type',
                                 'type'=>CHAR_TYPE,'size'=>255),
                           array('name'=>'google_shopping_cat',
                                 'type'=>CHAR_TYPE,'size'=>255),
                           array('name'=>'google_adwords',
                                 'type'=>CHAR_TYPE,'size'=>255));
    add_import_fields($db,$import_fields);
}

function google_import_fields(&$vendor_import_record)
{
    $vendor_import_record['google_shopping_type'] = array('type' => CHAR_TYPE);
    $vendor_import_record['google_shopping_cat'] = array('type' => CHAR_TYPE);
    $vendor_import_record['google_adwords'] = array('type' => CHAR_TYPE);
}

function google_vendor_import_head(&$dialog,$db)
{
    $dialog->add_style_sheet('../admin/shopping/google/vendors.css');
    $dialog->add_script_file('../admin/shopping/google/vendors.js');
}

function google_add_import_fields($db,&$dialog,$edit_type,$row)
{
    require_once 'google/vendors.php';
    add_google_import_fields($db,$dialog,$edit_type,$row);
}

function google_update_import(&$product_data)
{
    require_once 'google/vendors.php';
    update_google_import($product_data);
}

?>
