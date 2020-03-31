<?php
/*
                 Inroads Shopping Cart - Common Product Functions

                        Written 2013-2019 by Randall Severy
                         Copyright 2013-2019 Inroads, LLC
*/

if (! isset($shopping_cart)) {
   if (file_exists('../cartengine/adminperms.php')) $shopping_cart = true;
   else $shopping_cart = false;
}
if ($shopping_cart) require_once 'shopping-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

global $image_parent_type,$product_label,$products_label,$products_table;
global $categories_table,$category_products_table,$categories_script_name;

if (! isset($image_parent_type)) $image_parent_type = 1;
if (! isset($product_label)) $product_label = 'Product';
if (! isset($products_label)) $products_label = $product_label.'s';
if (! isset($products_table)) $products_table = 'products';
if (! isset($categories_table)) $categories_table = 'categories';
if (! isset($category_products_table))
   $category_products_table = 'category_products';
if (! isset($categories_script_name))
   $categories_script_name = 'categories.php';
if (! isset($products_matching_script_name))
    $products_matching_script_name = 'product_matching.php';
if (! isset($enable_multisite)) $enable_multisite = false;

function product_record_definition()
{
    global $product_fields,$shopping_cart,$enable_product_flags;
    global $enable_gift_certificates,$enable_wholesale;

    $product_record = array();
    $product_record['id'] = array('type' => INT_TYPE);
    $product_record['id']['key'] = true;
    $product_record['status'] = array('type' => INT_TYPE);
    $product_record['product_type'] = array('type' => INT_TYPE);
    $product_record['template'] = array('type' => CHAR_TYPE);
    $product_record['vendor'] = array('type' => INT_TYPE);
    $product_record['import_id'] = array('type' => INT_TYPE);
    $product_record['name'] = array('type' => CHAR_TYPE);
    $product_record['display_name'] = array('type' => CHAR_TYPE);
    $product_record['menu_name'] = array('type' => CHAR_TYPE);
    if ($shopping_cart)
       $product_record['order_name'] = array('type' => CHAR_TYPE);
    $product_record['list_price'] = array('type' => FLOAT_TYPE);
    $product_record['price'] = array('type' => FLOAT_TYPE);
    $product_record['sale_price'] = array('type' => FLOAT_TYPE);
    $product_record['cost'] = array('type' => FLOAT_TYPE);
    if (! empty($enable_wholesale))
       $product_record['account_discount'] = array('type' => FLOAT_TYPE);
    $product_record['min_order_qty'] = array('type' => INT_TYPE);
    $product_record['flags'] = array('type' => INT_TYPE);
    if (! empty($enable_product_flags)) {
       $product_record['left_flag'] = array('type' => INT_TYPE);
       $product_record['right_flag'] = array('type' => INT_TYPE);
    }
    $product_record['last_modified'] = array('type' => INT_TYPE);
    if ($shopping_cart) {
       $product_record['taxable'] = array('type' => INT_TYPE);
       $product_record['taxable']['fieldtype'] = CHECKBOX_FIELD;
       $product_record['shopping_gtin'] = array('type' => CHAR_TYPE);
       $product_record['shopping_brand'] = array('type' => CHAR_TYPE);
       $product_record['shopping_mpn'] = array('type' => CHAR_TYPE);
       $product_record['shopping_gender'] = array('type' => CHAR_TYPE);
       $product_record['shopping_color'] = array('type' => CHAR_TYPE);
       $product_record['shopping_age'] = array('type' => CHAR_TYPE);
       $product_record['shopping_condition'] = array('type' => CHAR_TYPE);
       $product_record['shopping_flags'] = array('type' => INT_TYPE);
       $product_record['price_break_type'] = array('type' => INT_TYPE);
       $product_record['price_breaks'] = array('type' => CHAR_TYPE);
    }
    $product_record['short_description'] = array('type' => CHAR_TYPE);
    $product_record['long_description'] = array('type' => CHAR_TYPE);
    if ($shopping_cart)
       $product_record['download_file'] = array('type' => CHAR_TYPE);
    $product_record['websites'] = array('type' => CHAR_TYPE);
    $product_record['video'] = array('type' => CHAR_TYPE);
    $product_record['audio'] = array('type' => CHAR_TYPE);
    $product_record['designer_image'] = array('type' => CHAR_TYPE);
    $product_record['seo_title'] = array('type' => CHAR_TYPE);
    $product_record['seo_description'] = array('type' => CHAR_TYPE);
    $product_record['seo_keywords'] = array('type' => CHAR_TYPE);
    $product_record['seo_header'] = array('type' => CHAR_TYPE);
    $product_record['seo_footer'] = array('type' => CHAR_TYPE);
    $product_record['seo_url'] = array('type' => CHAR_TYPE);
    $product_record['seo_category'] = array('type' => INT_TYPE);
    if (! empty($enable_gift_certificates))
       $product_record['gift_certificate'] = array('type' => CHAR_TYPE);

    if ($shopping_cart)
       call_shopping_event('add_product_fields',array(&$product_record));
    if (isset($product_fields)) {
       foreach ($product_fields as $field_name => $field) {
          if ($field['datatype']) {
             $product_record[$field_name] = array('type' => $field['datatype']);
             if (isset($field['fieldtype']) &&
                 ($field['fieldtype'] == CHECKBOX_FIELD))
                $product_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
             else if (isset($field['datafieldtype']) &&
                 ($field['datafieldtype'] == CHECKBOX_FIELD))
                $product_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
          }
       }
    }
    return $product_record;
}

