<?php
/*
               Inroads Shopping Cart - Products Tab - Inventory SubTab

                          Written 2008-2019 by Randall Severy
                           Copyright 2008-2019 Inroads, LLC
*/

global $part_number_prompt;
if (! isset($part_number_prompt)) $part_number_prompt = 'Part #';

global $inv_header_fields;
$inv_header_fields = array('id'=>'Inv ID','sequence'=>'Seq',
   'parent'=>'Product ID','attributes'=>'Attr IDs',
   'part_number'=>$part_number_prompt,'qty'=>'Qty','min_qty'=>'Min Qty',
   'min_order_qty'=>'Min Order Qty','weight'=>'Weight',
   'list_price'=>'List Price','price'=>'Price','sale_price'=>'Sale Price',
   'cost'=>'Cost','origin_zip'=>'Origin Zip','image'=>'Image',
   'available'=>'Available','backorder'=>'Backorderable');

function inventory_record_definition()
{
    global $inventory_fields;

    $inventory_record = array();
    $inventory_record['id'] = array('type' => INT_TYPE);
    $inventory_record['id']['key'] = true;
    $inventory_record['sequence'] = array('type' => INT_TYPE);
    $inventory_record['parent'] = array('type' => INT_TYPE);
    $inventory_record['attributes'] = array('type' => CHAR_TYPE);
    $inventory_record['part_number'] = array('type' => CHAR_TYPE);
    $inventory_record['qty'] = array('type' => INT_TYPE);
    $inventory_record['min_qty'] = array('type' => INT_TYPE);
    $inventory_record['min_order_qty'] = array('type' => INT_TYPE);
    $inventory_record['weight'] = array('type' => INT_TYPE);
    $inventory_record['list_price'] = array('type' => FLOAT_TYPE);
    $inventory_record['price'] = array('type' => FLOAT_TYPE);
    $inventory_record['sale_price'] = array('type' => FLOAT_TYPE);
    $inventory_record['cost'] = array('type' => FLOAT_TYPE);
    $inventory_record['origin_zip'] = array('type' => CHAR_TYPE);
    $inventory_record['image'] = array('type' => CHAR_TYPE);
    $inventory_record['available'] = array('type' => INT_TYPE);
    $inventory_record['available']['fieldtype'] = CHECKBOX_FIELD;
    $inventory_record['backorder'] = array('type' => INT_TYPE);
    $inventory_record['backorder']['fieldtype'] = CHECKBOX_FIELD;
    $inventory_record['last_modified'] = array('type' => INT_TYPE);
    if (isset($inventory_fields)) {
       foreach ($inventory_fields as $field_name => $field)
          if ($field['datatype'])
             $inventory_record[$field_name] = array('type' => $field['datatype']);
    }
    return $inventory_record;
}

function inventory_link_record_definition()
{
    $inventory_link_record = array();
    $inventory_link_record['id'] = array('type' => INT_TYPE);
    $inventory_link_record['id']['key'] = true;
    $inventory_link_record['primary_id'] = array('type' => INT_TYPE);
    $inventory_link_record['linked_id'] = array('type' => INT_TYPE);
    return $inventory_link_record;
}

function get_inventory_header_fields()
{
    global $inv_header_fields,$inventory_fields;

    $header_fields = $inv_header_fields;
    if (isset($inventory_fields)) {
       foreach ($inventory_fields as $field_name => $field) {
           if ($field['datatype']) {
              if (empty($field['title']))
                 $header_fields[$field_name] = $field_name;
              else $header_fields[$field_name] = $field['title'];
           }
       }
    }
    return $header_fields;
}

function add_inventory_buttons($dialog,$enabled=true)
{
    $dialog->add_button('Add Inventory','images/AddInventory.png',
                        'add_inventory();','add_inventory',$enabled);
    $dialog->add_button('Edit Inventory','images/EditInventory.png',
                        'edit_inventory();','edit_inventory',$enabled);
    $dialog->add_button('Delete Inventory','images/DeleteInventory.png',
                        'delete_inventory();','delete_inventory',$enabled);
}

function write_inventory_data($inventory_info,$features)
{
    global $inventory_field,$enable_inventory_available;

    $first_field = true;

    if ($features & USE_PART_NUMBERS) {
       if ($inventory_info) print $inventory_info['part_number'];
       print '|';
    }
    if ($features & MAINTAIN_INVENTORY) {
       if ($inventory_info) print $inventory_info['qty'];
       print '|';
       if ($inventory_info) print $inventory_info['min_qty'];
       print '|';
    }
    if ((! empty($enable_inventory_available)) ||
        (! ($features & MAINTAIN_INVENTORY))) {
       if ($inventory_info) print $inventory_info['available'];
       print '|';
    }
    if ($features & INVENTORY_BACKORDERS) {
       if ($inventory_info) print $inventory_info['backorder'];
       print '|';
    }
    if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH)) {
       if ($inventory_info) print $inventory_info['min_order_qty'];
       print '|';
    }
    if ($features & WEIGHT_ITEM) {
       if ($inventory_info) print $inventory_info['weight'];
       print '|';
    }
    if ($features & LIST_PRICE_INVENTORY) {
       if ($inventory_info) print $inventory_info['list_price'];
       print '|';
    }
    if ($features & REGULAR_PRICE_INVENTORY) {
       if ($inventory_info) print $inventory_info['price'];
       print '|';
    }
    if ($features & SALE_PRICE_INVENTORY) {
       if ($inventory_info) print $inventory_info['sale_price'];
       print '|';
    }
    if ($features & PRODUCT_COST_INVENTORY) {
       if ($inventory_info) print $inventory_info['cost'];
       print '|';
    }
    if ($features & DROP_SHIPPING) {
       if ($inventory_info) print $inventory_info['origin_zip'];
       print '|';
    }
    if ($inventory_info) print $inventory_info['image'];
    print '|';
    if (isset($inventory_fields)) {
       foreach ($inventory_fields as $field_name => $field) {
          if ($first_field) $first_field = false;
          else print '|';
          if ($inventory_info) print str_replace("'","\\'",$inventory_info[$field_name]);
       }
    }
    print "'; ";
}

function write_inventory_row($inventory_info,$inventory_row,$num_attributes,
                             $features,$no_options)
{
  
    print 'inventory_grid.table._data['.$inventory_row."]='";
    if ($inventory_info) {
       print $inventory_info['id'].'|'.$inventory_info['sequence'].'|';
       if ($no_options) {
          print str_replace('|','^',$inventory_info['attributes']).'|';
          $separator = '|';
       }
       else {
          print $inventory_info['attributes'].'|';
          $separator = '-+-';
       }
       $attributes = explode($separator,$inventory_info['attributes']);
       for ($loop = 0;  $loop < $num_attributes;  $loop++) {
           if (isset($attributes[$loop])) print $attributes[$loop];
           print '|';
       }
       write_inventory_data($inventory_info,$features);
    }
    else {
       print '-1|0||';
       for ($loop = 0;  $loop < $num_attributes;  $loop++) print '|';
       write_inventory_data(null,$features);
    }
}

function process_inv_attributes($options_array,&$row_num,$attribute_num,
                                $num_attributes,$product_attributes,$options,
                                $inventory,$features)
{
    $id = $product_attributes[$attribute_num];
    foreach ($options as $option_info) {
       if ($option_info['parent'] == $id) {
          $options_array[$attribute_num] = $option_info['id'];
          if ($attribute_num == $num_attributes) {
             $attr_key = '';
             for ($loop = 1;  $loop <= $num_attributes;  $loop++) {
                $attr_key .= $options_array[$loop];
                if ($loop < $num_attributes) $attr_key .= '-';
             }
             $curr_info = null;
             foreach ($inventory as $inventory_info) {
                if ($inventory_info['attributes'] == $attr_key) {
                   $curr_info = $inventory_info;   break;
                }
             }

             print 'inventory_grid.table._data['.$row_num."]='";
             if ($curr_info) print $curr_info['id'];
             else print '-1';
             print '|'.$row_num.'|'.$attr_key.'|';
             for ($loop = 1;  $loop <= $num_attributes;  $loop++)
                print $options_array[$loop].'|';
             write_inventory_data($curr_info,$features);
             $row_num++;
          }
          else process_inv_attributes($options_array,$row_num,
                  $attribute_num + 1,$num_attributes,$product_attributes,
                  $options,$inventory,$features);
       }
    }
}

