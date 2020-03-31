<?php
/*
                        Inroads Shopping Cart - Coupons Tab

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'sublist.php';
require_once 'utility.php';
require_once 'cartconfig-common.php';
require_once 'products-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

if (! isset($product_label)) $product_label = 'Product';
if (! isset($products_label)) $products_label = $product_label.'s';
if (! isset($enable_schedule)) $enable_schedule = false;

function coupon_record_definition()
{
    global $enable_schedule;

    $coupon_record = array();
    $coupon_record['id'] = array('type' => INT_TYPE);
    $coupon_record['id']['key'] = true;
    $coupon_record['coupon_code'] = array('type' => CHAR_TYPE);
    $coupon_record['description'] = array('type' => CHAR_TYPE);
    $coupon_record['coupon_type'] = array('type' => INT_TYPE);
    $coupon_record['amount'] = array('type' => FLOAT_TYPE);
    $coupon_record['websites'] = array('type' => CHAR_TYPE);
    $coupon_record['balance'] = array('type' => FLOAT_TYPE);
    $coupon_record['min_amount'] = array('type' => FLOAT_TYPE);
    $coupon_record['free_product'] = array('type' => INT_TYPE);
    $coupon_record['free_prod_attrs'] = array('type' => CHAR_TYPE);
    $coupon_record['gift_customer'] = array('type' => INT_TYPE);
    $coupon_record['start_date'] = array('type' => INT_TYPE);
    $coupon_record['end_date'] = array('type' => INT_TYPE);
    $coupon_record['max_qty'] = array('type' => INT_TYPE);
    $coupon_record['max_qty_per_cust'] = array('type' => INT_TYPE);
    $coupon_record['qty_used'] = array('type' => INT_TYPE);
    $coupon_record['flags'] = array('type' => INT_TYPE);
    if ($enable_schedule) {
       $coupon_record['schedule_pages'] = array('type' => CHAR_TYPE);
       $coupon_record['message_1'] = array('type' => CHAR_TYPE);
       $coupon_record['message_2'] = array('type' => CHAR_TYPE);
       $coupon_record['message_3'] = array('type' => CHAR_TYPE);
    }
    if (function_exists('custom_coupon_fields'))
       custom_coupon_fields($coupon_record);
    return $coupon_record;
}

function coupon_inventory_record_definition()
{
    $coupon_inventory_record = array();
    $coupon_inventory_record['id'] = array('type' => INT_TYPE);
    $coupon_inventory_record['id']['key'] = true;
    $coupon_inventory_record['parent'] = array('type' => INT_TYPE);
    $coupon_inventory_record['related_id'] = array('type' => INT_TYPE);
    return $coupon_inventory_record;
}

function discount_record_definition()
{
    $discount_record = array();
    $discount_record['id'] = array('type' => INT_TYPE);
    $discount_record['id']['key'] = true;
    $discount_record['parent'] = array('type' => INT_TYPE);
    $discount_record['start_qty'] = array('type' => INT_TYPE);
    $discount_record['end_qty'] = array('type' => INT_TYPE);
    $discount_record['discount'] = array('type' => FLOAT_TYPE);
    return $discount_record;
}

function add_head_variables(&$screen)
{
    global $enable_schedule;

    if ($enable_schedule) {
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      enable_schedule = true;\n";
       $head_block .= '    </script>';
       $screen->add_head_line($head_block);
    }
}

function add_coupon_filters($screen)
{
    $features = get_cart_config_value('features');
    if (function_exists('add_custom_coupon_filters'))
       add_custom_coupon_filters($screen,$db);
    if ($screen->skin) $screen->write("<div class=\"filter\"><span>");
    else {
       $screen->write("<tr style=\"height: 10px;\"><td colspan=\"2\"></td></tr>\n");
       $screen->write("<tr><td colspan=\"2\" style=\"padding-left: 0px; ");
       $screen->write("font-size: 12px; font-weight: bold; color: #636466;\">");
    }
    $screen->write('Type:');
    if ($screen->skin) $screen->write("</span>");
    else $screen->write("<br>\n");
    $screen->write("<select name=\"coupon_type\" id=\"coupon_type\" " .
                   "onChange=\"filter_coupons();\" class=\"select\"");
    if (! $screen->skin) $screen->write(" style=\"width: 148px;\"");
    $screen->write(">\n");
    $screen->add_list_item('','All',true);
    $screen->add_list_item('1','Percentage Off',false);
    $screen->add_list_item('2','Amount Off',false);
    $screen->add_list_item('3','Free Shipping',false);
    if ($features & GIFT_CERTIFICATES)
       $screen->add_list_item('4','Gift Certificate',false);
    $screen->add_list_item('5','Free Order',false);
    $screen->add_list_item('6','Free Product',false);
    $screen->add_list_item('7','Buy 1 Get 1 at x% Off',false);
    $screen->add_list_item('8','Quantity Discount',false);
    if (function_exists('add_coupon_types')) add_coupon_types($screen,array());
    $screen->end_choicelist();
    if ($screen->skin) $screen->write("</div>");
    else $screen->write("</td></tr>\n");
}

function display_coupons_screen()
{
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('coupons.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('coupons.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    add_head_variables($screen);
    if (function_exists('custom_init_coupons_screen'))
       custom_init_coupons_screen($screen);
    $screen->set_body_id('coupons');
    $screen->set_help('coupons');
    $screen->start_body(filemtime('coupons.php'));
    $screen->set_button_width(140);
    if ($screen->skin) {
       $screen->start_section();
       $screen->start_title_bar('Coupons');
       $screen->start_title_filters();
       add_coupon_filters($screen);
       add_search_box($screen,'search_coupons','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->start_button_column();
    $screen->add_button('Add Coupon','images/AddCoupon.png',
                        'add_coupon(true);',null,true,false,ADD_BUTTON);
    $screen->add_button('Edit Coupon','images/EditCoupon.png',
                        'edit_coupon(true);',null,true,false,EDIT_BUTTON);
    $screen->add_button('Delete Coupon','images/DeleteCoupon.png',
                        'delete_coupon(true);',null,true,false,DELETE_BUTTON);
    if ($screen->skin) {
       $screen->end_button_column();
       $screen->write("          <script type=\"text/javascript\">" .
                      "load_coupons_grid(true);</script>\n");
       $screen->end_section();
       $screen->start_section();
       $screen->start_title_bar('Special Offers');
       $screen->end_title_bar();
       $screen->start_button_column();
    }
    else {
       add_coupon_filters($screen);
       add_search_box($screen,'search_coupons','reset_search');
       $screen->add_button_separator('coupons_sep_row',20);
    }
    $screen->add_button('Add Offer','images/AddCoupon.png',
                        'add_coupon(false);',null,true,false,ADD_BUTTON);
    $screen->add_button('Edit Offer','images/EditCoupon.png',
                        'edit_coupon(false);',null,true,false,EDIT_BUTTON);
    $screen->add_button('Delete Offer','images/DeleteCoupon.png',
                        'delete_coupon(false);',null,true,false,DELETE_BUTTON);
    $screen->end_button_column();
    if (! $screen->skin) {
       $screen->write("          <span class=\"fieldprompt\"" .
                      " style=\"text-align: left; font-weight: bold;\">" .
                      "Coupons</span><br>\n");
       $screen->write("          <script>load_coupons_grid(true);</script>\n");
       $screen->write("          <br><span class=\"fieldprompt\"" .
                      " style=\"text-align: left; font-weight: bold;\">" .
                      "Special Offers</span><br>\n");
    }

    $screen->write("          <script type=\"text/javascript\">" .
                   "load_coupons_grid(false);</script>\n");
    if ($screen->skin) $screen->end_section(true);
    $screen->end_body();
}

function add_discount_buttons($dialog)
{
    $dialog->add_button_separator('coupon_buttons_row',20);
    $dialog->add_button('Add Discount','images/AddCoupon.png',
                        'add_discount();','add_discount',false);
    $dialog->add_button('Delete Discount','images/DeleteCoupon.png',
                        'delete_discount();','delete_discount',false);
    $dialog->add_button('View Order','images/ViewOrder.png','view_order();',
                        'view_order',null,false);
}

function display_coupon_fields($dialog,$edit_type,$db,$row)
{
    global $name_prompt,$name_col_width,$products_label,$enable_schedule;
    global $enable_coupon_inventory,$enable_multisite;

    if (! isset($name_prompt)) $name_prompt = 'Product Name';
    if (! isset($name_col_width)) $name_col_width = 200;
    if ($enable_schedule) require_once 'schedule-admin.php';
    if (! isset($enable_coupon_inventory)) $enable_coupon_inventory = false;
    $flags = get_row_value($row,'flags');
    if (! $flags) $flags = 0;
    $dialog->add_hidden_field('OldFlags',$flags);
    $coupon_flag = get_form_field('flag');
    $dialog->add_hidden_field('flag6',$coupon_flag);
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Offer';
    $dialog->write("      <script type=\"text/javascript\">\n");
    $dialog->write("        flags = ".$flags.";\n");
    if ($enable_coupon_inventory)
       $dialog->write("        enable_coupon_inventory = true;\n");
    $dialog->write("      </script>\n");
    $features = get_cart_config_value('features');
    $coupon_id = $row['id'];
    $coupon_type = get_row_value($row,'coupon_type');
    if ($coupon_type == '')  $coupon_type = 0;

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('coupon_tab','coupon_content','change_tab');
    $dialog->add_tab('coupon_tab',$coupon_label,'coupon_tab','coupon_content',
                     'change_tab',true,null,FIRST_TAB);
    if ($enable_schedule) 
       $dialog->add_tab('schedule_tab','Schedule','coupon_schedule_tab',
                        'schedule_content','change_tab',
                        $flags & COUPON_ADD_TO_SCHEDULE);
    $dialog->add_tab('products_tab',$products_label,'coupon_products_tab',
                     'products_content','change_tab',
                     $flags & COUPON_SELECTED_PRODUCTS);
    $dialog->add_tab('ex_products_tab','Excluded '.$products_label,
                     'coupon_ex_products_tab','ex_products_content',
                     'change_tab',$flags & COUPON_EXCLUDE_PRODUCTS);
    $dialog->add_tab('customers_tab','Customers','coupon_customers_tab',
                     'customers_content','change_tab',
                     $flags & COUPON_SELECTED_CUSTOMERS);
    $dialog->add_tab('discounts_tab','Discounts','coupon_discounts_tab',
                     'discounts_content','change_tab',$coupon_type == 8);
    $dialog->add_tab('usage_tab','Usage','usage_tab','usage_content',
                     'change_tab');

    if (function_exists('setup_coupon_tabs')) setup_coupon_tabs($dialog,$row);
    $dialog->end_tab_row('tab_row_middle');

    $dialog->start_tab_content('coupon_content',true);
    $dialog->start_field_table('coupon_table');
    $dialog->add_hidden_field('id',$coupon_id);
    if ($coupon_flag == 'true')
       $dialog->add_edit_row('Coupon Code:','coupon_code',
                             get_row_value($row,'coupon_code'),40);
    $dialog->add_textarea_row('Description:','description',
                              get_row_value($row,'description'),5,80,WRAP_SOFT);

    $dialog->start_row($coupon_label.' Type:','middle');
    $dialog->start_choicelist('coupon_type','change_coupon_type();');
    if (($edit_type == ADDRECORD) || ($coupon_type == 0))
       $dialog->add_list_item('','',$coupon_type == 0);
    $dialog->add_list_item('1','Percentage Off',$coupon_type == 1);
    $dialog->add_list_item('2','Amount Off',$coupon_type == 2);
    $dialog->add_list_item('3','Free Shipping',$coupon_type == 3);
    if ($features & GIFT_CERTIFICATES)
       $dialog->add_list_item('4','Gift Certificate',$coupon_type == 4);
    $dialog->add_list_item('5','Free Order',$coupon_type == 5);
    $dialog->add_list_item('6','Free Product',$coupon_type == 6);
    $dialog->add_list_item('7','Buy 1 Get 1 at x% Off',$coupon_type == 7);
    $dialog->add_list_item('8','Quantity Discount',$coupon_type == 8);
    if (function_exists('add_coupon_types')) add_coupon_types($dialog,$row);
    $dialog->end_choicelist();
    $dialog->write('<span id="buy1_flag"');
    if ($coupon_type != 7) $dialog->write(' style="display:none;"');
    $dialog->write('>');
    $dialog->add_checkbox_field('flag5','Any Item w/ Same or Lower Price',
                                $flags & COUPON_SAME_LOWER);
    $dialog->write('</span>');
    $dialog->end_row();
    $dialog->write("<tr><td></td><td>\n");
    $dialog->add_checkbox_field('flag7','Includes Free Shipping',
                                $flags & COUPON_FREE_SHIPPING);
    $dialog->end_row();
    if (! empty($enable_multisite)) {
       $dialog->start_row('Web Sites:','top');
       $websites = get_row_value($row,'websites');
       list_website_checkboxes($db,$dialog,$websites,'',4,null);
       $dialog->end_row();
    }
    $dialog->write("<tr valign=\"top\" id=\"available_row\">" .
                   "<td class=\"fieldprompt\" nowrap>" .
                   "Available For:</td><td style=\"padding: 0px;\">\n");
    $dialog->write("<table cellspacing=\"4\" cellpadding=\"0\"><tr><td>\n");
    $dialog->add_radio_field('flag1','0','All '.$products_label,
                             ! ($flags & COUPON_SELECTED_PRODUCTS),
                             'change_flags();');
    $dialog->write('</td><td>&nbsp;&nbsp;');
    $dialog->add_radio_field('flag1','1','Selected '.$products_label,
                             $flags & COUPON_SELECTED_PRODUCTS,
                             'change_flags();');
    $dialog->write('</td><td>&nbsp;&nbsp;');
    $dialog->add_checkbox_field('flag8','Exclude Products',
                                $flags & COUPON_EXCLUDE_PRODUCTS,
                                'change_flags();');
    $dialog->write("</td></tr>\n<tr><td>\n");
    $dialog->add_radio_field('flag2','0','All Customers',
                             ! ($flags & COUPON_SELECTED_CUSTOMERS),
                             'change_flags();');
    $dialog->write('</td><td>&nbsp;&nbsp;');
    $dialog->add_radio_field('flag2','2','Selected Customers',
                             $flags & COUPON_SELECTED_CUSTOMERS,
                             'change_flags();');
    $dialog->end_row();
    $dialog->write("<tr><td colspan=\"2\">\n");
    $dialog->add_checkbox_field('flag4',
                                'Only Available for Registered Customers',
                                $flags & COUPON_ONLY_REGISTERED);
    $dialog->end_row();
    if (function_exists('display_custom_coupon_flags'))
       display_custom_coupon_flags($dialog,$edit_type,$row,$db);
    $dialog->write("</table>\n</td></tr>\n");

    if ($enable_schedule) {
       $dialog->start_hidden_row('Add to Schedule:','schedule_row',false,
                                 'middle');
       $dialog->add_checkbox_field('flag3','',$flags & COUPON_ADD_TO_SCHEDULE,
                                   'change_flags();');
       $dialog->end_row();
    }

    $dialog->start_hidden_row('Free Product:','free_product_row',
                              ($coupon_type != 6));
    $free_product = get_row_value($row,'free_product');
    $dialog->write("<span id=\"free_product_cell\"");
    if (! $free_product) $dialog->write(" style=\"display: none;\"");
    $dialog->write(">\n");
    if ($enable_coupon_inventory)
       $free_prod_attrs = get_row_value($row,'free_prod_attrs');
    if ($free_product) {
       $query = 'select name from products where id=?';
       $query = $db->prepare_query($query,$free_product);
       $product_row = $db->get_record($query);
       if ($product_row && $product_row['name']) {
          $dialog->write($product_row['name']);
          if ($enable_coupon_inventory) {
             $query = 'select part_number from product_inventory where ' .
                      'parent=? and attributes=?';
             $query = $db->prepare_query($query,$free_product,$free_prod_attrs);
             $inv_row = $db->get_record($query);
             if ($inv_row && $inv_row['part_number'])
                $dialog->write(' ('.$inv_row['part_number'].')');
          }
       }
    }
    $dialog->write("</span>\n");
    $dialog->add_hidden_field('free_product',$free_product);
    if ($enable_coupon_inventory)
       $dialog->add_hidden_field('free_prod_attrs',$free_prod_attrs);
    $dialog->write("<input type=\"button\" class=\"small_button\" " .
                   "style=\"margin-left: 0px;\" ");
    $dialog->write("value=\"Select...\" onClick=\"select_product();\">\n");
    $dialog->end_row();

    $dialog->write("<tr valign=\"bottom\" id=\"amount_row\"");
    if (($coupon_type == 0) || ($coupon_type == 3) || ($coupon_type == 5) ||
        ($coupon_type == 6))
       $dialog->write(" style=\"display:none;\"");
    $dialog->write('>');
    $dialog->write("<td class=\"fieldprompt\" nowrap style=\"padding-left: 20px;\">");
    $dialog->write("Amount:</td><td>\n");
    $dialog->write("<input type=\"text\" class=\"text\" name=\"amount\" ");
    $dialog->write("size=\"10\" value=\"");
    $dialog->write(get_row_value($row,'amount'));
    $dialog->write("\"");
    if ($edit_type == ADDRECORD) $dialog->write(" onBlur=\"set_balance();\"");
    $dialog->write("><span id=\"percent_sign\"");
    if (($coupon_type != 1) && ($coupon_type != 7))
       $dialog->write(" style=\"display:none;\"");
    $dialog->write(">%</span></td></tr>\n");

    $dialog->write("<tr valign=\"bottom\" id=\"min_amount_row\"");
    if (($coupon_type == 0) || ($coupon_type == 4))
       $dialog->write(" style=\"display:none;\"");
    $dialog->write('>');
    $dialog->write("<td class=\"fieldprompt\" nowrap style=\"padding-left: 20px;\">");
    $dialog->write("Minimum Order Amount:</td><td>\n");
    $dialog->write("<input type=\"text\" class=\"text\" name=\"min_amount\" ");
    $dialog->write("size=\"10\" value=\"");
    $dialog->write(get_row_value($row,'min_amount'));
    $dialog->write("\"></td></tr>\n");

    $dialog->start_hidden_row('Balance:','balance_row',($coupon_type != 4));
    $dialog->add_input_field('balance',get_row_value($row,'balance'),10);
    $dialog->end_row();

    if (($edit_type == UPDATERECORD) && ($coupon_type == 4)) {
       $dialog->start_hidden_row('Used By:','usedby_row');
       $dialog->write("<tt>\n");
       $gift_customer = get_row_value($row,'gift_customer');
       if ((! isset($gift_customer)) || ($gift_customer == ''))
          $dialog->write('&nbsp;');
       else {
          $query = 'select fname,lname from customers where id=?';
          $query = $db->prepare_query($query,$gift_customer);
          $customer = $db->get_record($query);
          if (! $customer) {
             if (isset($db->error)) $dialog->write('Database Error: '.$db->error);
             else $dialog->write('Unknown');
          }
          else {
             $db->decrypt_record('customers',$customer);
             $dialog->write($customer['fname'].' '.$customer['lname']);
          }
       }
       $dialog->write("</tt>\n");
       $dialog->end_row();
    }

    if ($edit_type == ADDRECORD) $start_date = time();
    else $start_date = get_row_value($row,'start_date');
    $dialog->start_hidden_row('Start Date:','start_date_row',
                              ($coupon_type == 0));
    $dialog->add_date_field('start_date',$start_date,true);
    $dialog->end_row();

    if ($edit_type == ADDRECORD) $end_date = null;
    else $end_date = get_row_value($row,'end_date');
    $dialog->start_hidden_row('End Date:','end_date_row',
                              ($coupon_type == 0));
    $dialog->add_date_field('end_date',$end_date,true);
    $dialog->end_row();

    $dialog->start_hidden_row('Maximum Quantity:','max_qty_row',
                              (($coupon_type == 0) || ($coupon_type == 4)));
    $dialog->add_input_field('max_qty',get_row_value($row,'max_qty'),5);
    $dialog->end_row();

    $dialog->start_hidden_row('Max Qty Per Customer:','max_qty_per_cust_row',
                              (($coupon_type == 0) || ($coupon_type == 4)));
    $dialog->add_input_field('max_qty_per_cust',
                             get_row_value($row,'max_qty_per_cust'),5);
    $dialog->end_row();

    $dialog->start_hidden_row('Quantity Used:','qty_used_row',
                              (($coupon_type == 0) || ($coupon_type == 4)));
    $dialog->add_input_field('qty_used',get_row_value($row,'qty_used'),5);
    $dialog->end_row();

    if (function_exists('display_custom_coupon_fields'))
       display_custom_coupon_fields($dialog,$edit_type,$row,$db);

    $dialog->end_field_table();
    $dialog->end_tab_content();

    if ($enable_schedule) add_coupon_schedule_tab_content($db,$dialog,$row);

    $dialog->start_tab_content('products_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script type=\"text/javascript\">\n");
    $dialog->write("           var products = new SubList();\n");
    $dialog->write("           products.name = 'products';\n");
    $dialog->write("           products.script_url = 'coupons.php';\n");
    $dialog->write("           products.frame_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("edit_coupon';\n");
    else $dialog->write("add_coupon';\n");
    $dialog->write("           products.form_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("EditCoupon';\n");
    else $dialog->write("AddCoupon';\n");
    if ($dialog->skin)
       $dialog->write("           products.grid_width = 0;\n");
    else $dialog->write("           products.grid_width = 300;\n");
    $dialog->write("           products.grid_height = ");
    if ($enable_coupon_inventory) $dialog->write("250");
    else $dialog->write("380");
    $dialog->write(";\n");
    $dialog->write("           products.left_table = 'coupon_products';\n");
    $dialog->write("           products.left_titles = ['".$name_prompt .
                   "','Description'];\n");
    $dialog->write("           products.left_widths = [".$name_col_width .
                   ",-1];\n");
    $dialog->write("           products.left_fields = 'r.name,r.short_description';\n");
    $dialog->write("           products.left_label = 'products';\n");
    $dialog->write("           products.right_table = 'products';\n");
    $dialog->write("           products.right_titles = ['".$name_prompt .
                   "','Description'];\n");
    $dialog->write("           products.right_widths = [".$name_col_width .
                   ",-1];\n");
    $dialog->write("           products.right_fields = 'name,short_description';\n");
    $dialog->write("           products.right_label = 'products';\n");
    $dialog->write("           products.right_single_label = 'product';\n");
    $dialog->write("           products.default_frame = 'edit_coupon';\n");
    $dialog->write("           products.enable_double_click = false;\n");
    $dialog->write("           products.categories = false;\n");
    $dialog->write("           products.search_where = \"name like '%\$query\$%'" .
                   " or short_description like '%\$query\$%' or long_description" .
                   " like '%\$query\$%' or id='\$query\$'");
    if ($features & USE_PART_NUMBERS)
       $dialog->write(" or id in (select parent from product_inventory " .
                     "where part_number like '%\$query\$%')");
    $dialog->write("\";\n");
    $dialog->write("        </script>\n");
    create_sublist_grids('products',$dialog,$coupon_id,$products_label .
       ' available for this '.$coupon_label,'All '.$products_label,false,
       'ProductsQuery',$products_label,true,'add_multiple_products(0);');
    if ($enable_coupon_inventory) {
       $dialog->write("        <script type=\"text/javascript\">\n");
       $query = 'select id,name from attribute_options order by id';
       $options = $db->get_records($query,'id','name');
       if ($options) foreach ($options as $option_id => $option_name) {
          $option_name = str_replace("'","\\'",$option_name);
          $dialog->write('          attribute_options['.$option_id.'] = \'' .
                         $option_name."';\n");
       }
       $query = 'select related_id from coupon_products where parent=? ' .
                'order by sequence limit 1';
       $query = $db->prepare_query($query,$coupon_id);
       $product_row = $db->get_record($query);
       if ($product_row) $product_id = $product_row['related_id'];
       else $product_id = 0;
       $dialog->write('          create_inventory_grid('.$coupon_id.',' .
                      $product_id.");\n");
       $dialog->write("        </script>\n");
    }
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->start_tab_content('ex_products_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script type=\"text/javascript\">\n");
    $dialog->write("           var ex_products = new SubList();\n");
    $dialog->write("           ex_products.name = 'ex_products';\n");
    $dialog->write("           ex_products.script_url = 'coupons.php';\n");
    $dialog->write("           ex_products.frame_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("edit_coupon';\n");
    else $dialog->write("add_coupon';\n");
    $dialog->write("           ex_products.form_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("EditCoupon';\n");
    else $dialog->write("AddCoupon';\n");
    if ($dialog->skin)
       $dialog->write("           ex_products.grid_width = 0;\n");
    else $dialog->write("           ex_products.grid_width = 300;\n");
    $dialog->write("           ex_products.grid_height = 380;\n");
    $dialog->write("           ex_products.left_table = 'coupon_excluded_products';\n");
    $dialog->write("           ex_products.left_titles = ['".$name_prompt .
                   "','Description'];\n");
    $dialog->write("           ex_products.left_widths = [".$name_col_width .
                   ",-1];\n");
    $dialog->write("           ex_products.left_fields = 'r.name,r.short_description';\n");
    $dialog->write("           ex_products.left_label = 'products';\n");
    $dialog->write("           ex_products.right_table = 'products';\n");
    $dialog->write("           ex_products.right_titles = ['".$name_prompt .
                   "','Description'];\n");
    $dialog->write("           ex_products.right_widths = [".$name_col_width .
                   ",-1];\n");
    $dialog->write("           ex_products.right_fields = 'name,short_description';\n");
    $dialog->write("           ex_products.right_label = 'products';\n");
    $dialog->write("           ex_products.right_single_label = 'product';\n");
    $dialog->write("           ex_products.default_frame = 'edit_coupon';\n");
    $dialog->write("           ex_products.enable_double_click = false;\n");
    $dialog->write("           ex_products.categories = false;\n");
    $dialog->write("           ex_products.search_where = \"name like '%\$query\$%'" .
                   " or short_description like '%\$query\$%' or long_description" .
                   " like '%\$query\$%' or id='\$query\$'");
    if ($features & USE_PART_NUMBERS)
       $dialog->write(" or id in (select parent from product_inventory " .
                     "where part_number like '%\$query\$%')");
    $dialog->write("\";\n");
    $dialog->write("        </script>\n");
    create_sublist_grids('ex_products',$dialog,$coupon_id,
       $products_label.' not available for this '.$coupon_label,
       'All '.$products_label,false,'ExProductsQuery',$products_label,
       true,'add_multiple_products(1);');
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->start_tab_content('customers_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script type=\"text/javascript\">\n");
    $dialog->write("           var customers = new SubList();\n");
    $dialog->write("           customers.name = 'customers';\n");
    $dialog->write("           customers.script_url = 'coupons.php';\n");
    $dialog->write("           customers.frame_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("edit_coupon';\n");
    else $dialog->write("add_coupon';\n");
    $dialog->write("           customers.form_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("EditCoupon';\n");
    else $dialog->write("AddCoupon';\n");
    if ($dialog->skin)
       $dialog->write("           customers.grid_width = 0;\n");
    else $dialog->write("           customers.grid_width = 300;\n");
    $dialog->write("           customers.grid_height = 380;\n");
    $dialog->write("           customers.left_table = 'coupon_customers';\n");
    $dialog->write("           customers.left_titles = ['First Name','Last Name'];\n");
    $dialog->write("           customers.left_widths = [100,-1];\n");
    $dialog->write("           customers.left_fields = 'r.fname,r.lname';\n");
    $dialog->write("           customers.left_order = 'l.sequence,r.lname,r.fname';\n");
    $dialog->write("           customers.left_label = 'customers';\n");
    $dialog->write("           customers.right_table = 'customers';\n");
    $dialog->write("           customers.right_titles = ['First Name','Last Name'];\n");
    $dialog->write("           customers.right_widths = [100,-1];\n");
    $dialog->write("           customers.right_fields = 'fname,lname';\n");
    $dialog->write("           customers.right_order = 'lname,fname,id';\n");
    $dialog->write("           customers.right_label = 'customers';\n");
    $dialog->write("           customers.right_single_label = 'customers';\n");
    $dialog->write("           customers.default_frame = 'edit_coupon';\n");
    $dialog->write("           customers.enable_double_click = false;\n");
    $dialog->write("           customers.categories = false;\n");
    $dialog->write("           customers.search_where = \"email like '%\$query\$%'" .
                   " or fname like '%\$query\$%' or lname like '%\$query\$%' or " .
                   "company like '%\$query\$%'\";\n");
    $dialog->write("        </script>\n");
    create_sublist_grids('customers',$dialog,$coupon_id,
       'Customers allowed to use this '.$coupon_label,'All Customers',false,
       'CustomersQuery','Customers',true);
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->start_tab_content('discounts_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("          <script type=\"text/javascript\">" .
                   "create_discounts_grid(".$coupon_id.");</script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->start_tab_content('usage_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("          <script type=\"text/javascript\">" .
                   "create_usage_grid(".$coupon_id.");</script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    if (function_exists('display_custom_coupon_tab_sections'))
       display_custom_coupon_tab_sections($dialog,$db,$row,$edit_type);

    $dialog->end_tab_section();
}

function parse_coupon_fields($db,&$coupon_record)
{
    global $enable_multisite;

    $db->parse_form_fields($coupon_record);
    if (! empty($enable_multisite)) parse_website_checkboxes($coupon_record);
    $flags = 0;
    if (get_form_field('flag1') == 1) $flags |= 1;
    if (get_form_field('flag2') == 2) $flags |= 2;
    if (get_form_field('flag3') == 'on') $flags |= 4;
    if (get_form_field('flag4') == 'on') $flags |= 8;
    if (get_form_field('flag5') == 'on') $flags |= 16;
    if (get_form_field('flag6') == 'false') $flags |= 32;
    if (get_form_field('flag7') == 'on') $flags |= 64;
    if (get_form_field('flag8') == 'on') $flags |= 128;
    $coupon_record['flags']['value'] = $flags;
}

function create_coupon()
{
    $coupon_flag = get_form_field('flag');
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Special Offer';
    $db = new DB;
    $coupon_record = coupon_record_definition();
    if ($coupon_flag == 'true')
       $coupon_record['coupon_code']['value'] = 'New Coupon';
    else {
       $coupon_record['description']['value'] = 'New Special Offer';
       $coupon_record['flags']['value'] = COUPON_SPECIAL_OFFER;
    }
    if (! $db->insert('coupons',$coupon_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'coupon_id = '.$id.';';
    log_activity('Created New '.$coupon_label.' #'.$id);
}

function add_coupon()
{
    global $enable_schedule;

    $coupon_flag = get_form_field('flag');
    if ($coupon_flag == 'true') $coupon_label = $short_label = 'Coupon';
    else {
       $coupon_label = 'Special Offer';   $short_label = 'Offer';
    }
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from coupons where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($coupon_label.' not found',0);
       return;
    }
    $row['coupon_code'] = '';
    $row['description'] = '';
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('coupons.css');
    $dialog->add_style_sheet('utility.css');
    $dialog->add_script_file('coupons.js');
    $dialog->add_script_file('sublist.js');
    $dialog->add_script_file('utility.js');
    if ($enable_schedule) $dialog->add_script_file('schedule-admin.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_head_variables($dialog);
    $dialog->set_onload_function('add_coupon_onload();');
    $dialog_title = 'Add '.$coupon_label.' (#'.$id.')';
    $dialog->set_body_id('add_coupon');
    $dialog->set_help('add_coupon');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(140);
    $dialog->start_button_column(false,false,true);
    $dialog->start_bottom_buttons(false);
    $dialog->add_button('Add '.$short_label,'images/AddCoupon.png',
                        'process_add_coupon();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_bottom_buttons();
    add_discount_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('coupons.php','AddCoupon');
    if (! $dialog->skin) $dialog->start_field_table();
    display_coupon_fields($dialog,ADDRECORD,$db,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_coupon()
{
    global $enable_schedule;

    $coupon_flag = get_form_field('flag6');
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Special Offer';
    $db = new DB;
    $coupon_record = coupon_record_definition();
    parse_coupon_fields($db,$coupon_record);
    if ($coupon_flag == 'true') {
       $query = 'select id from coupons where coupon_code=?';
       $query = $db->prepare_query($query,
                                   $coupon_record['coupon_code']['value']);
       $row = $db->get_record($query);
       if ((! $row) && isset($db->error)) {
          http_response(422,$db->error);   return;
       }
       else if ($row) {
          http_response(406,'Coupon Code already exists');   return;
       }
    }
    $id = $coupon_record['id']['value'];
    if ($enable_schedule) {
       require_once 'schedule-admin.php';
       if (! update_coupon_schedule($db,$coupon_record)) return;
    }
    if (function_exists('custom_update_coupon_record'))
       custom_update_coupon_record($db,$coupon_record);
    if (! $db->update('coupons',$coupon_record)) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('custom_finish_coupon_update') &&
        (! custom_finish_coupon_update($db,$coupon_record,ADDRECORD))) return;
    http_response(201,$coupon_label.' Added');
    if ($coupon_flag == 'true')
       log_activity('Added Coupon '.$coupon_record['coupon_code']['value'] .
                    ' (#'.$id.')');
    else log_activity('Added Special Offer #'.$id);
}

function edit_coupon()
{
    global $enable_schedule;

    $coupon_flag = get_form_field('flag');
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Special Offer';
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from coupons where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error($coupon_label.' not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('coupons.css');
    $dialog->add_style_sheet('utility.css');
    $dialog->add_script_file('coupons.js');
    $dialog->add_script_file('sublist.js');
    $dialog->add_script_file('utility.js');
    if ($enable_schedule) $dialog->add_script_file('schedule-admin.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    add_head_variables($dialog);
    $dialog->set_onload_function('edit_coupon_onload();');
    $dialog_title = 'Edit '.$coupon_label.' (#'.$id.')';
    $dialog->set_body_id('edit_coupon');
    $dialog->set_help('edit_coupon');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(140);
    $dialog->start_button_column(false,false,true);
    $dialog->start_bottom_buttons(false);
    $dialog->add_button('Update','images/Update.png','update_coupon();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_bottom_buttons();
    add_discount_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('coupons.php','EditCoupon');
    if (! $dialog->skin) $dialog->start_field_table();
    display_coupon_fields($dialog,UPDATERECORD,$db,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_coupon()
{
    global $enable_schedule;

    $coupon_flag = get_form_field('flag6');
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Special Offer';
    $db = new DB;
    $coupon_record = coupon_record_definition();
    parse_coupon_fields($db,$coupon_record);
    if ($enable_schedule) {
       require_once 'schedule-admin.php';
       if (! update_coupon_schedule($db,$coupon_record)) return;
    }
    if (function_exists('custom_update_coupon_record'))
       custom_update_coupon_record($db,$coupon_record);
    if (! $db->update('coupons',$coupon_record)) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('custom_finish_coupon_update') &&
        (! custom_finish_coupon_update($db,$coupon_record,UPDATERECORD)))
       return;
    http_response(201,$coupon_label.' Updated');
    if ($coupon_flag == 'true')
       log_activity('Updated Coupon '.$coupon_record['coupon_code']['value'] .
                    ' (#'.$coupon_record['id']['value'].')');
    else log_activity('Updated Special Offer #'.$coupon_record['id']['value']);
}

function delete_coupon()
{
    global $enable_schedule,$enable_coupon_inventory;

    $coupon_flag = get_form_field('flag');
    if ($coupon_flag == 'true') $coupon_label = 'Coupon';
    else $coupon_label = 'Special Offer';
    $db = new DB;
    $id = get_form_field('id');
    if ($enable_coupon_inventory) {
       $query = 'delete from coupon_inventory where parent in (select ' .
                'related_id from coupon_products where parent=?)';
       $query = $db->prepare_query($query,$id);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
    }
    if (! delete_sublist_items('coupon_products',$id,$db)) {
       http_response(422,$db->error);   return;
    }
    if (! delete_sublist_items('coupon_customers',$id,$db)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from coupon_discounts where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    if ($enable_schedule) {
       require_once 'schedule-admin.php';
       if (! delete_coupon_schedule($db,$id)) return;
    }
    if (function_exists('custom_finish_coupon_update')) {
       $query = 'select * from coupons where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error))
             http_response(422,'Database Error: '.$db->error);
          else http_response(410,$coupon_label.' not found');
          return;
       }
    }
    $coupon_record = coupon_record_definition();
    $coupon_record['id']['value'] = $id;
    if (! $db->delete('coupons',$coupon_record)) {
       http_response(422,$db->error);   return;
    }
    if (function_exists('custom_finish_coupon_update') &&
        (! custom_finish_coupon_update($db,$row,DELETERECORD))) return;
    http_response(201,$coupon_label.' Deleted');
    log_activity('Deleted '.$coupon_label.' #'.$id);
}

function add_multiple_products()
{
    global $products_label;

    $db = new DB;
    $coupon_id =  get_form_field('id');
    $ids = get_form_field('ids');
    $frame = get_form_field('Frame');
    $type = get_form_field('Type');
    $id_array = explode(',',$ids);
    $dialog = new Dialog;
    setup_product_change_dialog($dialog);
    $dialog->add_style_sheet('coupons.css');
    $dialog->add_script_file('coupons.js');
    $dialog->set_body_id('add_multiple_products');
    $dialog->set_help('add_multiple_products');
    $dialog->start_body('Add Multiple '.$products_label);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddProduct.png',
                        'process_add_multiple_products();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('coupons.php','AddMultipleProducts');
    $dialog->add_hidden_field('id',$coupon_id);
    $dialog->add_hidden_field('ids',$ids);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('Type',$type);
    $dialog->start_field_table();
    display_product_change_choices($db,$dialog,$id_array,false);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_multiple_products()
{
    global $products_label;

    $coupon_id = get_form_field('id');
    $db = new DB;
    $id_array = parse_product_change_choices($db);
    if (count($id_array) == 0) {
       http_response(201,'No '.$products_label.' found to add');   return;
    }
    $type = get_form_field('Type');
    if ($type == 0) $table = 'coupon_products';
    else $table = 'coupon_excluded_products';
    $query = 'select sequence from '.$table.' where parent=? ' .
             'order by sequence desc limit 1';
    $query = $db->prepare_query($query,$coupon_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) {
          http_response(422,$db->error);   return;
       }
       $sequence = 0;
    }
    else $sequence = $row['sequence'];
    $starting_sequence = $sequence + 1;

    $sublist_record = sublist_record_definition();
    $sublist_record['parent']['value'] = $coupon_id;
    foreach ($id_array as $id) {
       $sublist_record['related_id']['value'] = $id;
       $sequence++;
       $sublist_record['sequence']['value'] = $sequence;
       if (! $db->insert($table,$sublist_record)) {
          http_response(422,$db->error);   return;
       }
    }

    log_activity('Added Sequence #s '.$starting_sequence.'-'.$sequence .
                 ' to '.$table.' for Coupon #'.$coupon_id);
    http_response(201,'Multiple '.$products_label.' Added');
}

function update_coupon_inventory()
{
    $db = new DB;
    $coupon_inv_record = coupon_inventory_record_definition();
    $db->parse_form_fields($coupon_inv_record);
    $part_number = get_form_field('part_number');
    $available = get_form_field('available');
    $command = get_form_field('Command');
    if (isset($coupon_inv_record['id']['value']) &&
        $coupon_inv_record['id']['value'] &&
        ($command == 'AddRecord')) $command = 'UpdateRecord';
    else if (((! isset($coupon_inv_record['id']['value'])) ||
              (! $coupon_inv_record['id']['value'])) &&
             ($command == 'UpdateRecord')) $command = 'AddRecord';
    if ($command == 'UpdateRecord') {
       if ($available == 'true') {
          http_response(201,'No Update');   return;
       }
       $command = 'DeleteRecord';
    }
    if ($command == 'AddRecord') {
       if ($available != 'true') {
          http_response(201,'No Update');   return;
       }
       if (! $db->insert('coupon_inventory',$coupon_inv_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Coupon Inventory Added');
       log_activity('Added Coupon Inventory #' . 
                    $coupon_inv_record['related_id']['value'] .
                    ' ('.$part_number.') to Coupon #' .
                    $coupon_inv_record['parent']['value']);
    }
    else if ($command == 'DeleteRecord') {
       if ((! isset($coupon_inv_record['id']['value'])) ||
           (! $coupon_inv_record['id']['value'])) {
          http_response(201,'No Update');   return;
       }
       if (! $db->delete('coupon_inventory',$coupon_inv_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Coupon Inventory Deleted');
       log_activity('Deleted Coupon Inventory #' .
                    $coupon_inv_record['related_id']['value'] .
                    ' ('.$part_number.') from Coupon #' .
                    $coupon_inv_record['parent']['value']);
    }
    else log_activity('update_coupon_inventory, Unknown Command = '.$command);
}

function update_discount()
{
    $db = new DB;
    $discount_record = discount_record_definition();
    $db->parse_form_fields($discount_record);
    $cmd = get_form_field('Command');
    if ($cmd == 'DeleteRecord') {
       if (! $db->delete('coupon_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Discount Deleted');
       log_activity('Deleted Coupon Discount ' . 
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Coupon #' .
                    $discount_record['parent']['value']);
    }
    else if (! $discount_record['id']['value']) {
       unset($discount_record['id']['value']);
       if (! $db->insert('coupon_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Discount Added');
       log_activity('Added Coupon Discount ' . 
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Coupon #' .
                    $discount_record['parent']['value']);
    }
    else {
       if (! $db->update('coupon_discounts',$discount_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Discount Updated');
       log_activity('Updated Coupon Discount ' .
                    $discount_record['start_qty']['value'] .
                    '-'.$discount_record['end_qty']['value'].' for Coupon #' .
                    $discount_record['parent']['value']);
    }
}

if (! check_login_cookie()) exit;

init_sublists('coupons.php','coupons.js',0);
$cmd = get_form_field('cmd');

if ($cmd == 'createcoupon') create_coupon();
else if ($cmd == 'addcoupon') add_coupon();
else if ($cmd == 'processaddcoupon') process_add_coupon();
else if ($cmd == 'editcoupon') edit_coupon();
else if ($cmd == 'updatecoupon') update_coupon();
else if ($cmd == 'deletecoupon') delete_coupon();
else if ($cmd == 'addmultipleproducts') add_multiple_products();
else if ($cmd == 'processaddmultipleproducts') process_add_multiple_products();
else if ($cmd == 'updatecouponinventory') update_coupon_inventory();
else if ($cmd == 'updatediscount') update_discount();
else if (process_sublist_command($cmd)) {}
else display_coupons_screen();

DB::close_all();

?>