function popular_product_record_definition()
{
    $popular_record = array();
    $popular_record['id'] = array('type' => INT_TYPE);
    $popular_record['id']['key'] = true;
    $popular_record['parent'] = array('type' => INT_TYPE);
    $popular_record['name'] = array('type' => CHAR_TYPE);
    $popular_record['attributes'] = array('type' => CHAR_TYPE);
    $popular_record['image'] = array('type' => CHAR_TYPE);
    return $popular_record;
}

function product_activity_record_definition()
{
    $activity_record = array();
    $activity_record['id'] = array('type' => INT_TYPE,'key' => true);
    $activity_record['parent'] = array('type' => INT_TYPE);
    $activity_record['activity_date'] = array('type' => INT_TYPE);
    $activity_record['activity'] = array('type' => CHAR_TYPE);
    return $activity_record;
}

function product_rebate_record_definition()
{
    $rebate_record = array();
    $rebate_record['id'] = array('type' => INT_TYPE,'key' => true);
    $rebate_record['parent'] = array('type' => INT_TYPE);
    $rebate_record['url'] = array('type' => CHAR_TYPE);
    $rebate_record['label'] = array('type' => CHAR_TYPE);
    $rebate_record['start_date'] = array('type' => INT_TYPE);
    $rebate_record['end_date'] = array('type' => INT_TYPE);
    return $rebate_record;
}

function get_product_status($record)
{
    if (isset($record['status']['value']))
       $product_status = $record['status']['value'];
    else $product_status = 0;
    return $product_status;
}

function setup_product_change_dialog(&$dialog)
{
    global $script_name;

    $dialog->enable_ajax();
    $dialog->add_style_sheet('products.css');
    $dialog->add_script_file('products.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $script = "<script>\n";
    $script .= "       script_name = '".$script_name."';\n";
    $script .= '    </script>';
    $script .= "    <style type=\"text/css\">\n" .
               "      .select { max-width: 500px; }\n" .
               '    </style>';
    $dialog->add_head_line($script);
}

function display_product_change_choices($db,$dialog,$id_array,$show_hr=true)
{
    global $enable_vendors,$product_label,$products_label;

    add_website_select_row($dialog,$db,'Web Site:','WebSite',null,true);
    if (count($id_array) > 0) {
       $dialog->write('<tr><td colspan="2">');
       if (count($id_array) > 1) $label = 'All Selected '.$products_label;
       else $label = 'Selected '.$product_label;
       $dialog->add_radio_field('select','selected',$label,true);
       $dialog->end_row();
    }
    $dialog->write('<tr><td colspan="2">');
    $dialog->add_radio_field('select','all','All '.$products_label,false);
    $dialog->end_row();
    $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: " .
                   "left;\" nowrap>\n");
    $dialog->add_radio_field('select','category','Category: ',
                             (count($id_array) == 0));
    $dialog->write("</td><td>\n");
    $dialog->start_choicelist('category','select_change_category();');
    $dialog->add_list_item('','',false);
    $query = 'select id,name from categories order by name';
    $rows = $db->get_records($query);
    if ($rows) foreach ($rows as $row)
       $dialog->add_list_item($row['id'],$row['name'],false);
    $dialog->end_choicelist();
    $dialog->end_row();
    if ($enable_vendors) {
       $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: " .
                      "left;\" nowrap>\n");
       $dialog->add_radio_field('select','vendor','Vendor: ',false);
       $dialog->write("</td><td>\n");
       $dialog->start_choicelist('vendor','select_change_vendor();');
       $dialog->add_list_item('','',false);
       $dialog->add_list_item('~','Unassigned',false);
       $query = 'select id,name from vendors order by name';
       $rows = $db->get_records($query);
       if ($rows) foreach ($rows as $row)
          $dialog->add_list_item($row['id'],$row['name'],false);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    if (function_exists('display_custom_product_change_select'))
       display_custom_product_change_select($dialog,$db);
    if ($show_hr)
       $dialog->write('<tr><td colspan="2" style="padding:5px 0px;"><hr>' .
                      "</td></tr>\n");
}

