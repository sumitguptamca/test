/*
          Inroads Shopping Cart - Admin Tab - Cart Config JavaScript Functions

                           Written 2008-2019 by Randall Severy
                            Copyright 2008-2019 Inroads, LLC
*/

var options_grid;
var countries_grid;
var states_grid;
var update_finish_function = null;

function cart_config()
{
   top.create_dialog('cart_config',null,null,810,680,false,
                     '../cartengine/admin.php?cmd=cartconfig',null);
}

function cart_config_onload()
{
   var start_field = document.CartConfig.Start;
   var end_field = document.CartConfig.End;
   if ((! start_field) || (! end_field) || (start_field.value != '$Start$') ||
       (end_field.value != '$End$')) {
      alert('Cart Config dialog did not finish loading properly, please Cancel and open the dialog again');
      return;
   }
}

function update_cart_config()
{
   var start_field = document.CartConfig.Start;
   var end_field = document.CartConfig.End;
   if ((! start_field) || (! end_field) || (start_field.value != '$Start$') ||
       (end_field.value != '$End$')) {
      alert('Cart Config dialog did not finish loading properly, please Cancel and open the dialog again');
      return;
   }
   top.enable_current_dialog_progress(true);
   submit_form_data("admin.php","cmd=updatecartconfig",document.CartConfig,
                    finish_update_cart_config);
}

function finish_update_cart_config(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) {
      if (options_grid) options_grid.table.process_updates(false);
      if (countries_grid) countries_grid.table.process_updates(false);
      if (states_grid) states_grid.table.process_updates(false);
   }
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      if (update_finish_function) {
         update_finish_function();   update_finish_function = null;
      }
      else top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function enable_cart_option_buttons(enable_flag)
{
   var add_cart_option_button = document.getElementById('add_cart_option');
   var edit_cart_option_button = document.getElementById('edit_cart_option');
   var delete_cart_option_button = document.getElementById('delete_cart_option');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_cart_option_button.style.display = display_style;
   edit_cart_option_button.style.display = display_style;
   delete_cart_option_button.style.display = display_style;
}

function enable_country_buttons(enable_flag)
{
   var add_country_button = document.getElementById('add_country');
   var edit_country_button = document.getElementById('edit_country');
   var delete_country_button = document.getElementById('delete_country');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_country_button.style.display = display_style;
   edit_country_button.style.display = display_style;
   delete_country_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
   if (content_id == 'options_content') enable_cart_option_buttons(true);
   else enable_cart_option_buttons(false);
   if (content_id == 'countries_content') enable_country_buttons(true);
   else enable_country_buttons(false);
   tab_click(tab,content_id);
   top.grow_current_dialog();
}

function change_subtab(tab,content_id)
{
   subtab_click(tab,content_id);
   top.grow_current_dialog();
}

function update_table_flags(table,field_name,field_label,flag)
{
   var prompt = 'Are you sure you want to ';
   if (flag) prompt += 'check';
   else prompt += 'uncheck';
   prompt += ' the '+field_label+' flag in all ';
   if (table == 'product') prompt += 'products?';
   else prompt += 'inventory records?';
   var response = confirm(prompt);
   if (! response) return;
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=updatetableflags&Table='+table+'&Field='+field_name +
                '&Flag='+flag;
   call_ajax('admin.php',fields,false,finish_update_table_flags);
}

function finish_update_table_flags(ajax_request)
{
   ajax_request.enable_parse_response();
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) alert('Flags Updated');
   else ajax_request.display_error();
}

function create_options_grid(num_tables,num_chars,num_rows)
{
    if (top.skin) var grid_height = 38 + (num_rows * 23);
    else var grid_height = 24 + (num_rows * 18);
    var list_height = (num_tables * 17);
    var max_height = 575 - list_height;
    if (grid_height > max_height) grid_height = max_height;
    var column_width = (num_chars * 6);
    var grid_width = 60 + column_width + 30;
    if (grid_width > 450) grid_width = 450;
    options_grid = new Grid("cart_options",grid_width,grid_height);
    options_grid.set_columns(["Id","Label","Seq"]);
    options_grid.set_field_names(["id","label","sequence"]);
    options_grid.set_column_widths([30,column_width,30]);
    var query = "select id,label,sequence from cart_options";
    options_grid.set_query(query);
    options_grid.set_where("table_id=0");
    options_grid.set_order_by("sequence,id");
    options_grid.set_id('options_grid');
    options_grid.table._url = "admin.php";
    options_grid.table.add_update_parameter("cmd","updatecartoption");
    options_grid.table.add_update_parameter("table_id","0");
    options_grid.load(true);
    options_grid.grid.setSelectedRows([]);
    options_grid.grid.setCellEditable(true,1);
    options_grid.grid.setCellEditable(true,2);
    options_grid.grid.setSelectionMode("single-cell");
    options_grid.grid.setVirtualMode(false);
    set_grid_navigation(options_grid.grid);
    options_grid.set_double_click_function(edit_cart_option);
    options_grid.display();
}

