<?php
/*
                 Inroads Shopping Cart - Common Category Functions

                          Written 2019 by Randall Severy
                           Copyright 2019 Inroads, LLC
*/

global $category_label,$subcategory_label,$categories_table;
global $category_products_table,$category_updates_rebuild_web_site;
global $subcategories_table,$image_parent_type,$script_name,$product_label;

if (! isset($category_label)) $category_label = 'Category';
if (! isset($subcategory_label)) $subcategory_label = 'Subcategory';
if (! isset($product_label)) $product_label = 'Product';
if (! isset($categories_table)) $categories_table = 'categories';
if (! isset($subcategories_table)) $subcategories_table = 'subcategories';
if (! isset($category_products_table))
   $category_products_table = 'category_products';
if (! isset($category_updates_rebuild_web_site))
   $category_updates_rebuild_web_site = false;
if (! isset($image_parent_type)) $image_parent_type = 0;
$script_name = basename($_SERVER['PHP_SELF']);

function category_record_definition()
{
    global $category_fields;

    $category_record = array();
    $category_record['id'] = array('type' => INT_TYPE);
    $category_record['id']['key'] = true;
    $category_record['status'] = array('type' => INT_TYPE);
    $category_record['category_type'] = array('type' => INT_TYPE);
    $category_record['template'] = array('type' => CHAR_TYPE);
    $category_record['product_list_template'] = array('type' => CHAR_TYPE);
    $category_record['product_template'] = array('type' => CHAR_TYPE);
    $category_record['template_rows'] = array('type' => INT_TYPE);
    $category_record['name'] = array('type' => CHAR_TYPE);
    $category_record['display_name'] = array('type' => CHAR_TYPE);
    $category_record['menu_name'] = array('type' => CHAR_TYPE);
    $category_record['flags'] = array('type' => INT_TYPE);
    $category_record['products_source'] = array('type' => INT_TYPE);
    $category_record['last_modified'] = array('type' => INT_TYPE);
    $category_record['short_description'] = array('type' => CHAR_TYPE);
    $category_record['long_description'] = array('type' => CHAR_TYPE);
    $category_record['external_url'] = array('type' => CHAR_TYPE);
    $category_record['websites'] = array('type' => CHAR_TYPE);
    $category_record['seo_title'] = array('type' => CHAR_TYPE);
    $category_record['seo_description'] = array('type' => CHAR_TYPE);
    $category_record['seo_keywords'] = array('type' => CHAR_TYPE);
    $category_record['seo_header'] = array('type' => CHAR_TYPE);
    $category_record['seo_footer'] = array('type' => CHAR_TYPE);
    $category_record['seo_url'] = array('type' => CHAR_TYPE);
    foreach ($category_fields as $field_name => $field)
       if ($field['datatype']) {
          $category_record[$field_name] = array('type' => $field['datatype']);
          if (isset($field['fieldtype']) &&
              ($field['fieldtype'] == CHECKBOX_FIELD))
             $category_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
       }
    return $category_record;
}

function category_filter_record_definition()
{
    $category_filter_record = array();
    $category_filter_record['id'] = array('type' => INT_TYPE);
    $category_filter_record['id']['key'] = true;
    $category_filter_record['parent'] = array('type' => INT_TYPE);
    $category_filter_record['field_name'] = array('type' => CHAR_TYPE);
    $category_filter_record['field_values'] = array('type' => CHAR_TYPE);
    return $category_filter_record;
}

