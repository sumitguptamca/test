<?php
/*
                 Inroads Shopping Cart - Public Product Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once __DIR__.'/../engine/ui.php';
require_once __DIR__.'/../engine/db.php';
require_once __DIR__.'/catalog-public.php';
if (file_exists(__DIR__.'/cartconfig-common.php')) {
   $shopping_cart = true;
   require_once __DIR__.'/cartconfig-common.php';
}
else {
   $shopping_cart = false;
   require_once __DIR__.'/catalog-common.php';
}
if (file_exists(__DIR__.'/../admin/custom-config.php'))
   require_once __DIR__.'/../admin/custom-config.php';

class Product extends Catalog {

function __construct($id_param=null,$db_param=null)
{
    global $category,$category_cookie,$shopping_cart,$catalog_features;
    global $rewrite_catalog_id_urls,$base_url;

    if (get_form_field('jscmd')) return null;

    if (($id_param === null) && isset($category)) {
       $this->path = $category->path;
       $this->db = $category->db;
       $this->id = $category->product_id;
       $this->currency = $category->currency;
       $this->cents_flag = $category->cents_flag;
       $this->exchange_rate = $category->exchange_rate;
       $this->products_table = $category->products_table;
       $this->categories_table = $category->categories_table;
       $this->subcategories_table = $category->subcategories_table;
       $this->category_products_table = $category->category_products_table;
       $this->products_image_type = $category->products_image_type;
       $this->categories_image_type = $category->categories_image_type;
       $this->base_product_url = $category->base_product_url;
       $this->display_product_page = $category->display_product_page;
       $this->wholesale = $category->wholesale;
       $this->account_id = $category->account_id;
       $this->discount = $category->discount;
    }
    else {
       parent::Catalog($db_param);
       if ($id_param !== null) $this->id = $id_param;
       else {
          $id = get_form_field('id');
          if (is_numeric($id)) $this->id = intval($id);
          else $this->id = $this->lookup_product_id($id);
       }
       if ($id_param === null) {
          if ($this->id === null) $this->path = '';
          else {
             $seo_category = $this->get_product_seo_category($this->id);
             if ($seo_category === null) {
                if (isset($this->db->error))
                   process_error('Database Error: '.$this->db->error,-1);
                else $this->process_404();
                $this->id = null;
                return null;
             }
             if (isset($rewrite_catalog_id_urls) && $rewrite_catalog_id_urls &&
                 (strstr($_SERVER['REQUEST_URI'],
                         $this->display_product_page) !== false) &&
                 (strstr($_SERVER['REQUEST_URI'],'norewrite') === false)) {
                $new_url = $this->get_product_seo_url($this->id,$seo_category);
                if (! $new_url) {
                   if (isset($this->db->error))
                      process_error('Database Error: '.$this->db->error,-1);
                   else $this->process_404();
                   $this->id = null;
                   return null;
                }
                if ($new_url) {
                   header('HTTP/1.1 301 Moved Permanently');
                   if (substr($base_url,-1,1) == '/')
                      $url = substr($base_url,0,-1);
                   else $url = $base_url;
                   $url .= $new_url;
                   header('Location: '.$url);
                   exit;
                }
             }
             if ($seo_category == 0) $this->path = '';
             else $this->path = $seo_category;
          }
          setcookie($category_cookie,$this->path,time() + (86400 * 100),'/');
       }
    }
    $this->shopping_cart = $shopping_cart;
    if ($shopping_cart)
       $this->features = get_cart_config_value('features',$this->db);
    else $this->features = $catalog_features;
    $this->attribute_option_ids = array();
    $this->attribute_options = array();
    $this->last_modified = 0;
    if ($this->id === null) $this->process_404();
}

function Product($id_param=null,$db_param=null)
{
    self::__construct($id_param,$db_param);
}

function process_404()
{
    if (file_exists('redirect.conf')) require 'redirect.php';
    else if (file_exists('404.html')) require '404.html';
    else header('HTTP/1.1 404 Not Found');
    exit;
}

function start_page($mtime=null)
{
    if (isset($mtime)) $mtime = max($mtime,$this->last_modified);
    else $mtime = $this->last_modified;
    $this->setup_conteg($mtime);
}

function get_product_flags()
{
    if (isset($this->info)) return $this->info['flags'];
    $query = 'select flags from products where id=?';
    if (isset($this->db)) $db = $this->db;
    else {
       $db = new DB;   $this->db = $db;
    }
    $query = $db->prepare_query($query,$this->id);
    $row = $db->get_record($query);
    if (! empty($row['flags'])) $flags = $row['flags'];
    else $flags = 0;
    return $flags;
}

function get_product_discount($product_id)
{
    global $custom_account_product_prices;

    if (isset($this->product_discounts)) return;
    if ($custom_account_product_prices) {
       $this->product_discounts = null;   return;
    }
    $query = 'select price,discount from account_products where (parent=?) ' .
             'and (related_id=?)';
    $query = $this->db->prepare_query($query,$this->account_id,$product_id);
    $this->product_discounts = $this->db->get_record($query);
}

function get_discount_price_breaks($price_breaks)
{
    $this->get_product_discount($this->id);
    $discount = $this->discount;
    $price_entries = explode('|',$price_breaks);
    $num_entries = count($price_entries);
    for ($loop = 0;  $loop < $num_entries;  $loop++) {
       if ($price_entries[$loop] == '') continue;
       $price_details = explode('-',$price_entries[$loop]);
       $price_details[2] = get_account_product_price($price_details[2],
                              $this->product_discounts,$discount,true);
       $price_entries[$loop] = implode('-',$price_details);
    }
    return implode('|',$price_entries);
}

function get_discount_price($price)
{
    $this->get_product_discount($this->id);
    $discount = $this->discount;
    $price = get_account_product_price($price,$this->product_discounts,
                                       $discount);
    if (function_exists('custom_update_discount_price'))
       custom_update_discount_price($this,$price);
    return $price;
}

function get_discount_inventory_prices($product_id,&$inventory)
{
    global $custom_account_product_prices,$account_product_prices;

    if ($custom_account_product_prices) return;
    if (! ($this->features & REGULAR_PRICE_INVENTORY)) return;
    $query = 'select related_id,discount from account_inventory where ' .
             'parent=? and related_id in (select id from product_inventory ' .
             'where parent=?)';
    $query = $this->db->prepare_query($query,$this->account_id,$product_id);
    $inv_discounts = $this->db->get_records($query,'related_id','discount');
    foreach ($inventory as $inv_index => $inv_info) {
       $inv_id = $inv_info['id'];
       $inventory[$inv_index]['regular_price'] = $inventory[$inv_index]['price'];
       if (! isset($inv_discounts[$inv_id])) {
          $price = null;   $factor = 0;
       }
       else if ($account_product_prices === true)
          $price = $inv_discounts[$inv_id];
       else $factor = (100 - $inv_discounts[$inv_id]) / 100;
       if ($account_product_prices === true) {
          if ($price) $inventory[$inv_index]['price'] = $price;
          else unset($inventory[$inv_index]);
       }
       else $inventory[$inv_index]['price'] = round($inv_info['price'] * $factor,2);
    }
}

function append_count_subqueries()
{
    global $shopping_cart,$subproduct_type;

    if (isset($this)) {
       if (! $this->shopping_cart) return '';
    }
    else if (! $shopping_cart) return '';
    if (! isset($subproduct_type)) $subproduct_type = 1;
    $query = ',(select count(sps.id) from related_products sps where (parent=' .
             'p.id) and (related_type='.$subproduct_type .
             ')) as sub_product_count';
    $query .= ',(select count(rps.id) from related_products rps where ' .
              '(parent=p.id) and (related_type=0)) as related_product_count';
    $query .= ',(select count(atrs.id) from product_attributes atrs where ' .
              'parent=p.id) as attribute_count';
    return $query;
}

function load_info()
{
    global $enable_product_flags;

    if (empty($this->id)) {
        $this->info = null;   return null;
    }
    $query = 'select *'.$this->append_count_subqueries() .
             ' from '.$this->products_table.' p where id='.$this->id;
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_info',1,$this->db);
    $this->info = $this->db->get_record($query);
    if (! $this->info) {
       if (isset($this->db->error))
          process_error("Database Error: ".$this->db->error,-1);
       else $this->process_404();
       $this->info = null;
       return null;
    }
    if (! isset($this->info['id'])) $this->info['id'] = $this->id;
    $this->info['num_images'] = 0;
    if (! empty($this->info['account_discount']))
       $this->discount = floatval($this->info['account_discount']);
    $this->process_widgets($this->info);
    if (isset($this->info['last_modified']) &&
        ($this->info['last_modified'] > $this->last_modified))
       $this->last_modified = $this->info['last_modified'];
    if ($this->wholesale &&
        (! ($this->get_product_flags() & NO_ACCOUNT_DISCOUNTS))) {
       if ($this->features & REGULAR_PRICE_BREAKS)
          $this->info['price_breaks'] =
             $this->get_discount_price_breaks($this->info['price_breaks']);
       if ($this->features & REGULAR_PRICE_PRODUCT) {
          $this->info['regular_price'] = $this->info['price'];
          $this->info['price'] = $this->get_discount_price($this->info['price']);
       }
       if ($this->features & SALE_PRICE_PRODUCT) {
          $this->info['regular_sale_price'] = $this->info['sale_price'];
          $this->info['sale_price'] = $this->get_discount_price($this->info['sale_price']);
       }
    }
    if ((! empty($enable_product_flags)) &&
        ((! empty($this->info['left_flag'])) ||
         (! empty($this->info['right_flag'])))) {
       require_once 'admin/productflags-admin.php';
       load_product_flag_info($this->db,$this->info);
    }
    return $this->info;
}

function load_data($data_type=null)
{
    if (! $this->id) {
       $this->data = null;   $this->info['data'] = null;
       $this->info['num_data'] = 0;   return null;
    }
    $query = "select id,data_type,label,data_value from product_data where " .
             "(parent=" . $this->id.")";
    if ($data_type) $query .= " and (data_type=".$data_type.")";
    $query .= " order by data_type,sequence";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'product','load_data',1,$this->db);
    $this->data = $this->db->get_records($query,'id');
    if (! $this->data) $this->data = array();
    $this->info['data'] = $this->data;
    $this->info['num_data'] = count($this->data);
    return $this->data;
}

function load_discounts($product_id=null)
{
    global $shopping_cart;

    if (! $shopping_cart) return array();
    if (! $product_id) $product_id = $this->id;
    if (! $product_id) {
       if (isset($this)) $this->discounts = null;
       return null;
    }
    if (isset($this)) {
       $db = $this->db;
       if ($this->wholesale &&
           (! ($this->get_product_flags() & NO_ACCOUNT_DISCOUNTS)))
          $discount_type = 1;
       else $discount_type = 0;
    }
    else {
       $db = new DB;   $discount_type = 0;
    }
    $query = "select * from product_discounts where parent=".$product_id .
             " and discount_type=".$discount_type." order by start_qty,end_qty";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'product','load_discounts',1,$db);
    $discounts = $db->get_records($query,'id');
    if (! $discounts) $discounts = array();
    if (isset($this)) {
       $this->discounts = $discounts;
       $this->info['discounts'] = $this->discounts;
    }
    return $discounts;
}

function inventory_available($inv_row,$features)
{
    global $enable_inventory_available;

    if ($features & ALLOW_BACKORDERS) return true;
    if (($features & INVENTORY_BACKORDERS) &&
        (! empty($inv_row['backorder']))) return true;
    if ($features & MAINTAIN_INVENTORY) {
       if ((! empty($enable_inventory_available)) &&
           (! empty($inv_row['available']))) return true;
       if (empty($inv_row['qty'])) return false;
       if ($inv_row['qty'] < 0) return false;
       return true;
    }
    if (empty($inv_row['available'])) return false;
/* Availability is now based on inventory qty or available flag, not price
    if (($features & REGULAR_PRICE_INVENTORY) && empty($inv_row['price']) &&
        ((! ($features & SALE_PRICE_INVENTORY)) ||
         empty($inv_row['sale_price']))) return false;
*/
    return true;
}

