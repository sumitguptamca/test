<?php
/*
                     Inroads Shopping Cart - Callouts Functions

                        Written 2015-2016 by Randall Severy
                         Copyright 2015-2016 Inroads, LLC
*/

function add_callout_config_buttons($dialog)
{
    global $attr_image_buttons_row_height,$manage_image_buttons;
    global $use_dynamic_images;

    if ((! $use_dynamic_images) && (! $manage_image_buttons))
       $buttoms_row_height = 100;
    else {
       $buttons_row_height = 132;
       if ($manage_image_buttons) $buttons_row_height -= 33;
       if (! $use_dynamic_images) $buttons_row_height -= 33;
    }

    $dialog->add_button_separator('callout_image_buttons_row',
                                  $buttons_row_height);
    if ($manage_image_buttons)
       $dialog->add_button('Manage Images','images/AdminUsers.png',
                           'manage_callout_images();','manage_callout_images',
                           false);
    $dialog->add_button('Update Images','images/EditUser.png',
                        'update_callout_images();','update_callout_images',
                        false);
}

function display_callout_config($dialog,$config_values)
{
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt\" ");
    $dialog->write("style=\"text-align:center;\"><i><u>Callout Images" .
                   "</u></i></td></tr>\n");
    $dialog->add_edit_row('Fill Color:','callout_color',
                          get_row_value($config_values,'callout_color'),30);
    $callout_size = get_row_value($config_values,'callout_size');
    if ($callout_size == '') $size_values = array('','');
    else $size_values = explode('|',$callout_size);
    $dialog->start_row('Size:','middle');
    $dialog->write("Width:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                   "callout_width\" size=5 value=\"".$size_values[0] .
                   "\">&nbsp;&nbsp;&nbsp;\n");
    $dialog->write("Height:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                   "callout_height\" size=5 value=\"".$size_values[1] .
                   "\">\n");
    $dialog->end_row();
    $dialog->start_row('Crop Ratio:','middle');
    $dialog->add_input_field('callout_crop_ratio',
                             get_row_value($config_values,
                                           'callout_crop_ratio'),5);
    $dialog->write(" (Width:Height)\n");
    $dialog->end_row();
    $dialog->start_row('Use Image:','middle');
    $callout_image = get_row_value($config_values,'callout_image');
    if ($callout_image == '') $callout_image = 'large';
    $dialog->start_choicelist('callout_image');
    $dialog->add_list_item('small','Small',$callout_image == 'small');
    $dialog->add_list_item('medium','Medium',$callout_image == 'medium');
    $dialog->add_list_item('large','Large',$callout_image == 'large');
    $dialog->add_list_item('zoom','Zoom',$callout_image == 'zoom');
    $dialog->end_choicelist();
    $dialog->end_row();
}

function update_callout_config($db,$config_record)
{
    $config_record['config_name']['value'] = 'callout_color';
    $config_record['config_value']['value'] = get_form_field('callout_color');
    if (! $db->insert('config',$config_record)) {
       http_response(422,$db->error);   return false;
    }
    $config_record['config_name']['value'] = 'callout_size';
    $width = get_form_field('callout_width');
    $height = get_form_field('callout_height');
    $config_record['config_value']['value'] = $width.'|'.$height;
    if (! $db->insert('config',$config_record)) {
       http_response(422,$db->error);   return false;
    }
    $config_record['config_name']['value'] = 'callout_crop_ratio';
    $config_record['config_value']['value'] =
       get_form_field('callout_crop_ratio');
    if (! $db->insert('config',$config_record)) {
       http_response(422,$db->error);   return false;
    }
    $config_record['config_name']['value'] = 'callout_image';
    $config_record['config_value']['value'] =
       get_form_field('callout_image');
    if (! $db->insert('config',$config_record)) {
       http_response(422,$db->error);   return false;
    }
    return true;
}

function add_callout_buttons($dialog,$db,$enabled=true)
{
    global $shopping_cart;

    $query = 'select config_value from config where config_name="' .
             'image_size_small"';
    $row = $db->get_record($query);
    if ($row && $row['config_value']) {
       $size_values = explode('|',$row['config_value']);
       if (isset($size_values)) $sep_height = $size_values[1] + 38;
       else $sep_height = 138;
    }
    else $sep_height = 138;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog->add_button_separator('callout_buttons_row',$sep_height);
    $dialog->add_button('Add Callout',$prefix.'images/AddImage.png',
                        'add_callout();','add_callout',$enabled);
    $dialog->add_button('Edit Callout',$prefix.'images/EditImage.png',
                        'edit_callout();','edit_callout',$enabled);
    $dialog->add_button('Delete Callout',$prefix.'images/DeleteImage.png',
                        'delete_callout();','delete_callout',$enabled);
}

