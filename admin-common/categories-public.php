<?php
/*
                 Inroads Shopping Cart - Public Category Functions

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

class Category extends Catalog {

function __construct($new_id=null,$db_param=null,$object_only=false)
{
    global $category_cookie,$top_category,$shopping_cart,$catalog_features;
    global $off_sale_option,$include_top_category_in_path;
    global $display_category_page,$rewrite_catalog_id_urls,$base_url;
    global $enable_category_filter_search,$cache_catalog_pages;
    global $cache_catalog_page_size;

    parent::__construct($db_param);
    if ($new_id) $this->id = $new_id;
    else {
       $id = get_form_field('id');
       if (is_numeric($id)) $this->id = intval($id);
       else $this->id = null;
    }
    $this->shopping_cart = $shopping_cart;
    if ($shopping_cart)
       $this->features = get_cart_config_value('features',$this->db);
    else $this->features = $catalog_features;
    if (! isset($off_sale_option)) $this->off_sale_option = 1;
    else $this->off_sale_option = $off_sale_option;
    $this->ajax_call = false;
    if (! empty($cache_catalog_pages)) {
       if (! empty($cache_catalog_page_size))
          $this->page_size = $cache_catalog_page_size;
       else $this->page_size = 12;
    }
    else $this->page_size = null;
    $this->page_number = null;
    $this->last_modified = 0;
    $this->index_by_id = true;
    $this->filter_search = false;
    $this->setup_queries();
    if (isset($enable_category_filter_search)) {
       $this->filter_search = $enable_category_filter_search;
       $this->num_filters = 0;
    }
    if (! $this->id) return;

    if ($object_only) return;

    if (! isset($include_top_category_in_path))
       $include_top_category_in_path = false;
    if (! isset($display_category_page))
       $display_category_page = 'display-category.php';
    $product = get_form_field('product');
    if ($product) {
       $using_paging = false;
       if (is_numeric($product)) {
          $product_id = intval($product);
          $query = 'select seo_url from '.$this->products_table.' where id=?';
          $query = $this->db->prepare_query($query,$product_id);
          $row = $this->db->get_record($query);
          if (! $row) $product_id = $this->lookup_product_id($product);
       }
       else if (substr($product,0,2) == 'p-') {
          $page_number = intval(substr($product,2));
          if ($page_number <= 0) $this->page_number = null;
          else $this->page_number = $page_number - 1;
          $product_id = 0;   $using_paging = true;
       }
       else $product_id = $this->lookup_product_id($product);
       if ($product_id) $this->product_id = $product_id;
       else if (! $using_paging) {
          $new_url = null;
          $query = 'select seo_url from '.$this->categories_table .
                   ' where id=?';
          $query = $this->db->prepare_query($query,$this->id);
          $row = $this->db->get_record($query);
          if ($row && $row['seo_url']) {
             if (function_exists('get_custom_url_prefix'))
                $prefix = get_custom_url_prefix(true,false);
             else $prefix = '';
             $new_url = $prefix.'/'.$row['seo_url'].'/';
          }
          if ($new_url) {
             header('HTTP/1.1 301 Moved Permanently');
             if (substr($base_url,-1,1) == '/') $url = substr($base_url,0,-1);
             else $url = $base_url;
             $url .= $new_url;
             header('Location: '.$url);
             exit;
          }
       }
    }
    else if (isset($rewrite_catalog_id_urls) && $rewrite_catalog_id_urls &&
             (strstr($_SERVER['REQUEST_URI'],$display_category_page) !== false) &&
             (strstr($_SERVER['REQUEST_URI'],'norewrite') === false) &&
             (get_form_field('template') === null)) {
       $new_url = null;
       $query = 'select seo_url from '.$this->categories_table.' where id=?';
       $query = $this->db->prepare_query($query,$this->id);
       $row = $this->db->get_record($query);
       if ($row && $row['seo_url']) $new_url = '/'.$row['seo_url'].'/';
       if ($new_url) {
          header('HTTP/1.1 301 Moved Permanently');
          if (substr($base_url,-1,1) == '/') $url = substr($base_url,0,-1);
          else $url = $base_url;
          if (function_exists('get_custom_url_prefix'))
             $url .= get_custom_url_prefix();
          $url .= $new_url;
          header('Location: '.$url);
          exit;
       }
    }

    $is_top = false;
    if (isset($top_category) && (! $include_top_category_in_path)) {
       $top_categories = $this->load_subcategory_ids($top_category);
       if ($top_categories && in_array($this->id,$top_categories))
          $is_top = true;
    }
    if ($is_top) $this->path = $this->id;
    else {
       $path_array = explode(',',$this->path);
       $path_offset = array_search($this->id,$path_array);
       if ($path_offset !== false) {
          array_splice($path_array,$path_offset + 1);
          $this->path = implode(',',$path_array);
       }
       else {
          $parent_ids = $this->load_parent_ids();
          $parent_id = -1;
          if ($parent_ids) {
             foreach ($parent_ids as $id) {
                if (in_array($id,$path_array)) {
                   $parent_id = $id;   break;
                }
             }
          }
          if ($parent_id != -1) {
             $path_offset = array_search($parent_id,$path_array);
             array_splice($path_array,$path_offset + 1);
             $this->path = implode(',',$path_array);
             if ($this->path != '') $this->path .= ','.$this->id;
          }
          else {
             $this->path = $this->id;
             while ($parent_ids && $parent_ids[0]) {
                $parent_id = $parent_ids[0];
                if (isset($top_category) && ($parent_id == $top_category) &&
                    (! $include_top_category_in_path)) break;
                $this->path = $parent_id.','.$this->path;
                $parent_ids = $this->load_parent_ids($parent_id);
             }
          }
       }
    }
    if (! isset($this->product_id))
       setcookie($category_cookie,$this->path,time() + (86400 * 100),'/');
}

function Category($new_id=null,$db_param=null,$object_only=false)
{
    self::__construct($new_id,$db_param,$object_only);
}

function setup_queries()
{
    global $product_fields,$category_off_sale_option,$enable_multisite;
    global $website_id,$shopping_feeds_enabled,$enable_product_flags;
    global $product_group_field,$shopping_cart,$enable_wholesale;

    if (function_exists('custom_setup_queries') && custom_setup_queries($this))
       return;
    if ($shopping_cart && empty($shopping_feeds_enabled)) {
       require_once 'shopping-common.php';
       $shopping_feeds_enabled = shopping_modules_installed();
    }
    $query_fields = 'p.id,p.status,p.product_type,p.vendor,p.name,p.display_name,' .
       'p.menu_name,p.flags,p.last_modified,p.short_description,p.long_description,' .
       'p.video,p.audio,p.seo_title,p.seo_description,p.seo_keywords,p.seo_header,' .
       'p.seo_footer,p.seo_url';
    if (! empty($enable_product_flags))
       $query_fields .= ',left_flag,right_flag';
    if ($this->shopping_cart) {
       $query_fields .= ',(select count(sps.id) from related_products sps where ' .
                        '(parent=p.id) and (related_type=1)) as ' .
                        'sub_product_count';
       $query_fields .= ',(select count(rps.id) from related_products rps ' .
                        'where (parent=p.id)  and (related_type=0)) as ' .
                        'related_product_count';
       $query_fields .= ',(select count(atrs.id) from product_attributes atrs ' .
                        'where parent=p.id) as attribute_count';
    }
    if (isset($product_fields)) {
       foreach ($product_fields as $field_name => $field)
          if ($field['datatype']) $query_fields .= ',p.'.$field_name;
    }
    $least_fields = array();
    if ($this->features & LIST_PRICE_PRODUCT) {
       $query_fields .= ',p.list_price';   $least_fields[] = 'p.list_price';
    }
    if ($this->features & REGULAR_PRICE_PRODUCT) {
       $query_fields .= ',p.price';   $least_fields[] = 'p.price';
    }
    if ($this->features & SALE_PRICE_PRODUCT) {
       $query_fields .= ',p.sale_price';   $least_fields[] = 'p.sale_price';
    }
    if ($this->features & REGULAR_PRICE_BREAKS) $query_fields .= ',p.price_breaks';
    if ($this->features & LIST_PRICE_INVENTORY) {
       $query_fields .= ',min(i.list_price) as list_price';
       $least_fields[] = 'i.list_price';
    }
    if ($this->features & REGULAR_PRICE_INVENTORY) {
       $query_fields .= ',min(i.price) as price';   $least_fields[] = 'i.price';
    }
    if ($this->features & SALE_PRICE_INVENTORY) {
       $query_fields .= ',min(i.sale_price) as sale_price';
       $least_fields[] = 'i.sale_price';
    }
    if (count($least_fields) == 1)
       $query_fields .= ','.$least_fields[0].' as min_price';
    else if (count($least_fields) > 1) {
       $query_fields .= ',least(';
       $num_fields = count($least_fields);
       for ($loop1 = 0;  $loop1 < $num_fields;  $loop1++) {
           if ($loop1 > 0) $query_fields .= ',';
           $query_fields .= 'coalesce(';
           for ($loop2 = 0;  $loop2 < $num_fields;  $loop2++) {
              if ($loop2 > 0) $query_fields .= ',';
              $query_fields .= 'nullif('.$least_fields[($loop1+$loop2) % $num_fields] .
                               ',0)';
           }
           $query_fields .= ')';
       }
       $query_fields .= ') as min_price';
    }
    if (! empty($enable_wholesale)) $query_fields .= ',p.account_discount';
    if ($this->features & USE_PART_NUMBERS) $query_fields .= ',i.part_number';
    if ($this->features & MAINTAIN_INVENTORY)
       $query_fields .= ',i.qty,i.min_qty';
    if (! empty($shopping_feeds_enabled))
       $query_fields .= ',p.shopping_gtin,p.shopping_mpn,p.shopping_brand,' .
                        'p.shopping_gender,p.shopping_color,p.shopping_age';

    if (! isset($enable_multisite)) $enable_multisite = false;
    if ($enable_multisite && isset($website_id)) {
       $product_website_where = ' and find_in_set("'.$website_id .
                                '",p.websites)';
       $category_website_where = ' and find_in_set("'.$website_id .
                                 '",c.websites)';
    }
    else {
       $product_website_where = '';   $category_website_where = '';
    }

    if ($this->filter_search) {
       $id_where = '';   $ids_where = '';
    }
    else {
       $id_where = '(c.parent=@id@) and ';
       $ids_where = '(c.parent in (@ids@)) and ';
    }

    if ($this->filter_search && (! empty($product_group_field))) {
       $this->group_by = ' group by '.$product_group_field;
       $this->count_field = 'distinct '.$product_group_field;
    }
    else {
       $this->group_by = ' group by p.id';
       $this->count_field = 'distinct p.id';
    }

    if ($this->shopping_cart && ($this->features & HIDE_OUT_OF_STOCK)) {
       $this->product_query = 'select '.$query_fields.' from ';
       if ($this->filter_search)
          $this->product_query .= $this->products_table.' p';
       else $this->product_query .= $this->category_products_table .
          ' c left join '.$this->products_table.' p on p.id=c.related_id';
       $this->product_query .= ' left join product_inventory i on i.parent=p.id ' .
          'where '.$id_where.'(isnull(p.status) or (p.status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))'.$product_website_where;
       if ($this->features & MAINTAIN_INVENTORY)
          $this->product_query .= ' and (! isnull(i.qty)) and (i.qty>0)';
       $this->product_query .= $this->group_by;
       $this->all_product_query = 'select '.$query_fields.' from ';
       if ($this->filter_search)
          $this->all_product_query .= $this->products_table.' p';
       else $this->all_product_query .= $this->category_products_table.' c left join ' .
          $this->products_table .' p on p.id=c.related_id';
       $this->all_product_query .= ' left join product_inventory i on i.parent=p.id ' .
          'where '.$ids_where.'(isnull(p.status) or (p.status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))'.$product_website_where;
       if ($this->features & MAINTAIN_INVENTORY)
          $this->all_product_query .= ' and (! isnull(i.qty)) and (i.qty>0)';
       $this->all_product_query .= $this->group_by;
       $this->search_query = 'select '.$query_fields.' from '.$this->products_table .
          ' p left join product_inventory i on i.parent=p.id where ((p.name like ' .
          "'%@key@%') or (p.display_name like '%@key@%') or (p.menu_name like " .
          "'%@key@%') or (p.short_description like '%@key@%') or (p.long_description " .
          "like '%@key@%')";
       if ($this->features & USE_PART_NUMBERS)
          $this->search_query .= " or (i.part_number like '%@key@%')";
       $this->search_query .= ') and (isnull(p.flags) or (not (p.flags&4))) ' .
          'and (isnull(p.status) or (p.status!='.$this->off_sale_option .
          ')) and (not isnull(p.id))'.$product_website_where;
       if ($this->features & MAINTAIN_INVENTORY)
          $this->search_query .= ' and (! isnull(i.qty)) and (i.qty>0)';
       $this->search_query .= $this->group_by;
       $this->search_count_query = 'select count('.$this->count_field .
          ') as num_products '.'from '.$this->products_table.' p left join ' .
          'product_inventory i on i.parent=p.id where ((p.name like ' .
          '"%@key@%") or (p.display_name like "%@key@%") or (p.menu_name ' .
          'like "%@key@%") or (p.short_description like "%@key@%") or ' .
          '(p.long_description like "%@key@%")';
       if ($this->features & USE_PART_NUMBERS)
          $this->search_count_query .= " or (i.part_number like '%@key@%')";
       $this->search_count_query .= ') and (isnull(p.flags) or (not (p.flags&4))) ' .
          'and (isnull(p.status) or (p.status!='.$this->off_sale_option .
          '))'.$product_website_where;
       if ($this->features & MAINTAIN_INVENTORY)
          $this->search_count_query .= ' and (! isnull(i.qty)) and (i.qty>0)';
       $this->search_order = 'p.name';
    }
    else if (! $this->shopping_cart) {
       $this->product_query = 'select '.$query_fields.' from ';
          if ($this->filter_search)
             $this->product_query .= $this->products_table.' p';
          else $this->product_query .= $this->category_products_table .
             ' c left join '.$this->products_table.' p on p.id=c.related_id';
       $this->product_query .= ' where '.$id_where.'(isnull(p.status) ' .
          'or (p.status!='.$this->off_sale_option.')) and (not isnull(p.id))' .
          $product_website_where.$this->group_by;
       $this->all_product_query = 'select '.$query_fields.' from ';
       if ($this->filter_search)
          $this->all_product_query .= $this->products_table.' p';
       else $this->all_product_query .= $this->category_products_table .
          ' c left join '.$this->products_table.' p on p.id=c.related_id';
       $this->all_product_query .= ' where '.$ids_where.'(isnull(p.status) ' .
          'or (p.status!='.$this->off_sale_option.')) and (not isnull(p.id))' .
          $product_website_where.$this->group_by;
       $this->search_query = 'select '.$query_fields.' from '.$this->products_table .
          " p where ((p.name like '%@key@%') or (p.display_name like '%@key@%') " .
          "or (p.menu_name like '%@key@%') or (p.short_description like '%@key@%') " .
          "or (p.long_description like '%@key@%')) and (isnull(p.flags) or " .
          '(not (p.flags & 4))) and (isnull(p.status) or (p.status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))' .
          $product_website_where.$this->group_by;
       $this->search_count_query = 'select count(id) as num_products from ' .
          $this->products_table." p where ((name like '%@key@%') or (display_name " .
          "like '%@key@%') or (menu_name like '%@key@%') or (short_description " .
          "like '%@key@%') or (long_description like '%@key@%')) and " .
          '(isnull(flags) or (not (flags & 4))) and (isnull(status) or (status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))'.$product_website_where;
       $this->search_order = 'name';
    }
    else {
       $this->product_query = 'select '.$query_fields.' from ';
       if ($this->filter_search)
          $this->product_query .= $this->products_table.' p';
       else $this->product_query .= $this->category_products_table.' c left join ' .
          $this->products_table.' p on p.id=c.related_id';
       $this->product_query .= ' left join product_inventory i on i.parent=' .
          'p.id where '.$id_where.'(isnull(p.status) or (p.status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))' .
          $product_website_where.$this->group_by;
       $this->all_product_query = 'select '.$query_fields.' from ';
       if ($this->filter_search)
          $this->all_product_query .= $this->products_table.' p';
       else $this->all_product_query .= $this->category_products_table .
          ' c left join '.$this->products_table.' p on p.id=c.related_id';
       $this->all_product_query .= ' left join product_inventory i on i.parent=' .
          'p.id where '.$ids_where.'(isnull(p.status) or (p.status!=' .
          $this->off_sale_option.')) and (not isnull(p.id))' .
          $product_website_where.$this->group_by;
       if ($this->features & USE_PART_NUMBERS) {
          $this->search_query = 'select '.$query_fields.' from ' .
             $this->products_table.' p left join product_inventory i on ' .
             "i.parent=p.id where ((p.name like '%@key@%') or (p.display_name " .
             "like '%@key@%') or (p.menu_name like '%@key@%') or " .
             "(p.short_description like '%@key@%') or (p.long_description " .
             "like '%@key@%') or (i.part_number like '%@key@%')) " .
             'and (isnull(p.flags) or (not (p.flags&4))) and (isnull(p.status) ' .
             'or (p.status!='.$this->off_sale_option.')) and (not isnull(p.id))' .
             $product_website_where.$this->group_by;
          $this->search_count_query = 'select count('.$this->count_field .
             ') as num_products from '.$this->products_table.' p left join ' .
             'product_inventory i on i.parent=p.id where ((p.name like ' .
             "'%@key@%') or (p.display_name like '%@key@%') or (p.menu_name " .
             "like '%@key@%') or (p.short_description like '%@key@%') " .
             "or (p.long_description like '%@key@%') or (i.part_number like " .
             "'%@key@%')) and (isnull(p.flags) or (not (p.flags&4))) and " .
             '(isnull(p.status) or (p.status!='.$this->off_sale_option .
             ')) and (not isnull(p.id))'.$product_website_where;
          $this->search_order = 'p.name';
       }
       else {
          $this->search_query = 'select '.$query_fields.' from ' .
             $this->products_table.' p left join product_inventory i on ' .
             "i.parent=p.id where ((p.name like '%@key@%') or (p.display_name " .
             "like '%@key@%') or (p.menu_name like '%@key@%') or (p.short_" .
             "description like '%@key@%') or (p.long_description like " .
             "'%@key@%')) and (isnull(p.flags) or (not (p.flags & 4))) " .
             'and (isnull(p.status) or (p.status!='.$this->off_sale_option .
             ')) and (not isnull(p.id))'.$product_website_where.$this->group_by;
          $this->search_count_query = 'select count(id) as num_products ' .
             'from '.$this->products_table." p where ((name like '%@key@%') " .
             "or (short_description like '%@key@%') or (display_name like " .
             "'%@key@%') or (menu_name like '%@key@%') or (long_description " .
             "like '%@key@%')) and (isnull(flags) or (not (flags & 4))) " .
             'and (isnull(status) or (status!='.$this->off_sale_option .
             ')) and (not isnull(p.id))'.$product_website_where;
          $this->search_order = 'name';
       }
    }

    $this->product_order = 'c.sequence,p.name';
    $this->category_order = 's.sequence,c.name';
    $this->all_product_order = 'p.name';
    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    $this->category_search_query = 'select * from '.$this->categories_table .
       " where ((name like '%@key@%') or (display_name like '%@key@%') or " .
       "((menu_name like '%@key@%') or (short_description like '%@key@%') or " .
       "(long_description like '%@key@%')) and (isnull(status) or (status!=" .
       $category_off_sale_option.'))'.$category_website_where;
    $this->category_search_count_query = 'select count(id) as num_categories ' .
       'from '.$this->categories_table." where ((name like '%@key@%') or " .
       "(display_name like '%@key@%') or (menu_name like '%@key@%') or " .
       "(short_description like '%@key@%') or (long_description like '%@key@%')) " .
       'and (isnull(c.status) or (c.status!='.$category_off_sale_option.'))' .
       $category_website_where;
    $this->category_search_order = 'name';
}

function set_products_table($products_table)
{
    parent::set_products_table($products_table);
    $this->setup_queries();
}

function start_page($mtime=null)
{
    if (isset($mtime)) $mtime = max($mtime,$this->last_modified);
    else $mtime = $this->last_modified;
    $this->setup_conteg($mtime);
}

function set_page_size($page_size)
{
    global $cache_catalog_pages;

    if (! empty($cache_catalog_pages)) return;
    if (intval($page_size) < 0) $this->page_size = null;
    else $this->page_size = intval($page_size);
}

function set_page_number($page_number)
{
    if (intval($page_number) < 0) $this->page_number = null;
    else $this->page_number = intval($page_number);
}

function set_index_by_id($index_by_id)
{
    $this->index_by_id = $index_by_id;
}

function set_product_query($query)
{
    $this->product_query = $query;
}

function set_all_product_query($query)
{
    $this->all_product_query = $query;
}

function set_product_order($order_by)
{
    $this->product_order = $order_by;
}

function set_all_product_order($order_by)
{
    $this->all_product_order = $order_by;
}

function set_search_query($query)
{
    $this->search_query = $query;
}

function set_search_count_query($query)
{
    $this->search_count_query = $query;
}

function set_search_order($order_by)
{
    $this->search_order = $order_by;
}

function set_category_search_query($query)
{
    $this->category_search_query = $query;
}

function set_category_search_count_query($query)
{
    $this->category_search_count_query = $query;
}

function set_category_search_order($order_by)
{
    $this->category_search_order = $order_by;
}

function set_category_order($order_by)
{
    $this->category_order = $order_by;
}

function load_subcategory_ids($cat_id)
{
    $query = "select related_id from ".$this->subcategories_table .
             " where parent=".$cat_id." order by sequence";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_subcategory_ids',
                                   1,$this->db);
    $subcategories = $this->db->get_records($query,null,'related_id');
    return $subcategories;
}

function load_parent_ids($cat_id=null)
{
    if (! $cat_id) $cat_id = $this->id;
    $query = 'select parent from '.$this->subcategories_table .
             ' where related_id='.$cat_id.' order by parent';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_parent_ids',1,
                                   $this->db);
    $rows = $this->db->get_records($query);
    if (! $rows) {
       if (isset($this->db->error)) log_error("Database Error: ".$this->db->error);
       return null;
    }
    $parent_ids = array();
    foreach ($rows as $row)
       if ($row['parent']) $parent_ids[] = $row['parent'];
    return $parent_ids;
}

function find_subcategory_ids($id,$subcategories,&$ids)
{
    if (in_array($id,$ids)) return null;
    $ids[] = $id;   $subcategory_array = $subcategories;
    foreach ($subcategory_array as $subcategory)
       if ($subcategory['parent'] == $id)
          $this->find_subcategory_ids($subcategory['related_id'],
                                      $subcategories,$ids);
}

function load_all_subcategory_ids($cat_id=null)
{
    global $subcategories_table;

    if (! $cat_id) $cat_id = $this->id;
    if (! $cat_id) return null;
    if (isset($this)) {
       $db = $this->db;
       $subcategories_table = $this->subcategories_table;
    }
    else {
       $db = new DB;
       if (! isset($subcategories_table))
          $subcategories_table = 'subcategories';
    }

    $query = "select * from ".$subcategories_table." order by parent,sequence";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category',
                                   'load_all_subcategory_ids',1,$db);
    if ((! isset($this)) || $this->index_by_id)
       $subcategories = $db->get_records($query,'id');
    else $subcategories = $db->get_records($query);
    if (! $subcategories) return null;

    $ids = array();
    $this->find_subcategory_ids($cat_id,$subcategories,$ids);

    return $ids;
}

function load_specific_categories($cat_ids=array())
{
    global $category_off_sale_option,$subcategories_table;
    global $website_id,$enable_multisite;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    if (! isset($enable_multisite)) $enable_multisite = false;
    if (! $cat_ids) return array();
    if (isset($this)) {
       $db = $this->db;   $category_order = $this->category_order;
    }
    else {
       $db = new DB;   $category_order = 's.sequence,c.name';
    }
    $query = 'select c.* from categories c where c.id in ('.
             implode(',',$cat_ids).
             ') and (isnull(c.status) or (c.status!='.
             $category_off_sale_option.'))';
    if ($enable_multisite && isset($website_id))
       $query .= ' and find_in_set("'.$website_id.'",c.websites)';
    $query .= ' order by '.$category_order;
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_subcategories',
                                   1,$db);
    if ((! isset($this)) || $this->index_by_id)
       $subcategories = $db->get_records($query,'id');
    else $subcategories = $db->get_records($query);
    if (! $subcategories) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       return null;
    }
    if (isset($this)) {
       foreach ($subcategories as $row) {
          if ($row['last_modified'] > $this->last_modified)
             $this->last_modified = $row['last_modified'];
       }
    }
    return $subcategories;
}

function append_filter_query(&$query,$filter_query)
{
    $group_by_pos = strripos($query,'group by');
    $where_pos = strripos($query,'where');
    if (($group_by_pos === false) ||
        (($where_pos !== false) && ($group_by_pos < $where_pos)))
       $query .= ' AND ('.$filter_query.')';
    else $query = substr($query,0,$group_by_pos).'AND ('.$filter_query.') ' .
         substr($query,$group_by_pos);
}

function update_queries_with_filter()
{
    if (isset($this->filter_product_query)) {
       $this->append_filter_query($this->product_query,$this->filter_product_query);
       $this->append_filter_query($this->all_product_query,$this->filter_product_query);
       $this->append_filter_query($this->search_query,$this->filter_product_query);
       $this->append_filter_query($this->search_count_query,$this->filter_product_query);
    }
    if (isset($this->filter_subcategory_query)) {
       $this->append_filter_query($this->category_search_query,$this->filter_subcategory_query);
       $this->append_filter_query($this->category_search_count_query,$this->filter_subcategory_query);
    }
}

function set_filter_query($query,$type=1)
{
    if ($type === 0) $this->filter_subcategory_query = $query;
    else $this->filter_product_query = $query;
    $this->update_queries_with_filter();
}

function load_subcategories($cat_id=null)
{
    global $category_off_sale_option,$subcategories_table,$website_id;
    global $category_products_table,$enable_multisite;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    if (! isset($enable_multisite)) $enable_multisite = false;
    if (! $cat_id) $cat_id = $this->id;
    if (! $cat_id) return null;
    if (isset($this)) {
       $db = $this->db;   $category_order = $this->category_order;
       $subcategories_table = $this->subcategories_table;
       $category_products_table = $this->category_products_table;
    }
    else {
       $db = new DB;   $category_order = "s.sequence,c.name";
       if (! isset($subcategories_table))
          $subcategories_table = 'subcategories';
       if (! isset($category_products_table))
          $category_products_table = 'category_products';
    }
    $query = 'select c.*,(select count(id) from '.$category_products_table .
             ' where parent=c.id) as num_products,(select count(id) from ' .
             $subcategories_table.' where parent=c.id) as num_categories from ' .
             $subcategories_table.' s left join ' .
             'categories c on c.id=s.related_id where s.parent=' .
             $cat_id.' and (isnull(c.status) or (c.status!=' .
             $category_off_sale_option.'))';
    if ($enable_multisite && isset($website_id))
       $query .= ' and find_in_set("'.$website_id.'",c.websites)';
    $query .= " order by ".$category_order;
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_subcategories',
                                   1,$db);
    if ((! isset($this)) || $this->index_by_id)
       $subcategories = $db->get_records($query,'id');
    else $subcategories = $db->get_records($query);
    if (! $subcategories) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,-1);
       return null;
    }
    if (isset($this)) {
       foreach ($subcategories as $row) {
          if ($row['last_modified'] > $this->last_modified)
             $this->last_modified = $row['last_modified'];
       }
    }
    return $subcategories;
}

function load_info($cat_id=null)
{
    global $categories_table,$enable_category_filter_search;

    if ((! $cat_id) && isset($this)) $cat_id = $this->id;
    if (! $cat_id) {
       if (isset($this)) $this->info = null;
       return null;
    }
    if (! is_numeric($cat_id)) {
       process_error("Invalid Category ID ".$cat_id,-1);
       if (isset($this)) $this->info = null;
       return null;
    }
    if (isset($this)) {
       $db = $this->db;
       $categories_table = $this->categories_table;
    }
    else {
       $db = new DB;
       if (! isset($categories_table)) $categories_table = 'categories';
    }
    $query = 'select * from '.$categories_table.' where id=?';
    $query = $db->prepare_query($query,$cat_id);
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_info',1,$db);
    $info = $db->get_record($query);
    if (! $info) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,-1);
       if (isset($this)) $this->info = null;
       return null;
    }
    $info['num_images'] = 0;
    if (isset($this)) $filter_search = $this->filter_search;
    else if (isset($enable_category_filter_search) &&
             $enable_category_filter_search) $filter_search = true;
    if ($filter_search && ($info['products_source'] != 1))
       $filter_search = false;
    if ($filter_search) {
       $query = 'select f.field_name,f.field_values,c.filter_label,' .
          'c.filter_type,c.filter_group,c.filter_value_source,' .
          'c.filter_values,c.filter_sequence from category_filters f left ' .
          'join catalog_fields c on c.field_name=f.field_name and ' .
          'c.filter=1 where f.parent=? order by c.filter_sequence';
       $query = $db->prepare_query($query,$cat_id);
       $filters = $db->get_records($query);
       if ((! $filters) && isset($db->error)) {
          process_error("Database Error: ".$db->error,-1);   return null;
       }
       $info['filters'] = $filters;
    }
    if (isset($this)) {
       $this->info = $info;
       if (isset($info['last_modified']) &&
           ($info['last_modified'] > $this->last_modified))
          $this->last_modified = $info['last_modified'];
       if ($this->filter_search) {
          if (! $filter_search) $this->filter_search = false;
          else $this->num_filters = count($info['filters']);
       }
    }
    return $info;
}

function set_category_filters()
{
    if (! $this->filter_search) return;
    $this->query_filters = array();   $form_fields = get_form_fields();
    foreach ($form_fields as $field_name => $field_value) {
       if (substr($field_name,0,13) == 'filter_group_')
          $this->query_filters[] =
             array('filter_group' => substr($field_name,13),
                   'field_values' => $field_value);
    }
    if (($this->num_filters > 0) && (count($this->query_filters) == 0)) {
       foreach ($this->info['filters'] as $filter) {
          if (! $filter['filter_group']) continue;
          $_GET['filter_group_'.$filter['filter_group']] =
             $filter['field_values'];
       }
    }
    $this->setup_queries();
}

function get_discount_prices()
{
    global $account_product_prices,$custom_account_product_prices;

    if ($custom_account_product_prices) return;

    $ids = array();
    foreach ($this->products as $product_info) {
       if (! in_array($product_info['id'],$ids)) $ids[] = $product_info['id'];
    }
    $query = 'select related_id,price,discount from account_products where ' .
             '(parent=?) and (related_id in (?))';
    $query = $this->db->prepare_query($query,$this->account_id,$ids);
    $product_discounts = $this->db->get_records($query,'related_id');
    if ($this->features & REGULAR_PRICE_INVENTORY) {
       $query = 'select related_id,discount from account_inventory where ' .
                'parent=? and related_id in (select id from product_inventory ' .
                'where parent in (?))';
       $query = $this->db->prepare_query($query,$this->account_id,$ids);
       $inv_discounts = $this->db->get_records($query,'related_id','discount');
    }
    foreach ($this->products as $index => $product_info) {
       $product_id = $product_info['id'];   $price = null;
       if ($product_info['flags'] & NO_ACCOUNT_DISCOUNTS)
          $use_account_discounts = false;
       else $use_account_discounts = true;
       if ($this->features & REGULAR_PRICE_INVENTORY) {
          if (array_key_exists('inv_attributes',$product_info))
             $inv_attributes = $product_info['inv_attributes'];
          else $inv_attributes = null;
          foreach ($product_info['inventory'] as $inv_index => $inv_info) {
             $inv_id = $inv_info['id'];
             $this->products[$index]['inventory'][$inv_index]['regular_price'] =
                $inv_info['price'];
             if ((! $inv_discounts) || (! isset($inv_discounts[$inv_id])) ||
                 (! $use_account_discounts)) {
                $price = 0;   $factor = 1;
             }
             else if ($account_product_prices === true)
                $price = $inv_discounts[$inv_id];
             else $factor = (100 - $inv_discounts[$inv_id]) / 100;
             if (($account_product_prices === true) && $use_account_discounts) {
                if ($price)
                   $this->products[$index]['inventory'][$inv_index]['price'] = $price;
                else {
                   unset($this->products[$index]['inventory'][$inv_index]);
                   unset($product_info['inventory'][$inv_index]);   continue;
                }
             }
             else $this->products[$index]['inventory'][$inv_index]['price'] =
                     round($inv_info['price'] * $factor,2);
             if (($inv_attributes !== null) &&
                 ($this->products[$index]['inventory'][$inv_index]['attributes'] ==
                  $inv_attributes)) {
                $this->products[$index]['regular_price'] = $inv_info['price'];
                $this->products[$index]['price'] =
                   number_format($this->products[$index]['inventory'][$inv_index]['price'],2);
             }
          }
          if (count($product_info['inventory']) == 0) {
             unset($this->products[$index]);   continue;
          }
       }
       if ($use_account_discounts) {
          if (! empty($product_info['account_discount']))
             $discount = floatval($product_info['account_discount']);
          else $discount = $this->discount;
       }
       else $discount = 0;
       $factor = (100 - $discount) / 100;
       if (isset($product_discounts[$product_id]) && $use_account_discounts)
          $price = get_account_product_price($price,
                      $product_discounts[$product_id],$discount);
       else if (! empty($product_info['price']))
          $price = round($product_info['price'] * $factor,2);
       else $price = 0;
       if (($this->features & REGULAR_PRICE_BREAKS) &&
           isset($product_discounts[$product_id])) {
          $price_entries = explode('|',$product_info['price_breaks']);
          $num_entries = count($price_entries);
          for ($loop = 0;  $loop < $num_entries;  $loop++) {
             if ($price_entries[$loop] == '') continue;
             $price_details = explode('-',$price_entries[$loop]);
             $price_details[2] = get_account_product_price($price_details[2],
                 $product_discounts[$product_id],$discount,true);
             $price_entries[$loop] = implode('-',$price_details);
          }
          $this->products[$index]['price_breaks'] = implode('|',$price_entries);
       }
       if ($this->features & REGULAR_PRICE_PRODUCT) {
          $this->products[$index]['regular_price'] = $product_info['price'];
          $this->products[$index]['price'] = $price;
       }
       else if ($this->features & REGULAR_PRICE_INVENTORY) {
          if (array_key_exists('inv_attributes',$product_info))
             $inv_attributes = $product_info['inv_attributes'];
          else $inv_attributes = null;
          foreach ($product_info['inventory'] as $inv_index => $inv_info) {
             if (isset($this->products[$index]['regular_price'])) continue;
             $this->products[$index]['inventory'][$inv_index]['regular_price'] =
                $inv_info['price'];
             if (($account_product_prices === true) && $use_account_discounts) {
                if ($price)
                   $this->products[$index]['inventory'][$inv_index]['price'] =
                      $price;
                else {
                   unset($this->products[$index]['inventory'][$inv_index]);
                   unset($product_info['inventory'][$inv_index]);   continue;
                }
             }
             else {
                $this->products[$index]['inventory'][$inv_index]['price'] =
                     round($inv_info['price'] * $factor,2);
             }
             if (($inv_attributes !== null) &&
                 ($this->products[$index]['inventory'][$inv_index]
                  ['attributes'] == $inv_attributes)) {
                $this->products[$index]['regular_price'] = $inv_info['price'];
                $this->products[$index]['price'] =
                   number_format($this->products[$index]['inventory']
                                 [$inv_index]['price'],2);
             }
          }
          if (count($product_info['inventory']) == 0) {
             unset($this->products[$index]);   continue;
          }
       }
       if ($account_product_prices && $use_account_discounts) {}
       else if ($this->features & SALE_PRICE_PRODUCT) {
          $this->products[$index]['regular_sale_price'] =
             $product_info['sale_price'];
          $this->products[$index]['sale_price'] =
             round($product_info['sale_price'] * $factor,2);
       }
       else if ($this->features & SALE_PRICE_INVENTORY) {
          foreach ($product_info['inventory'] as $inv_index => $inv_info) {
             $this->products[$index]['inventory'][$inv_index]['regular_sale_price'] =
                $inv_info['sale_price'];
             $this->products[$index]['inventory'][$inv_index]['sale_price'] =
                round($inv_info['sale_price'] * $factor,2);
          }
       }
    }
    if (function_exists('custom_update_discount_prices'))
       custom_update_discount_prices($this);
}

function load_images()
{
    global $use_dynamic_images;

    if (! $this->id) {
       $this->info['images'] = null;   $this->info['num_images'] = 0;
       $this->info['image_data'] = null;   return null;
    }
    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $this->images = array();
    $this->image_data = array();
    $query = "select * from images where parent_type=" .
             $this->categories_image_type." and parent=".$this->id .
             " order by sequence,id";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_images',1,
                                   $this->db);
    $rows = $this->db->get_records($query);
    if ($rows) foreach ($rows as $row) {
       $row['dynamic'] = $use_dynamic_images;
       $this->images[$row['filename']] = strip_tags($row['caption']);
       $this->image_data[$row['filename']] = $row;
    }
    $this->info['images'] = $this->images;
    $this->info['image_data'] = $this->image_data;
    $this->info['num_images'] = count($this->images);
    return $this->images;
}

function get_other_products($type,$data=null)
{
    global $cart_cookie;

    $ids = array();
    switch ($type) {
       case 'cart':
          $cart_id = get_cookie($cart_cookie);
          if (! $cart_id) $cart_id = get_form_field('CartID');
          if (! $cart_id) return $ids;
          if (isset($data['related_type'])) {
             $query = 'select distinct related_id from related_products where ' .
                      '(related_type=?) and (parent in (select distinct ' .
                      'product_id from cart_items where parent=?))';
             $query = $this->db->prepare_query($query,$data['related_type'],$cart_id);
             $ids = $this->db->get_records($query,null,'related_id');
          }
          else {
             $query = 'select distinct product_id from cart_items where parent=?';
             $query = $this->db->prepare_query($query,$cart_id);
             $ids = $this->db->get_records($query,null,'product_id');
          }
          if (! $ids) return array();
          break;
       default:
          require_once __DIR__.'/../engine/modules.php';
          if (module_attached('get_other_products')) {
             $ids = call_module_event('get_other_products',
                                      array($type,$this->db,$this,$data),
                                      null,true,true);
             if (! $ids) $ids = array();
          }
          break;
    }
    return $ids;
}

function get_product_count($all_sub_flag=false)
{
    if (! $this->id) return null;
    if ($this->shopping_cart) {
       $query = 'select count('.$this->count_field.') as num_products from ';
       if ($this->filter_search) $query .= $this->products_table.' p';
       else $query .= $this->category_products_table.' c left join ' .
                         $this->products_table.' p on p.id=c.related_id';
       $query .= ' left join product_inventory i on i.parent=p.id where ';
    }
    else {
       $query = 'select count('.$this->count_field.') as num_products from ';
       if ($this->filter_search) $query .= $this->products_table.' p';
       else $query .= $this->category_products_table.' c left join ' .
                         $this->products_table.' p on p.id=c.related_id';
       $query .= ' where ';
    }
    if (! $this->filter_search) {
       if ($all_sub_flag) {
          if (is_array($all_sub_flag)) $cat_ids = $all_sub_flag;
          else $cat_ids = $this->load_all_subcategory_ids();
          if (count($cat_ids) == 0) return null;
          $query .= "(c.parent in (".implode(",",$cat_ids).")) and ";
       }
       else $query .= "(c.parent=".$this->id.") and ";
    }
    $query .= "(isnull(p.status) or (p.status!=".$this->off_sale_option .
              "))";
    if ($this->shopping_cart && ($this->features & MAINTAIN_INVENTORY) &&
        ($this->features & HIDE_OUT_OF_STOCK))
       $query .= " and (! isnull(i.qty)) and (i.qty>0)";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','get_product_count',
                                   1,$this->db);
    if (isset($this->filter_product_query)){
       $this->append_filter_query($query,$this->filter_product_query);
    }
    $row = $this->db->get_record($query);
    if (! $row) {
       process_error("Database Error: ".$this->db->error,-1);   return;
    }
    $num_products = $row['num_products'];
    return $num_products;
}

function load_products($all_sub_flag=false)
{
    global $use_dynamic_images,$enable_product_flags;

    if (! $this->id) {
       $this->products = null;
       if (function_exists('custom_load_products'))
          custom_load_products($this);
       return null;
    }
    if ($all_sub_flag) {
       if (is_array($all_sub_flag)) $cat_ids = $all_sub_flag;
       else $cat_ids = $this->load_all_subcategory_ids();
       if (count($cat_ids) == 0) {
          if (function_exists('custom_load_products'))
             custom_load_products($this);
          return null;
       }
       $query = str_replace('@ids@',implode(',',$cat_ids),$this->all_product_query);
       if ($this->all_product_order) $query .= ' order by '.$this->all_product_order;
    }
    else {
       $query = str_replace('@id@',$this->id,$this->product_query);
       if ($this->product_order) $query .= ' order by '.$this->product_order;
    }
    if (isset($this->page_size,$this->page_number)) {
       $start_row = $this->page_number * $this->page_size;
       $query .= ' limit '.$start_row.','.$this->page_size;
    }
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_products',
                                   1,$this->db);
    $rows = $this->db->get_records($query);
    if (! $rows) {
       if (isset($this->db->error)) {
          if ($this->ajax_call) http_response(422,$this->db->error);
          else process_error('Database Error: '.$this->db->error,-1);
       }
       $this->products = null;
       if (function_exists('custom_load_products'))
          custom_load_products($this);
       return null;
    }
    $this->products = array();   $ids = array();
    foreach ($rows as $row) {
       $id = $row['id'];
       if (! $id) continue;
       if ((! empty($enable_product_flags)) &&
           ((! empty($row['left_flag'])) || (! empty($row['right_flag'])))) {
          require_once 'admin/productflags-admin.php';
          load_product_flag_info($this->db,$row);
       }
       $this->products[$id] = $row;
       $ids[] = $id;
       if ($this->shopping_cart) $this->products[$id]['inventory'] = array();
       $this->products[$id]['attributes'] = array();
       $this->products[$id]['images'] = array();
       $this->products[$id]['image_data'] = array();
       $this->products[$id]['num_images'] = 0;
       if ($this->shopping_cart &&
           ($this->features & (QTY_DISCOUNTS|QTY_PRICING)))
          $this->products[$id]['discounts'] = array();
       $this->products[$id]['data'] = array();
       if ($row['last_modified'] > $this->last_modified)
          $this->last_modified = $row['last_modified'];
    }

    if (empty($ids)) {
       if (function_exists('custom_load_products'))
          custom_load_products($this);
       return $this->products;
    }

    if ($this->shopping_cart) {
       $query = 'select * from product_inventory where parent in (?) ' .
                'order by parent,sequence';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_products',
                                      2,$this->db);
       $query = $this->db->prepare_query($query,$ids);
       $rows = $this->db->get_records($query);
       if ($rows) {
          foreach ($rows as $row) {
             $id = $row['id'];   $parent = $row['parent'];
             $this->products[$parent]['inventory'][$id] = $row;
          }
       }
       else if (isset($this->db->error)) {
          if ($this->ajax_call) http_response(422,$this->db->error);
          else process_error('Database Error: '.$this->db->error,-1);
          if (function_exists('custom_load_products'))
             custom_load_products($this);
          return $this->products;
       }

       $first_attr = true;   $attr_ids = '';   $attr_array = array();
       $query = 'select a.*,p.parent from product_attributes p left join ' .
                'attributes a on a.id=p.related_id where p.parent in (?) ' .
                'order by parent,sequence';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_products',
                                      3,$this->db);
       $query = $this->db->prepare_query($query,$ids);
       $rows = $this->db->get_records($query);
       if ($rows) {
          $last_parent = -1;
          foreach ($rows as $row) {
             $attr_id = $row['id'];
             if (! $attr_id) continue;
             $parent = $row['parent'];
             if ($parent != $last_parent) {
                $index = 0;   $last_parent = $parent;
             }
             $row['order_id'] = $index;
             $this->products[$parent]['attributes'][$attr_id] = $row;
             if (! in_array($attr_id,$attr_array)) {
                if ($first_attr) $first_attr = false;
                else $attr_ids .= ',';
                $attr_ids .= $attr_id;
                $attr_array[] = $attr_id;
             }
             $index++;
          }
       }
       else if (isset($this->db->error)) {
          if ($this->ajax_call) http_response(422,$this->db->error);
          else process_error('Database Error: '.$this->db->error,-1);
          if (function_exists('custom_load_products'))
             custom_load_products($this);
          return $this->products;
       }

       if ($attr_ids == '') $this->attribute_options = null;
       else {
          $this->attribute_options = array();
          $query = 'select o.*,(select filename from images where parent_type=' .
                   $this->products_image_type.' and parent=o.id limit 1)' .
                   ' as image from attribute_options o where o.parent in (' .
                   $attr_ids.') order by o.parent,o.sequence';
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'category','load_products',
                                         4,$this->db);
          $rows = $this->db->get_records($query);
          if ($rows) {
             foreach ($rows as $row) {
                if (! isset($this->attribute_options[$row['parent']]))
                   $this->attribute_options[$row['parent']] = array();
                $this->attribute_options[$row['parent']][$row['id']] = $row;
             }
          }
          else if (isset($this->db->error)) {
             if ($this->ajax_call) http_response(422,$this->db->error);
             else process_error('Database Error: '.$this->db->error,-1);
             if (function_exists('custom_load_products'))
                custom_load_products($this);
             return $this->products;
          }
       }
    }

    if ($this->shopping_cart &&
        ($this->features & (QTY_DISCOUNTS|QTY_PRICING))) {
       if ($this->wholesale) $discount_type = 1;
       else $discount_type = 0;
       $query = 'select * from product_discounts where parent in (?) and ' .
                'discount_type=? order by start_qty,end_qty';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_products',6,
                                      $this->db);
       $query = $this->db->prepare_query($query,$ids,$discount_type);
       $rows = $this->db->get_records($query);
       if ($rows) {
          foreach ($rows as $row) {
             $parent = $row['parent'];
             $this->products[$parent]['discounts'][$row['id']] = $row;
          }
       }
       else if (isset($this->db->error)) {
          if ($this->ajax_call) http_response(422,$this->db->error);
          else process_error('Database Error: '.$this->db->error,-1);
       }
    }

    if ($this->load_product_data) {
       $query = 'select * from product_data where parent in (?) ' .
                'order by data_type,sequence';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_products',7,
                                      $this->db);
       $query = $this->db->prepare_query($query,$ids);
       $rows =  $this->db->get_records($query);
       if ($rows) {
          foreach ($rows as $row) {
             $parent = $row['parent'];
             $this->products[$parent]['data'][$row['id']] = $row;
          }
       }
       else if (isset($this->db->error)) {
          if ($this->ajax_call) http_response(422,$this->db->error);
          else process_error('Database Error: '.$this->db->error,-1);
       }
    }

    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $query = 'select * from images where parent_type=? and parent in (?) ' .
             'order by parent,sequence,id';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_products',5,
                                   $this->db);
    $query = $this->db->prepare_query($query,$this->products_image_type,$ids);
    $rows = $this->db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $row['dynamic'] = $use_dynamic_images;
          $filename = $row['filename'];   $parent = $row['parent'];
          $this->products[$parent]['images'][$filename] =
             strip_tags($row['caption']);
          $this->products[$parent]['image_data'][$filename] = $row;
          $this->products[$parent]['num_images'] =
             count($this->products[$parent]['images']);
       }
    }
    else if (isset($this->db->error)) {
       if ($this->ajax_call) http_response(422,$this->db->error);
       else process_error('Database Error: '.$this->db->error,-1);
    }

    if ($this->wholesale) $this->get_discount_prices();
    if (function_exists('custom_load_products')) custom_load_products($this);
    return $this->products;
}

function escape_data($data)
{
    $data = str_replace("'","\\'",$data);
    $data = str_replace("\n","<br>",$data);
    $data = str_replace("\r","",$data);
    return $data;
}

function ajax_load_products()
{
    global $inventory_fields;

    if (! isset($inventory_fields)) $inventory_fields = array();
    $this->ajax_call = true;
    $products = $this->load_products();
    $product_var = get_form_field("ProductVar");
    if (! $product_var) $product_var = "products";
    $inventory_var = get_form_field("InventoryVar");
    if (! $inventory_var) $inventory_var = "inventory";

    if ($products) foreach ($products as $product_info) {
       print $product_var."[".$product_info['id']."] = ['" .
             $this->escape_data($product_info['name'])."','" .
             $this->escape_data($product_info['short_description'])."','";
       $images = $product_info['image_data'];
       if ($images) {
          reset($images);   $image_filename = key($images);
       }
       else $image_filename = null;
       if ($image_filename) print $image_filename;
       print "']; ";
       print $inventory_var."[".$product_info['id']."] = [";
       $inventory = $product_info['inventory'];
       $first_inventory = true;
       if ($inventory) foreach ($inventory as $inventory_info) {
          if ($first_inventory) $first_inventory = false;
          else print ",";
          print "[".$inventory_info['id'].",";
          if (isset($inventory_info['sequence']))
             print $inventory_info['sequence'].",'";
          else print "0,'";
          print $inventory_info['attributes']."'";
          foreach ($inventory_fields as $field_name => $field) {
             if ($field['datatype'] == CHAR_TYPE)
                print ",'".$this->escape_data($inventory_info[$field_name])."'";
             else if (isset($inventory_info[$field_name]))
                print ",".$inventory_info[$field_name];
             else print ",0";
          }
          print "]";
       }
       print "]; ";
    }
}

function load_category_data()
{
    global $website_id,$enable_multisite,$category_off_sale_option;

    if (isset($this->categories)) return;
    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    $query = 'select * from '.$this->categories_table .
             ' where (isnull(status) or (status!='.$category_off_sale_option .
             '))';
    if ((! empty($enable_multisite)) && isset($website_id))
       $query .= ' and find_in_set("'.$website_id.'",websites)';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_category_data',1,
                                   $this->db);
    $this->categories = $this->db->get_records($query,'id');
    $query = 'select s.* from '.$this->subcategories_table.' s left join ' .
             $this->categories_table.' c on c.id=s.related_id order by ' .
             $this->category_order;
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_category_data',2,
                                   $this->db);
    $this->subcategories = $this->db->get_records($query);
    $this->subcategory_ids = array();
    foreach ($this->subcategories as $subcategory) {
       $parent = $subcategory['parent'];
       if (! isset($this->subcategory_ids[$parent]))
          $this->subcategory_ids[$parent] = array();
       $this->subcategory_ids[$parent][] = $subcategory['related_id'];
    }
}

function load_subcategory_info($cat_id=null,$load_images=true)
{
    global $category_off_sale_option,$use_dynamic_images;
    global $categories_table,$subcategories_table,$categories_image_type;
    global $website_id,$enable_multisite;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    if (! $cat_id) $cat_id = $this->id;
    if (! $cat_id) return null;
    if (isset($this) && (! empty($this->categories))) {
       $rows = array();
       if (! empty($this->subcategory_ids[$cat_id])) {
          foreach ($this->subcategory_ids[$cat_id] as $related_id) {
             if (isset($this->categories[$related_id]))
                $rows[] = $this->categories[$related_id];
          }
       }
    }
    else {
       if (isset($this)) {
          $db = $this->db;   $category_order = $this->category_order;
          $categories_table = $this->categories_table;
          $subcategories_table = $this->subcategories_table;
          $categories_image_type = $this->categories_image_type;
       }
       else {
          $db = new DB;   $category_order = "s.sequence,c.name";
          if (! isset($categories_table)) $categories_table = 'categories';
          if (! isset($subcategories_table))
             $subcategories_table = 'subcategories';
          if (! isset($categories_image_type))
             $categories_image_type = 0;
       }
       $query = 'select s.related_id,c.* from '.$subcategories_table .
                ' s left join '.$categories_table.' c on c.id=s.related_id ' .
                'where s.parent=? and (isnull(c.status) or (c.status!=' .
                $category_off_sale_option.'))';
       $query = $db->prepare_query($query,$cat_id);
       if ((! empty($enable_multisite)) && isset($website_id))
          $query .= ' and find_in_set("'.$website_id.'",c.websites)';
       $query .= ' order by '.$category_order;
       if (isset($this,$this->page_size,$this->page_number)) {
          $start_row = $this->page_number * $this->page_size;
          $query .= ' limit '.$start_row.','.$this->page_size;
       }
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_subcategory_info',
                                      1,$db);
       $rows = $db->get_records($query);
       if (! $rows) {
          if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
          return null;
       }
    }
    $categories = array();   $ids = array();
    foreach ($rows as $row) {
       $id = $row['id'];
       if (! $id) continue;
       $categories[$id] = $row;
       $ids[] = $id;
       $categories[$id]['images'] = array();
       $categories[$id]['image_data'] = array();
       if (isset($this) && isset($this->last_modified) &&
           ($row['last_modified'] > $this->last_modified))
          $this->last_modified = $row['last_modified'];
       $categories[$id]['num_images'] = 0;
    }

    if (empty($ids)) return $categories;
    if (! $load_images) return $categories;

    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $query = 'select * from images where parent_type=? and parent in (?) ' .
             'order by parent,sequence,id';
    $query = $db->prepare_query($query,$categories_image_type,$ids);
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_subcategory_info',
                                   2,$db);
    $rows = $db->get_records($query);
    if ($rows) foreach ($rows as $row) {
       $row['dynamic'] = $use_dynamic_images;
       $filename = $row['filename'];   $parent = $row['parent'];
       $categories[$parent]['images'][$filename] =
          strip_tags($row['caption']);
       $categories[$parent]['image_data'][$filename] = $row;
       $categories[$parent]['num_images'] =
          count($categories[$parent]['images']);
    }
    else if (isset($db->error)) process_error('Database Error: '.$db->error,-1);

    return $categories;
}

function load_subcategory_num_children(&$categories)
{
    global $enable_category_filter_search;

    if (! isset($enable_category_filter_search))
       $enable_category_filter_search = false;
    $cat_ids = array();
    if ($enable_category_filter_search) $filter_cat_ids = array();
    foreach ($categories as $category) {
       $cat_id = $category['id'];
       $categories[$cat_id]['num_products'] = 0;
       $categories[$cat_id]['num_subcategories'] = 0;
       if ($enable_category_filter_search) {
          if ($category['products_source'] == 1) $filter_cat_ids[] = $cat_id;
          else $cat_ids[] = $cat_id;
       }
       else $cat_ids[] = $cat_id;
    }
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    if (count($cat_ids) > 0) {
       $query = 'select parent,count(id) as num_products from ' .
                'category_products where parent in (?) group by parent';
       $query = $db->prepare_query($query,$cat_ids);
       $rows = $db->get_records($query,'parent','num_products');
       if ($rows) foreach ($rows as $cat_id => $num_products)
          $categories[$cat_id]['num_products'] = $num_products;
       $query = 'select parent,count(id) as num_subcategories from ' .
                'subcategories where parent in (?) group by parent';
       $query = $db->prepare_query($query,$cat_ids);
       $rows = $db->get_records($query,'parent','num_subcategories');
       if ($rows) foreach ($rows as $cat_id => $num_subcategories)
          $categories[$cat_id]['num_subcategories'] = $num_subcategories;
    }
    if ($enable_category_filter_search && (count($filter_cat_ids) > 0)) {
       $query = 'select * from category_filters where parent in (?)';
       $query = $db->prepare_query($query,$filter_cat_ids);
       $filters = $db->get_records($query);
       $base_query = 'select count(id) as num_products from products where ';
       if ($filters) foreach ($filter_cat_ids as $cat_id) {
          $where = '';
          foreach ($filters as $filter) {
             if ($filter['parent'] == $cat_id) {
                $field_values = explode('|',$filter['field_values']);
                $value_where = '';
                foreach ($field_values as $field_value) {
                   if ($value_where) $value_where .= ' or ';
                   $value_where .= '('.$filter['field_name'].'="' .
                                   $field_value.'")';
                }
                if ($value_where) {
                   if ($where) $where .= ' and ';
                   $where .= '('.$value_where.')';
                }
             }
          }
          if ($where) {
             $query = $base_query.$where;
             $row = $db->get_record($query);
             if ($row)
                $categories[$cat_id]['num_products'] = $row['num_products'];
          }
       }
    }
}

function get_subcategory_count($cat_id=null)
{
    global $category_off_sale_option;
    global $categories_table,$subcategories_table;
    global $website_id,$enable_multisite;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    if (! isset($enable_multisite)) $enable_multisite = false;
    if (! $cat_id) $cat_id = $this->id;
    if (! $cat_id) return 0;
    if (isset($this)) {
       $db = $this->db;
       $categories_table = $this->categories_table;
       $subcategories_table = $this->subcategories_table;
    }
    else {
       $db = new DB;
       if (! isset($categories_table)) $categories_table = 'categories';
       if (! isset($subcategories_table))
          $subcategories_table = 'subcategories';
    }
    $query = "select count(distinct s.id) as num_categories from " .
             $subcategories_table." s left join ".$categories_table." c on " .
             "c.id=s.related_id where s.parent=".$cat_id." and " .
             "(isnull(c.status) or (c.status!=".$category_off_sale_option."))";
    if ($enable_multisite && isset($website_id))
       $query .= ' and find_in_set("'.$website_id.'",c.websites)';
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','get_subcategory_count',
                                   1,$db);
    $row = $db->get_record($query);
    if (! $row) {
       process_error("Database Error: ".$db->error,-1);   return 0;
    }
    $num_categories = $row['num_categories'];
    return $num_categories;
}

function get_category_search_count($search_key)
{
    $query = str_replace("@key@",$search_key,
                         $this->category_search_count_query);
    $row = $this->db->get_record($query);
    if (! $row) {
       process_error("Database Error: ".$this->db->error,-1);   return 0;
    }
    $num_categories = $row['num_categories'];
    return $num_categories;
}

function load_search_categories($search_key)
{
    if (isset($this)) $db = $this->db;
    else $db = new DB;
    $query = str_replace("@key@",$search_key,$this->category_search_query);
    if ($this->category_search_order)
       $query .= " order by ".$this->category_search_order;
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_search_categories',
                                   1,$db);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,-1);
       return null;
    }
    $categories = array();
    while ($row = $db->fetch_assoc($result)) {
       if ((! isset($this)) || $this->index_by_id)
          $categories[$row['id']] = $row;
       else $categories[] = $row;
    }
    $db->free_result($result);

    return $categories;
}

function get_search_count($search_key)
{
    $query = str_replace("@key@",$search_key,$this->search_count_query);
    $row = $this->db->get_record($query);
    if (! $row) {
       if (isset($this->db->error))
          process_error("Database Error: ".$this->db->error,-1);
       return 0;
    }
    $num_products = $row['num_products'];
    return $num_products;
}

function load_search_products($search_key)
{
    global $use_dynamic_images,$enable_product_flags;

    ini_set('memory_limit','256M');
    $query = str_replace('@key@',$search_key,$this->search_query);
    if ($this->search_order) $query .= ' order by '.$this->search_order;
    if (isset($this->page_size,$this->page_number)) {
       $start_row = $this->page_number * $this->page_size;
       $query .= ' limit '.$start_row.','.$this->page_size;
    }
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_search_products',1,
                                   $this->db);
    $result = $this->db->query($query);
    if (! $result) {
       if (isset($this->db->error))
          process_error('Database Error: '.$this->db->error,-1);
       return null;
    }
    $this->products = array();   $ids = array();
    while ($row = $this->db->fetch_assoc($result)) {
       $id = $row['id'];
       if (! $id) continue;
       $row['inventory'] = array();
       $row['attributes'] = array();
       $row['images'] = array();
       $row['image_data'] = array();
       $row['num_images'] = 0;
       if ($this->shopping_cart &&
           ($this->features & (QTY_DISCOUNTS|QTY_PRICING)))
          $row['discounts'] = array();
       if ((! empty($enable_product_flags)) &&
           ((! empty($row['left_flag'])) || (! empty($row['right_flag'])))) {
          require_once 'admin/productflags-admin.php';
          load_product_flag_info($this->db,$row);
       }
       if ($this->index_by_id) $this->products[$id] = $row;
       else $this->products[] = $row;
       $ids[] = $id;
       if ($row['last_modified'] > $this->last_modified)
          $this->last_modified = $row['last_modified'];
    }
    $this->db->free_result($result);

    if (empty($ids)) return $this->products;

    if ($this->shopping_cart) {
       $query = 'select * from product_inventory where parent in (?) ' .
                'order by parent,sequence';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_search_products',
                                      2,$this->db);
       $query = $this->db->prepare_query($query,$ids);
       $rows = $this->db->get_records($query);
       if ($rows) {
          foreach ($rows as $row) {
             $id = $row['id'];   $parent = $row['parent'];
             if ($this->index_by_id)
                $this->products[$parent]['inventory'][$id] = $row;
             else {
                foreach ($this->products as $index => $product_info) {
                   if ($product_info['id'] == $parent)
                      $this->products[$index]['inventory'][$id] = $row;
                }
             }
          }
       }
       else if (isset($this->db->error)) {
          process_error('Database Error: '.$this->db->error,-1);
          return $this->products;
       }

       $first_attr = true;   $attr_ids = '';   $attr_array = array();
       $query = 'select a.*,p.parent from product_attributes p left join ' .
                'attributes a on a.id=p.related_id where p.parent in (?) ' .
                'order by parent,sequence';
       if (function_exists('custom_update_catalog_query'))
          custom_update_catalog_query($query,'category','load_search_products',
                                      3,$this->db);
       $query = $this->db->prepare_query($query,$ids);
       $rows = $this->db->get_records($query);
       if ($rows) {
          $last_parent = -1;
          foreach ($rows as $row) {
             $attr_id = $row['id'];
             if (! $attr_id) continue;
             $parent = $row['parent'];
             if ($parent != $last_parent) {
                $index = 0;   $last_parent = $parent;
             }
             $row['order_id'] = $index;
             if ($this->index_by_id)
                $this->products[$parent]['attributes'][$attr_id] = $row;
             else {
                foreach ($this->products as $index => $product_info) {
                   if ($product_info['id'] == $parent)
                      $this->products[$index]['attributes'][$attr_id] = $row;
                }
             }
             if (! in_array($attr_id,$attr_array)) {
                if ($first_attr) $first_attr = false;
                else $attr_ids .= ',';
                $attr_ids .= $attr_id;
                $attr_array[] = $attr_id;
             }
             $index++;
          }
       }
       else if (isset($this->db->error)) {
          process_error('Database Error: '.$this->db->error,-1);
          return $this->products;
       }

       if ($attr_ids == '') $this->attribute_options = null;
       else {
          $this->attribute_options = array();
          $query = 'select o.*,(select filename from images where parent_type=' .
                   $this->products_image_type.' and parent=o.id limit 1)' .
                   ' as image from attribute_options o where o.parent in (' .
                   $attr_ids.') order by o.parent,o.sequence';
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'category',
                                         'load_search_products',4,$this->db);
          $result = $this->db->query($query);
          if ($result) {
             while ($row = $this->db->fetch_assoc($result)) {
                if (! isset($this->attribute_options[$row['parent']]))
                   $this->attribute_options[$row['parent']] = array();
                $this->attribute_options[$row['parent']][$row['id']] = $row;
             }
             $this->db->free_result($result);
          }
          else if (isset($this->db->error)) {
             process_error('Database Error: '.$this->db->error,-1);
             return $this->products;
          }
       }

       if ($this->shopping_cart &&
           ($this->features & (QTY_DISCOUNTS|QTY_PRICING))) {
          if ($this->wholesale) $discount_type = 1;
          else $discount_type = 0;
          $query = 'select * from product_discounts where parent in (?) ' .
                   'and discount_type=? order by start_qty,end_qty';
          if (function_exists('custom_update_catalog_query'))
             custom_update_catalog_query($query,'category','load_products',6,
                                         $this->db);
          $query = $this->db->prepare_query($query,$ids,$discount_type);
          $rows = $this->db->get_records($query);
          if ($rows) {
             foreach ($rows as $row) {
                $parent = $row['parent'];
                if ($this->index_by_id)
                   $this->products[$parent]['discounts'][$row['id']] = $row;
                else {
                   foreach ($this->products as $index => $product_info) {
                      if ($product_info['id'] == $parent)
                         $this->products[$index]['discounts'][$row['id']] = $row;
                   }
                }
             }
          }
          else if (isset($this->db->error)) {
             if ($this->ajax_call) http_response(422,$this->db->error);
             else process_error('Database Error: '.$this->db->error,-1);
          }
       }
    }

    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $query = 'select * from images where parent_type=? and parent in (?) ' .
             'order by parent,sequence,id';
    if (function_exists('custom_update_catalog_query'))
       custom_update_catalog_query($query,'category','load_search_products',5,
                                   $this->db);
    $query = $this->db->prepare_query($query,$this->products_image_type,$ids);
    $rows = $this->db->get_records($query);
    if ($rows) {
       foreach ($rows as $row) {
          $row['dynamic'] = $use_dynamic_images;
          $filename = $row['filename'];   $parent = $row['parent'];
          if ($this->index_by_id) {
             $this->products[$parent]['images'][$filename] =
                strip_tags($row['caption']);
             $this->products[$parent]['image_data'][$filename] = $row;
             $this->products[$parent]['num_images'] =
                count($this->products[$parent]['images']);
          }
          else {
             foreach ($this->products as $index => $product_info) {
                if ($product_info['id'] == $parent) {
                   $this->products[$index]['images'][$filename] =
                      strip_tags($row['caption']);
                   $this->products[$index]['image_data'][$filename] = $row;
                   $this->products[$index]['num_images'] =
                      count($this->products[$index]['images']);
                }
             }
          }
       }
    }
    else if (isset($this->db->error)) {
       process_error('Database Error: '.$this->db->error,-1);
    }

    if ($this->wholesale) $this->get_discount_prices();
    return $this->products;
}

function end_page()
{
    if (isset($this->conteg)) $this->show_conteg();
}

};

function get_top_category()
{
    global $top_category,$enable_multisite,$website_id;

    if (! empty($enable_multisite)) {
       $db = new DB;
       if (! empty($website_id)) {
          $query = 'select top_category from web_sites where id=?';
          $query = $db->prepare_query($query,$website_id);
       }
       else {
          $dir = basename(getcwd());
          if ($dir == 'public_html') $dir = '/';
          else $dir = '/'.$dir.'/';
          $relative_dir = substr($dir,1);
          $query = 'select top_category from web_sites where (rootdir=?) ' .
                   'or (rootdir=?)';
          $query = $db->prepare_query($query,$dir,$relative_dir);
       }
       $row = $db->get_record($query);
       if (! empty($row['top_category'])) return $row['top_category'];
       return $top_category;
    }
    else return $top_category;
}

function load_featured_products($page_size=null,$page_number=null)
{
    global $products_table,$products_image_type,$shopping_cart;
    global $use_dynamic_images,$enable_product_flags;

    $db = new DB;
    if (! isset($products_table)) $products_table = "products";
    if (! isset($products_image_type)) $products_image_type = 1;
    $query = "select * from ".$products_table." where (flags&1) order by name";
    if (($page_size !== null) && ($page_number !== null)) {
       $start_row = $page_number * $page_size;
       $query .= " limit ".$start_row.",".$page_size;
    }
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_featured_products',
                                   1,$db);
    $result = $db->query($query);
    if (! $result) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,-1);
       return null;
    }
    $products = array();   $first_field = true;   $ids = '';
    while ($row = $db->fetch_assoc($result)) {
       $id = $row['id'];
       if (! $id) continue;
       if ((! empty($enable_product_flags)) &&
           ((! empty($row['left_flag'])) || (! empty($row['right_flag'])))) {
          require_once 'admin/productflags-admin.php';
          load_product_flag_info($db,$row);
       }
       $products[$id] = $row;
       if ($first_field) $first_field = false;
       else $ids .= ',';
       $ids .= $id;
       $products[$id]['inventory'] = array();
       $products[$id]['images'] = array();
       $products[$id]['image_data'] = array();
       $products[$id]['num_images'] = 0;
    }
    $db->free_result($result);

    if ($ids == '') return $products;

    if ($shopping_cart) {
       $query = "select * from product_inventory where parent in (".$ids .
                ") order by parent,sequence";
       if (function_exists("custom_update_catalog_query"))
          custom_update_catalog_query($query,'category',
                                      'load_featured_products',2,$db);
       $result = $db->query($query);
       if ($result) {
          while ($row = $db->fetch_assoc($result)) {
             $id = $row['id'];   $parent = $row['parent'];
             $products[$parent]['inventory'][$id] = $row;
          }
          $db->free_result($result);
       }
       else if (isset($db->error)) {
          process_error("Database Error: ".$db->error,-1);
          return $products;
       }
    }

    if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    $query = "select * from images where parent_type=" .
             $products_image_type." and parent in (" .
             $ids.") order by parent,sequence,id";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_featured_products',
                                   3,$db);
    $result = $db->query($query);
    if ($result) {
       while ($row = $db->fetch_assoc($result)) {
          $row['dynamic'] = $use_dynamic_images;
          $filename = $row['filename'];   $parent = $row['parent'];
          $products[$parent]['images'][$filename] =
             strip_tags($row['caption']);
          $products[$parent]['image_data'][$filename] = $row;
          $products[$parent]['num_images'] =
             count($products[$parent]['images']);
       }
       $db->free_result($result);
    }
    else if (isset($db->error)) {
       process_error("Database Error: ".$db->error,-1);
    }

    return $products;
}

function find_subcategories($id,&$categories,&$subcategories,&$ids)
{
    if (in_array($id,$ids)) return null;
    if (! isset($categories[$id])) return null;
    $ids[] = $id;
    $category_info = $categories[$id];
    $sub_array = array();
    $found_parent = false;   $subcategory_array = $subcategories;
    foreach ($subcategory_array as $subcategory) {
       if ($subcategory['parent'] == $id) {
          $sub_record = find_subcategories($subcategory['related_id'],$categories,
                                           $subcategories,$ids);
          if ($sub_record) $sub_array[] = $sub_record;
          $found_parent = true;
       }
       else if ($found_parent) break;
    }
    if ($found_parent) $category_info['subcategories'] = $sub_array;
    return $category_info;
}

function load_category_tree($db = null)
{
    global $top_category,$category_off_sale_option;
    global $categories_table,$subcategories_table;

    if (! $db) $db = new DB;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;
    if (! isset($categories_table)) $categories_table = 'categories';
    if (! isset($subcategories_table)) $subcategories_table = 'subcategories';
    $query = "select * from ".$categories_table." where (isnull(status) or " .
             "(status!=".$category_off_sale_option."))";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_category_tree',
                                   1,$db);
    $categories = $db->get_records($query,'id');
    if (! $categories) return null;
    $query = "select * from ".$subcategories_table." order by parent,sequence";
    if (function_exists("custom_update_catalog_query"))
       custom_update_catalog_query($query,'category','load_category_tree',
                                   2,$db);
    $subcategories = $db->get_records($query,'id');
    if (! $subcategories) return null;

    $category_tree = array();
    $ids = array();
    $category_tree = find_subcategories($top_category,$categories,
                                        $subcategories,$ids);

    return $category_tree;
}

function load_category_info($id)
{
    global $category;

    if (isset($category)) return $category->load_info($id);
    else return @Category::load_info($id);
}

function load_subcategory_info($id)
{
    global $category;

    if (isset($category)) return $category->load_subcategory_info($id);
    else return @Category::load_subcategory_info($id);
}

function load_subcategories($id)
{
    global $category;

    if (isset($category)) return $category->load_subcategories($id);
    else return @Category::load_subcategories($id);
}

$ajaxcmd = get_form_field("ajaxcmd");

if ($ajaxcmd == "loadproducts") {
   $category = new Category;
   $category->ajax_load_category_products();
   DB::close_all();
}

?>