function load_inventory_data($db,$parent,&$attributes,&$product_attributes,
   &$options,&$dynamic_list,&$current_attributes,&$num_attributes,$ajax_flag)
{
    $dynamic_list = false;
    $query = 'select * from attributes';
    $attributes = $db->get_records($query,'id');
    if (! $attributes) {
       if (isset($db->error)) {
          if ($ajax_flag) http_response(422,'Database Error: '.$db->error);
          else print 'Database Error: '.$db->error."<br>\n";
          return false;
       }
       $attributes = array();
    }

    $query = 'select related_id from product_attributes where parent=? ' .
             'order by related_id';
    $query = $db->prepare_query($query,$parent);
    $rows = $db->get_records($query);
    if ((! $rows) && isset($db->error)) {
       if ($ajax_flag) http_response(422,'Database Error: '.$db->error);
       else print 'Database Error: '.$db->error."<br>\n";
       return false;
    }
    $product_attributes = array();   $index = 1;
    $current_attributes = '';   $attribute_ids = array();
    $num_attributes = 0;
    if ($rows) foreach ($rows as $row) {
       $id = $row['related_id'];
       if (! $attributes[$id]) continue;
       if ((! $attributes[$id]['sub_product']) &&
           (! $attributes[$id]['dynamic'])) continue;
       if ($attributes[$id]['dynamic']) $dynamic_list = true;
       if ($index > 1) $current_attributes .= '-';
       $current_attributes .= $id;
       $attribute_ids[] = $id;
       $product_attributes[$index++] = $id;
       $num_attributes++;
    }

    if ($num_attributes > 0) {
       $query = 'select id,parent,name,adjust_type from attribute_options ' .
                'where parent in (?) order by parent,sequence';
       $query = $db->prepare_query($query,$attribute_ids);
       $options = $db->get_records($query,'id');
       if ((! $options) && isset($db->error)) {
          if ($ajax_flag) http_response(422,'Database Error: '.$db->error);
          else print 'Database Error: '.$db->error."<br>\n";
          return false;
       }
    }
    else $options = null;
    return true;
}


function add_inventory_variables(&$dialog)
{
    $script_name = basename($_SERVER['PHP_SELF']);
    $script = "<script>\n";
    $script .= "      script_name = '".$script_name."';\n";
    $script .= '    </script>';
    $dialog->add_head_line($script);
}

function display_inventory_fields($dialog,$edit_type,$db,$id,$row)
{

    global $inventory_fields,$price_prompt;
    global $part_number_prompt,$part_number_size,$enable_inventory_available;

    $features = get_cart_config_value('features',$db);
    if (! isset($price_prompt)) $price_prompt = 'List Price';
    if (! isset($part_number_size)) $part_number_size = 80;
    $parent = get_form_field('Parent');
    $no_options = get_form_field('nooptions');
    if ($no_options) $separator = '|';
    else $separator = '-';

    if (! load_inventory_data($db,$parent,$attributes,$product_attributes,
             $options,$dynamic_list,$current_attributes,$num_attributes,false))
       return;

    if (function_exists('custom_init_inventory_screen'))
       custom_init_inventory_screen($dialog,$db,$row,$edit_type);
    if ($edit_type == UPDATERECORD) $dialog->add_hidden_field('id',$id);
    $dialog->add_hidden_field('Frame',get_form_field('Frame'));
    $dialog->add_hidden_field('parent',$parent);
    if ($no_options) $dialog->add_hidden_field('nooptions',$no_options);
    $dialog->add_edit_row('Sequence:','sequence',$row,3);
    $inv_attributes = get_row_value($row,'attributes');
    $attr_values = explode($separator,$inv_attributes);
    $attr_index = 0;
    foreach ($product_attributes as $attr_id) {
       $attr_type = $attributes[$attr_id]['admin_type'];
       if ($attr_type == 4) $align = 'top';
       else $align = 'middle';
       $dynamic = $attributes[$attr_id]['dynamic'];
       $dialog->start_row($attributes[$attr_id]['name'].':',$align);
       if (! $dynamic) {
          if (isset($attr_values[$attr_index]))
             $attr_value = $attr_values[$attr_index];
          else $attr_value = '';
          $dialog->add_hidden_field('attr_'.$attr_index,$attr_value);
       }
       switch ($attr_type) {
          case 0:
             if (isset($attr_values[$attr_index]))
                $option_id = $attr_values[$attr_index];
             else $option_id = -1;
             if ($dynamic) {
                $dialog->start_choicelist('attr_'.$attr_index);
                foreach ($options as $option_info) {
                   if ($option_info['parent'] == $attr_id) {
                      $option_name = $option_info['name'];
                      if ($option_info['adjust_type'] == 2)
                         $option_name = 'Start - '.$option_name;
                      else if ($option_info['adjust_type'] == 3)
                         $option_name = 'End - '.$option_name;
                      $dialog->add_list_item($option_info['id'],$option_name,
                                             $option_id == $option_info['id']);
                   }
                }
                $dialog->end_choicelist();
             }
             else {
                if (isset($options[$option_id]))
                   $option_name = $options[$option_id]['name'];
                else $option_name = '';
                $dialog->write('<tt>'.$option_name.'</tt>');
             }
             break;
          case 4:
             if (isset($attr_values[$attr_index]))
                $attr_value = $attr_values[$attr_index];
             else $attr_value = '';
             if ($dynamic) {
                $dialog->start_textarea_field('attr_'.$attr_index,
                   $attributes[$attr_id]['height'],$attributes[$attr_id]['width'],
                   WRAP_SOFT);
                write_form_value($attr_value);
                $dialog->end_textarea_field();
             }
             else $dialog->write('<tt>'.$attr_value.'</tt>');
             break;
          default:
             if (isset($attr_values[$attr_index]))
                $attr_value = $attr_values[$attr_index];
             else $attr_value = '';
             if ($dynamic) {
                $dialog->write('<input type="text" class="text" name="attr_' .
                               $attr_index.'" size="20" value="');
                write_form_value($attr_value);
                $dialog->write("\">\n");
             }
             else $dialog->write('<tt>'.$attr_value.'</tt>');
       }
       $dialog->end_row();
       $attr_index++;
    }
    if ($features & USE_PART_NUMBERS)
       $dialog->add_edit_row($part_number_prompt.':','part_number',$row,
                             ($part_number_size/4));
    if ($features & MAINTAIN_INVENTORY) {
       $dialog->add_edit_row('Quantity:','qty',$row,10);
       $dialog->add_edit_row('Minimum Quantity:','min_qty',$row,2);
    }
    if ((! empty($enable_inventory_available)) ||
        (! ($features & MAINTAIN_INVENTORY))) {
       $dialog->start_row('In Stock:','middle');
       $dialog->add_checkbox_field('available','',$row);
       if ($features & MAINTAIN_INVENTORY)
          $dialog->write('(when Quantity is 0)');
       $dialog->end_row();
    }
    if ($features & INVENTORY_BACKORDERS) {
       $dialog->start_row('Backorderable:','middle');
       $dialog->add_checkbox_field('backorder','',$row);
       $dialog->end_row();
    }
    if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH)) {
       $dialog->add_edit_row('Min Order Qty:','min_order_qty',$row,2);
    }
    if ($features & WEIGHT_ITEM)
       $dialog->add_edit_row('Weight:','weight',$row,10);
    if ($features & LIST_PRICE_INVENTORY)
       $dialog->add_edit_row($price_prompt.':','list_price',$row,10);
    if ($features & REGULAR_PRICE_INVENTORY)
       $dialog->add_edit_row('Price:','price',$row,10);
    if ($features & SALE_PRICE_INVENTORY)
       $dialog->add_edit_row('Sale Price:','sale_price',$row,10);
    if ($features & PRODUCT_COST_INVENTORY)
       $dialog->add_edit_row('Product Cost:','cost',$row,10);
    if ($features & DROP_SHIPPING)
       $dialog->add_edit_row('Origin Zip:','origin_zip',$row,10);
    if ($edit_type == UPDATERECORD) $frame_name = 'edit_inventory';
    else $frame_name = 'add_inventory';

    if ($dialog->using_table_cols && ($dialog->num_table_cols > 2)) {
       $dialog->set_row_colspan(3);   $single_row = true;
       if ($dialog->curr_table_col != 0) {
          $dialog->write("</tr>\n");   $dialog->curr_table_col = 0;
       }
    }
    else $single_row = false;
    $dialog->add_browse_row('Image:','image',$row,35,$frame_name,
                            '/images/original/',true,$single_row,true,false);
    if (isset($inventory_fields)) {
       foreach ($inventory_fields as $field_name => $field) {
          if (isset($field['fieldtype'])) $field_type = $field['fieldtype'];
          else $field_type = EDIT_FIELD;
          if (isset($field['fieldwidth'])) $field_width = $field['fieldwidth'];
          else if (isset($field['width'])) $field_width = $field['width'] / 4;
          else continue;
          if (isset($field['prompt'])) $prompt = $field['prompt'];
          else if (isset($field['title'])) $prompt = $field['title'];
          else continue;
          switch ($field_type) {
             case EDIT_FIELD:
                $dialog->add_edit_row($prompt.':',$field_name,$row,$field_width);
                break;
             case TEXTAREA_FIELD:
                $dialog->add_textarea_row($prompt,$field_name,$row,
                                          $field['height'],$field_width,
                                          $field['wrap']);
                break;
             case CHECKBOX_FIELD:
                $dialog->start_row($prompt,'top');
                $dialog->add_checkbox_field($field_name,'',$row);
                $dialog->end_row();
                break;
             case HTMLEDIT_FIELD:
                $dialog->start_row($prompt,'top');
                $dialog->add_htmleditor_popup_field($field_name,$row,
                                 $field['title'],$field_width,$field['height']);
                $dialog->end_row();
                break;
             case CUSTOM_FIELD:
                $dialog->start_row($prompt,'middle');
                if (function_exists('display_custom_inventory_field'))
                   display_custom_inventory_field($dialog,$field_name,$row);
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
                if (isset($field['row_colspan']))
                   $dialog->set_row_colspan($field['row_colspan']);
                $dialog->add_browse_row($prompt,$field_name,$row,
                   $field_width,$frame_name,$browse_dir,$single_dir,$single_row,
                   $browse_image_type,$include_dirpath,false,$resize,null,
                   null,false,0,$suffix);
                break;
          }
       }
    }
    if (function_exists('display_custom_inventory_fields'))
       display_custom_inventory_fields($dialog,$db,$row,$edit_type);
    require_once '../engine/modules.php';
    if (module_attached('display_custom_fields'))
       call_module_event('display_custom_fields',
                         array('inventory',$db,&$dialog,$edit_type,$row));
}