function load_inventory()
{
    global $hide_off_sale_inventory;

    if (! $this->id) {
       $this->inventory = null;   return null;
    }
    if (isset($this->inventory)) return $this->inventory;
    $query = 'select * from product_inventory where parent=? order by sequence';
    $query = $this->db->prepare_query($query,$this->id);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_inventory',1,
                                   $this->db);
    $this->inventory = $this->db->get_records($query,'id');
    if (! $this->inventory) $this->inventory = array();
    if (! empty($hide_off_sale_inventory)) {
       foreach ($this->inventory as $id => $inv_row) {
          if (! $this->inventory_available($inv_row,$this->features))
             unset($this->inventory[$id]);
       }
    }
    if ($this->wholesale &&
        (! ($this->get_product_flags() & NO_ACCOUNT_DISCOUNTS)))
       $this->get_discount_inventory_prices($this->id,$this->inventory);
    $this->info['inventory'] = $this->inventory;
    return $this->inventory;
}

function load_images()
{
    global $use_dynamic_images;

    if (! $this->id) {
       $this->images = null;   $this->info['images'] = null;
       $this->image_data = null;   $this->info['image_data'] = null;
       $this->info['num_images'] = 0;   return null;
    }
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $this->images = array();
    $this->image_data = array();
    $query = "select * from images where parent_type=" .
             $this->products_image_type." and parent=".$this->id .
             " order by sequence,id";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'product','load_images',1,$this->db);
    $result = $this->db->query($query);
    if ($result) {
       while ($row = $this->db->fetch_assoc($result)) {
          $row['dynamic'] = $use_dynamic_images;
          $this->images[$row['filename']] = strip_tags($row['caption']);
          $this->image_data[$row['filename']] = $row;
       }
       $this->db->free_result($result);
    }
    else if (isset($this->db->error))
       process_error("Database Error: ".$this->db->error,-1);
    $this->info['images'] = $this->images;
    $this->info['image_data'] = $this->image_data;
    $this->info['num_images'] = count($this->images);
    return $this->images;
}

