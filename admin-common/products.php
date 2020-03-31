<?php
/*
                       Inroads Shopping Cart - Products Tab

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

if (isset($argc) && ($argc > 1)) $bg_command = $argv[1];
else $bg_command = null;

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'image.php';
require_once 'sublist.php';
require_once 'utility.php';
require_once 'seo.php';
require_once 'catalogconfig-common.php';
if (file_exists("../cartengine/adminperms.php")) {
   $shopping_cart = true;
   require_once 'cartconfig-common.php';
   require_once 'inventory.php';
   $features = get_cart_config_value('features');
   if ($features === '') $features = 0;
   if ($features & QTY_DISCOUNTS) $use_discounts = true;
   else $use_discounts = false;
   if ($features & QTY_PRICING) $use_qty_pricing = true;
   else $use_qty_pricing = false;
   if ($features & REGULAR_PRICE_BREAKS) $use_price_breaks = true;
   else $use_price_breaks = false;
}
else {
   $shopping_cart = false;
   require_once 'catalog-common.php';
   $features = $catalog_features;
   $use_discounts = false;
   $use_qty_pricing = false;
   $use_price_breaks = false;
}
require_once 'products-common.php';
if ($shopping_cart) require_once 'inventory-common.php';
if ($use_discounts || $use_qty_pricing) require_once 'discounts.php';
if ($use_price_breaks) require_once 'pricebreak.php';

define("DOWNLOADS_DATA_TYPE",1);

if ($bg_command) $default_base_href = $ssl_url;
else $default_base_href = get_current_url();

if (! isset($name_prompt)) $name_prompt = $product_label.' Name';
if (! isset($name_col_width)) $name_col_width = 250;
if (! isset($use_display_name)) $use_display_name = true;
if (! isset($product_fields)) $product_fields = array();
if (! isset($enable_vendors)) $enable_vendors = false;
if (! isset($enable_reviews)) $enable_reviews = false;
if (! isset($enable_product_callouts)) $enable_product_callouts = false;
if (! isset($use_callout_groups)) $use_callout_groups = false;
if (! isset($enable_product_flags)) $enable_product_flags = false;
if (! isset($enable_popular_products)) $enable_popular_products = false;
if (! isset($product_tabs)) {
   $product_tabs = array('product' => true,'image' => true,'attributes' => true,
                         'inventory' => true,'qtydiscounts' => false,
                         'pricebreaks' => false,'categories' => true,
                         'seo' => true,'activity' => true,'reviews' => false);
   if ($use_price_breaks) $product_tabs['pricebreaks'] = true;
   if ($enable_reviews) $product_tabs['reviews'] = true;
}
if ($shopping_cart && ($features & QTY_DISCOUNTS|QTY_PRICING))
   $product_tabs['qtydiscounts'] = true;
if ($features & (SUB_PRODUCT_COLLECTION|SUB_PRODUCT_RELATED))
   $product_tabs['subproducts'] = true;
if (! isset($category_label)) $category_label = "Category";
if (! isset($categories_label)) $categories_label = "Categories";
if (! empty($enable_gift_certificates)) {
   if (! isset($product_tabs['specs'])) $product_tabs['specs'] = true;
   if (! isset($product_types))
      $product_types = array(0=>'Standard Product',100=>'Gift Certificate');
}

$script_name = basename($_SERVER['PHP_SELF']);

$product_tab_labels = array();
$product_tab_order = array();

function add_script_prefix(&$screen,$dialog_title)
{
    global $shopping_cart,$admin_path,$cms_base_url,$use_dynamic_images;
    global $image_subdir_prefix,$sample_image_size,$dynamic_image_url;
    global $features,$enable_multisite;
    global $enable_inventory_available;

    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart) {
       $head_block .= "      shopping_cart = true;\n";
       $head_block .= "      script_prefix='../cartengine/';\n";
    }
    $head_block .= "      admin_path = '".$admin_path."';\n";
    if (get_form_field("insidecms")) {
       $head_block .= "      inside_cms = true;\n";
       if ($dialog_title)
          $head_block .= "      dialog_title = '".$dialog_title."';\n";
       $screen->use_cms_top();
    }
    if (isset($cms_base_url))
       $head_block .= "      cms_url = '".$cms_base_url."';\n";
    $head_block .= "      image_dir = '/images';\n";
    if (isset($use_dynamic_images) && $use_dynamic_images)
       $head_block .= "      dynamic_images = true;\n";
    if (isset($image_subdir_prefix) && $image_subdir_prefix)
       $head_block .= "       image_subdir_prefix = " .
                      $image_subdir_prefix.";\n";
    if (isset($dynamic_image_url))
       $head_block .= "      dynamic_image_url = '".$dynamic_image_url."';\n";
    if (isset($sample_image_size))
       $head_block .= "      sample_image_size = '".$sample_image_size."';\n";
    $head_block .= "      features = ".$features.";\n";
    if ($features & INVENTORY_BACKORDERS)
       $head_block .= '      enable_inventory_backorder = true;'."\n";
    if (! empty($enable_inventory_available))
       $head_block .= '      enable_inventory_available = true;'."\n";
    if (! empty($enable_multisite))
       $head_block .= '      enable_multisite = true;'."\n";
    $head_block .= "    </script>";
    $screen->add_head_line($head_block);
}

function add_product_styles(&$dialog,$db=null)
{
    $style = "<style type=\"text/css\">\n";
    $style .= "      input.checkbox { padding-left:0px; padding-top:0px; margin-left:0px;\n" .
              "                       margin-top:0px; width:14px; height:14px; }\n";
    if (function_exists('add_custom_product_dialog_styles'))
       $style .= add_custom_product_dialog_styles();
    $style .= "    </style>";
    $dialog->add_head_line($style);
}

function add_product_variables(&$dialog,$db=null)
{
    global $name_prompt,$use_display_name,$product_label,$products_label;
    global $script_name,$categories_script_name,$products_matching_script_name,$cache_catalog_pages;
    global $url_prefix,$base_url,$use_callout_groups,$related_types;
    global $shopping_cart,$shopping_modules;

    $script = "<script type=\"text/javascript\">\n";
    $script .= "      name_prompt = '".$name_prompt."';\n";
    $script .= "      product_label = '".$product_label."';\n";
    $script .= "      products_label = '".$products_label."';\n";
    if (! $use_display_name) $script .= "      use_display_name = false;\n";
    $script .= "      if (typeof(top.current_tab) != \"undefined\")\n";
    $script .= "         product_dialog_height = top.get_content_frame()." .
               "product_dialog_height;\n";
    $script .= "      else product_dialog_height = 500;\n";
    $script .= "      script_name = '".$script_name."';\n";
    $script .= "      categories_script_name = '".$categories_script_name."';\n";
    $script .= "      products_matching_script_name = '".$products_matching_script_name."';\n";
    if (! empty($cache_catalog_pages))
       $script .= "      cache_catalog_pages = true;\n";
    $script .= "      url_prefix = '".$url_prefix."';\n";
    $default_base_href = get_current_url();
    $script .= "      default_base_href = '".$default_base_href."';\n";
    $script .= "      base_url = '".$base_url."';\n";
    if ($use_callout_groups) {
       $script .= "      use_callout_groups = true;\n";
       $query = 'select * from callout_groups';
       $callout_groups = $db->get_records($query);
       if ($callout_groups) {
          $script .= '      callout_groups = {';
          $first_group = true;
          foreach ($callout_groups as $group) {
             $name = str_replace('"',"\\\"",$group['name']);
             if ($first_group) $first_group = false;
             else $script .= ',';
             $script .= $group['id'].':"'.$name.'"';
          }
          $script .= "};\n";
       }
    }
    if (isset($related_types)) {
       reset($related_types);   $first_type = true;
       $script .= '      related_types = [';
       while (list($related_type,$label) = each($related_types)) {
          if ($first_type) $first_type = false;
          else $script .= ',';
          $script .= $related_type;
       }
       $script .= "];\n";
    }
    if ($shopping_cart && (! empty($shopping_modules))) {
       $script .= '      shopping_modules = [';   $first_module = true;
       foreach ($shopping_modules as $module) {
          if ($first_module) $first_module = false;
          else $script .= ',';
          $script .= '\''.$module.'\'';
       }
       $script .= "];\n";
    }
    if (function_exists("add_custom_product_dialog_variables"))
       $script .= add_custom_product_dialog_variables();
    $script .= "    </script>";
    $dialog->add_head_line($script);
}

function add_update_function(&$dialog,$id)
{
    $sublist = get_form_field("sublist");
    if (! $sublist) return;
    $update_window = get_form_field("updatewindow");
    $frame_name = get_form_field("frame");
    $side = get_form_field("side");
    if (! $id) $id = 'null';
    $script = "<script>\n" .
              "      update_window = '".$frame_name."';\n" .
              "      function update_sublist() {\n" .
              "         var iframe = top.get_dialog_frame('".$update_window."').contentWindow;\n" .
              "         iframe.".$sublist.".update('".$side."',".$id.");\n" .
              "      }\n" .
              "    </script>";
    $dialog->add_head_line($script);
}

function add_product_filter_row($screen,$prompt,$field_name,$data,$use_index,
                                $all_label=null,$all_value='')
{
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write($prompt.":");
    if ($screen->skin) $screen->write("</span>");
    else $screen->write("<br>\n");
    $screen->write("<select name=\"".$field_name."\" id=\"".$field_name."\" " .
                   "onChange=\"filter_products();\" " .
                   "class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    if (! $all_label) $all_label = 'All '.$prompt.'s';
    $screen->add_list_item($all_value,$all_label,false);
    if ($use_index) {
       while (list($index,$value) = each($data))
          $screen->add_list_item($index,$value,false);
    }
    else foreach ($data as $value) $screen->add_list_item($value,$value,false);
    $screen->end_choicelist();
    if ($screen->skin) $screen->write("</div>");
    else $screen->write("</td></tr>\n");
}

function add_product_filters($screen,$status_values,$db)
{
    global $enable_vendors,$product_types;

    if (isset($product_types))
       add_product_filter_row($screen,'Product Type','product_type',
                              $product_types,true,'All');
    add_product_filter_row($screen,'Status','status',$status_values,
                           true,'All');
    if ($enable_vendors) {
       $query = 'select id,name from vendors order by name';
       $vendors = $db->get_records($query,'id','name');
       if ($vendors) {
          $vendors = array('~'=>'Unassigned') + $vendors;
          add_product_filter_row($screen,'Vendor','vendor',$vendors,true);
       }
    }
    if (function_exists('add_custom_product_filters'))
       add_custom_product_filters($screen,$db);
}

function display_products_screen()
{
    global $product_fields,$name_prompt,$name_col_width,$desc_col_width;
    global $product_label,$products_label,$products_table,$features;
    global $script_name,$cache_catalog_pages,$shopping_cart;
    global $product_types,$enable_reviews,$shopping_modules;
    global $enable_vendors,$product_dialog_width,$product_tab_labels;

    if ($shopping_cart) load_shopping_modules();
    if (! isset($desc_col_width)) $desc_col_width = 350;
    $num_product_prices = 0;   $num_inventory_prices = 0;
    if ($features & REGULAR_PRICE_PRODUCT) $num_product_prices++;
    if ($features & LIST_PRICE_PRODUCT) $num_product_prices++;
    if ($features & SALE_PRICE_PRODUCT) $num_product_prices++;
    if ($features & PRODUCT_COST_PRODUCT) $num_product_prices++;
    if ($features & REGULAR_PRICE_INVENTORY) $num_inventory_prices++;
    if ($features & LIST_PRICE_INVENTORY) $num_inventory_prices++;
    if ($features & SALE_PRICE_INVENTORY) $num_inventory_prices++;
    if ($features & PRODUCT_COST_INVENTORY) $num_inventory_prices++;
    $db = new DB;
    $status_values = load_cart_options(PRODUCT_STATUS,$db);
    if (isset($product_dialog_width)) $dialog_width = $product_dialog_width;
    else {
       init_product_tabs(null,-1);
       $dialog_width = 270;
       foreach ($product_tab_labels as $tab_label)
          $dialog_width += (8 * strlen($tab_label)) + 32;
    }

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('products.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('products.js');
    $screen->add_script_file('product_matching.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    if (function_exists('custom_show_product_buttons'))
       $show_buttons = custom_show_product_buttons($db);
    else $show_buttons = true;
    $script = "<script type=\"text/javascript\">\n";
    $script .= '       var use_part_numbers = ';
    if ($features & USE_PART_NUMBERS) $script .= 'true';
    else $script .= 'false';
    $script .= ";\n";
    if ($shopping_cart) $script .= "       var include_taxable = true;\n";
    $script .= "       products_table = '".$products_table."';\n";
    $script .= "       script_name = '".$script_name."';\n";
    if (! $show_buttons) $script .= "       show_buttons = false;\n";
    $script .= '    </script>';
    $screen->add_head_line($script);
    add_script_prefix($screen,null);
    add_website_js_array($screen,$db);
    $styles = "<style type=\"text/css\">\n";
    $styles .= "      #products_grid .aw-column-3 { text-align: center; }\n";
    $status_column = 5;
    if (isset($product_types)) {
       $styles .= "      #products_grid .aw-column-4 { text-align: center; }\n";
       $status_column++;
    }
    if ($enable_vendors) $status_column++;
    $styles .= '      #products_grid .aw-column-'.$status_column .
               " { text-align: center; }\n";
    if ($shopping_cart)
       $styles .= '      #products_grid .aw-column-'.($status_column + 1) .
                  " { text-align: center; }\n";
    $styles .= '    </style>';
    $screen->add_head_line($styles);
    if (function_exists('custom_init_product_screen'))
       custom_init_product_screen($screen);
    $screen->set_body_id('products');
    $screen->set_help('products');
    require_once '../engine/modules.php';
    call_module_event('update_head',array($products_table,&$screen,$db));
    $screen->start_body(filemtime($script_name));
    if ($screen->skin) {
       $screen->start_title_bar($products_label);
       $screen->start_title_filters();
       add_product_filters($screen,$status_values,$db);
       add_search_box($screen,'search_products','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    if ($show_buttons) {
       $screen->set_button_width(148);
       if (function_exists('custom_start_product_buttons'))
          custom_start_product_buttons($screen);
       $screen->start_button_column();
       $screen->add_button('Add '.$product_label,'images/AddProduct.png',
                           'add_product();',null,true,false,ADD_BUTTON);
       $screen->add_button('Edit '.$product_label,'images/EditProduct.png',
                           'edit_product();',null,true,false,EDIT_BUTTON);
       $screen->add_button('Copy '.$product_label,'images/EditProduct.png',
                           'copy_product();',null,true,false,EDIT_BUTTON);
       $screen->add_button('Delete '.$products_label,'images/DeleteProduct.png',
                           'delete_product();',null,true,false,DELETE_BUTTON);
       $screen->add_button('View '.$product_label,'images/AdminUsers.png',
                           'view_product();');
       $screen->add_button('Multiple Edit','images/AdminUsers.png',
                           'edit_multiple_products();');
       $screen->add_button('Change Status','images/AdminUsers.png',
                           'change_product_status();');
       if (($num_product_prices > 1) || ($num_inventory_prices > 1))
          $screen->add_button('Change Prices','images/AdminUsers.png',
                              'change_prices();');
       if ($shopping_cart && (count($shopping_modules) > 0)) 
          $screen->add_button('Shopping Publish','images/AdminUsers.png',
                              'change_shopping_publish();');
       if (! empty($cache_catalog_pages))
          $screen->add_button('Rebuild Cache','images/AdminUsers.png',
                              'rebuild_product_cache();');
       if ($shopping_cart) {
          $screen->add_button('Export Inventory','images/ImportData.png',
                              'export_inventory();');
          $screen->add_button('Import Inventory','images/ImportData.png',
                              'import_inventory();');
       }
        if ($enable_vendors) {
            $screen->add_button('Match Products','images/AdminUsers.png',
                'match_products_dialog();');
        }
       if (function_exists('display_custom_product_buttons'))
          display_custom_product_buttons($screen,$db);
       call_module_event('display_custom_buttons',
                         array($products_table,&$screen,$db));
       if (! $screen->skin) {
          add_product_filters($screen,$status_values,$db);
          add_search_box($screen,'search_products','reset_search');
       }
       $screen->end_button_column();
    }
    else {
       $screen->set_button_width(0);
       $screen->start_button_column();
       $screen->end_button_column();
    }

    $first_field = true;   reset($product_fields);
    $field_names = '';   $col_names = '';  $col_widths = '';
    while (list($field_name,$field) = each($product_fields)) {
       if (isset($field['columnheader'])) {
          if ($first_field) $first_field = false;
          else {
             $field_names .= ',';   $col_names .= ',';   $col_widths .= ',';
          }
          $field_names .= "'".$field_name."'";
          $col_names .= "'".$field['columnheader']."'";
          $col_widths .= $field['columnwidth'];
       }
    }
    $product_dialog_height = get_product_screen_height($db);
    $screen->write("\n          <script>\n");
    $screen->write('             var field_names = ['.$field_names."];\n");
    $screen->write('             var field_col_names = ['.$col_names."];\n");
    $screen->write('             var field_col_widths = ['.$col_widths."];\n");
    $screen->write('             var product_dialog_height = '.$product_dialog_height.";\n");
    $screen->write('             var product_dialog_width = '.$dialog_width.";\n");
    $screen->write('             var product_status_values = [');
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $screen->write(',');
       if (isset($status_values[$loop]))
          $screen->write("\"".$status_values[$loop]."\"");
       else $screen->write("\"\"");
    }
    $screen->write("];\n");
    if (isset($product_types)) {
       $screen->write("             var product_types = [];\n");
       reset($product_types);
       while (list($index,$label) = each($product_types))
          $screen->write("             product_types[".$index."] = \"" .
                         $label."\";\n");
       $desc_col_width -= 75;
    }
    if ($enable_vendors) 
       $screen->write("             enable_vendors = true;\n");
/*
    if ($enable_vendors) {
       $screen->write("             var vendors = [];\n");
       $query = 'select * from vendors order by id';
       $result = $db->query($query);
       if ($result) {
          while ($vendor_row = $db->fetch_assoc($result))
             $screen->write('             vendors['.$vendor_row['id']."] = \"" .
                            $vendor_row['name']."\";\n");
          $db->free_result($result);
       }
    }
*/
    $screen->write("             var name_prompt = '".$name_prompt."';\n");
    $screen->write("             var product_label = '".$product_label."';\n");
    $screen->write("             var products_label = '".$products_label."';\n");
    $screen->write("             var name_col_width = ".$name_col_width.";\n");
    $screen->write("             var desc_col_width = ".$desc_col_width.";\n");
    if (function_exists('write_custom_product_variables'))
       write_custom_product_variables($screen,$db);
    $screen->write("             load_grid(true);\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function add_product_tab($new_tab,$label,$before_order=null)
{
    global $product_tab_labels,$product_tab_order;

    $product_tab_labels[$new_tab] = $label;
    if ($before_order == null) $product_tab_order[] = $new_tab;
    else {
       reset($product_tab_order);   $insert_pos = -1;
       while (list($index,$tab_name) = each($product_tab_order))
          if ($tab_name == $before_order) {
             $insert_pos = $index;   break;
          }
       if ($insert_pos == -1) $product_tab_order[] = $new_tab;
       else array_splice($product_tab_order,$insert_pos,0,array($new_tab));
    }
}

function remove_product_tab($tab)
{
    global $product_tab_labels,$product_tab_order;

    unset($product_tab_labels[$tab]);
    reset($product_tab_order);
    while (list($index,$tab_name) = each($product_tab_order))
       if ($tab_name == $tab) {
          unset($product_tab_order[$index]);   break;
       }
}

function init_product_tabs($row,$edit_type)
{
    global $product_tabs,$shopping_cart,$use_price_breaks,$features;
    global $product_label,$products_label,$categories_label,$use_discounts;
    global $shopping_feeds_enabled,$enable_popular_products,$related_types;
    global $related_tab,$shopping_modules,$use_qty_pricing;

    if ($shopping_cart && empty($shopping_feeds_enabled))
       $shopping_feeds_enabled = shopping_modules_installed();
    if ($product_tabs['product']) add_product_tab('product',$product_label);
    if (isset($product_tabs['specs']) && $product_tabs['specs'])
       add_product_tab('specs','Specs');
    if (! empty($shopping_feeds_enabled))
       add_product_tab('shopping','Shopping');
    if ($product_tabs['image']) add_product_tab('image','Images');
    if ($shopping_cart && $product_tabs['attributes'])
       add_product_tab('attributes','Attributes');
    if ($shopping_cart && $product_tabs['inventory'])
       add_product_tab('inventory','Inventory');
    if ($use_discounts && $product_tabs['qtydiscounts'])
       add_product_tab('qtydiscounts','Qty Discounts');
    if ($use_qty_pricing && $product_tabs['qtydiscounts'])
       add_product_tab('qtydiscounts','Qty Prices');
    if ($use_price_breaks && $product_tabs['pricebreaks'])
       add_product_tab('pricebreaks','Price Breaks');
    if ($product_tabs['categories']) add_product_tab('categories',$categories_label);
    if (isset($related_types)) {
       reset($related_types);
       while (list($related_type,$label) = each($related_types)) {
          if (isset($related_tab) && ($related_type != 0) &&
              in_array($related_type,$related_tab)) continue;
          add_product_tab('related_'.$related_type,$label);
       }
    }
    if ($product_tabs['seo']) add_product_tab('seo','SEO');
    if ((! isset($product_tabs['activity'])) || $product_tabs['activity'])
       add_product_tab('activity','Activity');
    if (isset($product_tabs['reviews']) && $product_tabs['reviews'])
       add_product_tab('reviews','Reviews');
    if ($enable_popular_products && ($edit_type != ADDRECORD))
       add_product_tab('popular','Popular');
    if (function_exists('setup_product_tabs')) setup_product_tabs($row);
}

function write_start_hidden_product_fields($dialog,$edit_type,$id,$row,$db)
{
    global $cache_catalog_pages,$cloudflare_site,$shopping_cart;

    $dialog->add_hidden_field('Start','$Start$');
    $dialog->add_hidden_field("id",$id);
    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field("old_flags",get_row_value($row,'flags'));
       $dialog->add_hidden_field("old_seo_url",get_row_value($row,'seo_url'));
       $dialog->add_hidden_field('old_websites',get_row_value($row,'websites'));
       if (((! empty($cache_catalog_pages)) ||
            isset($cloudflare_site)) && $id) {
          $query = 'select parent from category_products where related_id=?';
          $query = $db->prepare_query($query,$id);
          $result = $db->query($query);
          if ($result) {
             $categories = '';
             while ($row = $db->fetch_assoc($result)) {
                if ($categories != '') $categories .= ',';
                $categories .= $row['parent'];
             }
             $db->free_result($result);
             if ($categories) $dialog->add_hidden_field("categories",$categories);
          }
       }
    }
    else {
       $order_frame = get_form_field('OrderFrame');
       if ($order_frame) $dialog->add_hidden_field('OrderFrame',$order_frame);
    }
    if ($shopping_cart)
       call_shopping_event('add_hidden_product_fields',
                           array(&$dialog,$edit_type,$row,$db));

}

