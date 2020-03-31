/*
          Inroads Shopping Cart - Attributes Tab JavaScript Functions

                        Written 2008-2018 by Randall Severy, James Mussman
                         Copyright 2008-2018 Inroads, LLC
*/

var attributes_grid = null;
var options_grid = null;
var conditions_grid = null;
var sets_grid = null;
var frame_name;
var form_name;
var cancel_add_attribute = true;
var copying_attribute = false;
var cancel_add_set = true;
var include_overlay = false;
var inside_cms = false;
var taxable_attributes = false;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(attributes_grid,-1,new_height -
                   get_grid_offset(attributes_grid));
    else resize_grid(attributes_grid,new_width,new_height);
}

function resize_dialog(new_width,new_height)
{
    if (options_grid) {
       if (top.skin)
          resize_grid(options_grid,-1,new_height -
                      get_grid_offset(options_grid));
       else resize_grid(options_grid,new_width-120,new_height-100);
    }
    if (conditions_grid) {
       if (top.skin)
          resize_grid(conditions_grid,-1,new_height -
                      get_grid_offset(conditions_grid));
       else resize_grid(conditions_grid,new_width-120,new_height-100);
    }
    if (sets_grid) {
       if (top.skin)
          resize_grid(sets_grid,-1,new_height - get_grid_offset(sets_grid));
       else resize_grid(sets_grid,new_width-120,new_heigh);
    }
}

function load_grid()
{
   var grid_size = get_default_grid_size();
   var attr_columns = ['Id','Name','Display Name','Type','Admin Type',
                       'Sub-Product','Dynamic','Required','# Options'];
   if (taxable_attributes) attr_columns.push('Taxable');
   var attr_column_widths = [0,400,150,70,70,75,60,60,60];
   if (taxable_attributes) attr_column_widths.push(60);
   var query = 'select a.id,a.name,a.display_name,a.type,IFNULL(a.admin_type,a.type) ' +
               'as admin_type,a.sub_product,a.dynamic,' +
               'a.required,(select count(o.id) from attribute_options o ' +
               'where o.parent=a.id) as num_options';
   if (taxable_attributes) query += ',IFNULL(taxable,1)';
   query += ' from attributes a';
   attributes_grid = new Grid('attributes',grid_size.width,grid_size.height);
   attributes_grid.set_columns(attr_columns);
   attributes_grid.set_column_widths(attr_column_widths);
   attributes_grid.set_query(query);
   attributes_grid.set_order_by('name');
   attributes_grid.table.set_convert_cell_data(convert_attribute_data);
   attributes_grid.set_id('attributes_grid');
   attributes_grid.load(false);
   attributes_grid.set_double_click_function(edit_attribute);
   attributes_grid.display();
}

function reload_grid()
{
   attributes_grid.table.reset_data(false);
   attributes_grid.grid.refresh();
   window.setTimeout(function() { attributes_grid.table.restore_position(); },0);
}

function getElementByClass(tag_name,class_name)
{
   class_name = class_name.replace(/^\s+/,'').replace(/\s+$/,'');
   var page_tags = document.getElementsByTagName(tag_name);
   for (var loop = 0;  loop < page_tags.length;  loop++)
      if (page_tags[loop].className.replace(/^\s+/,'').replace(/\s+$/,'') == class_name)
         return page_tags[loop];
   return null;
}

function set_attribute_fields(visible_flag,attribute_type)
{
   var options_tab = document.getElementById('options_tab');
   if (! options_tab) options_tab = getElementByClass('table','options_tab');
   if (visible_flag) options_tab.style.display = '';
   else options_tab.style.display = 'none';
   resize_tabs();
   var default_row = document.getElementById('default_row');
   if (visible_flag || (attribute_type == 14))
      default_row.style.display = 'none';
   else default_row.style.display = '';
   var subproduct_row = document.getElementById('subproduct_row');
   if (visible_flag) subproduct_row.style.display = '';
   else subproduct_row.style.display = 'none';
   var dynamic_row = document.getElementById('dynamic_row');
   if (visible_flag || (attribute_type == 4) || (attribute_type == 5))
      dynamic_row.style.display = '';
   else dynamic_row.style.display = 'none';
   var required_row = document.getElementById('required_row');
   if (visible_flag) required_row.style.display = '';
   else required_row.style.display = 'none';
   var taxable_row = document.getElementById('taxable_row');
   if (taxable_row) {
      if (visible_flag) taxable_row.style.display = '';
      else taxable_row.style.display = 'none';
   }
   var width_row = document.getElementById('width_row');
   if (attribute_type == 4) width_row.style.display = '';
   else width_row.style.display = 'none';
   var height_row = document.getElementById('height_row');
   if (attribute_type == 4) height_row.style.display = '';
   else height_row.style.display = 'none';
   var length_row = document.getElementById('length_row');
   if (attribute_type == 4) length_row.style.display = '';
   else length_row.style.display = 'none';
   top.grow_current_dialog();
}