function load_callouts()
{
    global $use_callout_groups;

    if (! isset($this->info['image_data'])) return null;
    if (! isset($use_callout_groups)) $use_callout_groups = false;
    if ($use_callout_groups) $index = 1;
    $ids = array();   $filenames = array();
    foreach ($this->info['image_data'] as $filename => $row) {
       if ($use_callout_groups) {
          if (! $row['callout_group']) continue;
          $id = $row['callout_group'];
       }
       else $id = $row['id'];
       if (in_array($id,$ids)) continue;
       $ids[] = $id;
       $filenames[$row['id']] = $filename;
    }
    if (count($ids) == 0) return null;
    $query = 'select * from callouts where parent in (?) ' .
             'order by parent,sequence,id';
    $query = $this->db->prepare_query($query,$ids);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_callouts',1,
                                   $this->db);
    $rows = $this->db->get_records($query);
    if (! $rows) {
       if (isset($this->db->error))
          process_error('Database Error: '.$this->db->error,-1);
    }
    else foreach ($rows as $row) {
       $parent = $row['parent'];
       if ($use_callout_groups) {
          foreach ($this->info['image_data'] as $filename => $data_row) {
             if ($data_row['callout_group'] != $parent) continue;
             if (! isset($this->info['image_data'][$filename]['callouts']))
                $this->info['image_data'][$filename]['callouts'] = array();
             $row['id'] = $index++;
             $row['parent'] = $this->info['image_data'][$filename]['id'];
             $this->info['image_data'][$filename]['callouts'][] = $row;
          }
       }
       else {
          $filename = $filenames[$parent];
          if (! isset($this->info['image_data'][$filename]['callouts']))
             $this->info['image_data'][$filename]['callouts'] = array();
          $this->info['image_data'][$filename]['callouts'][] = $row;
       }
    }
    return $this->info['image_data'];
}

function load_rebates()
{
   if (! $this->id) {
       $this->rebates = null;   $this->info['rebates'] = null;   return null;
    }
    $curr_time = time();
    $query = 'select * from product_rebates where (parent=?) and ' .
             '(start_date<=?) and (end_date>=?) order by id';
    $query = $this->db->prepare_query($query,$this->id,$curr_time,$curr_time);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_rebates',1,
                                   $this->db);
    $this->rebates = $this->db->get_records($query);
    if (isset($this->db->error)) {
       $this->rebates = array();
       process_error("Database Error: ".$this->db->error,-1);
    }
    $this->info['rebates'] = $this->rebates;
    return $this->rebates;
}

function find_attribute($option_id)
{
    foreach ($this->attribute_options as $attr_id => $option_list) {
       foreach ($option_list as $compare_id => $option_info) {
          if ($option_id == $compare_id) return $attr_id;
       }
    }
    return null;
}

function load_attributes()
{
    global $filter_inventory_attributes;

    if (! $this->id) {
       $this->attributes = null;   return null;
    }
    if (! isset($filter_inventory_attributes))
       $filter_inventory_attributes = true;
    $this->attributes = array();   $first_field = true;   $ids = '';
    $query = "select a.* from product_attributes p left join " .
             "attributes a on a.id=p.related_id where p.parent=" .
             $this->id." order by sequence";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'product','load_attributes',1,
                                   $this->db);
    $attributes = $this->db->get_records($query);
    $index = 0;
    if ($attributes) foreach ($attributes as $attr_info) {
       $attr_id = $attr_info['id'];
       if (! $attr_id) continue;
       $this->attributes[$attr_id] = $attr_info;
       $this->attributes[$attr_id]['order_id'] = $index;
       if ($first_field) $first_field = false;
       else $ids .= ',';
       $ids .= $attr_id;
       $this->attribute_options_ids[] = $attr_id;
       $this->attribute_options[$attr_id] = array();
       $index++;
    }

    if (! isset($this->inventory)) $this->load_inventory();

    if ($ids == '') {
       $this->attributes = null;   return null;
    }
    else {
       $query = "select o.*,(select filename from images where " .
                "parent_type=2 and parent=o.id limit 1)" .
                " as image from attribute_options o where o.parent in (" .
                $ids.") order by o.parent,o.sequence";
       if (function_exists("custom_update_catalog_query"))
          custom_update_catalog_query($query,'product','load_attributes',2,
                                      $this->db);
       $attr_options = $this->db->get_records($query);
       if ($attr_options) foreach ($attr_options as $option) {
          if ($filter_inventory_attributes &&
              (($this->attributes[$option['parent']]['sub_product'] == 1) ||
               ($this->attributes[$option['parent']]['dynamic'] == 1))) {
             $option_found = false;   $option_id = $option['id'];
             foreach ($this->inventory as $inv_row) {
                $attributes = $inv_row['attributes'];
                if (strpos($attributes,'|') !== false)
                   $inv_options = explode('|',$attributes);
                else $inv_options = explode('-',$attributes);
                if (in_array($option_id,$inv_options)) {
                   $option_found = true;   break;
                }
             }
             if (! $option_found) continue;
          }
          $this->attribute_options[$option['parent']][$option['id']] = $option;
       }
    }

    if (! $filter_inventory_attributes) return $this->attributes;

    foreach ($this->inventory as $inventory) {
       $attributes = $inventory['attributes'];
       if (! $attributes) continue;
       if (strpos($attributes,'|') !== false)
          $attributes = explode('|',$attributes);
       else $attributes = explode('-',$attributes);
       foreach ($attributes as $option_id) {
          if (! is_numeric($option_id)) continue;
          $parent = $this->find_attribute($option_id);
          if (! $parent) continue;
          if (! isset($this->attributes[$parent])) continue;
          if (! isset($this->attributes[$parent]['count']))
             $this->attributes[$parent]['count'] = 1;
          else $this->attributes[$parent]['count']++;
          if (! isset($this->attribute_options[$parent][$option_id]['count']))
             $this->attribute_options[$parent][$option_id]['count'] = 1;
          else $this->attribute_options[$parent][$option_id]['count']++;
       }
    }
    foreach ($this->attributes as $attr_id => $attr_info) {
       if (($attr_info['sub_product'] != 1) && ($attr_info['dynamic'] != 1))
          continue;
       if (! isset($attr_info['count'])) {
          unset($this->attributes[$attr_id]);
          unset($this->attribute_options[$attr_id]);
       }
    }
    foreach ($this->attribute_options as $attr_id => $option_list) {
       foreach ($option_list as $option_id => $option_info) {
          $parent = $option_info['parent'];
          if (! isset($this->attributes[$parent])) continue;
          if (($this->attributes[$parent]['sub_product'] != 1) &&
              ($this->attributes[$parent]['dynamic'] != 1)) continue;
          if (! isset($option_info['count']))
             unset($this->attribute_options[$attr_id][$option_id]);
       }
    }

    return $this->attributes;
}