function change_option_table()
{
   options_grid.table.process_updates(false);
   var option_table_field = document.CartConfig.OptionTable;
   var option_table = option_table_field.options[option_table_field.selectedIndex].value;
   options_grid.set_where("table_id=" + option_table);
   options_grid.table.add_update_parameter("table_id",option_table);
   options_grid.table.reset_data(true);
   options_grid.grid.refresh();
}

function add_cart_option()
{
   var option_table_field = document.CartConfig.OptionTable;
   var option_table = option_table_field.options[option_table_field.selectedIndex].value;
   top.create_dialog('add_cart_option',null,null,450,100,false,
                     '../cartengine/admin.php?cmd=addcartoption&table=' +
                     option_table,null);
}

function process_add_cart_option()
{
   if (! validate_form_field(document.AddCartOption.id,"Id")) return;
   if (! validate_form_field(document.AddCartOption.label,"Label")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("admin.php","cmd=processaddcartoption",document.AddCartOption,
                    finish_add_cart_option);
}

function finish_add_cart_option(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.options_grid.table.reset_data(true);
      iframe.options_grid.grid.refresh();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function edit_cart_option()
{
   if (options_grid.table._num_rows < 1) {
      alert('There are no cart options to edit');   return;
   }
   var option_table_field = document.CartConfig.OptionTable;
   var option_table = option_table_field.options[option_table_field.selectedIndex].value;
   var grid_row = options_grid.grid.getCurrentRow();
   var id = options_grid.grid.getCellText(0,grid_row);
   top.create_dialog('edit_cart_option',null,null,450,100,false,
                     '../cartengine/admin.php?cmd=editcartoption&table=' +
                     option_table+'&id=' + id,null);
}

function update_cart_option()
{
   if (! validate_form_field(document.EditCartOption.label,"Label")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("admin.php","cmd=updatecartoption",document.EditCartOption,
                    finish_update_cart_option);
}

function finish_update_cart_option(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.options_grid.table.reset_data(true);
      iframe.options_grid.grid.refresh();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function delete_cart_option()
{
   if (options_grid.table._num_rows < 1) {
      alert('There are no cart options to delete');   return;
   }
   var option_table_field = document.CartConfig.OptionTable;
   var option_table = option_table_field.options[option_table_field.selectedIndex].value;
   var grid_row = options_grid.grid.getCurrentRow();
   var id = options_grid.grid.getCellText(0,grid_row);
   var label = options_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete the '+label+' option?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   call_ajax("admin.php","cmd=deletecartoption&table="+option_table+"&id=" +
             id,false,finish_delete_cart_option);
}

function finish_delete_cart_option(ajax_request)
{
   ajax_request.enable_parse_response();
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.options_grid.table.reset_data(true);
      iframe.options_grid.grid.refresh();
   }
   else ajax_request.display_error();
}

function create_countries_grid()
{
   if (top.skin) countries_grid = new Grid("countries",-100,550);
   else countries_grid = new Grid("countries",510,550);
   countries_grid.set_columns(["Id","Country","Code","Handling","Available",
      "Billing","Shipping"]);
   countries_grid.set_column_widths([30,265,40,55,0,50,50]);
   var query = "select id,country,code,handling,available,available as " +
               "billing,available as shipping from countries";
   countries_grid.set_query(query);
   countries_grid.set_order_by("id");
   countries_grid.set_id('countries_grid');
   countries_grid.table.set_convert_cell_data(convert_country_data);
   countries_grid.table.set_convert_cell_update(convert_country_update);
   countries_grid.table._url = "admin.php";
   countries_grid.table.add_update_parameter("cmd","updatecountry");
   countries_grid.load(true);
   var checkbox_template = new AW.Templates.Checkbox;
   var checkbox = new AW.HTML.SPAN;
   checkbox.setContent("html",function() { return ''; });
   checkbox_template.setContent("box/text",checkbox);
   countries_grid.grid.setCellTemplate(checkbox_template,5);
   countries_grid.grid.setCellTemplate(checkbox_template,6);
   countries_grid.set_double_click_function(edit_country);
   countries_grid.display();
}

function add_country()
{
   top.create_dialog('add_country',null,null,450,180,false,
                     '../cartengine/admin.php?cmd=addcountry',null);
}

function process_add_country()
{
   if (! validate_form_field(document.AddCountry.country,"Country")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("admin.php","cmd=processaddcountry",document.AddCountry,
                    finish_add_country);
}

function finish_add_country(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.countries_grid.table.reset_data(true);
      iframe.countries_grid.grid.refresh();
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function edit_country()
{
   if (countries_grid.table._num_rows < 1) {
      alert('There are no countries to edit');   return;
   }
   var grid_row = countries_grid.grid.getCurrentRow();
   var id = countries_grid.grid.getCellText(0,grid_row);
   top.create_dialog('edit_country',null,null,450,180,false,
                     '../cartengine/admin.php?cmd=editcountry&id=' + id,null);
}

function update_country()
{
   if (! validate_form_field(document.EditCountry.country,"Country")) return;

   top.enable_current_dialog_progress(true);
   submit_form_data("admin.php","cmd=updatecountry",document.EditCountry,
                    finish_update_country);
}

function finish_update_country(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 200) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.countries_grid.table.reset_data(true);
      iframe.countries_grid.grid.refresh();
      top.close_current_dialog();
   }
}

function delete_country()
{
   if (countries_grid.table._num_rows < 1) {
      alert('There are no countries to delete');   return;
   }
   var grid_row = countries_grid.grid.getCurrentRow();
   var id = countries_grid.grid.getCellText(0,grid_row);
   var country = countries_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete '+country+'?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   call_ajax("admin.php","cmd=deletecountry&id=" + id,false,
             finish_delete_country);
}

function finish_delete_country(ajax_request)
{
   ajax_request.enable_parse_response();
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame('cart_config').contentWindow;
      iframe.countries_grid.table.reset_data(true);
      iframe.countries_grid.grid.refresh();
   }
   else ajax_request.display_error();
}

function convert_country_data(col,row,text)
{
   if (col == 5) {
      if (parse_int(text) & 1) return true;
      else return false;
   }
   if (col == 6) {
      if (parse_int(text) & 2) return true;
      else return false;
   }
   return text;
}

function convert_country_update(col,row,value)
{
   if (col == 5) {
      var new_value = 0;
      if (value) new_value |= 1;
      if (countries_grid.grid.getCellValue(6,row)) new_value |= 2;
      if (! countries_grid.table._updates[row]) countries_grid.table._updates[row] = [];
      countries_grid.table._updates[row][4] = new_value;
      return new_value;
   }
   if (col == 6) {
      var new_value = 0;
      if (countries_grid.grid.getCellValue(5,row)) new_value |= 1;
      if (value) new_value |= 2;
      if (! countries_grid.table._updates[row]) countries_grid.table._updates[row] = [];
      countries_grid.table._updates[row][4] = new_value;
      return new_value;
   }
   return value;
}

function check_all_countries(col)
{
   var num_rows = countries_grid.table._num_rows;
   for (var row = 0;  row < num_rows;  row++) {
      countries_grid.grid.setCellValue(true,col,row);
      convert_country_update(col,row,true);
   }
}

function uncheck_all_countries(col)
{
   var num_rows = countries_grid.table._num_rows;
   for (var row = 0;  row < num_rows;  row++) {
      countries_grid.grid.setCellValue(false,col,row);
      convert_country_update(col,row,false);
   }
}

function create_states_grid()
{
   if (top.skin) states_grid = new Grid("states",-100,550);
   else states_grid = new Grid("states",470,550);
   states_grid.set_columns(["Name","Code","Tax Rate (%)","Handling",
      "Available","Billing","Shipping"]);
   states_grid.set_column_widths([180,35,80,55,0,50,50]);
   states_grid.set_field_names(["name","code","tax","handling","available"]);
   var query = "select name,code,tax,handling,available,available as " +
               "billing,available as shipping from states";
   states_grid.set_query(query);
   states_grid.set_order_by("name");
   states_grid.set_id('states_grid');
   states_grid.table.set_convert_cell_data(convert_states_data);
   states_grid.table.set_convert_cell_update(convert_states_update);
   states_grid.table._url = "admin.php";
   states_grid.table.add_update_parameter("cmd","updatestate");
   states_grid.load(true);
   states_grid.grid.setSelectedRows([]);
   states_grid.grid.setCellEditable(true,2);
   states_grid.grid.setCellEditable(true,3);
   states_grid.grid.setSelectionMode("single-cell");
   states_grid.grid.setVirtualMode(false);
   var checkbox_template = new AW.Templates.Checkbox;
   var checkbox = new AW.HTML.SPAN;
   checkbox.setContent("html",function() { return ''; });
   checkbox_template.setContent("box/text",checkbox);
   states_grid.grid.setCellTemplate(checkbox_template,5);
   states_grid.grid.setCellTemplate(checkbox_template,6);
   set_grid_navigation(states_grid.grid);
   states_grid.display();
}

function convert_states_data(col,row,text)
{
   if (col == 5) {
      if (parse_int(text) & 1) return true;
      else return false;
   }
   if (col == 6) {
      if (parse_int(text) & 2) return true;
      else return false;
   }

   return text;
}

function convert_states_update(col,row,value)
{
   if (col == 5) {
      var new_value = 0;
      if (value) new_value |= 1;
      if (! states_grid.table._updates[row]) states_grid.table._updates[row] = [];
      if (states_grid.grid.getCellValue(6,row)) new_value |= 2;
      states_grid.table._updates[row][4] = new_value;
      return new_value;
   }
   if (col == 6) {
      var new_value = 0;
      if (states_grid.grid.getCellValue(5,row)) new_value |= 1;
      if (value) new_value |= 2;
      if (! states_grid.table._updates[row]) states_grid.table._updates[row] = [];
      states_grid.table._updates[row][4] = new_value;
      return new_value;
   }
   return value;
}

function check_all_states(col)
{
   var num_rows = states_grid.table._num_rows;
   for (var row = 0;  row < num_rows;  row++) {
      states_grid.grid.setCellValue(true,col,row);
      convert_states_update(col,row,true);
   }
}

function uncheck_all_states(col)
{
   var num_rows = states_grid.table._num_rows;
   for (var row = 0;  row < num_rows;  row++) {
      states_grid.grid.setCellValue(false,col,row);
      convert_states_update(col,row,false);
   }
}

function contiguous_us_only()
{
   var skip_states = ['AK','AS','FM','GU','HI','MH','MP','PW','PR','VI'];
   var num_rows = states_grid.table._num_rows;
   for (var row = 0;  row < num_rows;  row++) {
      var code = states_grid.grid.getCellValue(1,row);
      if (skip_states.indexOf(code) != -1) var state = false;
      else var state = true;
      states_grid.grid.setCellValue(state,5,row);
      states_grid.grid.setCellValue(state,6,row);
      convert_states_update(5,row,state);
      convert_states_update(6,row,state);
   }
}

function init_seo_urls()
{
    var response = confirm('All empty SEO URLs will be filled in with ' +
                           'default values.  Do you wish to continue?');
    if (! response) return;
    top.display_status('Initializing SEO',
                       'Initializing SEO URLs, Please Wait...',
                       600,100,null);
    call_ajax('admin.php','cmd=initseo',true,finish_init_seo_urls,600);
}

function finish_init_seo_urls(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
}

function rebuild_htaccess()
{
    var response = confirm('All SEO Rewrite Rules will be rebuilt.  ' +
                           'Do you wish to continue?');
    if (! response) return;
    top.display_status('Rebuilding Rewrites',
                       'Rebuilding SEO Rewrite Rules, Please Wait...',
                       600,100,null);
    call_ajax('admin.php','cmd=rebuildhtaccess',true,finish_rebuild_htaccess);
}

function finish_rebuild_htaccess(ajax_request)
{
    var status = ajax_request.get_status();
    top.remove_status();
}

