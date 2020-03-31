<?php
/*
                     Inroads Shopping Cart - Categories Tab

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
require_once 'seo.php';
require_once 'utility.php';
require_once 'catalogconfig-common.php';
if (file_exists('../cartengine/adminperms.php')) {
   $shopping_cart = true;
   require_once 'cartconfig-common.php';
   $features = get_cart_config_value('features');
   if ($features & REGULAR_PRICE_BREAKS) $use_price_breaks = true;
   else $use_price_breaks = false;
}
else {
   $shopping_cart = false;
   require_once 'catalog-common.php';
   $features = $catalog_features;
   $use_price_breaks = false;
}
require_once 'categories-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';
if ($use_price_breaks) require_once 'pricebreak.php';
if (! isset($category_fields)) $category_fields = array();
if (! isset($category_tabs))
   $category_tabs = array('category' => true,'image' => true,
                       'subcategories' => true,'parentcategories' => true,
                       'products' => true,'seo' => true);
if (! isset($products_label)) $products_label = $product_label.'s';
if (! isset($products_script_name)) $products_script_name = 'products.php';
if (! isset($categories_label)) $categories_label = 'Categories';
if (! isset($subcategories_label)) $subcategories_label = 'Subcategories';
if (! isset($products_table)) $products_table = 'products';
if (! isset($enable_category_filter_search))
   $enable_category_filter_search = false;

if ($bg_command) $default_base_href = $ssl_url;
else $default_base_href = get_current_url();

$category_tab_labels = array();
$category_tab_order = array();

function add_script_prefix(&$screen,$dialog_title)
{
    global $shopping_cart,$products_script_name,$admin_path,$cms_base_url;
    global $use_dynamic_images,$sample_image_size,$dynamic_image_url;
    global $categories_table,$subcategories_table,$category_products_table;
    global $script_name,$image_subdir_prefix,$enable_category_filter_search;
    global $enable_multisite;

    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix='../cartengine/';\n";
    $head_block .= "      categories_table = '".$categories_table."';\n";
    $head_block .= "      subcategories_table = '".$subcategories_table."';\n";
    $head_block .= "      category_products_table = '" .
                   $category_products_table."';\n";
    $head_block .= "      script_name = '".$script_name."';\n";
    $head_block .= "      products_script_name = '".$products_script_name .
                   "';\n";
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
    if ($enable_category_filter_search)
       $head_block .= "      enable_category_filter_search = true;\n";
    if (! empty($enable_multisite))
       $head_block .= '      enable_multisite = true;'."\n";
    $head_block .= "    </script>";
    $screen->add_head_line($head_block);
}

function add_update_function(&$dialog,$id)
{
    global $category_updates_rebuild_web_site;

    $sublist = get_form_field("sublist");
    if (! $sublist) {
       if (isset($category_updates_rebuild_web_site) &&
           $category_updates_rebuild_web_site) {
          $script = "<script>rebuild_web_site = true;</script>";
          $dialog->add_head_line($script);
       }
       return;
    }
    $update_window = get_form_field("updatewindow");
    $frame_name = get_form_field("frame");
    $side = get_form_field("side");
    if (! $id) $id = 'null';
    $script = "<script>\n" .
              "      update_window = '".$frame_name."';\n";
    if (isset($category_updates_rebuild_web_site) &&
        $category_updates_rebuild_web_site)
       $script .= "      rebuild_web_site = true;\n";
    $script .= "      function update_sublist() {\n" .
              "         var iframe = top.get_dialog_frame('".$update_window."').contentWindow;\n" .
              "         iframe.".$sublist.".update('".$side."',".$id.");\n" .
              "      }\n" .
              "    </script>";
    $dialog->add_head_line($script);
}

function add_category_variables(&$dialog)
{
    global $category_label,$url_prefix,$base_url;

    $script = "<script>\n";
    $script .= "      var category_label = '".$category_label."';\n";
    $script .= "      url_prefix = '".$url_prefix."';\n";
    $default_base_href = get_current_url();
    $script .= "      default_base_href = '".$default_base_href."';\n";
    $script .= "      base_url = '".$base_url."';\n";
    if (function_exists("add_custom_category_dialog_variables"))
       $script .= add_custom_category_dialog_variables();
    $script .= "    </script>";
    $dialog->add_head_line($script);
}

function add_category_filter_row($screen,$prompt,$field_name,$data,$use_index,
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
                   "onChange=\"filter_categories();\" " .
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

function add_category_filters($screen,$status_values,$db)
{
    global $category_types;

    if (isset($category_types))
       add_category_filter_row($screen,'Category Type','category_type',
                              $category_types,true,'All');
    add_category_filter_row($screen,'Status','status',$status_values,
                           true,'All');
    if (function_exists('add_custom_category_filters'))
       add_custom_category_filters($screen,$db);
}

function display_categories_screen()
{
    global $products_label,$category_label,$categories_label,$script_name;
    global $subcategories_label,$cache_catalog_pages,$category_types;

    $db = new DB;
    $status_values = load_cart_options(CATEGORY_STATUS,$db);
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet("categories.css");
    $screen->add_style_sheet("utility.css");
    $screen->add_script_file("categories.js");
    if (file_exists("../admin/custom-config.js"))
       $screen->add_script_file("../admin/custom-config.js");
    add_script_prefix($screen,null);
    add_website_js_array($screen,$db);
    $screen->set_body_id('categories');
    $screen->set_help('categories');
    $screen->start_body(filemtime($script_name));
    if ($screen->skin) {
       $screen->start_title_bar($categories_label);
       $screen->start_title_filters();
       add_category_filters($screen,$status_values,$db);
       add_search_box($screen,"search_categories","reset_search");
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $label_length = strlen($category_label);
    if ($label_length > 8) $button_width = ($label_length * 5) + 100;
    else $button_width = 148;
    $screen->set_button_width($button_width);
    $screen->start_button_column();
    $screen->add_button("Add ".$category_label,"images/AddCategory.png",
                        "add_category();",null,true,false,ADD_BUTTON);
    $screen->add_button("Edit ".$category_label,"images/EditCategory.png",
                        "edit_category();",null,true,false,EDIT_BUTTON);
    $screen->add_button("Delete ".$category_label,"images/DeleteCategory.png",
                        "delete_category();",null,true,false,DELETE_BUTTON);
    $screen->add_button('View '.$category_label,'images/AdminUsers.png',
                        'view_category();');
    if (! empty($cache_catalog_pages))
       $screen->add_button("Rebuild Cache","images/AdminUsers.png",
                           "rebuild_category_cache();");
    if (function_exists("display_custom_category_buttons"))
       display_custom_category_buttons($screen,$db);
    if (! $screen->skin) {
       add_category_filters($screen,$status_values,$db);
       add_search_box($screen,"search_categories","reset_search");
    }
    $screen->end_button_column();
    $product_dialog_height = get_product_screen_height($db);
    $screen->write("\n          <script>\n");
    $screen->write("             var product_dialog_height = " .
                   $product_dialog_height.";\n");
    $screen->write("             var category_status_values = [");
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $screen->write(",");
       if (isset($status_values[$loop]))
          $screen->write("\"".$status_values[$loop]."\"");
       else $screen->write("\"\"");
    }
    $screen->write("];\n");
    if (isset($category_types)) {
       $screen->write("             var category_types = [");
       for ($loop = 0;  $loop < count($category_types);  $loop++) {
          if ($loop > 0) $screen->write(",");
          if (isset($category_types[$loop]))
             $screen->write("\"".$category_types[$loop]."\"");
          else $screen->write("\"\"");
       }
       $screen->write("];\n");
    }
    if (function_exists("write_custom_category_variables"))
       write_custom_category_variables($screen,$db);
    $screen->write("             products_label = '".$products_label."';\n");
    $screen->write("             category_label = '".$category_label."';\n");
    $screen->write("             categories_label = '".$categories_label."';\n");
    $screen->write("             subcategories_label = '" .
                   $subcategories_label."';\n");
    $screen->write("             load_grid();\n");
    $screen->write("          </script>\n");
    $screen->end_body();
}

function add_category_tab($new_tab,$label,$before_order=null)
{
    global $category_tab_labels,$category_tab_order;

    $category_tab_labels[$new_tab] = $label;
    if ($before_order == null) $category_tab_order[] = $new_tab;
    else {
       $insert_pos = -1;
       foreach ($category_tab_order as $index => $tab_name)
          if ($tab_name == $before_order) {
             $insert_pos = $index;   break;
          }
       if ($insert_pos == -1) $category_tab_order[] = $new_tab;
       else array_splice($category_tab_order,$insert_pos,0,array($new_tab));
    }
}

function remove_category_tab($tab)
{
    global $category_tab_labels,$category_tab_order;

    unset($category_tab_labels[$tab]);
    foreach ($category_tab_order as $index => $tab_name)
       if ($tab_name == $tab) {
          unset($category_tab_order[$index]);   break;
       }
}

function init_category_tabs($row)
{
    global $category_tabs,$category_label,$categories_label;
    global $subcategories_label,$products_label;

    if ($category_tabs['category']) add_category_tab("category",$category_label);
    if (isset($category_tabs['specs']) && $category_tabs['specs'])
       add_category_tab("specs","Specs");
    if ($category_tabs['image']) add_category_tab("image","Images");
    if ($category_tabs['subcategories'])
       add_category_tab("subcategory",$subcategories_label);
    if ($category_tabs['parentcategories'])
       add_category_tab("parentcategory","Parent ".$categories_label);
    if ($category_tabs['products'])
       add_category_tab("product",$products_label);
    if ($category_tabs['seo']) add_category_tab("seo","SEO");
    if (function_exists("setup_category_tabs")) setup_category_tabs($row);
}

function add_category_template_row($dialog,$prompt,$field_name,$row,$templates,
                                   $rows_option=false)
{
    $template = get_row_value($row,$field_name);
    $dialog->start_row($prompt,'middle','fieldprompt','cat_template_cell');
    $dialog->start_choicelist($field_name);
    $dialog->add_list_item('','Default Template',(! $template));
    if ($templates) foreach ($templates as $filename)
       $dialog->add_list_item($filename,$filename,$template == $filename);
    $dialog->end_choicelist();
    if ($rows_option) {
       $dialog->add_inner_prompt('# Product Rows:');
       $template_rows = get_row_value($row,'template_rows');
       if (! $template_rows) $template_rows = 1;
       $dialog->add_radio_field('template_rows',1,1,($template_rows == 1));
       $dialog->add_radio_field('template_rows',2,2,($template_rows == 2));
       $dialog->add_radio_field('template_rows',3,3,($template_rows == 3));
    }
    $dialog->end_row();
}

function load_category_filters($dialog,$db,$row)
{
    global $products_table,$product_types,$product_label;

    $query = 'select field_label,field_name,option_table from catalog_fields ' .
             'where filter=1 order by filter_sequence';
    $catalog_fields = $db->get_records($query);
    if (! $catalog_fields) return;
    $query = 'select * from category_filters where parent=?';
    $query = $db->prepare_query($query,$row['id']);
    $category_filters = $db->get_records($query,'field_name','field_values');
    $query = 'select * from cart_options order by table_id,label';
    $options_list = $db->get_records($query);
    if (isset($product_types)) {
       if (isset($category_filters['product_type']))
          $product_type = $category_filters['product_type'];
       else $product_type = '';
       if ($product_type === '') $product_type = -1;
       $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: " .
                      "left;\">".$product_label." Type:<br>\n");
       reset($product_types);
       if (count($product_types) > 8) {
          $dialog->start_choicelist('filter^product_type');
          $dialog->add_list_item('','All',($product_type == -1));
          foreach ($product_types as $type_value => $type_label)
             $dialog->add_list_item($type_value,$type_label,
                                    $product_type == $type_value);
          $dialog->end_choicelist();
       }
       else {
          $dialog->add_radio_field('filter^product_type','','All&nbsp;&nbsp;',
                                   ($product_type == -1));
          foreach ($product_types as $type_value => $type_label)
             $dialog->add_radio_field('filter^product_type',$type_value,
                $type_label.'&nbsp;&nbsp;',($product_type == $type_value));
       }
       $dialog->end_row();
    }
    foreach ($catalog_fields as $catalog_field) {
       $field_name = $catalog_field['field_name'];
       if (isset($category_filters[$field_name]))
          $field_values = explode('|',$category_filters[$field_name]);
       else $field_values = array();
       $option_table = $catalog_field['option_table'];
       $filter_values = array();
       if ($option_table) {
          if ($options_list) foreach ($options_list as $option) {
             if ($option['table_id'] == $option_table)
                $filter_values[$option['id']] = $option['label'];
          }
       }
       else {
          $query = 'select distinct '.$field_name.' from '.$products_table .
                   ' order by '.$field_name;
          $filter_values = $db->get_records($query,null,$field_name);
       }
       $dialog->write("<tr><td class=\"fieldprompt\" style=\"text-align: " .
                      "left;\">".$catalog_field['field_label'].":<br>\n");
       $dialog->start_table(null,'category_filters_row',0,1);
       $dialog->write('<tr valign="top">');
       if ($filter_values && (count($filter_values) > 0)) {
          $index = 0;
          foreach ($filter_values as $option_id => $filter_value) {
             if (! $filter_value) continue;
             if (($index > 2) && (($index % 4) == 0))
                $dialog->write('<tr valign="top">');
             $dialog->write("<td width=\"25%\">");
             if ($option_table) {
                $check_name = 'filter^'.$field_name.'^'.$option_id;
                $checked = in_array($option_id,$field_values);
             }
             else {
                $encoded_value = urlencode($filter_value);
                $check_name = 'filter^'.$field_name.'^'.$encoded_value;
                $checked = in_array($filter_value,$field_values);
             }
             $dialog->add_checkbox_field($check_name,$filter_value,$checked);
             $dialog->write("</td>\n");
             if (($index % 4) == 3) $dialog->write("</tr>\n");
             $index++;
          }
          if ($index < 4) while ($index < 4) {
             $dialog->write("<td>&nbsp;</td>");
             if (($index % 4) == 3) $dialog->write("</tr>\n");
             $index++;
          }
          if (($index % 4) != 0) $dialog->write("</tr>\n");
       }
       $dialog->end_table();
       $dialog->end_row();
    }
}

function save_category_filters($db,$category_record)
{
    global $product_types;

    $id = $category_record['id']['value'];
    $old_products_source = get_form_field('old_products_source');
    if ($old_products_source == 1) {
       $query = 'delete from category_filters where parent=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return false;
       }
    }
    if ($category_record['products_source']['value'] != 1) return true;
    $query = 'select field_name from catalog_fields where filter=1 ' .
             'order by filter_sequence';
    $catalog_fields = $db->get_records($query,null,'field_name');
    if (! $catalog_fields) return true;
    $category_filter_record = category_filter_record_definition();
    $category_filter_record['parent']['value'] = $id;
    if (isset($product_types)) {
       $product_type = get_form_field('filter^product_type');
       if ($product_type !== '') {
          $category_filter_record['field_name']['value'] = 'product_type';
          $category_filter_record['field_values']['value'] = $product_type;
          if (! $db->insert('category_filters',$category_filter_record)) {
             http_response(422,$db->error);   return false;
          }
       }
    }
    $form_fields = get_form_fields();
    foreach ($catalog_fields as $catalog_field_name) {
       $category_filter_record['field_name']['value'] = $catalog_field_name;
       $field_values = '';
       foreach ($form_fields as $field_name => $checked) {
          if (strpos($field_name,'^') === false) continue;
          $parts = explode('^',$field_name);
          if ($parts[0] != 'filter') continue;
          if ($parts[1] != $catalog_field_name) continue;
          if ($checked != 'on') continue;
          if ($field_values) $field_values .= '|';
          $field_values .= urldecode($parts[2]);
       }
       if (! $field_values) continue;
       $category_filter_record['field_values']['value'] = $field_values;
       if (! $db->insert('category_filters',$category_filter_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    return true;
}

function display_category_fields($dialog,$edit_type,$id,$row,$db)
{
    global $name_prompt,$product_label,$products_label,$script_name;
    global $name_col_width,$default_base_href,$base_url,$category_fields;
    global $category_tabs,$shopping_cart,$features,$prefix;
    global $category_tab_labels,$category_tab_order;
    global $category_label,$categories_label,$subcategories_label;
    global $category_types,$enable_multisite;
    global $categories_table,$subcategories_table,$products_table;
    global $category_products_table,$image_parent_type;
    global $category_products_left_fields,$category_products_right_fields;
    global $category_products_double_click_function,$use_cached_dialogs;
    global $disable_catalog_config,$enable_category_filter_search;

    if (! isset($name_prompt)) $name_prompt = 'Product Name';
    if (! isset($name_col_width)) $name_col_width = 200;
    if (! isset($product_label)) $product_label = 'Product';
    if (! isset($products_label)) $products_label = $product_label.'s';
    if (! isset($category_products_left_fields))
       $category_products_left_fields = 'r.name,r.short_description';
    if (! isset($category_products_right_fields))
       $category_products_right_fields = 'name,short_description';
    if (! isset($use_cached_dialogs)) $use_cached_dialogs = false;
    if (! isset($disable_catalog_config)) $disable_catalog_config = false;

    $status_values = load_cart_options(CATEGORY_STATUS,$db);
    $dialog->add_hidden_field('id',$id);
    if ($edit_type == UPDATERECORD) {
       $dialog->add_hidden_field('old_seo_url',get_row_value($row,'seo_url'));
       $dialog->add_hidden_field('old_websites',get_row_value($row,'websites'));
    }
    if ($enable_category_filter_search) {
       $products_source = get_row_value($row,'products_source');
       if ($products_source === '') $products_source = 0;
    }
    $website_where = get_website_where();

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('category_tab','category_content','change_tab');
    reset($category_tab_order);
    end($category_tab_order);   $last_tab = key($category_tab_order);
    $first_tab = true;
    foreach ($category_tab_order as $index => $tab_name) {
       $tab_sequence = 0;
       if ($first_tab) {
          $tab_sequence |= FIRST_TAB;   $first_tab = false;
       }
       if ($index == $last_tab) $tab_sequence |= LAST_TAB;
       if ($category_tab_labels[$tab_name]{0} == '~') {
          $tab_label = substr($category_tab_labels[$tab_name],1);
          $visible = false;
       }
       else {
          $tab_label = $category_tab_labels[$tab_name];   $visible = true;
       }
       $dialog->add_tab($tab_name.'_tab',$tab_label,'cat_'.$tab_name.'_tab',
                        $tab_name.'_content','change_tab',$visible,null,
                        $tab_sequence);
    }
    $dialog->end_tab_row('category_tab_row_middle');

    if ($category_tabs['category']) {
       $dialog->start_tab_content('category_content',true);
       $dialog->write("<div style=\"position: relative;\">\n");
       add_base_href($dialog,$base_url,true);
       if ($edit_type == UPDATERECORD) {
          $dialog->write('<a id="view_link" class="view_link" href="#" ' .
                         'onClick="view_category_link(); return false;">' .
                         'View</a>'."\n");
          if ($use_cached_dialogs && (! get_form_field('insidecms')) &&
              (! get_form_field('frame'))) {
             $dialog->write("<a id=\"previous_link\" class=\"previous_link\" " .
                            "onClick=\"return previous_category();\" href=\"#\">" .
                            "<< Previous Category</a>\n");
             $dialog->write("<a id=\"next_link\" class=\"next_link\" " .
                            "onClick=\"return next_category();\" href=\"#\">" .
                            "Next Category >></a>\n");
          }
       }
       $dialog->start_field_table('category_table');
       $dialog->add_edit_row($category_label.' Name:','name',$row,80);
       $dialog->add_edit_row('Display Name:','display_name',$row,80);
       $dialog->add_edit_row('Menu Name:','menu_name',$row,80);
       $dialog->start_row($category_label.' Status:','middle');
       $dialog->start_choicelist('status');
       $status = get_row_value($row,'status');
       foreach ($status_values as $index => $status_label)
          $dialog->add_list_item($index,$status_label,$status == $index);
       $dialog->end_choicelist();
       $dialog->end_row();
       if (isset($category_types)) {
          $category_type = get_row_value($row,'category_type');
          $dialog->start_row($category_label.' Type:','middle');
          foreach ($category_types as $type_value => $type_label)
             $dialog->add_radio_field('category_type',$type_value,$type_label,
                                      ($category_type == $type_value),
                                      'change_category_type(this);');
          $dialog->end_row();
       }
       if (! $disable_catalog_config) {
          $templates = load_catalog_templates($db);
          add_category_template_row($dialog,'Subcategory List Template:',
                                    'template',$row,$templates,true);
          add_category_template_row($dialog,'Product List Template:',
                                    'product_list_template',$row,$templates);
          add_category_template_row($dialog,'Default Product Template:',
                                    'product_template',$row,$templates);
       }
       $flags = get_row_value($row,'flags');
       $dialog->start_row('Flags:','top');
       $dialog->start_table();
       $dialog->write("<tr><td style=\"padding-bottom:5px;\">\n");
       $feature_num = 0;
       $dialog->add_checkbox_field('flag0','Do not include in Breadcrumbs&nbsp;&nbsp;&nbsp;&nbsp;',
                                         $flags & NO_BREADCRUMBS);
       $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
       $feature_num++;
       $dialog->add_checkbox_field('flag2','Hide Long Description on Page Load',
                                   $flags & HIDE_DESCRIPTION);
       $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
       $feature_num++;
       $dialog->add_checkbox_field('flag1','No Hyperlink',$flags & NO_HYPERLINK);
       $dialog->write("</td><td style=\"padding-bottom:5px;\">\n");
       $feature_num++;
       $dialog->add_checkbox_field('flag3','Do not use as SEO Category',
                                   $flags & NO_SEO_CATEGORY);
       $dialog->write("</td></tr><tr><td style=\"padding-bottom:5px;\">\n");
       $feature_num++;
       $dialog->add_checkbox_field('flag4','Hide Show/Hide Link',
                                   $flags & HIDE_SHOW_HIDE_LINK);
       $feature_num++;
       if (function_exists('add_custom_category_flags'))
          add_custom_category_flags($dialog,$row,$feature_num);
       if ($feature_num % 2) $dialog->write('</td><td>&nbsp;');
       $dialog->end_row();
       $dialog->end_table();
       $dialog->end_row();
       if ($enable_category_filter_search) {
          $dialog->add_hidden_field('old_products_source',$products_source);
          $dialog->start_row($product_label.' List Source:','middle');
          $dialog->add_radio_field('products_source',0,$product_label.' List',
             ($products_source == 0),'change_products_source();');
          $dialog->write("&nbsp;&nbsp;&nbsp;\n");
          $dialog->add_radio_field('products_source',1,'Filter Search',
             ($products_source == 1),'change_products_source();');
          $dialog->end_row();
       }
       $dialog->start_row('Short Description:','top');
       $dialog->add_htmleditor_popup_field('short_description',$row,
          'Short Description',550,100,null,null,null,false,
          'catalogtemplates.xml');
       $dialog->end_row();
       $dialog->start_row('Long Description:','top');
       $dialog->add_htmleditor_popup_field('long_description',$row,
          'Long Description',550,200,null,null,null,false,
          'catalogtemplates.xml');
       $dialog->end_row();
       $dialog->add_edit_row('External URL:','external_url',$row,80);
       if (isset($enable_multisite) && $enable_multisite) {
          $dialog->start_row('Web Sites:','top');
          list_website_checkboxes($db,$dialog,get_row_value($row,'websites'));
          $dialog->end_row();
       }
       if ($edit_type == UPDATERECORD) $frame_name = 'edit_category';
       else $frame_name = 'add_category';

       foreach ($category_fields as $field_name => $field) {
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
                $dialog->start_row($field['prompt'],'middle');
                $dialog->add_checkbox_field($field_name,'',$row);
                $dialog->end_row();
                break;
             case HTMLEDIT_FIELD:
                $dialog->start_row($field['prompt'],'top');
                $dialog->add_htmleditor_popup_field($field_name,$row,
                   $field['title'],$field['width'],$field['height'],null,
                   null,null,false,'catalogtemplates.xml');
                $dialog->end_row();
                break;
             case CUSTOM_FIELD:
                $dialog->start_row($field['prompt'],'middle');
                if (function_exists('display_custom_category_field'))
                   display_custom_category_field($dialog,$field_name,
                                                 get_row_value($row,$field_name));
                $dialog->end_row();
                break;
             case CUSTOM_ROW:
                if (function_exists('display_custom_category_field'))
                   display_custom_category_field($dialog,$field_name,$row);
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
       $dialog->end_field_table();
       add_base_href($dialog,$default_base_href,true);
       $dialog->write("</div>\n");
       $dialog->end_tab_content();
    }

    if (isset($category_tabs['specs']) && $category_tabs['specs']) {
       $dialog->start_tab_content('specs_content',false);
       if (function_exists('display_category_specs_fields'))
          display_category_specs_fields($dialog,$db,$row,$edit_type);
       $dialog->end_tab_content();
    }

    if ($category_tabs['image']) {
       $dialog->start_tab_content('image_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <table cellspacing=\"0\" cellpadding=\"0\" " .
                      "width=\"100%\"><tr valign=\"top\">\n");
       $dialog->write("          <td><script>init_images(\"");
       if ($shopping_cart) $dialog->write('../cartengine/');
       $dialog->write($script_name."\",");
       if ($edit_type == UPDATERECORD) {
          if (get_form_field("insidecms"))
             $dialog->write("\"smartedit\",\"EditCategory\"");
          else $dialog->write("\"edit_category\",\"EditCategory\"");
       }
       else $dialog->write("\"add_category\",\"AddCategory\"");
       if ($dialog->skin) $dialog->write(",-100");
       else $dialog->write(",600");
       $dialog->write(",".$image_parent_type.");\n");
       $dialog->write("                    create_images_grid(".$id.");</script></td>\n");
       add_image_sequence_buttons($dialog);
       $dialog->write("        </tr></table>\n");
       add_image_sample($dialog);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if ($category_tabs['subcategories']) {
       $dialog->start_tab_content("subcategory_content",false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write("           subcategories = new SubList();\n");
       $dialog->write("           subcategories.name = 'subcategories';\n");
       $dialog->write("           subcategories.script_url = '" .
                      $script_name."';\n");
       $dialog->write("           subcategories.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_category';\n");
       else $dialog->write("add_category';\n");
       $dialog->write("           subcategories.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditCategory';\n");
       else $dialog->write("AddCategory';\n");
       if ($dialog->skin)
          $dialog->write("           subcategories.grid_width = 0;\n");
       else $dialog->write("           subcategories.grid_width = 300;\n");
       $dialog->write("           subcategories.grid_height = 500;\n");
       $dialog->write("           subcategories.left_table = '" .
                      $subcategories_table."';\n");
       $dialog->write("           subcategories.left_titles = ['" .
                      $category_label." Name'];\n");
       $dialog->write("           subcategories.left_label = 'subcategories';\n");
       $dialog->write("           subcategories.right_table = '" .
                      $categories_table."';\n");
       $dialog->write("           subcategories.right_titles = ['" .
                      $category_label." Name'];\n");
       $dialog->write("           subcategories.right_label = 'categories';\n");
       $dialog->write("           subcategories.right_single_label = 'category';\n");
       if ($website_where) 
          $dialog->write("           subcategories.right_where = '" .
                         $website_where."';\n");
       $dialog->write("           subcategories.default_frame = 'edit_category';\n");
       $dialog->write("           subcategories.enable_double_click = true;\n");
       $dialog->write("           subcategories.categories = true;\n");
       if ($website_where)
          $dialog->write("           subcategories.search_where = \"(name " .
                         "like '%\$query\$%' or display_name " .
                         "like '%\$query\$%' or short_description like " .
                         "'%\$query\$%' or long_description like " .
                         "'%\$query\$%') and ".$website_where."\";\n");
       $dialog->write("        </script>\n");
       create_sublist_grids("subcategories",$dialog,$id,$subcategories_label,
                            "All ".$categories_label,false,"SubcategoryQuery",
                            $categories_label,true);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if ($category_tabs['parentcategories']) {
       $dialog->start_tab_content("parentcategory_content",false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write("           parentcategories = new SubList();\n");
       $dialog->write("           parentcategories.name = 'parentcategories';\n");
       $dialog->write("           parentcategories.script_url = '" .
                      $script_name."';\n");
       $dialog->write("           parentcategories.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_category';\n");
       else $dialog->write("add_category';\n");
       $dialog->write("           parentcategories.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditCategory';\n");
       else $dialog->write("AddCategory';\n");
       if ($dialog->skin)
          $dialog->write("           parentcategories.grid_width = 0;\n");
       else $dialog->write("           parentcategories.grid_width = 300;\n");
       $dialog->write("           parentcategories.grid_height = 500;\n");
       $dialog->write("           parentcategories.left_table = '" .
                      $subcategories_table."';\n");
       $dialog->write("           parentcategories.left_titles = ['" .
                      $category_label." Name'];\n");
       $dialog->write("           parentcategories.left_label = 'subcategories';\n");
       $dialog->write("           parentcategories.right_table = '" .
                      $categories_table."';\n");
       $dialog->write("           parentcategories.right_titles = ['" .
                      $category_label." Name'];\n");
       $dialog->write("           parentcategories.right_label = 'categories';\n");
       $dialog->write("           parentcategories.right_single_label = 'category';\n");
       if ($website_where) 
          $dialog->write("           parentcategories.right_where = '" .
                         $website_where."';\n");
       $dialog->write("           parentcategories.default_frame = 'edit_category';\n");
       $dialog->write("           parentcategories.enable_double_click = true;\n");
       $dialog->write("           parentcategories.reverse_list = true;\n");
       $dialog->write("           parentcategories.categories = true;\n");
       if ($website_where)
          $dialog->write("           parentcategories.search_where = \"(name " .
                         "like '%\$query\$%' or display_name " .
                         "like '%\$query\$%' or short_description like " .
                         "'%\$query\$%' or long_description like " .
                         "'%\$query\$%') and ".$website_where."\";\n");
       $dialog->write("        </script>\n");
       create_sublist_grids("parentcategories",$dialog,$id,"Parent " .
                            $categories_label,"All ".$categories_label,false,
                            "ParentCategoryQuery",$categories_label,false);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if ($category_tabs['products']) {
       $dialog->start_tab_content("product_content",false);
       $dialog->write('        <div id="category_products_div" ');
       if ($dialog->skin) $dialog->write('class="fieldSection"');
       else $dialog->write('style="padding: 4px;"');
       if ($enable_category_filter_search && ($products_source == 1))
          $dialog->write(' style="display:none;"');
       $dialog->write(">\n");
       $dialog->write("        <script type=\"text/javascript\">\n");
       $dialog->write("           products = new SubList();\n");
       $dialog->write("           products.name = 'products';\n");
       $dialog->write("           products.script_url = '" .
                      $script_name."';\n");
       $dialog->write("           products.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_category';\n");
       else $dialog->write("add_category';\n");
       $dialog->write("           products.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditCategory';\n");
       else $dialog->write("AddCategory';\n");
       if ($dialog->skin)
          $dialog->write("           products.grid_width = 0;\n");
       else $dialog->write("           products.grid_width = 300;\n");
       $dialog->write("           products.grid_height = 500;\n");
       $dialog->write("           products.left_table = '" .
                      $category_products_table."';\n");
       $dialog->write("           products.left_titles = ['".$name_prompt .
                      "','Description'];\n");
       $dialog->write("           products.left_widths = [".$name_col_width .
                      ",-1];\n");
       $dialog->write("           products.left_fields = '" .
                      $category_products_left_fields."';\n");
       $dialog->write("           products.left_label = 'products';\n");
       $dialog->write("           products.right_table = '" .
                      $products_table."';\n");
       $dialog->write("           products.right_titles = ['".$name_prompt .
                      "','Description'];\n");
       $dialog->write("           products.right_widths = [".$name_col_width .
                      ",-1];\n");
       $dialog->write("           products.right_fields = '" .
                      $category_products_right_fields."';\n");
       $dialog->write("           products.right_label = 'products';\n");
       $dialog->write("           products.right_single_label = 'product';\n");
       if ($website_where) 
          $dialog->write("           products.right_where = '".$website_where."';\n");
       $dialog->write("           products.default_frame = 'edit_category';\n");
       $dialog->write("           products.enable_double_click = true;\n");
       if (isset($category_products_double_click_function))
          $dialog->write("           products.double_click_function = " .
                         $category_products_double_click_function.";\n");
       $dialog->write("           products.products_script_name = products_script_name;\n");
       $dialog->write("           products.categories = false;\n");
       $dialog->write("           products.search_where = \"");
       if ($website_where) $dialog->write('(');
       $dialog->write("name like '%\$query\$%' or display_name like " .
                      "'%\$query\$%' or short_description like '%\$query\$%' " .
                      "or long_description like '%\$query\$%'");
       if ($features & USE_PART_NUMBERS)
          $dialog->write(" or id in (select parent from product_inventory " .
                         "where part_number like '%\$query\$%')");
       if ($website_where) $dialog->write(') and '.$website_where);
       $dialog->write("\";\n");
       $dialog->write("        </script>\n");
       $filters = array(
          array('prompt' => 'Status:','table' => 'products','field' => 'status',
                'all_label' => 'All'));
       if ($shopping_cart)
          $filters[0]['query'] = 'select id,label from cart_options where ' .
                              'table_id=0 order by id';
       else $filters[0]['values'] = load_cart_options(PRODUCT_STATUS,$db);
       if (function_exists('update_sublist_filters'))
          update_sublist_filters($filters,'category_products',$dialog,$db,$row);
       create_sublist_grids("products",$dialog,$id,$category_label." " .
                            $products_label,"All ".$products_label,false,
                            "ProductsQuery",$products_label,true,null,
                            $filters,$db);
       $dialog->write("        </div>\n");
       if ($enable_category_filter_search) {
          $dialog->write('        <div id="category_filters_div" ');
          if ($products_source == 0) $dialog->write(' style="display:none;"');
          $dialog->write(">\n");
          $dialog->start_field_table('category_filters_table');
          load_category_filters($dialog,$db,$row);
          $dialog->end_field_table();
          $dialog->write("        </div>\n");
       }
       $dialog->end_tab_content();
    }

    if ($category_tabs['seo']) {
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
       $dialog->add_edit_row('URL Alias:','seo_url',$row,40);
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    if (function_exists('display_custom_category_tab_sections'))
       display_custom_category_tab_sections($dialog,$db,$row,$edit_type);

    $dialog->end_tab_section();
}

function add_tab_buttons($dialog)
{
    global $use_price_breaks,$product_label,$category_label,$subcategory_label;
    global $include_new_product_button;

    if (! isset($product_label)) $product_label = 'Product';
    if (! isset($include_new_product_button)) $include_new_product_button = true;
    $dialog->add_button_separator('category_buttons_row',20);
    add_image_buttons($dialog,false);
    $dialog->add_button('New '.$subcategory_label,'images/AddCategory.png',
                        'add_category(subcategories);','new_subcategory',false);
    $dialog->add_button('New '.$category_label,'images/AddCategory.png',
                        'add_category(parentcategories);','new_parentcategory',false);
    if ($include_new_product_button)
       $dialog->add_button('New '.$product_label,'images/AddProduct.png',
                           'new_product();','new_product',false);
    if ($use_price_breaks)
       $dialog->add_button('Set Price Breaks','images/AddProduct.png',
                           'set_price_breaks();','set_price_breaks',false);
}

function parse_category_flags()
{
    global $num_category_flags;

    if (! isset($num_category_flags)) $num_category_flags = NUM_CATEGORY_FLAGS;
    $flags = 0;
    for ($loop = 0;  $loop < $num_category_flags;  $loop++)
       if (get_form_field("flag".$loop) == 'on') $flags |= (1 << $loop);
    return $flags;
}

function create_category()
{
    global $categories_table,$category_label;

    $db = new DB;
    $category_record = category_record_definition();
    $category_record['name']['value'] = "New ".$category_label;
    if (! $db->insert($categories_table,$category_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print "category_id = ".$id.";";
    log_activity("Created New ".$category_label." #".$id);
}

function add_category()
{
    global $default_base_href,$use_price_breaks,$category_label;
    global $enable_multisite,$website_cookie,$script_name,$categories_table;

    init_category_tabs(array());
    $db = new DB;
    $id = get_form_field("id");
    if (! is_numeric($id)) {
       process_error("Invalid ".$category_label." ".$id,0);   return;
    }
    $row = $db->get_record("select * from ".$categories_table .
                           " where id=".$id);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error($category_label." not found",0);
       return;
    }
    $row['name'] = '';
    if (isset($enable_multisite) && $enable_multisite &&
        isset($_COOKIE[$website_cookie]))
       $row['websites'] = $_COOKIE[$website_cookie];
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet("categories.css");
    $dialog->add_style_sheet("utility.css");
    $dialog->add_script_file("categories.js");
    $dialog->add_script_file("utility.js");
    $dialog->add_style_sheet("image.css");
    $dialog->add_script_file("image.js");
    $dialog->add_script_file("sublist.js");
    if ($use_price_breaks) $dialog->add_script_file("pricebreak.js");
    if (file_exists("../admin/custom-config.js"))
       $dialog->add_script_file("../admin/custom-config.js");
    if (get_form_field("sublist")) $dialog_title = "New";
    else $dialog_title = "Add";
    $dialog_title .= " ".$category_label." (#".$id.")";
    add_script_prefix($dialog,$dialog_title);
    add_category_variables($dialog);
    add_base_href($dialog,$default_base_href,false);
    add_update_function($dialog,$id);
    $dialog->set_onload_function("add_category_onload();");
    if (function_exists("custom_init_category_dialog"))
       custom_init_category_dialog($dialog);
    $dialog->set_body_id('add_category');
    $dialog->set_help('add_category');
    $dialog->start_body($dialog_title);
    $label_length = strlen($category_label);
    if ($label_length > 8) $button_width = ($label_length * 5) + 105;
    else $button_width = 152;
    $dialog->set_button_width($button_width);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button("Add ".$category_label,"images/AddCategory.png",
                        "process_add_category();");
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    add_tab_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form($script_name,"AddCategory");
    if (! $dialog->skin) $dialog->start_field_table();
    display_category_fields($dialog,ADDRECORD,$id,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_category()
{
    global $category_label,$enable_multisite,$enable_category_filter_search;

    $db = new DB;
    $category_record = category_record_definition();
    $db->parse_form_fields($category_record);
    $id = $category_record['id']['value'];
    if (! empty($enable_multisite)) parse_website_checkboxes($category_record);
    $category_record['flags']['value'] = parse_category_flags();
    if (! add_category_record($db,$category_record,$category_id,$error_code,
                             $error,false)) {
       http_response($error_code,$error);   return;
    }
    if ($enable_category_filter_search &&
        (! save_category_filters($db,$category_record))) return;

    http_response(201,$category_label." Added");
    log_activity("Added ".$category_label." ".$category_record['name']['value'] .
                 " (#".$category_id.")");
}

function edit_category()
{
    global $default_base_href,$use_price_breaks,$category_tabs,$category_label;
    global $script_name,$categories_table;

    $db = new DB;
    $id = get_form_field("id");
    if (! is_numeric($id)) {
       process_error("Invalid ".$category_label." ".$id,0);   return;
    }
    $query = 'select * from '.$categories_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error($category_label." not found",0);
       return;
    }
    init_category_tabs($row);
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet("categories.css");
    $dialog->add_style_sheet("utility.css");
    $dialog->add_script_file("categories.js");
    $dialog->add_script_file("utility.js");
    $dialog->add_style_sheet("image.css");
    $dialog->add_script_file("image.js");
    $dialog->add_script_file("sublist.js");
    if ($use_price_breaks) $dialog->add_script_file("pricebreak.js");
    if (file_exists("../admin/custom-config.js"))
       $dialog->add_script_file("../admin/custom-config.js");
    add_base_href($dialog,$default_base_href,false);
    add_update_function($dialog,null);
    $dialog_title = "Edit ".$category_label." - ".$row['name']." (#".$id.")";
    add_script_prefix($dialog,$dialog_title);
    add_category_variables($dialog);
    add_website_js_array($dialog,$db);
    if ($category_tabs['image'])
       $dialog->set_onload_function("category_onload(); images_onload();");
    else $dialog->set_onload_function("category_onload();");
    if (function_exists("custom_init_category_dialog"))
       custom_init_category_dialog($dialog);
    $dialog->set_body_id('edit_category');
    $dialog->set_help('edit_category');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(152);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button("Update","images/Update.png","update_category();");
    $dialog->add_button("Cancel","images/Update.png",
                        "close_category_dialog();");
    add_tab_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form($script_name,"EditCategory");
    if (! $dialog->skin) $dialog->start_field_table();
    display_category_fields($dialog,UPDATERECORD,$id,$row,$db);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function load_category()
{
    global $categories_table,$category_label;

    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from '.$categories_table.' where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,$category_label.' #'.$id.' not found');
       return;
    }
    print json_encode($row);
}

function update_category()
{
    global $category_label,$enable_multisite,$enable_category_filter_search;

    $db = new DB;
    $category_record = category_record_definition();
    $db->parse_form_fields($category_record);
    $id = $category_record['id']['value'];

    if (! empty($enable_multisite)) parse_website_checkboxes($category_record);
    if (! update_category_record($db,$category_record,$error)) {
       http_response(422,$error);   return;
    }
    $category_record['flags']['value'] = parse_category_flags();
    if (! update_category_record($db,$category_record,$error)) {
       http_response(422,$error);   return;
    }
    if ($enable_category_filter_search &&
        (! save_category_filters($db,$category_record))) return;
    http_response(201,$category_label.' Updated');
    log_activity('Updated '.$category_label.' '.$category_record['name']['value'] .
                 ' (#'.$id.')');
}

function delete_category()
{
    global $category_label,$enable_multisite;
    global $enable_category_filter_search;

    $db = new DB;
    $id = get_form_field('id');
    $move_cat_parent = get_form_field('movecatparent');
    $move_prod_parent = get_form_field('moveprodparent');
    if ($move_cat_parent || $move_prod_parent) {
       $query = 'select parent from subcategories where related_id=? limit 1';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if ($row && $row['parent']) $parent = $row['parent'];
       else $parent = null;
    }
    if ($move_cat_parent && $parent) {
       $query = 'update subcategories set parent=? where parent=?';
       $query = $db->prepare_query($query,$parent,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
       $delete_subs = false;
    }
    else $delete_subs = true;
    if ($move_prod_parent && $parent) {
       $query = 'update '.$category_products_table .
                ' set parent=? where parent=?';
       $query = $db->prepare_query($query,$parent,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
       $delete_products = false;
    }
    else $delete_products = true;
    if (! delete_category_record($db,$id,$category_name,$error,$delete_subs,
                                 $delete_products)) {
       http_response(422,$error);   return;
    }
    if ($enable_category_filter_search) {
       $query = 'delete from category_filters where parent=?';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,$category_label.' Deleted');
    log_activity('Deleted '.$category_label.' '.$category_name.' (#'.$id.')');
}

function rebuild_cache()
{
    global $shopping_cart,$script_name,$categories_table;

    set_time_limit(0);
    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $query = 'select count(c.id) as num_categories from ' .
             $categories_table.' c';
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,'No Categories Found');
       return;
    }
    $num_categories = $row['num_categories'];
    $num_pages = ceil($num_categories / 50);

    log_activity('Rebuilding Cached Pages for '.$num_categories.' Categories');
    for ($category_page = 0;  $category_page < $num_pages;  $category_page++) {
       // keep the web server connection alive
       print 'Page #'.$category_page."\n";
       $command = $prefix.$script_name.' rebuildcache '.$category_page;
       $process = new Process($command);
       if ($process->return != 0) {
          http_response(422,'Unable to start cache rebuild (' .
                            $process->return.')');
          return;
       }
       $counter = 0;
       while ($process->status()) {
          if ($counter == 500) {
             $process->stop();
             http_response(422,'Cache Rebuild took too long');   return;
          }
          sleep(1);
          $counter++;
       }
    }
    http_response(201,'Cache Rebuilt');
    log_activity('Rebuilt Cached Pages for '.$num_categories.' Categories');
}

function process_rebuild_cache($category_page)
{
    global $categories_table,$cloudflare_site;

    set_time_limit(0);
    if (isset($cloudflare_site)) require_once '../admin/cloudflare-admin.php';

    $db = new DB;
    $query = 'select id,seo_url,websites from '.$categories_table .
             ' order by id';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,$db->error);
       else log_error('No Categories Found');
       return;
    }

    $num_categories = count($rows);
    $start_line = $category_page * 50;
    $end_line = $start_line + 50;
    if ($end_line > $num_categories) $end_line = $num_categories;
    $category_line = 0;
    log_activity('Rebuilding Cached Pages for Categories #'.($start_line+1) .
                 '-' .$end_line);

    foreach ($rows as $row) {
       if ($category_line < $start_line) {
          $category_line++;   continue;
       }
       $id = $row['id'];
       generate_cached_category_page($db,$id,true);
       update_htaccess(0,$id,$row['seo_url'],$row['websites'],$db);
       if (isset($cloudflare_site))
          update_cloudflare_category($id,$row['seo_url']);
       $category_line++;
       if ($category_line == $end_line) break;
    }
}

function resequence_categories()
{
    $db = new DB;
    $query = 'select s.id,s.parent,s.sequence from subcategories s left ' .
             'join categories c on s.related_id=c.id order by s.parent,' .
             's.sequence,c.name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print "Query: ".$query."<br>\n";
          print "Database Error: ".$db->error."<br>\n";
       }
       else print "No Categories Found to Resequence<br>\n";
       return;
    }
    $current_parent = -1;
    foreach ($rows as $row) {
       if ($row['parent'] != $current_parent) {
          $sequence = 1;   $current_parent = $row['parent'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update subcategories set sequence='.$sequence .
                   ' where id='.$row['id'];
          if (! $db->query($query)) {
             print "Query: ".$query."<br>\n";
             print "Database Error: ".$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced SubCategories');
    print "Resequenced SubCategories<br>\n";
}

function output_data($buffer)
{
    $buffer = str_replace("\"","\"\"",$buffer);
    print $buffer;
}

function export_find_subcategories($category_list,$subcategories,$categories,
                                   $cat_id,$level)
{
    foreach ($subcategories as $subcategory)
       if ($subcategory['parent'] == $cat_id) {
          $subcat_id = $subcategory['related_id'];
          if (isset($category_list[$subcat_id])) continue;
          if (! isset($categories[$subcat_id])) continue;
          $category_list[$subcat_id] = $categories[$subcat_id];
          $category_list[$subcat_id]['level'] = $level;
          $category_list = export_find_subcategories($category_list,$subcategories,
                                                     $categories,$subcat_id,
                                                     ($level + 1));
       }
    return $category_list;
}

function export_category_structure()
{
    global $top_category,$category_off_sale_option;

    if (! isset($category_off_sale_option)) $category_off_sale_option = 1;

    $db = new DB;

    $query = "select * from subcategories order by sequence";
    $subcategories = $db->get_records($query);
    if ((! $subcategories) && isset($db->error)) {
       process_error("Database Error: ".$db->error,-1);   return true;
    }

    $query = "select * from categories where (isnull(status) or (status!=" .
             $category_off_sale_option.")) order by name";
    $categories = $db->get_records($query,'id');
    if ((! $categories) && isset($db->error)) {
       process_error("Database Error: ".$db->error,-1);   return true;
    }

    $category_list = array();
    $category_list[$top_category] = $categories[$top_category];
    $category_list[$top_category]['level'] = 0;
    $category_list = export_find_subcategories($category_list,$subcategories,
                                               $categories,$top_category,1);

    if (get_browser_type() == MSIE)
       header("Content-type: application/inroads");
    else header("Content-type: application/octet-stream");
    header("Content-disposition: attachment; filename=\"categories.csv\"");

    $category_record = category_record_definition();
    print "Category";
    foreach ($category_record as $field_name => $field_def) {
       print ",\"";   output_data($field_name);   print "\"";
    }
    print "\n";

    foreach ($category_list as $cat_id => $category) {
       if ($category['menu_name']) $cat_name = $category['menu_name'];
       else if ($category['display_name']) $cat_name = $category['display_name'];
       else $cat_name = $category['name'];
       $prefix = '';
       for ($loop = 0;  $loop < $category['level'];  $loop++)
          $prefix .= '   ';
       print $prefix.$cat_name;
       foreach ($category as $field_name => $field_value) {
          if ($field_name == 'level') continue;
          print ",\"";   output_data($field_value);   print "\"";
       }
       print "\n";
    }
    foreach ($categories as $cat_id => $category) {
       if (isset($category_list[$cat_id])) continue;
       if ($category['menu_name']) $cat_name = $category['menu_name'];
       else if ($category['display_name']) $cat_name = $category['display_name'];
       else $cat_name = $category['name'];
       print '~'.$cat_name;
       foreach ($category as $field_name => $field_value) {
          print ",\"";   output_data($field_value);   print "\"";
       }
       print "\n";
    }

    log_activity("Exported Category Structure");
}

function init_category_images()
{
    $db = new DB;
    $query = 'select c.id,c.name,c.display_name,(select count(i.id) from ' .
             'images i where i.parent_type=0 and i.parent=c.id) as num_images,' .
             '(select pi.filename from images pi where pi.parent_type=1 and ' .
             'pi.parent=(select cp.related_id from category_products cp where ' .
             'parent=c.id order by cp.sequence limit 1) order by pi.sequence ' .
             'limit 1) as first_image from categories c';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print "Query: ".$query."<br>\n";
          print "Database Error: ".$db->error."<br>\n";
       }
       else print "No Categories Found to set Images for<br>\n";
       return;
    }
    $image_record = image_record_definition();
    $image_record['parent_type']['value'] = 0;
    $image_record['sequence']['value'] = 0;
    foreach ($rows as $row) {
       if ($row['num_images'] > 0) continue;
       if (! $row['first_image']) continue;

       $image_record['parent']['value'] = $row['id'];
       $image_record['filename']['value'] = $row['first_image'];
       if ($row['display_name']) $description = $row['display_name'];
       else $description = $row['name'];
       $image_record['description']['value'] = $description;
       if (! $db->insert('images',$image_record)) {
           print "Database Error: ".$db->error."<br>\n";
          return;
       }
    }
    log_activity('Initialized Category Images');
    print "Initialized Category Images<br>\n";
}

function rebuild_web_site()
{
    global $cms_module,$cms_program,$cms_base_url,$login_cookie,$cms_use_http;

    require_once $cms_module;
    $admin_user = get_cookie($login_cookie);
    $wsd = new WSD($cms_program,$admin_user,$cms_base_url);
    if (isset($cms_use_http) && $cms_use_http) $wsd->use_http(true);
    $wsd->rebuildall();
}

if (isset($argc) && ($argc == 2) && ($argv[1] == 'rebuildwebsite')) {
   rebuild_web_site();   DB::close_all();   exit(0);
}
if (isset($argc) && ($argc == 2) && ($argv[1] == 'rebuildhtaccess')) {
   rebuild_htaccess();   DB::close_all();   exit(0);
}

if ((! $bg_command) && (! check_login_cookie())) exit;

init_images($script_name,'categories.js',$image_parent_type);
init_sublists($script_name,'categories.js',0);

if ($bg_command == 'rebuildcache') {
   process_rebuild_cache($argv[2]);   DB::close_all();   exit(0);
}

$cmd = get_form_field('cmd');

if ($cmd == 'createcategory') create_category();
else if ($cmd == 'addcategory') add_category();
else if ($cmd == 'processaddcategory') process_add_category();
else if ($cmd == 'editcategory') edit_category();
else if ($cmd == 'loadcategory') load_category();
else if ($cmd == 'updatecategory') update_category();
else if ($cmd == 'deletecategory') delete_category();
else if ($cmd == 'rebuildcache') rebuild_cache();
else if ($cmd == 'resequencecategories') resequence_categories();
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
else if ($cmd == 'rebuildhtaccess') rebuild_htaccess();
else if ($cmd == 'initseo') initialize_seo_urls();
else if ($cmd == 'setpricebreaks') set_price_breaks();
else if ($cmd == 'applypricebreaks') apply_price_breaks();
else if ($cmd == 'exportstructure') export_category_structure();
else if ($cmd == 'initimages') init_category_images();
else if (process_sublist_command($cmd)) {}
else if (function_exists('process_category_command') &&
         process_category_command($cmd)) {}
else display_categories_screen();

DB::close_all();

?>