function add_callout_sequence_buttons($dialog,$hidden=true)
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $dialog->write("          <td id=\"callout_sequence_buttons\" width=\"50\" " .
                   "nowrap align=\"center\"");
    if ($hidden) $dialog->write(" style=\"display:none;\"");
    $dialog->write(">\n");
    add_image_sequence_button($prefix.'images/MoveTop.png','move_callout_top();',
                              'Top',true);
    add_image_sequence_button($prefix.'images/MoveUp.png','move_callout_up();',
                              'Up',true);
    add_image_sequence_button($prefix.'images/MoveDown.png','move_callout_down();',
                              'Down',true);
    add_image_sequence_button($prefix.'images/MoveBottom.png',
                              'move_callout_bottom();','Bottom',false);
    $dialog->write("          </td>\n");
}

function setup_callouts_grid($dialog,$product_id,$edit_type)
{
    global $shopping_cart,$script_name;

    $frame = get_form_field('frame');
    $dialog->write("        <table cellspacing=\"0\" cellpadding=\"0\" " .
                   "width=\"100%\">\n");
    $dialog->write("        <tr><td class=\"fieldprompt callout_header\">" .
                   "Callouts:</td></tr>\n");
    $dialog->write("        <tr valign=\"top\">\n");
    $dialog->write("          <td id=\"callouts_cell\"><script>init_callouts(\"");
    if ($shopping_cart) $dialog->write('../cartengine/');
    $dialog->write($script_name."\",");
    if ($edit_type == UPDATERECORD) {
       if ($frame) $dialog->write("\"".$frame."\",\"EditProduct\"");
       else if (get_form_field('insidecms'))
          $dialog->write("\"smartedit\",\"EditProduct\"");
       else $dialog->write("\"edit_product\",\"EditProduct\"");
    }
    else {
       if ($frame) $dialog->write("\"".$frame."\",\"AddProduct\"");
       else $dialog->write("\"add_product\",\"AddProduct\"");
    }
    if ($dialog->skin) $dialog->write(',-100');
    else $dialog->write(',600');
    $dialog->write(");\n");
    $dialog->write("                    create_callouts_grid(".$product_id .
                   ",'callouts_cell');</script></td>\n");
    add_callout_sequence_buttons($dialog,true);
    $dialog->write("        </tr></table>\n");
}

function callout_record_definition()
{
    $callout_record = array();
    $callout_record['id'] = array('type' => INT_TYPE);
    $callout_record['id']['key'] = true;
    $callout_record['parent'] = array('type' => INT_TYPE);
    $callout_record['sequence'] = array('type' => INT_TYPE);
    $callout_record['name'] = array('type' => CHAR_TYPE);
    $callout_record['xpos'] = array('type' => FLOAT_TYPE);
    $callout_record['ypos'] = array('type' => FLOAT_TYPE);
    $callout_record['image'] = array('type' => CHAR_TYPE);
    $callout_record['title'] = array('type' => CHAR_TYPE);
    $callout_record['description'] = array('type' => CHAR_TYPE);
    return $callout_record;
}

function add_callout_script_prefix(&$dialog)
{
    global $shopping_cart,$script_name;

    if (! isset($script_name)) $script_name = basename($_SERVER['PHP_SELF']);
    $head_block = "<script type=\"text/javascript\">\n";
    $head_block .= "      callouts_script_url='";
    if ($shopping_cart) $head_block .= '../cartengine/';
    $head_block .= $script_name."';\n";
    $head_block .= "    </script>";
    $dialog->add_head_line($head_block);
}