function load_related_products($related_type=0,$related_ids=null,
                               $product_id=null,$db=null,$order_by=null)
{
    global $products_table,$off_sale_option;

    if (! $product_id) $product_id = $this->id;
    if ((! $product_id) && (! $related_ids)) {
       if (! isset($this->related)) $this->related = array();
       $this->related[$related_type] = null;   return null;
    }
    if (isset($this)) {
       $db = $this->db;   $products_table = $this->products_table;
       $features = $this->features;
    }
    else {
       if (! $db) $db = new DB;
       $features = 0;
    }
    if (empty($products_table)) $products_table = 'products';
    if (! isset($off_sale_option)) $off_sale_option = 1;
    $related_products = array();   $first_field = true;
    if (($features & MAINTAIN_INVENTORY) && ($features & HIDE_OUT_OF_STOCK)) {
       if ($related_ids)
          $query = 'select p.*'.@Product::append_count_subqueries() .
                   ' from '.$products_table.' p left join ' .
                   'product_inventory i on i.parent=p.id where (p.id in (' .
                   $related_ids.')) and (isnull(p.status) or (p.status!=' .
                   $off_sale_option.')) and (! isnull(i.qty)) and (i.qty>0)';
       else {
          $query = 'select p.*'.@Product::append_count_subqueries() .
                   ' from related_products r left join ' .
                   $products_table.' p on p.id=r.related_id left join ' .
                   'product_inventory i on i.parent=p.id where (r.parent=' .
                   $product_id.') and (r.related_type='.$related_type .
                   ') and (isnull(p.status) or (p.status!=' .
                   $off_sale_option.')) and (! isnull(i.qty)) and (i.qty>0)';
          if (! $order_by) $order_by = 'r.sequence';
       }
    }
    else if ($related_ids)
       $query = 'select p.*'.@Product::append_count_subqueries() .
                ' from '.$products_table.' p where (p.id in (' .
                $related_ids.')) and ((p.status!='.$off_sale_option .
                ') or isnull(p.status))';

    else {
       $query = 'select p.*'.@Product::append_count_subqueries() .
                ' from related_products r left join ' .
                $products_table.' p on p.id=r.related_id where (r.parent=' .
                $product_id.') and (r.related_type='.$related_type .
                ') and ((p.status!='.$off_sale_option .
                ') or isnull(p.status))';
       if (! $order_by) $order_by = 'r.sequence';
    }
    if ($order_by) $query .= ' order by '.$order_by;
    $related_ids = '';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_products',1,
                                   $db);
    $rows = $db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $related_id = $row['id'];
          if (! $related_id) continue;
          if (isset($related_products[$related_id])) continue;
          $related_products[$related_id] = new Product($related_id,$db);
          $related_products[$related_id]->info = $row;
          if ($first_field) $first_field = false;
          else $related_ids .= ',';
          $related_ids .= $related_id;
          $related_products[$related_id]->discounts = array();
          $related_products[$related_id]->inventory = array();
          $related_products[$related_id]->images = array();
          $related_products[$related_id]->image_data = array();
          $related_products[$related_id]->info['num_images'] = 0;
          $related_products[$related_id]->attributes = array();
          if (isset($this) && ($row['last_modified'] > $this->last_modified))
             $this->last_modified = $row['last_modified'];

       }
    }
    else if (isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    if (isset($this)) {
       if (! isset($this->related)) $this->related = array();
       $this->related[$related_type] = $related_products;
       if (! isset($this->related_ids)) $this->related_ids = array();
       $this->related_ids[$related_type] = $related_ids;
    }
    return $related_products;
}

function load_related_discounts($related_type=0)
{
    global $shopping_cart;

    if (! $shopping_cart) return array();
    if ((! isset($this->related_ids[$related_type])) ||
        ($this->related_ids[$related_type] == '')) return;
    $query = 'select * from product_discounts where parent in (' .
             $this->related_ids[$related_type] .
             ') order by parent,start_qty,end_qty';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_discounts',1,
                                   $this->db);
    $rows = $this->db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $row_id = $row['id'];   $parent = $row['parent'];
          $this->related[$related_type][$parent]->discounts[$row_id] = $row;
       }
    }
    else if (isset($this->db->error))
       process_error('Database Error: '.$this->db->error,-1);
    $related_ids = explode(',',$this->related_ids[$related_type]);
    foreach ($related_ids as $index => $parent)
       $this->related[$related_type][$parent]->info['discounts'] =
          $this->related[$related_type][$parent]->discounts;
}

function load_related_inventory($related_type=0,$related_ids=null,
                                $related_products=null,$db=null)
{
    global $shopping_cart;

    if (! $shopping_cart) return array();
    if (isset($this)) {
       if (empty($this->related_ids[$related_type])) return null;
       $related_ids = $this->related_ids[$related_type];
       $db = $this->db;
    }
    else if ((! $related_ids) || (! $related_products)) return null;
    else if (! $db) $db = new DB;
    $query = 'select * from product_inventory where parent in (' .
             $related_ids.') order by parent,sequence';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_inventory',1,
                                   $db);
    $rows = $db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $inventory_id = $row['id'];   $parent = $row['parent'];
          if (isset($this))
             $this->related[$related_type][$parent]->
                    inventory[$inventory_id] = $row;
          else $related_products[$parent]->inventory[$inventory_id] = $row;
       }
       if (isset($this) && $this->wholesale &&
           (! ($this->get_product_flags() & NO_ACCOUNT_DISCOUNTS))) {
          $ids = explode(',',$this->related_ids[$related_type]);
          foreach ($ids as $id)
             $this->get_discount_inventory_prices($id,
                $this->related[$related_type][$id]->inventory);
       }

    }
    else if (isset($db->error))
       process_error('Database Error: '.$db->error,-1);
    if (isset($this)) return $this->related[$related_type];
    else return $related_products;
}

function load_related_images($related_type=0)
{
    global $use_dynamic_images;

    if ((! isset($this->related_ids[$related_type])) ||
        ($this->related_ids[$related_type] == '')) return;
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $query = 'select * from images where parent_type=' .
             $this->products_image_type.' and parent in (' .
             $this->related_ids[$related_type].') order by parent,sequence,id';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_images',1,
                                   $this->db);
    $rows = $this->db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $row['dynamic'] = $use_dynamic_images;
          $filename = $row['filename'];   $parent = $row['parent'];
          $this->related[$related_type][$parent]->images[$filename] =
             strip_tags($row['caption']);
          $this->related[$related_type][$parent]->image_data[$filename] = $row;
       }
    }
    else if (isset($this->db->error))
       process_error('Database Error: '.$this->db->error,-1);
    $related_ids = explode(',',$this->related_ids[$related_type]);
    foreach ($related_ids as $index => $parent) {
       $this->related[$related_type][$parent]->info['images'] =
          $this->related[$related_type][$parent]->images;
       $this->related[$related_type][$parent]->info['image_data'] =
          $this->related[$related_type][$parent]->image_data;
       $this->related[$related_type][$parent]->info['num_images'] =
          count($this->related[$related_type][$parent]->images);
    }
}

function load_related_attributes($related_type=0)
{
    if ((! isset($this->related_ids[$related_type])) ||
        ($this->related_ids[$related_type] == '')) return;
    $first_field = true;   $attr_ids = '';
    $query = 'select p.parent,a.* from product_attributes p left join ' .
             'attributes a on a.id=p.related_id where p.parent in (' .
             $this->related_ids[$related_type].') order by p.parent,sequence';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_attributes',
                                   1,$this->db);
    $rows = $this->db->get_records($query);
    if ($rows) {
       $current_parent = -1;
       foreach ($rows as $row) {
          $attr_id = $row['id'];
          if (! $attr_id) continue;
          $parent = $row['parent'];
          if ($parent != $current_parent) {
             $current_parent = $parent;   $index = 0;
          }
          $this->related[$related_type][$parent]->attributes[$attr_id] = $row;
          $this->related[$related_type][$parent]->attributes[$attr_id]['order_id'] = $index;
          $index++;
          if (! in_array($attr_id,$this->attribute_option_ids)) {
             if ($first_field) $first_field = false;
             else $attr_ids .= ',';
             $attr_ids .= $attr_id;
             $this->attribute_options_ids[] = $attr_id;
             if (! isset($this->attribute_options[$attr_id]))
                $this->attribute_options[$attr_id] = array();
          }
       }
    }
    else if (isset($this->db->error))
       process_error('Database Error: '.$this->db->error,-1);

    if ($attr_ids == '') return;
    $query = 'select o.*,(select filename from images where parent_type=2 and parent=o.id limit 1)' .
             ' as image from attribute_options o where o.parent in (' .
             $attr_ids.') order by o.parent,o.sequence';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_related_attributes',
                                   2,$this->db);
    $rows = $this->db->get_records($query);
    if ($rows) {
       foreach ($rows as $row)
          $this->attribute_options[$row['parent']][$row['id']] = $row;
    }
}

