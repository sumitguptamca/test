<?php
/*
                 Inroads Shopping Cart - Public Catalog Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once __DIR__.'/currency.php';
require_once __DIR__.'/utility.php';

class Catalog {

function __construct($db_param=null,$object_only=false)
{
    global $category_cookie,$default_currency,$base_product_url;
    global $display_product_page,$enable_wholesale,$user_cookie;
    global $products_table,$categories_table,$subcategories_table;
    global $category_products_table,$products_image_type,$categories_image_type;

    if ($db_param) $this->db = $db_param;
    else $this->db = new DB;
    if ((! $object_only) && (isset($_COOKIE[$category_cookie])))
       $this->path = $_COOKIE[$category_cookie];
    else $this->path = "";
    if (! isset($default_currency)) $default_currency = "USD";
    $this->currency = $default_currency;
    $this->cents_flag = true;
    setup_exchange_rate($this);
    if (isset($products_table)) $this->products_table = $products_table;
    else $this->products_table = "products";
    if (isset($categories_table)) $this->categories_table = $categories_table;
    else $this->categories_table = "categories";
    if (isset($subcategories_table))
       $this->subcategories_table = $subcategories_table;
    else $this->subcategories_table = "subcategories";
    if (isset($category_products_table))
       $this->category_products_table = $category_products_table;
    else $this->category_products_table = "category_products";
    if (isset($products_image_type))
       $this->products_image_type = $products_image_type;
    else $this->products_image_type = 1;
    if (isset($categories_image_type))
       $this->categories_image_type = $categories_image_type;
    else $this->categories_image_type = 0;
    if (! isset($base_product_url)) $this->base_product_url = "products/";
    else $this->base_product_url = $base_product_url;
    if (! isset($display_product_page))
       $this->display_product_page = "display-product.php";
    else $this->display_product_page = $display_product_page;
    $this->load_product_data = true;
    $this->wholesale = false;
    $this->account_id = 0;
    $this->discount = 0;
    if ((! $object_only) && (! empty($enable_wholesale))) {
       if (isset($_COOKIE[$user_cookie])) {
          $query = 'select account_id from customers where id=?';
          $query = $this->db->prepare_query($query,$_COOKIE[$user_cookie]);
          $row = $this->db->get_record($query);
          if ($row && $row['account_id']) {
             $this->wholesale = true;
             $this->account_id = $row['account_id'];
             $query = 'select status,discount_rate from accounts where id=?';
             $query = $this->db->prepare_query($query,$this->account_id);
             $row = $this->db->get_record($query);
             if ($row) {
                if ($row['status'] != 0) $this->wholesale = false;
                else if ($row['discount_rate'])
                   $this->discount = floatval($row['discount_rate']);
             }
          }
       }
    }
}

function Catalog($db_param=null,$object_only=false)
{
    self::__construct($db_param,$object_only);
}

function setup_conteg($mtime)
{
    ob_start();
    require_once __DIR__.'/../engine/conteg.php';
    $conteg_params = array(
       'noprint' => true,
       'modified' => $mtime,
       'use_expires' => false
    );
    $this->conteg = new Conteg($conteg_params);
}

function set_currency($currency)
{
    change_currency($this,$currency);
}

function set_amount_cents($cents_flag)
{
    $this->cents_flag = $cents_flag;
}

function set_products_table($products_table)
{
    $this->products_table = $products_table;
}

function set_products_image_type($image_type)
{
    $this->products_image_type = $image_type;
}

function set_base_product_url($base_product_url)
{
    $this->base_product_url = $base_product_url;
}

function set_load_product_data($load_product_data)
{
    $this->load_product_data = $load_product_data;
}

function get_amount($amount)
{
    if ($this->cents_flag) $precision = 2;
    else $precision = 0;
    return format_amount($amount,$this->currency,$this->exchange_rate,
                         $precision);
}

function write_amount($amount)
{
    print $this->get_amount($amount);
}

function get_first_image(&$filename,&$description)
{
    if (! isset($this->images)) {
       $filename = null;   $description = null;    return;
    }
    $description = reset($this->images);
    $filename = key($this->images);
}

function get_category_path()
{
    return explode(",",$this->path);
}

function load_categories($ids)
{
    if ($ids == '') return array();
    $query = 'select * from '.$this->categories_table.' where id in (?)';
    $query = $this->db->prepare_query($query,explode(',',$ids));
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'catalog','load_categories',1,
                                   $this->db);
    $category_records = $this->db->get_records($query,'id');
    if (! $category_records) {
       if (isset($this->db->error))
          process_error("Database Error: " .$this->db->error,-1);
       return array();
    }

    $id_array = explode(",",$ids);
    $category_info = array();
    foreach ($id_array as $id)
       if (isset($category_records[$id]))
          $category_info[$id] = $category_records[$id];

    return $category_info;
}

function get_category_path_info()
{
    return $this->load_categories($this->path);
}

function get_current_category()
{
    if (empty($this->path)) return null;
    $path_array = explode(",",$this->path);
    return $path_array[count($path_array) - 1];
}

function get_current_category_info()
{
    $id = $this->get_current_category();
    if (! $id) return null;
    if (! is_numeric($id)) {
       process_error("Invalid Category ID ".$id,-1);   return null;
    }
    $query = 'select * from '.$this->categories_table.' where id=?';
    $query = $this->db->prepare_query($query,$id);
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'catalog',
                                   'get_current_category_info',1,$this->db);
    $category_info = $this->db->get_record($query);
    if (! $category_info) {
       if (isset($this->db->error))
          process_error("Database Error: ".$this->db->error,-1);
       return null;
    }
    return $category_info;
}

function get_subcategory_url($category_info,$attributes=null,$use_base=false,
                             $use_trailing_slash=true,$include_anchor=true)
{
    global $base_url;

    if (! $category_info) return '';
    if ($include_anchor) $return_url = '<a href="';
    else $return_url = '';
    if (isset($category_info['external_url']) &&
        $category_info['external_url']) {
       $external_url = $category_info['external_url'];
       $return_url .= $external_url;
    }
    else {
       $external_url = null;
       if (isset($category_info['seo_url']))
          $seo_url = $category_info['seo_url'];
       else $seo_url = '';
       if (! $seo_url) {
          if (! isset($category_info['id'])) return '';
          $seo_url = $category_info['id'];
       }
       if ($use_base) {
          $return_url .= $base_url;
          if (function_exists('get_custom_url_prefix'))
             $return_url .= get_custom_url_prefix();
       }
       $return_url .= $seo_url;
       if ($use_trailing_slash) $return_url .= '/';
    }
    if ($include_anchor) {
       $return_url .= '"';   $title = null;
       if ($external_url) $return_url .= ' target="_blank" rel="nofollow"';
       if (isset($category_info['seo_title']) && $category_info['seo_title'])
          $title = $category_info['seo_title'];
       else if (isset($category_info['display_name']) &&
                $category_info['display_name'])
          $title = $category_info['display_name'];
       else if (isset($category_info['name']) && $category_info['name'])
          $title = $category_info['name'];
       if ($title) {
          $encoding = ini_get('default_charset');
          if (! $encoding) $encoding = 'ISO-8859-1';
          $return_url .= ' title="'.htmlentities($title,
             ENT_COMPAT,$encoding).'"';
       }
       if ($attributes != null) $return_url .= ' '.$attributes;
       $return_url .= '>';
    }
    return $return_url;
}

function write_subcategory_url($category_info,$attributes=null,$use_base=false,
                               $use_trailing_slash=true)
{
    print Catalog::get_subcategory_url($category_info,$attributes,$use_base,
                                       $use_trailing_slash);
}

function get_product_url_string($category_info,$product_info,$attributes=null,
                                $use_trailing_slash=true,$include_anchor=true,
                                $db=null)
{
    global $display_product_page,$base_product_url,$db;
    global $categories_table,$category_products_table,$cache_catalog_pages;

    if (! isset($product_info['id'])) return '';
    if (isset($this) && isset($this->display_product_page))
       $display_product_page = $this->display_product_page;
    else if (! isset($display_product_page))
       $display_product_page = 'display-product.php';
    if (isset($this) && isset($this->base_product_url))
       $base_product_url = $this->base_product_url;
    else if (! isset($base_product_url)) $base_product_url = 'products/';
    if (isset($this) && isset($this->categories_table)) {
       $categories_table = $this->categories_table;
       $category_products_table = $this->category_products_table;
    }
    else {
      if (! isset($categories_table)) $categories_table = 'categories';
      if (! isset($category_products_table))
         $category_products_table = 'category_products';
    }

    if ($include_anchor) $return_url = '<a href="';
    else $return_url = '';
    if ((! empty($cache_catalog_pages)) && $category_info &&
        (! ($product_info['flags'] & 9))) {
       if (isset($this) && $this->db) $db = $this->db;
       else if (! isset($db)) $db = new DB;
       $query = 'select id from '.$category_products_table .
                ' where parent=? and related_id=?';
       $query = $db->prepare_query($query,$category_info['id'],
                                   $product_info['id']);
       $row = $db->get_record($query);
       if (empty($row['id'])) $category_info = null;
    }
    if ($product_info['flags'] & 9) {
       $seo_url = str_replace('&','%2526',$product_info['seo_url']);
       if (! empty($seo_url)) $return_url .= $seo_url;
       else $return_url .= $base_product_url.$product_info['id'];
       if ($use_trailing_slash) $return_url .= '/';
    }
    else if ($category_info) {
       $category_seo_url = $category_info['seo_url'];
       if (empty($category_seo_url)) $category_seo_url = $category_info['id'];
       $product_seo_url = str_replace('&','%2526',$product_info['seo_url']);
       if (empty($product_seo_url)) $product_seo_url = $product_info['id'];
       $return_url .= $category_seo_url.'/'.$product_seo_url;
       if ($use_trailing_slash) $return_url .= '/';
    }
    else {
       if (isset($this) && $this->db) $db = $this->db;
       else if (! isset($db)) $db = new DB;
       $product_seo_url = str_replace('&','%2526',$product_info['seo_url']);
       if (empty($product_seo_url)) $product_seo_url = $product_info['id'];
       if (isset($product_info['seo_category']))
          $seo_category = $product_info['seo_category'];
       else $seo_category = 0;
       if (empty($seo_category) || ($seo_category == -1)) $seo_category = 0;
       if ($seo_category == 0) {
          $query = 'select p.parent from '.$category_products_table.' p join ' .
                   $categories_table.' c on c.id=p.parent where p.related_id=? ' .
                   'and (isnull(c.flags) or (not c.flags&8)) ' .
                   'order by p.id limit 1';
          $query = $db->prepare_query($query,$product_info['id']);
          $row = $db->get_record($query);
          if ($row) $seo_category = $row['parent'];
       }
       if ($seo_category == 0)
          $return_url .= $base_product_url.$product_seo_url;
       else {
          $query = 'select seo_url from '.$categories_table.' where id=?';
          $query = $db->prepare_query($query,$seo_category);
          $row = $db->get_record($query);
          if (! $row) $return_url .= $base_product_url.$product_seo_url;
          else {
             $category_seo_url = $row['seo_url'];
             if (empty($category_seo_url)) $category_seo_url = $seo_category;
             $return_url .= $category_seo_url.'/'.$product_seo_url;
          }
       }
       if ($use_trailing_slash) $return_url .= '/';
    }
    if ($include_anchor) {
       $return_url .= '"';   $title = null;
       if (! empty($product_info['seo_title']))
          $title = $product_info['seo_title'];
       else if (! empty($product_info['display_name']))
          $title = $product_info['display_name'];
       else if (! empty($product_info['name'])) $title = $product_info['name'];

       if ($title) {
          $encoding = ini_get('default_charset');
          if (! $encoding) $encoding = 'ISO-8859-1';
          $return_url .= ' title="'.htmlentities($title,
             ENT_COMPAT,$encoding).'"';
       }
       if ($attributes != null) $return_url .= ' '.$attributes;
       $return_url .= '>';
    }
    return $return_url;
}

function write_product_url($category_info,$product_info,$attributes=null,
                           $use_trailing_slash=true)
{
    print Catalog::get_product_url_string($category_info,$product_info,
             $attributes,$use_trailing_slash);
}

function get_product_url($id)
{
    global $products_table;

    if (isset($this)) {
       $db = $this->db;
       $products_table = $this->products_table;
    }
    else {
       $db = new DB;
       if (! isset($products_table)) $products_table = "products";
    }
    $query = 'select id,name,display_name,flags,seo_title,seo_url,' .
             'seo_category from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'catalog','get_product_url',1,$db);
    $product_info = $db->get_record($query);
    if (! $product_info) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,-1);
       else process_error("Unable to load product information",-1);
       return null;
    }
    return $product_info;
}

function get_product_seo_category($product_id)
{
    $query = 'select seo_category from '.$this->products_table .
             ' where id=?';
    $query = $this->db->prepare_query($query,$product_id);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'catalog',
                                   'get_product_seo_category',1,$this->db);
    $row = $this->db->get_record($query);
    if (! $row) return null;
    $seo_category = $row['seo_category'];
    if (empty($seo_category) || ($seo_category == -1)) $seo_category = 0;
    if (($seo_category == 0) && $product_id) {
       $query = 'select p.parent from category_products p join ' .
                'categories c on c.id=p.parent where p.related_id=? ' .
                'and (isnull(c.flags) or (not c.flags&8)) ' .
                'order by p.id limit 1';
       $query = $this->db->prepare_query($query,$product_id);
       $row = $this->db->get_record($query);
       if ($row) $seo_category = $row['parent'];
    }
    return $seo_category;
}

function get_product_seo_url($product_id,$seo_category)
{
    $query = 'select flags,seo_url from '.$this->products_table .
             ' where id=?';
    $query = $this->db->prepare_query($query,$product_id);
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'catalog','get_product_seo_url',1,
                                   $this->db);
    $row = $this->db->get_record($query);
    if (! $row) return null;
    $new_url = null;
    if (($row['flags'] & (FEATURED|UNIQUEURL)) && $row['seo_url'])
       $new_url = '/'.$row['seo_url'].'/';
    else if ($row['seo_url'] && ($seo_category != 0)) {
       $query = 'select seo_url from categories where id=?';
       $query = $this->db->prepare_query($query,$seo_category);
       $cat_row = $this->db->get_record($query);
       if ($cat_row && $cat_row['seo_url'])
          $new_url = '/'.$cat_row['seo_url'].'/' .
                     $row['seo_url'].'/';
    }
    return $new_url;
}

function lookup_product_id($product)
{
    global $enable_multisite,$base_url;

    if (! isset($enable_multisite)) $enable_multisite = false;
    if ($enable_multisite && ($this instanceof Category)) {
       $query = 'select websites from '.$this->categories_table.' where id=?';
       $query = $this->db->prepare_query($query,$this->id);
       $row = $this->db->get_record($query);
       $websites = $row['websites'];
       $website_array = explode(',',$websites);
       $multisite_query = " and (";
       foreach ($website_array as $index => $website_id) {
          if ($index > 0) $multisite_query .= " or ";
          $multisite_query .= "find_in_set('".$website_id."',websites)";
       }
       $multisite_query .= ")";
    }
    else {
       $websites = null;   $multisite_query = null;
    }
    $query = 'select id from '.$this->products_table.' where seo_url=?';
    $query = $this->db->prepare_query($query,$product);
    if ($multisite_query) $query .= $multisite_query;
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'catalog','lookup_product_id',1,
                                   $this->db);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error)) return null;
       require_once __DIR__.'/seo.php';
       $new_id = lookup_redirect(1,$product,$websites,$this->db);
       if ($new_id) {
          $seo_category = $this->get_product_seo_category($new_id);
          $new_url = $this->get_product_seo_url($new_id,$seo_category);
          if ($new_url) {
             header('HTTP/1.1 301 Moved Permanently');
             if (substr($base_url,-1,1) == '/')
                $url = substr($base_url,0,-1);
             else $url = $base_url;
             $url .= $new_url;
             header('Location: '.$url);
             exit;
          }
          return null;
       }
    }
    $product_id = $row['id'];
    return intval($product_id);
}

function parse_inventory_attributes($attributes,$inventory_attributes)
{
    if (isset($attributes)) {
       $no_options = true;
       foreach ($attributes as $attr_id => $attr_info) {
          if (isset($this->products['attribute_options']) &&
              (count($this->products['attribute_options'][$attr_id]) != 0))
             $no_options = false;
       }
    }
    else $no_options = false;
    if ($no_options) $attr_array = explode('|',$inventory_attributes);
    else $attr_array = explode('-',$inventory_attributes);

    return $attr_array;
}

function get_inventory_prices($product_id)
{
    $price_info = array('lowest_price' => null,'lowest_sale_price' => null,
                        'lowest_list_price' => null,'default_price' => null,
                        'default_sale_price' => null,'default_list_price' => null,
                        'highest_price' => null,'highest_sale_price' => null,
                        'highest_list_price' => null,'default_inventory' => null);
    if (! $product_id) {
       log_error("No Product ID specified in get_inventory_prices");
       return $price_info;
    }
    if (isset($this->products) && isset($this->products[$product_id])) {
       if (isset($this->products[$product_id]['inventory']))
          $inventory = $this->products[$product_id]['inventory'];
       if (isset($this->products[$product_id]['attributes']))
          $attributes = $this->products[$product_id]['attributes'];
       if (isset($this->attribute_options))
          $attribute_options = $this->attribute_options;
    }
    else if ($this->id == $product_id) {
       if (isset($this->inventory)) $inventory = $this->inventory;
       if (isset($this->attributes)) $attributes = $this->attributes;
       if (isset($this->attribute_options))
          $attribute_options = $this->attribute_options;
    }
    else if (isset($this->related)) {
       foreach ($this->related as $related_products) {
          if (isset($related_products[$product_id])) {
             if (isset($related_products[$product_id]->inventory))
                $inventory = $related_products[$product_id]->inventory;
             if (isset($related_products[$product_id]->attributes))
                $attributes = $related_products[$product_id]->attributes;
             if (isset($this->attribute_options))
                $attribute_options = $this->attribute_options;
             break;
          }
       }
    }

    if (! isset($inventory)) {
       $query = 'select * from product_inventory where parent=? ' .
                'order by sequence';
       $query = $this->db->prepare_query($query,$product_id);
       if (function_exists("custom_update_catalog_query"))
          custom_update_catalog_query($query,'catalog',
                                      'get_inventory_prices',1,$this->db);
       $inventory = $this->db->get_records($query,'id');
       if (! $inventory) return $price_info;
    }

    if (! isset($attributes)) {
       $attr_ids = '';
       $query = 'select a.* from product_attributes p left join ' .
                'attributes a on a.id=p.related_id where p.parent=? ' .
                'order by sequence';
       $query = $this->db->prepare_query($query,$product_id);
       if (function_exists("custom_update_catalog_query"))
          custom_update_catalog_query($query,'catalog','get_inventory_prices',
                                      2,$this->db);
       $rows = $this->db->get_records($query);
       if ($rows) {
          $attributes = array();   $first_field = true;
          foreach ($rows as $row) {
             $attr_id = $row['id'];
             if (! $attr_id) continue;
             $attributes[$attr_id] = $row;
             if ($first_field) $first_field = false;
             else $attr_ids .= ',';
             $attr_ids .= $attr_id;
          }
       }
       else if (isset($this->db->error)) {
          log_error($query);   log_error($this->db->error);
          return $price_info;
       }
    }
    else if (! isset($attribute_options)) {
       $first_field = true;   $attr_ids = '';
       foreach ($attributes as $attr_id => $attr_info) {
          if ($first_field) $first_field = false;
          else $attr_ids .= ',';
          $attr_ids .= $attr_id;
       }
    }

    if ((! isset($attribute_options)) && ($attr_ids != '')) {
       $query = 'select o.*,(select filename from images where parent_type=2 ' .
                'and parent=o.id limit 1) as image from attribute_options o ' .
                'where o.parent in (?) order by o.parent,o.sequence';
       $query = $this->db->prepare_query($query,explode(',',$attr_ids));
       if (function_exists("custom_update_catalog_query"))
          custom_update_catalog_query($query,'catalog','get_inventory_prices',
                                      3,$this->db);
       $rows = $this->db->get_records($query);
       if ($rows) {
          $attribute_options = array();
          foreach ($rows as $row) {
             if (! isset($attribute_options[$row['parent']]))
                $attribute_options[$row['parent']] = array();
             $attribute_options[$row['parent']][$row['id']] = $row;
          }
       }
       else if (isset($this->db->error)) {
          log_error($query);   log_error($this->db->error);
          return $price_info;
       }
    }

    $attr_count = 0;   $default_attributes = '';
    if (isset($attributes)) {
       $no_options = false;
       foreach ($attributes as $attr_id => $attr_info) {
          if (! isset($attribute_options)) $no_options = true;
          else if (! isset($attribute_options[$attr_id])) $no_options = true;
          else if (count($attribute_options[$attr_id]) == 0)
             $no_options = true;
       }
       foreach ($attributes as $attr_id => $attr_info) {
          if ($attr_info['sub_product'] == 1) {
             if ($default_attributes != '') {
                if ($no_options) $default_attributes .= '|';
                else $default_attributes .= '-';
             }
             if (isset($attribute_options) && isset($attribute_options[$attr_id])) {
                foreach ($attribute_options[$attr_id] as $option_id => $option) {
                   if ($option['default_value']) {
                      $default_attributes .= $option_id;   break;
                   }
                }
             }
             $attr_count++;
          }
          else if ($attr_info['dynamic'] == 1) $attr_count++;
       }
    }
    else $no_options = false;

    foreach ($inventory as $inventory_id => $inventory_data) {
       if ($inventory_data['attributes'] == '') $num_attrs = 0;
       else {
          if ($no_options) $attr_array = explode('|',$inventory_data['attributes']);
          else $attr_array = explode('-',$inventory_data['attributes']);
          $num_attrs = count($attr_array);
       }
       if ($num_attrs != $attr_count) continue;
       if ($inventory_data['attributes'] == $default_attributes) {
          if ($price_info['default_inventory'] === null)
             $price_info['default_inventory'] = $inventory_data;
          if (($this->features & SALE_PRICE_INVENTORY) &&
              isset($inventory_data['sale_price']) &&
              ($inventory_data['sale_price'] != 0))
             $price_info['default_sale_price'] = $inventory_data['sale_price'];
          if (($this->features & REGULAR_PRICE_INVENTORY) &&
              isset($inventory_data['price']) &&
              ($inventory_data['price'] != 0))
             $price_info['default_price'] = $inventory_data['price'];
          if (($this->features & LIST_PRICE_INVENTORY) &&
              isset($inventory_data['list_price']) &&
              ($inventory_data['list_price'] != 0))
             $price_info['default_list_price'] = $inventory_data['list_price'];
       }
       if (($this->features & SALE_PRICE_INVENTORY) &&
           isset($inventory_data['sale_price']) &&
           ($inventory_data['sale_price'] != 0)) {
          if (($price_info['lowest_sale_price'] === null) ||
              ($inventory_data['sale_price'] < $price_info['lowest_sale_price']))
             $price_info['lowest_sale_price'] = $inventory_data['sale_price'];
          if (($price_info['highest_sale_price'] === null) ||
              ($inventory_data['sale_price'] > $price_info['highest_sale_price']))
             $price_info['highest_sale_price'] = $inventory_data['sale_price'];
       }
       if (($this->features & REGULAR_PRICE_INVENTORY) &&
           isset($inventory_data['price']) &&
           ($inventory_data['price'] != 0)) {
          if (($price_info['lowest_price'] === null) ||
              ($inventory_data['price'] < $price_info['lowest_price'])) {
             $price_info['lowest_price'] = $inventory_data['price'];
             if ($price_info['default_inventory'] === null)
                $price_info['default_inventory'] = $inventory_data;
          }
          if (($price_info['highest_price'] === null) ||
              ($inventory_data['price'] > $price_info['highest_price']))
             $price_info['highest_price'] = $inventory_data['price'];
       }
       if (($this->features & LIST_PRICE_INVENTORY) &&
           isset($inventory_data['list_price']) &&
           ($inventory_data['list_price'] != 0)) {
          if (($price_info['lowest_list_price'] === null) ||
              ($inventory_data['list_price'] < $price_info['lowest_list_price']))
             $price_info['lowest_list_price'] = $inventory_data['list_price'];
          if (($price_info['highest_list_price'] === null) ||
              ($inventory_data['list_price'] > $price_info['highest_list_price']))
             $price_info['highest_list_price'] = $inventory_data['list_price'];
       }
    }

    if ($price_info['default_price'] === null)
       $price_info['default_price'] = $price_info['lowest_price'];
    if ($price_info['default_sale_price'] === null)
       $price_info['default_sale_price'] = $price_info['lowest_sale_price'];
    if ($price_info['default_list_price'] === null)
       $price_info['default_list_price'] = $price_info['lowest_list_price'];

    return $price_info;
}

function show_conteg()
{
    $this->conteg->show();
}

function process_widget($widget_block)
{
    $widget_block = substr($widget_block,11,-13);
    $curr_pos = 0;  $form_fields = array();
    while (UI::parse_next_parameter($widget_block,$curr_pos,$param_name,
                                    $param_value)) {
       if ($param_name == 'plugin') $plugin_name = $param_value;
       else $form_fields[$param_name] = $param_value;
    }
    return get_widget_output($plugin_name,$form_fields);
}

function process_widgets(&$info)
{
    foreach ($info as $key => $value) {
       $replaced = false;
       $start_pos = strpos($value,'<wsdwidget ');
       while ($start_pos !== false) {
          $end_pos = strpos($value,'</wsdwidget>',$start_pos);
          if ($end_pos === false) break;
          $widget_block = substr($value,$start_pos,$end_pos - $start_pos + 12);
          $widget_code = $this->process_widget($widget_block);
          $value = substr($value,0,$start_pos).$widget_code .
                   substr($value,$end_pos + 12);
          $replaced = true;
          $start_pos = strpos($value,'<wsdwidget ',$start_pos);
       }
       if ($replaced) $info[$key] = $value;
    }
}

function view_record_definition()
{
    $view_record = array();
    $view_record['id'] = array('type' => INT_TYPE);
    $view_record['id']['key'] = true;
    $view_record['parent'] = array('type' => INT_TYPE);
    $view_record['customer_id'] = array('type' => INT_TYPE);
    $view_record['ip_address'] = array('type' => CHAR_TYPE);
    $view_record['view_date'] = array('type' => INT_TYPE);
    return $view_record;
}

function save_view()
{
    global $shopping_cart,$user_cookie;

    if (! $shopping_cart) return;
    if (empty($this->id)) return;
    $customer_id = get_cookie($user_cookie);
    if (! empty($_SERVER['REMOTE_ADDR']))
       $ip_address = $_SERVER['REMOTE_ADDR'];
    else $ip_address = null;
    if (empty($customer_id) && empty($ip_address)) return;

    $view_record = $this->view_record_definition();
    $view_record['parent']['value'] = $this->id;
    if (! empty($customer_id))
       $view_record['customer_id']['value'] = $customer_id;
    if (! empty($ip_address))
       $view_record['ip_address']['value'] = $ip_address;
    $view_record['view_date']['value'] = time();
    if ($this instanceof Category) $view_table = 'category_views';
    else $view_table = 'product_views';
    $this->db->enable_log_query(false);
    $this->db->insert($view_table,$view_record);
    $this->db->enable_log_query(true);
    require_once __DIR__.'/../engine/modules.php';
    if (module_attached('save_view')) {
       if ($this instanceof Category) $type = 'Category';
       else $type = 'Product';
       if (empty($this->info)) $info = null;
       else $info = $this->info;
       call_module_event('save_view',array($type,$this->id,$info,$this),
                         null,true);
    }
}

};

function verify_coupon_discount($db,$coupon,$product_id)
{
    $today = time();
    if ($coupon['start_date'] &&
        ($today < strtotime('0:00',$coupon['start_date']))) return false;
    if ($coupon['end_date'] &&
        ($today > (strtotime('0:00',$coupon['end_date']) + 86400)))
       return false;
    $flags = $coupon['flags'];
    if ($flags & 1) {
       $query = 'select id from coupon_products where (parent=?) and ' .
                '(related_id=?)';
       $query = $db->prepare_query($query,$coupon['id'],$product_id);
       if (! $db->get_record($query)) return false;
    }
    return true;
}

function init_qty_select($onshow_event=null,$onselect_event=null,
                         $onkeyup_event=null)
{
    global $cart_qty_select;

    $html = '';
    if ($cart_qty_select == 'bootstrap-combobox') {
       $html .= '<script type="text/javascript">'."\n";
       $html .= '  $(document).ready(function() { $(\'.combobox\').' .
                'combobox({appendId:\'_combo\'';
       if ($onshow_event) $html .= ',onShow:'.$onshow_event;
       if ($onselect_event) $html .= ',onSelect:'.$onselect_event;
       if ($onkeyup_event) $html .= ',onKeyup:'.$onkeyup_event;
       $html .= '}); });';
       $html .= "\n</script>\n";
    }
    return $html;
}

function build_qty_select($field_name,$qty,$price,$product_id,$class=null,
   $onchange_event=null,$add_init=true)
{
    global $cart_qty_select,$cart,$product;

    if (isset($cart) && $cart) {
       $coupon_code = $cart->get('coupon_code');
       if ($coupon_code && ($coupon_code[0] == '~')) $coupon_code = null;
       $db = $cart->db;
    }
    else if (isset($product)) {
       $coupon_code = null;   $db = $product->db;
    }
    else {
       $coupon_code = null;   $db = new DB;
    }
    $query = 'select * from coupons where (coupon_type=8) and ';
    if ($coupon_code) {
       $query .= '(coupon_code=?)';
       $query = $db->prepare_query($query,$coupon_code);
    }
    else $query .= '(flags&32)';
    $coupons = $db->get_records($query);
    if ($coupons) {
       $query = 'select * from coupon_discounts order by parent,start_qty';
       $discounts = $db->get_records($query);
       foreach ($coupons as $index => $row) {
          if (! verify_coupon_discount($db,$row,$product_id)) {
             unset($coupons[$index]);   continue;
          }
          $coupon_discounts = array();
          if ($discounts) foreach ($discounts as $discount) {
             if ($discount['parent'] == $row['id'])
                $coupon_discounts[] = $discount;
          }
          $coupons[$index]['discounts'] = $coupon_discounts;
       }
    }
    if (count($coupons) == 0) $coupons = null;

    if (! isset($cart_qty_select)) $cart_qty_select = null;
    $html = '<select name="'.$field_name.'" id="'.$field_name.'"';
    if ($class || ($cart_qty_select == 'bootstrap-combobox')) {
       $html .= ' class="';
       if ($cart_qty_select == 'bootstrap-combobox') {
          $html .= 'combobox form-control';
          if ($class) $html .= ' ';
       }
       if ($class) $html .= $class;
       $html .= '"';
    }
    if ($onchange_event) $html .= ' onChange="'.$onchange_event.'"';
    $html .= ">\n";
    $html .= '  <option value="0"';
    if ($qty === '') $html .= ' selected';
    if ($coupons) $label = '0|None';
    else $label = '0';
    $html .= '>'.$label."</option>\n";
    $qty_found = false;   $qty_values = array();
    if ($qty === 0) {
       $qty_values[] = 0;   $qty_found = true;
    }
    for ($loop = 1;  $loop < 11;  $loop++) {
       $qty_values[] = $loop;
       if ($loop == $qty) $qty_found = true;
    }
    if ($qty && (! $qty_found) && is_numeric($qty)) $qty_values[] = $qty;
    foreach ($qty_values as $loop) {
       $html .= '  <option value="'.$loop.'"';
       if ($loop == $qty) $html .= ' selected';
       $label = $loop;
       if ($coupons) {
          $label .= '|';
          $highest_discount = 0;
          foreach ($coupons as $coupon) {
             foreach ($coupon['discounts'] as $discount) {
                if ($loop < $discount['start_qty']) continue;
                if ($discount['end_qty'] && ($loop > $discount['end_qty']))
                   continue;
                if ($discount['discount'] > $highest_discount)
                   $highest_discount = $discount['discount'];
                break;
             }
          }
          if ($highest_discount) {
             if ($highest_discount > 100) $highest_discount = 100;
             $factor = (100 - $highest_discount) / 100;
             $qty_price = round($price * $factor,2);
          }
          else $qty_price = $price;
          if (isset($cart))
             $label .= $cart->write_amount($qty_price,false,false);
          else if (isset($product)) $label .= $product->get_amount($qty_price);
          else $label .= format_amount($qty_price);
       }
       $html .= '>'.$label.'</option>'."\n";
    }
    $html .= "</select>\n";
    if ((! isset($cart)) && $add_init) $html .= init_qty_select();
    return $html;
}

if (! function_exists("inroads_debug")) {
   function inroads_debug()
   {
       global $login_cookie;

       if (! isset($_COOKIE[$login_cookie])) return false;
       if ($_COOKIE[$login_cookie] == 'severy') return true;
       if ($_COOKIE[$login_cookie] == 'patel') return true;
       return false;
   }
}

?>