function select_attribute_type(type_field)
{
   var attribute_type = type_field.options[type_field.selectedIndex].value;
   if (((attribute_type < 3) || (attribute_type >= 8)) &&
       (attribute_type != 14)) var visible_flag = true;
   else var visible_flag = false;
   set_attribute_fields(visible_flag,attribute_type);
}

function attribute_fields_onload(form_name)
{
   var type_field = document.forms[form_name].type;
   var attribute_type = type_field.options[type_field.selectedIndex].value;
   if (((attribute_type > 2) && (attribute_type < 8)) ||
        (attribute_type == 14))
      set_attribute_fields(false,attribute_type);
}

function add_attribute()
{
   cancel_add_attribute = true;
   copying_attribute = false;
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('attributes.php','cmd=createattribute',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_attribute,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_attribute(ajax_request,data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var attribute_id = -1;
   eval(ajax_request.request.responseText);

   attributes_grid.table.save_position();
   if (include_overlay) var dialog_width = 940;
   else var dialog_width = 800;
   var url = '../cartengine/attributes.php?cmd=addattribute&id=' +
             attribute_id;
   if (copying_attribute) url += '&copy=true';
   top.create_dialog('add_attribute',null,null,dialog_width,420,
                     false,url,null);
}

function add_attribute_onclose(user_close)
{
   if (cancel_add_attribute) {
      var attribute_id = document.AddAttribute.id.value;
      call_ajax('attributes.php','cmd=deleteattribute&id=' + attribute_id,true);
   }
}

function add_attribute_onload()
{
   top.set_current_dialog_onclose(add_attribute_onclose);
   attribute_fields_onload('AddAttribute');
   images_onload();
}

function process_add_attribute()
{
   if (! validate_form_field(document.AddAttribute.name,'Name')) return;

   top.enable_current_dialog_progress(true);
   submit_form_data('attributes.php','cmd=processaddattribute',
                    document.AddAttribute,finish_add_attribute);
}

function finish_add_attribute(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      cancel_add_attribute = false;
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_attribute()
{
   if (attributes_grid.table._num_rows < 1) {
      alert('There are no attributes to edit');   return;
   }
   var grid_row = attributes_grid.grid.getCurrentRow();
   var id = attributes_grid.grid.getCellText(0,grid_row);
   attributes_grid.table.save_position();
   if (include_overlay) var dialog_width = 940;
   else var dialog_width = 800;
   top.create_dialog('edit_attribute',null,null,dialog_width,420,false,
                     '../cartengine/attributes.php?cmd=editattribute&id=' +
                     id,null);
}

function edit_attribute_onload()
{
   attribute_fields_onload('EditAttribute');
   images_onload();
}

function update_attribute()
{
   if (! validate_form_field(document.EditAttribute.name,'Name')) return;

   top.enable_current_dialog_progress(true);
   submit_form_data('attributes.php','cmd=updateattribute',
                    document.EditAttribute,finish_update_attribute);
}

function finish_update_attribute(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function copy_attribute()
{
   if (attributes_grid.table._num_rows < 1) {
      alert('There are no attributes to copy');   return;
   }
   var grid_row = attributes_grid.grid.getCurrentRow();
   var id = attributes_grid.grid.getCellText(0,grid_row);
   cancel_add_attribute = true;
   copying_attribute = true;
   top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('attributes.php','cmd=copyattribute&id=' + id,
                               true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_attribute,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function delete_attribute()
{
   if (attributes_grid.table._num_rows < 1) {
      alert('There are no attributes to delete');   return;
   }
   var grid_row = attributes_grid.grid.getCurrentRow();
   var id = attributes_grid.grid.getCellText(0,grid_row);
   var name = attributes_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete '+name+' attribute?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   attributes_grid.table.save_position();
   call_ajax('attributes.php','cmd=deleteattribute&id=' + id,true,
             finish_delete_attribute);
}

function finish_delete_attribute(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function init_options(frame,name)
{
   frame_name = frame;
   form_name = name;
}

function enable_option_buttons(enable_flag)
{
   var add_option_button = document.getElementById('add_option');
   var edit_option_button = document.getElementById('edit_option');
   var delete_option_button = document.getElementById('delete_option');
   var option_buttons_row = document.getElementById('option_buttons_row');
   var image_buttons_row = document.getElementById('image_buttons_row');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_option_button.style.display = display_style;
   edit_option_button.style.display = display_style;
   delete_option_button.style.display = display_style;
   option_buttons_row.style.display = display_style;
   image_buttons_row.style.display = display_style;
}

function create_options_grid(parent)
{
   var grid_size = get_default_grid_size();
   if (! top.skin) {
      grid_size.width -= 75;   grid_size.height -= 100;
   } 
   options_grid = new Grid('attribute_options',grid_size.width,
                           grid_size.height);
   if (include_overlay) {
      options_grid.set_columns(['Id','Seq','Name','Type','Adjustment',
                                'Default','Image','Overlay Image']);
      options_grid.set_column_widths([0,30,150,80,70,50,140,140]);
      var query = 'select id,sequence,name,adjust_type,adjustment,default_value,' +
                  '(select filename from images where parent_type=2 and ' +
                  'parent=o.id limit 1) as image,overlay_image from ' +
                  'attribute_options o';
   }
   else {
      options_grid.set_columns(['Id','Seq','Name','Type','Adjustment',
                                'Default','Image']);
      options_grid.set_column_widths([0,30,150,80,70,50,140]);
      var query = 'select id,sequence,name,adjust_type,adjustment,default_value,' +
                  '(select filename from images where parent_type=2 and ' +
                  'parent=o.id limit 1) as image from attribute_options o';
   }
   options_grid.set_query(query);
   if (parent == -1) var where = 'o.parent=-1';
   else var where = 'o.parent=' + parent;
   options_grid.set_where(where);
   query = 'select count(id) from attribute_options o';
   options_grid.set_info_query(query);
   options_grid.set_order_by('sequence');
   options_grid.table.set_convert_cell_data(convert_options_data);
   options_grid.set_id('options_grid');
   options_grid.load(false);
   options_grid.grid.onCurrentRowChanged = select_image;
   options_grid.set_double_click_function(edit_option);
   options_grid.display();
}

function add_option()
{
   var id = document.forms[form_name].id.value;
   var type = document.forms[form_name].type.selectedIndex;
   top.create_dialog('add_option',null,null,540,210,false,
                     '../cartengine/attributes.php?cmd=addoption&Parent=' + id +
                     '&Frame=' + frame_name + '&Attribute_Type=' + type,null);
}

function process_add_option()
{
   if (! validate_form_field(document.AddOption.name,'Option Name')) return;

   top.enable_current_dialog_progress(true);
   if (typeof(update_price_breaks) != 'undefined') update_price_breaks();
   submit_form_data('attributes.php','cmd=processaddoption',document.AddOption,
                    finish_add_option);
}

function finish_add_option(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.AddOption.Frame.value).contentWindow;
      iframe.options_grid.table.reset_data(false);
      iframe.options_grid.grid.refresh();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_option()
{
   if (options_grid.table._num_rows < 1) {
      alert('There are no options to edit');   return;
   }
   var grid_row = options_grid.grid.getCurrentRow();
   var id = options_grid.grid.getCellText(0,grid_row);
   var type = document.forms[form_name].type.selectedIndex;
   var height = 210;
   if (type == 10) height = 300;
   top.create_dialog('edit_option',null,null,540,height,false,
                     '../cartengine/attributes.php?cmd=editoption&id=' + id +
                     '&Frame=' + frame_name + '&Attribute_Type=' + type,null);
}

function update_option()
{
   if (! validate_form_field(document.EditOption.name,'Option Name')) return;

   top.enable_current_dialog_progress(true);
   if (typeof(update_price_breaks) != 'undefined') update_price_breaks();
   submit_form_data('attributes.php','cmd=updateoption',document.EditOption,
                    finish_update_option);
}

function finish_update_option(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.EditOption.Frame.value).contentWindow;
      iframe.options_grid.table.reset_data(false);
      iframe.options_grid.grid.refresh();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_option()
{
   if (options_grid.table._num_rows < 1) {
      alert('There are no options to delete');   return;
   }
   var grid_row = options_grid.grid.getCurrentRow();
   var id = options_grid.grid.getCellText(0,grid_row);
   var name = options_grid.grid.getCellText(2,grid_row);
   var response = confirm('Are you sure you want to delete the option "' +
                          name + '"?');
   if (! response) return;
   call_ajax('attributes.php','cmd=deleteoption&id=' + id,
             true,finish_delete_option);
}

function finish_delete_option(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) {
      options_grid.table.reset_data(false);
      options_grid.grid.refresh();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function enable_condition_buttons(enable_flag)
{
   var add_condition_button = document.getElementById('add_condition');
   var edit_condition_button = document.getElementById('edit_condition');
   var delete_condition_button = document.getElementById('delete_condition');
   var condition_buttons_row = document.getElementById('condition_buttons_row');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_condition_button.style.display = display_style;
   edit_condition_button.style.display = display_style;
   delete_condition_button.style.display = display_style;
   condition_buttons_row.style.display = display_style;
}

function create_conditions_grid(parent)
{
   var grid_size = get_default_grid_size();
   if (! top.skin) {
      grid_size.width -= 75;   grid_size.height -= 100;
   } 
   conditions_grid = new Grid('attribute_conditions',grid_size.width,
                              grid_size.height);
   conditions_grid.set_columns(['Id','Seq','If this option','Option','Then',
                                'Attribute']);
   conditions_grid.set_column_widths([0,30,100,100,100,100]);
   var query = 'select id,sequence,compare,value,action,(select name from ' +
               'attributes where id=target) as target from ' +
               'attribute_conditions';
   conditions_grid.set_query(query);
   if (parent == -1) var where = 'parent=-1';
   else var where = 'parent=' + parent;
   conditions_grid.set_where(where);
   query = 'select count(id) from attribute_conditions';
   conditions_grid.set_info_query(query);
   conditions_grid.set_order_by('sequence');
   conditions_grid.table.set_convert_cell_data(convert_conditions_data);
   conditions_grid.set_id('conditions_grid');
   conditions_grid.load(false);
   conditions_grid.set_double_click_function(edit_option);
   conditions_grid.display();
}

function add_condition()
{
   if (document.AddAttribute) {
      var id = document.AddAttribute.id.value;
      var frame_name = 'add_attribute';
   }
   else {
      var id = document.EditAttribute.id.value;
      var frame_name = 'edit_attribute';
   }
   var attr_type = get_selected_list_value('admin_type');
   top.create_dialog('add_condition',null,null,400,125,false,
                     '../cartengine/attributes.php?cmd=addcondition&Parent=' +
                     id + '&Frame=' + frame_name + '&Attribute_Type=' +
                     attr_type,null);
}

function process_add_condition()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('attributes.php','cmd=processaddcondition',
                    document.AddCondition,finish_add_condition);
}

function finish_add_condition(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.AddCondition.Frame.value).
                   contentWindow;
      iframe.conditions_grid.table.reset_data(false);
      iframe.conditions_grid.grid.refresh();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_condition()
{
   if (conditions_grid.table._num_rows < 1) {
      alert('There are no conditions to edit');   return;
   }
   var grid_row = conditions_grid.grid.getCurrentRow();
   var id = conditions_grid.grid.getCellText(0,grid_row);
   if (document.AddAttribute) var frame_name = 'add_attribute';
   else var frame_name = 'edit_attribute';
   var attr_type = get_selected_list_value('admin_type');
   top.create_dialog('edit_condition',null,null,400,125,false,
                     '../cartengine/attributes.php?cmd=editcondition&id=' +
                     id + '&Frame=' + frame_name + '&Attribute_Type=' +
                     attr_type,null);
}

function update_condition()
{
   top.enable_current_dialog_progress(true);
   submit_form_data('attributes.php','cmd=updatecondition',
                    document.EditCondition,finish_update_condition);
}

function finish_update_condition(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.EditCondition.Frame.value).
                   contentWindow;
      iframe.conditions_grid.table.reset_data(false);
      iframe.conditions_grid.grid.refresh();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_condition()
{
   if (conditions_grid.table._num_rows < 1) {
      alert('There are no conditions to delete');   return;
   }
   var grid_row = conditions_grid.grid.getCurrentRow();
   var id = conditions_grid.grid.getCellText(0,grid_row);
   var name = conditions_grid.grid.getCellText(2,grid_row);
   var response = confirm('Are you sure you want to delete the ' +
                          'selected condition?');
   if (! response) return;
   call_ajax('attributes.php','cmd=deletecondition&id=' + id,
             true,finish_delete_condition);
}

function finish_delete_condition(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) {
      conditions_grid.table.reset_data(false);
      conditions_grid.grid.refresh();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_tab(tab,content_id)
{
   if (content_id == 'options_content') {
      enable_option_buttons(true);   enable_image_buttons(true);
      enable_condition_buttons(false);
      if (top.skin && (navigator.userAgent.indexOf('Chrome') != -1) &&
          (document.compatMode == 'BackCompat'))
         resize_grid(options_grid,-1,get_dialog_body_height() -
                     get_grid_offset(options_grid));
   }
   else if (content_id == 'conditions_content') {
      enable_option_buttons(false);   enable_image_buttons(false);
      enable_condition_buttons(true);
      if (top.skin && (navigator.userAgent.indexOf('Chrome') != -1) &&
          (document.compatMode == 'BackCompat'))
         resize_grid(conditions_grid,-1,get_dialog_body_height() -
                     get_grid_offset(conditions_grid));
   }
   else {
      enable_option_buttons(false);   enable_image_buttons(false);
      enable_condition_buttons(false);
   }
   tab_click(tab,content_id);
   top.grow_current_dialog();
   if (content_id == 'options_content') options_grid.resize_column_headers();
   else if (content_id == 'conditions_content')
      conditions_grid.resize_column_headers();
   resize_tabs();
}

function change_option_tab(tab,content_id)
{
   if (content_id == 'pricebreaks_content') enable_price_break_buttons(true);
   else enable_price_break_buttons(false);
   tab_click(tab,content_id);
   top.grow_current_dialog();
   resize_tabs();
}

function select_adjust_type(type_field)
{
   var adjust_type = type_field.options[type_field.selectedIndex].value;
   if (adjust_type == 4) var visible_flag = true;
   else var visible_flag = false;
   if (top.skin) var option_tab = document.getElementById('option_tab');
   else var option_tab = document.getElementById('option_tab_cell');
   if (visible_flag) option_tab.style.display = '';
   else option_tab.style.display = 'none';
   if (top.skin)
      var pricebreaks_tab = document.getElementById('pricebreaks_tab');
   else var pricebreaks_tab = document.getElementById('pricebreaks_tab_cell');
   if (visible_flag) pricebreaks_tab.style.display = '';
   else pricebreaks_tab.style.display = 'none';
   top.grow_current_dialog();
}

var type_values = ['Choice List','Radio','Check Box','File','Text Area',
                   'Custom','Start Group','End Group','Hidden',
                   'Multiple Fields','Color','Image','Buttons','Buttons',
                   'Designer'];
var admin_type_values = ['Choice List','Radio','Check Box','File','Text Area',
                   'Custom','Edit Field','Skip','Designer'];

function convert_attribute_data(col,row,text)
{
   if ((col == 3) || (col == 4)) {
      var type = parse_int(text);
      if (col == 3) {
         if (typeof(type_values[type]) == 'undefined') return text;
         return type_values[type];
      }
      else if (col == 4) {
         if (typeof(admin_type_values[type]) == 'undefined') return text;
         return admin_type_values[type];
      }
   }
   if ((col == 5) || (col == 6) || (col == 7) || (col == 9)) {
      if (parse_int(text) == 1) return 'Yes';
      else return 'No';
   }
   return text;
}

function search_attributes()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status('Search','Searching Attributes...',350,100,null);
   window.setTimeout(function() {
      var where = "name like '%" + query + "%' or display_name like '%" +
                  query + "%' or description like '%" + query + "%'";
      attributes_grid.set_where(where);
      attributes_grid.table.reset_data(false);
      attributes_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All Attributes...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      attributes_grid.set_where('');
      attributes_grid.table.reset_data(false);
      attributes_grid.grid.refresh();
      top.remove_status();
   },0);
}

var option_type_values = ['Fixed','Percentage','Start Group','End Group',
                          'Price Breaks'];

function convert_options_data(col,row,text)
{
   if (col == 3) return option_type_values[parse_int(text)];
   if (col == 5) {
      if (parse_int(text) == 1) return 'Yes';
      else return 'No';
   }
   return text;
}

function resequence_options(old_row,new_row)
{
   if (old_row == new_row) return true;
   var id = document.forms[form_name].id.value;
   var old_sequence = options_grid.grid.getCellText(1,old_row);
   var new_sequence = options_grid.grid.getCellText(1,new_row);
   top.enable_current_dialog_progress(true);
   call_ajax('attributes.php','cmd=resequenceoptions&Parent=' + id +
             '&OldSequence=' + old_sequence + '&NewSequence=' + new_sequence,true,
             function (ajax_request) {
                finish_resequence_options(ajax_request,new_row);
             });
}

function finish_resequence_options(ajax_request,new_row)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status != 201) {
      if ((status >= 200) && (status < 300)) ajax_request.display_error();
      return false;
   }
   options_grid.table.reset_data(false);
   options_grid.grid.refresh(); 
   options_grid.grid.setSelectedRows([new_row]);
   options_grid.grid.setCurrentRow(new_row);
   return true;
}

function move_option_top()
{
   if (options_grid.table._num_rows < 1) return;
   var grid_row = parse_int(options_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_options(grid_row,0);
}

function move_option_up()
{
   if (options_grid.table._num_rows < 1) return;
   var grid_row = parse_int(options_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_options(grid_row,grid_row - 1);
}

function move_option_down()
{
   var num_rows = options_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(options_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_options(grid_row,grid_row + 1);
}

function move_option_bottom()
{
   var num_rows = options_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(options_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_options(grid_row,num_rows - 1);
}

var compare_values = ['equals','not equals','greater than','less than'];
var action_values = ['show','hide'];

function convert_conditions_data(col,row,text)
{
   if (col == 2) return compare_values[parse_int(text)];
   if (col == 3) {
      var attr_type = get_selected_list_value('admin_type');
      if (attr_type != 0) return text;
      var option_id = parse_int(text);
      for (var loop = 0;  loop < options_grid.table._num_rows;  loop++) {
         if (options_grid.grid.getCellText(0,loop) == option_id)
            return options_grid.grid.getCellText(2,loop);
      }
      return text;
   }
   if (col == 4) return action_values[parse_int(text)];
   return text;
}

function resequence_conditions(old_row,new_row)
{
   if (old_row == new_row) return true;
   var id = document.forms[form_name].id.value;
   var old_sequence = conditions_grid.grid.getCellText(1,old_row);
   var new_sequence = conditions_grid.grid.getCellText(1,new_row);
   top.enable_current_dialog_progress(true);
   call_ajax('attributes.php','cmd=resequenceconditions&Parent=' + id +
             '&OldSequence=' + old_sequence + '&NewSequence=' + new_sequence,true,
             function (ajax_request) {
                finish_resequence_conditions(ajax_request,new_row);
             });
}

function finish_resequence_conditions(ajax_request,new_row)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status != 201) {
      if ((status >= 200) && (status < 300)) ajax_request.display_error();
      return false;
   }
   conditions_grid.table.reset_data(false);
   conditions_grid.grid.refresh(); 
   conditions_grid.grid.setSelectedRows([new_row]);
   conditions_grid.grid.setCurrentRow(new_row);
   return true;
}

function move_condition_top()
{
   if (conditions_grid.table._num_rows < 1) return;
   var grid_row = parse_int(conditions_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_conditions(grid_row,0);
}

function move_condition_up()
{
   if (conditions_grid.table._num_rows < 1) return;
   var grid_row = parse_int(conditions_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_conditions(grid_row,grid_row - 1);
}

function move_condition_down()
{
   var num_rows = conditions_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(conditions_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_conditions(grid_row,grid_row + 1);
}

function move_condition_bottom()
{
   var num_rows = conditions_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(conditions_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_conditions(grid_row,num_rows - 1);
}

function picker_show_delay()
{
   var dialog =  top.get_current_dialog_object();
   if (picker.container.offsetHeight==0) {
      dialog.contentarea.firstChild.scrolling = 'no';
      setTimeout(picker_show_delay,100);
      return false;
   }
   dialog.contentarea.firstChild.scrolling = '';
   var width = dialog.width;
   var height = dialog.height;
   var data = document.getElementById('__data');
   picker.container.style.top = '10px';
   var left = getOffset(data).left+30;
   picker.container.style.left = left+'px';
   var pickerBottom = picker.container.offsetHeight + (10);
   var pickerRight = picker.container.offsetWidth + (left);
   if (height < pickerBottom) height = pickerBottom;
   if (width < pickerRight) width = pickerRight;
   top.resize_dialog(dialog,width,height);
}

function bundles()
{
   top.create_dialog('bundles',null,null,650,300,false,'bundles.php',null);
}

function attribute_sets()
{
   top.create_dialog('attribute_sets',null,null,530,300,false,
                     '../cartengine/attributes.php?cmd=attributesets',null);
}

function create_sets_grid()
{
   var grid_size = get_default_grid_size();
   sets_grid = new Grid('attribute_sets',grid_size.width,grid_size.height);
   sets_grid.set_columns(['Id','Name','# Attributes']);
   sets_grid.set_column_widths([0,300,75]);
   var query = 'select s.id,s.name,(select count(a.id) from ' +
               'attribute_set_attributes a where a.parent=s.id) as ' +
               'num_attrs from attribute_sets s';
   sets_grid.set_query(query);
   sets_grid.set_order_by('name');
   sets_grid.set_id('sets_grid');
   sets_grid.load(true);
   sets_grid.set_double_click_function(edit_set);
   sets_grid.display();
}

function reload_sets_grid()
{
   sets_grid.table.reset_data(true);
   sets_grid.grid.refresh();
   window.setTimeout(function() { sets_grid.table.restore_position(); },0);
}

function add_set()
{
   cancel_add_attribute_set = true;
   var ajax_request = new Ajax('attributes.php','cmd=createset',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_set,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_set(ajax_request,data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var set_id = -1;
   eval(ajax_request.request.responseText);

   sets_grid.table.save_position();
   var url = '../cartengine/attributes.php?cmd=addset&id=' +
             set_id;
   top.create_dialog('add_set',null,null,780,600,false,url,null);
}

function add_set_onclose(user_close)
{
   if (cancel_add_set) {
      var set_id = document.AddSet.id.value;
      call_ajax('attributes.php','cmd=deleteset&id=' + set_id,true);
   }
}

function add_set_onload()
{
   top.set_current_dialog_onclose(add_set_onclose);
}

function process_add_set()
{
   if (! validate_form_field(document.AddSet.name,'Name')) return;

   top.enable_current_dialog_progress(true);
   submit_form_data('attributes.php','cmd=processaddset',document.AddSet,
                    finish_add_set);
}

function finish_add_set(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_dialog_frame('attribute_sets').contentWindow.reload_sets_grid();
      cancel_add_set = false;
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_set()
{
    if (sets_grid.table._num_rows < 1) {
       alert('There are no attribute sets to edit');   return;
    }
    var grid_row = sets_grid.grid.getCurrentRow();
    var id = sets_grid.grid.getCellText(0,grid_row);
    sets_grid.table.save_position();
    top.create_dialog('edit_set',null,null,780,600,false,
                      '../cartengine/attributes.php?cmd=editset&id=' + id,
                      null);
}

function update_set()
{
    if (! validate_form_field(document.EditSet.name,'Name')) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('attributes.php','cmd=updateset',document.EditSet,
                      finish_update_set);
}

function finish_update_set(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('attribute_sets').contentWindow.reload_sets_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_set()
{
    if (sets_grid.table._num_rows < 1) {
       alert('There are no attribute sets to delete');   return;
    }
    var grid_row = sets_grid.grid.getCurrentRow();
    var id = sets_grid.grid.getCellText(0,grid_row);
    var name = sets_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the "' + name +
                           '" attribute set?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    sets_grid.table.save_position();
    call_ajax('attributes.php','cmd=deleteset&id=' + id,true,
              finish_delete_set);
}

function finish_delete_set(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_sets_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function select_set()
{
    if (sets_grid.table._num_rows < 1) {
       alert('There are no attribute sets to select');   return;
    }
    var grid_row = sets_grid.grid.getCurrentRow();
    var id = sets_grid.grid.getCellText(0,grid_row);
    var product_id = document.SelectSet.Product.value;
    var url = 'cmd=addattributeset&Set=' + id + '&Product=' + product_id;
    call_ajax('products.php',url,true,finish_select_set);
}

function finish_select_set(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var frame = document.SelectSet.Frame.value;
       top.get_dialog_frame(frame).contentWindow.attribute_list.
          reload_left_sublist_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}
