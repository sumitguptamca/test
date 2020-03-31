/*
          Inroads Shopping Cart - Categories Tab JavaScript Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

var categories_grid = null;
var cancel_add_category = true;
var update_window = '';
var rebuild_web_site = false;
var script_prefix = '';
var products_label;
var products_script_name;
var url_prefix = '';
var category_label;
var categories_label;
var subcategories_label;
var default_base_href = '';
var base_url = '';
var admin_path;
var inside_cms = false;
var dialog_title;
var categories_table;
var subcategories_table;
var category_products_table;
var script_name;
var subcategories = null;
var parentcategories = null;
var products = null;
var category_type_column = -1;
var product_source_column = -1;
var num_products_column = 3;
var num_categories_column = 4;
var status_column = 5;
var enable_multisite = false;
var enable_category_filter_search = false;
var filter_where = '';

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(categories_grid,-1,new_height - get_grid_offset(categories_grid));
    else resize_grid(categories_grid,new_width,new_height)
}

function resize_dialog(new_width,new_height)
{
    if (top.skin) {
       if (subcategories) subcategories.resize(true);
       if (parentcategories) parentcategories.resize(true);
       if (products) products.resize(true);
    }
}

function load_grid()
{
   var grid_size = get_default_grid_size();
   categories_grid = new Grid(categories_table,grid_size.width,grid_size.height);
   if (typeof(category_columns) == 'undefined') {
      category_columns = ['Id',category_label + ' Name','Display Name'];
      if (typeof(category_types) != 'undefined')
         category_columns.push('Type');
      if (enable_category_filter_search)
         category_columns.push('Prod Src');
      category_columns.push.apply(category_columns,[products_label,
                                  subcategories_label,'Status','URL Alias']);
      if (enable_multisite) category_columns.push('Web Sites');
   }
   categories_grid.set_columns(category_columns);
   if (typeof(category_column_widths) == 'undefined') {
      category_column_widths = [0,400,300];
      if (typeof(category_types) != 'undefined')
         category_column_widths.push(100);
      if (enable_category_filter_search)
         category_column_widths.push(60);
      category_column_widths.push.apply(category_column_widths,[60,80,115,150]);
      if (enable_multisite) category_column_widths.push(0);
   }
   categories_grid.set_column_widths(category_column_widths);
   if (typeof(category_types) != 'undefined') {
      category_type_column = 3;   num_products_column++;
      num_categories_column++;   status_column++;
   }
   if (enable_category_filter_search) {
      if (category_type_column != -1) product_source_column = 4;
      else product_source_column = 3;
      num_products_column++;   num_categories_column++;   status_column++;
   }
   if (typeof(category_query) != 'undefined') var query = category_query;
   else {
      var query = 'select c.id,c.name,c.display_name,';
      if (typeof(category_types) != 'undefined') query += 'c.category_type,';
      if (enable_category_filter_search) query += 'c.products_source,';
      query += '(select count(id) from '+category_products_table +
               ' where parent=c.id) as num_products,(select count(id) from ' +
               subcategories_table+' where parent=c.id) ' +
               'as num_categories,c.status,c.seo_url';
      if (enable_multisite) query += ',websites';
      query += ' from '+categories_table+' c';
   }
   categories_grid.set_query(query);
   if (typeof(category_info_query) != 'undefined') query = category_info_query;
   else query = 'select count(c.id) from '+categories_table+' c';
   categories_grid.set_info_query(query);
   if ((typeof(top.website) != 'undefined') && (top.website != 0)) {
      var where = 'find_in_set("' + top.website + '",websites)';
      categories_grid.set_where(where);
   }
   categories_grid.set_order_by('c.name');
   if (typeof(convert_category_data) != 'undefined')
      categories_grid.table.set_convert_cell_data(convert_category_data);
   else categories_grid.table.set_convert_cell_data(convert_data);
   categories_grid.set_id('categories_grid');
   categories_grid.load(false);
   categories_grid.set_double_click_function(edit_category);
   categories_grid.display();
}

function reload_grid()
{
   categories_grid.table.reset_data(false);
   categories_grid.grid.refresh();
   window.setTimeout(function() { categories_grid.table.restore_position(); },0);
}

function add_category(sublist)
{
   cancel_add_category = true;
   top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = '';
   var ajax_request = new Ajax(url + script_name,'cmd=createcategory',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   if (typeof(sublist) != 'undefined') var ajax_data = sublist;
   else var ajax_data = null;
   ajax_request.set_callback_function(continue_add_category,ajax_data);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_category(ajax_request,sublist)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var category_id = -1;
   eval(ajax_request.request.responseText);

   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=addcategory&id=' + category_id;
   if (sublist) {
      if (update_window == '') update_window = sublist.frame_name;
      var dialog_name = 'add_category_' + (new Date()).getTime();
      url += '&frame='+dialog_name+'&sublist='+sublist.name +
             '&side=both&updatewindow='+update_window;
   }
   else var dialog_name = 'add_category';
   if (categories_grid) categories_grid.table.save_position();
   if (top.skin) var dialog_width = 1000;
   else var dialog_width = 900;
   top.create_dialog(dialog_name,null,null,dialog_width,535,false,url,null);
}

function category_dialog_onclose(user_close)
{
   top.num_dialogs--;
}

function add_category_onclose(user_close)
{
   if (cancel_add_category) {
      var category_id = document.AddCategory.id.value;
      call_ajax(script_name,'cmd=deletecategory&id=' + category_id,true);
   }
   if (inside_cms && (cms_top() != top))
      category_dialog_onclose(user_close);
}

function category_onload()
{
   if (inside_cms && (cms_top() != top)) {
      var cms = cms_top();
      cms.dialog_onload(document,window);
      var dialog_window = cms.dialog_windows[cms.num_dialogs - 1];
      var dialog_name = cms.dialog_names[cms.num_dialogs - 1];
      top.dialog_windows[top.num_dialogs] = dialog_window;
      top.dialog_names[top.num_dialogs] = dialog_name;
      top.num_dialogs++;
      top.set_dialog_title(dialog_name,dialog_title);
      top.dialog_onload(document,window);
      top.set_current_dialog_onclose(category_dialog_onclose);
   }
   if (typeof(custom_category_onload) != 'undefined') custom_category_onload();
   if (top.use_cached_dialogs && (! inside_cms) && document.EditCategory)
      top.cache_current_dialog();
}

function close_category_dialog()
{
   if (inside_cms && (cms_top() != top))
      cms_top().close_current_dialog();
   else if (top.use_cached_dialogs && document.EditCategory)
      top.hide_current_dialog();
   else top.close_current_dialog();
}

function add_category_onload()
{
   category_onload();
   top.set_current_dialog_onclose(add_category_onclose);
}

function change_category_type(radio_button)
{
   if (typeof(custom_change_category_type) != 'undefined')
      custom_change_category_type(radio_button);
}

function change_products_source()
{
   var products_source = get_selected_radio_button('products_source');
   var category_products_div = document.getElementById('category_products_div');
   var category_filters_div = document.getElementById('category_filters_div');
   if (products_source == 0) {
      category_products_div.style.display = '';
      category_filters_div.style.display = 'none';
   }
   else {
      category_products_div.style.display = 'none';
      category_filters_div.style.display = '';
   }
}

function process_add_category()
{
   if (! validate_form_field(document.AddCategory.name,category_label + ' Name',
       "change_tab(document.getElementById('category_tab'),'category_content');")) return;

   var fields = 'cmd=processaddcategory&' + build_form_data(document.AddCategory);
/*
   if (rebuild_web_site) {
      top.display_status('Add Category','Rebuilding Web Site, Please Wait...',
                         420,100,null);
      var timeout_seconds = 60;
   }
   else {
*/
      top.enable_current_dialog_progress(true);
      var timeout_seconds = 30;
