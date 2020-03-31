<?php
/*
                 Inroads Shopping Cart - Amazon Shopping Module

                       Written 2018-2019 by Randall Severy
                        Copyright 2018-2019 Inroads, LLC

*/

function amazon_module_info(&$modules)
{
    require_once 'amazon/amazon-common.php';
    $modules[] = array('modulename'=>'amazon','name'=>'Amazon',
                       'flag'=>AMAZON_FLAG);
}

function amazon_update_cart_config_fields(&$cart_config_fields)
{
    require_once 'amazon/config.php';
    amazon_update_config_fields($cart_config_fields);
}

function amazon_cart_config_head(&$dialog,$db)
{
    $dialog->add_style_sheet('../admin/shopping/amazon/config.css');
    $dialog->add_script_file('../admin/shopping/amazon/config.js');
}

function amazon_cart_config_section($db,$dialog,$config_values)
{
    require_once 'amazon/config.php';
    amazon_config_section($db,$dialog,$config_values);
}

function amazon_update_cart_config_field($field_name,&$new_field_value,$db)
{
    require_once 'amazon/config.php';
    return amazon_update_config_field($field_name,$new_field_value,$db);
}

function amazon_products_head(&$dialog,$db,$edit_type)
{
    $dialog->add_script_file('../admin/shopping/amazon/admin.js');
}

function amazon_add_price_fields(&$dialog,$row)
{
    require_once 'amazon/admin.php';
    add_amazon_price_fields($dialog,$row);
}

function amazon_add_shopping_fields($db,&$dialog,$row)
{
    require_once 'amazon/admin.php';
    add_amazon_shopping_fields($db,$dialog,$row);
}

function amazon_setup_select_field($field,&$field_info)
{
    if ($field != 'amazon_item_type') return;
    $field_info['table'] = 'amazon_item_types';
    $field_info['id_field'] = 'item_type';
    $field_info['label_field'] = 'description';
    $field_info['use_listbox'] = false;
    $field_info['label_width'] = 810;
    $field_info['dialog_width'] = 1000;
}

function amazon_update_product_id($db,$row,$old_id,$new_id,$cgi)
{
    update_product_id($db,'amazon_pending_deletes','product_id',null,$old_id,
                      $new_id);
}

function amazon_add_product_fields(&$product_record)
{
    require_once 'amazon/admin.php';
    add_amazon_product_fields($product_record);
}

function amazon_update_product($db,$product_record,$old_product_info,&$error,
                               $bulk_flag)
{
    global $skip_amazon_updates;

    if (! empty($skip_amazon_updates)) return true;
    $status_map = get_cart_config_value('amazon_status_map',$db);
    $status_map = explode('|',$status_map);
    if ($status_map[0] === '') return true;
    $shopping_flags = get_shopping_flags($product_record,1);
    $old_shopping_flags = get_shopping_flags($old_product_info,1);
    if (isset($product_record['status']['value']))
       $product_status = $product_record['status']['value'];
    else $product_status = $old_product_info['status'];
    $old_status = $old_product_info['status'];
    $amazon_delete_status = $status_map[0];
    if ((($old_shopping_flags & 1) && (! ($shopping_flags & 1))) ||
        (($old_status != $amazon_delete_status) &&
         ($product_status == $amazon_delete_status))) {
       $row = $db->convert_record_to_array($product_record);
       require_once 'amazon/admin.php';
       return add_amazon_delete_product($db,$row,$error);
    }
    else if (($shopping_flags & 1) &&
             ($product_status != $amazon_delete_status)) {
       $row = $db->convert_record_to_array($product_record);
       require_once 'amazon/admin.php';
       return remove_amazon_delete($db,$row,$error);
    }
    return true;
}

function amazon_update_product_status($db,$product_id,$old_status,$new_status,
                                      &$error)
{
    global $skip_amazon_updates;

    if (! empty($skip_amazon_updates)) return true;
    $status_map = get_cart_config_value('amazon_status_map',$db);
    $status_map = explode('|',$status_map);
    if ($status_map[0] === '') return true;
    $query = 'select * from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $row = $db->get_record($query);
    if (! $row) {
       $error = $db->error;   return false;
    }
    $shopping_flags = get_shopping_flags($row,1);
    if (! ($shopping_flags & 1)) return true;

    $amazon_delete_status = $status_map[0];
    if (($old_status != $amazon_delete_status) &&
         ($new_status == $amazon_delete_status)) {
       require_once 'amazon/admin.php';
       return add_amazon_delete_product($db,$row,$error);
    }
    else if (($old_status == $amazon_delete_status) &&
             ($new_status != $amazon_delete_status)) {
       require_once 'amazon/admin.php';
       return remove_amazon_delete($db,$row,$error);
    }
    return true;
}