function get_category_filter_id_array($db,$category,$inventory,$src_query,
                                      $return_field)
{
    $query = 'select products_source from categories where id=?';
    $query = $db->prepare_query($query,$category);
    $row = $db->get_record($query);
    if ($row['products_source'] != 1) return null;
    $query = 'select * from category_filters where parent=?';
    $query = $db->prepare_query($query,$category);
    $filters = $db->get_records($query);
    if (empty($filters)) return null;
    $where = 'where ';   $first_where = true;   $args = array();
    foreach ($filters as $filter) {
       if ($first_where) $first_where = false;
       else $where .= ' and ';
       $values = explode('|',$filter['field_values']);
       if (count($values) == 1) {
          $where .= '('.$filter['field_name'].'=?)';
          $args[] = $filter['field_values'];
       }
       else {
          $where .= '('.$filter['field_name'].' in (?))';
          $args[] = $values;
       }
    }
    if ($inventory)
       $query = 'select id'.$src_query.' from product_inventory where ' .
                'parent in (select id from products '.$where.') order by id';
    else $query = 'select id from products '.$where.' order by id';
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $id_array = $db->get_records($query,null,$return_field);
    if (! $id_array) $id_array = array();
    return $id_array;
}

function parse_product_change_choices($db,$inventory=false,$src_query='')
{
    global $enable_category_filter_search;

    $select = get_form_field('select');
    if ($src_query) $return_field = null;
    else $return_field = 'id';
    if ($select == 'selected') {
       $ids = get_form_field('ids');
       $id_array = explode(',',$ids);
       if ($inventory) {
          $query = 'select id'.$src_query.' from product_inventory where ' .
                   'parent in (?) order by id';
          $query = $db->prepare_query($query,$id_array);
          $id_array = $db->get_records($query);
       }
    }
    else if ($select == 'all') {
       if ($inventory)
          $query = 'select id'.$src_query.' from product_inventory ' .
                   'order by id';
       else $query = 'select id from products order by id';
       $id_array = $db->get_records($query,null,$return_field);
       if (! $id_array) $id_array = array();
    }
    else if ($select == 'category') {
       $category = get_form_field('category');
       if (! empty($enable_category_filter_search))
          $id_array = get_category_filter_id_array($db,$category,$inventory,
                                                   $src_query,$return_field);
       else $id_array = null;
       if ($id_array === null) {
          if ($inventory)
             $query = 'select id'.$src_query.' from product_inventory where ' .
                      'parent in (select related_id from category_products ' .
                      'where parent=?) order by id';
          else $query = 'select p.id from products p join category_products ' .
                        'cp on cp.related_id=p.id where (cp.parent=?) ' .
                        'order by p.id';
          $query = $db->prepare_query($query,$category);
          $id_array = $db->get_records($query,null,$return_field);
          if (! $id_array) $id_array = array();
       }
    }
    else if ($select == 'vendor') {
       $vendor = get_form_field('vendor');
       if ($inventory)
          $query = 'select id'.$src_query.' from product_inventory where ' .
                   'parent in (select id from products where ';
       else $query = 'select id from products where ';
       if ($vendor == '~') $query .= '((vendor="") or (isnull(vendor))';
       else $query .= '(vendor=?)';
       if ($inventory) $query .= ')';
       $query .= ' order by id';
       if ($vendor != '~') $query = $db->prepare_query($query,$vendor);
       $id_array = $db->get_records($query,null,$return_field);
       if (! $id_array) $id_array = array();
    }
    else if (function_exists('process_custom_product_change_select'))
       $id_array = process_custom_product_change_select($select,$db);
    else $id_array = array();

    $website = get_form_field('WebSite');
    if ($website && (count($id_array) > 0)) {
       if ($inventory)
          $query = 'select id from product_inventory where parent in ' .
                   '(select id from products where find_in_set(?,websites))';
       else $query = 'select id from products where find_in_set(?,websites)';
       $query = $db->prepare_query($query,$website);
       $website_ids = $db->get_records($query,'id');
       foreach ($id_array as $index => $id) {
          if ($src_query) $id = $id['id'];
          if (! isset($website_ids[$id])) unset($id_array[$index]);
       }
    }
    return $id_array;
}

function load_category_pages($categories,$db,$id)
{
    if ($categories == null) {
       $old_categories = get_form_field('categories');
       if ($old_categories) $categories = explode(',',$old_categories);
       else $categories = array();
    }
    if (! $id) return $categories;
    $query = 'select parent from category_products where related_id=?';
    $query = $db->prepare_query($query,$id);
    $result = $db->query($query);
    if ($result) {
       while ($row = $db->fetch_assoc($result))
          if (! in_array($row['parent'],$categories))
             $categories[] = $row['parent'];
       $db->free_result($result);
    }
    return $categories;
}