function display_callout_fields($db,$dialog,$edit_type,$row)
{
    $frame = get_form_field('Frame');
    $image = get_form_field('Image');
    $config_values = load_config_values($db);
    $callout_size = get_row_value($config_values,'callout_size');
    $callout_image = get_row_value($config_values,'callout_image');
    if ($callout_image == '') $callout_image = 'large';
    $image_sizes = get_row_value($config_values,'image_size_'.$callout_image);
    if ($image_sizes == '') $size_values = array('100','100');
    else $size_values = explode('|',$image_sizes);
    $dialog_width = intval($size_values[0]) + 117;
    $dialog_height = intval($size_values[1]) + 30;

    if ($edit_type == UPDATERECORD) {
       $parent = $row['parent'];
       $dialog->add_hidden_field('id',$row);
    }
    else {
       $parent = get_form_field('Parent');
       $dialog->add_hidden_field('parent',$parent);
    }
    if (! $image) {
       $query = 'select filename from images where callout_group=? limit 1';
       $query = $db->prepare_query($query,$parent);
       $image_row = $db->get_record($query);
       if ($image_row && $image_row['filename'])
          $image = $image_row['filename'];
    }
    $dialog->add_hidden_field('Frame',$frame);
    if ($image) $dialog->add_hidden_field('Image',$image);
    $dialog->add_hidden_field('Size',$callout_image);
    $dialog->add_hidden_field('ImageWidth',$size_values[0]);
    $dialog->add_hidden_field('ImageHeight',$size_values[1]);
    $dialog->add_hidden_field('DialogWidth',$dialog_width);
    $dialog->add_hidden_field('DialogHeight',$dialog_height);
    $dialog->start_field_table(null,'fieldtable callout_table');
    $dialog->add_edit_row('Title:','title',$row,59);
    if ($image)
       $pos_button = '<input type="button" class="pos_button" value="' .
                     'Set Position..." onClick="set_position();">';
    else $pos_button = null;
    $dialog->add_edit_row('X %:','xpos',$row,3,null,$pos_button);
    $dialog->add_edit_row('Y %:','ypos',$row,3);
    $image = get_row_value($row,'image');
    if ($edit_type == ADDRECORD) $frame = 'add_callout';
    else $frame = 'edit_callout';
    $dialog->add_browse_row('Image:','image',$image,59,$frame,
                            '/images/callouts/',true,false,true,false,false,
                            'callout');
    if ($callout_size) {
       $size_info = explode('|',$callout_size);
       $dialog->write("<tr><td></td><td class=\"callout_note_cell\">\n");
       $dialog->write("Note: For best results, uploaded images should be at " .
                      "least ".$size_info[0]." pixels wide and ".$size_info[1] .
                      " pixels high");
       $dialog->end_row();
    }
    $dialog->add_textarea_row('Description:','description',
       get_row_value($row,'description'),10,60,WRAP_SOFT);
    $dialog->write("<tr><td></td><td class=\"callout_note_cell\">\n");
    $dialog->write("Note: Use line breaks for paragraphs in description");
    $dialog->end_row();
    $dialog->end_field_table();
}