function load_popular()
{
    if (! $this->id) {
       $this->popular = null;   return null;
    }
    if (isset($this->popular)) return $this->popular;
    $query = 'select * from popular_products where parent=? order by id';
    $query = $this->db->prepare_query($query,$this->id);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_popular',1,
                                   $this->db);
    $this->popular = $this->db->get_records($query);
    if (! $this->popular) $this->popular = array();
    $this->info['popular'] = $this->popular;
    return $this->popular;
}

function get_attributes($attr_id,$attr_type,$field_name,$initial_label,
                        $class=null,$onchange_event=null,$extra=null,
                        $base_price=null,$attr_row=null)
{
    $return_data = '';
    if (! isset($this->attribute_options[$attr_id])) return '';
    $attribute_options = $this->attribute_options[$attr_id];
    if (count($attribute_options) == 0) {
       $attr_index = 0;
       foreach ($this->attributes as $attribute_id => $attr_info) {
          if ($attribute_id == $attr_id) break;
          $attr_index++;
       }
       $option_values = array();
       foreach ($this->inventory as $inventory_id => $inventory_data) {
          $attr_array = explode('|',$inventory_data['attributes']);
          if (isset($attr_array[$attr_index]) &&
              (! in_array($attr_array[$attr_index],$option_values)))
             $option_values[] = $attr_array[$attr_index];
       }
       asort($option_values);
       $attribute_options = array();
       foreach ($option_values as $option_value) {
          $attribute_options[$option_value] = array();
          $attribute_options[$option_value]['default_value'] = false;
          $attribute_options[$option_value]['name'] = $option_value;
       }
    }
    if ($attr_type == 0) {
       $return_data .= "<select name=\"".$field_name."\"";
       if ($extra) $return_data .= " ".$extra;
       if ($class) $return_data .= " class=\"".$class."\"";
       if ($onchange_event) $return_data .= " onChange=\"".$onchange_event."\"";
       $return_data .= ">\n";
       if ($initial_label !== null)
          $return_data .= "   <option value=\"\">".$initial_label."</option>\n";
       foreach ($attribute_options as $id => $option) {
          $return_data .= "   <option value=\"".$id."\"";
          if ($option['default_value']) $return_data .= " selected";
          $return_data .= ">".$option['name'];
          if ($base_price && isset($option['adjust_type']) &&
              $option['adjustment']) {
             if ($option['adjust_type'] == 1)
                $attribute_price = $base_price * ($option['adjustment'] / 100);
             else $attribute_price = $option['adjustment'];
             if ($attribute_price != 0)
                $return_data .= "&nbsp;&nbsp;&nbsp;(+" .
                   $this->get_amount($attribute_price).")";
          }
          $return_data .= "</option>\n";
       }
       $return_data .= "</select>\n";
    }
    else if ($attr_type == 1) {
       $option_index = 0;
       foreach ($attribute_options as $id => $option) {
          $return_data .= "<span><input type=\"radio\" name=\"".$field_name."\" " .
                          "value=\"".$id."\" id=\"".$field_name."_" .
                          $option_index."\"";
          if ($extra) $return_data .= " ".$extra;
          if ($class) $return_data .= " class=\"".$class."\"";
          if ($option['default_value']) $return_data .= " checked";
          if ($onchange_event) $return_data .= " onClick=\"".$onchange_event."\"";
          $return_data .= "><label for=\"".$field_name."_".$option_index."\">" .
                          $option['name'];
          if ($base_price && isset($option['adjust_type']) &&
              $option['adjustment']) {
             if ($option['adjust_type'] == 1)
                $attribute_price = $base_price * ($option['adjustment'] / 100);
             else $attribute_price = $option['adjustment'];
             if ($attribute_price != 0)
                $return_data .= "&nbsp;&nbsp;&nbsp;(+" .
                   $this->get_amount($attribute_price).")";
          }
          $return_data .= "</label></span>\n";
          $option_index++;
       }
    }
    else if ($attr_type == 2) {
       list($id,$option) = each($attribute_options);
       $return_data .= "<span><input type=\"checkbox\" name=\"".$field_name .
                       "\" id=\"".$field_name."\" value=\"".$id."\"";
       if ($extra) $return_data .= " ".$extra;
       if ($class) $return_data .= " class=\"".$class."\"";
       if ($option['default_value']) $return_data .= " checked";
       if ($onchange_event) $return_data .= " onClick=\"".$onchange_event."\"";
       $return_data .= "><label for=\"".$field_name."\">".$option['name'];
       if ($base_price && isset($option['adjust_type']) &&
           $option['adjustment']) {
          if ($option['adjust_type'] == 1)
             $attribute_price = $base_price * ($option['adjustment'] / 100);
          else $attribute_price = $option['adjustment'];
          if ($attribute_price != 0)
             $return_data .= "&nbsp;&nbsp;&nbsp;(+" .
                $this->get_amount($attribute_price).")";
       }
       $return_data .= "</label></span>\n";
    }
    else if ($attr_type == 4) {
       if (! $attr_row)
          return "Missing attribute row information for TextArea attribute";
       $return_data .= "<textarea name=\"".$field_name."\" id=\"".$field_name."\"";
       if ($extra) $return_data .= " ".$extra;
       if ($class) $return_data .= " class=\"".$class."\"";
       $num_rows = $attr_row['height'];
       if (($attr_row['wrap'] != WRAP_OFF) && (get_browser_type() == FIREFOX))
          $num_rows--;
       $return_data .= " rows=\"".$num_rows."\" cols=\"" .
                       $attr_row['width']."\" wrap=\"";
       if ($attr_row['wrap'] == WRAP_OFF) $return_data .= " off";
       else if ($attr_row['wrap'] == WRAP_HARD) $return_data .= " hard";
       else $return_data .= " soft";
       $return_data .= "\"";
       if ($onchange_event) $return_data .= " onChange=\"".$onchange_event."\"";
       $return_data .= "></textarea>\n";
    }
    else $return_data .= "Unsupported attribute type ".$attr_type;

    return $return_data;
}

function display_attributes($attr_id,$attr_type,$field_name,$initial_label,
                            $class=null,$onchange_event=null,$extra=null,
                            $base_price=null)
{
    print $this->get_attributes($attr_id,$attr_type,$field_name,$initial_label,
                                $class,$onchange_event,$extra,$base_price);
}

function parse_attributes($attributes)
{
    if (isset($this->attributes)) {
       $no_options = false;
       foreach ($this->attributes as $attr_id => $attr_info) {
          if (! isset($this->attribute_options)) $no_options = true;
          else if (! isset($this->attribute_options[$attr_id]))
             $no_options = true;
          else if (count($this->attribute_options[$attr_id]) == 0)
             $no_options = true;
       }
    }
    else $no_options = false;
    if ($no_options) $attr_array = explode('|',$attributes);
    else $attr_array = explode('-',$attributes);

    return $attr_array;
}