function parse_inventory_fields($db,&$inventory_record)
{
    $no_options = get_form_field('nooptions');
    if ($no_options) $separator = '|';
    else $separator = '-';
    $db->parse_form_fields($inventory_record);
    $index = 0;   $attributes = '';
    while (($option_id = get_form_field('attr_'.$index)) !== null) {
       if ($attributes != '') $attributes .= $separator;
       $attributes .= $option_id;
       $index++;
    }
    $inventory_record['attributes']['value'] = $attributes;
}

function display_inventory_links($dialog,$db,$id)
{
    $query = 'select * from inventory_link where (primary_id=?) or ' .
             '(linked_id=?) order by primary_id,linked_id';
    $query = $db->prepare_query($query,$id,$id);
    $links = $db->get_records($query);
    if ($links) {
       $primary_link = false;   $inv_ids = array();   $inv = array();
       foreach ($links as $index => $link) {
          if ($link['primary_id'] == $id) {
             $primary_link = true;   $inv_ids[] = $link['linked_id'];
             $inv[$link['linked_id']] = array();
          }
          else {
             $inv_ids[] = $link['primary_id'];
             $inv[$link['primary_id']] = array();
          }
       }
       $query = 'select i.id,p.name,i.attributes,i.part_number from ' .
                'products p join product_inventory i on i.parent=p.id ' .
                'where i.id in (?)';
       $query = $db->prepare_query($query,$inv_ids);
       $products = $db->get_records($query);
       $option_ids = array();
       if ($products) foreach ($products as $product) {
          $inv_id = $product['id'];
          if (strpos($product['attributes'],'|') !== false)
             $attributes = explode('|',$product['attributes']);
          else $attributes = explode('-',$product['attributes']);
          $option_ids = array_unique(array_merge($option_ids,$attributes));
          $inv[$inv_id]['product'] = $product['name'];
          $inv[$inv_id]['attributes'] = $attributes;
          $inv[$inv_id]['part_number'] = $product['part_number'];
       }
       if (! empty($option_ids)) {
          $query = 'select o.id,coalesce(nullif(a.display_name,""),a.name) ' .
                   'as attr_name,o.name as option_name ' .
                   'from attributes a join attribute_options o on a.id=' .
                   'o.parent where o.id in (?)';
          $query = $db->prepare_query($query,$option_ids);
          $options = $db->get_records($query,'id');
          foreach ($inv as $inv_id => $inv_info) {
             $option_ids = $inv_info['attributes'];
             $attributes = '';
             foreach ($option_ids as $option_id) {
                if (! isset($options[$option_id])) continue;
                $option_info = $options[$option_id];
                if ($attributes) $attributes .= ', ';
                $attributes .= $option_info['attr_name'].': ' .
                               $option_info['option_name'];
             }
             $inv[$inv_id]['attributes'] = $attributes;
          }
       }
    }

    $dialog->add_section_row('Linked Inventory');
    $dialog->write('<tr><td colspan="2" style="padding:0px;">');
    $dialog->start_table(null,'linked_inventory',4);
    $dialog->write('<tr><th align="left">Product</th><th align="left">' .
                   "Attributes</th><th>Part Number</th></tr>\n");
    if ($links) foreach ($inv as $inv_row) {
       $dialog->write('<tr valign="top"><td>'.$inv_row['product'].'</td><td>' .
                      $inv_row['attributes'].'</td><td align="center">' .
                      $inv_row['part_number']."</td></tr>\n");
    }
    $dialog->end_table();
    $dialog->end_row();
    $dialog->write('<tr><td colspan="2" align="center">');
    if ((! $links) || $primary_link)
       $dialog->write('<input type="button" class="' .
                     'small_button" value="Manage Links..." ' .
                      'onClick="manage_links('.$id.');">'."\n");
    $dialog->end_row();
}