function add_callout()
{
    $db = new DB;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('callouts.js');
    add_callout_script_prefix($dialog);
    $dialog->set_body_id('add_callout');
    $dialog->set_help('add_callout');
    $dialog->start_body('Add Callout');
    $dialog->set_button_width(115);
    $dialog->start_button_column();
    $dialog->add_button('Add Callout','images/AddImage.png',
                        'process_add_callout();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('products.php','AddCallout');
    display_callout_fields($db,$dialog,ADDRECORD,array());
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_callout()
{
    $db = new DB;
    $callout_record = callout_record_definition();
    $db->parse_form_fields($callout_record);
    $parent = $callout_record['parent']['value'];
    $query = 'select sequence from callouts where parent=?' .
             ' order by sequence desc limit 1';
    $query = $db->prepare_query($query,$parent);
    $row = $db->get_record($query);
    if (isset($db->error)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }
    if ((! $row) || (! $row['sequence'])) $sequence = 1;
    else $sequence = $row['sequence'] + 1;
    $callout_record['sequence']['value'] = $sequence;
    if (! $db->insert('callouts',$callout_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    http_response(201,'Callout Added');
    log_activity('Added Callout '.$callout_record['title']['value'] .
                 ' to Image #'.$parent);
}

function edit_callout()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from callouts where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Callout not found',0);
       return;
    }
    $frame = get_form_field('Frame');
    $image = get_form_field('Image');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('callouts.js');
    $dialog_title = 'Edit Callout (#'.$id.')';
    add_callout_script_prefix($dialog);
    $dialog->set_body_id('edit_callout');
    $dialog->set_help('edit_callout');
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_callout();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('products.php','EditCallout');
    display_callout_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_form();
    $dialog->end_body();
}

function update_callout()
{
    $db = new DB;
    $callout_record = callout_record_definition();
    $db->parse_form_fields($callout_record);
    if (! $db->update('callouts',$callout_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Callout Updated');
    log_activity('Updated Callout '.$callout_record['title']['value'] .
                 ' (#'.$callout_record['id']['value'].')');
}

function delete_callout()
{
    $id = get_form_field('id');

    $db = new DB;
    $callout_record = callout_record_definition();
    $callout_record['id']['value'] = $id;
    if (! $db->delete('callouts',$callout_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Callout Deleted');
    log_activity('Deleted Callout #'.$id);
}

function position_callout()
{
    global $prefix;

    $frame = get_form_field('Frame');
    $image = get_form_field('Image');
    $xpos = floatval(get_form_field('xpos'));
    if ($xpos > 100) $xpos = 0;
    $ypos = floatval(get_form_field('ypos'));
    if ($ypos > 100) $ypos = 0;
    $size = get_form_field('Size');
    $image_width = intval(get_form_field('ImageWidth'));
    $image_height = intval(get_form_field('ImageHeight'));
    $xpos = round($image_width * ($xpos / 100),2) - 14;
    if ($xpos < 0) $xpos = 0;
    $ypos = round($image_height * ($ypos / 100),2) - 14;
    if ($ypos < 0) $ypos = 0;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('callouts.js');
    $dialog_title = 'Set Position';
    $dialog->set_body_id('position_callout');
    $dialog->set_help('position_callout');
    $dialog->set_onload_function("set_position_onload();");
    $dialog->start_body('Set Callout Position');
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_position();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('products.php','PositionCallout');
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('ImageWidth',$image_width);
    $dialog->add_hidden_field('ImageHeight',$image_height);
    $dialog->start_field_table(null,'fieldtable position_table',0);
    $dialog->write("<tr><td class=\"drag_container\">");
    $dialog->write("<img src=\"".$prefix."/images/".$size."/".$image."?v=" .
                   time()."\">\n");
    $dialog->write("<img src=\"images/target.png\" id=\"target\" " .
                   "class=\"dragme\" style=\"top:".$ypos."px; left:".$xpos .
                   "px;\">\n");
    $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function resequence_callouts()
{
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');

    $db = new DB;

    $query = 'select id,sequence from callouts where parent='.$parent .
             ' order by sequence,id';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       else http_response(410,'No Callouts Found');
       return;
    }
    foreach ($rows as $row) {
       $current_sequence = $row['sequence'];
       $updated_sequence = $current_sequence;
       if ($current_sequence == $old_sequence)
          $updated_sequence = $new_sequence;
       else if ($old_sequence > $new_sequence) {
          if (($current_sequence >= $new_sequence) &&
              ($current_sequence < $old_sequence))
             $updated_sequence = $current_sequence + 1;
       }
       else {
          if (($current_sequence > $old_sequence) &&
              ($current_sequence <= $new_sequence))
             $updated_sequence = $current_sequence - 1;
       }
       if ($updated_sequence != $current_sequence) {
          $query = 'update callouts set sequence='.$updated_sequence .
                   ' where id='.$row['id'];
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,'Database Error: '.$db->error);   return;
          }
       }
    }

    http_response(201,'Callouts Resequenced');
    log_activity('Resequenced Callout #'.$old_sequence.' to #'.$new_sequence .
                 ' for Image #'.$parent);
}

function callout_group_record_definition()
{
    $group_record = array();
    $group_record['id'] = array('type' => INT_TYPE);
    $group_record['id']['key'] = true;
    $group_record['name'] = array('type' => CHAR_TYPE);
    return $group_record;
}

function callout_groups()
{
    global $shopping_cart;

    $db = new DB;
    $query = 'select id,name from callout_groups order by name limit 1';
    $row = $db->get_record($query);
    if ($row) {
       $group_id = $row['id'];   $name = $row['name'];
    }
    else {
       $group_id = -1;   $name = '';
    }
    $group_label = 'Callouts for <span id="group_label">'.$name.'</span>:';

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_style_sheet('callouts.css');
    $screen->add_script_file('callouts.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $screen->set_body_id('callout_groups');
    $screen->set_help('callout_groups');
    if ($shopping_cart) {
       $head_block = "<script type=\"text/javascript\">\n";
       $head_block .= "      script_prefix='../cartengine/';\n";
       $head_block .= "    </script>";
       $screen->add_head_line($head_block);
       $prefix = '../cartengine/';
    }
    else $prefix = '';
    $screen->start_body();
    $screen->set_button_width(148);
    if ($screen->skin) {
       $screen->start_section();
       $screen->start_title_bar('Callout Groups');
       $screen->start_title_filters();
       add_search_box($screen,'search_groups','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->start_button_column();
    $screen->add_button('Add Group',$prefix.'images/AddOrder.png',
                        'add_group();');
    $screen->add_button('Edit Group',$prefix.'images/EditOrder.png',
                        'edit_group();');
    $screen->add_button('Delete Group',$prefix.'images/DeleteOrder.png',
                        'delete_group();');
    if ($screen->skin) {
       $screen->end_button_column();
       $screen->write("          <script>load_groups_grid();</script>\n");
       $screen->end_section();
       $screen->start_section();
       $screen->start_title_bar($group_label);
       $screen->end_title_bar();
       $screen->start_button_column();
    }
    else {
       add_search_box($screen,'search_groups','reset_search');
       $screen->add_button_separator('groups_sep_row',20);
       $screen->write("<td colspan=\"2\"></td></tr>\n");
    }
    $screen->add_button('Add Callout',$prefix.'images/AddImage.png',
                        'add_callout();');
    $screen->add_button('Edit Callout',$prefix.'images/EditImage.png',
                        'edit_callout();');
    $screen->add_button('Delete Callout',$prefix.'images/DeleteImage.png',
                        'delete_callout();');
    $screen->end_button_column();
    if (! $screen->skin) {
       $screen->write("          <span class=\"fieldprompt\"" .
                      " style=\"text-align: left; font-weight: bold;\">" .
                      "Callout Groups</span><br>\n");
       $screen->write("          <script>load_groups_grid();</script>\n");
       $screen->write("          <br><span class=\"fieldprompt\"" .
                      " style=\"text-align: left; font-weight: bold;\">" .
                      $group_label."</span><br>\n");
    }
    $screen->write("        <table cellspacing=\"0\" cellpadding=\"0\" " .
                   "width=\"100%\"><tr valign=\"top\">\n");
    $screen->write("          <td id=\"callouts_cell\"><script>" .
                   "load_callouts_grid(".$group_id.");</script></td>\n");
    add_callout_sequence_buttons($screen,false);
    $screen->write("        </tr></table>\n");
    if ($screen->skin) $screen->end_section(true);
    $screen->end_body();
}

function display_group_fields($dialog,$edit_type,$row,$db)
{
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field('id',$row);
    $dialog->add_edit_row('Name:','name',$row,50);
}

function add_group()
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('callouts.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog->set_body_id('add_callout_group');
    $dialog->set_help('add_callout_group');
    $dialog->start_body('Add Callout Group');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Group',$prefix.'images/AddOrder.png',
                        'process_add_group();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('admin.php','AddGroup');
    $dialog->start_field_table();
    display_group_fields($dialog,ADDRECORD,array(),$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_group()
{
    $db = new DB;
    $group_record = callout_group_record_definition();
    $db->parse_form_fields($group_record);
    if (! $db->insert('callout_groups',$group_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Group Added');
    log_activity('Added Callout Group '.$group_record['name']['value'].' (#'.
                 $group_record['id']['value'].')');
}

function edit_group()
{
    global $shopping_cart;

    if ($shopping_cart) $prefix = '../cartengine/';
    else $prefix = '';
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from callout_groups where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error))
          process_error('Database Error: '.$db->error,0);
       else process_error('Callout Group not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('callouts.css');
    $dialog->add_script_file('callouts.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $dialog_title = 'Edit Callout Group (#'.$id.')';
    $dialog->set_body_id('edit_callout_group');
    $dialog->set_help('edit_callout_group');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Update',$prefix.'images/Update.png',
                        'update_group();');
    $dialog->add_button('Cancel',$prefix.'images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('admin.php','EditGroup');
    $dialog->start_field_table();
    display_group_fields($dialog,UPDATERECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_group()
{
    $db = new DB;
    $group_record = callout_group_record_definition();
    $db->parse_form_fields($group_record);
    if (! $db->update('callout_groups',$group_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Group Updated');
    log_activity('Updated Callout Group '.$group_record['name']['value'] .
                 ' (#'.$group_record['id']['value'].')');
}

function delete_group()
{
    $id = get_form_field('id');
    $db = new DB;
    $query = 'delete from callouts where parent=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    $group_record = callout_group_record_definition();
    $group_record['id']['value'] = $id;
    if (! $db->delete('callout_groups',$group_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Group Deleted');
    log_activity('Deleted Callout Group #'.$id);
}

function process_callout_command($cmd)
{
    if ($cmd == 'addcallout') add_callout();
    else if ($cmd == 'processaddcallout') process_add_callout();
    else if ($cmd == 'editcallout') edit_callout();
    else if ($cmd == 'updatecallout') update_callout();
    else if ($cmd == 'deletecallout') delete_callout();
    else if ($cmd == 'positioncallout') position_callout();
    else if ($cmd == 'resequencecallout') resequence_callouts();
    else if ($cmd == 'calloutgroups') callout_groups();
    else if ($cmd == 'addcalloutgroup') add_group();
    else if ($cmd == 'processaddcalloutgroup') process_add_group();
    else if ($cmd == 'editcalloutgroup') edit_group();
    else if ($cmd == 'updatecalloutgroup') update_group();
    else if ($cmd == 'deletecalloutgroup') delete_group();
}

?>
