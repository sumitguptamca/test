<?php
/*
                Inroads Shopping Cart - Common Shopping API Functions

                        Written 2013-2019 by Randall Severy
                         Copyright 2013-2019 Inroads, LLC
*/

global $shopping_modules,$shopping_base_url;

$shopping_modules = null;
$shopping_base_url = null;

function get_shopping_base_url()
{
    global $base_url,$shopping_base_url;
    global $use_development_site,$dev_site_hostname,$live_site_hostname;

    if ($shopping_base_url) return $shopping_base_url;
    if (isset($use_development_site) && $use_development_site)
    $shopping_base_url = str_replace($dev_site_hostname,$live_site_hostname,
                                     $base_url);
    else $shopping_base_url = $base_url;
    return $shopping_base_url;
}

function build_product_url($db,$row)
{
    $id = $row['id'];
    if ($row['flags'] & 9) {
       $seo_url = $row['seo_url'];
       if ($seo_url && ($seo_url != '')) $product_url = $seo_url.'/';
       else $product_url = 'products/'.$id.'/';
    }
    else {
       if (isset($row['seo_category']) && $row['seo_category'])
          $seo_category = $row['seo_category'];
       else if ($id) {
          $query = 'select p.parent from category_products p join ' .
                   'categories c on c.id=p.parent where p.related_id=?' .
                   ' and (isnull(c.flags) or (not c.flags&8)) ' .
                   'order by p.id limit 1';
          $query = $db->prepare_query($query,$id);
          $cat_row = $db->get_record($query);
          if ($cat_row) $seo_category = $cat_row['parent'];
          else $seo_category = 0;
       }
       else $seo_category = 0;
       if ($seo_category) {
          $query = 'select seo_url from categories where id=?';
          $query = $db->prepare_query($query,$seo_category);
          $cat_row = $db->get_record($query);
          if ($cat_row) $seo_url = $cat_row['seo_url'];
          else $seo_url = null;
       }
       else $seo_url = null;
       $product_seo_url = str_replace('&','%2526',$row['seo_url']);
       if ((! $product_seo_url) || ($product_seo_url == ''))
          $product_seo_url = $id;
       if ($seo_url) $product_url = $seo_url.'/'.$product_seo_url.'/';
       else $product_url = 'products/'.$product_seo_url.'/';
    }
    return get_shopping_base_url().$product_url;
}

function build_image_url($db,$row,$image_size='original',$limit='1')
{
    global $use_dynamic_images,$image_subdir_prefix,$image_base_url;

    $query = 'select filename from images where parent_type=1 and parent=?' .
             ' order by sequence limit '.$limit;
    $query = $db->prepare_query($query,$row['id']);
    $image_row = $db->get_record($query);
    if (! $image_row) return null;
    $image_filename = $image_row['filename'];
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;
    if ($use_dynamic_images) {
       $image_url = 'images/'.$image_size.'/';
       if ($image_subdir_prefix) {
          $prefix = substr($image_filename,0,$image_subdir_prefix);
          $image_url .= $prefix.'/';
       }
       $image_url .= rawurlencode($image_filename);
    }
    else if ($image_subdir_prefix) {
       $prefix = substr($image_filename,0,$image_subdir_prefix);
       $image_url = 'images/'.$image_size.'/'.$prefix.'/' .
                    rawurlencode($image_filename);
    }
    else $image_url = 'images/'.$image_size.'/'.rawurlencode($image_filename);
    if (isset($image_base_url)) return $image_base_url.$image_url;
    return get_shopping_base_url().$image_url;
}

function get_product_price($db,$row,$features)
{
    if (($features & SALE_PRICE_PRODUCT) && $row['sale_price'])
       return $row['sale_price'];
    else if ($features & SALE_PRICE_INVENTORY) {
       $query = 'select min(sale_price) as price from product_inventory ' .
                'where parent=?';
       $query = $db->prepare_query($query,$row['id']);
       $price_row = $db->get_record($query);
       if (! $price_row) return null;
       if ($price_row['sale_price']) return $price_row['sale_price'];
    }
    if ($features & REGULAR_PRICE_PRODUCT)
       $price = $row['price'];
    else if ($features & REGULAR_PRICE_INVENTORY) {
       $query = 'select min(price) as price from product_inventory ' .
                'where parent=?';
       $query = $db->prepare_query($query,$row['id']);
       $price_row = $db->get_record($query);
       if (! $price_row) return null;
       $price = $price_row['price'];
    }
    else $price = null;
    return $price;
}