function get_lowest_price($inventory,$attr_count,&$price,&$sale_price,&$list_price)
{
    $price = 0;   $sale_price = 0;   $list_price = 0;
    if (! $inventory) return;
    foreach ($inventory as $inventory_id => $inventory_data) {
       if ($inventory_data['attributes'] == '') $num_attrs = 0;
       else {
          $attr_array = $this->parse_attributes($inventory_data['attributes']);
          $num_attrs = count($attr_array);
       }
       if ($num_attrs != $attr_count) continue;
       if (isset($inventory_data['sale_price']) &&
           ($inventory_data['sale_price'] != 0) &&
           (($sale_price == 0) || ($inventory_data['sale_price'] < $sale_price)))
          $sale_price = $inventory_data['sale_price'];
       if (isset($inventory_data['price']) &&
           ($inventory_data['price'] != 0) &&
           (($price == 0) || ($inventory_data['price'] < $price)))
          $price = $inventory_data['price'];
       if (isset($inventory_data['list_price']) &&
           ($inventory_data['list_price'] != 0) &&
           (($list_price == 0) || ($inventory_data['list_price'] < $list_price)))
          $list_price = $inventory_data['list_price'];
    }
}

function get_robots_tag()
{
    $current_category = $this->get_current_category();
    if (isset($this->info['seo_category']))
       $seo_category = $this->info['seo_category'];
    else $seo_category = 0;
    if ($seo_category == -1) return 'INDEX,FOLLOW';
    if (($seo_category === null) || ($seo_category == '')) $seo_category = 0;
    if ($seo_category != 0) {
       if ($seo_category == $current_category) return 'INDEX,FOLLOW';
       return 'NOINDEX,NOFOLLOW';
    }

    if (empty($this->id)) return 'INDEX,FOLLOW';
    $query = 'select parent from category_products where related_id=? ' .
             'order by id limit 1';
    $query = $this->db->prepare_query($query,$this->id);
    $row = $this->db->get_record($query);
    if (! $row) return 'INDEX,FOLLOW';
    $first_category = $row['parent'];
    if ($first_category == $current_category) return 'INDEX,FOLLOW';
    return 'NOINDEX,NOFOLLOW';
}

function print_robots_tag()
{
    print $this->get_robots_tag();
}

function end_page()
{
    if (isset($this->conteg)) $this->show_conteg();
}

};

