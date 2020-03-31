<?php
/*
                      Inroads Shopping Cart - Attributes Tab

              Written 2008-2019 by Randall Severy and James Mussman
                        Copyright 2008-2019 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'image.php';
require_once 'sublist.php';
require_once 'utility.php';
require_once 'cartconfig-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

$attribute_types = array('Choice List','Radio','Check Box','File','Text Area',
                         'Custom','Start Group','End Group','Hidden',
                         'Multiple Fields','Color','Image','Buttons - Radio',
                         'Buttons - Check','Designer');

$admin_attribute_types = array('Choice List','Radio','Check Box','File',
                               'Text Area','Custom','Edit Field','Skip',
                               'Designer');
if (! isset($attribute_fields)) $attribute_fields = array();
if (! isset($option_fields)) $option_fields = array();

function attribute_record_definition()
{
    global $attribute_fields;

    $attribute_record = array();
    $attribute_record['id'] = array('type' => INT_TYPE);
    $attribute_record['id']['key'] = true;
    $attribute_record['name'] = array('type' => CHAR_TYPE);
    $attribute_record['display_name'] = array('type' => CHAR_TYPE);
    $attribute_record['order_name'] = array('type' => CHAR_TYPE);
    $attribute_record['type'] = array('type' => INT_TYPE);
    $attribute_record['admin_type'] = array('type' => INT_TYPE);
    $attribute_record['url'] = array('type' => CHAR_TYPE);
    $attribute_record['description'] = array('type' => CHAR_TYPE);
    $attribute_record['default_value'] = array('type' => CHAR_TYPE);
    $attribute_record['select_function'] = array('type' => CHAR_TYPE);
    $attribute_record['data'] = array('type' => CHAR_TYPE);
    $attribute_record['sub_product'] = array('type' => INT_TYPE);
    $attribute_record['sub_product']['fieldtype'] = CHECKBOX_FIELD;
    $attribute_record['dynamic'] = array('type' => INT_TYPE);
    $attribute_record['dynamic']['fieldtype'] = CHECKBOX_FIELD;
    $attribute_record['required'] = array('type' => INT_TYPE);
    $attribute_record['required']['fieldtype'] = CHECKBOX_FIELD;
    $attribute_record['taxable'] = array('type' => INT_TYPE);
    $attribute_record['taxable']['fieldtype'] = CHECKBOX_FIELD;
    $attribute_record['width'] = array('type' => INT_TYPE);
    $attribute_record['height'] = array('type' => INT_TYPE);
    $attribute_record['max_length'] = array('type' => INT_TYPE);
    $attribute_record['wrap'] = array('type' => INT_TYPE);
    $attribute_record['flags'] = array('type' => INT_TYPE);
    $attribute_record['last_modified'] = array('type' => INT_TYPE);
    foreach ($attribute_fields as $field_name => $field) {
       if ($field['datatype']) {
          $attribute_record[$field_name] = array('type' => $field['datatype']);
          if (isset($field['fieldtype']) &&
              ($field['fieldtype'] == CHECKBOX_FIELD))
             $attribute_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
       }
    }
    return $attribute_record;
}

function display_attributes_screen()
{
    global $include_attribute_overlay_images,$taxable_attributes;
    global $enable_bundles;

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('attributes.css');
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('attributes.js');
    if (file_exists('../admin/custom-config.js'))
       $screen->add_script_file('../admin/custom-config.js');
    $head_block = "<script type=\"text/javascript\">\n";
    if (isset($include_attribute_overlay_images) &&
        $include_attribute_overlay_images)
       $head_block .= "      include_overlay = true;\n";
    if (isset($taxable_attributes) && $taxable_attributes)
       $head_block .= "      taxable_attributes = true;\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
    $screen->set_body_id('attributes');
    $screen->set_help('attributes');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Attributes');
       $screen->start_title_filters();
       add_search_box($screen,'search_attributes','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(143);
    $screen->start_button_column();
    $screen->add_button('Add Attribute','images/AddAttribute.png',
                        'add_attribute();',null,true,false,ADD_BUTTON);
    $screen->add_button('Edit Attribute','images/EditAttribute.png',
                        'edit_attribute();',null,true,false,EDIT_BUTTON);
    $screen->add_button('Copy Attribute','images/EditAttribute.png',
                        'copy_attribute();');
    $screen->add_button('Delete Attribute','images/DeleteAttribute.png',
                        'delete_attribute();',null,true,false,DELETE_BUTTON);
    if (function_exists('display_custom_attribute_buttons'))
       display_custom_attribute_buttons($screen);
    $screen->add_button_separator('sets_button_row',20,true);
    if (! empty($enable_bundles))
       $screen->add_button('Bundles','images/CopyAttribute.png','bundles();');
    $screen->add_button('Attribute Sets','images/Update.png',
                        'attribute_sets();');
    if (! $screen->skin)
       add_search_box($screen,'search_attributes','reset_search');
    $screen->end_button_column();
?>

          <script>load_grid();</script> 
<?    $screen->end_body();
}

function display_attribute_fields($db,$dialog,$edit_type,$id,$row)
{
    global $attribute_types,$attr_desc_field_type,$taxable_attributes;
    global $admin_attribute_types,$attribute_fields;

    if (! isset($attr_desc_field_type)) $attr_desc_field_type = TEXTAREA_FIELD;
    if (! isset($taxable_attributes)) $taxable_attributes = false;
    $dialog->add_hidden_field('id',$id);

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('attribute_tab','attribute_content','change_tab');
    $dialog->add_tab('attribute_tab','Attribute','attribute_tab',
                     'attribute_content','change_tab',true,null,FIRST_TAB);
    $dialog->add_tab('options_tab','Options','options_tab','options_content',
                     'change_tab');
    $dialog->add_tab('conditions_tab','Conditions','conditions_tab',
                     'conditions_content','change_tab',true,null,LAST_TAB);
    $dialog->end_tab_row('');

    $dialog->start_tab_content('attribute_content',true);
    $dialog->start_field_table();

    $dialog->add_edit_row('Name:','name',$row,60);
    $flags = get_row_value($row,'flags');
    $dialog->start_row('Display Name:');
    $dialog->add_input_field('display_name',$row,47);
    $dialog->add_checkbox_field('flag0','No Prompt',$flags & 1);
    $dialog->end_row();
    $dialog->add_edit_row('Name on Order:','order_name',$row,60);

    $att_type = get_row_value($row,'type');
    $dialog->start_row('Type:','middle');
    $dialog->start_choicelist('type','select_attribute_type(this);');
    for ($loop = 0;  $loop < count($attribute_types);  $loop++)
       $dialog->add_list_item($loop,$attribute_types[$loop],$att_type == $loop);
    $dialog->end_choicelist();
    $dialog->end_row();

    $admin_type = get_row_value($row,'admin_type');
    if ($admin_type === '') $admin_type = $att_type;
    $dialog->start_row('Admin Type:','middle');
    $dialog->start_choicelist('admin_type');
    for ($loop = 0;  $loop < count($admin_attribute_types);  $loop++)
       $dialog->add_list_item($loop,$admin_attribute_types[$loop],
                              $admin_type == $loop);
    $dialog->end_choicelist();
    $dialog->write(' (Display type on Add/Edit Order screen)');
    $dialog->end_row();

    switch ($attr_desc_field_type) {
       case EDIT_FIELD:
          $dialog->add_edit_row('Description:','description',$row,55);
          break;
       case TEXTAREA_FIELD:
          $dialog->add_textarea_row('Description:','description',$row,
                                    5,61,WRAP_SOFT);
          break;
       case HTMLEDIT_FIELD:
          $dialog->start_row('Description:','top');
          $dialog->add_htmleditor_popup_field('description',$row,
                                              'Description',550,80);
          $dialog->end_row();
          break;
    }

    $dialog->start_hidden_row('Default Value:','default_row',false,'top');
    $dialog->start_textarea_field('default_value',5,61,WRAP_SOFT);
    write_form_value(get_row_value($row,'default_value'));
    $dialog->end_textarea_field();
    $dialog->end_row();

    if ($edit_type == UPDATERECORD) $frame_name = 'edit_attribute';
    else $frame_name = 'add_attribute';
    $dialog->add_browse_row('URL:','url',$row,60,$frame_name);
    $dialog->add_edit_row('Select Function:','select_function',$row,60);
    $dialog->add_edit_row('Data:','data',$row,60);

    $dialog->start_hidden_row('Sub-Product:','subproduct_row',false,'middle');
    $dialog->add_checkbox_field('sub_product','',$row);
    $dialog->write(' (Separate inventory record for each option)');
    $dialog->end_row();

    $dialog->start_hidden_row('Dynamic:','dynamic_row',false,'middle');
    $dialog->add_checkbox_field('dynamic','',$row);
    $dialog->write(' (Select available options/specify value in inventory)');
    $dialog->end_row();

    $dialog->start_hidden_row('Required:','required_row',false,'middle');
    $dialog->add_checkbox_field('required','',$row);
    $dialog->end_row();

    if ($taxable_attributes) {
       $taxable = get_row_value($row,'taxable');
       if ($taxable === '') $taxable = 1;
       $dialog->start_hidden_row('Taxable:','taxable_row',false,'middle');
       $dialog->add_checkbox_field('taxable','',$taxable);
       $dialog->end_row();
    }

    $dialog->start_hidden_row('# of Columns:','width_row',true);
    $width = get_row_value($row,'width');
    if ($width < 0) $width = -intval($width).'%';
    $dialog->add_input_field('width',$width,10);
    $dialog->end_row();

    $dialog->start_hidden_row('# of Rows:','height_row',true);
    $dialog->add_input_field('height',$row,10);
    $dialog->end_row();

    $dialog->start_hidden_row('Maximum Length:','length_row',true);
    $dialog->add_input_field('max_length',$row,10);
    $dialog->write(' (Empty = unlimited)');
    $dialog->end_row();

    foreach ($attribute_fields as $field_name => $field) {
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
                         $field['title'],$field['width'],$field['height']);
             $dialog->end_row();
             break;
          case CUSTOM_FIELD:
             $dialog->start_row($field['prompt'],'middle');
             if (function_exists('display_attribute_attribute_field'))
                display_custom_attribute_field($dialog,$field_name,
                                            get_row_value($row,$field_name));
             $dialog->end_row();
             break;
          case CUSTOM_ROW:
             if (function_exists('display_custom_attribute_field'))
                display_custom_attribute_field($dialog,$field_name,$row);
             break;
       }
    }

    require_once '../engine/modules.php';
    if (module_attached('display_custom_fields'))
       call_module_event('display_custom_fields',
                         array('attributes',$db,&$dialog,$edit_type,$row));

    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('options_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\"\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("          <table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">");
    $dialog->write("            <tr valign=\"top\"><td width=\"100%\">\n");
    $dialog->write('        <script type="text/javascript">init_options(');
    if ($edit_type == UPDATERECORD) $dialog->write("\"edit_attribute\",\"EditAttribute\"");
    else $dialog->write("\"add_attribute\",\"AddAttribute\"");
    $dialog->write(");\n");
    $dialog->write('                create_options_grid('.$id.");\n");
    $dialog->write("                init_images(\"../cartengine/attributes.php\",");
    if ($edit_type == UPDATERECORD) $dialog->write("\"edit_attribute\",\"EditAttribute\"");
    else $dialog->write("\"add_attribute\",\"AddAttribute\"");
    $dialog->write(",480,2);</script>\n");
    add_image_sample($dialog);
    $dialog->write("            </td>\n");
    if ($dialog->skin)
       $dialog->write("            <td class=\"miniButtons\" " .
                      "style=\"padding-left: 10px;\">\n");
    else $dialog->write("            <td width=\"50\" nowrap align=\"center\">\n");
    $dialog->add_dialog_button('Top','images/MoveTop.png',
                               'move_option_top();',true);
    $dialog->add_dialog_button('Up','images/MoveUp.png',
                               'move_option_up();',true);
    $dialog->add_dialog_button('Down','images/MoveDown.png',
                               'move_option_down();',true);
    $dialog->add_dialog_button('Bottom','images/MoveBottom.png',
                               'move_option_bottom();',true);
    $dialog->write("            </td>\n");
    $dialog->write("          </tr></table>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->start_tab_content('conditions_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\"\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("          <table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">");
    $dialog->write("            <tr valign=\"top\"><td width=\"100%\">\n");
    $dialog->write('              <script type="text/javascript">create_conditions_grid(' .
                   $id.");</script>\n");
    $dialog->write("            </td>\n");
    if ($dialog->skin)
       $dialog->write("            <td class=\"miniButtons\" " .
                      "style=\"padding-left: 10px;\">\n");
    else $dialog->write("            <td width=\"50\" nowrap align=\"center\">\n");
    $dialog->add_dialog_button('Top','images/MoveTop.png',
                               'move_condition_top();',true);
    $dialog->add_dialog_button('Up','images/MoveUp.png',
                               'move_condition_up();',true);
    $dialog->add_dialog_button('Down','images/MoveDown.png',
                               'move_condition_down();',true);
    $dialog->add_dialog_button('Bottom','images/MoveBottom.png',
                               'move_condition_bottom();',true);
    $dialog->write("            </td>\n");
    $dialog->write("          </tr></table>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    $dialog->end_tab_section();
}

function parse_attribute_fields($db,&$attribute_record)
{
    $db->parse_form_fields($attribute_record);
    $attribute_record['last_modified']['value'] = time();
    $flags = 0;
    if (get_form_field('flag0') == 'on') $flags |= 1;
    $attribute_record['flags']['value'] = $flags;
    if (substr($attribute_record['width']['value'],-1) == '%')
       $attribute_record['width']['value'] =
          -intval(substr($attribute_record['width']['value'],0,-1));
}

function add_option_buttons($dialog)
{
    $dialog->add_button_separator('option_buttons_row',20);
    $dialog->add_button('Add Option','images/AddOption.png',
                        'add_option();','add_option',null,false);
    $dialog->add_button('Edit Option','images/EditOption.png',
                        'edit_option();','edit_option',null,false);
    $dialog->add_button('Delete Option','images/DeleteOption.png',
                        'delete_option();','delete_option',null,false);
    $dialog->add_button_separator('image_buttons_row',20);
    add_image_buttons($dialog,false);
}

function add_condition_buttons($dialog)
{
    $dialog->add_button_separator('condition_buttons_row',20);
    $dialog->add_button('Add Condition','images/AddOption.png',
                        'add_condition();','add_condition',null,false);
    $dialog->add_button('Edit Condition','images/EditOption.png',
                        'edit_condition();','edit_condition',null,false);
    $dialog->add_button('Delete Condition','images/DeleteOption.png',
                        'delete_condition();','delete_condition',null,false);
}

function create_attribute()
{
    $db = new DB;
    $attribute_record = attribute_record_definition();
    $attribute_record['name']['value'] = 'New Attribute';
    if (! $db->insert('attributes',$attribute_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'attribute_id = '.$id.';';
    log_activity('Created New Attribute #'.$id);
}

function add_attribute_head_block(&$dialog)
{
    global $cms_base_url,$include_attribute_overlay_images;

    $head_block = "<script type=\"text/javascript\">\n";
    if (isset($cms_base_url))
       $head_block .= "      cms_url = '".$cms_base_url."';\n";
    $head_block .= "      image_dir = '/attrimages';\n";
    if (isset($include_attribute_overlay_images) &&
        $include_attribute_overlay_images)
       $head_block .= "      include_overlay = true;\n";
    $head_block .= '    </script>';
    $dialog->add_head_line($head_block);
}

function add_attribute()
{
    $db = new DB;
    $id = get_form_field('id');
    $copying = get_form_field('copy');
    if ($copying) {
       $query = 'select * from attributes where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          if (isset($db->error))
             process_error('Database Error: '.$db->error,0);
          else process_error('Attribute not found',0);
          return;
       }
       $action = 'Copy';
    }
    else {
       $row = array();   $action = 'Add';
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->add_style_sheet('image.css');
    $dialog->add_script_file('image.js');
    $dialog->set_onload_function('add_attribute_onload();');
    add_attribute_head_block($dialog);
    $dialog_title = $action.' Attribute (#'.$id.')';
    $dialog->set_body_id('add_attribute');
    $dialog->set_help('add_attribute');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(145);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button($action.' Attribute','images/AddAttribute.png',
                        'process_add_attribute();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_option_buttons($dialog);
    add_condition_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','AddAttribute');
    if (! $dialog->skin) $dialog->start_field_table();
    display_attribute_fields($db,$dialog,ADDRECORD,$id,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_attribute()
{
    $db = new DB;
    $attribute_record = attribute_record_definition();
    parse_attribute_fields($db,$attribute_record);
    $attribute_record['last_modified']['value'] = time();
    if (! $db->update('attributes',$attribute_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('add_attribute')) {
       $attribute_info = $db->convert_record_to_array($attribute_record);
       if (! call_module_event('add_attribute',array($db,$attribute_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Attribute Added');
    log_activity('Added Attribute '.$attribute_record['name']['value'].' (#'.
                 $attribute_record['id']['value'].')');
}

function edit_attribute()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from attributes where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->add_style_sheet('image.css');
    $dialog->add_script_file('image.js');
    $dialog->set_onload_function('edit_attribute_onload();');
    add_attribute_head_block($dialog);
    $dialog_title = 'Edit Attribute (#'.$id.')';
    $dialog->set_body_id('edit_attribute');
    $dialog->set_help('edit_attribute');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(145);
    $dialog->start_button_column(false,false,true);
    $dialog->add_button('Update','images/Update.png','update_attribute();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    add_option_buttons($dialog);
    add_condition_buttons($dialog);
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','EditAttribute');
    if (! $dialog->skin) $dialog->start_field_table();
    display_attribute_fields($db,$dialog,UPDATERECORD,$id,$row);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_attribute()
{
    $db = new DB;
    $attribute_record = attribute_record_definition();
    parse_attribute_fields($db,$attribute_record);
    $attribute_record['last_modified']['value'] = time();
    if (! $db->update('attributes',$attribute_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_attribute')) {
       $attribute_info = $db->convert_record_to_array($attribute_record);
       if (! call_module_event('update_attribute',
                               array($db,$attribute_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Attribute Updated');
    log_activity('Updated Attribute '.$attribute_record['name']['value'].' (#'.
                 $attribute_record['id']['value'].')');
}

function copy_attribute()
{
    $db = new DB;
    $old_id = get_form_field('id');
    $query = 'select * from attributes where id=?';
    $query = $db->prepare_query($query,$old_id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute not found',0);
       return;
    }
    $attribute_record = attribute_record_definition();
    foreach ($row as $field_name => $field_value) {
       if ($field_name == 'id') continue;
       $attribute_record[$field_name]['value'] = $field_value;
    }
    if (! $db->insert('attributes',$attribute_record)) {
       http_response(422,$db->error);   return;
    }
    $new_id = $db->insert_id();

    $query = 'select * from attribute_options where parent=?';
    $query = $db->prepare_query($query,$old_id);
    $option_rows = $db->get_records($query);
    if (! $option_rows) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);
       }
       return;
    }
    $option_record = option_record_definition();
    $image_record = image_record_definition();
    foreach ($option_rows as $option_row) {
       $option_id = $option_row['id'];
       foreach ($option_row as $field_name => $field_value) {
          if ($field_name == 'id') continue;
          if ($field_name == 'parent')
             $option_record[$field_name]['value'] = $new_id;
          else $option_record[$field_name]['value'] = $field_value;
       }
       if (! $db->insert('attribute_options',$option_record)) {
          http_response(422,$db->error);   return;
       }
       $new_option_id = $db->insert_id();

       $query = 'select * from images where parent=? and parent_type=2';
       $query = $db->prepare_query($query,$option_id);
       $image_rows = $db->get_records($query);
       if (! $image_rows) {
          if (isset($db->error)) {
             http_response(422,'Database Error: '.$db->error);
             return;
          }
       }
       else foreach ($image_rows as $image_row) {
          foreach ($image_row as $field_name => $field_value) {
             if ($field_name == 'id') continue;
             if ($field_name == 'parent')
                $image_record[$field_name]['value'] = $new_option_id;
             else $image_record[$field_name]['value'] = $field_value;
          }
          if (! $db->insert('images',$image_record)) {
             http_response(422,$db->error);   return;
          }
       }
    }

    print 'attribute_id = '.$new_id.';';
    log_activity('Copied Attribute '.$attribute_record['name']['value'] .
                 ' (#'.$old_id.') to #'.$new_id);
}

function delete_attribute()
{
    $db = new DB;
    $id = get_form_field('id');

    if (! delete_options($id,$db)) return;
    if (! delete_conditions($id,$db)) return;
    $query = 'delete from product_attributes where related_id=?';
    $query = $db->prepare_query($query,$id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    log_activity('Deleted Product Attributes for Attribute ID #'.$id);

    require_once '../engine/modules.php';
    if (module_attached('delete_attribute')) {
       $query = 'select * from attributes where id=?';
       $query = $db->prepare_query($query,$id);
       $attribute_info = $db->get_record($query);
    }
    $attribute_record = attribute_record_definition();
    $attribute_record['id']['value'] = $id;
    if (! $db->delete('attributes',$attribute_record)) {
       http_response(422,$db->error);   return;
    }
    if (module_attached('delete_attribute')) {
       require_once '../engine/modules.php';
       if (! call_module_event('delete_attribute',
                               array($db,$attribute_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Attribute Deleted');
    log_activity('Deleted Attribute #'.$id);
}

function option_record_definition()
{
    global $option_fields;

    $option_record = array();
    $option_record['id'] = array('type' => INT_TYPE);
    $option_record['id']['key'] = true;
    $option_record['sequence'] = array('type' => INT_TYPE);
    $option_record['parent'] = array('type' => INT_TYPE);
    $option_record['name'] = array('type' => CHAR_TYPE);
    $option_record['order_name'] = array('type' => CHAR_TYPE);
    $option_record['adjust_type'] = array('type' => INT_TYPE);
    $option_record['adjustment'] = array('type' => FLOAT_TYPE);
    $option_record['price_break_type'] = array('type' => INT_TYPE);
    $option_record['price_breaks'] = array('type' => CHAR_TYPE);
    $option_record['default_value'] = array('type' => INT_TYPE);
    $option_record['default_value']['fieldtype'] = CHECKBOX_FIELD;
    $option_record['overlay_image'] = array('type' => CHAR_TYPE);
    $option_record['url'] = array('type' => CHAR_TYPE);
    $option_record['last_modified'] = array('type' => INT_TYPE);
    $option_record['data'] = array('type' => CHAR_TYPE);
    foreach ($option_fields as $field_name => $field)
       if ($field['datatype']) {
          $option_record[$field_name] = array('type' => $field['datatype']);
          if (isset($field['fieldtype']) &&
              ($field['fieldtype'] == CHECKBOX_FIELD))
             $option_record[$field_name]['fieldtype'] = CHECKBOX_FIELD;
       }
    return $option_record;
}

function display_option_fields($db,$dialog,$edit_type,$row,$use_price_breaks)
{
    global $include_attribute_overlay_images,$option_fields,$url_prefix;
    global $default_price_break_type;

    $adjust_type = get_row_value($row,'adjust_type');
    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('option_tab','option_content','change_option_tab');
    $dialog->add_tab('option_tab','Option','option_tab','option_content',
                     'change_option_tab',($adjust_type == 4),null,FIRST_TAB);
    if ($use_price_breaks) {
       $dialog->add_tab('pricebreaks_tab','Price Breaks','pricebreaks_tab',
                        'pricebreaks_content','change_option_tab',
                        ($adjust_type == 4),null,LAST_TAB);
       $change_function = 'select_adjust_type(this);';
    }
    else $change_function = null;
    $dialog->end_tab_row('');

    $dialog->start_tab_content('option_content',true);
    $dialog->start_field_table();

    if ($edit_type == UPDATERECORD) $dialog->add_hidden_field('id',$row);
    $dialog->add_edit_row('Option Name:','name',$row,40);
    $dialog->add_edit_row('Name on Order:','order_name',$row,40);
    $dialog->add_edit_row('Sequence:','sequence',$row,10);
    $dialog->start_row('Adjustment Type:','middle');
    $dialog->start_choicelist('adjust_type',$change_function);
    $dialog->add_list_item(0,'Fixed',$adjust_type == 0);
    $dialog->add_list_item(1,'Percentage',$adjust_type == 1);
    $dialog->add_list_item(2,'Start Group',$adjust_type == 2);
    $dialog->add_list_item(3,'End Group',$adjust_type == 3);
    if ($use_price_breaks)
       $dialog->add_list_item(4,'Price Breaks',$adjust_type == 4);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Adjustment:','adjustment',$row,10);
    if ($include_attribute_overlay_images) {
       $overlay_image = get_row_value($row,'overlay_image');
       if ($edit_type == ADDRECORD) $frame = 'add_option';
       else $frame = 'edit_option';
       $dialog->add_browse_row('Overlay Image:','overlay_image',$overlay_image,
                               40,$frame,'/attrimages/overlay/',true,false,
                               true,false);
    }
    $dialog->add_edit_row('URL:','url',$row,40);
    $dialog->start_row('Default:','middle');
    $dialog->add_checkbox_field('default_value','',$row);
    $dialog->end_row();
    $attribute_type = get_form_field('Attribute_Type');
    $dialog->write('<tr valign=middle>');
    switch ($attribute_type) {
       case 10: //color
          $dialog->start_row('Color:');
          $data = get_row_value($row,'data');
          $dialog->write('<input type="text" class="text" name="data" ' .
             'id="__data" size=40 value="'.$data.'" style="background-color:' .
             $data.'">');
          $dialog->write('<script type="text/javascript">if(typeof(\'' .
                         'dhtmlXColorPickerInput\')!=\'undefined\'){
                             var picker = new dhtmlXColorPickerInput(\'__data\');
                             picker.setImagePath(\''.$url_prefix.'/engine/images/\');
                             picker.setColor("'.$data.'");
                             picker.attachEvent(\'onShow\',picker_show_delay)
                             picker.init();
                             }</script>');
          $dialog->end_row();
          break;
       case 11: //image
          if ($edit_type == ADDRECORD) $frame = 'add_option';
          else $frame = 'edit_option';
          $dialog->add_browse_row('Thumbnail:','data',$row,40,$frame,
                                  '/attrimages/thumbnail/',true,false,
                                  true,false);
          break;
       default: //all others
          $dialog->add_edit_row('Data:','data',$row,40);
          break;
    }
    
    foreach ($option_fields as $field_name => $field) {
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
                         $field['title'],$field['width'],$field['height']);
             $dialog->end_row();
             break;
          case CUSTOM_FIELD:
             $dialog->start_row($field['prompt'],'middle');
             if (function_exists('display_custom_option_field'))
                display_custom_option_field($dialog,$field_name,
                                            get_row_value($row,$field_name));
             $dialog->end_row();
             break;
          case CUSTOM_ROW:
             if (function_exists('display_custom_option_field'))
                display_custom_option_field($dialog,$field_name,$row);
             break;
       }
    }
    require_once '../engine/modules.php';
    if (module_attached('display_custom_fields'))
       call_module_event('display_custom_fields',
                         array('options',$db,&$dialog,$edit_type,$row));
    $dialog->end_field_table();
    $dialog->end_tab_content();

    if ($use_price_breaks) {
       $dialog->start_tab_content('pricebreaks_content',false);
       if ($edit_type == ADDRECORD) $form_name = 'AddOption';
       else $form_name = 'EditOption';
       if ($edit_type == ADDRECORD) {
          if (! isset($default_price_break_type)) $price_break_type = 0;
          else $price_break_type = $default_price_break_type;
       }
       else $price_break_type = $row['price_break_type'];
       display_price_breaks($dialog,$form_name,$price_break_type,
                            get_row_value($row,'price_breaks'));
       $dialog->end_tab_content();
    }

    $dialog->end_tab_section();
}

function add_option()
{
    global $url_prefix;

    $db = new DB;
    $parent = get_form_field('Parent');
    $frame = get_form_field('Frame');
    $type = get_form_field('Attribute_Type');
    $features = get_cart_config_value('features',$db);
    if ($features & REGULAR_PRICE_BREAKS) {
       require_once 'pricebreak.php';   $use_price_breaks = true;
    }
    else $use_price_breaks = false;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    if ($use_price_breaks) {
       $dialog->enable_aw();
       $dialog->add_script_file('pricebreak.js');
    }
    switch ($type) {
       case 10:
          $dialog->add_script_file($url_prefix.'/engine/dhtmlxcommon.js',
                                   '../engine/dhtmlxcommon.js');
          $dialog->add_script_file($url_prefix.'/engine/dhtmlxcolorpicker.js',
                                   '../engine/dhtmlxcolorpicker.js');
          $dialog->add_style_sheet($url_prefix.'/engine/dhtmlxcolorpicker.css',
                                   '../engine/dhtmlxcolorpicker.css');
          break;
    }
    $dialog->set_body_id('add_attribute_option');
    $dialog->set_help('add_attribute_option');
    $dialog->start_body('Add Option');
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Add Option','images/AddOption.png',
                        'process_add_option();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    if ($use_price_breaks) add_price_break_buttons($dialog,false,true);
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','AddOption');
    $dialog->add_hidden_field('parent',$parent);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('Attribute_Type',$type);
    if (! $dialog->skin) $dialog->start_field_table();
    display_option_fields($db,$dialog,ADDRECORD,array(),$use_price_breaks);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_option()
{
    $db = new DB;
    $parent = get_form_field('parent');
    $query = 'select sequence from attribute_options where parent=? ' .
             'order by sequence desc limit 1';
    $query = $db->prepare_query($query,$parent);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       $sequence = 0;
    }
    else $sequence = $row['sequence'];
    $sequence++;

    $option_record = option_record_definition();
    $db->parse_form_fields($option_record);
    $option_record['sequence']['value'] = $sequence;
    $option_record['last_modified']['value'] = time();
    if (! $db->insert('attribute_options',$option_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    require_once '../engine/modules.php';
    if (module_attached('add_attr_option')) {
       $query = 'select * from attributes where id=?';
       $query = $db->prepare_query($query,$parent);
       $attribute_info = $db->get_record($query);
       if (! $attribute_info) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       $option_info = $db->convert_record_to_array($option_record);
       if (! call_module_event('add_attr_option',
                               array($db,$attribute_info,$option_info))) {
          http_response(422,get_module_errors());   return;
       }
    }

    http_response(201,'Attribute Option Added');
    log_activity('Added Attribute Option '.$option_record['name']['value'] .
                 ' to Attribute #'.$parent);
}

function edit_option()
{
    global $url_prefix;

    $db = new DB;
    $id = get_form_field('id');
    $type = get_form_field('Attribute_Type');
    $features = get_cart_config_value('features',$db);
    if ($features & REGULAR_PRICE_BREAKS) {
       require_once 'pricebreak.php';   $use_price_breaks = true;
    }
    else $use_price_breaks = false;

    $query = 'select * from attribute_options where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute Option not found',0);
       return;
    }
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    if ($use_price_breaks) {
       $dialog->enable_aw();
       $dialog->add_script_file('pricebreak.js');
    }
    switch ($type) {
       case 10:
          $dialog->add_script_file($url_prefix.'/engine/dhtmlxcommon.js',
                                   '../engine/dhtmlxcommon.js');
          $dialog->add_script_file($url_prefix.'/engine/dhtmlxcolorpicker.js',
                                   '../engine/dhtmlxcolorpicker.js');
          $dialog->add_style_sheet($url_prefix.'/engine/dhtmlxcolorpicker.css',
                                   '../engine/dhtmlxcolorpicker.css');
          break;
    }
    $dialog_title = 'Edit Attribute Option (#'.$id.')';
    $dialog->set_body_id('edit_attribute_option');
    $dialog->set_help('edit_attribute_option');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_option();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    if ($use_price_breaks) add_price_break_buttons($dialog,false,true);
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','EditOption');
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->add_hidden_field('parent',$row);
    if (! $dialog->skin) $dialog->start_field_table();
    display_option_fields($db,$dialog,UPDATERECORD,$row,$use_price_breaks);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_option()
{
    $db = new DB;
    $option_record = option_record_definition();
    $db->parse_form_fields($option_record);
    $option_record['last_modified']['value'] = time();
    if (! $db->update('attribute_options',$option_record)) {
       http_response(422,$db->error);   return;
    }
    require_once '../engine/modules.php';
    if (module_attached('update_attr_option')) {
       $option_info = $db->convert_record_to_array($option_record);
       $query = 'select * from attributes where id=?';
       $query = $db->prepare_query($query,$option_info['parent']);
       $attribute_info = $db->get_record($query);
       if (! $attribute_info) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       if (! call_module_event('update_attr_option',
                               array($db,$attribute_info,$option_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Option Updated');
    log_activity('Updated Attribute Option '.$option_record['name']['value']);
}

function delete_option()
{
    $db = new DB;
    $id = get_form_field('id');
    require_once '../engine/modules.php';
    if (module_attached('delete_attr_option')) {
       $query = 'select * from attribute_options where id=?';
       $query = $db->prepare_query($query,$id);
       $option_info = $db->get_record($query);
       if (! $option_info) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       $query = 'select * from attributes where id=?';
       $query = $db->prepare_query($query,$option_info['parent']);
       $attribute_info = $db->get_record($query);
       if (! $attribute_info) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
    }
    $option_record = option_record_definition();
    $option_record['id']['value'] = $id;
    if (! $db->delete('attribute_options',$option_record)) {
       http_response(422,$db->error);   return;
    }
    if (! delete_images(2,$id,$error,$db)) {
       http_response(422,$error);   return;
    }
    if (module_attached('delete_attr_option')) {
       require_once '../engine/modules.php';
       if (! call_module_event('delete_attr_option',
                               array($db,$attribute_info,$option_info))) {
          http_response(422,get_module_errors());   return;
       }
    }
    http_response(201,'Attribute Option Deleted');
    log_activity('Deleted Attribute Option #'.$id);
}

function delete_options($parent,$db)
{
    $query = 'select id from attribute_options where parent=?';
    $query = $db->prepare_query($query,$parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          http_response(422,$db->error);   return false;
       }
       return true;
    }
    foreach ($rows as $row) {
       if (! delete_images(2,$row['id'],$error,$db)) {
          http_response(422,$error);   return false;
       }
    }
    $query = 'delete from attribute_options where parent=?';
    $query = $db->prepare_query($query,$parent);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    log_activity('Deleted Options for Attribute ID #'.$parent);
    return true;
}

function resequence_options()
{
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');

    $db = new DB;

    $query = 'select id,sequence from attribute_options where parent=? ' .
             'order by sequence';
    $query = $db->prepare_query($query,$parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       return false;
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
          $query = 'update attribute_options set sequence=? where id=?';
          $query = $db->prepare_query($query,$updated_sequence,$row['id']);
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,'Database Error: '.$db->error);   return;
          }
       }
    }

    http_response(201,'Attribute Options Resequenced');
    log_activity('Resequenced Attribute Option #'.$old_sequence.' to #' .
                 $new_sequence.' for Attribute #'.$parent);
}

function condition_record_definition()
{
    $condition_record = array();
    $condition_record['id'] = array('type' => INT_TYPE);
    $condition_record['id']['key'] = true;
    $condition_record['sequence'] = array('type' => INT_TYPE);
    $condition_record['parent'] = array('type' => INT_TYPE);
    $condition_record['compare'] = array('type' => INT_TYPE);
    $condition_record['value'] = array('type' => CHAR_TYPE);
    $condition_record['action'] = array('type' => INT_TYPE);
    $condition_record['target'] = array('type' => INT_TYPE);
    return $condition_record;
}

function display_condition_fields($db,$dialog,$edit_type,$row)
{
    if ($edit_type == UPDATERECORD) $dialog->add_hidden_field('id',$row);
    $dialog->add_hidden_field('parent',$row);
    $attr_type = get_form_field('Attribute_Type');
    if ($attr_type == 0) {
       $query = 'select id,name from attribute_options where parent=?';
       $query = $db->prepare_query($query,$row['parent']);
       $options = $db->get_records($query);
    }
    else $options = null;
    $query = 'select id,name from attributes';
    $attributes = $db->get_records($query);
    $compare = get_row_value($row,'compare');
    $dialog->start_row('If this option:','middle');
    $dialog->start_choicelist('compare');
    $dialog->add_list_item(0,'equals',$compare == 0);
    $dialog->add_list_item(1,'not equals',$compare == 1);
    $dialog->add_list_item(2,'greater than',$compare == 2);
    $dialog->add_list_item(3,'less than',$compare == 3);
    $dialog->end_choicelist();
    $dialog->end_row();
    if ($options) {
       $value = get_row_value($row,'value');
       $dialog->start_row('Option:','middle');
       $dialog->start_choicelist('value');
       $dialog->add_list_item('','',(! $value));
       foreach ($options as $option)
          $dialog->add_list_item($option['id'],$option['name'],
                                 $value == $option['id']);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    else $dialog->add_edit_row('Option:','value',$row,20);
    $action = get_row_value($row,'action');
    $dialog->start_row('Then:','middle');
    $dialog->start_choicelist('action');
    $dialog->add_list_item(0,'show',$action == 0);
    $dialog->add_list_item(1,'hide',$action == 1);
    $dialog->end_choicelist();
    $dialog->end_row();
    $target = get_row_value($row,'target');
    $dialog->start_row('Attribute:','middle');
    $dialog->start_choicelist('target');
    $dialog->add_list_item('','',(! $target));
    if ($attributes) {
       foreach ($attributes as $attribute)
          $dialog->add_list_item($attribute['id'],$attribute['name'],
                                 $target == $attribute['id']);
    }
    $dialog->end_choicelist();
    $dialog->end_row();

}

function add_condition()
{
    $db = new DB;
    $parent = get_form_field('Parent');
    $frame = get_form_field('Frame');

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->set_body_id('add_attribute_condition');
    $dialog->set_help('add_attribute_condition');
    $dialog->start_body('Add Condition');
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button('Add Condition','images/AddOption.png',
                        'process_add_condition();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','AddCondition');
    $dialog->add_hidden_field('parent',$parent);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    display_condition_fields($db,$dialog,ADDRECORD,array('parent'=>$parent));
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_condition()
{
    $db = new DB;
    $parent = get_form_field('parent');
    $query = 'select sequence from attribute_conditions where parent=? ' .
             'order by sequence desc limit 1';
    $query = $db->prepare_query($query,$parent);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return;
       }
       $sequence = 0;
    }
    else $sequence = $row['sequence'];
    $sequence++;

    $condition_record = condition_record_definition();
    $db->parse_form_fields($condition_record);
    $condition_record['sequence']['value'] = $sequence;
    if (! $db->insert('attribute_conditions',$condition_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    http_response(201,'Attribute Condition Added');
    log_activity('Added Attribute Condition #'.$db->insert_id() .
                 ' to Attribute #'.$parent);
}

function edit_condition()
{
    $db = new DB;
    $id = get_form_field('id');
    $frame = get_form_field('Frame');

    $query = 'select * from attribute_conditions where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute Condition not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog_title = 'Edit Attribute Condition (#'.$id.')';
    $dialog->set_body_id('edit_attribute_condition');
    $dialog->set_help('edit_attribute_condition');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(125);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_condition();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','EditCondition');
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->start_field_table();
    display_condition_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_condition()
{
    $db = new DB;
    $condition_record = condition_record_definition();
    $db->parse_form_fields($condition_record);
    if (! $db->update('attribute_conditions',$condition_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Condition Updated');
    log_activity('Updated Attribute Condition #'.$condition_record['id']['value']);
}

function delete_condition()
{
    $db = new DB;
    $id = get_form_field('id');
    $condition_record = condition_record_definition();
    $condition_record['id']['value'] = $id;
    if (! $db->delete('attribute_conditions',$condition_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Attribute Condition Deleted');
    log_activity('Deleted Attribute Condition #'.$id);
}

function delete_conditions($parent,$db)
{
    $query = 'delete from attribute_conditions where parent=?';
    $query = $db->prepare_query($query,$parent);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return false;
    }
    log_activity('Deleted Conditions for Attribute ID #'.$parent);
    return true;
}

function resequence_conditions()
{
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');

    $db = new DB;

    $query = 'select id,sequence from attribute_conditions where parent=? ' .
             'order by sequence';
    $query = $db->prepare_query($query,$parent);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       return false;
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
          $query = 'update attribute_conditions set sequence=? where id=?';
          $query = $db->prepare_query($query,$updated_sequence,$row['id']);
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,'Database Error: '.$db->error);   return;
          }
       }
    }

    http_response(201,'Attribute Conditions Resequenced');
    log_activity('Resequenced Attribute Condition #'.$old_sequence.' to #' .
                 $new_sequence.' for Attribute #'.$parent);
}

function attribute_set_record_definition()
{
    $set_record = array();
    $set_record['id'] = array('type' => INT_TYPE);
    $set_record['id']['key'] = true;
    $set_record['name'] = array('type' => CHAR_TYPE);
    return $set_record;
}

function attribute_sets()
{
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->set_body_id('attribute_sets');
    $dialog->set_help('attribute_sets');
    $dialog->start_body('Attribute Sets');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Add Set','images/AddCategory.png','add_set();');
    $dialog->add_button('Edit Set','images/EditCategory.png','edit_set();');
    $dialog->add_button('Delete Set','images/DeleteCategory.png',
                        'delete_set();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->write("        <script>create_sets_grid();</script>\n");
    $dialog->end_body();
}

function display_set_fields($dialog,$edit_type,$row,$db)
{
    $id = get_row_value($row,'id');
    $dialog->add_hidden_field('id',$id);
    $dialog->add_edit_row('Name:','name',$row,85);
    $dialog->write('<tr><td colspan="2">'."\n");
    $dialog->write("        <script type=\"text/javascript\">\n");
    $dialog->write("           set_attributes_list = new SubList();\n");
    $dialog->write("           set_attributes_list.name = 'set_attributes_list';\n");
    $dialog->write("           set_attributes_list.script_url = 'attributes.php';\n");
    $dialog->write("           set_attributes_list.frame_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("edit_set';\n");
    else $dialog->write("add_set;'\n");
    $dialog->write("           set_attributes_list.form_name = '");
    if ($edit_type == UPDATERECORD) $dialog->write("EditSet';\n");
    else $dialog->write("AddSet';\n");
    $dialog->write("           set_attributes_list.grid_width = 250;\n");
    $dialog->write("           set_attributes_list.grid_height = 490;\n");
    $dialog->write("           set_attributes_list.left_table = '" .
                   "attribute_set_attributes';\n");
    $dialog->write("           set_attributes_list.left_titles = ['Name'];\n");
    $dialog->write("           set_attributes_list.left_label = 'attributes';\n");
    $dialog->write("           set_attributes_list.right_table = 'attributes';\n");
    $dialog->write("           set_attributes_list.right_titles = ['Name'];\n");
    $dialog->write("           set_attributes_list.right_label = 'attributes';\n");
    $dialog->write("           set_attributes_list.right_single_label = '" .
                   "attribute';\n");
    $dialog->write("        </script>\n");
    create_sublist_grids('set_attributes_list',$dialog,$id,
                         'Set Attributes','All Attributes');
    $dialog->write("</td></tr>\n");
}

function create_set()
{
    $db = new DB;
    $set_record = attribute_set_record_definition();
    $set_record['name']['value'] = 'New Attribute Set';
    if (! $db->insert('attribute_sets',$set_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'set_id = '.$id.';';
    log_activity('Created New Attribute Set #'.$id);
}

function add_set()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from attribute_sets where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute Set not found',0);
       return;
    }
    $row['name'] = '';
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->add_script_file('sublist.js');
    $dialog->set_body_id('add_set');
    $dialog->set_help('add_set');
    $dialog->set_onload_function('add_set_onload();');
    $dialog->start_body('Add Attribute Set');
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Add Set','images/AddCategory.png',
                        'process_add_set();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','AddSet');
    $dialog->start_field_table();
    display_set_fields($dialog,ADDRECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_set()
{
    $db = new DB;
    $set_record = attribute_set_record_definition();
    $db->parse_form_fields($set_record);
    if (! $db->update('attribute_sets',$set_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Attribute Set Added');
    log_activity('Added Attribute Set '.$set_record['name']['value'] .
                 ' (#'.$set_record['id']['value'].')');
}

function edit_set()
{
    $db = new DB;
    $id = get_form_field('id');
    $query = 'select * from attribute_sets where id=?';
    $query = $db->prepare_query($query,$id);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Attribute Set not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->add_script_file('sublist.js');
    $dialog_title = 'Edit Attribute Set (#'.$id.')';
    $dialog->set_body_id('edit_set');
    $dialog->set_help('edit_set');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_set();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','EditSet');
    $dialog->start_field_table();
    display_set_fields($dialog,UPDATERECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_set()
{
    $db = new DB;
    $set_record = attribute_set_record_definition();
    $db->parse_form_fields($set_record);
    if (! $db->update('attribute_sets',$set_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Attribute Set Updated');
    log_activity('Updated Attribute Set '.$set_record['name']['value'] .
                 ' (#'.$set_record['id']['value'].')');
}

function delete_set()
{
    $id = get_form_field('id');
    $db = new DB;
    if (! delete_sublist_items('attribute_set_attributes',$id,$db)) {
       http_response(422,$db->error);   return;
    }
    $set_record = attribute_set_record_definition();
    $set_record['id']['value'] = $id;
    if (! $db->delete('attribute_sets',$set_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Attribute Set Deleted');
    log_activity('Deleted Attribute Set #'.$id);
}

function select_set()
{
    $product_id = get_form_field('Product');
    $frame = get_form_field('Frame');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('attributes.css');
    $dialog->add_script_file('attributes.js');
    $dialog->set_body_id('select_attribute_set');
    $dialog->set_help('select_attribute_set');
    $dialog->start_body('Select Attribute Set');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Select','images/Update.png','select_set();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('attributes.php','SelectSet');
    $dialog->add_hidden_field('Product',$product_id);
    $dialog->add_hidden_field('Frame',$frame);
    $dialog->write("        <script>create_sets_grid();</script>\n");
    $dialog->end_form();
    $dialog->end_body();
}

if (! check_login_cookie()) exit;

init_images('attributes.php','attributes.js',2);
$cmd = get_form_field('cmd');

if ($cmd == 'createattribute') create_attribute();
else if ($cmd == 'addattribute') add_attribute();
else if ($cmd == 'processaddattribute') process_add_attribute();
else if ($cmd == 'editattribute') edit_attribute();
else if ($cmd == 'updateattribute') update_attribute();
else if ($cmd == 'copyattribute') copy_attribute();
else if ($cmd == 'deleteattribute') delete_attribute();
else if ($cmd == 'addoption') add_option();
else if ($cmd == 'processaddoption') process_add_option();
else if ($cmd == 'editoption') edit_option();
else if ($cmd == 'updateoption') update_option();
else if ($cmd == 'deleteoption') delete_option();
else if ($cmd == 'resequenceoptions') resequence_options();
else if ($cmd == 'addcondition') add_condition();
else if ($cmd == 'processaddcondition') process_add_condition();
else if ($cmd == 'editcondition') edit_condition();
else if ($cmd == 'updatecondition') update_condition();
else if ($cmd == 'deletecondition') delete_condition();
else if ($cmd == 'resequenceconditions') resequence_conditions();
else if ($cmd == 'attributesets') attribute_sets();
else if ($cmd == 'createset') create_set();
else if ($cmd == 'addset') add_set();
else if ($cmd == 'processaddset') process_add_set();
else if ($cmd == 'editset') edit_set();
else if ($cmd == 'updateset') update_set();
else if ($cmd == 'deleteset') delete_set();
else if ($cmd == 'selectset') select_set();
else if ($cmd == 'addimage') add_image();
else if ($cmd == 'processaddimage') process_add_image();
else if ($cmd == 'processuploadedimage') process_uploaded_image();
else if ($cmd == 'updateimagefile') update_image_file();
else if ($cmd == 'editimage') edit_image();
else if ($cmd == 'updateimage') update_image();
else if ($cmd == 'getimageinfo') get_image_info();
else if ($cmd == 'deleteimage') delete_image();
else if (process_sublist_command($cmd)) {}
else if (function_exists('custom_attribute_command') &&
         custom_attribute_command($cmd)) {}
else display_attributes_screen();

DB::close_all();

?>