function get_product_list_price($db,$row,$features)
{
    if ($features & LIST_PRICE_PRODUCT)
       $price = $row['list_price'];
    else if ($features & LIST_PRICE_INVENTORY) {
       $query = 'select min(list_price) as list_price from product_inventory ' .
                'where parent=?';
       $query = $db->prepare_query($query,$row['id']);
       $price_row = $db->get_record($query);
       if (! $price_row) return null;
       $price = $price_row['list_price'];
    }
    else $price = null;
    return $price;
}

function get_product_cost($db,$row,$features)
{
    if ($features & PRODUCT_COST_PRODUCT) $cost = $row['cost'];
    else if ($features & PRODUCT_COST_INVENTORY) {
       $query = 'select min(cost) as cost from product_inventory ' .
                'where parent=?';
       $query = $db->prepare_query($query,$row['id']);
       $cost_row = $db->get_record($query);
       if (! $cost_row) return null;
       $cost = $cost_row['cost'];
    }
    else $cost = null;
    return $cost;
}

function get_product_weight($db,$row,$features)
{
    static $default_weight = -1;

    if ($features & WEIGHT_ITEM) {
       $query = 'select weight from product_inventory where parent=? limit 1';
       $query = $db->prepare_query($query,$row['id']);
       $weight_row = $db->get_record($query);
       if (! $weight_row) $weight = null;
       else $weight = $weight_row['weight'];
    }
    else $weight = null;
    if (! $weight) {
       if ($default_weight == -1)
          $default_weight = call_shipping_event('default_weight',array($db),
                                                true,true);
       $weight = $default_weight;
    }
    return $weight;
}

function get_product_category($db,$row)
{
    global $top_category;
    static $cached_categories = array();

    if (isset($row['seo_category']) && $row['seo_category'])
       $seo_category = $row['seo_category'];
    else {
       $query = 'select p.parent from category_products p join ' .
                'categories c on c.id=p.parent where (p.related_id=?)' .
                ' and (isnull(c.flags) or (not c.flags&8)) and ' .
                '(p.parent!=?) order by p.id limit 1';
       $query = $db->prepare_query($query,$row['id'],$top_category);
       $cat_row = $db->get_record($query);
       if (! $cat_row) return '';
       $seo_category = $cat_row['parent'];
    }
    if (! $seo_category) return '';
    if (! empty($cached_categories[$seo_category]))
       return $cached_categories[$seo_category];

    $current_category = $seo_category;
    $categories = array();

    while (! in_array($current_category,$categories)) {
       $categories[] = $current_category;
       $query = 'select s.parent from subcategories s join categories c on ' .
                'c.id=s.parent where (s.related_id=?) and (isnull(c.flags) ' .
                'or (not c.flags&8)) and (s.parent!=?) order by s.parent ' .
                'limit 1';
       $query = $db->prepare_query($query,$current_category,$top_category);
       $cat_row = $db->get_record($query);
       if (empty($cat_row['parent'])) break;
       $current_category = $cat_row['parent'];
    }
    if (empty($categories)) return '';

    $query = 'select id,name,display_name from categories where id in (?)';
    $query = $db->prepare_query($query,$categories);
    $cat_info = $db->get_records($query,'id');
    if (! $cat_info) return '';
    $categories = array_reverse($categories);
    $category = '';
    foreach ($categories as $cat_id) {
       if (! isset($cat_info[$cat_id])) continue;
       if ($category) $category .= ' > ';
       if (empty($cat_info[$cat_id]['display_name']))
          $category .= $cat_info[$cat_id]['name'];
       else $category .= $cat_info[$cat_id]['display_name'];
    }
    $cached_categories[$seo_category] = $category;

    return $category;
}

function get_shopping_status_where()
{
    global $off_sale_option,$sold_out_option,$publish_sold_out_products;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($sold_out_option)) $sold_out_option = 2;
    if (! isset($publish_sold_out_products)) $publish_sold_out_products = true;

    $where = '(isnull(status) or ';
    if (! $publish_sold_out_products) $where .= '(';
    $where .= '(status!='.$off_sale_option.')';
    if (! $publish_sold_out_products)
       $where .= ' and (status!='.$sold_out_option.'))';
    $where .= ')';
    return $where;
}