function fill_product_record($db,$product_id,&$product_record,&$error)
{
    $query = 'select * from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $row = $db->get_record($query);
    if (! $row) {
       $error = $db->error;   return false;
    }
    while (list($field_name,$field_value) = each($row)) {
       if (isset($product_record[$field_name]))
          $product_record[$field_name]['value'] = $field_value;
    }
    return true;
}

function load_product_info($db,$product_id)
{
    $query = 'select * from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $product_info = $db->get_record($query);
    return $product_info;
}

function load_inventory_records($db,$product_id)
{
    global $shopping_cart;

    if (! $shopping_cart) return null;
    $query = 'select * from product_inventory where parent=?';
    $query = $db->prepare_query($query,$product_id);
    $inventory_records = $db->get_records($query);
    if (! $inventory_records) {
       if (isset($db->error)) return null;
       return array(array());
    }
    $attributes_found = false;
    foreach ($inventory_records as $inventory_info) {
       if ($inventory_info['attributes']) {
          $attributes_found = true;   break;
       }
    }
    if (! $attributes_found) return $inventory_records;

    $query = 'select id from attribute_options where parent in (select ' .
             'related_id from product_attributes where parent=?)';
    $query = $db->prepare_query($query,$product_id);
    $options = $db->get_records($query,'id');
    reset($inventory_records);
    while (list($index,$inventory_info) = each($inventory_records)) {
       if (! $inventory_info['attributes']) continue;
       $attributes = explode('-',$inventory_info['attributes']);
       $options_found = true;
       foreach ($attributes as $attribute) {
          if (! isset($options[$attribute])) {
             $options_found = false;   break;
          }
       }
       if (! $options_found) unset($inventory_records[$index]);
    }
    if (count($inventory_records) == 0) $inventory_records = array(array());
    return $inventory_records;
}

function set_product_category_info($db,&$product_info)
{
    if (! array_key_exists('seo_category',$product_info)) return;

    $product_id = $product_info['id'];
    $seo_category = $product_info['seo_category'];
    if (! $seo_category) {
       $query = 'select parent from category_products where related_id=? ' .
                'order by id limit 1';
       $query = $db->prepare_query($query,$product_id);
       $row = $db->get_record($query);
       if ($row) $seo_category = $row['parent'];
       else $seo_category = 0;
    }
    if ($seo_category) {
       $query = 'select name from categories where id=?';
       $query = $db->prepare_query($query,$seo_category);
       $row = $db->get_record($query);
       if ($row) $category = $row['name'];
       else $category = '';
    }
    else $category = '';
    $product_info['cat_id'] = $seo_category;
    $product_info['cat_name'] = $category;
}

function update_inventory_records($db,$product_info,&$inventory)
{
    global $features;

    if (! $inventory) return;
    if (! isset($product_info['id'])) return;
    if (! isset($features)) {
       if (function_exists('get_cart_config_value'))
          $features = get_cart_config_value('features',$db);
       else $features = 0;
    }
    $product_id = $product_info['id'];
    $load_options = false;   $load_image = false;
    if (isset($inventory['id'])) {
       $inventory = array($inventory);   $single_record = true;
    }
    else $single_record = false;
    reset($inventory);
    while (list($index,$inv_info) = each($inventory)) {
       if (! isset($inv_info['attributes']))
          $inventory[$index]['attributes'] = '';
       else if ($inv_info['attributes']) $load_options = true;
       if (! isset($inv_info['image'])) {
          $inventory[$index]['image'] = '';   $load_image = true;
       }
       else if (! $inv_info['image']) $load_image = true;
       if (! isset($inv_info['part_number']))
          $inventory[$index]['part_number'] = '';
    }
    if ($load_options) {
       $query = 'select id,name,adjustment,adjust_type from attribute_options ' .
                'where parent in (select related_id from product_attributes ' .
                'where parent=?) order by id';
       $query = $db->prepare_query($query,$product_id);
       $options = $db->get_records($query,'id');
    }
    else $options = null;
    if ($load_image) {
       $query = 'select filename from images where (parent_type=1) and ' .
                '(parent=?) order by sequence limit 1';
       $query = $db->prepare_query($query,$product_id);
       $image_row = $db->get_record($query);
       if ($image_row) $image = $image_row['filename'];
       else $image = null;
    }
    else $image = null;
    reset($inventory);
    while (list($index,$inv_info) = each($inventory)) {
       if (($features & LIST_PRICE_PRODUCT) &&
           array_key_exists('list_price',$product_info))
          $inventory[$index]['list_price'] = $product_info['list_price'];
       else if (! isset($inventory[$index]['list_price']))
          $inventory[$index]['list_price'] = 0;
       if (($features & REGULAR_PRICE_PRODUCT) &&
           array_key_exists('price',$product_info))
          $inventory[$index]['price'] = $product_info['price'];
       else if (! isset($inventory[$index]['price']))
          $inventory[$index]['price'] = 0;
       if (($features & SALE_PRICE_PRODUCT) &&
           array_key_exists('sale_price',$product_info))
          $inventory[$index]['sale_price'] = $product_info['sale_price'];
       else if (! isset($inventory[$index]['sale_price']))
          $inventory[$index]['sale_price'] = 0;
       if (($features & PRODUCT_COST_PRODUCT) &&
           array_key_exists('cost',$product_info))
          $inventory[$index]['cost'] = $product_info['cost'];
       else if (! isset($inventory[$index]['cost']))
          $inventory[$index]['cost'] = 0;
       if ($inventory[$index]['sale_price'])
          $current_price = floatval($inventory[$index]['sale_price']);
       else $current_price = floatval($inventory[$index]['price']);
       $option_price = 0;   $name = '';
       if ($options && $inv_info['attributes']) {
          $attributes = explode('-',$inv_info['attributes']);
          foreach ($attributes as $attribute) {
             if (isset($options[$attribute])) {
                $option = $options[$attribute];
                if ($option['adjustment']) {
                   if ($option['adjust_type'] == 1)
                      $option_price += round($current_price *
                                             ($option['adjustment'] / 100),2);
                   else $option_price += floatval($option['adjustment']);
                }
                if ($name) $name .= ', ';
                $name .= trim($option['name']);
             }
          }
       }
       $inventory[$index]['name'] = $name;
       $inventory[$index]['option_price'] = $option_price;
       if ((! $inventory[$index]['image']) && $image)
          $inventory[$index]['image'] = $image;
    }
    if ($single_record) $inventory = $inventory[0];
    else reset($inventory);
}