function write_inventory_field($dialog,$field_value,&$first_field)
{
    if ($first_field) $first_field = false;
    else $dialog->write(",");
    $dialog->write($field_value);
}

function display_product_fields($dialog,$edit_type,$id,$row,$db=null)
{
    global $name_prompt,$name_col_width,$price_prompt,$use_display_name;
    global $product_fields,$inventory_fields,$default_base_href,$base_url;
    global $desc_field_type,$default_price_break_type;
    global $hide_featured_product_flag,$product_label,$products_label;
    global $category_label,$categories_label,$shopping_cart,$features;
    global $product_tab_labels,$product_tab_order,$long_description_height;
    global $products_table,$script_name,$image_parent_type;
    global $include_product_downloads,$product_types,$shopping_feeds_enabled;
    global $prefix,$part_number_prompt,$enable_multisite;
    global $audio_dir,$part_number_size,$enable_gift_certificates;
    global $categories_table,$category_products_table,$enable_vendors;
    global $enable_reorders,$use_cached_dialogs,$enable_product_callouts;
    global $use_callout_groups,$disable_catalog_config,$enable_product_flags;
    global $enable_designer,$related_types,$related_tab,$shopping_modules;
    global $max_shopping_flag,$enable_downloadable_products;
    global $downloadable_products_dir,$on_account_products,$enable_wholesale;
    global $enable_linked_inventory,$enable_inventory_available;

    if (! $db) $db = new DB;
    if (! isset($price_prompt)) $price_prompt = 'List Price';
    if (! isset($desc_field_type)) $desc_field_type = HTMLEDIT_FIELD;
    if (! isset($default_price_break_type)) $default_price_break_type = 0;
    if (! isset($hide_featured_product_flag)) $hide_featured_product_flag = false;
    if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';
    if (! isset($part_number_size)) $part_number_size = 80;
    if (! isset($enable_reorders)) $enable_reorders = false;
    if (! isset($use_cached_dialogs)) $use_cached_dialogs = false;
    if (! isset($disable_catalog_config)) $disable_catalog_config = false;
    if (! isset($enable_designer)) $enable_designer = false;
    $status_values = load_cart_options(PRODUCT_STATUS);
    if ($shopping_cart) load_shopping_modules();

    $dialog->start_tab_section('prod_tab_section');
    $dialog->start_tab_row('product_tab','product_content');
    reset($product_tab_order);
    end($product_tab_order);   $last_tab = key($product_tab_order);
    reset($product_tab_order);   $first_tab = true;
    $using_more = false;
    $avail_space = intval(get_form_field('window_width')) - 243;
    $tab_space = 0;
    while (list($index,$tab_name) = each($product_tab_order)) {
       $tab_sequence = 0;
       if ($first_tab) {
          $tab_sequence |= FIRST_TAB;   $first_tab = false;
       }
       if ($index == $last_tab) $tab_sequence |= LAST_TAB;
       if ($product_tab_labels[$tab_name]{0} == '~') {
          $tab_label = substr($product_tab_labels[$tab_name],1);
          $visible = false;
       }
       else {
          $tab_label = $product_tab_labels[$tab_name];   $visible = true;
       }
       $tab_width = round(8.5 * strlen($tab_label)) + 32;

       if ($index == $last_tab) $compare_width = $tab_width;
       else $compare_width = $tab_width + 73;
       if ((! $using_more) && ($tab_space + $compare_width > $avail_space)) {
          $dialog->add_tab('more_tab','More',null,null,null,true,null,LAST_TAB,true);
          $dialog->start_tab_menu();
          $using_more = true;
       }
       $dialog->add_tab($tab_name.'_tab',$tab_label,'prod_'.$tab_name.'_tab',
                        $tab_name.'_content','change_tab',$visible,null,
                        $tab_sequence);
       $tab_space += $tab_width;
    }
    if ($using_more) {
       $dialog->end_tab_menu();   $dialog->end_tab();
    }
    $dialog->end_tab_row('prod_tab_row_middle');

    if (isset($product_tab_labels['product'])) {
       $flags = get_row_value($row,'flags');
       $dialog->start_tab_content('product_content',true);
       $dialog->write("<div style=\"position: relative;\">\n");
       add_base_href($dialog,$base_url,true);
       if ($edit_type == UPDATERECORD) {
          $dialog->write('<a id="view_link" class="view_link" href="#" ' .
                         'onClick="view_product_link(); return false;">' .
                         'View</a>'."\n");
          if ($use_cached_dialogs && (! get_form_field('insidecms')) &&
              (! get_form_field('frame'))) {
             $dialog->write("<a id=\"previous_link\" class=\"previous_link\" " .
                            "onClick=\"return previous_product();\" href=\"#\">" .
                            "<< Previous Product</a>\n");
             $dialog->write("<a id=\"next_link\" class=\"next_link\" " .
                            "onClick=\"return next_product();\" href=\"#\">" .
                            "Next Product >></a>\n");
          }
       }
       $dialog->start_field_table('product_table');
       $dialog->add_edit_row($name_prompt.':','name',$row,50);
       if ($use_display_name)
          $dialog->add_edit_row('Display Name:','display_name',$row,50);
       $dialog->add_edit_row('Menu Name:','menu_name',$row,50);
       if ($shopping_cart) {
          $dialog->start_row('Order/Invoice Name:');
          $dialog->add_input_field('order_name',$row,50);
          $dialog->write('&nbsp;&nbsp;&nbsp;');
          $dialog->add_checkbox_field('flag8','Hide Name in Orders',
                                      $flags & HIDE_NAME_IN_ORDERS);
          $dialog->end_row();
       }
       $dialog->start_row($product_label.' Status:','middle');
       $dialog->start_choicelist('status');
       $status = get_row_value($row,'status');
       while (list($index,$label) = each($status_values))
          $dialog->add_list_item($index,$label,$status == $index);
       $dialog->end_choicelist();
       $dialog->end_row();
       if (isset($product_types)) {
          $product_type = get_row_value($row,'product_type');
          $dialog->start_row($product_label.' Type:','middle');
          if (count($product_types) > 8) {
             $dialog->start_choicelist('product_type');
             while (list($type_value,$type_label) = each($product_types))
                $dialog->add_list_item($type_value,$type_label,
                                       $product_type == $type_value,
                                       'change_product_type(this);');
             $dialog->end_choicelist();
          }
          else while (list($type_value,$type_label) = each($product_types))
             $dialog->add_radio_field('product_type',$type_value,$type_label,
                                      ($product_type == $type_value),
                                      'change_product_type(this);');
          $dialog->end_row();
       }
       if (! $disable_catalog_config) {
          $templates = load_catalog_templates($db);
          $template = get_row_value($row,'template');
          $dialog->start_row('Catalog Template:','middle');
          $dialog->start_choicelist('template');
          $dialog->add_list_item('','Default Template',(! $template));
          if ($templates) foreach ($templates as $filename)
             $dialog->add_list_item($filename,$filename,$template == $filename);
          $dialog->end_choicelist();
          $dialog->end_row();
       }
       if ($enable_vendors) {
          $vendor = get_row_value($row,'vendor');
          $dialog->start_row('Vendor:','middle');
          $query = 'select id,name from vendors order by name';
          $vendors = $db->get_records($query);
          if ($vendors) {
             $dialog->start_choicelist('vendor','select_vendor();');
             $dialog->add_list_item('','',(! $vendor));
             foreach ($vendors as $vendor_info) {
                $dialog->add_list_item($vendor_info['id'],
                   $vendor_info['name'],($vendor_info['id'] == $vendor));
             }
             $dialog->end_choicelist();
          }
          $dialog->add_inner_prompt('Import:');
          $dialog->write('<span id="import_list"><input type="hidden" ' .
                         'name="import_id" value="'.get_row_value($row,'import_id') .
                         '"></span>');
          $dialog->end_row();
       }
       if (function_exists('display_custom_product_price_fields'))
          display_custom_product_price_fields($dialog,$row,$features);
       else {
          if ($features & LIST_PRICE_PRODUCT)
             $dialog->add_edit_row($price_prompt.':','list_price',$row,10);
          if ($features & REGULAR_PRICE_PRODUCT)
             $dialog->add_edit_row('Price:','price',$row,10);
          if ($features & SALE_PRICE_PRODUCT)
             $dialog->add_edit_row('Sale Price:','sale_price',$row,10);
       }
       if ($shopping_cart)
          call_shopping_event('add_price_fields',array(&$dialog,$row));
       if ($features & PRODUCT_COST_PRODUCT)
          $dialog->add_edit_row('Product Cost:','cost',$row,10);
       if (! empty($enable_wholesale)) {
          $dialog->start_row('Account Discount:');
          $dialog->write('<input type="text" class="text discount" ' .
             'name="account_discount" id="account_discount" size="3" value="');
          write_form_value(get_row_value($row,'account_discount'));
          $dialog->write('">% off of regular price &nbsp;&nbsp;&nbsp;' .
             '<i>(overrides Account Discount Rate but not Account Product ' .
             'Discount Rate)</i>'."\n");
          $dialog->end_row();
       }
       if ($features & (MIN_ORDER_QTY_PRODUCT|MIN_ORDER_QTY_BOTH))
          $dialog->add_edit_row('Min Order Qty:','min_order_qty',$row,3);
       $dialog->start_row('Flags:','top');
       $dialog->start_table();
       $dialog->write("<tr><td style=\"padding-bottom:5px;\">\n");
       $feature_num = 0;
       if (! $hide_featured_product_flag) {
          $dialog->add_checkbox_field('flag0','&nbsp;Featured '.$product_label .
                                      '&nbsp;&nbsp;&nbsp;&nbsp;',$flags & FEATURED);
          $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          $feature_num++;
       }
/*
       if ($features & MAINTAIN_INVENTORY) {
          $dialog->add_checkbox_field('flag1','&nbsp;Dynamic Inventory' .
                                      '&nbsp;&nbsp;&nbsp;&nbsp;',
                                      $flags & DYNAMIC);
          $feature_num++;
          if ($feature_num % 3)
             $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
       }
*/
       $dialog->add_checkbox_field('flag2','&nbsp;Do not include in search ' .
                                           'results&nbsp;&nbsp;&nbsp;&nbsp;',
                                   $flags & NOSEARCH);
       $feature_num++;
       if ($feature_num % 3)
          $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
       else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
       $dialog->add_checkbox_field('flag3','&nbsp;Unique URL Alias',
                                   $flags & UNIQUEURL);
       $feature_num++;
       if ($shopping_cart) {
          if ($feature_num % 3)
             $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
          $dialog->add_checkbox_field('flag4','&nbsp;No Quantity Selection',
                                      $flags & NO_QUANTITY);
          $feature_num++;
          if (! $enable_product_flags) {
             if ($feature_num % 3)
                $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
             else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
             $dialog->add_checkbox_field('flag5','&nbsp;New '.$product_label,
                                         $flags & NEW_PRODUCT);
             $feature_num++;
             if ($feature_num % 3)
                $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
             else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
             $dialog->add_checkbox_field('flag6','&nbsp;On Sale',$flags & ON_SALE);
             $feature_num++;
          }
          if ($feature_num % 3)
             $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
          $dialog->add_checkbox_field('flag9','&nbsp;On Account',
                                      $flags & ON_ACCOUNT);
          $feature_num++;

          if ($features & USE_COUPONS) {
             if ($feature_num % 3)
                $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
             else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
             $dialog->add_checkbox_field('flag10','&nbsp;No Coupons/Special Offers',
                                         $flags & NO_COUPONS);
             $feature_num++;
          }
          if (! empty($enable_wholesale)) {
             if ($feature_num % 3)
                $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
             else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
             $dialog->add_checkbox_field('flag11','&nbsp;No Account Discounts',
                                         $flags & NO_ACCOUNT_DISCOUNTS);
             $feature_num++;
          }
       }
       if ($shopping_cart) {
          $taxable = get_row_value($row,'taxable');
          if ($taxable === '') $taxable = 1;
          if ($feature_num % 3)
             $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
          $dialog->add_checkbox_field('taxable','&nbsp;Taxable',$taxable);
          $feature_num++;
       }
       if ($enable_reorders) {
          if ($feature_num % 3)
             $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
          else $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
          $dialog->add_checkbox_field('flag7','&nbsp;Re-Order '.$product_label,
                                      $flags & REORDER_PRODUCT);
          $feature_num++;
       }
       if (function_exists('add_custom_product_flags'))
          add_custom_product_flags($dialog,$row,$feature_num);
       if ($feature_num % 3) $dialog->write('</td><td>&nbsp;');
       $dialog->end_row();
       if ($enable_product_flags) {
          require_once '../admin/productflags-admin.php';
          write_product_flag_fields($db,$dialog,$row);
       }
       $dialog->end_table();
       $dialog->end_row();
       switch ($desc_field_type) {
          case EDIT_FIELD:
             $dialog->add_edit_row('Short Description:','short_description',
                                   $row,80);
             break;
          case TEXTAREA_FIELD:
             $dialog->add_textarea_row('Short Description:','short_description',
                                       $row,4,65,WRAP_SOFT);
             break;
          case HTMLEDIT_FIELD:
             $dialog->start_row('Short Description:','top');
             $dialog->add_htmleditor_popup_field('short_description',$row,
                'Short Description',550,60,null,null,null,false,
                'catalogtemplates.xml');
             $dialog->end_row();
             break;
       }
       if (! isset($long_description_height)) $long_description_height = 100;
       if ($long_description_height > 0) {
          $dialog->start_row('Long Description:','top');
          $dialog->add_htmleditor_popup_field('long_description',$row,
             'Long Description',550,$long_description_height,null,null,null,
             false,'catalogtemplates.xml');
          $dialog->end_row();
       }
       if ($enable_multisite) {
          $dialog->start_row('Web Sites:','top');
          list_website_checkboxes($db,$dialog,get_row_value($row,'websites'));
          $dialog->end_row();
       }
       if (! empty($include_product_downloads)) {
          $dialog->start_row('Downloads:','top');
          add_base_href($dialog,$default_base_href,true);
          add_product_data_grid($dialog,get_row_value($row,'id'),
                                DOWNLOADS_DATA_TYPE,'Download','Downloads',
                                $edit_type,100,false);
          add_base_href($dialog,$base_url,true);
          $dialog->end_row();
       }
       if ($edit_type == UPDATERECORD) $frame_name = 'edit_product';
       else $frame_name = 'add_product';
       if (! empty($enable_downloadable_products))
          $dialog->add_browse_row('Product Download:','download_file',$row,50,
                      $frame_name,$downloadable_products_dir,true,true,false,
                      false);
       if (plugin_installed('video')) {
          require_once get_plugin_dir().'/video/video.conf';
          if (substr($video_dir,-1) != '/') $video_dir .= '/';
          $dialog->add_browse_row('Video:','video',$row,50,$frame_name,
                                  $video_dir,true,true,false,false,false);
       }
       if (isset($audio_dir)) {
          if (substr($audio_dir,-1) != '/') $audio_dir .= '/';
          $dialog->add_browse_row('Audio:','audio',$row,50,$frame_name,
                                  $audio_dir,true,true,false,false,false);
       }
       if ($enable_designer)
          $dialog->add_browse_row('Designer Image:','designer_image',$row,
             50,$frame_name,'/images/designer',true,true,true,false);
       while (list($field_name,$field) = each($product_fields)) {
          if (isset($field['fieldtype'])) switch ($field['fieldtype']) {
             case EDIT_FIELD:
                $dialog->add_edit_row($field['prompt'],$field_name,$row,
                                      $field['fieldwidth']);
                break;
             case TEXTAREA_FIELD:
                $dialog->add_textarea_row($field['prompt'],$field_name,$row,
                                          $field['height'],$field['fieldwidth'],
                                          $field['wrap']);
                break;
             case CHECKBOX_FIELD:
                $dialog->start_row($field['prompt'],'top');
                $dialog->add_checkbox_field($field_name,'',$row);
                $dialog->end_row();
                break;
             case HTMLEDIT_FIELD:
                $dialog->start_row($field['prompt'],'top');
                $dialog->add_htmleditor_popup_field($field_name,$row,
                   $field['title'],$field['width'],$field['height'],null,null,
                   null,false,'catalogtemplates.xml');
                $dialog->end_row();
                break;
             case CUSTOM_FIELD:
                $dialog->start_row($field['prompt'],'middle');
                if (function_exists('display_custom_product_field'))
                   display_custom_product_field($dialog,$field_name,
                                                get_row_value($row,$field_name));
                $dialog->end_row();
                break;
             case CUSTOM_ROW:
                if (function_exists('display_custom_product_field'))
                   display_custom_product_field($dialog,$field_name,$row);
                break;
             case BROWSE_ROW:
                if (isset($field['dir'])) $browse_dir = $field['dir'];
                else $browse_dir = '';
                if (isset($field['single_dir']))
                   $single_dir = $field['single_dir'];
                else $single_dir = false;
                if (isset($field['single_row']))
                   $single_row = $field['single_row'];
                else $single_row = false;
                if (isset($field['image_type']))
                   $browse_image_type = $field['image_type'];
                else $browse_image_type = false;
                if (isset($field['include_dirpath']))
                   $include_dirpath = $field['include_dirpath'];
                else $include_dirpath = true;
                if (isset($field['resize'])) $resize = $field['resize'];
                else $resize = null;
                if (isset($field['suffix'])) $suffix = $field['suffix'];
                else $suffix = null;
                $dialog->add_browse_row($field['prompt'],$field_name,
                   get_row_value($row,$field_name),$field['fieldwidth'],
                   $frame_name,$browse_dir,$single_dir,$single_row,
                   $browse_image_type,$include_dirpath,false,$resize,null,
                   null,false,0,$suffix);
                break;
          }
       }
       require_once '../engine/modules.php';
       if (module_attached('display_custom_fields'))
          call_module_event('display_custom_fields',
                            array('products',$db,&$dialog,$edit_type,$row));
       $dialog->end_field_table();
       add_base_href($dialog,$default_base_href,true);
       $dialog->write("</div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['specs'])) {
       $dialog->start_tab_content('specs_content',false);
       if (! empty($enable_gift_certificates)) {
          require_once '../admin/modules/giftcertificates/admin.php';
          start_gift_specs($dialog,$row);
       }
       if (function_exists('display_product_specs_fields'))
          display_product_specs_fields($dialog,$db,$row,$edit_type);
       if (! empty($enable_gift_certificates)) end_gift_specs($dialog,$row);
       $dialog->end_tab_content();
    }

    if ((count($shopping_modules) > 0) || (! empty($shopping_feeds_enabled))) {
       $dialog->start_tab_content('shopping_content',false);
       $dialog->start_field_table('shopping_table');
       $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt shopping_section\">" .
                      "Standard Shopping Fields</td></tr>\n");
       $dialog->add_edit_row('GTIN/UPC:','shopping_gtin',$row,70);
       $dialog->add_edit_row('MPN:','shopping_mpn',$row,70);
       $dialog->add_edit_row('Brand:','shopping_brand',$row,70);
       $dialog->start_row('Gender:','middle');
       $gender = strtolower(trim(get_row_value($row,'shopping_gender')));
       $genders = array('male','female','unisex');
       $dialog->start_choicelist('shopping_gender');
       $dialog->add_list_item('','',false);
       foreach ($genders as $value)
          $dialog->add_list_item($value,$value,$gender == $value);
       if ($gender && (! in_array($gender,$genders)))
          $dialog->add_list_item($gender,$gender,true);
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->add_edit_row('Color:','shopping_color',$row,70);
       $dialog->start_row('Age:','middle');
       $age = strtolower(trim(get_row_value($row,'shopping_age')));
       if ($age == 'child') $age = 'kids';
       $ages = array('newborn','infant','toddler','kids','adult');
       $dialog->start_choicelist('shopping_age');
       $dialog->add_list_item('','',false);
       foreach ($ages as $value)
          $dialog->add_list_item($value,$value,$age == $value);
       if ($age && (! in_array($age,$ages)))
          $dialog->add_list_item($age,$age,true);
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->start_row('Condition:','middle');
       $condition = strtolower(trim(get_row_value($row,'shopping_condition')));
       $conditions = array('new','refurbished','used');
       $dialog->start_choicelist('shopping_condition');
       $dialog->add_list_item('','',false);
       foreach ($conditions as $value)
          $dialog->add_list_item($value,$value,$condition == $value);
       if ($condition && (! in_array($condition,$conditions)))
          $dialog->add_list_item($condition,$condition,true);
       $dialog->end_choicelist();
       $dialog->end_row();
       call_shopping_event('add_shopping_fields',array($db,&$dialog,$row));
       if ($max_shopping_flag != -1)
          $dialog->add_hidden_field('MaxShoppingFlag',$max_shopping_flag);
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['image'])) {
       $dialog->start_tab_content('image_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <table cellspacing=\"0\" cellpadding=\"0\" " .
                      "width=\"100%\"><tr valign=\"top\">\n");
       $dialog->write("          <td id=\"images_cell\"><script>init_images(\"");
       if ($shopping_cart) $dialog->write('../cartengine/');
       $dialog->write($script_name."\",");
       if ($edit_type == UPDATERECORD) {
          $frame = get_form_field('frame');
          if ($frame) $dialog->write("\"".$frame."\",\"EditProduct\"");
          else if (get_form_field('insidecms'))
             $dialog->write("\"smartedit\",\"EditProduct\"");
          else $dialog->write("\"edit_product\",\"EditProduct\"");
       }
       else {
          $frame = get_form_field('frame');
          if ($frame) $dialog->write("\"".$frame."\",\"AddProduct\"");
          else $dialog->write("\"add_product\",\"AddProduct\"");
       }
       if ($dialog->skin) $dialog->write(',-100');
       else $dialog->write(',600');
       $dialog->write(','.$image_parent_type.");\n");
       $dialog->write('                    create_images_grid('.$id .
                      ",'images_cell');</script></td>\n");
       add_image_sequence_buttons($dialog);
       $dialog->write("        </tr></table>\n");
       add_image_sample($dialog);
       if ($enable_product_callouts && (! $use_callout_groups))
          setup_callouts_grid($dialog,$id,$edit_type);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['attributes'])) {
       $dialog->start_tab_content('attributes_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write("           attribute_list = new SubList();\n");
       $dialog->write("           attribute_list.name = 'attribute_list';\n");
       $dialog->write("           attribute_list.script_url = '" .
                      $script_name."';\n");
       $dialog->write("           attribute_list.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_product';\n");
       else $dialog->write("add_product;'\n");
       $dialog->write("           attribute_list.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditProduct';\n");
       else $dialog->write("AddProduct';\n");
       if ($dialog->skin)
          $dialog->write("           attribute_list.grid_width = 0;\n");
       else $dialog->write("           attribute_list.grid_width = 220;\n");
       $dialog->write("           attribute_list.grid_height = 600;\n");
       $dialog->write("           attribute_list.left_table = '" .
                      "product_attributes';\n");
       $dialog->write("           attribute_list.left_titles = ['Name'];\n");
       $dialog->write("           attribute_list.left_label = 'attributes';\n");
       $dialog->write("           attribute_list.right_table = 'attributes';\n");
       $dialog->write("           attribute_list.right_titles = ['Name'];\n");
       $dialog->write("           attribute_list.right_label = 'attributes';\n");
       $dialog->write("           attribute_list.right_single_label = '" .
                      "attribute';\n");
       $dialog->write("        </script>\n");
       create_sublist_grids('attribute_list',$dialog,$id,
                            $product_label.' Attributes','All Attributes');
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['inventory'])) {
       $dialog->start_tab_content('inventory_content',false);
       if ($dialog->skin)
          $dialog->write("        <div id=\"inventory_grid_div\" " .
                         "class=\"fieldSection\">\n");
       else $dialog->write("        <div id=\"inventory_grid_div\" " .
                           "style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write('           var col_names = [');
       $first_field = true;
       if ((! empty($enable_inventory_available)) ||
           (! ($features & MAINTAIN_INVENTORY))) $show_available = true;
       else $show_available = false;
       if ($features & USE_PART_NUMBERS)
          write_inventory_field($dialog,"\"".$part_number_prompt."\"",
                                $first_field);
       if ($features & MAINTAIN_INVENTORY) {
          write_inventory_field($dialog,"\"Qty\"",$first_field);
          write_inventory_field($dialog,"\"Min Qty\"",$first_field);
       }
       if ($show_available)
          write_inventory_field($dialog,"\"In Stock\"",$first_field);
       if ($features & INVENTORY_BACKORDERS)
          write_inventory_field($dialog,"\"Backorderable\"",$first_field);
       if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH))
          write_inventory_field($dialog,"\"Min Order Qty\"",$first_field);
       if ($features & WEIGHT_ITEM)
          write_inventory_field($dialog,"\"Weight\"",$first_field);
       if ($features & LIST_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"List Price($)\"",$first_field);
       if ($features & REGULAR_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"Price($)\"",$first_field);
       if ($features & SALE_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"Sale Price($)\"",$first_field);
       if ($features & PRODUCT_COST_INVENTORY)
          write_inventory_field($dialog,"\"Product Cost($)\"",$first_field);
       if ($features & DROP_SHIPPING)
          write_inventory_field($dialog,"\"Origin Zip\"",$first_field);
       write_inventory_field($dialog,"\"Image\"",$first_field);
       if (isset($inventory_fields)) {
          reset($inventory_fields);
          while (list($field_name,$field) = each($inventory_fields)) {
             if (isset($field['title']))
                write_inventory_field($dialog,'"'.$field['title'].'"',
                                      $first_field);
             else write_inventory_field($dialog,'""',$first_field);
          }
       }
       $dialog->write("];\n");
       $dialog->write('           var fld_names = [');
       $first_field = true;
       if ($features & USE_PART_NUMBERS)
          write_inventory_field($dialog,"\"part_number\"",$first_field);
       if ($features & MAINTAIN_INVENTORY) {
          write_inventory_field($dialog,"\"qty\"",$first_field);
          write_inventory_field($dialog,"\"min_qty\"",$first_field);
       }
       if ($show_available)
          write_inventory_field($dialog,"\"available\"",$first_field);
       if ($features & INVENTORY_BACKORDERS)
          write_inventory_field($dialog,"\"backorder\"",$first_field);
       if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH))
          write_inventory_field($dialog,"\"min_order_qty\"",$first_field);
       if ($features & WEIGHT_ITEM)
          write_inventory_field($dialog,"\"weight\"",$first_field);
       if ($features & LIST_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"list_price\"",$first_field);
       if ($features & REGULAR_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"price\"",$first_field);
       if ($features & SALE_PRICE_INVENTORY)
          write_inventory_field($dialog,"\"sale_price\"",$first_field);
       if ($features & PRODUCT_COST_INVENTORY)
          write_inventory_field($dialog,"\"cost\"",$first_field);
       if ($features & DROP_SHIPPING)
          write_inventory_field($dialog,"\"origin_zip\"",$first_field);
       write_inventory_field($dialog,"\"image\"",$first_field);
       if (isset($inventory_fields)) {
          reset($inventory_fields);
          while (list($field_name,$field) = each($inventory_fields))
             write_inventory_field($dialog,"\"".$field_name."\"",
                                   $first_field);
       }
       $dialog->write("];\n");
       $dialog->write('           var col_widths = [');
       $first_field = true;
       if ($features & USE_PART_NUMBERS)
          write_inventory_field($dialog,$part_number_size,$first_field);
       if ($features & MAINTAIN_INVENTORY) {
          write_inventory_field($dialog,50,$first_field);
          write_inventory_field($dialog,50,$first_field);
       }
       if ($show_available)
          write_inventory_field($dialog,50,$first_field);
       if ($features & INVENTORY_BACKORDERS)
          write_inventory_field($dialog,50,$first_field);
       if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH))
          write_inventory_field($dialog,50,$first_field);
       if ($features & WEIGHT_ITEM)
          write_inventory_field($dialog,50,$first_field);
       if ($features & LIST_PRICE_INVENTORY)
          write_inventory_field($dialog,75,$first_field);
       if ($features & REGULAR_PRICE_INVENTORY)
          write_inventory_field($dialog,75,$first_field);
       if ($features & SALE_PRICE_INVENTORY)
          write_inventory_field($dialog,75,$first_field);
       if ($features & PRODUCT_COST_INVENTORY)
          write_inventory_field($dialog,85,$first_field);
       if ($features & DROP_SHIPPING)
          write_inventory_field($dialog,60,$first_field);
       write_inventory_field($dialog,200,$first_field);
       if (isset($inventory_fields)) {
          reset($inventory_fields);
          while (list($field_name,$field) = each($inventory_fields)) {
             if (isset($field['colwidth'])) $width = $field['colwidth'];
             else if (isset($field['width'])) $width = $field['width'];
             else $width = 0;
             write_inventory_field($dialog,$width,$first_field);
          }
       }
       $dialog->write("];\n");
       if (! empty($enable_linked_inventory))
          $dialog->write("           edit_inventory_width = 800;\n");
       $dialog->write('           create_inventory_grid('.$id.");\n");
       $dialog->write("        </script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['qtydiscounts'])) {
       $dialog->start_tab_content('qtydiscounts_content',false);
       display_discounts($dialog,$id);
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['pricebreaks'])) {
       if ($edit_type == ADDRECORD)
          $price_break_type = $default_price_break_type;
       else $price_break_type = $row['price_break_type'];
       if ($edit_type == UPDATERECORD) $form_name = 'EditProduct';
       else $form_name = 'AddProduct';
       $dialog->start_tab_content('pricebreaks_content',false);
       display_price_breaks($dialog,$form_name,$price_break_type,
                            get_row_value($row,'price_breaks'));
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['categories'])) {
       $dialog->start_tab_content('categories_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write("           categories = new SubList();\n");
       $dialog->write("           categories.name = 'categories';\n");
       $dialog->write("           categories.script_url = '".$script_name .
                      "';\n");
       $dialog->write("           categories.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_product';\n");
       else $dialog->write("add_product;'\n");
       $dialog->write("           categories.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditProduct';\n");
       else $dialog->write("AddProduct';\n");
       if ($dialog->skin)
          $dialog->write("           categories.grid_width = 0;\n");
       else $dialog->write("           categories.grid_width = 290;\n");
       $dialog->write('           categories.grid_height = ' .
                      "product_dialog_height - 85;\n");
       $dialog->write("           categories.left_table = '" .
                      $category_products_table."';\n");
       $dialog->write("           categories.left_titles = ['".$category_label .
                      " Name'];\n");
       $dialog->write("           categories.left_label = 'categories';\n");
       $dialog->write("           categories.right_table = '" .
                      $categories_table."';\n");
       $dialog->write("           categories.right_titles = ['".$category_label .
                      " Name'];\n");
       $dialog->write("           categories.right_label = 'categories';\n");
       $dialog->write("           categories.right_single_label = 'category';\n");
       $dialog->write("           categories.default_frame = 'edit_product';\n");
       $dialog->write("           categories.enable_double_click = true;\n");
       $dialog->write("           categories.reverse_list = true;\n");
       $dialog->write("           categories.categories = true;\n");
       $dialog->write("        </script>\n");
       create_sublist_grids('categories',$dialog,$id,$categories_label,
                            'All '.$categories_label,true,'CategoryQuery',
                            $categories_label,false);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($related_types)) {
       reset($related_types);   $inside_related_tab = false;
       while (list($related_type,$label) = each($related_types)) {
          if (isset($related_tab) && in_array($related_type,$related_tab)) {
             if (! $inside_related_tab) {
                $num_grids = count($related_tab);
                $inside_related_tab = true;
                $dialog->start_tab_content('related_'.$related_type.'_content',
                                           false);
                if ($dialog->skin)
                   $dialog->write("        <div class=\"fieldSection\">\n");
                else $dialog->write("        <div style=\"padding: 4px;\">\n");
             }
          }
          else {
             $num_grids = 1;
             if ($inside_related_tab) {
                $inside_related_tab = false;
                $dialog->write("        </div>\n");
                $dialog->end_tab_content();
             }
             $dialog->start_tab_content('related_'.$related_type.'_content',
                                        false);
             if ($dialog->skin)
                $dialog->write("        <div class=\"fieldSection\">\n");
             else $dialog->write("        <div style=\"padding: 4px;\">\n");
          }
          $sublist_name = 'related_'.$related_type;
          $dialog->write('        <script type="text/javascript">'."\n");
          $dialog->write('           '.$sublist_name." = new SubList();\n");
          $dialog->write('           '.$sublist_name.'.name = \''.$sublist_name."';\n");
          $dialog->write('           '.$sublist_name.'.related_type = '.$related_type.";\n");
          $dialog->write('           '.$sublist_name.'.script_url = \''.$script_name."';\n");
          $dialog->write('           '.$sublist_name.'.frame_name = \'');
          if ($edit_type == UPDATERECORD) $dialog->write("edit_product';\n");
          else $dialog->write("add_product;'\n");
          $dialog->write('           '.$sublist_name.'.form_name = \'');
          if ($edit_type == UPDATERECORD) $dialog->write("EditProduct';\n");
          else $dialog->write("AddProduct';\n");
          if ($dialog->skin)
             $dialog->write('           '.$sublist_name.".grid_width = 0;\n");
          else $dialog->write('           '.$sublist_name.".grid_width = 300;\n");
          $dialog->write('           '.$sublist_name.'.grid_height = ');
          $dialog->write('(product_dialog_height - 85)');
          if ($num_grids > 1) $dialog->write('/'.$num_grids);
          $dialog->write(";\n");
          $dialog->write('           '.$sublist_name.".left_table = 'related_products';\n");
          $dialog->write('           '.$sublist_name.'.left_titles = [\''.$name_prompt .
                         "','Description'];\n");
          $dialog->write('           '.$sublist_name.'.left_widths = ['.$name_col_width .
                         ",-1];\n");
          $dialog->write('           '.$sublist_name.".left_fields = 'r.name,r.short_description';\n");
          $dialog->write('           '.$sublist_name.".left_label = 'products';\n");
          $dialog->write('           '.$sublist_name.'.right_table = \''.$products_table."';\n");
          $dialog->write('           '.$sublist_name.'.right_titles = [\''.$name_prompt .
                         "','Description'];\n");
          $dialog->write('           '.$sublist_name.'.right_widths = ['.$name_col_width .
                         ",-1];\n");
          $dialog->write('           '.$sublist_name.".right_fields = 'name,short_description';\n");
          $dialog->write('           '.$sublist_name.".right_label = 'products';\n");
          $dialog->write('           '.$sublist_name.".right_single_label = 'product';\n");
          $dialog->write('           '.$sublist_name.".default_frame = 'edit_product';\n");
          $dialog->write('           '.$sublist_name.".enable_double_click = true;\n");
          $dialog->write('           '.$sublist_name.".search_form = true;\n");
          $dialog->write('           '.$sublist_name.".categories = false;\n");
          $dialog->write('           '.$sublist_name.'.search_where = "name like ' .
                         "'%\$query\$%' or display_name like " .
                         "'%\$query\$%' or short_description like '%\$query\$%' " .
                         "or long_description like '%\$query\$%'");
          if ($features & USE_PART_NUMBERS)
             $dialog->write(' or id in (select parent from product_inventory ' .
                            "where part_number like '%\$query\$%')");
          $dialog->write("\";\n");
          $dialog->write("        </script>\n");
          create_sublist_grids($sublist_name,$dialog,$id,$label,
             'All '.$products_label,false,'Related_'.$related_type.'_Query',
             $products_label,true);
          if (! $inside_related_tab) {
             $dialog->write("        </div>\n");
             $dialog->end_tab_content();
          }
       }
       if ($inside_related_tab) {
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
       }
    }

    if (isset($product_tab_labels['seo'])) {
       $dialog->start_tab_content('seo_content',false);
       $dialog->start_field_table('seo_table');
       $dialog->add_edit_row('Meta Title:','seo_title',$row,80);
       $dialog->add_textarea_row('Meta Description:','seo_description',$row,
                                 5,65,WRAP_SOFT);
       $dialog->add_textarea_row('Meta Keywords:','seo_keywords',$row,5,65,
                                 WRAP_SOFT);
       $dialog->add_textarea_row('Page Header:','seo_header',$row,3,65,
                                 WRAP_SOFT);
       $dialog->add_textarea_row('Page Footer:','seo_footer',$row,3,65,
                                 WRAP_SOFT);
       $dialog->add_edit_row('URL Alias:','seo_url',$row,64);
       $dialog->start_row('SEO '.$category_label,'middle');
       $dialog->start_choicelist('seo_category',null,'select seo_category');
       $seo_category = get_row_value($row,'seo_category');
       if ($seo_category == '') $seo_category = 0;
       $query = 'select c.id,c.name from category_products p left join ' .
                'categories c on p.parent=c.id where p.related_id=?' .
                ' and (isnull(c.flags) or (not c.flags&8)) order by p.id';
       $query = $db->prepare_query($query,$id);
       $categories = $db->get_records($query,'id','name');
       if (! $categories) $categories = array();
       if (count($categories) == 0)
          $first_label = '[First '.$category_label.' Record]';
       else {
          list($index,$first_category_name) = each($categories);
          $first_label = '[First '.$category_label.' Record] (' .
                         $first_category_name.')';
       }
       $dialog->add_list_item(-1,'[All '.$categories_label.']',
                              $seo_category == -1);
       $dialog->add_list_item(0,$first_label,$seo_category == 0);
       reset($categories);
       while (list($seo_row_id,$seo_row_name) = each($categories))
          $dialog->add_list_item($seo_row_id,$seo_row_name,
                                 $seo_row_id == $seo_category);
       $dialog->end_choicelist();
       $dialog->end_row();
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if ((! isset($product_tabs['activity'])) || $product_tabs['activity']) {
       $dialog->start_tab_content('activity_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write('        <script>create_activity_grid('.$row['id'] .
                      ");</script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['reviews'])) {
       $dialog->start_tab_content('reviews_content',false);
       if ($dialog->skin)
          $dialog->write("        <div id=\"reviews_grid_div\" " .
                         "class=\"fieldSection\">\n");
       else $dialog->write("        <div id=\"reviews_grid_div\" " .
                           "style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write('           create_reviews_grid('.$id.");\n");
       $dialog->write("        </script>\n");
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (isset($product_tab_labels['popular'])) {
       $query = 'select a.id,a.name,a.display_name,a.admin_type from ' .
                'product_attributes pa join attributes a on a.id=' .
                'pa.related_id where pa.parent=? order by pa.sequence';
       $query = $db->prepare_query($query,$row['id']);
       $attributes = $db->get_records($query);
       if ($attributes) {
          while (list($index,$attribute) = each($attributes)) {
             if ($attribute['display_name'])
                $attributes[$index]['name'] = $attribute['display_name'];
             $attr_ids[] = $attribute['id'];
          }
          if (count($attr_ids) > 0) {
             $query = 'select id,parent,name from attribute_options where ' .
                      'parent in (?) order by parent,sequence';
             $query = $db->prepare_query($query,$attr_ids);
             $options = $db->get_records($query);
          }
          $dialog->write("<script type=\"text/javascript\">\n");
          reset($attributes);
          while (list($index,$attribute) = each($attributes)) {
             $dialog->write('  popular_attributes['.$index."] = [];\n");
             $attr_id = $attribute['id'];
             $attr_options = array();
             if ($options) foreach ($options as $option) {
                if ($option['parent'] == $attr_id) {
                   $option_name = str_replace('"',"\\\"",$option['name']);
                   $dialog->write('  popular_attributes['.$index.'][' .
                                  $option['id'].'] = "'.$option_name .
                                  "\";\n");
                   $attr_options[] = $option;
                }
             }
             $attributes[$index]['options'] = $attr_options;
          }
          $dialog->write("</script>\n");
       }
       $query = 'select * from popular_products where parent=?';
       $query = $db->prepare_query($query,$row['id']);
       $popular_products = $db->get_records($query);
       $num_popular = count($popular_products);
       if ($num_popular > 0) {
          $query = 'select id,filename from images where parent_type=1 and ' .
                   'parent=? order by sequence';
          $query = $db->prepare_query($query,$row['id']);
          $images = $db->get_records($query);
       }

       $dialog->start_tab_content('popular_content',false);
       $dialog->add_hidden_field('OldNumPopular',$num_popular);
       $dialog->add_hidden_field('NumPopular',$num_popular);
       if ($attributes)
          $dialog->add_hidden_field('NumAttributes',count($attributes));
       $dialog->start_field_table('popular_table');
       $dialog->write('<tr><th></th><th align="left">Name</th>');
       $attr_ids = array();
       if ($attributes) foreach ($attributes as $attribute)
          $dialog->write('<th>'.$attribute['name'].'</th>');
       $dialog->write("<th align=\"left\">Image</th></tr>\n");
       $index = 0;
       foreach ($popular_products as $popular) {
          $dialog->write('<tr valign="middle"><td align="center">');
          $dialog->add_radio_field('popular_sel',$index,'',false);
          $dialog->write("</td>\n<td>");
          $dialog->add_input_field('popular_name_'.$index,$popular['name'],30);
          $attr_index = 0;
          $popular_attributes = explode('-',$popular['attributes']);
          if ($attributes) foreach ($attributes as $attribute) {
             if (isset($popular_attributes[$attr_index]))
                $popular_attribute = $popular_attributes[$attr_index];
             else $popular_attribute = -1;
             $dialog->write("</td>\n<td align=\"center\">");
             $dialog->start_choicelist('popular_attr_'.$index.'_'.$attr_index);
             $dialog->add_list_item('','',$popular_attribute == -1);
             foreach ($attribute['options'] as $option)
                $dialog->add_list_item($option['id'],$option['name'],
                                       $option['id'] == $popular_attribute);
             $dialog->end_choicelist();
             $attr_index++;
          }
          $dialog->write("</td>\n<td>");
          $dialog->start_choicelist('popular_image_'.$index);
          $dialog->add_list_item('','',(! $popular['image']));
          if ($images) foreach ($images as $image)
             $dialog->add_list_item($image['filename'],$image['filename'],
                                    $image['filename'] == $popular['image']);
          $dialog->end_choicelist();
          $dialog->write("</td></tr>\n");
          $index++;
       }
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (function_exists('display_custom_product_tab_sections'))
       display_custom_product_tab_sections($dialog,$db,$row,$edit_type);

    $dialog->end_tab_section();
}

function write_end_hidden_product_fields($dialog)
{
    $dialog->add_hidden_field('End','$End$');
// print "<script type=\"text/javascript\">alert('Product dialog fully loaded');</script>\n";
}

function select_shopping_field()
{
    global $script_name;

    $frame = get_form_field('Frame');
    $field = get_form_field('Field');
    $form = get_form_field('Form');
    $label = get_form_field('Label');
    $value = get_form_field('Value');
    $field_info = array();
    call_shopping_event('setup_select_field',array($field,&$field_info));
    if ((! $field_info['use_listbox']) && $value) {
       $db = new DB;
       $query = 'select (select count(t2.'.$field_info['id_field'].') from ' .
                $field_info['table'].' t2 where t2.'.$field_info['label_field'] .
                ' <= t1.'.$field_info['label_field'].') as row from ' .
                $field_info['table'].' t1 where t1.'.$field_info['id_field'] .
                '=?';
       $query = $db->prepare_query($query,$value);
       $row = $db->get_record($query);
       if (empty($row['row'])) $current_row = 0;
       else $current_row = intval($row['row']) - 1;
    }

    $dialog = new Dialog;
    $dialog->add_script_file('products.js');
    if ($field_info['use_listbox']) {
       $head_block = "<style>\n  select,option { font-size: 11px !important; }\n" .
                     '</style>';
       $dialog->add_head_line($head_block);
    }
    else {
       $dialog->enable_aw();
       $dialog->add_style_sheet('products.css');
       $dialog->add_style_sheet('utility.css');
    }
    $dialog->set_body_id('select_shopping_field');
    $dialog->set_help('select_shopping_field');
    if ((! $field_info['use_listbox']) && $value && $current_row)
       $dialog->set_onload_function('shopping_field_onload(' .
                                    $current_row.');');

    $dialog->start_body('Select '.$label);
    if (! $field_info['use_listbox']) $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button('Select','images/Update.png',
                        'choose_shopping_field_value();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    if (! $field_info['use_listbox'])
       add_search_box($dialog,'search_shopping_fields',
                      'reset_search_shopping_fields');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'SelectValue');
    if ($field_info['use_listbox']) $dialog->start_field_table();
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('Field',$field);
    $dialog->add_hidden_field('Label',$label);
    if ($form) $dialog->add_hidden_field('Form',$form);
    if ($field_info['use_listbox']) {
       $dialog->write("<tr><td>\n");
       $dialog->start_listbox('Value',35,true,null,
                              'choose_shopping_field_value();');
       $dialog->add_list_item('','',! $value);
       call_shopping_event('load_select_field_list',
                           array(&$dialog,$field,$value));
       $dialog->end_choicelist();
       $dialog->write("</td></tr>\n");
       $dialog->end_field_table();
    }
    else {
       $dialog->write("          <script>\n");
       $dialog->write("             load_shopping_field_grid('" .
          $field_info['table']."','".$field_info['id_field']."','" .
          $field_info['label_field']."',".$field_info['label_width'].");\n");
       $dialog->write("          </script>\n");
    }
    $dialog->end_form();
    $dialog->end_body();
}

function add_tab_buttons($dialog,$db)
{
    global $product_label,$category_label,$shopping_cart,$product_tab_labels;
    global $enable_reviews,$enable_product_callouts,$use_callout_groups;
    global $enable_popular_products;

    $dialog->add_button_separator('product_buttons_row',20);
    if (isset($product_tab_labels['image']))
       add_image_buttons($dialog,false);
    if ($enable_product_callouts && (! $use_callout_groups))
       add_callout_buttons($dialog,$db,false);
    $dialog->add_button('Add Set','images/AddOrder.png',
                        'add_attribute_set();','add_set',false);
    $dialog->add_button('Delete All','images/DeleteOrder.png',
                        'delete_all_attributes();','delete_all',false);
    if (isset($product_tab_labels['inventory']))
       add_inventory_buttons($dialog,false);
    if (isset($product_tab_labels['qtydiscounts']))
       add_discount_buttons($dialog,false);
    if (isset($product_tab_labels['pricebreaks']))
       add_price_break_buttons($dialog);
    if (isset($product_tab_labels['categories']))
       $dialog->add_button('New '.$category_label,'images/AddCategory.png',
                           'new_category();','new_category',false);
    $dialog->add_button('New '.$product_label,'images/AddProduct.png',
                        'add_product(products);','new_product',false);
    if ($enable_reviews) {
       $dialog->add_button('Add Review','images/AddOrder.png',
                           'add_review();','add_review',false);
       $dialog->add_button('Edit Review','images/EditOrder.png',
                           'edit_review();','edit_review',false);
       $dialog->add_button('Delete Review','images/DeleteOrder.png',
                           'delete_review();','delete_review',false);
    }
    if ($enable_popular_products) {
       $dialog->add_button('Add Popular','images/AddProduct.png',
                           'add_popular_product();','add_popular',false);
       $dialog->add_button('Delete Popular','images/DeleteProduct.png',
                           'delete_popular_product();','delete_popular',false);
    }
    if (function_exists('add_custom_product_tab_buttons'))
       add_custom_product_tab_buttons($dialog);
}

function parse_product_flags()
{
    global $num_product_flags;

    if (! isset($num_product_flags)) $num_product_flags = NUM_PRODUCT_FLAGS;
    $flags = 0;
    for ($loop = 0;  $loop < $num_product_flags;  $loop++)
       if (get_form_field("flag".$loop) == 'on') $flags |= (1 << $loop);
    return $flags;
}

function save_popular_products($db,$product_record)
{
    global $product_label;

    $old_num_popular = intval(get_form_field('OldNumPopular'));
    $num_popular = intval(get_form_field('NumPopular'));
    if (($old_num_popular == 0) && ($num_popular == 0)) return true;

    $product_id = $product_record['id']['value'];
    $query = 'delete from popular_products where parent=?';
    $query = $db->prepare_query($query,$product_id);
    $db->log_query($query);
    if (! $db->query($query)) return false;
    if ($num_popular == 0) return true;

    $num_attributes = intval(get_form_field('NumAttributes'));
    if ($num_attributes == 0) return true;
    $popular_record = popular_product_record_definition();
    $popular_record['parent']['value'] = $product_id;
    for ($loop = 0;   $loop < $num_popular;  $loop++) {
       $field_name = 'popular_name_'.$loop;
       $popular_record['name']['value'] = get_form_field($field_name);
       $attributes = '';
       for ($attr_loop = 0;  $attr_loop < $num_attributes;  $attr_loop++) {
          $field_name = 'popular_attr_'.$loop.'_'.$attr_loop;
          $attribute = get_form_field($field_name);
          if ($attribute) {
             if ($attributes) $attributes .= '-';
             $attributes .= $attribute;
          }
       }
       if (! $attributes) continue;
       $popular_record['attributes']['value'] = $attributes;
       $field_name = 'popular_image_'.$loop;
       $popular_record['image']['value'] = get_form_field($field_name);
       if (! $db->insert('popular_products',$popular_record)) return false;
    }

    log_activity('Updated Popular Products for '.$product_label.' ' .
                 $product_record['name']['value'] .
                 ' (#'.$product_record['id']['value'].')');
    return true;
}

function create_product()
{
    global $default_attributes,$product_label,$products_table;

    if (! isset($default_attributes)) $default_attributes = array();
    $db = new DB;
    $product_record = product_record_definition();
    $product_record['name']['value'] = "New ".$product_label;
    if (! $db->insert($products_table,$product_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();

    if (count($default_attributes) > 0) {
       $sequence = 0;
       foreach ($default_attributes as $attr_id) {
          $sequence++;
          $sublist_record = sublist_record_definition();
          $sublist_record['parent']['value'] = $id;
          $sublist_record['related_id']['value'] = $attr_id;
          $sublist_record['sequence']['value'] = $sequence;
          if (! $db->insert("product_attributes",$sublist_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }

    print "product_id = ".$id.";";
    log_activity("Created New ".$product_label." #".$id);
}

function add_product()
{
    global $default_base_href,$product_label,$shopping_cart,$script_name;
    global $product_tab_labels,$enable_multisite,$website_cookie;
    global $products_table,$enable_product_callouts,$use_callout_groups;
    global $enable_product_flags,$enable_gift_certificates;

    if ($enable_product_callouts && (! $use_callout_groups))
       require_once 'callouts.php';

    init_product_tabs(array(),ADDRECORD);
    $db = new DB;
    $id = get_form_field('id');
    if (! is_numeric($id)) {
       process_error('Invalid '.$product_label.' '.$id,0);   return;
    }
    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($product_label.' #'.$id.' not found',0);
       return;
    }
    $row['name'] = '';
    if ($enable_multisite && isset($_COOKIE[$website_cookie]))
       $row['websites'] = $_COOKIE[$website_cookie];
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('products.css');
    $dialog->add_style_sheet('utility.css');
    if ($enable_product_callouts && (! $use_callout_groups))
       $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('products.js');
    $dialog->add_script_file('utility.js');
    if (isset($product_tab_labels['image'])) {
       $dialog->add_style_sheet('image.css');
       $dialog->add_script_file('image.js');
    }
    $dialog->add_script_file('sublist.js');
    if (isset($product_tab_labels['inventory']))
       $dialog->add_script_file('inventory.js');
    if (isset($product_tab_labels['qtydiscounts']))
       $dialog->add_script_file('discounts.js');
    if (isset($product_tab_labels['pricebreaks']))
       $dialog->add_script_file('pricebreak.js');
    if ($enable_product_callouts && (! $use_callout_groups))
       $dialog->add_script_file('callouts.js');
    if (! empty($enable_gift_certificates))
       $dialog->add_script_file('../admin/modules/giftcertificates/admin.js');
    if ($enable_product_flags)
       $dialog->add_script_file('../admin/productflags-admin.js');
    if ($shopping_cart)
       call_shopping_event('products_head',array(&$dialog,$db,ADDRECORD));
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_product_styles($dialog);
    add_product_variables($dialog,$db);
    add_base_href($dialog,$default_base_href,false);
    add_update_function($dialog,$id);
    if (get_form_field('sublist')) $dialog_title = 'New';
    else $dialog_title = 'Add';
    $dialog_title .= ' '.$product_label.' (#'.$id.')';
    add_script_prefix($dialog,$dialog_title);
    $dialog->set_onload_function('add_product_onload();');
    if (function_exists('custom_init_product_dialog'))
       custom_init_product_dialog($dialog);
    $dialog->set_body_id('add_product');
    $dialog->set_help('add_product');
    $dialog->start_body($dialog_title);
    $dialog->start_form($script_name,'AddProduct');
    $dialog->set_button_width(148);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Add '.$product_label,'images/AddProduct.png',
                        'process_add_product();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_tab_buttons($dialog,$db);
    $dialog->end_button_column();
    write_start_hidden_product_fields($dialog,ADDRECORD,$id,array(),$db);
    if (! $dialog->skin) $dialog->start_field_table();
    display_product_fields($dialog,ADDRECORD,$id,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    write_end_hidden_product_fields($dialog);
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_product()
{
    global $product_label,$enable_multisite,$enable_popular_products;

    if (! isset($off_sale_option)) $off_sale_option = 1;
    $db = new DB;
    $product_record = product_record_definition();
    $db->parse_form_fields($product_record);
    $flags = parse_product_flags();
    $product_record['flags']['value'] = $flags;
    $max_shopping_flag = get_form_field('MaxShoppingFlag');
    if ($max_shopping_flag !== null) {
       $shopping_flags = parse_shopping_flags($max_shopping_flag);
       $product_record['shopping_flags']['value'] = $shopping_flags;
    }
    if ($enable_multisite) parse_website_checkboxes($product_record);
    if (! add_product_record($db,$product_record,$product_id,$error_code,
                             $error,false)) {
       http_response($error_code,$error);   return;
    }
    if ($enable_popular_products) {
       if (! save_popular_products($db,$product_record)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,$product_label.' Added');
    log_activity('Added '.$product_label.' '.$product_record['name']['value'] .
                 ' (#'.$product_id.')');
    write_product_activity($product_label.' Added by ' .
                           get_product_activity_user($db),$product_id,$db);
}

function edit_product()
{
    global $default_base_href,$product_tab_labels,$product_label;
    global $products_table,$shopping_cart,$script_name;
    global $enable_product_callouts,$use_callout_groups,$enable_product_flags;
    global $enable_gift_certificates;

    if ($enable_product_callouts) require_once 'callouts.php';

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($product_label.' #'.$id.' not found',0);
       return;
    }
    init_product_tabs($row,UPDATERECORD);
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('products.css');
    $dialog->add_style_sheet('utility.css');
    if ($enable_product_callouts && (! $use_callout_groups))
       $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('products.js');
    $dialog->add_script_file('utility.js');
    if (isset($product_tab_labels['image'])) {
       $dialog->add_style_sheet('image.css');
       $dialog->add_script_file('image.js');
    }
    $dialog->add_script_file('sublist.js');
    if (isset($product_tab_labels['inventory']))
       $dialog->add_script_file('inventory.js');
    if (isset($product_tab_labels['qtydiscounts']))
       $dialog->add_script_file('discounts.js');
    if (isset($product_tab_labels['pricebreaks']))
       $dialog->add_script_file('pricebreak.js');
    if ($enable_product_callouts && (! $use_callout_groups))
       $dialog->add_script_file('callouts.js');
    if (! empty($enable_gift_certificates))
       $dialog->add_script_file('../admin/modules/giftcertificates/admin.js');
    if ($enable_product_flags)
       $dialog->add_script_file('../admin/productflags-admin.js');
    if ($shopping_cart)
       call_shopping_event('products_head',array(&$dialog,$db,UPDATERECORD));
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_product_styles($dialog,$db);
    add_product_variables($dialog,$db);
    add_website_js_array($dialog,$db);
    add_base_href($dialog,$default_base_href,false);
    add_update_function($dialog,null);
    $dialog_title = 'Edit '.$product_label.' - '.$row['name'].' (#'.$id.')';
    add_script_prefix($dialog,$dialog_title);
    if (isset($product_tab_labels['image']))
       $dialog->set_onload_function('product_onload(); images_onload();');
    else $dialog->set_onload_function('product_onload();');
    if (function_exists('custom_init_product_dialog'))
       custom_init_product_dialog($dialog);
    $dialog->set_body_id('edit_product');
    $dialog->set_help('edit_product');
    $dialog->start_body($dialog_title);
    $dialog->start_form($script_name,'EditProduct');
    $dialog->set_button_width(148);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Update','images/Update.png','update_product();');
    $dialog->add_button('Cancel','images/Update.png','close_product_dialog();');
    add_tab_buttons($dialog,$db);
    $dialog->end_button_column();
    write_start_hidden_product_fields($dialog,UPDATERECORD,$id,$row,$db);
    if (! $dialog->skin) $dialog->start_field_table();
    display_product_fields($dialog,UPDATERECORD,$id,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    write_end_hidden_product_fields($dialog);
    $dialog->end_form();
    $dialog->end_body();
}

function load_product()
{
    global $products_table,$product_label;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,$product_label.' #'.$id.' not found');
       return;
    }
    $row['shopping_gender'] = strtolower(trim($row['shopping_gender']));
    $row['shopping_age'] = strtolower(trim($row['shopping_age']));
    if ($row['shopping_age'] == 'child') $row['shopping_age'] = 'kids';
    print json_encode($row);
}

function load_imports()
{
    $db = new DB;
    $vendor = get_form_field('vendor');
    $query = 'select id,name from vendor_imports where parent=?';
    $query = $db->prepare_query($query,$vendor);
    $imports = $db->get_records($query);
    if (! $imports) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       return;
    }
    print json_encode($imports);
}

function load_seo_categories()
{
    global $category_label,$categories_label;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select c.id,c.name from category_products p left join ' .
             'categories c on p.parent=c.id where p.related_id=? and ' .
             '(isnull(c.flags) or (not c.flags&8)) order by p.id';
    $query = $db->prepare_query($query,$id);
    $categories = $db->get_records($query,'id','name');
    if (! $categories) $categories = array();
    if (count($categories) == 0)
       $first_label = '[First '.$category_label.' Record]';
    else {
       list($index,$first_category_name) = each($categories);
       $first_label = '[First '.$category_label.' Record] (' .
                      $first_category_name.")";
    }
    $seo_categories = array();
    $seo_categories[-1] = '[All '.$categories_label.']';
    $seo_categories[0] = $first_label;
    while (list($seo_row_id,$seo_row_name) = each($categories))
       $seo_categories[$seo_row_id] = $seo_row_name;
    print json_encode($seo_categories);
}

function update_product()
{
    global $product_label,$enable_multisite,$enable_popular_products;

    $db = new DB;
    $product_record = product_record_definition();
    $db->parse_form_fields($product_record);
    $flags = parse_product_flags();
    $product_record['flags']['value'] = $flags;
    $max_shopping_flag = get_form_field('MaxShoppingFlag');
    if ($max_shopping_flag !== null) {
       $shopping_flags = parse_shopping_flags($max_shopping_flag);
       $product_record['shopping_flags']['value'] = $shopping_flags;
    }
    if ($enable_multisite) parse_website_checkboxes($product_record);
    if (! update_product_record($db,$product_record,$error)) {
       http_response(422,$error);   return;
    }
    if ($enable_popular_products) {
       if (! save_popular_products($db,$product_record)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,$product_label." Updated");
    $product_id = $product_record['id']['value'];
    log_activity("Updated ".$product_label." ".$product_record['name']['value'] .
                 " (#".$product_id.")");
    write_product_activity($product_label.' Updated by ' .
                           get_product_activity_user($db),$product_id,$db);
}

function copy_product()
{
    global $products_table,$product_label,$use_discounts,$image_parent_type;
    global $shopping_cart,$use_qty_pricing;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    $product_name = $row['name'];
    $product_record = product_record_definition();
    while (list($field_name,$field_value) = each($row))
       if (isset($product_record[$field_name]))
          $product_record[$field_name]['value'] = $field_value;
    unset($product_record['id']['value']);
    $product_record['name']['value'] = $product_name.' (Copy)';
    if ($shopping_cart) {
       $product_record['shopping_flags']['value'] = 0;
       call_shopping_event('copy_product_record',array($db,&$product_record));
    }
    $product_record['seo_url']['value'] = '';
    $product_record['last_modified']['value'] = time();
    if (function_exists('custom_update_product_record'))
       custom_update_product_record($db,$product_record,COPYRECORD);
    if (! $db->insert($products_table,$product_record)) {
       http_response(422,$db->error);   return;
    }
    $new_id = $db->insert_id();
    $product_record['id']['value'] = $new_id;
    if (! copy_image_records($image_parent_type,$id,$new_id,$db))
       return false;

/* TODO: Copy product data, related products, and any other dependent records */

    if ($shopping_cart) {
       if (! copy_inventory_records($id,$new_id,$db)) return false;
       if (! copy_sublist_items('product_attributes',$id,$new_id,$db))
          return false;
       if (($use_discounts || $use_qty_pricing) &&
           (! copy_discounts($id,$new_id,$db))) return false;
    }
    if (function_exists('custom_update_product'))
       custom_update_product($db,$product_record,ADDRECORD);
    require_once '../engine/modules.php';
    if (module_attached('add_product')) {
       $product_info = $db->convert_record_to_array($product_record);
       set_product_category_info($db,$product_info);
       $inventory = load_inventory_records($db,$new_id);
       update_inventory_records($db,$product_info,$inventory);
       if (! call_module_event('add_product',
                               array($db,$product_info,$inventory))) {
          http_response(422,get_module_errors());   return;
       }
    }
    log_activity('Copied '.$product_label.' #'.$id.' to #'.$new_id.' (' .
                 $product_name.')');
    http_response(201,$product_label.' Copied');
    write_product_activity('Copied from '.$product_label.' #'.$id .
                           get_product_activity_user($db),$new_id,$db);
}

function delete_product()
{
    global $product_label,$cache_catalog_pages;

    $db = new DB;
    $ids = get_form_field("ids");
    $id_array = explode(',',$ids);
    if (! empty($cache_catalog_pages)) $cached_categories = array();
    $product_record = product_record_definition();
    foreach ($id_array as $id) {
       if (! empty($cache_catalog_pages))
          $cached_categories = load_category_pages($cached_categories,$db,$id);
       if (! delete_product_record($db,$id,$product_name,$error)) {
          http_response(422,$error);   return;
       }
       log_activity("Deleted ".$product_label." #".$id." (".$product_name.")");
    }
    if (! empty($cache_catalog_pages))
       update_cached_category_pages($db,$cached_categories);
    http_response(201,$product_label."s Deleted");
}

function edit_multiple_products()
{
    global $script_name,$products_label,$features;
    global $shopping_feeds_enabled,$enable_multisite,$shopping_cart;
    global $products_table,$product_types,$disable_catalog_config,$use_display_name;
    global $enable_vendors,$desc_field_type,$long_description_height,$audio_dir;

    $db = new DB;
    $fields = $db->get_field_defs($products_table);
    ksort($fields);
    $default_fields = array('name','cost','price');
    $skip_fields = array('id','last_modified','name');
    if (! isset($product_types)) $skip_fields[] = 'product_type';
    if (isset($disable_catalog_config) && $disable_catalog_config)
       $skip_fields[] = 'template';
    if (! $enable_vendors) $skip_fields[] = 'vendor';
    if (! $use_display_name) $skip_fields[] = 'display_name';
    if (! ($features & LIST_PRICE_PRODUCT)) $skip_fields[] = 'list_price';
    if (! ($features & REGULAR_PRICE_PRODUCT)) $skip_fields[] = 'price';
    if (! ($features & SALE_PRICE_PRODUCT)) $skip_fields[] = 'sale_price';
    if (! ($features & PRODUCT_COST_PRODUCT)) $skip_fields[] = 'cost';
    if (! ($features & REGULAR_PRICE_BREAKS))
       $skip_fields = array_merge($skip_fields,
                                  array('price_break_type','price_breaks'));
    if (isset($desc_field_type) && ($desc_field_type == 0))
       $skip_fields[] = 'short_description';
    if (isset($long_description_height) && ($long_description_height == 0))
       $skip_fields[] = 'long_description';
    if (! plugin_installed('video')) $skip_fields[] = 'video';
    if (! isset($audio_dir)) $skip_fields[] = 'audio';
    if (! $enable_multisite) $skip_fields[] = 'websites';
    if ($shopping_cart && empty($shopping_feeds_enabled))
       $shopping_feeds_enabled = shopping_modules_installed();
    if (! empty($shopping_feeds_enabled)) {
       $shopping_fields = array();
       while (list($field_name,$field_info) = each($fields))
          if (substr($field_name,0,7) == 'shopping_')
             $shopping_fields[] = $field_name;
       $skip_fields = array_merge($skip_fields,$shopping_fields);
    }

    $ids = get_form_field('ids');
    $id_array = explode(',',$ids);
    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->set_body_id('edit_multiple_products');
    $dialog->set_help('edit_multiple_products');
    $dialog->start_body('Edit Multiple '.$products_label);
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Continue','images/AdminUsers.png',
                        'continue_edit_multiple();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'EditMultiple');
    $dialog->add_hidden_field('ids',$ids);
    $dialog->add_hidden_field('cmd','editmultiplecontinue');
    $dialog->start_field_table();
    display_product_change_choices($db,$dialog,$id_array);
    $dialog->write('<tr><td colspan="2"><span class="fieldprompt">' .
                   'Fields to Edit:</span>'."\n");
    $dialog->write('<span class="perms_link"><a href="#" onClick="' .
       'check_all_fields(); return false;">Select All</a>&nbsp;|&nbsp;' .
       '<a href="#" onClick="uncheck_all_fields(); return false;">' .
       'Select None</a></span>'."\n");
    $dialog->write('<div class="edit_multiple_fields">'."\n");
    foreach ($fields as $field_name => $field_info) {
       if (in_array($field_name,$skip_fields)) continue;
       if (in_array($field_name,$default_fields)) $checked = true;
       else $checked = false;
       $dialog->add_checkbox_field('field_'.$field_name,$field_name,$checked);
       $dialog->write("<br>\n");
    }
    $dialog->write("</div>\n");
    $dialog->end_row();
    $dialog->write('<tr><td colspan="2">');
    $dialog->add_checkbox_field('SameValues',
       'Update all products with the same field values',false);
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function edit_multiple_continue()
{
    global $products_table,$products_label,$script_name,$features;

    $db = new DB;
    $id_array = parse_product_change_choices($db);
    if (count($id_array) == 0) {
       process_error('No '.$products_label.' found to edit',-1);   return;
    }
    $ids = implode(',',$id_array);
    $form_fields = get_form_fields();
    $fields = array();
    foreach ($form_fields as $field_name => $field_value) {
       if (substr($field_name,0,6) == 'field_') {
          if ($field_value == 'on') $fields[] = substr($field_name,6);
       }
    }
    if (count($fields) == 0) {
       process_error('You must select one or more fields to edit',-1);
       return;
    }
    $same_values = get_form_field('SameValues');
    if (! $same_values) {
       array_splice($fields,0,0,'name');
       $query = 'select id';
       foreach ($fields as $field_name) $query .= ','.$field_name;
       $query .= ' from '.$products_table.' where id in (?) order by id';
       $query = $db->prepare_query($query,$id_array);
       $rows = $db->get_records($query);
       if (! $rows) {
          if (isset($db->error)) process_error($db->error,-1);
          else process_error('No '.$products_label.' found to edit',-1);
          return;
       }
       $num_products = count($rows);
    }
    else $num_products = count($ids);
    if ($num_products == 0) {
       $db->free_result($result);
       process_error('No '.$products_label.' found to edit',-1);   return;
    }
    $db_fields = $db->get_field_defs($products_table);
    $width = 210;
    foreach ($fields as $field_name) {
       if (isset($db_fields[$field_name]['size']))
          $field_size = intval($db_fields[$field_name]['size']);
       else $field_size = 20;
       if ($field_size > 20) $field_size = 20;
       $db_fields[$field_name]['size'] = $field_size;
       $width += ($field_size * 8) + 7;
    }
    if ($same_values) {
       $width = 0;   $height = 0;
    }
    else {
       $height = 60 + ($num_products * 25);
       if ($height < 100) $height = 100;
    }

    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->set_onload_function('continue_edit_multiple_onload(' .
                                 $width.','.$height.');');
    $dialog->set_body_id('edit_multiple_continue');
    $dialog->set_help('edit_multiple_continue');
    $dialog->start_body('Edit Multiple '.$products_label);
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png',
                        'update_multiple_products();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'EditMultiple');
    $dialog->add_hidden_field('ids',$ids);
    $dialog->add_hidden_field('fields',implode(',',$fields));
    if ($same_values) $dialog->add_hidden_field('SameValues',$same_values);
    $dialog->start_field_table(null,'fieldtable editmultiple',2,0);
    if ($same_values) {
       foreach ($fields as $field_name) {
          $field_size = $db_fields[$field_name]['size'];
          $dialog->add_edit_row($field_name.':',$field_name,'',$field_size);
       }
    }
    else {
       $dialog->write("<tr><th>id</th>");
       foreach ($fields as $field_name)
          $dialog->write('<th>'.$field_name."</th>\n");
       $dialog->write("</tr>\n");
       foreach ($rows as $row) {
          $product_id = $row['id'];
          $dialog->write("<tr><td align=\"center\" style=\"width: 45px;\">" .
                         $row['id']."</td>\n");
          foreach ($fields as $field_name) {
             $input_field_name = $product_id.'_'.$field_name;
             $field_size = $db_fields[$field_name]['size'];
             $field_value = $row[$field_name];
             $dialog->write("<td align=\"center\"><input type=\"text\" " .
                            "class=\"text\" name=\"".$input_field_name .
                            "\" style=\"width: ".($field_size * 8) .
                            "px;\" value=\"");
             write_form_value($field_value);
             $dialog->write("\"></td>\n");
          }
          $dialog->write("</tr>\n");
       }
    }
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_multiple_products()
{
    global $product_label,$products_label;

    set_time_limit(0);
    $db = new DB;
    $ids = get_form_field('ids');
    $id_array = explode(',',$ids);
    $fields = get_form_field('fields');
    $fields_array = explode(',',$fields);
    $same_values = get_form_field('SameValues');

    $activity_user = get_product_activity_user($db);
    $product_record = product_record_definition();
    foreach ($id_array as $product_id) {
       if (! fill_product_record($db,$product_id,$product_record,$error)) {
          http_response(422,$error);   return;
       }
       foreach ($fields_array as $field_name) {
          if ($same_values) $input_field_name = $field_name;
          else $input_field_name = $product_id.'_'.$field_name;
          $product_record[$field_name]['value'] =
             get_form_field($input_field_name);
       }
       $product_record['last_modified']['value'] = time();
       if (! update_product_record($db,$product_record,$error,null,true)) {
          http_response(422,$error);   return;
       }
       log_activity('Updated '.$product_label.' ' .
                    $product_record['name']['value'].' (#'.$product_id.')');
       write_product_activity($product_label.' Updated by Multiple Edit by ' .
                              $activity_user,$product_id,$db);
    }

    http_response(201,$products_label.' Updated');
}

function change_product_status()
{
    global $product_label,$script_name;

    $db = new DB;
    $ids = get_form_field('ids');
    $id_array = explode(',',$ids);
    $status_values = load_cart_options(PRODUCT_STATUS,$db);
    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->set_body_id('change_product_status');
    $dialog->set_help('change_product_status');
    $dialog->start_body('Change '.$product_label.' Status');
    $dialog->set_button_width(133);
    $dialog->start_button_column();
    $dialog->add_button('Change Status','images/AdminUsers.png',
                        'update_product_status();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'ChangeProductStatus');
    $dialog->add_hidden_field('ids',$ids);
    $dialog->start_field_table();
    display_product_change_choices($db,$dialog,$id_array);
    $dialog->start_row($product_label.' Status: ');
    $dialog->start_choicelist('status',null);
    $dialog->add_list_item('','',false);
    for ($loop = 0;  $loop < count($status_values);  $loop++)
       if (isset($status_values[$loop]))
          $dialog->add_list_item($loop,$status_values[$loop],false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_product_status()
{
    global $product_label;

    set_time_limit(0);
    $db = new DB;
    $status = get_form_field('status');
    $id_array = parse_product_change_choices($db);
    if (count($id_array) == 0) {
       http_response(201,'No '.$product_label.' Status Values Changed');
       return;
    }

    $activity_user = get_product_activity_user($db);
    $product_record = product_record_definition();
    foreach ($id_array as $product_id) {
       if (! fill_product_record($db,$product_id,$product_record,$error)) {
          http_response(422,$error);   return;
       }
       $product_record['status']['value'] = $status;
       $product_record['last_modified']['value'] = time();
       if (! update_product_record($db,$product_record,$error,null,true)) {
          http_response(422,$error);   return;
       }
       log_activity('Updated Status for '.$product_label.' ' .
                    $product_record['name']['value'].' (#'.$product_id.')');
       write_product_activity($product_label.' Status set to '.$status.' by ' .
                              $activity_user,$product_id,$db);
    }
    http_response(201,$product_label.' Status Changed');
}

function change_prices()
{
    global $product_label,$script_name,$features;

    $price_fields = array();
    if ($features & REGULAR_PRICE_PRODUCT) $price_fields['price'] = 'Price';
    if ($features & LIST_PRICE_PRODUCT)
       $price_fields['list_price'] = 'List Price';
    if ($features & SALE_PRICE_PRODUCT)
       $price_fields['sale_price'] = 'Sale Price';
    if ($features & PRODUCT_COST_PRODUCT)
       $price_fields['cost'] = 'Product Cost';
    if (count($price_fields) == 0) {
       if ($features & REGULAR_PRICE_INVENTORY)
          $price_fields['price'] = 'Price';
       if ($features & LIST_PRICE_INVENTORY)
          $price_fields['list_price'] = 'List Price';
       if ($features & SALE_PRICE_INVENTORY)
          $price_fields['sale_price'] = 'Sale Price';
       if ($features & PRODUCT_COST_INVENTORY)
          $price_fields['cost'] = 'Product Cost';
       $inventory_prices = true;
    }
    else $inventory_prices = false;

    $db = new DB;
    $ids = get_form_field('ids');
    $id_array = explode(',',$ids);
    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->set_body_id('change_prices');
    $dialog->set_help('change_prices');
    $dialog->start_body('Change '.$product_label.' Prices');
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Change Prices','images/AdminUsers.png',
                        'update_prices();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'ChangePrices');
    if ($inventory_prices) $dialog->add_hidden_field('Inventory','true');
    $dialog->add_hidden_field('ids',$ids);
    $dialog->start_field_table();
    display_product_change_choices($db,$dialog,$id_array);
    $dialog->write('<tr><td class="fieldprompt" style="text-align: ' .
                   'left;" nowrap colspan="2">Set ');
    $dialog->start_choicelist('dest',null);
    $dialog->add_list_item('','',false);
    reset($price_fields);
    while (list($field_name,$label) = each($price_fields))
       $dialog->add_list_item($field_name,$label,false);
    $dialog->end_choicelist();
    $dialog->write(' = ');
    $dialog->start_choicelist('src',null);
    $dialog->add_list_item('','',false);
    reset($price_fields);
    while (list($field_name,$label) = each($price_fields))
       $dialog->add_list_item($field_name,$label,false);
    $dialog->end_choicelist();
    $dialog->write(' ');
    $dialog->start_choicelist('calc',null);
    $dialog->add_list_item('','',false);
    $dialog->add_list_item('+','+',false);
    $dialog->add_list_item('-','-',false);
    $dialog->add_list_item('*','*',false);
    $dialog->add_list_item('/','/',false);
    $dialog->end_choicelist();
    $dialog->write(" <input type=\"text\" class=\"text\" name=\"factor\" " .
                   "size=\"3\" value=\"\"></td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_prices()
{
    global $product_label;

    set_time_limit(0);
    $db = new DB;
    if (get_form_field('Inventory') == 'true') $inventory = true;
    else $inventory = false;
    $src_field = get_form_field('src');
    if ($inventory) {
       if ($src_field) $src_query = ','.$db->escape($src_field);
       else $src_query = '';
    }
    else $src_query = '';
    $id_array = parse_product_change_choices($db,$inventory,$src_query);
    if (count($id_array) == 0) {
       if ($inventory) http_response(201,'No Inventory Prices Changed');
       else http_response(201,'No '.$product_label.' Prices Changed');
       return;
    }
    $dest_field = get_form_field('dest');
    $calc = get_form_field('calc');
    $factor = get_form_field('factor');
    if ($factor != '') $factor = floatval($factor);
    $activity_user = get_product_activity_user($db);
    if ($inventory) $product_record = inventory_record_definition();
    else $product_record = product_record_definition();
    foreach ($id_array as $id_info) {
       if ($inventory && $src_field) $product_id = $id_info['id'];
       else $product_id = $id_info;
       if ($inventory) $product_record['id']['value'] = $product_id;
       else if (! fill_product_record($db,$product_id,$product_record,
                                      $error)) {
          http_response(422,$error);   return;
       }
       if ($src_field) {
          if ($inventory) $src_price = $id_info[$src_field];
          else $src_price = $product_record[$src_field]['value'];
          if (! $src_price) $src_price = 0;
       }
       else $src_price = 0;
       switch ($calc) {
          case '+': $dest_price = $src_price + $factor;   break;
          case '-': $dest_price = $src_price - $factor;   break;
          case '*': $dest_price = $src_price * $factor;   break;
          case '/': $dest_price = $src_price / $factor;   break;
          case '': if ($src_field) $dest_price = $src_price;
                   else $dest_price = $factor;
                   break;
       }
       $dest_price = round($dest_price,2);
       $product_record[$dest_field]['value'] = $dest_price;
       if ($inventory) {
          $product_record['last_modified']['value'] = time();
          if (! $db->update('product_inventory',$product_record)) {
             http_response(422,$error);   return;
          }
          log_activity('Updated '.$dest_field.' for Inventory Record #' .
                       $product_id);
          write_product_activity('Inventory Price '.$dest_field.' set to ' .
             $dest_price.' by '.$activity_user,$product_id,$db);
       }
       else {
          $product_record['last_modified']['value'] = time();
          if (! update_product_record($db,$product_record,$error,null,true)) {
             http_response(422,$error);   return;
          }
          log_activity('Updated '.$dest_field.' for '.$product_label.' ' .
                       $product_record['name']['value'].' (#'.$product_id.')');
          write_product_activity($product_label.' Price '.$dest_field .
             ' set to '.$dest_price.' by '.$activity_user,$product_id,$db);
       }
    }
    http_response(201,$product_label.' Prices Changed');
}

function change_shopping_publish()
{
    global $product_label,$script_name;

    $db = new DB;
    $ids = get_form_field('ids');
    $id_array = explode(',',$ids);
    $modules = array();
    call_shopping_event('module_info',array(&$modules));
    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->set_body_id('change_product_status');
    $dialog->set_help('change_product_status');
    $dialog->start_body('Change '.$product_label.' Shopping Publish Flags');
    $dialog->set_button_width(133);
    $dialog->start_button_column();
    $dialog->add_button('Change Flags','images/AdminUsers.png',
                        'update_shopping_publish();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form($script_name,'ChangeShoppingPublish');
    $dialog->add_hidden_field('ids',$ids);
    $dialog->start_field_table();
    display_product_change_choices($db,$dialog,$id_array);
    $dialog->write('<tr><td class="fieldprompt" style="text-align: ' .
                   'left;" colspan="2">'.$product_label .
                   ' Shopping Publish Flags:'."<br><br>\n");
    $max_shopping_flag = -1;
    foreach ($modules as $module_info) {
       $dialog->add_checkbox_field('shopping_flag'.$module_info['flag'],
                                   $module_info['name'],false);
       $dialog->write("<br>\n");
       if ($module_info['flag'] > $max_shopping_flag)
          $max_shopping_flag = $module_info['flag'];
    }
    if ($max_shopping_flag != -1)
       $dialog->add_hidden_field('MaxShoppingFlag',$max_shopping_flag);
    $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_shopping_publish()
{
    global $product_label;

    set_time_limit(0);
    $db = new DB;
    $max_shopping_flag = get_form_field('MaxShoppingFlag');
    if ($max_shopping_flag !== null)
       $shopping_flags = parse_shopping_flags($max_shopping_flag);
    else $shopping_flags = 0;
    $id_array = parse_product_change_choices($db);
    if (count($id_array) == 0) {
       http_response(201,'No '.$product_label.' Shopping Publish Flags Changed');
       return;
    }

    $activity_user = get_product_activity_user($db);
    $product_record = product_record_definition();
    foreach ($id_array as $product_id) {
       if (! fill_product_record($db,$product_id,$product_record,$error)) {
          http_response(422,$error);   return;
       }
       $product_record['last_modified']['value'] = time();
       $product_record['shopping_flags']['value'] = $shopping_flags;
       if (! update_product_record($db,$product_record,$error,null,true)) {
          http_response(422,$error);   return;
       }
       log_activity('Updated Shopping Publish Flags for '.$product_label.' ' .
                    $product_record['name']['value'].' (#'.$product_id.')');
       write_product_activity($product_label.' Shopping Publish Flags set to ' .
          $shopping_flags.' by '.$activity_user,$product_id,$db);
    }
    http_response(201,$product_label.' Shopping Publish Flags Changed');
}

function rebuild_cache($bg_flag)
{
    global $shopping_cart;

    set_time_limit(0);
    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $query = 'select count(id) as num_products from products p';
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,'No Products Found');
       return;
    }
    $num_products = $row['num_products'];
    $num_pages = ceil($num_products / 50);

    if (($num_products > 100) && (! $bg_flag)) {
       $spawn_result = spawn_program($prefix.'products.php rebuildcache');
       if ($spawn_result != 0)
          http_response(422,'Rebuild Cache Request returned '.$spawn_result);
       else http_response(202,'Submitted Rebuild Cache Request');
       return;
    }

    log_activity('Rebuilding Cached Pages for '.$num_products.' Products');
    for ($product_page = 0;  $product_page < $num_pages;  $product_page++) {
       if (! $bg_flag) print 'Page #'.$product_page."\n";  // keep the web server connection alive
       $command = $prefix.'products.php rebuildcache '.$product_page;
       $process = new Process($command);
       if ($process->return != 0) {
          http_response(422,'Unable to start cache rebuild ('.$process->return.')');
          return;
       }
       $counter = 0;
       while ($process->status()) {
          if ($counter == 500) {
             $process->stop();
             $error = 'Cache Rebuild took too long';
             if ($bg_flag) log_error($error);
             else http_response(422,$error);
             return;
          }
          sleep(1);
          $counter++;
       }
    }
    if (! $bg_flag) http_response(201,'Cache Rebuilt');
    log_activity('Rebuilt Cached Pages for '.$num_products.' Products');
}

function process_rebuild_cache($product_page)
{
    set_time_limit(0);

    $db = new DB;
    $query = 'select id,flags,seo_url from products p order by id';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(201,'No Products Found');
       return;
    }

    $num_products = count($rows);
    $start_line = $product_page * 50;
    $end_line = $start_line + 50;
    if ($end_line > $num_products) $end_line = $num_products;
    $product_line = 0;
    log_activity('Rebuilding Cached Pages for Products #'.($start_line + 1) .
                 '-' .$end_line);

    foreach ($rows as $row) {
       if ($product_line < $start_line) {
          $product_line++;   continue;
       }
       $flags = $row['flags'];   $seo_url = $row['seo_url'];
       generate_cached_product_page($db,$row['id'],-1,$flags,$flags,$seo_url,
                                    $seo_url,true);
       $product_line++;
       if ($product_line == $end_line) break;
    }
}

function process_update_cache($product_id)
{
    $db = new DB;
    $cached_categories = load_category_pages(null,$db,$product_id);
    update_cached_category_pages($db,$cached_categories);
}

function ajax_generate_cached_page()
{
    global $products_table;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select flags,seo_url from '.$products_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       http_response(422,$db->error);   return;
    }
    $edit_type = get_form_field('EditType');
    $flags = $row['flags'];
    $seo_url = $row['seo_url'];
    $product_record['flags']['value'] = $flags;
    if ($edit_type == ADDRECORD) {
       $old_flags = NULL;   $old_seo_url = NULL;
    }
    else {
       $old_flags = get_form_field('OldFlags');
       $old_seo_url = get_form_field('OldSeoUrl');
    }
    generate_cached_product_page($db,$id,$edit_type,$flags,$old_flags,$seo_url,
                                 $old_seo_url);
    http_response(201,'Cached Page Generated');
}

function resequence_products()
{
    $db = new DB;
    $query = 'select c.id,c.parent,c.sequence from category_products c left ' .
             'join products p on c.related_id=p.id order by c.parent,' .
             'c.sequence,p.name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print "Query: ".$query."<br>\n";
          print "Database Error: ".$db->error."<br>\n";
       }
       else print "No Products Found to Resequence<br>\n";
       return;
    }
    $current_parent = -1;
    foreach ($rows as $row) {
       if ($row['parent'] != $current_parent) {
          $sequence = 1;   $current_parent = $row['parent'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update category_products set sequence='.$sequence .
                   ' where id=?';
          $query = $db->prepare_query($query,$row['id']);
          if (! $db->query($query)) {
             print "Query: ".$query."<br>\n";
             print "Database Error: ".$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced Category Products');
    print "Resequenced Category Products<br>\n";
}

function resequence_attributes()
{
    $db = new DB;
    $query = 'select p.id,p.parent,p.sequence from product_attributes p left ' .
             'join attributes a on p.related_id=a.id order by p.parent,' .
             'p.sequence,a.name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print "Query: ".$query."<br>\n";
          print "Database Error: ".$db->error."<br>\n";
       }
       else print "No Product Attributes Found to Resequence<br>\n";
       return;
    }
    $current_parent = -1;
    foreach ($rows as $row) {
       if ($row['parent'] != $current_parent) {
          $sequence = 1;   $current_parent = $row['parent'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update product_attributes set sequence='.$sequence .
                   ' where id=?';
          $query = $db->prepare_query($query,$row['id']);
          if (! $db->query($query)) {
             print "Query: ".$query."<br>\n";
             print "Database Error: ".$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced Product Attributes');
    print "Resequenced Product Attributes<br>\n";
}

function update_product_id($db,$table,$field,$condition,$old_id,$new_id)
{
    $query = 'update '.$table.' set '.$field.'=? where ('.$field .
             '=?)';
    if ($condition) $query .= ' and ('.$condition.')';
    $query = $db->prepare_query($query,$new_id,$old_id);
    if (! $db->query($query)) {
       print "Query: ".$query."<br>\n";
       print "Database Error: ".$db->error."<br>\n";
       exit;
    }
}

function reset_msg($msg,$cgi)
{
    print $msg;
    if ($cgi) {
       print "<br>\n";   flush();
    }
    else print "\n";
}

function reset_product_ids($cgi=true)
{
    global $base_product_url;

    set_time_limit(0);
    $db = new DB;
    $query = 'select * from products order by id';
    $products = $db->get_records($query);
    if (! $products) {
       if (isset($db->error)) {
          reset_msg('Query: '.$query,$cgi);
          reset_msg('Database Error: '.$db->error,$cgi);
       }
       else reset_msg('No Products Found to Reset',$cgi);
       return;
    }

    $new_id = 1;
    foreach ($products as $row) {
       $old_id = $row['id'];
       if ($old_id == $new_id) {
          reset_msg('Skipping Product #'.$old_id.' (no change)',$cgi);
          $new_id++;   continue;
       }
       reset_msg('Moving Product #'.$old_id.' to #'.$new_id,$cgi);
       $flags = $row['flags'];
       $shopping_flags = $row['shopping_flags'];
       update_product_id($db,'products','id',null,$old_id,$new_id);
       update_product_id($db,'cart_items','product_id',null,$old_id,$new_id);
       update_product_id($db,'wishlist_items','product_id',null,$old_id,$new_id);
       update_product_id($db,'account_products','related_id',null,$old_id,$new_id);
       update_product_id($db,'order_items','product_id',null,$old_id,$new_id);
       update_product_id($db,'category_products','related_id',null,$old_id,$new_id);
       update_product_id($db,'related_products','parent',null,$old_id,$new_id);
       update_product_id($db,'related_products','related_id',null,$old_id,$new_id);
       update_product_id($db,'product_attributes','parent',null,$old_id,$new_id);
       update_product_id($db,'product_inventory','parent',null,$old_id,$new_id);
       update_product_id($db,'product_data','parent',null,$old_id,$new_id);
       update_product_id($db,'product_discounts','parent',null,$old_id,$new_id);
       update_product_id($db,'images','parent','parent_type=1',$old_id,$new_id);
       update_product_id($db,'coupon_products','related_id',null,$old_id,$new_id);
       update_product_id($db,'registry_items','product_id',null,$old_id,$new_id);
       update_product_id($db,'schedule_items','related_id','parent_type=1',
                         $old_id,$new_id);
       update_product_id($db,'reviews','parent',null,$old_id,$new_id);
       call_shopping_event('update_product_id',
                           array($db,$row,$old_id,$new_id,$cgi));

       if ($flags & (FEATURED|UNIQUEURL)) {
          if (! isset($base_product_url)) $base_product_url = 'products/';
          $seo_url = $row['seo_url'];
          if ((! $seo_url) || ($seo_url == ''))
             $seo_url = $base_product_url.$product_id;
          reset_msg('   Updating Unique URL '.$seo_url,$cgi);
          update_htaccess(1,$new_id,$seo_url,$row['websites'],$db,$old_id);
       }
       $new_id++;
    }

    log_activity('Reset All Product IDs');
    reset_msg('Reset All Product IDs',$cgi);
}

function select_product()
{
    global $product_label,$products_label,$products_table,$features;
    global $script_name,$name_prompt;
    global $image_subdir_prefix,$use_dynamic_images;

    if (function_exists("custom_select_product") && custom_select_product())
       return;

    $status_values = load_cart_options(PRODUCT_STATUS);
    $frame = get_form_field('frame');
    $multiple = get_form_field('multiple');
    $select_image = get_form_field('selectimage');
    $change_function = get_form_field('changefunction');
    if ($select_image) {
       if (! isset($image_subdir_prefix)) $image_subdir_prefix = null;
       if (! isset($use_dynamic_images)) $use_dynamic_images = false;
    }
    $select_type = get_form_field('type');
    $include_inventory = get_form_field('inventory');

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('products.css');
    $dialog->add_style_sheet('utility.css');
    if ($select_image) $dialog->add_style_sheet('image.css');
    $dialog->add_script_file('products.js');
    if ($include_inventory) $dialog->add_script_file('inventory.js');
    if (file_exists("../admin/custom-config.js"))
       $dialog->add_script_file("../admin/custom-config.js");
    $script = "<script>\n";
    if ($features & USE_PART_NUMBERS)
       $script .= "       use_part_numbers = true;\n";
    $script .= "       products_label = '".$products_label."';\n";
    $script .= "       products_table = '".$products_table."';\n";
    $script .= "       script_name = '".$script_name."';\n";
    if ($select_image) {
       if ($use_dynamic_images)
          $script .= "       use_dynamic_images = true;\n";
       if ($image_subdir_prefix)
          $script .= "       image_subdir_prefix = " .
                         $image_subdir_prefix.";\n";
    }
    if ($multiple == 'true') $script .= "       select_multiple = true;\n";
    $account_id = get_form_field('account');
    if ($account_id) $script .= "       account_id = ".$account_id.";\n";
    if ($select_type !== null)
       $script .= "       select_type = ".$select_type.";\n";
    $script .= "    </script>";
    $dialog->add_head_line($script);
    $styles = "<style type=\"text/css\">\n";
    $styles .= "      #products_grid .aw-column-3 { text-align: center; }\n";
    $styles .= "    </style>";
    $dialog->add_head_line($styles);
    add_script_prefix($dialog,null);
    $dialog_title = 'Select '.$product_label;
    if ($select_image) {
       $dialog_title .= ' Image';
       $select_function = 'select_product_image();';
    }
    else $select_function = 'select_product();';
    $dialog->set_body_id('select_product');
    $dialog->set_help('select_product');
    if (function_exists("custom_init_select_product_dialog"))
       custom_init_select_product_dialog($dialog);
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button("Select","images/Update.png",$select_function);
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    add_search_box($dialog,"search_products","reset_search");
    $dialog->end_button_column();
    $dialog->write("\n          <script>\n");
    $dialog->write("             var select_frame = '".$frame."';\n");
    if ($change_function) 
       $dialog->write("             select_product_change_function = '" .
                      $change_function."';\n");
    $dialog->write("             var product_status_values = [");
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $dialog->write(",");
       if (isset($status_values[$loop]))
          $dialog->write("\"".$status_values[$loop]."\"");
       else $dialog->write("\"\"");
    }
    $dialog->write("];\n");
    $dialog->write("             var name_prompt = '".$name_prompt."';\n");
    $dialog->write("             load_grid(false");
    if ($include_inventory) $dialog->write(',true');
    $dialog->write(");\n");
    $dialog->write("          </script>\n");
    if ($select_image) {
       $dialog->write("        <br>\n");
       $dialog->write("        <div id=\"sample_image_div\" class=\"" .
                      "select_image_div\"></div>\n");
    }
    if ($include_inventory) {
       $dialog->write("        <script type=\"text/javascript\">\n");
       $db = new DB;
       $query = 'select id from products order by name limit 1';
       $product_row = $db->get_record($query);
       if ($product_row) $product_id = $product_row['id'];
       else $product_id = 0;
       $dialog->write('          create_select_inventory_grid(' .
                      $product_id.");\n");
       $dialog->write("        </script>\n");
    }
    $dialog->end_body();
}

function list_product_images()
{
    $id = get_form_field('id');
    $db = new DB;
    $query = 'select filename from images where parent_type=1 and parent=?';
    $query = $db->prepare_query($query,$id);
    $images = $db->get_records($query);
    if (! $images) {
       if (isset($db->error)) http_response(422,$db->error);
       return;
    }
    while (list($index,$image) = each($images)) {
       $image['filename'] = str_replace("\"","\\\"",$image['filename']);
       print 'product_images['.$index.']="'.$image['filename'].'"; ';
    }
}

function add_product_data_button($icon,$funct,$alt)
{
?>
            <table cellspacing=0 cellpadding=0 class="dialog_button">
              <tr onMouseOver="button_mouseover(this);" onMouseOut="button_mouseout(this);"
                onClick="<? print $funct; ?>" class="button_out" valign="middle">
                <td><img src="<? print $icon ?>" alt="<?
   print $alt; ?>" title="<? print $alt; ?>"></td>
              </tr>
            </table>
<?
}

function add_product_data_grid($dialog,$product_id,$data_type,$single_label,
                               $multi_label,$edit_type,$height,$no_prompt)
{
    global $shopping_cart,$script_name;

    $dialog->write("        <table cellspacing=\"0\" cellpadding=\"0\"");
    if ($dialog->skin) $dialog->write(" width=\"100%\"");
    $dialog->write("><tr valign=\"middle\">\n");
    $dialog->write("          <td id=\"product_data_".$data_type."_cell\">" .
                   "<script>add_product_data_grid(".$product_id.",".$data_type .
                   ",\"");
    if ($shopping_cart) $dialog->write("../cartengine/");
    $dialog->write("productdata.php\",");
    if ($edit_type == UPDATERECORD) {
       if (get_form_field('insidecms')) $frame = 'smartedit';
       else {
          $frame = get_form_field('frame');
          if (! $frame) $frame = 'edit_product';
       }
       $dialog->write('"'.$frame.'","EditProduct"');
    }
    else {
       $frame = get_form_field('frame');
       if (! $frame) $frame = 'add_product';
       $dialog->write('"'.$frame.'","AddProduct"');
    }
    $dialog->write(",'".$single_label."','".$multi_label."',".$height.",");
    if ($no_prompt) $dialog->write("true");
    else $dialog->write("false");
    $dialog->write(");</script></td>\n");
    $dialog->write("          <td id=\"data_sequence_buttons\" width=\"30\" " .
                   "nowrap align=\"center\">\n");
    add_product_data_button("images/MoveTop.png","move_data_top(" .
                            $data_type.");","Top");
    add_product_data_button("images/MoveUp.png","move_data_up(" .
                            $data_type.");","Up");
    add_product_data_button("images/MoveDown.png","move_data_down(" .
                            $data_type.");","Down");
    add_product_data_button("images/MoveBottom.png","move_data_bottom(" .
                            $data_type.");","Bottom");
    $dialog->write("          </td>\n");
    $dialog->write("          <td id=\"data_function_buttons\" width=\"30\" " .
                   "nowrap align=\"center\">\n");
    add_product_data_button("images/AddData.png","add_data(" .
                            $data_type.");","Add");
    $dialog->write("            <br>\n");
    add_product_data_button("images/DeleteData.png","delete_data(" .
                            $data_type.");","Delete");
    $dialog->write("          </td>\n");
    $dialog->write("        </tr></table>\n");
}

function add_attribute_set()
{
    $set_id = get_form_field('Set');
    $product_id = get_form_field('Product');

    $db = new DB;
    $query = 'select * from attribute_set_attributes where parent=?';
    $query = $db->prepare_query($query,$set_id);
    $attributes = $db->get_records($query);
    if (! $attributes) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(410,'Attribute Set #'.$set_id.' Not Found');
       return;
    }
    $sublist_record = sublist_record_definition();
    $sublist_record['parent']['value'] = $product_id;
    foreach ($attributes as $attribute) {
       $sublist_record['related_id']['value'] = $attribute['related_id'];
       $sublist_record['sequence']['value'] = $attribute['sequence'];
       if (! $db->insert('product_attributes',$sublist_record)) {
          http_response(422,$db->error);   return;
       }
    }

    http_response(201,'Added Attribute Set');
    log_activity('Added Attribute Set #'.$set_id.' to Product #'.$product_id);
}

function delete_all_attributes()
{
    $id = get_form_field('id');
    $db = new DB;
    if (! delete_sublist_items('product_attributes',$id,$db)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Product Attributes Deleted');
    log_activity('Deleted All Product Attributes for Product #'.$id);
}

if ((! $bg_command) && (! check_login_cookie())) exit;

init_images($script_name,'products.js',$image_parent_type);
init_sublists($script_name,'products.js',1);

if ($bg_command == 'rebuildcache') {
   if ($argc == 2) rebuild_cache(true);
   else process_rebuild_cache($argv[2]);
   DB::close_all();   exit(0);
}
else if ($bg_command == 'updatecache') {
   process_update_cache($argv[2]);   DB::close_all();   exit(0);
}
else if ($bg_command == 'resetids') {
   reset_product_ids(false);   DB::close_all();   exit(0);
}

$cmd = get_form_field('cmd');

if ($cmd == 'createproduct') create_product();
else if ($cmd == 'addproduct') add_product();
else if ($cmd == 'processaddproduct') process_add_product();
else if ($cmd == 'editproduct') edit_product();
else if ($cmd == 'loadproduct') load_product();
else if ($cmd == 'loadimports') load_imports();
else if ($cmd == 'loadseocategories') load_seo_categories();
else if ($cmd == 'updateproduct') update_product();
else if ($cmd == 'copyproduct') copy_product();
else if ($cmd == 'deleteproduct') delete_product();
else if ($cmd == 'editmultiple') edit_multiple_products();
else if ($cmd == 'editmultiplecontinue') edit_multiple_continue();
else if ($cmd == 'updatemultiple') update_multiple_products();
else if ($cmd == 'changeproductstatus') change_product_status();
else if ($cmd == 'updateproductstatus') update_product_status();
else if ($cmd == 'changeprices') change_prices();
else if ($cmd == 'updateprices') update_prices();
else if ($cmd == 'changeshoppingpublish') change_shopping_publish();
else if ($cmd == 'updateshoppingpublish') update_shopping_publish();
else if ($cmd == 'rebuildcache') rebuild_cache(false);
else if ($cmd == 'generatecachedpage') ajax_generate_cached_page();
else if ($cmd == 'resequenceproducts') resequence_products();
else if ($cmd == 'resequenceattributes') resequence_attributes();
else if ($cmd == 'resetids') reset_product_ids();
else if ($cmd == 'shoppingfield') select_shopping_field();
else if ($cmd == 'listimages') list_product_images();
else if ($cmd == 'addimage') add_image();
else if ($cmd == 'processaddimage') process_add_image();
else if ($cmd == 'processuploadedimage') process_uploaded_image();
else if ($cmd == 'updateimagefile') update_image_file();
else if ($cmd == 'editimage') edit_image();
else if ($cmd == 'updateimage') update_image();
else if ($cmd == 'getimageinfo') get_image_info();
else if ($cmd == 'deleteimage') delete_image();
else if ($cmd == 'resequenceimages') resequence_images();
else if ($cmd == 'sequenceimages') sequence_images();
else if ($cmd == 'addattributeset') add_attribute_set();
else if ($cmd == 'deleteallattributes') delete_all_attributes();
else if ($cmd == 'rebuildhtaccess') rebuild_htaccess();
else if ($cmd == 'initseo') initialize_seo_urls();
else if ($cmd == 'selectproduct') select_product();
else if ($cmd == 'testcertificate') {
   require_once '../admin/modules/giftcertificates/admin.php';
   test_certificate();
}
else if (($use_discounts || $use_qty_pricing) &&
         process_discount_command($cmd)) {}
else if (process_sublist_command($cmd)) {}
else if (function_exists('process_inventory_command') &&
         process_inventory_command($cmd)) {}
else if (function_exists('process_product_command') &&
         process_product_command($cmd)) {}
else if (substr($cmd,-7) == 'callout') {
   require_once 'callouts.php';   process_callout_command($cmd);
}
else {
   require_once '../engine/modules.php';
   if (call_module_event('custom_command',array('products',$cmd),
                         null,true,true)) {}
   else display_products_screen();
}

DB::close_all();

?>