function check_shopping_status($status)
{
    global $off_sale_option,$sold_out_option,$publish_sold_out_products;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    if (! isset($sold_out_option)) $sold_out_option = 2;
    if (! isset($publish_sold_out_products)) $publish_sold_out_products = true;

    if ($status == $off_sale_option) return false;
    if ((! $publish_sold_out_products) && ($status == $sold_out_option))
       return false;

    return true;
}

$max_shopping_flag = -1;

function add_shopping_flag($dialog,$index,$shopping_flags)
{
    global $max_shopping_flag;

    $dialog->write("<span class=\"shopping_flag\">");
    $checked = ($shopping_flags & (1 << $index));
    $dialog->add_checkbox_field('shopping_flag'.$index,'Publish',$checked);
    if ($index > $max_shopping_flag) $max_shopping_flag = $index;
    $dialog->write("</span>\n");    
}

function parse_shopping_flags($max_shopping_flag)
{
    $flags = 0;
    for ($loop = 0;  $loop <= $max_shopping_flag;  $loop++)
       if (get_form_field('shopping_flag'.$loop) == 'on')
          $flags |= (1 << $loop);
    return $flags;
}

function get_shopping_flags($record,$default_value)
{
    if (isset($record['shopping_flags']['value']))
       $shopping_flags = $record['shopping_flags']['value'];
    else if (isset($record['shopping_flags']))
       $shopping_flags = $record['shopping_flags'];
    else $shopping_flags = 0;
    if ($shopping_flags === '') $shopping_flags = $default_value;
    return $shopping_flags;
}

function get_import_shopping_flags($product_data)
{
    if ($product_data->product_id) {
       if (empty($product_data->product_info['shopping_flags']))
          $shopping_flags = 0;
       else $shopping_flags = $product_data->product_info['shopping_flags'];
    }
    else if (empty($product_data->product_record['shopping_flags']['value']))
       $shopping_flags = 0;
    else $shopping_flags =
       $product_data->product_record['shopping_flags']['value'];
    return $shopping_flags;
}

function shopping_modules_installed()
{
    global $admin_directory,$shopping_modules;

    if (! empty($shopping_modules)) return true;
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $modules_dir = @opendir($admin_directory.'shopping/');
    if (! $modules_dir) return false;
    while (($module = readdir($modules_dir)) !== false) {
       if (substr($module,-4) == '.php') return true;
    }
    return false;
}

function shopping_module_installed($module)
{
    global $admin_directory,$shopping_modules;

    if (! empty($shopping_modules)) {
       if (in_array($module,$shopping_modules)) return true;
       return false;
    }
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    if (file_exists($admin_directory.'shopping/'.$module.'.php')) return true;
    return false;
}

function load_shopping_modules()
{
    global $admin_directory,$shopping_modules;

    $shopping_modules = array();
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    $modules_dir = @opendir($admin_directory.'shopping/');
    if (! $modules_dir) return;
    while (($module = readdir($modules_dir)) !== false) {
       if (substr($module,-4) == '.php') {
          $module_name = substr($module,0,-4);
          $module_file = $admin_directory.'shopping/'.$module;
          require_once $module_file;
          $shopping_modules[] = $module_name;
       }
    }
    sort($shopping_modules);
}

function shopping_module_event_exists($event)
{
    global $shopping_modules;

    if ($shopping_modules === null) load_shopping_modules();
    if (empty($shopping_modules)) return false;
    foreach ($shopping_modules as $module) {
       $function_name = $module.'_'.$event;
       if (function_exists($function_name)) return true;
    }
    return false;
}

function call_shopping_event($event,$parameters,$continue_on_false=true,
                             $return_first_true=false)
{
    global $shopping_modules;

    if ($shopping_modules === null) load_shopping_modules();
    if (empty($shopping_modules)) {
       if ($return_first_true) return false;
       return true;
    }
    foreach ($shopping_modules as $module) {
       $function_name = $module.'_'.$event;
       if (! function_exists($function_name)) continue;
       $ret_value = call_user_func_array($function_name,$parameters);
       if (($ret_value === false) && (! $continue_on_false)) return false;
       if (($ret_value === true) && $return_first_true) return true;
    }
    if ($return_first_true) return false;
    return true;
}

?>