function generate_cached_category_page($db,$id,$rebuild=false)
{
    global $base_url,$display_category_page,$cache_catalog_pages;
    global $cache_catalog_page_size,$category_products_table;

    if (empty($cache_catalog_pages)) return;
    if (empty($display_category_page))
       $display_category_page = 'display-category.php';
    $query = 'select count(id) as num_products from ' .
             $category_products_table.' where parent=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (empty($row['num_products'])) return;
    if (! empty($cache_catalog_page_size))
       $page_size = $cache_catalog_page_size;
    else $page_size = 12;
    $num_pages = ceil(intval($row['num_products'])/$page_size);

    for ($page = 1;  $page <= $num_pages;  $page++) {
       $src_url = $base_url.$display_category_page.'?id='.$id;
       if ($page > 1) {
          $src_url .= '&product=p-'.$page;
          $dest_file = '../cache/'.$id.'-p-'.$page.'.html';
       }
       else $dest_file = '../cache/'.$id.'-category.html';
       $page_contents = @file_get_contents($src_url);
       if (! $page_contents) {
          log_error('Unable to get content from '.$src_url);   return;
       }
       $cache_file = @fopen($dest_file,'w');
       if (! $cache_file) {
          log_error('Unable to open cache file '.$dest_file);   return;
       }
       fwrite($cache_file,$page_contents);
       fclose($cache_file);
       if ($rebuild) log_activity('Rebuilt Cached Page '.substr($dest_file,9));
       else log_activity('Built Cached Page '.substr($dest_file,9));
    }
}

function add_category_record($db,$category_record,&$category_id,&$error_code,
                            &$error,$insert_flag,$module=null)
{
    global $category_updates_rebuild_web_site,$cms_program,$categories_table;
    global $enable_multisite,$script_name,$cloudflare_site;

    if (! empty($enable_multisite))
       $websites = $category_record['websites']['value'];
    else $websites = null;
    $seo_url = $category_record['seo_url']['value'];
    if (empty($seo_url)) {
       $display_name = $category_record['display_name']['value'];
       $category_name = $category_record['name']['value'];
       if ($display_name)
          $seo_url = create_default_seo_url($db,$display_name,$websites);
       else $seo_url = create_default_seo_url($db,$category_name,$websites);
       $category_record['seo_url']['value'] = $seo_url;
    }
    if (empty($seo_url)) $seo_url = $category_id;
    else if (! validate_seo_url($db,$seo_url,$websites,$error_code,$error))
       return false;
    $category_record['last_modified']['value'] = time();
    if (function_exists('custom_update_category_record'))
       custom_update_category_record($db,$category_record);
    if ($insert_flag) {
       if (! $db->insert($categories_table,$category_record)) {
          $error_code = 422;   $error = $db->error;   return false;
       }
       $category_id = $db->insert_id();
       $category_record['id']['value'] = $category_id;
    }
    else {
       if (! $db->update($categories_table,$category_record)) {
          $error_code = 422;   $error = $db->error;   return;
       }
       $category_id = $category_record['id']['value'];
    }
    generate_cached_category_page($db,$category_id);
    update_htaccess(0,$category_id,$seo_url,$websites,$db);
    if (isset($cloudflare_site)) {
       require_once '../admin/cloudflare-admin.php';
       update_cloudflare_category($category_id,$seo_url);
    }
    require_once '../engine/modules.php';
    if (module_attached('add_category')) {
       $category_info = $db->convert_record_to_array($category_record);
       if (! call_module_event('add_category',array($db,$category_info))) {
          $error_code = 422;   $error = get_module_errors();   return false;
       }
    }
    if ($category_updates_rebuild_web_site && isset($cms_program))
       spawn_program($script_name.' rebuildwebsite');
    return true;
}