//   }
   call_ajax(script_name,fields,true,finish_add_category,timeout_seconds);
}

function finish_add_category(ajax_request)
{
   var status = ajax_request.get_status();
//   if (rebuild_web_site) top.remove_status(); else
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      if (typeof(update_sublist) != 'undefined') update_sublist();
      else top.get_content_frame().reload_grid();
      cancel_add_category = false;
      top.close_current_dialog();
   }
   else if (status == 406) {
      change_tab(document.getElementById('seo_tab'),'seo_content');
      document.AddCategory.seo_url.focus();   return;
   }
   else if (status == 409) {
      change_tab(document.getElementById('seo_tab'),'seo_content');
      document.AddCategory.seo_url.focus();   return;
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_category()
{
   if (categories_grid.table._num_rows < 1) {
      alert('There are no categories to edit');   return;
   }
   var grid_row = categories_grid.grid.getCurrentRow();
   var id = categories_grid.grid.getCellText(0,grid_row);
   categories_grid.table.save_position();
   if (top.use_cached_dialogs) {
      var cached_dialog = top.get_cached_dialog('edit_category');
      if (cached_dialog) {
         cached_dialog.load_category_info(id);
         top.show_dialog('edit_category');
         top.set_current_dialog_title('Edit '+category_label);
         top.enable_current_dialog_progress(true);
         return;
      }
   }
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=editcategory&id=' + id;
   if (top.skin) var dialog_width = 1000;
   else var dialog_width = 900;
   top.create_dialog('edit_category',null,null,dialog_width,535,false,url,null);
}

function load_category_info(id)
{
   clear_form_fields(document.EditCategory);
   set_current_tab('category_tab');
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = '';
   var fields = 'cmd=loadcategory&id=' + id;
   var ajax_request = new Ajax(url + script_name,fields,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(finish_load_category,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function set_category_flags(flags)
{
   var checkboxes = document.getElementsByTagName('input');
   for (var loop = 0;  loop < checkboxes.length;  loop++) {
      if ((checkboxes[loop].type == 'checkbox') &&
          (checkboxes[loop].name.substring(0,4) == 'flag')) {
         var bit = checkboxes[loop].name.substring(4);
         if (flags & (1 << bit)) checkboxes[loop].checked = true;
      }
   }
}

function reload_sublist(sublist,parent)
{
   if (! sublist) return;
   sublist.set_parent(parent);
   sublist.reload_left_sublist_grid();
}

function finish_load_category(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) {
      top.enable_current_dialog_progress(false);   return;
   }

   var category_data = JSON.parse(ajax_request.request.responseText);
   var dialog_title = 'Edit '+category_label+' - '+category_data.name +
                      ' (#'+category_data.id+')';
   set_current_dialog_title(dialog_title);
   document.getElementsByTagName('base')[0].href = base_url;
   update_form_fields(document.EditCategory,category_data);
   var category_id = category_data.id;
   var seo_url = category_data.seo_url;
   if (! seo_url) seo_url = category_id;
   var view_url = url_prefix + '/' + seo_url + '/';
   document.getElementById('view_link').href = view_url;
   set_category_flags(category_data.flags);

   window.setTimeout(function() {
      document.getElementsByTagName('base')[0].href = default_base_href;
      set_image_parent(category_id);
      reload_images_grid(0);
      reload_sublist(subcategories,category_id);
      reload_sublist(parentcategories,category_id);
      reload_sublist(products,category_id);
   },0);

   top.enable_current_dialog_progress(false);
}

function previous_category()
{
   var categories_grid = top.get_content_frame().categories_grid;
   var grid_row = categories_grid.grid.getCurrentRow();
   if (grid_row == 0) {
      alert('There are no previous categories');   return;
   }
   grid_row--;
   categories_grid.grid.setSelectedRows([grid_row]);
   categories_grid.grid.setCurrentRow(grid_row);
   var id = categories_grid.grid.getCellText(0,grid_row);
   top.set_current_dialog_title('Edit '+category_label);
   top.enable_current_dialog_progress(true);
   load_category_info(id);
   return false;
}

function next_category()
{
   var categories_grid = top.get_content_frame().categories_grid;
   var grid_row = categories_grid.grid.getCurrentRow();
   grid_row++;
   if (grid_row == categories_grid.table._num_rows) {
      alert('There are no more categories');   return;
   }
   categories_grid.grid.setSelectedRows([grid_row]);
   categories_grid.grid.setCurrentRow(grid_row);
   var id = categories_grid.grid.getCellText(0,grid_row);
   top.set_current_dialog_title('Edit '+category_label);
   top.enable_current_dialog_progress(true);
   load_category_info(id);
   return false;
}

function update_category()
{
   if (! validate_form_field(document.EditCategory.name,category_label + ' Name',
       "change_tab(document.getElementById('category_tab'),'category_content');")) return;

   var fields = 'cmd=updatecategory&' + build_form_data(document.EditCategory);
/*
   if (rebuild_web_site) {
      top.display_status('Update Category','Rebuilding Web Site, Please Wait...',
                         420,100,null);
      var timeout_seconds = 60;
   }
   else {
*/
      if ((! inside_cms) || (cms_top() == top))
         top.enable_current_dialog_progress(true);
      var timeout_seconds = 30;
//   }
   call_ajax(script_name,fields,true,finish_update_category,timeout_seconds);
}

function finish_update_category(ajax_request)
{
   var status = ajax_request.get_status();
//   if (rebuild_web_site) top.remove_status(); else
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(false);
   if (status == 201) {
      if ((typeof(images_grid) != 'undefined') && images_grid)
         images_grid.table.process_updates(false);
      if (typeof(update_sublist) != 'undefined') update_sublist();
      else if ((typeof(top.current_tab) != 'undefined') && (! inside_cms))
         top.get_content_frame().reload_grid();
      else if (typeof(cms_top) != 'undefined') {
         if (inside_cms) top.num_dialogs--;
         cms_top().location.reload(true);   return;
      }
      if (top.use_cached_dialogs) top.hide_current_dialog();
      else top.close_current_dialog();
   }
   else if (status == 406) {
      change_tab(document.getElementById('seo_tab'),'seo_content');
      document.EditCategory.seo_url.focus();   return;
   }
   else if (status == 409) {
      change_tab(document.getElementById('seo_tab'),'seo_content');
      document.EditCategory.seo_url.focus();   return;
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_category()
{
   if (categories_grid.table._num_rows < 1) {
      alert('There are no categories to delete');   return;
   }
   var grid_row = categories_grid.grid.getCurrentRow();
   var id = categories_grid.grid.getCellText(0,grid_row);
   var name = categories_grid.grid.getCellText(1,grid_row);
   var response = confirm('Are you sure you want to delete the '+name +
                          ' category?');
   if (! response) return;
   var url = 'cmd=deletecategory&id=' + id;
   var num_categories = categories_grid.grid.getCellText(num_categories_column,
                                                         grid_row);
   if (num_categories > 0) {
      var response = confirm('Do you want to move the subcategories in this ' +
                             'category to the parent category?');
      if (response) url += '&movecatparent=true';
   }
   var num_products = categories_grid.grid.getCellText(num_products_column,
                                                       grid_row);
   if (num_products > 0) {
      var response = confirm('Do you want to move the products in this ' +
                             'category to the parent category?');
      if (response) url += '&moveprodparent=true';
   }
   top.enable_current_dialog_progress(true);
   categories_grid.table.save_position();
   call_ajax(script_name,url,true,finish_delete_category);
}

function finish_delete_category(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function view_category()
{
   if (categories_grid.table._num_rows < 1) {
      alert('There are no categories to view');   return;
   }
   var grid_row = categories_grid.grid.getCurrentRow();
   var seo_url = categories_grid.grid.getCellText(status_column + 1,grid_row);
   var url = '/' + seo_url + '/';
   if (enable_multisite) {
      var websites = categories_grid.grid.getCellText(status_column + 2,
                                                      grid_row);
      websites = websites.split(',');
      url = website_urls[websites[0]] + url;
   }
   window.open(url);
}

function view_category_link()
{
   var id = document.EditCategory.id.value;
   var seo_url = document.EditCategory.seo_url.value;
   if (seo_url) var url = '/' + seo_url + '/';
   else var_url = '/display-category.php?id=' + id;
   if (enable_multisite) {
      for (var website_id in website_urls) {
         if (document.EditCategory['website_' + website_id].checked) {
            url = website_urls[website_id] + url;   break;
         }
      }
   }
   window.open(url);
}

function rebuild_category_cache()
{
   top.display_status('Rebuild Cache','Rebuilding '+category_label +
                      ' Cache, Please Wait...',600,100,null);
   call_ajax(script_name,'cmd=rebuildcache',true,
             finish_rebuild_category_cache,0);
}

function finish_rebuild_category_cache(ajax_request)
{
   var status = ajax_request.get_status();
   top.remove_status();
   if (status == 201) alert(category_label+' Cache Rebuilt');
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_tab(tab,content_id)
{
   var show_sep = false;
   if (content_id == 'image_content') {
      enable_image_buttons(true);   show_sep = true;
   }
   else enable_image_buttons(false);
   var new_subcategory_button = document.getElementById('new_subcategory');
   if (content_id == 'subcategory_content') {
      new_subcategory_button.style.display = '';   show_sep = true;
      if (top.skin) subcategories.resize(false);
   }
   else new_subcategory_button.style.display = 'none';
   var new_parentcategory_button = document.getElementById('new_parentcategory');
   if (content_id == 'parentcategory_content') {
      new_parentcategory_button.style.display = '';   show_sep = true;
      if (top.skin) parentcategories.resize(false);
   }
   else new_parentcategory_button.style.display = 'none';
   var new_product_button = document.getElementById('new_product');
   if (content_id == 'product_content') {
      if (new_product_button) {
         new_product_button.style.display = '';   show_sep = true;
      }
      if (top.skin) products.resize(false);
   }
   else if (new_product_button) new_product_button.style.display = 'none';
   if (typeof(set_price_breaks) != 'undefined') {
      var set_price_breaks_button = document.getElementById('set_price_breaks');
      if (content_id == 'product_content') {
         set_price_breaks_button.style.display = '';   show_sep = true;
      }
      else set_price_breaks_button.style.display = 'none';
   }
   var category_buttons_row = document.getElementById('category_buttons_row');
   if (category_buttons_row) {
      if (show_sep) category_buttons_row.style.display = '';
      else category_buttons_row.style.display = 'none';
   }
   tab_click(tab,content_id);
   if (content_id == 'image_content') images_grid.resize_column_headers();
   if (content_id == 'subcategory_content')
      subcategories.resize_column_headers();
   if (content_id == 'parentcategory_content')
      parentcategories.resize_column_headers();
   if (content_id == 'product_content')
      products.resize_column_headers();
}

function convert_data(col,row,text)
{
   if (col == category_type_column) {
      var type = parse_int(text);
      if (typeof(category_types[type]) == 'undefined') return text;
      return category_types[type];
   }
   else if (col == product_source_column) {
      if (text == '0') return 'List';
      else if (text == '1') return 'Filter';
   }
   else if (col == num_products_column) {
      if (enable_category_filter_search) {
         var source = categories_grid.grid.getCellValue(product_source_column,
                                                        row);
         if (source == 'Filter') return 'n/a';
      }
   }
   else if (col == status_column) {
      var status = parse_int(text);
      if (typeof(category_status_values[status]) == 'undefined') return text;
      return category_status_values[status];
   }
   return text;
}

function search_categories()
{
   var query = document.SearchForm.query.value;
   if (query == '') {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status('Search','Searching '+categories_label+'...',
                      350,100,null);
   window.setTimeout(function() {
      var where = "c.name like '%" + query + "%' or c.short_description like '%" +
                  query + "%' or c.long_description like '%" + query +
                  "%' or c.seo_url like '%" + query + "%'";
      if (! isNaN(query)) where += ' or id=' + query;
      if ((typeof(top.website) != 'undefined') && (top.website != 0))
         where = '(' + where + ') and find_in_set("' + top.website + '",websites)';
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      categories_grid.set_where(where);
      categories_grid.table.reset_data(false);
      categories_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status('Search','Loading All '+categories_label+'...',
                      350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      if ((typeof(top.website) != 'undefined') && (top.website != 0))
         var where = "find_in_set('" + top.website + "',websites)";
      else var where = '';
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      categories_grid.set_where(where);
      categories_grid.table.reset_data(false);
      categories_grid.grid.refresh();
      top.remove_status();
   },0);
}

function get_category_filter_value(field_name)
{
    var list = document.getElementById(field_name);
    if (! list) return '';
    if (list.selectedIndex == -1) return '';
    return list.options[list.selectedIndex].value;
}

function filter_categories()
{
    filter_where = '';
    var category_type = get_category_filter_value('category_type');
    if (category_type) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(c.category_type=' + category_type + ')';
    }
    var status = get_category_filter_value('status');
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(c.status=' + status + ')';
    }
    if (typeof(get_custom_category_filters) != 'undefined')
       filter_where += get_custom_category_filters();
    var query = document.SearchForm.query.value;
    if (query) search_categories();
    else {
       top.display_status('Search','Searching '+categories_label+'...',
                          350,100,null);
       window.setTimeout(function() {
          categories_grid.set_where(filter_where);
          categories_grid.table.reset_data(false);
          categories_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function new_product()
{
   cancel_add_product = true;
   top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = '';
   var ajax_request = new Ajax(url + products_script_name,
                               'cmd=createproduct',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_product,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_product(ajax_request,data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var product_id = -1;
   eval(ajax_request.request.responseText);

   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) {
      var product_dialog_height = 300;
      var url = admin_path;
   }
   else {
      var product_dialog_height = top.get_content_frame().product_dialog_height;
      var url = script_prefix;
   }
   url += products_script_name + '?cmd=addproduct&id=' + product_id;
   if (update_window == '') update_window = products.frame_name;
   var dialog_name = 'add_product_' + (new Date()).getTime();
   url += '&frame='+dialog_name+'&sublist='+products.name +
          '&side=both&updatewindow='+update_window;
   top.create_dialog(dialog_name,null,null,900,product_dialog_height,false,url,null);
}