function delete_cached_page($db,$id,$flags,$old_flags,$seo_url,$old_seo_url)
{
    if (($old_flags & (FEATURED|UNIQUEURL)) &&
        (! ($flags & (FEATURED|UNIQUEURL)))) {
       if (empty($old_seo_url)) $old_seo_url = $id;
       $cache_file = '../cache/'.$old_seo_url.'.html';
       unlink($cache_file);
    }
    else if ($old_seo_url != $seo_url) {
       if (empty($old_seo_url)) $old_seo_url = $id;
       $query = 'select distinct c.id from categories c join ' .
                'category_products cp on cp.parent=c.id where ' .
                'cp.related_id=? order by c.id';
       $query = $db->prepare_query($query,$id);
       $rows = $db->get_records($query);
       if (! $rows) return;
       foreach ($rows as $row) {
          $cache_file = '../cache/'.$row['id'].'-'.$old_seo_url.'.html';
          unlink($cache_file);
       }
    }
}

function update_cached_category_pages($db,$categories)
{
    global $base_url,$display_category_page;
    global $cache_catalog_page_size,$category_products_table;

    if (empty($display_category_page))
       $display_category_page = 'display-category.php';
    if (! empty($cache_catalog_page_size))
       $page_size = $cache_catalog_page_size;
    else $page_size = 12;
    foreach ($categories as $category) {
       $query = 'select count(id) as num_products from ' .
                $category_products_table.' where parent=?';
       $query = $db->prepare_query($query,$category);
       $row = $db->get_record($query);
       if (empty($row['num_products'])) continue;
       $num_pages = ceil(intval($row['num_products'])/$page_size);

       for ($page = 1;  $page <= $num_pages;  $page++) {
          $src_url = $base_url.$display_category_page.'?id='.$category;
          if ($page > 1) {
             $src_url .= '&product=p-'.$page;
             $dest_file = '../cache/'.$category.'-p-'.$page.'.html';
          }
          else $dest_file = '../cache/'.$category.'-category.html';
          $page_contents = @file_get_contents($src_url);
          if (! $page_contents) {
             log_error('Unable to get content from '.$src_url);
             continue;
          }
          $cache_file = @fopen($dest_file,'w');
          if (! $cache_file) {
             log_error('Unable to open cache file '.$dest_file);   continue;
          }
          fwrite($cache_file,$page_contents);
          fclose($cache_file);
          log_activity('Built Cached Page '.substr($dest_file,9));
       }
    }
}