function amazon_delete_product($db,$row,&$error)
{
    $shopping_flags = get_shopping_flags($row,1);
    if (($shopping_flags & 1) && (! empty($row['amazon_updated']))) {
       require_once 'amazon/admin.php';
       return add_amazon_delete_product($db,$row,$error);
    }
    return true;
}

function amazon_delete_image($db,$product_id,$filename)
{
    $query = 'select * from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $row = $db->get_record($query);
    if (! ($row['shopping_flags'] & 1)) return;
    require_once 'amazon/admin.php';
    add_amazon_delete_image($db,$product_id,$row,$filename);
}

function amazon_update_export_tables($table,&$tables)
{
    if ($table == '*allcart') $tables[] = 'amazon_pending_deletes';
}

function amazon_update_export_fields(&$query,&$field_defs)
{
    $query .= ',p.amazon_asin,p.amazon_type,p.amazon_item_type,' .
              'p.amazon_price';
}

function amazon_setup_templates(&$template_names,&$template_tables,
                                &$template_prefixes)
{
    $template_names[200] = 'Amazon Results';
    $template_tables['amazon'] = 'Amazon:';
    $template_prefixes['amazon'] = CUSTOM_PREFIX;
}

function amazon_templates_head(&$dialog)
{
    $dialog->add_script_file('../admin/shopping/amazon/templates-config.js');
}

function amazon_lookup_template_variable(&$email,$prefix,$field_name,
                                         &$field_value)
{
    if ($prefix != 'amazon') return false;
    if (isset($email->data[$field_name]))
       $field_value = $email->data[$field_name];
    else $field_value = '';
    return true;
}

function amazon_init_reports($db,&$report_ids,&$report_titles)
{
    $report_ids[] = 'Amazon';
    $report_titles[] = 'Amazon Reports';
}

function amazon_reports_head(&$screen,$db)
{
    $screen->add_script_file('../admin/shopping/amazon/reports.js');
}

function amazon_add_report_log_file_type(&$screen)
{
    $screen->add_list_item('Amazon','Amazon',false);
}

function amazon_report_log_file_info($report_type,&$log_filename,&$title)
{
    if ($report_type != 'Amazon') return false;
    $log_filename = '../admin/amazon.log';   $title = 'Amazon Log File';
    return true;
}

function amazon_add_report_rows(&$screen,$db)
{
    require_once 'amazon/reports.php';
    add_amazon_report_rows($screen,$db);
}

function amazon_run_report($report,&$report_data)
{
    require_once 'amazon/reports.php';
    return run_amazon_report($report,$report_data);
}

function amazon_init_vendors($db)
{
    $import_fields = array(array('name'=>'amazon_type','type'=>CHAR_TYPE,
                                 'size'=>25),
                           array('name'=>'amazon_item_type','type'=>CHAR_TYPE,
                                 'size'=>255));
    add_import_fields($db,$import_fields);
}

function amazon_import_fields(&$vendor_import_record)
{
    $vendor_import_record['amazon_type'] = array('type' => CHAR_TYPE);
    $vendor_import_record['amazon_item_type'] = array('type' => CHAR_TYPE);
}

function amazon_vendor_import_head(&$dialog,$db)
{
    $dialog->add_style_sheet('../admin/shopping/amazon/vendors.css');
    $dialog->add_script_file('../admin/shopping/amazon/vendors.js');
}

function amazon_add_import_fields($db,&$dialog,$edit_type,$row)
{
    require_once 'amazon/vendors.php';
    add_amazon_import_fields($db,$dialog,$edit_type,$row);
}

function amazon_check_vendor_data(&$product_data,$row)
{
    $product_data->current_asin = null;
    return true;
}

function amazon_find_matching_vendor_product(&$part_number,$mpn,$upc,
   $product_data,$addl_match,&$product_id,&$inv_id)
{
    require_once 'amazon/vendors.php';
    return find_amazon_matching_vendor_product($part_number,$mpn,$upc,
              $product_data,$addl_match,$product_id,$inv_id);
}

function amazon_update_import(&$product_data)
{
    require_once 'amazon/vendors.php';
    update_amazon_import($product_data);
}

?>