function add_inventory()
{
    $db = new DB;
    $sequence = get_form_field('sequence');
    $sequence += 1;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('inventory.js');
    add_inventory_variables($dialog);
    $dialog->set_body_id('add_inventory');
    $dialog->set_help('add_inventory');
    $dialog->start_body('Add Inventory Record');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Inventory','images/AddInventory.png',
                        'process_add_inventory();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('inventory.php','AddInventory');
    $dialog->start_field_table();
    $row = array('sequence' => $sequence);
    display_inventory_fields($dialog,ADDRECORD,$db,0,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_inventory()
{
    $db = new DB;
    $inventory_record = inventory_record_definition();
    parse_inventory_fields($db,$inventory_record);
    if (! $db->insert('product_inventory',$inventory_record)) {
       http_response(422,$db->error);   return;
    }
    $inventory_record['id']['value'] = $db->insert_id();
    $parent = $inventory_record['parent']['value'];
    require_once '../engine/modules.php';
    if (module_attached('add_inventory')) {
       $product_info = load_product_info($db,$parent);
       set_product_category_info($db,$product_info);
       $inventory_info = $db->convert_record_to_array($inventory_record);
       update_inventory_records($db,$product_info,$inventory_info);
       if (! call_module_event('add_inventory',
                               array($db,$product_info,$inventory_info),
                               null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Inventory Record Added');
    log_activity('Added Inventory Record #'.$inventory_record['id']['value'] .
                 ' to Product #'.$parent);
}

function edit_inventory()
{
    global $enable_linked_inventory;

    $db = new DB;
    $id = get_form_field('id');
    if ($id == -1) {
       $parent = get_form_field('Parent');
       $attributes = get_form_field('Attributes');
       $query = 'select * from product_inventory where parent=? and ' .
                'attributes=?';
       $query = $db->prepare_query($query,$parent,$attributes);
       $row = $db->get_record($query);
       if ($row) $id = $row['id'];
       else {
          $row = array();
          $row['id'] = $id;
          $row['sequence'] = get_form_field('Sequence');
          $row['attributes'] = $attributes;
       }
    }
    else {
       $query = 'select * from product_inventory where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error))
             process_error('Database Error: '.$db->error,0);
          else process_error('Inventory Record Not Found',0);
          return;
       }
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('inventory.js');
    $dialog->add_style_sheet('products.css');
    add_inventory_variables($dialog);
    if ($id == -1) $dialog_title = 'Edit Inventory Record (New)';
    else $dialog_title = 'Edit Inventory Record (#'.$id.')';
    $dialog->set_body_id('edit_inventory');
    $dialog->set_help('edit_inventory');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_inventory();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('inventory.php','EditInventory');
    $dialog->start_field_table('edit_inventory_table');
    display_inventory_fields($dialog,UPDATERECORD,$db,$id,$row);
    if (! empty($enable_linked_inventory))
       display_inventory_links($dialog,$db,$id);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_inventory()
{
    $db = new DB;
    $inventory_record = inventory_record_definition();
    parse_inventory_fields($db,$inventory_record);
    if ($inventory_record['id']['value'] == -1) {
       unset($inventory_record['id']['value']);
       if (! $db->insert('product_inventory',$inventory_record)) {
          http_response(422,$db->error);   return;
       }
       $inventory_record['id']['value'] = $db->insert_id();
    }
    else if (! $db->update('product_inventory',$inventory_record)) {
       http_response(422,$db->error);   return;
    }
    $product_id = $inventory_record['parent']['value'];
    $attributes = $inventory_record['attributes']['value'];
    if (using_linked_inventory($db))
       update_linked_inventory($db,$inventory_record['id']['value'],
                               $inventory_record['qty']['value'],
                               $product_id,$attributes);

    require_once '../engine/modules.php';
    if (module_attached('update_inventory')) {
       $product_info = load_product_info($db,$product_id);
       set_product_category_info($db,$product_info);
       $inventory_info = $db->convert_record_to_array($inventory_record);
       update_inventory_records($db,$product_info,$inventory_info);
       if (! call_module_event('update_inventory',
                               array($db,$product_info,$inventory_info),
                               null,true)) {
          http_response(422,get_module_errors());   return;
       }
    }

    $activity = 'Updated Inventory Record #'.$inventory_record['id']['value'] .
                ' for Product #'.$product_id;
    if ($attributes) $activity .= ' with Attributes '.$attributes;
    log_activity($activity);
    $activity = 'Updated Inventory Record #'.$inventory_record['id']['value'];
    if ($attributes) $activity .= ' with Attributes '.$attributes;
    write_product_activity($activity.' by '.get_product_activity_user($db),
                           $product_id,$db);
    http_response(201,'Inventory Record Updated');
}

function load_inventory()
{
    $parent = get_form_field('parent');
    $db = new DB;
    $features = get_cart_config_value('features',$db);

    if (! load_inventory_data($db,$parent,$attributes,$product_attributes,
             $options,$dynamic_list,$current_attributes,$num_attributes,true))
       return;

    $query = 'select * from product_inventory where parent=' .
             $parent.' order by sequence';
    $inventory = $db->get_records($query,'id');
    if (! $inventory) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       $inventory = array();
    }
    $num_inventory = count($inventory);

    if ($dynamic_list) {
       if ($num_inventory == 0) $num_rows = 1;
       else $num_rows = $num_inventory;
    }
    else $num_rows = 1;
    $no_options = false;
    foreach ($product_attributes as $id) {
       if ($attributes[$id]) {
          print 'attributes['.$id."] = '";
          write_field_data($attributes[$id]['name']);
          print "'; ";
       }
       print 'options['.$id.'] = []; ';
       $num_options = 0;
       foreach ($options as $option_info) {
          if ($option_info['parent'] == $id) {
             print 'options['.$id.']['.$num_options.'] = [' .
                   $option_info['id'].",'";
             $option_name = $option_info['name'];
             if ($option_info['adjust_type'] == 2)
                $option_name = 'Start - '.$option_name;
             else if ($option_info['adjust_type'] == 3)
                $option_name = 'End - '.$option_name;
             write_field_data($option_name);
             print "']; ";
             $num_options++;
          }
       }
       if ($num_options == 0) $no_options = true;
       if (! $dynamic_list) $num_rows *= $num_options;
    }

    print 'num_rows = '.$num_rows.'; ';
    print "current_attributes = '".$current_attributes."'; ";
    $options_array = array();
    print 'set_inventory_columns('.$num_attributes.',';
    if ($dynamic_list) print 'true';
    else print 'false';
    print '); ';
    if ($dynamic_list) {
       print 'enable_dynamic_columns(); ';
       if ($num_inventory == 0)
          write_inventory_row(null,0,$num_attributes,$features,$no_options);
       else {
          $inventory_row = 0;
          foreach ($inventory as $inventory_info)
             write_inventory_row($inventory_info,$inventory_row++,
                                 $num_attributes,$features,$no_options);
       }
    }
    else if ($num_attributes > 0) {
       $row_num = 0;
       process_inv_attributes($options_array,$row_num,1,$num_attributes,
                              $product_attributes,$options,$inventory,
                              $features);
    }
    else {
       $curr_info = null;
       foreach ($inventory as $inventory_info) {
          if ($inventory_info['attributes'] == '') {
             $curr_info = $inventory_info;   break;
          }
       }
       print "inventory_grid.table._data[0]='";
       if ($curr_info) print $curr_info['id'];
       else print '-1';
       print '|';
       if ($curr_info) print $curr_info['sequence'];
       else print '0';
       print '||';
       write_inventory_data($curr_info,$features);
    }
}

function get_inventory_table_info()
{
    $parent = get_form_field('parent');
    $db = new DB;

    $query = 'select p.related_id from product_attributes p left join ' .
             'attributes a on a.id=p.related_id where p.parent=? ' .
             'and ((a.sub_product=1) or (a.dynamic=1)) order by sequence';
    $query = $db->prepare_query($query,$parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);
          return;
       }
    }

    $inventory_record = inventory_record_definition();

    print 'this.num_records = 0; ';
    print 'this._field_names = [';
    $first_field = true;
    foreach ($inventory_record as $field_name => $field_def) {
       if ($field_name == 'parent') continue;
       if ($first_field) $first_field = false;
       else print ',';
       print "'".$field_name."'";
       if ($field_name == 'attributes') {
          $index = 0;
          if ($rows) foreach ($rows as $row) {
             if ($first_field) $first_field = false;
             else print ',';
             print "'attr_".$index."'";
             $index++;
          }
       }
    }
    print '];';
}

function update_inventory_record()
{
    $parent = get_form_field('parent');
    $db = new DB;

    $inventory_record = inventory_record_definition();
    $db->parse_form_fields($inventory_record);
    $num_attributes = get_form_field('numattributes');
    $no_options = get_form_field('nooptions');
    $attributes = '';
    for ($loop = 0;  $loop < $num_attributes;  $loop++) {
       if ($loop > 0) {
          if ($no_options) $attributes .= '|';
          else $attributes .= '-';
       }
       $attributes .= get_form_field('attr_'.$loop);
    }
    $inventory_record['attributes']['value'] = $attributes;
    if ($inventory_record['id']['value'] == -1) {
       $new_record = true;
       unset($inventory_record['id']['value']);
    }
    else $new_record = false;
    if ($new_record) {
       if (! $db->insert('product_inventory',$inventory_record)) {
          http_response(422,$db->error);   return;
       }
       $inventory_record['id']['value'] = $db->insert_id();
       require_once '../engine/modules.php';
       if (module_attached('add_inventory')) {
          $product_info = load_product_info($db,$parent);
          set_product_category_info($db,$product_info);
          $inventory_info = $db->convert_record_to_array($inventory_record);
          update_inventory_records($db,$product_info,$inventory_info);
          if (! call_module_event('add_inventory',
                                  array($db,$product_info,$inventory_info),
                                  null,true)) {
             http_response(422,get_module_errors());   return;
          }
       }
       http_response(200,'Product Inventory Added');
       if (! empty($inventory_record['attributes']['value']))
          $attributes = $inventory_record['attributes']['value'];
       else $attributes = null;
       if ($attributes)
          log_activity('Added Product Inventory for Product ID #'.$parent .
                       ' Attributes '.$attributes);
       else log_activity('Added Product Inventory for Product ID #'.$parent);
       $activity = 'Added Product Inventory';
       if ($attributes) $activity .= ' with Attributes '.$attributes;
       write_product_activity($activity.' by '.get_product_activity_user($db),
                              $parent,$db);
    }
    else {
       if (! $db->update('product_inventory',$inventory_record)) {
          http_response(422,$db->error);   return;
       }
       if (! empty($inventory_record['attributes']['value']))
          $attributes = $inventory_record['attributes']['value'];
       else $attributes = null;
       if (! empty($inventory_record['qty']['value']))
          $qty = $inventory_record['qty']['value'];
       else $qty = 0;
       if (using_linked_inventory($db))
          update_linked_inventory($db,$inventory_record['id']['value'],$qty,
                                  $parent,$attributes);
       require_once '../engine/modules.php';
       if (module_attached('update_inventory')) {
          $product_info = load_product_info($db,$parent);
          set_product_category_info($db,$product_info);
          $inventory_info = $db->convert_record_to_array($inventory_record);
          update_inventory_records($db,$product_info,$inventory_info);
          if (! call_module_event('update_inventory',
                                  array($db,$product_info,$inventory_info),
                                  null,true)) {
             http_response(422,get_module_errors());   return;
          }
       }
       http_response(200,'Product Inventory Updated');
       if ($attributes)
          log_activity('Updated Product Inventory for Product ID #'.$parent.' Attributes '.
                       $attributes);
       else log_activity('Updated Product Inventory for Product ID #'.$parent);
       $activity = 'Updated Product Inventory';
       if ($attributes) $activity .= ' with Attributes '.$attributes;
       write_product_activity($activity.' by '.get_product_activity_user($db),
                              $parent,$db);
    }
}

function delete_inventory_record()
{
    $id = get_form_field('id');
    if (! $id) return;
    $db = new DB;
    $inventory_record = inventory_record_definition();
    $inventory_record['id']['value'] = $id;
    if (! $db->delete('product_inventory',$inventory_record)) {
       http_response(422,$db->error);   return;
    }
    if (using_linked_inventory($db)) delete_linked_inventory($db,$id);
    log_activity('Deleted Inventory Record #'.$id);
    http_response(200,'Product Inventory Deleted');
}

function update_inventory_data()
{
    $command = get_form_field('Command');
    if ($command == 'GetTableInfo') get_inventory_table_info();
    else if ($command == 'UpdateRecord') update_inventory_record();
    else if ($command == 'DeleteRecord') delete_inventory_record();
    else log_activity('update_inventory_data, Unknown Command = '.$command);
}

function copy_inventory_records($old_parent,$new_parent,$db=null)
{
    global $product_label;

    if (! $db) $db = new DB;
    $query = 'select * from product_inventory where parent=?';
    $query = $db->prepare_query($query,$old_parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return false;
       }
       return true;
    }
    $inventory_record = inventory_record_definition();
    if ($rows) foreach ($rows as $row) {
       foreach ($inventory_record as $field_name => $field_info)
          $inventory_record[$field_name]['value'] = $row[$field_name];
       unset($inventory_record['id']['value']);
       $inventory_record['parent']['value'] = $new_parent;
       if (! $db->insert('product_inventory',$inventory_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    log_activity('Copied Inventory for '.$product_label.' #'.$old_parent .
                 ' to '.$product_label.' #'.$new_parent);
    write_product_activity('Copied Inventory from '.$product_label. ' #' .
       $old_parent.' by '.get_product_activity_user($db),$new_parent,$db);

    return true;
}

function delete_inventory_records($parent,&$error,$db)
{
    require_once '../engine/modules.php';
    if (module_attached('delete_inventory') || using_linked_inventory($db)) {
       $product_info = load_product_info($db,$parent);
       $query = 'select * from product_inventory where parent=?';
       $query = $db->prepare_query($query,$parent);
       $rows = $db->get_records($query);
       if (! $rows) {
          if (isset($db->error)) {
             $error = $db->error;   return false;
          }
          return true;
       }
    }
    $query = 'delete from product_inventory where parent=?';
    $query = $db->prepare_query($query,$parent);
    $db->log_query($query);
    if (! $db->query($query)) {
       $error = $db->error;   return false;
    }
    if (using_linked_inventory($db)) {
       foreach ($rows as $inventory_info)
          delete_linked_inventory($db,$inventory_info['id']);
    }
    if (module_attached('delete_inventory')) {
       foreach ($rows as $inventory_info) {
          if (! call_module_event('delete_inventory',
                                  array($db,$product_info,$inventory_info),
                                  null,true)) {
             $error = get_module_errors();   return false;
          }
       }
    }
    log_activity('Deleted Product Inventory for Product ID #'.$parent);
    write_product_activity('Deleted Product Inventory by ' .
                           get_product_activity_user($db),$parent,$db);
    return true;
}

function delete_inventory()
{
    $parent = get_form_field('parent');
    $db = new DB;
    if (! delete_inventory_records($parent,$error,$db)) {
       http_response(422,$error);   return;
    }
    http_response(200,'Inventory Deleted');
}

function build_inventory_option_ids($prod_attrs,$attributes,&$option_ids,
                                    $attribute,$level)
{
    $attr_id = $prod_attrs[$level - 1];   $num_attrs = count($prod_attrs);
    if (! isset($attributes[$attr_id]['options'])) return;
    if ($attribute) $attribute .= '-';
    foreach ($attributes[$attr_id]['options'] as $option_id) {
       $new_attribute = $attribute.$option_id;
       if ($level == $num_attrs) $option_ids[] = $new_attribute;
       else build_inventory_option_ids($prod_attrs,$attributes,$option_ids,
                                       $new_attribute,($level + 1));
    }
}

function cleanup_inventory($db,$attr_options,$remove_orphans,$create_missing)
{
    global $features,$enable_linked_inventory,$enable_inventory_available;

    if ($remove_orphans) {
       $num_orphans = 0;
       $query = 'select count(id) as num_records from product_inventory ' .
                'where isnull(parent)';
       $row = $db->get_record($query);
       if (! $row) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
       $num_records = $row['num_records'];
       if ($num_records) {
          $query = 'delete from product_inventory where isnull(parent)';
          $db->log_query($query);
          if (! $db->query($query)) {
             process_error('Database Error: '.$db->error,-1);   return false;
          }
          $num_orphans += $num_records;
       }
       $query = 'select count(id) as num_records from product_inventory ' .
                'where parent not in (select id from products)';
       $row = $db->get_record($query);
       if (! $row) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
       $num_records = $row['num_records'];
       if ($num_records) {
          $query = 'delete from product_inventory where parent not in ' .
                   '(select id from products)';
          $db->log_query($query);
          if (! $db->query($query)) {
             process_error('Database Error: '.$db->error,-1);   return false;
          }
          $num_orphans += $num_records;
       }
    }

    $query = 'select id,sub_product,dynamic from attributes';
    $attributes = $db->get_records($query,'id');
    if (! $attributes) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
    }
    else foreach ($attr_options as $option) {
       $attr_id = $option['parent'];
       if (! isset($attributes[$attr_id]['options']))
          $attributes[$attr_id]['options'] = array();
       $attributes[$attr_id]['options'][] = $option['id'];
    }
    $query = 'select * from product_attributes order by parent,related_id';
    $prod_attrs = $db->get_records($query);
    if (! $prod_attrs) {
       if (isset($db->error)) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
    }
    $query = 'select id,parent,attributes,sequence from product_inventory ' .
             'order by parent';
    $inventory = $db->get_records($query);
    if (! $inventory) {
       process_error('Database Error: '.$db->error,-1);   return false;
    }
    if ($create_missing) {
       $query = 'select id from products order by id';
       $product_ids = $db->get_records($query,'id','id');
       if (! $product_ids) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
    }

    $attr_products = array();
    if ($prod_attrs) foreach ($prod_attrs as $prod_attr) {
       $product_id = $prod_attr['parent'];
       $attr_id = $prod_attr['related_id'];
       if (! isset($attributes[$attr_id])) continue;
       $attribute = $attributes[$attr_id];
       if (! $attribute['sub_product']) continue;
       if (! isset($attr_products[$product_id]))
          $attr_products[$product_id] = array('attributes' => array(),
                                              'inventory' => array(),
                                              'sequence' => 0);
       if ($attribute['dynamic'])
          $attr_products[$product_id]['dynamic'] = true;
       $attr_products[$product_id]['attributes'][] = $attr_id;
    }

    if ($prod_attrs) foreach ($inventory as $inv_record) {
       $product_id = $inv_record['parent'];
       if (! isset($attr_products[$product_id])) continue;
       $inv_id = $inv_record['id'];
       $attr_products[$product_id]['inventory'][$inv_id] = $inv_record;
       if ($inv_record['sequence'] > $attr_products[$product_id]['sequence'])
          $attr_products[$product_id]['sequence'] = $inv_record['sequence'];
    }

    $inventory_record = inventory_record_definition();
    if ($features & USE_PART_NUMBERS)
       $inventory_record['part_number']['value'] = '';
    if ($features & MAINTAIN_INVENTORY) {
       $inventory_record['qty']['value'] = 0;
       if (! empty($enable_inventory_available))
          $inventory_record['available']['value'] = 1;
    }
    else $inventory_record['available']['value'] = 1;
    if ($features & INVENTORY_BACKORDERS)
       $inventory_record['backorder']['value'] = 1;
    $num_created = 0;

    foreach ($attr_products as $product_id => $product) {
       if (isset($product['dynamic'])) continue;
       $inventory_record['parent']['value'] = $product_id;
       $option_ids = array();   $num_inv = 0;
       build_inventory_option_ids($product['attributes'],$attributes,
                                  $option_ids,'',1);
       foreach ($option_ids as $attribute) {
          $attribute_found = false;
          foreach ($product['inventory'] as $inv_id => $inv_record) {
             if ($attribute == $inv_record['attributes']) {
                unset($product['inventory'][$inv_id]);
                $attribute_found = true;   $num_inv++;   break;
             }
          }
          if ($create_missing && (! $attribute_found)) {
             $inventory_record['attributes']['value'] = $attribute;
             $inventory_record['sequence']['value'] = ++$product['sequence'];
             if (! $db->insert('product_inventory',$inventory_record)) {
                process_error('Database Error: '.$db->error,-1);   return false;
             }
             $num_created++;   $num_inv++;
          }
       }
       if ($remove_orphans && (! empty($option_ids))) {
          foreach ($product['inventory'] as $inv_id => $inv_record) {
             $query = 'delete from product_inventory where id=?';
             $query = $db->prepare_query($query,$inv_id);
             $db->log_query($query);
             if (! $db->query($query)) {
                process_error('Database Error: '.$db->error,-1);   return false;
             }
             $num_orphans++;
          }           
       }
       if ($create_missing && ($num_inv == 0)) {
          $inventory_record['attributes']['value'] = '';
          $inventory_record['sequence']['value'] = 1;
          if (! $db->insert('product_inventory',$inventory_record)) {
             process_error('Database Error: '.$db->error,-1);   return false;
          }
          $num_created++;
       }
    }
    if ($remove_orphans && (! empty($enable_linked_inventory))) {
       $query = 'select count(id) as num_records from inventory_link ' .
          'where primary_id not in (select id from product_inventory)';
       if (! $row) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
       $num_records = $row['num_records'];
       if ($num_records) {
          $query = 'delete from inventory_link where primary_id not in ' .
                   '(select id from product_inventory)';
          $db->log_query($query);
          if (! $db->query($query)) {
             process_error('Database Error: '.$db->error,-1);   return false;
          }
       }
       $query = 'select count(id) as num_records from inventory_link ' .
          'where linked_id not in (select id from product_inventory)';
       if (! $row) {
          process_error('Database Error: '.$db->error,-1);   return false;
       }
       $num_records = $row['num_records'];
       if ($num_records) {
          $query = 'delete from inventory_link where linked_id not in ' .
                   '(select id from product_inventory)';
          $db->log_query($query);
          if (! $db->query($query)) {
             process_error('Database Error: '.$db->error,-1);   return false;
          }
       }
    }
    if ($create_missing) {
       foreach ($inventory as $inv_record) {
          $product_id = $inv_record['parent'];
          if (isset($product_ids[$product_id]))
             unset($product_ids[$product_id]);
       }
       $inventory_record['attributes']['value'] = '';
       $inventory_record['sequence']['value'] = 1;
       foreach ($product_ids as $product_id) {
          $inventory_record['parent']['value'] = $product_id;
          if (! $db->insert('product_inventory',$inventory_record)) {
             process_error('Database Error: '.$db->error,-1);
             return false;
          }
          $num_created++;
       }
    }

    if ($remove_orphans && $num_orphans)
       log_activity('Removed '.$num_orphans.' Orphaned Inventory Records');
    if ($create_missing && $num_created)
       log_activity('Created '.$num_created.' Missing Inventory Records');

    return true;
}

function build_inventory_query($db,&$header)
{
    global $part_number_prompt,$features,$enable_inventory_available;
    global $enable_multisite,$website_cookie;

    $header_fields = get_inventory_header_fields();
    $query = 'select p.name,"" as attribute_names';
    $header = array('Product Name','Attributes');
    if ($features & USE_PART_NUMBERS) {
       $query .= ',i.part_number';
       $header[] = $header_fields['part_number'];
    }
    if ($features & MAINTAIN_INVENTORY) {
       $query .= ',i.qty';
       $header[] = $header_fields['qty'];
    }
    $inv_field_defs = $db->get_field_defs('product_inventory');
    foreach ($inv_field_defs as $field_name => $field) {
       switch ($field_name) {
          case 'min_qty':
             if ($features & MAINTAIN_INVENTORY) break;
             continue 2;
          case 'min_order_qty':
             if ($features & (MIN_ORDER_QTY|MIN_ORDER_QTY_BOTH)) break;
             continue 2;
          case 'weight':
             if ($features & WEIGHT_ITEM) break;
             continue 2;
          case 'list_price':
             if ($features & LIST_PRICE_INVENTORY) break;
             continue 2;
          case 'price':
             if ($features & REGULAR_PRICE_INVENTORY) break;
             continue 2;
          case 'sale_price':
             if ($features & SALE_PRICE_INVENTORY) break;
             continue 2;
          case 'cost':
             if ($features & PRODUCT_COST_INVENTORY) break;
             continue 2;
          case 'origin_zip':
             if ($features & DROP_SHIPPING) break;
             continue 2;
          case 'available':
             if (($features & MAINTAIN_INVENTORY) &&
                 empty($enable_inventory_available)) continue 2;
             break;
          case 'backorder':
             if (! ($features & INVENTORY_BACKORDERS)) continue 2;
             break;
          case 'part_number':
          case 'qty':
          case 'last_modified':
             continue 2;
       }
       $header[] = $header_fields[$field_name];
       $query .= ',i.'.$field_name;
    }
    $query .= ' from product_inventory i left join products p on ' .
              'i.parent=p.id';
    if ((! empty($enable_multisite)) && isset($_COOKIE[$website_cookie])) {
       $website_id = $_COOKIE[$website_cookie];
       if ($website_id != 0)
          $query .= ' where find_in_set("'.$website_id.'",p.websites)';
    }
    $query .= ' order by p.id,i.id';
    return $query;
}

function get_inv_column($header,$name)
{
    $column = array_search($name,$header);
    if ($column === false) return null;
    return chr($column + 65);
}

function set_column_size($worksheet,$header,$name,$size)
{
    $column = get_inv_column($header,$name);
    if (! $column) return;
    $worksheet->getColumnDimension($column)->setWidth($size);
}

function export_inventory()
{
    $dialog = new Dialog;
    $dialog->add_script_file('inventory.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->start_body('Export Inventory');
    $dialog->start_button_column();
    $dialog->add_button('Export','images/Export.png',
                        'process_export_inventory();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('products.php','ExportInventory');
    $dialog->start_field_table();
    $dialog->add_hidden_field('cmd','processexportinventory');
    $dialog->start_row('File Format:');
    $dialog->start_choicelist('Format');
    $dialog->add_list_item('','',true);
    $dialog->add_list_item('xlsx','Excel Workbook (*.xlsx)',false);
    $dialog->add_list_item('xls','Excel 97-2003 Workbook (*.xls)',false);
    $dialog->add_list_item('csv','CSV (Comma delimited) (*.csv)',false);
    $dialog->add_list_item('txt','Text (Tab delimited) (*.txt)',false);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->start_row('Options:','top');
    $dialog->add_checkbox_field('remove_orphans',
                                'Remove Orphaned Inventory Records',false);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('create_missing',
                                'Create Missing Inventory Records',false);
    $dialog->end_row();
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_export_inventory()
{
    global $attribute_column;

    set_time_limit(0);
    ini_set('memory_limit','2048M');
    $db = new DB;
    $query = 'select id,name,display_name from attributes';
    $attributes = $db->get_records($query,'id');
    foreach ($attributes as $id => $attr_info) {
       if ($attr_info['display_name']) $name = $attr_info['display_name'];
       else $name = $attr_info['name'];
       if (substr($name,-1) == '*') $name = substr($name,0,-1);
       if (substr($name,-1) == ':') $name = substr($name,0,-1);
       if (substr($name,0,7) == 'Choose ') $name = substr($name,7);
       if (substr($name,0,7) == 'Select ') $name = substr($name,7);
       $attributes[$id] = trim($name);
    }
    $query = 'select id,parent,name from attribute_options';
    $attr_options = $db->get_records($query,'id');
    $remove_orphans = get_form_field('remove_orphans');
    $create_missing = get_form_field('create_missing');
    if ($remove_orphans || $create_missing) {
       if (! cleanup_inventory($db,$attr_options,$remove_orphans,
                               $create_missing)) return;
    }
    $query = build_inventory_query($db,$header);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,-1);
       else process_error('No Records Found to Export',-1);
       return;
    }
    $format = get_form_field('Format');
    $filename = 'inventory.'.$format;
    if ($format == 'xls') {
       $output_format = 'Excel5';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($format == 'xlsx') {
       $output_format = 'Excel2007';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($format == 'csv') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }
    else if ($format == 'txt') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }

    foreach ($rows as $index => $row) {
       $inv_attrs = $row['attributes'];
       if ($inv_attrs) {
          if (strpos($inv_attrs,'|') !== false)
             $inv_attrs = explode('|',$inv_attrs);
          else $inv_attrs = explode('-',$inv_attrs);
          $attribute_names = '';
          foreach ($inv_attrs as $attribute) {
             if ($attribute_names) $attribute_names .= ', ';
             if (! is_numeric($attribute)) $attribute_names .= $attribute;
             else {
                if (! isset($attr_options[$attribute]))
                   $attribute_names .= 'Option #'.$attribute;
                else {
                   $option = $attr_options[$attribute];
                   if (isset($attributes[$option['parent']]))
                      $attribute_names .= $attributes[$option['parent']].': ';
                   $attribute_names .= $option['name'];
                }
             }
          }
          $rows[$index]['attribute_names'] = $attribute_names;
       }
       if (isset($row['available'])) {
          if ($row['available'] == 1) $rows[$index]['available'] = 'Yes';
          else $rows[$index]['available'] = 'No';
       }
       if (isset($row['backorder'])) {
          if ($row['backorder'] == 1) $rows[$index]['backorder'] = 'Yes';
          else $rows[$index]['backorder'] = 'No';
       }
    }

    header('Content-Type: '.$mime_type);
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-cache');

    require_once '../engine/PHPExcel/IOFactory.php';
    class PHPExcel_Cell_InvValueBinder extends PHPExcel_Cell_DefaultValueBinder
       implements PHPExcel_Cell_IValueBinder {
       public function bindValue(PHPExcel_Cell $cell,$value = null) {
          global $attribute_column;
          if ($cell->getColumn() == $attribute_column) {
             $cell->setValueExplicit($value,
                PHPExcel_Cell_DataType::TYPE_STRING);
             return true;
          }
          if (is_numeric($value)) {
             if (($value[0] == '0') && (strlen($value) > 1))
                $cell->setValueExplicit($value,
                   PHPExcel_Cell_DataType::TYPE_STRING);
             else $cell->setValueExplicit($value,
                     PHPExcel_Cell_DataType::TYPE_NUMERIC);
             return true;
          }
          return parent::bindValue($cell,$value);
       }
    }
    $excel = new PHPExcel();
    $excel->setActiveSheetIndex(0);
    $worksheet = $excel->getActiveSheet();
    PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_InvValueBinder());
    $attribute_column = get_inv_column($header,'Attr IDs');
    set_column_size($worksheet,$header,'Inv ID',7);
    set_column_size($worksheet,$header,'Seq',5);
    set_column_size($worksheet,$header,'Product ID',10);
    set_column_size($worksheet,$header,'Product Name',35);
    set_column_size($worksheet,$header,'Attr IDs',12);
    set_column_size($worksheet,$header,'Attributes',60);
    set_column_size($worksheet,$header,'Qty',5);
    $worksheet->fromArray($header,NULL,'A1');
    $worksheet->fromArray($rows,NULL,'A2');
    $num_rows = count($rows);
    $column = get_inv_column($header,'Part #');
    if ($column)
       $worksheet->getStyle($column.'2:'.$column.($num_rows + 1))->
          getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::
                                           FORMAT_TEXT);
    $column = get_inv_column($header,'Attr IDs');
    if ($column)
       $worksheet->getStyle($column.'2:'.$column.($num_rows + 1))->
          getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::
                                           FORMAT_TEXT);
    $column = get_inv_column($header,'Available');
    if ($column)
       $worksheet->getStyle($column.'1:'.$column.($num_rows + 1))->
          getAlignment()->setHorizontal(PHPExcel_Style_Alignment::
                                        HORIZONTAL_CENTER);
    $column = get_inv_column($header,'Backorderable');
    if ($column)
       $worksheet->getStyle($column.'1:'.$column.($num_rows + 1))->
          getAlignment()->setHorizontal(PHPExcel_Style_Alignment::
                                        HORIZONTAL_CENTER);
    $writer = PHPExcel_IOFactory::createWriter($excel,$output_format);
    if ($format == 'txt') $writer->setDelimiter("\t");
    $writer->save('php://output');

    log_activity('Exported Product Inventory');
}

function import_inventory()
{
    global $script_name;

    $dialog = new Dialog;
    $dialog->add_script_file('inventory.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('import_inventory');
    $dialog->set_help('import_inventory');
    $dialog->start_body('Import Inventory');
    $dialog->start_button_column();
    $dialog->add_button('Import','images/Import.png',
                        'process_import_inventory();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_field_table();
    $dialog->write("<form method=\"POST\" action=\"".$script_name."\" " .
                   "name=\"ImportInventory\" ");
    $dialog->write("encType=\"multipart/form-data\">\n");
    $dialog->add_hidden_field('cmd','processimportinventory');
    $dialog->start_row('Import File:','middle');
    $dialog->write("<input type=\"file\" name=\"Filename\" size=\"35\" " .
                   "class=\"browse_button\">\n");
    $dialog->end_row();
    $dialog->write("<tr><td colspan=\"2\"><i><b>Note: All inventory " .
                   "records included in the import file will be updated " .
                   "with the imported data.  Inventory records not included " .
                   "in the import will not be updated.</b></i></td></tr>\n");
    $dialog->end_form();
    $dialog->end_field_table();
    $dialog->end_body();
}

function process_import_inventory()
{
    $header_fields = get_inventory_header_fields();
    set_time_limit(0);
    ini_set('memory_limit','2048M');
    ignore_user_abort(true);
    $filename = $_FILES['Filename']['name'];
    $temp_name = $_FILES['Filename']['tmp_name'];
    $file_type = $_FILES['Filename']['type'];
    $temp_filename = tempnam('import','import');
    if ($temp_name) {
       if (! move_uploaded_file($temp_name,$temp_filename)) {
          log_error('Attempted to move '.$temp_name.' to '.$temp_filename);
          process_error('Unable to save uploaded file',-1);   return;
       }
    }
    else {
       process_error('You must select an Import File',-1);   return;
    }

    $extension = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if ($extension == 'xls') $format = 'Excel5';
    else if ($extension == 'xlsx') $format = 'Excel2007';
    else if ($extension == 'csv') $format = 'CSV';
    else {
       unlink($temp_filename);
       process_error('Only XLS, XLSX, or CSV files can be imported',-1);
       return;
    }
    require_once '../engine/PHPExcel/IOFactory.php';
    $reader = PHPExcel_IOFactory::createReader($format);
    $import_file = $reader->load($temp_filename);
    $data = $import_file->getActiveSheet()->toArray(null,false,false,false);
    if (! $data) {
       unlink($temp_filename);
       process_error('Unable to load data from '.$filename,-1);   return;
    }
    $column_names = $data[0];   $field_map = array();
    foreach ($column_names as $index => $column_name) {
       $field_name = array_search($column_name,$header_fields);
       if ($field_name === false) continue;
       if ($field_name == 'parent') continue;
       $field_map[$field_name] = $index;
    }
    if (! isset($field_map['id'])) {
       unlink($temp_filename);
       process_error('Inv ID Column is Missing from Import',-1);   return;
    }
    $db = new DB;
    $db->enable_log_query(false);
    $inventory_record = inventory_record_definition();

    foreach ($data as $row_index => $row) {
       if ($row_index == 0) continue;
       foreach ($field_map as $field_name => $index) {
          $field_value = trim($row[$index]);
          if (($field_name == 'available') || ($field_name == 'backorder')) {
             $field_value = strtolower($field_value);
             if ($field_value[0] == 'y') $field_value = 1;
             else $field_value = 0;
          }
          $inventory_record[$field_name]['value'] = $field_value;
       }
       if (! $db->update('product_inventory',$inventory_record)) {
          unlink($temp_filename);   process_error($db->error,-1);   return;
       }
       if (using_linked_inventory($db))
          update_linked_inventory($db,$inventory_record['id']['value'],
                                  $inventory_record['qty']['value'],
                                  $inventory_record['parent']['value'],
                                  $inventory_record['attributes']['value']);
    }

    unlink($temp_filename);

    $dialog = new Dialog;
    $dialog->add_script_file('inventory.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_onload_function('finish_import_inventory();');
    $dialog->set_body_id('finish_import_inventory');
    $dialog->start_body('Import Inventory');
    $dialog->end_body();

    log_activity('Imported Product Inventory');
}

function manage_links()
{
    $id = get_form_field('id');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('inventory.js');
    $dialog->add_style_sheet('products.css');
    $dialog_title = 'Manage Links for Inventory Record #'.$id;
    $dialog->set_body_id('manage_links');
    $dialog->set_help('manage_links');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(120);
    $dialog->start_button_column();
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog(); return false;');
    $dialog->add_button_separator('manage_links_row',20,true);
    $dialog->add_button('Add Link','images/AddInventory.png',
                        'add_inv_link('.$id.'); return false;');
    $dialog->add_button('Remove Link','images/DeleteInventory.png',
                        'remove_inv_link(); return false;');
    $dialog->end_button_column();
    $dialog->write("          <script type=\"text/javascript\">\n");
    $dialog->write('             load_inv_links_grid('.$id.");\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function add_inv_link()
{
    $id = get_form_field('id');
    $db = new DB;
    $status_values = load_cart_options(PRODUCT_STATUS,$db);
    $query = 'select id from products order by name limit 1';
    $row = $db->get_record($query);
    if (! empty($row['id'])) $first_id = $row['id'];
    else $first_id = 0;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('inventory.js');
    $dialog->add_script_file('products.js');
    $dialog->add_style_sheet('products.css');
    $dialog->add_style_sheet('utility.css');
    $script = "<script type=\"text/javascript\">\n";
    $script .= 'var product_status_values = [';
    for ($loop = 0;  $loop < count($status_values);  $loop++) {
       if ($loop > 0) $script .= ',';
       if (isset($status_values[$loop]))
          $script .= "\"".$status_values[$loop]."\"";
       else $script .= "\"\"";
    }
    $script .= "];\n";
    $script .= 'var select_product_column_widths = ' .
               "[0,300,360,150,0,0,0,0,0,0,0,0,0,0];\n";
    $script .= 'var link_inventory_primary_id = '.$id.";\n";
    $script .= "    </script>";
    $dialog->add_head_line($script);
    $styles = "<style type=\"text/css\">\n";
    $styles .= "      #products_grid .aw-column-3 { text-align: center; }\n";
    $styles .= '    </style>';
    $dialog->add_head_line($styles);
    $dialog_title = 'Add Link to Inventory Record #'.$id;
    $dialog->set_body_id('add_inv_link');
    $dialog->set_help('add_inv_link');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button('Add','images/AddInventory.png',
                        'process_add_inv_link(); return false;');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_search_box($dialog,'search_products','reset_search');
    $dialog->end_button_column();
    $dialog->write("          <script type=\"text/javascript\">\n");
    $dialog->write("             load_grid(false,true);\n");
    $dialog->write('             create_link_inventory_grid('.$first_id.");\n");
    $dialog->write("          </script>\n");
    $dialog->end_body();
}

function process_add_inv_link()
{
    $db = new DB;
    $link_record = inventory_link_record_definition();
    $db->parse_form_fields($link_record);
    if (! $db->insert('inventory_link',$link_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Inventory Link Record Added');
    log_activity('Linked Inventory Record #' .
                 $link_record['primary_id']['value'] .
                 ' to #'.$link_record['linked_id']['value']);
}

function remove_inv_link()
{
    $link_id = get_form_field('link_id');
    $db = new DB;
    $link_record = inventory_link_record_definition();
    $link_record['id']['value'] = $link_id;
    if (! $db->delete('inventory_link',$link_record)) {
       http_response(422,$db->error);   return;
    }
    log_activity('Deleted Inventory Link Record #'.$link_id);
    http_response(201,'Inventory Link Deleted');
}

function process_inventory_command($cmd)
{
    if ($cmd == 'addinventory') add_inventory();
    else if ($cmd == 'processaddinventory') process_add_inventory();
    else if ($cmd == 'editinventory') edit_inventory();
    else if ($cmd == 'updateinventory') update_inventory();
    else if ($cmd == 'loadinventory') load_inventory();
    else if ($cmd == 'updateinventorydata') update_inventory_data();
    else if ($cmd == 'deleteinventory') delete_inventory();
    else if ($cmd == 'exportinventory') export_inventory();
    else if ($cmd == 'processexportinventory') process_export_inventory();
    else if ($cmd == 'importinventory') import_inventory();
    else if ($cmd == 'processimportinventory') process_import_inventory();
    else if ($cmd == 'managelinks') manage_links();
    else if ($cmd == 'addinvlink') add_inv_link();
    else if ($cmd == 'processaddinvlink') process_add_inv_link();
    else if ($cmd == 'removeinvlink') remove_inv_link();
    else return false;
    return true;
}

?>