function generate_cached_product_page($db,$id,$edit_type,$flags,$old_flags,
                                      $seo_url,$old_seo_url,$rebuild=false)
{
    global $base_url,$cache_catalog_pages,$display_category_page;
    global $display_product_page;

    if (empty($cache_catalog_pages)) return;

    if (($edit_type == UPDATERECORD) &&
        (($seo_url != $old_seo_url) || ($flags != $old_flags)))
       delete_cached_page($db,$id,$flags,$old_flags,$seo_url,$old_seo_url);

    if (empty($display_category_page))
       $display_category_page = 'display-category.php';
    if (empty($display_product_page))
       $display_product_page = 'display-product.php';

    if (empty($seo_url)) $seo_url = $id;
    if ($flags & (FEATURED|UNIQUEURL)) {
       $src_url = $base_url.$display_product_page.'?id='.$id;
       $dest_file = '../cache/'.$seo_url.'.html';
       $page_contents = @file_get_contents($src_url);
       if (! $page_contents) {
          log_error('Unable to get content from '.$src_url);
          continue;
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
    else {
       $query = 'select distinct c.id from categories c join ' .
                'category_products cp on cp.parent=c.id ' .
                'where cp.related_id=? order by c.id';
       $query = $db->prepare_query($query,$id);
       $rows = $db->get_records($query);
       if (! $rows) return;
       foreach ($rows as $row) {
          $src_url = $base_url.$display_category_page.'?id='.$row['id'] .
                     '&product='.$id;
          $dest_file = '../cache/'.$row['id'].'-'.$seo_url.'.html';
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
          if ($rebuild)
             log_activity('Rebuilt Cached Page '.substr($dest_file,9));
          else log_activity('Built Cached Page '.substr($dest_file,9));
       }
    }
    if (! $rebuild) {
       $cached_categories = load_category_pages(null,$db,$id);
       update_cached_category_pages($db,$cached_categories);
    }
}

function add_product_record($db,$product_record,&$product_id,&$error_code,
                            &$error,$insert_flag,$module=null)
{
    global $products_table,$base_product_url;
    global $enable_multisite,$shopping_cart,$cloudflare_site;

    if (isset($product_record['status']['value']))
       $product_status = $product_record['status']['value'];
    else $product_status = 0;
    if (isset($product_record['seo_url']['value']))
       $seo_url = $product_record['seo_url']['value'];
    else $seo_url = '';
    if (isset($product_record['flags']['value']))
       $flags = $product_record['flags']['value'];
    else $flags = 0;
    if ($enable_multisite) {
       if (isset($product_record['websites']['value']))
          $websites = $product_record['websites']['value'];
       else $websites = null;
    }
    else $websites = null;
    if ($seo_url && ($seo_url != '')) {
       if (! validate_seo_url($db,$seo_url,$websites,$error_code,$error))
          return false;
    }
    $product_record['last_modified']['value'] = time();
    if (empty($seo_url)) {
       if (isset($product_record['display_name']['value']))
          $display_name = $product_record['display_name']['value'];
       else $display_name = null;
       if (isset($product_record['name']['value']))
          $product_name = $product_record['name']['value'];
       else $product_name = null;
       if ($display_name)
          $seo_url = create_default_seo_url($db,$display_name,$websites);
       else if ($product_name)
          $seo_url = create_default_seo_url($db,$product_name,$websites);
       else $seo_url = null;
       if ($seo_url) $product_record['seo_url']['value'] = $seo_url;
       else unset($product_record['seo_url']['value']);
    }
    if (function_exists('custom_update_product_record'))
       custom_update_product_record($db,$product_record,ADDRECORD);
    if ($insert_flag) {
       if (! $db->insert($products_table,$product_record)) {
          $error_code = 422;   $error = $db->error;   return false;
       }
       $product_id = $db->insert_id();
       $product_record['id']['value'] = $product_id;
    }
    else {
       if (! $db->update($products_table,$product_record)) {
          $error_code = 422;   $error = $db->error;   return false;
       }
       $product_id = $product_record['id']['value'];
    }
    if (function_exists('custom_update_product'))
       custom_update_product($db,$product_record,ADDRECORD);
    if ($flags & (FEATURED|UNIQUEURL)) {
       if (! isset($base_product_url)) $base_product_url = 'products/';
       if (empty($seo_url)) $seo_url = $base_product_url.$product_id;
       update_htaccess(1,$product_id,$seo_url,$websites,$db);
    }
    generate_cached_product_page($db,$product_id,ADDRECORD,$flags,$flags,
                                 $seo_url,$seo_url);
    if (isset($cloudflare_site)) {
       $cached_categories = load_category_pages(null,$db,$product_id);
       require_once '../admin/cloudflare-admin.php';
       if ($flags & (FEATURED|UNIQUEURL))
          update_cloudflare_product($product_id,
                                    $product_record['seo_url']['value']);
       update_cloudflare_product_categories($db,$cached_categories,$product_id,
                                            $product_record['seo_url']['value']);
    }
    require_once '../engine/modules.php';
    if (module_attached('add_product')) {
       $product_info = $db->convert_record_to_array($product_record);
       set_product_category_info($db,$product_info);
       $inventory = load_inventory_records($db,$product_id);
       update_inventory_records($db,$product_info,$inventory);
       if (! call_module_event('add_product',
                               array($db,$product_info,$inventory),$module)) {
          $error_code = 422;   $error = get_module_errors();   return false;
       }
    }
    if ($shopping_cart) {
       if (! call_shopping_event('add_product',
                array($db,$product_record,&$error),false))
          return false;
    }
    return true;
}

function update_product_record($db,&$product_record,&$error,$module=null,
                               $bulk_flag=false)
{
    global $products_table,$base_product_url,$enable_multisite;
    global $off_sale_option,$shopping_cart,$cloudflare_site;

    if (! isset($off_sale_option)) $off_sale_option = 1;

    if (empty($product_record['id']['value'])) {
       $error = 'No Product ID specified in update_product_record';
       return false;
    }
    $product_id = $product_record['id']['value'];
    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$product_id);
    $old_product_info = $db->get_record($query);
    if (! $old_product_info) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Product #'.$product_id.' not found';
       return false;
    }
    if (isset($product_record['status']['value']))
       $product_status = $product_record['status']['value'];
    else $product_status = $old_product_info['status'];
    if (isset($product_record['seo_url']['value']))
       $seo_url = $product_record['seo_url']['value'];
    else $seo_url = $old_product_info['seo_url'];
    if (isset($product_record['flags']['value']))
       $flags = $product_record['flags']['value'];
    else $flags = $old_product_info['flags'];
    $old_status = $old_product_info['status'];
    $old_flags = $old_product_info['flags'];
    $old_seo_url = $old_product_info['seo_url'];
    if ($shopping_cart)
       $old_shopping_flags = $old_product_info['shopping_flags'];
    if ($enable_multisite) {
       $old_websites = get_form_field('old_websites');
       if (isset($product_record['websites']['value']))
          $websites = $product_record['websites']['value'];
       else $websites = $old_product_info['websites'];
    }
    else {
       $old_websites = null;   $websites = null;
    }
    if (empty($seo_url)) {
       if (isset($product_record['display_name']['value']))
          $display_name = $product_record['display_name']['value'];
       else $display_name = $old_product_info['display_name'];
       if (isset($product_record['name']['value']))
          $product_name = $product_record['name']['value'];
       else $product_name = $old_product_info['name'];
       if ($display_name)
          $seo_url = create_default_seo_url($db,$display_name,$websites);
       else $seo_url = create_default_seo_url($db,$product_name,$websites);
       $product_record['seo_url']['value'] = $seo_url;
    }
    if ($seo_url && (! validate_seo_url($db,$seo_url,$websites,$error_code,
                                        $error,null,$product_id)))
          return false;
    if (! isset($product_record['last_modified']['value']))
       $product_record['last_modified']['value'] = time();
    if (function_exists('custom_update_product_record'))
       custom_update_product_record($db,$product_record,UPDATERECORD);
    if (! $db->update($products_table,$product_record)) {
       $error = $db->error;   return false;
    }
    if (! isset($product_record['name']['value']))
       $product_record['name']['value'] = $old_product_info['name'];
    if (function_exists('custom_update_product'))
       custom_update_product($db,$product_record,UPDATERECORD);
    if (($seo_url != $old_seo_url) || ($flags != $old_flags)) {
       if ($flags & (FEATURED|UNIQUEURL)) {
          if (! isset($base_product_url)) $base_product_url = 'products/';
          if (empty($seo_url)) $seo_url = $base_product_url.$product_id;
          update_htaccess(1,$product_id,$seo_url,$websites,$db);
       }
       else if ($old_flags & (FEATURED|UNIQUEURL))
          update_htaccess(1,$product_id,null,$websites,$db);
       if ($old_seo_url && ($seo_url != $old_seo_url))
          update_redirect(1,$product_id,$old_seo_url,$old_websites,$db);
    }
    generate_cached_product_page($db,$product_id,UPDATERECORD,$flags,
                                 $old_flags,$seo_url,$old_seo_url);
    if (isset($cloudflare_site)) {
       $cached_categories = load_category_pages(null,$db,$product_id);
       require_once '../admin/cloudflare-admin.php';
       update_cloudflare_product_categories($db,$cached_categories,$product_id,
                                            $product_record['seo_url']['value']);
       if ($old_seo_url && ($seo_url != $old_seo_url))
          update_cloudflare_product_categories($db,$cached_categories,$product_id,
                                               $old_seo_url);
       if ($flags & (FEATURED|UNIQUEURL)) {
          update_cloudflare_product($product_id,
                                    $product_record['seo_url']['value']);
          if ($old_seo_url && ($seo_url != $old_seo_url))
             update_cloudflare_product($product_id,$old_seo_url);
       }
    }
    require_once '../engine/modules.php';
    if (module_attached('update_product')) {
       $product_info = $db->convert_record_to_array($product_record);
       set_product_category_info($db,$product_info);
       $inventory = load_inventory_records($db,$product_id);
       update_inventory_records($db,$product_info,$inventory);
       if (! call_module_event('update_product',
                               array($db,$product_info,$inventory),$module)) {
          $error = get_module_errors();   return false;
       }
    }
    if ($shopping_cart) {
       if (! call_shopping_event('update_product',
                array($db,$product_record,$old_product_info,&$error,$bulk_flag),
                false))
          return false;
    }
    return true;
}