function load_inventory()
{
    global $inventory_fields,$option_fields,$amount_cents_flag;
    global $shopping_cart,$catalog_features,$user_cookie,$enable_wholesale;
    global $account_product_prices,$hide_off_sale_inventory,$attribute_fields;
    global $hide_off_sale_options,$enable_inventory_available;

    if (! isset($hide_off_sale_options)) $hide_off_sale_options = true;
    if ($shopping_cart) $features = get_cart_config_value('features');
    else $features = $catalog_features;
    write_javascript_header();
    if ($features & REGULAR_PRICE_BREAKS)
       print 'if (typeof(price_break_data)=="undefined") ' .
             'var price_break_data=new Array();'."\n";
    print 'if (typeof(attribute_data)=="undefined") ' .
          'var attribute_data=new Array();'."\n";
    print 'if (typeof(attribute_options)=="undefined") ' .
          'var attribute_options=new Array();'."\n";
    print 'if (typeof(attribute_conditions)=="undefined") ' .
          'var attribute_conditions=new Array();'."\n";
    print 'if (typeof(inventory_data)=="undefined") ' .
          'var inventory_data=new Array();'."\n";
    $id = get_form_field('id');
    if (! $id) $id = get_form_field('amp;id');
    if ($id && (! is_numeric($id))) $id = null;
    if (! $id) {
       $ids = get_form_field('ids');
       if (! $ids) $ids = get_form_field('amp;ids');
       if (! $ids) {
          log_error('No id or ids found for load_inventory call');   return;
       }
       $ids = trim($ids,' ,');   $id_array = explode(',',$ids);
    }
    else $id_array = array($id);
    $option_ids = get_form_field('optionids');
    $db = new DB;

    if ($features & REGULAR_PRICE_BREAKS) {
       $query = 'select id,price_break_type,price_breaks from products ' .
                'where id in (?)';
       $query = $db->prepare_query($query,$id_array);
       $price_breaks = $db->get_records($query,'id');
    }

    $query = 'select id,flags from products where id in (?)';
    $query = $db->prepare_query($query,$id_array);
    $flags = $db->get_records($query,'id','flags');

    $wholesale = false;   $account_id = -1;   $discount = 0;
    if (($features & (REGULAR_PRICE_INVENTORY|REGULAR_PRICE_BREAKS)) &&
        (! empty($enable_wholesale))) {
       $customer_id = get_cookie($user_cookie);
       if ($customer_id) {
          $query = 'select account_id from customers where id=?';
          $query = $db->prepare_query($query,$customer_id);
          $row = $db->get_record($query);
          if ($row && $row['account_id']) {
             $wholesale = true;
             $account_id = $row['account_id'];
             $query = 'select status,discount_rate from accounts where id=?';
             $query = $db->prepare_query($query,$account_id);
             $row = $db->get_record($query);
             if ($row) {
                if ($row['status'] != 0) $wholesale = false;
                else if ($row['discount_rate'])
                   $discount = floatval($row['discount_rate']);
             }
          }
       }
    }
    if ($wholesale) {
       $query = 'select related_id,price,discount from account_products ' .
                'where (parent=?) and (related_id in (?))';
       $query = $db->prepare_query($query,$account_id,$id_array);
       $product_discounts = $db->get_records($query,'related_id');
       if ($features & REGULAR_PRICE_INVENTORY) {
          $query = 'select related_id,discount from account_inventory where ' .
                   'parent=? and related_id in (select id from product_inventory ' .
                   'where parent in (?))';
          $query = $db->prepare_query($query,$account_id,$id_array);
          $inv_discounts = $db->get_records($query,'related_id','discount');
       }
    }

    $query = 'select a.related_id,(select count(id) from attribute_options ' .
             'where parent=a.related_id) as num_options from ' .
             'product_attributes a where parent in (?)';
    $query = $db->prepare_query($query,$id_array);
    $no_options = false;
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_inventory',1,$db);
    $rows = $db->get_records($query);
    if ($rows) {
       $no_options = false;
       foreach ($rows as $row) {
          if ($row['num_options'] == 0) $no_options = true;
       }
    }
    if ($no_options) print "var no_options = true;\n";
    else print "var no_options = false;\n";

    $base_query = 'select p.parent,a.* from product_attributes p right join ' .
                  'attributes a on a.id=p.related_id where ';
    if ($id) {
       $query = $base_query.'p.parent=? order by sequence';
       $query = $db->prepare_query($query,$id);
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'product','load_inventory',2,$db);
       $attribute_data = $db->get_records($query);
       if (! $attribute_data) $attribute_data = array();
    }
    else {
       $attribute_data = array();
       foreach ($id_array as $index => $id_value) {
          $query = $base_query.'(p.parent=?) order by sequence';
          $query = $db->prepare_query($query,$id_value);
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'product','load_inventory',2,
                                         $db);
          $rows = $db->get_records($query);
          if ($rows) $attribute_data = array_merge($attribute_data,$rows);
       }
       if ($option_ids) {
          $attr_ids = array();
          foreach ($attribute_data as $attr_row)
             $attr_ids[$attr_row['id']] = true;
          $query = 'select a.* from attributes a where a.id in (select ' .
                   'o.parent from attribute_options o where o.id in (?)) ' .
                   'order by a.id';
          $query = $db->prepare_query($query,explode(',',$option_ids));
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'product','load_inventory',5,
                                         $db);
          $rows = $db->get_records($query);
          if ($rows) foreach ($rows as $attr_row) {
             $attr_id = $attr_row['id'];
             if (! isset($attr_ids[$attr_id])) {
                $attr_row['parent'] = 0;
                $attribute_data[] = $attr_row;
             }
          }
       }
    }

    $query = 'select * from product_inventory where parent in (?) ' .
             'order by parent,sequence';
    $query = $db->prepare_query($query,$id_array);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'product','load_inventory',4,$db);
    $inventory = $db->get_records($query);

    if ($inventory) {
       $inv_options = array();
       foreach ($inventory as $inv_row) {
          $attributes = $inv_row['attributes'];
          if (! $attributes) continue;
          if (strpos($attributes,'|') !== false)
             $attributes = explode('|',$attributes);
          else $attributes = explode('-',$attributes);
          foreach ($attributes as $option_id) {
             if (! is_numeric($option_id)) continue;
             $inv_options[$option_id] = true;
          }
       }
    }

    if ($features & REGULAR_PRICE_BREAKS)
       print "var price_break_fields = ['price_break_type','price_breaks'];\n";
    print "var attribute_fields = ['id','name','display_name','order_name'," .
          "'type','admin_type','url','description','default_value'," .
          "'select_function','data','sub_product','dynamic','required'," .
          "'taxable','width','height','max_length','wrap','flags'," .
          "'last_modified'";
    if (isset($attribute_fields)) {
       foreach ($attribute_fields as $field_name => $field)
          if ($field['datatype']) print ",'".$field_name."'";
    }
    print "];\n";
    print "var option_fields = ['id','sequence','name','order_name'," .
          "'adjust_type','adjustment','price_break_type','price_breaks'," .
          "'default_value','overlay_image','url','data','last_modified'";
    if (isset($option_fields)) {
       foreach ($option_fields as $field_name => $field)
          if ($field['datatype']) print ",'".$field_name."'";
    }
    print "];\n";
    print "var condition_fields = ['id','sequence','compare','value'," .
          "'action','target'];\n";

    if ($features & REGULAR_PRICE_BREAKS) {
       if ($price_breaks) foreach ($price_breaks as $row) {
          if ($wholesale && $row['price_breaks'] &&
              (! ($flags[$row['id']] & NO_ACCOUNT_DISCOUNTS))) {
             $product_id = $row['id'];   $product_discount = $discount;
             $break_array = explode('|',$row['price_breaks']);
             foreach ($break_array as $break_index => $break_info) {
                if (isset($product_discounts[$product_id]))
                   $discount_row = $product_discounts[$product_id];
                else $discount_row = null;
                $break_info = explode('-',$break_info);
                $break_info[2] = get_account_product_price($break_info[2],
                   $discount_row,$product_discount,true);
                $break_array[$break_index] = implode('-',$break_info);
             }
             $row['price_breaks'] = implode('|',$break_array);
          }
          print 'price_break_data['.$row['id'].']=['.$row['price_break_type'] .
                ",'".$row['price_breaks']."'];\n";
       }
    }

    if (count($attribute_data) > 0) {
       foreach ($id_array as $id_value) {
          print 'if (typeof(attribute_data['.$id_value.'])=="undefined") ' .
                'attribute_data['.$id_value."]=new Array();\n";
          print 'if (typeof(attribute_options['.$id_value.'])=="undefined") ' .
                'attribute_options['.$id_value."]=new Array();\n";
          print 'if (typeof(attribute_conditions['.$id_value.'])=="undefined") ' .
                'attribute_conditions['.$id_value."]=new Array();\n";
       }
       $other_ids = array();
       $attr_indices = array();
       foreach ($attribute_data as $index => $attr_row) {
          $parent = $attr_row['parent'];
          if (! isset($attr_indices[$parent])) $attr_indices[$parent] = 0;
          else $attr_indices[$parent]++;
          $attr_index = $attr_indices[$parent];
          if (! $parent) $parent = 0;
          if (($parent !== $id) && (! in_array($parent,$id_array)) &&
              (! in_array($parent,$other_ids))) {
             print 'attribute_data['.$parent."]=new Array();\n";
             print 'attribute_options['.$parent."]=new Array();\n";
             print 'attribute_conditions['.$parent."]=new Array();\n";
             $other_ids[] = $parent;
          }
          $query = 'select * from attribute_options where parent=? ' .
                   'order by sequence';
          $query = $db->prepare_query($query,$attr_row['id']);
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'product','load_inventory',3,
                                         $db);
          $options = $db->get_records($query);
          if ($hide_off_sale_options && $inventory && $options &&
              ($attr_row['sub_product'] == 1)) {
             foreach ($options as $option_index => $option_info) {
                if (! isset($inv_options[$option_info['id']]))
                   unset($options[$option_index]);
             }
             if (count($options) == 0) $options = null;
          }
          $query = 'select * from attribute_conditions where parent=? ' .
                   'order by sequence';
          $query = $db->prepare_query($query,$attr_row['id']);
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'product','load_inventory',4,
                                         $db);
          $conditions = $db->get_records($query);

          print 'attribute_data['.$parent.']['.$attr_index.']=[';
          $first_field = true;
          foreach ($attr_row as $field_name => $field_value) {
             if ($field_name == 'parent') continue;
             if ($first_field) $first_field = false;
             else print ',';
             print "'";
             write_js_data($field_value,false);
             print "'";
          }
          print "];\n";
          print 'attribute_options['.$parent.']['.$attr_index."]=new Array();\n";
          if ($options) foreach ($options as $option_row) {
             print 'attribute_options['.$parent.']['.$attr_index .
                   ']['.$option_row['id'].']=[';
             $first_field = true;
             foreach ($option_row as $field_name => $field_value) {
                if ($field_name == 'parent') continue;
                if ($first_field) $first_field = false;
                else print ',';
                print "'";
                if ($wholesale && ($field_name == 'price_breaks') &&
                    (! empty($field_value)) &&
                    (! ($flags[$parent] & NO_ACCOUNT_DISCOUNTS))) {
                   if (isset($product_discounts[$parent]))
                      $product_discount = floatval($product_discounts[$parent]);
                   else $product_discount = $discount;
                   if ($product_discount) {
                      $factor = (100 - $product_discount) / 100;
                      $break_array = explode('|',$field_value);
                      foreach ($break_array as $break_index => $break_info) {
                         $break_info = explode('-',$break_info);
                         $break_price = floatval($break_info[2]);
                         $break_price = round($break_price * $factor,2);
                         $break_info[2] = $break_price;
                         $break_array[$break_index] = implode('-',$break_info);
                      }
                      $field_value = implode('|',$break_array);
                   }
                }
                write_js_data($field_value,false);
                print "'";
             }
             print "];\n";
          }
          print 'attribute_conditions['.$parent.']['.$attr_index."]=new Array();\n";
          if ($conditions) foreach ($conditions as $cond_row) {
             print 'attribute_conditions['.$parent.']['.$attr_index .
                   ']['.$cond_row['id'].']=[';
             $first_field = true;
             foreach ($cond_row as $field_name => $field_value) {
                if ($field_name == 'parent') continue;
                if ($first_field) $first_field = false;
                else print ',';
                print "'";
                write_js_data($field_value,false);
                print "'";
             }
             print "];\n";
          }

       }
    }

    $catalog = new Catalog();
    if (isset($amount_cents_flag))
       $catalog->set_amount_cents($amount_cents_flag);
    print "var inventory_fields = ['id','sequence','parent','attributes'";
    if ($features & USE_PART_NUMBERS) print ",'part_number'";
    if ($features & MAINTAIN_INVENTORY) print ",'qty','min_qty'";
    if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH))
       print ",'min_order_qty'";
    if ($features & WEIGHT_ITEM) print ",'weight'";
    if ($features & LIST_PRICE_INVENTORY) print ",'list_price'";
    if ($features & REGULAR_PRICE_INVENTORY) {
       if ($wholesale) print ",'regular_price'";
       print ",'price'";
    }
    if ($features & SALE_PRICE_INVENTORY) print ",'sale_price'";
    if ($features & PRODUCT_COST_INVENTORY) print ",'cost'";
    if ($features & DROP_SHIPPING) print ",'origin_zip'";
    print ",'image'";
    if ((! empty($enable_inventory_available)) ||
        (! ($features & MAINTAIN_INVENTORY))) print ",'available'";
    if ($features & INVENTORY_BACKORDERS) print ",'backorder'";
    print ",'last_modified'";
    if (isset($inventory_fields)) {
       foreach ($inventory_fields as $field_name => $field)
          if ($field['datatype']) print ",'".$field_name."'";
    }
    print "];\n";
    if ($id) print 'inventory_data['.$id."]=new Array();\n";
    else {
       foreach ($id_array as $id_value)
          print 'inventory_data['.$id_value."]=new Array();\n";
    }
    if ($inventory) {
       foreach ($inventory as $inv_row) {
          if (! empty($hide_off_sale_inventory)) {
             if (! Product::inventory_available($inv_row,$features)) continue;
          }
          print 'inventory_data['.$inv_row['parent'].']['.$inv_row['id'].']=[';
          $first_field = true;
          foreach ($inv_row as $field_name => $field_value) {
             if (($field_name == 'part_number') &&
                 (! ($features & USE_PART_NUMBERS))) continue;
             if (($field_name == 'qty') &&
                 (! ($features & MAINTAIN_INVENTORY))) continue;
             if (($field_name == 'min_qty') &&
                 (! ($features & MAINTAIN_INVENTORY))) continue;
             if (($field_name == 'min_order_qty') &&
                 (! ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH))))
                continue;
             if (($field_name == 'weight') &&
                 (! ($features & WEIGHT_ITEM))) continue;
             if ($field_name == 'list_price') {
                if (! ($features & LIST_PRICE_INVENTORY)) continue;
                $field_value = $catalog->get_amount($field_value);
             }
             if ($field_name == 'price') {
                if (! ($features & REGULAR_PRICE_INVENTORY)) continue;
                if ($wholesale &&
                    (! ($flags[$inv_row['parent']] & NO_ACCOUNT_DISCOUNTS))) {
                   if ($first_field) $first_field = false;
                   else print ',';
                   print "'";
                   write_js_data($catalog->get_amount($field_value),false);
                   print "'";
                   $inv_id = $inv_row['id'];
                   $product_id = $inv_row['parent'];
                   $product_discount = $discount;
                   if (! empty($inv_discounts[$inv_id])) {
                      $inv_discount = floatval($inv_discounts[$inv_id]);
                      if ($account_product_prices === true)
                         $field_value = $inv_discount;
                      else {
                         $factor = (100 - $inv_discount) / 100;
                         $field_value = round($field_value * $factor,2);
                      }
                   }
                   else if (isset($product_discounts[$product_id]))
                      $field_value = get_account_product_price($field_value,
                                          $product_discounts[$product_id],
                                          $product_discount);
                }
                $field_value = $catalog->get_amount($field_value);
             }
             if ($field_name == 'sale_price') {
                if (! ($features & SALE_PRICE_INVENTORY)) continue;
                $field_value = $catalog->get_amount($field_value);
             }
             if ($field_name == 'cost') {
                if (! ($features & PRODUCT_COST_INVENTORY)) continue;
                $field_value = $catalog->get_amount($field_value);
             }
             if (($field_name == 'origin_zip') &&
                 (! ($features & DROP_SHIPPING))) continue;
             if (($field_name == 'available') &&
                 ($features & MAINTAIN_INVENTORY) &&
                 empty($enable_inventory_available)) continue;
             if (($field_name == 'backorder') &&
                 (! ($features & INVENTORY_BACKORDERS))) continue;
             if ($first_field) $first_field = false;
             else print ',';
             print "'";
             write_js_data($field_value,false);
             print "'";
          }
          print "];\n";
       }
    }
    $db->close();
}

