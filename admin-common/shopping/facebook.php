<?php
/*
                 Inroads Shopping Cart - Facebook Commerce Module

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC

*/

define('FACEBOOK_SHOPPING_FLAG',6);

function facebook_module_info(&$modules)
{
    $modules[] = array('modulename'=>'facebook','name'=>'Facebook Commerce',
                       'flag'=>FACEBOOK_SHOPPING_FLAG);
}

function facebook_update_cart_config_fields(&$cart_config_fields)
{
    require_once 'facebook/config.php';
    facebook_update_config_fields($cart_config_fields);
}

function facebook_cart_config_section($db,$dialog,$config_values)
{
    require_once 'facebook/config.php';
    facebook_config_section($db,$dialog,$config_values);
}

function facebook_add_shopping_fields($db,&$dialog,$row)
{
    if (shopping_module_installed('google')) return;

    require_once 'facebook/admin.php';
    add_facebook_shopping_fields($db,$dialog,$row);
}

function facebook_setup_select_field($field,&$field_info)
{
    if (shopping_module_installed('google')) return;

    if ($field != 'google_shopping_cat') return;
    $field_info['use_listbox'] = true;
}

function facebook_load_select_field_list(&$dialog,$field,$value)
{
    if (shopping_module_installed('google')) return;

    if ($field != 'google_shopping_cat') return;
    require_once 'facebook/admin.php';
    load_facebook_product_types($dialog,$value);
}

function facebook_add_product_fields(&$product_record)
{
    require_once 'facebook/admin.php';
    add_facebook_product_fields($product_record);
}

function facebook_update_export_fields(&$query,&$field_defs)
{
    if (shopping_module_installed('google')) return;

    $query .= ',p.google_shopping_cat';
}

function facebook_init_vendors($db)
{
    if (shopping_module_installed('google')) return;

    $import_fields = array(array('name'=>'google_shopping_cat',
                                 'type'=>CHAR_TYPE,'size'=>255));
    add_import_fields($db,$import_fields);
}

function facebook_import_fields(&$vendor_import_record)
{
    if (shopping_module_installed('google')) return;

    $vendor_import_record['google_shopping_cat'] = array('type' => CHAR_TYPE);
}

function facebook_vendor_import_head(&$dialog,$db)
{
    if (shopping_module_installed('google')) return;

    $dialog->add_style_sheet('../admin/shopping/facebook/vendors.css');
    $dialog->add_script_file('../admin/shopping/facebook/vendors.js');
}

function facebook_add_import_fields($db,&$dialog,$edit_type,$row)
{
    if (shopping_module_installed('google')) return;

    require_once 'facebook/vendors.php';
    add_facebook_import_fields($db,$dialog,$edit_type,$row);
}

function facebook_update_import(&$product_data)
{
    if (shopping_module_installed('google')) return;

    require_once 'facebook/vendors.php';
    update_facebook_import($product_data);
}

?>