function delete_product_record($db,$product_id,&$product_name,&$error,
                               $module=null)
{
    global $product_label,$products_table;
    global $product_tabs,$shopping_cart,$cache_catalog_pages;
    global $category_products_table,$image_parent_type;
    global $shopping_cart,$cloudflare_site,$related_types;

    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$product_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) $error_msg = 'Database Error: '.$db->error;
       else $error_msg = $product_label.' #'.$product_id.' not found';
       $error = $error_msg;   return false;
    }
    $product_name = $row['name'];
    $flags = $row['flags'];
    if ($shopping_cart) {
       if ($row['shopping_flags'] === '') $shopping_flags = 255;
       else $shopping_flags = $row['shopping_flags'];
    }

    require_once '../engine/modules.php';
    if (module_attached('delete_product')) {
       $inventory = load_inventory_records($db,$product_id);
       if (! call_module_event('delete_product',
                               array($db,$row,$inventory),$module)) {
          $error = get_module_errors();   return false;
       }
    }
    if ($shopping_cart) {
       if (! call_shopping_event('delete_product',
                array($db,$row,&$error),false)) return false;
       if ($product_tabs['attributes']) {
          if (! delete_sublist_items('product_attributes',$product_id,$db)) {
             $error = $db->error;   return false;
          }
       }
       if ($product_tabs['inventory']) {
          if (! delete_inventory_records($product_id,$error,$db)) return false;
       }
       $features = get_cart_config_value('features',$db);
       if ($features & (QTY_DISCOUNTS|QTY_PRICING)) {
          $query = 'delete from product_discounts where parent=?';
          $query = $db->prepare_query($query,$product_id);
          $db->log_query($query);
          if (! $db->query($query)) {
             $error = $db->error;   return false;
          }
       }
    }
    if (isset($cloudflare_site))
       $cached_categories = load_category_pages(null,$db,$product_id);
    if (! delete_images($image_parent_type,$product_id,$error,$db))
       return false;
    if (! empty($related_types)) {
       if (! delete_sublist_items('related_products',$product_id,$db)) {
          $error = $db->error;   return false;
       }
       if (! delete_related_items('related_products',$product_id,$db)) {
          $error = $db->error;   return false;
       }
    }
    if (! delete_related_items($category_products_table,$product_id,$db)) {
       $error = $db->error;   return false;
    }
    $query = 'delete from product_activity where parent=?';
    $query = $db->prepare_query($query,$product_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }

    $query = 'delete from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$product_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }

    if (function_exists('custom_update_product'))
       custom_update_product($db,$product_record,DELETERECORD);
    $seo_url = $row['seo_url'];
    if (! empty($cache_catalog_pages)) {
       if (empty($seo_url)) $seo_url = $product_id;
       delete_cached_page($db,$product_id,0,$flags,NULL,$seo_url);
    }
    if ($flags & (FEATURED|UNIQUEURL))
       update_htaccess(1,$product_id,null,$row['websites'],$db);
    if (isset($cloudflare_site)) {
       require_once '../admin/cloudflare-admin.php';
       if ($flags & (FEATURED|UNIQUEURL))
          update_cloudflare_product($product_id,$row['seo_url']);
       update_cloudflare_product_categories($db,$cached_categories,$product_id,
                                            $seo_url);
    }

    return true;
}

function get_product_activity_user($db=null)
{
    global $login_cookie,$auto_reorder_label;

    $admin_user = get_cookie($login_cookie);
    if (! $admin_user) $admin_user = getenv('REMOTE_USER');
    if (! $admin_user) return '';
    if ($admin_user == 'api') return 'API';
    if ($admin_user == 'reorders') {
       if (! isset($auto_reorder_label)) $auto_reorder_label = 'Reorder';
       return $auto_reorder_label.' Module';
    }
    if (! $db) $db = new DB;
    $full_name = get_user_name($db,$admin_user);
    if ($full_name) return $full_name.' ('.$admin_user.')';
    return $admin_user;
}

function write_product_activity($activity,$product_id,$db=null)
{
    if (empty($activity)) return false;
    if (strlen($activity) > 255) $activity = substr($activity,0,255);
    if (! $db) $db = new DB;
    $activity_record = product_activity_record_definition();
    $activity_record['parent']['value'] = $product_id;
    $activity_record['activity_date']['value'] = time();
    $activity_record['activity']['value'] = $activity;
    if (! $db->insert('product_activity',$activity_record)) return false;
    return true;
}

?>