function oos_notify()
{
    global $user_cookie;

    require_once 'customers-common.php';

    $db = new DB;
    $notices_record = notices_record_definition();
    $product_id = get_form_field('product_id');
    $attributes = get_form_field('attributes');
    $customer_id = get_cookie($user_cookie);
    if (! $customer_id) {
       $email = get_form_field('email');
       $fname = get_form_field('fname');
       $lname = get_form_field('lname');
       $query = 'select id from customers where email=?';
       $query = $db->prepare_query($query,$email);
       $row = $db->get_record($query);
       if ($row && $row['id']) $customer_id = $row['id'];
       else {
          $customer_id = 0;
          $notices_record['email']['value'] = $email;
          $notices_record['fname']['value'] = $fname;
          $notices_record['lname']['value'] = $lname;
       }
    }
    $notices_record['parent']['value'] = $customer_id;
    $notices_record['product_id']['value'] = $product_id;
    $notices_record['attributes']['value'] = $attributes;
    $notices_record['followup']['value'] = 0;
    $notices_record['create_date']['value'] = time();
    if (! $db->insert('customer_notices',$notices_record)) {
       http_response(422,$db->error);   return;
    }
    $notices_record['id']['value'] = $db->insert_id();
    $query = 'select name from products where id=?';
    $query = $db->prepare_query($query,$product_id);
    $row = $db->get_record($query);
    if ($row) $product = $row['name'].' (#'.$product_id.')';
    else $product = '#'.$product_id;
    http_response(200,'Customer Notice Added');
    $activity = 'Added Customer Notice for Product '.$product;
    log_activity($activity.' to Customer #'.$customer_id);
    write_customer_activity($activity,$customer_id,$db);

    if (defined('OOS_SIGNUP_EMAIL')) {
       require_once '../engine/email.php';
       $notice = $db->convert_record_to_array($notices_record);
       $email = new Email(OOS_SIGNUP_EMAIL,
                          array('customer' => $customer_id,
                                'product' => $product_id,
                                'attributes' => $attributes,
                                'notice' => $notice));
       if (! $email->send()) log_error($email->error);
       if (! empty($customer_id))
          write_customer_activity($email->activity,$customer_id,$db);
    }
}

$jscmd = get_form_field('jscmd');
if ($jscmd == 'loadinventory') load_inventory();
else if ($jscmd == 'oos_notify') oos_notify();
else if (function_exists('process_product_jscmd'))
   process_product_jscmd($jscmd);

?>