function update_category_record($db,&$category_record,&$error,$module=null,
                                $bulk_flag=false)
{
    global $category_updates_rebuild_web_site,$cms_program,$categories_table;
    global $script_name,$cloudflare_site,$enable_multisite;

    if (empty($category_record['id']['value'])) {
       $error = 'No Category ID specified in update_category_record';
       return false;
    }
    $category_id = $category_record['id']['value'];
    $query = 'select * from '.$categories_table.' where id=?';
    $query = $db->prepare_query($query,$category_id);
    $old_category_info = $db->get_record($query);
    if (! $old_category_info) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Category #'.$category_id.' not found';
       return false;
    }
    $old_seo_url = $old_category_info['seo_url'];
    $seo_url = $category_record['seo_url']['value'];
    if (! empty($enable_multisite)) {
       $old_websites = $old_category_info['websites'];
       $websites = $category_record['websites']['value'];
    }
    else {
       $old_websites = null;    $websites = null;
    }
    if (empty($seo_url)) {
       $display_name = $category_record['display_name']['value'];
       $category_name = $category_record['name']['value'];
       if ($display_name)
          $seo_url = create_default_seo_url($db,$display_name,$websites);
       else $seo_url = create_default_seo_url($db,$category_name,$websites);
       $category_record['seo_url']['value'] = $seo_url;
    }
    if (empty($seo_url)) $seo_url = $category_id;
    else if (! validate_seo_url($db,$seo_url,$websites,$error_code,$error,
                                $category_id)) return false;
    $category_record['last_modified']['value'] = time();
    if (function_exists('custom_update_category_record'))
       custom_update_category_record($db,$category_record);
    if (! $db->update($categories_table,$category_record)) {
       $error = $db->error;   return false;
    }
    generate_cached_category_page($db,$category_id);
    if (($seo_url != $old_seo_url) || ($websites != $old_websites)) {
       update_htaccess(0,$category_id,$seo_url,$websites,$db);
       if ($old_seo_url)
          update_redirect(0,$category_id,$old_seo_url,$old_websites,$db);
    }
    if (isset($cloudflare_site)) {
       require_once '../admin/cloudflare-admin.php';
       update_cloudflare_category($category_id,$seo_url);
       if ($seo_url != $old_seo_url)
          update_cloudflare_category($category_id,$old_seo_url);
    }
    require_once '../engine/modules.php';
    if (module_attached('update_category')) {
       $category_info = $db->convert_record_to_array($category_record);
       if (! call_module_event('update_category',array($db,$category_info))) {
          $error = get_module_errors();   return false;
       }
    }
    if ($category_updates_rebuild_web_site && isset($cms_program))
       spawn_program($script_name.' rebuildwebsite');
    return true;
}

function delete_category_record($db,$category_id,&$category_name,&$error,
            $delete_subs=true,$delete_products=true,$module=null)
{
    global $category_updates_rebuild_web_site,$cms_program,$category_label;
    global $categories_table,$cache_catalog_pages,$enable_multisite;
    global $script_name,$categories_table,$subcategories_table;
    global $category_products_table,$image_parent_type,$cloudflare_site;

    $query = 'select * from '.$categories_table.' where id=?';
    $query = $db->prepare_query($query,$category_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) $error_msg = 'Database Error: '.$db->error;
       else $error_msg = $category_label.' #'.$category_id.' not found';
       $error = $error_msg;   return false;
    }
    $category_name = $row['name'];
    $category_record = category_record_definition();
    $category_record['id']['value'] = $category_id;
    if (! $db->delete($categories_table,$category_record)) {
       http_response(422,$db->error);   return;
    }
    if (! empty($cache_catalog_pages)) {
       $page = 1;
       $cache_file = '../cache/'.$category_id.'-category.html';   $page = 1;
       while (file_exists($cache_file)) {
          unlink($cache_file);   $page++;
          $cache_file = '../cache/'.$category_id.'-p-'.$page.'.html';
       }
    }
    if (! delete_images($image_parent_type,$category_id,$error,$db))
       return false;
    if (! delete_related_items($subcategories_table,$category_id,$db))
       return false;
    if ($delete_subs &&
        (! delete_sublist_items($subcategories_table,$category_id,$db))) {
       $error = $db->error;   return false;
    }
    if ($delete_products &&
        (! delete_sublist_items($category_products_table,$category_id,$db))) {
       $error = $db->error;   return false;
    }
    if (! empty($enable_multisite)) $websites = $row['websites'];
    else $websites = null;
    update_htaccess(0,$category_id,null,$websites,$db);
    if (isset($cloudflare_site)) {
       $seo_url = $row['seo_url'];
       require_once '../admin/cloudflare-admin.php';
       update_cloudflare_category($category_id,$seo_url);
    }
    require_once '../engine/modules.php';
    if (! call_module_event('delete_category',array($db,$category_id))) {
       http_response(422,get_module_errors());   return;
    }
    if ($category_updates_rebuild_web_site && isset($cms_program))
       spawn_program($script_name.' rebuildwebsite');

    return true;
}

?>
