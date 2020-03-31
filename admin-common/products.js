/*
             Inroads Shopping Cart - Products Tab JavaScript Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

var shopping_cart = false;
var products_grid = null;
var shopping_field_grid = null;
var cancel_add_product = true;
var status_column = -1;
var type_column = -1;
var taxable_column = -1;
var description_column = -1;
var update_window = '';
var script_prefix = '';
var products_table = 'products';
var name_prompt = 'Product Name';
var product_label = 'Product';
var products_label = 'Products';
var use_display_name = true;
var use_part_numbers = false;
var product_dialog_height = 0;
var script_name;
var categories_script_name;
var cache_catalog_pages = false;
var url_prefix = '';
var default_base_href = '';
var base_url = '';
var product_label;
var products_label;
var admin_path;
var inside_cms = false;
var dialog_title;
var data_grids = [];
var data_grid_info = [];
var num_data_grids = 0;
var product_main_screen_flag;
var include_taxable = false;
var activity_grid;
var reviews_grid = null;
var show_buttons = true;
var enable_vendors = false;
var select_product_change_function = null;
var dynamic_images = false;
var use_dynamic_images = false;
var image_subdir_prefix = 0;
var dynamic_image_url = null;
var sample_image_size = 'small';
var selected_product_image = -1;
var product_images = null;
var attribute_list = null;
var categories = null;
var products = null;
var features = 0;
var enable_inventory_backorder = false;
var enable_inventory_available = false;
var account_id = null;
var select_multiple = false;
var filter_where = '';
var popular_attributes = [];
var related_types = [];
var product_name_column = 1;
var enable_multisite = false;
var websites_column = -1;
var select_type = -1;
var shopping_modules = null;

var MAINTAIN_INVENTORY = 1;
var USE_PART_NUMBERS = 4;
var REGULAR_PRICE_PRODUCT = 32;
var REGULAR_PRICE_INVENTORY = 64;
var REGULAR_PRICE_BREAKS = 128;
var LIST_PRICE_PRODUCT = 256;
var LIST_PRICE_INVENTORY = 512;
var SALE_PRICE_PRODUCT = 1024;
var SALE_PRICE_INVENTORY = 2048;
var PRODUCT_COST_PRODUCT = 524288;
var PRODUCT_COST_INVENTORY = 1048576;
var HIDE_OUT_OF_STOCK = 32768;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(products_grid,-1,new_height - get_grid_offset(products_grid));
    else resize_grid(products_grid,new_width,new_height);
}

function resize_dialog(new_width,new_height)
{
   if (products_grid) {
      if (typeof(inventory_grid) != 'undefined') {
         var offset = top.get_style(document.body,'padding-top',true) +
                      top.get_style(document.body,'padding-bottom',true);
         var inner_height = new_height - offset - 4;
         var grid_height = Math.floor(inner_height / 2);
      }
      else if (top.skin)
         var grid_height = new_height - get_grid_offset(products_grid);
      else var grid_height = new_height;
      if (top.skin) resize_grid(products_grid,-1,grid_height);
      else resize_grid(products_grid,new_width,grid_height);
      if (typeof(inventory_grid) != 'undefined') {
         if (top.skin) resize_grid(inventory_grid,-1,grid_height);
         else resize_grid(inventory_grid,new_width,grid_height);
      }
   }
   if ((! top.skin) && (num_data_grids > 0)) {
      for (var loop = 0;  loop < num_data_grids;  loop++) {
         var data_grid = data_grids[data_grid_info[loop].type];
         var no_prompt = data_grid_info[loop].no_prompt;
         if (no_prompt) var grid_width = new_width - 150;
         else var grid_width = new_width - 230;
         resize_grid(data_grid,grid_width,-1);
      }
   }
   if (top.skin) {
      if (attribute_list) attribute_list.resize(true);
      if (categories) categories.resize(true);
      if (products) products.resize(true);
      for (var related_type in related_types) {
         if (window['related_' + related_type])
            window['related_' + related_type].resize(true);
      }
   }
   if (shopping_field_grid) {
      if (top.skin)
         resize_grid(shopping_field_grid,-1,new_height - get_grid_offset(shopping_field_grid));
      else resize_grid(shopping_field_grid,new_width,new_height)
   }
}

function load_grid(main_screen_flag,include_inventory)
{
   if (typeof(include_inventory) == 'undefined') include_inventory = false;
   product_main_screen_flag = main_screen_flag;
   var grid_size = get_default_grid_size();
   if (include_inventory) var grid_height = Math.floor(grid_size.height / 2);
   else grid_height = grid_size.height;
   products_grid = new Grid(products_table,grid_size.width,grid_height);
   products_grid.set_server_sorting(true);
   if (main_screen_flag) {
      if (typeof(product_columns) != 'undefined') {
         products_grid.set_columns(product_columns);
         if (typeof(product_field_names) != 'undefined') {
            if (status_column == -1)
               status_column = product_field_names.indexOf('status');
            if (description_column == -1)
               description_column =
                  product_field_names.indexOf('short_description');
            if (typeof(product_types) != 'undefined')
               type_column = product_field_names.indexOf('product_type');
            if (include_taxable)
               taxable_column = product_field_names.indexOf('taxable');
            if (enable_multisite)
               websites_column = product_field_names.indexOf('websites');
         }
      }
      else {
         var column_names = new Array();
         column_names[0] = 'Id';
         column_names[1] = name_prompt;
         column_names[2] = 'Display Name';
         column_names[3] = '# Cat';
         var index = 4;
         if (typeof(product_types) != 'undefined') {
            type_column = index;
            column_names[index++] = 'Type';
         }
         if (enable_vendors) column_names[index++] = 'Vendor';
         description_column = index;
         column_names[index++] = 'Description';
         for (var loop = 0;  loop < field_col_names.length;  loop++)
            column_names[index++] = field_col_names[loop];
         status_column = index;
         column_names[index++] = 'Status';
         if (include_taxable) {
            taxable_column = index;
            column_names[index++] = 'Taxable';
         }
         if (enable_multisite) {
            websites_column = index;
            column_names[index++] = 'Web Sites';
         }
         products_grid.set_columns(column_names);
      }
      if (typeof(product_field_names) != 'undefined')
         products_grid.set_field_names(product_field_names);
      else {
         var grid_field_names = new Array();
         grid_field_names[0] = 'id';
         grid_field_names[1] = 'name';
         grid_field_names[2] = 'display_name';
         grid_field_names[3] = 'num_cats';
         var index = 4;
         if (typeof(product_types) != 'undefined')
            grid_field_names[index++] = 'product_type';
         if (enable_vendors) grid_field_names[index++] = 'vendor';
         grid_field_names[index++] = 'short_description';
         for (var loop = 0;  loop < field_names.length;  loop++)
            grid_field_names[index++] = field_names[loop];
         grid_field_names[index++] = 'status';
         if (include_taxable) grid_field_names[index++] = 'taxable';
         if (enable_multisite) grid_field_names[index++] = 'websites';
         products_grid.set_field_names(grid_field_names);
      }
      if (typeof(product_column_widths) != 'undefined')
         products_grid.set_column_widths(product_column_widths);
      else {
         var column_widths = new Array();
         column_widths[0] = 0;
         column_widths[1] = name_col_width;
         column_widths[2] = 150;
         column_widths[3] = 40;
         index = 4;
         if (typeof(product_types) != 'undefined')
            column_widths[index++] = 100;
         if (enable_vendors) column_widths[index++] = 100;
         column_widths[index++] = desc_col_width;
         for (var loop = 0;  loop < field_col_widths.length;  loop++)
            column_widths[index++] = field_col_widths[loop];
         column_widths[index++] = 150;
         if (include_taxable) column_widths[index++] = 60;
         if (enable_multisite) column_widths[index++] = 0;
         products_grid.set_column_widths(column_widths);
      }
      if (typeof(product_query) != 'undefined')
         var query = product_query;
      else {
         var query = 'select p.id,p.name,p.display_name,(select count(' +
            'parent) from category_products where related_id=' +
            'p.id) as num_cats,';
         if (typeof(product_types) != 'undefined') query += 'p.product_type,';
         if (enable_vendors)
            query += 'IFNULL((select name from vendors where id=p.vendor),' +
                     'vendor) as vendor,';
         query += 'p.short_description,';
         for (var loop = 0;  loop < field_names.length;  loop++)
            query += field_names[loop] + ',';
         query += 'p.status';
         if (include_taxable) query += ',IFNULL(p.taxable,1)';
         if (enable_multisite) query += ',websites';
         query += ' from ' + products_table + ' p';
      }
      if ((typeof(top.website) != 'undefined') && (top.website != 0)) {
         var where = 'find_in_set("' + top.website + '",p.websites)';
         products_grid.set_where(where);
      }
   }
   else {
      if (typeof(select_product_columns) != 'undefined')
         products_grid.set_columns(select_product_columns);
      else products_grid.set_columns(['ID',name_prompt,'Description','Status',
                                      '','','','','','','','','','']);
      if (typeof(select_product_column_widths) != 'undefined')
         products_grid.set_column_widths(select_product_column_widths);
      else products_grid.set_column_widths([0,200,275,150,0,0,0,0,0,0,0,0,0,0]);
      if (typeof(select_product_query) != 'undefined')
         var query = select_product_query;
      else {
         var query = 'select p.id,p.name,p.short_description,p.status,' +
                     'p.display_name';
         if (features & USE_PART_NUMBERS)
            query += ',(select part_number from product_inventory where ' +
                     'parent=p.id limit 1) as part_number';
         else query += ",'' as part_number";
         if (features & LIST_PRICE_PRODUCT) query += ',p.list_price';
         else if (features & LIST_PRICE_INVENTORY)
            query += ',(select list_price from product_inventory where ' +
                     'parent=p.id limit 1) as list_price';
         else query += ",'' as list_price";
         if (features & REGULAR_PRICE_PRODUCT) query += ',p.price';
         else if (features & REGULAR_PRICE_INVENTORY)
            query += ',(select price from product_inventory where ' +
                     'parent=p.id limit 1) as price';
         else query += ",'' as price";
         if (features & SALE_PRICE_PRODUCT) query += ',p.sale_price';
         else if (features & SALE_PRICE_INVENTORY)
            query += ',(select sale_price from product_inventory where ' +
                     'parent=p.id limit 1) as sale_price';
         else query += ",'' as sale_price";
         if (features & REGULAR_PRICE_BREAKS) query += ',p.price_break_type';
         else query += ',0 as price_break_type';
         if (features & PRODUCT_COST_PRODUCT) query += ',p.cost';
         else if (features & PRODUCT_COST_INVENTORY)
            query += ',(select cost from product_inventory where ' +
                     'parent=p.id limit 1) as cost';
         else query += ",'' as cost";
         query += ',p.account_discount,p.flags';
         if (shopping_cart) query += ',p.order_name';
         else query += ',"" as order_name';
         query += ' from ' + products_table + ' p';
      }
      if (typeof(select_product_field_names) != 'undefined')
         products_grid.set_field_names(select_product_field_names);
      else {
         var grid_field_names = ['id','name','short_description',
            'status','display_name','part_number','list_price','price',
            'sale_price','price_break_type','cost','account_discount','flags',
            'order_name'];
         products_grid.set_field_names(grid_field_names);
      }
      description_column = 2;
      status_column = 3;
      if ((features & MAINTAIN_INVENTORY) && (features & HIDE_OUT_OF_STOCK)) {
         var info_query = 'select count(p.id) from products p';
         products_grid.set_info_query(info_query);
         var where = '(select sum(qty) from product_inventory i where ' +
                     'parent=p.id)>0'
         if (select_type != -1)
            where += 'and (p.product_type=' + select_type + ')';
         products_grid.set_where(where);
      }
      else if (select_type != -1) {
         var where = 'p.product_type=' + select_type;
         products_grid.set_where(where);
      }
   }
   products_grid.set_query(query);
   if (typeof(product_info_query) != 'undefined')
      products_grid.set_info_query(product_info_query);
   else products_grid.set_info_query('select count(p.id) from products p');
   products_grid.set_order_by('name');
   if (typeof(convert_product_data) != 'undefined')
      products_grid.table.set_convert_cell_data(convert_product_data);
   else products_grid.table.set_convert_cell_data(convert_data);
   products_grid.set_id('products_grid');
   if ((! main_screen_flag) &&
       (typeof(custom_update_select_product_grid) != 'undefined'))
      custom_update_select_product_grid(products_grid);
   products_grid.load(false);
   if (main_screen_flag) {
      products_grid.grid.clearSelectedModel();
      products_grid.grid.setSelectionMode('multi-row');
      if (show_buttons) products_grid.set_double_click_function(edit_product);
   }
   else if (typeof(select_inv_product) != 'undefined')
      products_grid.grid.onCurrentRowChanged = select_inv_product;
   else {
      products_grid.set_double_click_function(select_product);
      products_grid.grid.onCurrentRowChanged = change_select_product;
      if (select_multiple) {
         products_grid.grid.clearSelectedModel();
         products_grid.grid.setSelectionMode('multi-row');
      }
   }
   var number_format = new AW.Formats.Number;
   products_grid.grid.setCellFormat(number_format,0);
   products_grid.display();
}

function reload_grid()
{
   products_grid.table.reset_data(false);
   products_grid.grid.refresh();
   window.setTimeout(function() { products_grid.table.restore_position(); },0);
}

function get_product_form()
{
   if (document.AddProduct) return document.AddProduct;
   else return document.EditProduct;
}

function add_product(sublist)
{
   cancel_add_product = true;
   top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = '';
   var ajax_request = new Ajax(url + script_name,'cmd=createproduct',true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   if (typeof(sublist) != 'undefined') var ajax_data = sublist;
   else var ajax_data = null;
   ajax_request.set_callback_function(continue_add_product,ajax_data);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_product(ajax_request,sublist)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var product_id = -1;
   eval(ajax_request.request.responseText);

   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=addproduct&id=' + product_id;
   if (sublist) {
      if (update_window == '') update_window = sublist.frame_name;
      var dialog_name = 'add_product_' + (new Date()).getTime();
      url += '&frame='+dialog_name+'&sublist='+sublist.name +
             '&side=both&updatewindow='+update_window;
   }
   else var dialog_name = 'add_product';
   var window_width = top.get_document_window_width();
   if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
   else window_width -= top.default_dialog_frame_width;
   url += '&window_width=' + window_width;
   if (products_grid) products_grid.table.save_position();
   top.create_dialog(dialog_name,null,null,product_dialog_width,
                     product_dialog_height,false,url,null);
}

function product_dialog_onclose(user_close)
{
   top.num_dialogs--;
}

function add_product_onclose(user_close)
{
   if (cancel_add_product) {
      var product_id = document.AddProduct.id.value;
      call_ajax(script_name,"cmd=deleteproduct&ids=" + product_id,true);
   }
   if (inside_cms && (cms_top() != top))
      product_dialog_onclose(user_close);
}

function check_dialog_fully_loaded()
{
   var form = get_product_form();
   var start_field = form.Start;
   var end_field = form.End;
   if ((! start_field) || (! end_field) || (start_field.value != '$Start$') ||
       (end_field.value != '$End$')) {
      alert('Product dialog did not finish loading properly, please Cancel and open the dialog again');
      return false;
   }
   return true;
}

function product_onload()
{
   check_dialog_fully_loaded();
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
      top.set_current_dialog_onclose(product_dialog_onclose);
   }
   if (typeof(custom_product_onload) != "undefined") custom_product_onload();
   if (top.use_cached_dialogs && (! inside_cms) && document.EditProduct)
      top.cache_current_dialog();
   if (get_product_form().vendor) select_vendor();
   if (typeof(data_grids[1]) != 'undefined')
      window.setTimeout(function() {
         data_grids[1].resize_column_headers();
      },0);
}

function close_product_dialog()
{
   if (inside_cms && (cms_top() != top))
      cms_top().close_current_dialog();
   else if (top.use_cached_dialogs && document.EditProduct)
      top.hide_current_dialog();
   else top.close_current_dialog();
}

function add_product_onload()
{
   product_onload();
   top.set_current_dialog_onclose(add_product_onclose);
}

function change_product_type(radio_button)
{
   if (typeof(custom_change_product_type) != 'undefined')
      custom_change_product_type(radio_button);
   else if (typeof(change_gift_certificate_product_type) != 'undefined')
      change_gift_certificate_product_type(radio_button);
}

function set_import_id(import_id)
{
   var import_list = document.getElementById('import_list');
   var html = '<input type="hidden" name="import_id" value="'+import_id+'">';
   import_list.innerHTML = html;
}

function select_vendor(current_import_id)
{
   var vendor_list = get_product_form().vendor;
   if (vendor_list.selectedIndex < 1) {
      set_import_id('');   return;
   }
   var vendor = vendor_list.options[vendor_list.selectedIndex].value;
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = '';
   var fields = 'cmd=loadimports&vendor=' + vendor;
   call_ajax(url + script_name,fields,true,finish_select_vendor);
}

function finish_select_vendor(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if ((status != 200) || (! ajax_request.request.responseText)) {
      set_import_id('');   return;
   }
   var imports = JSON.parse(ajax_request.request.responseText);
   if (imports.length == 0) {
      set_import_id('');   return;
   }
   var field = get_product_form().import_id;
   if (field.nodeName == 'SELECT') {
      if (field.selectedIndex == -1) var import_id = '';
      else var import_id = field.options[field.selectedIndex].value;
   }
   else var import_id = field.value;
   var import_list = document.getElementById('import_list');
   var html = '<select name="import_id" id="import_id" class="select">';
   html += '<option value=""';
   if (import_id == '') html += ' selected';
   html += '></option>';
   for (var index in imports) {
      html += '<option value="' + imports[index].id + '"';
      if (import_id == imports[index].id) html += ' selected';
      html += '>' +imports[index].name + '</option>';
   }
   html += '</select>';
   import_list.innerHTML = html;
}

function generate_cached_page()
{
   if (document.AddProduct) {
      var edit_type = 0;
      var product_id = document.AddProduct.id.value;
   }
   else {
      var edit_type = 1;
      var product_id = document.EditProduct.id.value;
   }
   var fields = "cmd=generatecachedpage&id=" + product_id + "&EditType=" +
                edit_type;
   if (edit_type == 1) {
      var old_flags = document.EditProduct.old_flags.value;
      var old_seo_url = document.EditProduct.old_seo_url.value;
      fields += "&OldFlags=" + old_flags + "&OldSeoUrl=" +
                encodeURIComponent(old_seo_url);
   }
   call_ajax(script_name,fields,true);
}

function process_add_product()
{
   if (! check_dialog_fully_loaded()) return;
   if (! validate_form_field(document.AddProduct.name,name_prompt,
       "set_current_tab('product_tab');")) return;
/*
   if (use_display_name) {
      if (! validate_form_field(document.AddProduct.display_name,"Display Name",
          "set_current_tab('product_tab');")) return;
   }
*/

   top.enable_current_dialog_progress(true);
   if (typeof(update_price_breaks) != "undefined") update_price_breaks();
   if (typeof(update_discounts) != "undefined") update_discounts();
   var fields = "cmd=processaddproduct&" + build_form_data(document.AddProduct);
   call_ajax(script_name,fields,true,finish_add_product,60);
}

function finish_add_product(ajax_request)
{
   var status = ajax_request.get_status();
   if ((status == 201) && (typeof(inventory_grid) != "undefined") &&
       inventory_grid)
      inventory_grid.table.process_updates(false);
   if (cache_catalog_pages) generate_cached_page();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      if (document.AddProduct.OrderFrame) {
         var form = document.AddProduct;
         var frame = form.OrderFrame.value;
         var iframe = top.get_dialog_frame(frame).contentWindow;
         var flags = 0;
         var bit = 0;
         while (typeof(form['flag' + bit]) != 'undefined') {
            if (form['flag' + bit].checked) flags |= (1 << bit);
            bit++;
         }
         var product_data = {
            id: form.id.value,
            name: form.name.value,
            description: form.short_description.value,
            status: get_selected_list_value('status'),
            display_name: form.display_name.value,
            part_number: '',
            list_price: form.list_price.value,
            price: form.price.value,
            sale_price: form.sale_price.value,
            price_break_type: null,
            flags: flags
         };
         if (typeof(inventory_grid) != 'undefined') {
            var inv_grid = inventory_grid.grid;
            product_data.inv_id = inv_grid.getCellText(0,0);
            product_data.attributes = inv_grid.getCellText(2,0);
            product_data.part_number = inv_grid.getCellText(3,0);
         }
         iframe.change_product(product_data);
      }
      else if (typeof(update_sublist) != "undefined") update_sublist();
      else top.get_content_frame().reload_grid();
      cancel_add_product = false;
      top.close_current_dialog();
   }
   else if (status == 406) {
      set_current_tab('seo_tab');   document.AddProduct.seo_url.focus();
      return;
   }
   else if (status == 409) {
      set_current_tab('seo_tab');   document.AddProduct.seo_url.focus();
      return;
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_product()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to edit');   return;
   }
   if (products_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one '+product_label+' to edit');   return;
   }
   var grid_row = products_grid.grid.getCurrentRow();
   var id = products_grid.grid.getCellText(0,grid_row);
   products_grid.table.save_position();
   if (top.use_cached_dialogs) {
      var cached_dialog = top.get_cached_dialog('edit_product');
      if (cached_dialog) {
         cached_dialog.load_product_info(id);
         top.show_dialog('edit_product');
         top.set_current_dialog_title('Edit '+product_label);
         top.enable_current_dialog_progress(true);
         return;
      }
   }
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=editproduct&id=' + id;
   var window_width = top.get_document_window_width();
   if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
   else window_width -= top.default_dialog_frame_width;
   url += '&window_width=' + window_width;
   top.create_dialog('edit_product',null,null,product_dialog_width,
                     product_dialog_height,false,url,null);
}

function load_product_info(id)
{
   if (document.EditProduct.MaxShoppingFlag)
      var max_shopping_flag = document.EditProduct.MaxShoppingFlag.value;
   clear_form_fields(document.EditProduct);
   if (document.EditProduct.MaxShoppingFlag)
      document.EditProduct.MaxShoppingFlag.value = max_shopping_flag;
   if (typeof(custom_clear_product_fields) != 'undefined')
      custom_clear_product_fields(document.EditProduct);
   document.EditProduct.Start.value = '$Start$';
   document.EditProduct.End.value = '$End$';
   set_current_tab('product_tab');
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   var fields = 'cmd=loadproduct&id=' + id;
   var ajax_request = new Ajax(url + script_name,fields,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(finish_load_product,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function set_product_flags(flags)
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

function set_shopping_fields(product_data)
{
   var flags = product_data.shopping_flags;
   var checkboxes = document.getElementsByTagName('input');
   for (var loop = 0;  loop < checkboxes.length;  loop++) {
      if ((checkboxes[loop].type == 'checkbox') &&
          (checkboxes[loop].name.substring(0,13) == 'shopping_flag')) {
         var bit = checkboxes[loop].name.substring(13);
         if (flags & (1 << bit)) checkboxes[loop].checked = true;
      }
   }
   if (shopping_modules) {
      for (var index in shopping_modules) {
         if (typeof(window[shopping_modules[index] +
                    '_set_fields']) != 'undefined')
         url = window[shopping_modules[index] + '_set_fields'](product_data);
      }
   }
}

function reload_sublist(sublist,parent)
{
   if (! sublist) return;
   sublist.set_parent(parent);
   sublist.reload_left_sublist_grid();
//   sublist.reload_right_sublist_grid();
}

function reload_seo_category_list(product_data)
{
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   var fields = 'cmd=loadseocategories&id=' + product_data.id;
   var ajax_request = new Ajax(url + script_name,fields,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(finish_reload_seo_category_list,
                                      product_data);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function finish_reload_seo_category_list(ajax_request,product_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) return;
   var seo_categories = JSON.parse(ajax_request.request.responseText);
   var seo_category_list = document.getElementById('seo_category');
   while (seo_category_list.options.length > 0) seo_category_list.remove(0);
   var selected_index = 0;   var index = 1;
   var new_option = new Option('','');
   for (var option_id in seo_categories) {
      var new_option = new Option(seo_categories[option_id],option_id);
      seo_category_list.options[index] = new_option;
      if (option_id == product_data.seo_category) selected_index = index;
      index++;
   }
   seo_category_list.selectedIndex = selected_index;
}

function finish_load_product(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) {
      top.enable_current_dialog_progress(false);   return;
   }

   var product_data = JSON.parse(ajax_request.request.responseText);
   var dialog_title = 'Edit '+product_label+' - '+product_data.name +
                      ' (#'+product_data.id+')';
   set_current_dialog_title(dialog_title);
   document.getElementsByTagName('base')[0].href = base_url;
   update_form_fields(document.EditProduct,product_data);
   var product_id = product_data.id;
   var view_url = url_prefix + '/products/' + product_id + '/';
   document.getElementById('view_link').href = view_url;
   set_product_flags(product_data.flags);
   set_shopping_fields(product_data);
   if (typeof(custom_finish_load_product) != 'undefined')
      custom_finish_load_product(product_data);

   window.setTimeout(function() {
      document.getElementsByTagName('base')[0].href = default_base_href;
      set_image_parent(product_id);
      reload_images_grid(0);
      reload_sublist(attribute_list,product_id);
      if ((typeof(inventory_grid) != 'undefined') && inventory_grid)
         set_inventory_parent(product_id);
      if (typeof(standard_discounts_grid) != 'undefined')
         set_discounts_parent(product_id);
      reload_sublist(categories,product_id);
      for (var related_type in related_types)
         reload_sublist(window['related_' + related_type],product_id);
      set_activity_parent(product_id);
      if (reviews_grid) set_reviews_parent(product_id);
      for (var data_type in data_grids) {
         var data_grid = data_grids[data_type];
         data_grid.set_where('(data_type=' + data_type + ') and (parent=' +
                             product_id + ')');
         data_grid.table.reset_data(true);
         data_grid.grid.refresh();
      }
      if (typeof(reload_product_flags) != 'undefined')
         reload_product_flags(product_data);
      if (get_product_form().vendor) {
         set_import_id(product_data.import_id);   select_vendor();
      }
      reload_seo_category_list(product_data);
   },0);

   top.enable_current_dialog_progress(false);
}

function previous_product()
{
   var products_grid = top.get_content_frame().products_grid;
   var grid_row = products_grid.grid.getCurrentRow();
   if (grid_row == 0) {
      alert('There are no previous products');   return;
   }
   grid_row--;
   products_grid.grid.setSelectedRows([grid_row]);
   products_grid.grid.setCurrentRow(grid_row);
   var id = products_grid.grid.getCellText(0,grid_row);
   top.set_current_dialog_title('Edit '+product_label);
   top.enable_current_dialog_progress(true);
   load_product_info(id);
   return false;
}

function next_product()
{
   var products_grid = top.get_content_frame().products_grid;
   var grid_row = products_grid.grid.getCurrentRow();
   grid_row++;
   if (grid_row == products_grid.table._num_rows) {
      alert('There are no more products');   return;
   }
   products_grid.grid.setSelectedRows([grid_row]);
   products_grid.grid.setCurrentRow(grid_row);
   var id = products_grid.grid.getCellText(0,grid_row);
   top.set_current_dialog_title('Edit '+product_label);
   top.enable_current_dialog_progress(true);
   load_product_info(id);
   return false;
}

function update_product()
{
   if (! check_dialog_fully_loaded()) return;
   if (! validate_form_field(document.EditProduct.name,name_prompt,
       "set_current_tab('product_tab');")) return;
/*
   if (use_display_name) {
      if (! validate_form_field(document.EditProduct.display_name,"Display Name",
          "set_current_tab('product_tab');")) return;
   }
*/

   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(true);
   if (typeof(update_price_breaks) != "undefined") update_price_breaks();
   if (typeof(update_discounts) != "undefined") update_discounts();
   var fields = "cmd=updateproduct&" + build_form_data(document.EditProduct);
   call_ajax(script_name,fields,true,finish_update_product,60);
}

function finish_update_product(ajax_request)
{
   var status = ajax_request.get_status();
   if ((status == 201) && (typeof(inventory_grid) != "undefined") &&
       inventory_grid) {
      if (attribute_list &&
          (attribute_list.changed || rows_changed ||
           (current_attributes != initial_attributes))) {
         var parent = document.EditProduct.id.value;
         status = delete_old_inventory(parent);
         if (status == 200) status = 201;
         reset_inventory_rows();
      }
      inventory_grid.table.process_updates(false);
   }
   if ((status == 201) && (typeof(images_grid) != "undefined") && images_grid)
      images_grid.table.process_updates(false);
   if (cache_catalog_pages) generate_cached_page();
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(false);
   if (status == 201) {
      if (typeof(update_sublist) != "undefined") update_sublist();
      else if ((typeof(top.current_tab) != "undefined") && (! inside_cms))
         top.get_content_frame().reload_grid();
      else if (typeof(cms_top) != "undefined") {
         if (inside_cms) top.num_dialogs--;
         cms_top().location.reload(true);   return;
      }
      if (top.use_cached_dialogs) top.hide_current_dialog();
      else top.close_current_dialog();
   }
   else if (status == 406) {
      set_current_tab('seo_tab');   document.EditProduct.seo_url.focus();
      return;
   }
   else if (status == 409) {
      set_current_tab('seo_tab');   document.EditProduct.seo_url.focus();
      return;
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function copy_product()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to copy');   return;
   }
   if (products_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one '+product_label+' to copy');   return;
   }
   var grid_row = products_grid.grid.getCurrentRow();
   var id = products_grid.grid.getCellText(0,grid_row);
   var name = products_grid.grid.getCellText(product_name_column,grid_row);
   var response = confirm('Are you sure you want to copy the '+name+' ' +
                          product_label+'?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   products_grid.table.save_position();
   call_ajax(script_name,"cmd=copyproduct&id=" + id,true,finish_copy_product,
             60);
}

function finish_copy_product(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_product()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to delete');   return;
   }
   var selected_rows = products_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = products_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   if (num_selected == 0) {
      alert('You must select at least one '+product_label+' to delete');
      return;
   }
   if (num_selected == 1) {
      var grid_row = products_grid.grid.getCurrentRow();
      var name = products_grid.grid.getCellText(product_name_column,grid_row);
      var response = confirm('Are you sure you want to delete the '+name+' ' +
                             product_label+'?');
   }
   else var response = confirm('Are you sure you want to delete the selected ' +
                               products_label+'?');
   if (! response) return;
   top.enable_current_dialog_progress(true);
   products_grid.table.save_position();
   call_ajax(script_name,"cmd=deleteproduct&ids=" + ids,true,
             finish_delete_product,60);
}

function finish_delete_product(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) reload_grid();
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function view_product()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to view');   return;
   }
   if (products_grid.grid.getSelectedRows().length > 1) {
      alert('You must select only one '+product_label+' to view');   return;
   }
   var grid_row = products_grid.grid.getCurrentRow();
   var id = products_grid.grid.getCellText(0,grid_row);
   var url = '/products/' + id + '/';
   if (enable_multisite && (websites_column != -1)) {
      var websites = products_grid.grid.getCellText(websites_column,grid_row);
      websites = websites.split(',');
      url = website_urls[websites[0]] + url;
   }
   window.open(url);
}

function view_product_link()
{
   var id = document.EditProduct.id.value;
   var url = '/products/' + id + '/';
   if (enable_multisite) {
      for (var website_id in website_urls) {
         if (document.EditProduct['website_' + website_id].checked) {
            url = website_urls[website_id] + url;   break;
         }
      }
   }
   window.open(url);
}

function validate_list_field(field_name,label,field)
{
   var field_value = get_selected_list_value(field_name);
   if (field_value == '') {
      alert('You must select a ' + label);
      field.focus();
      return false;
   }
   return true;
}

function edit_multiple_products()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to edit');   return;
   }
   var selected_rows = products_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = products_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   products_grid.table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=editmultiple';
   if (num_selected > 0) url += '&ids=' + ids;
   top.create_dialog('multiple_edit',null,null,500,280,false,url,null);
}

function continue_edit_multiple()
{
   var select = get_selected_radio_button('select');
   if ((select == 'category') &&
       (! validate_list_field('category','Category',
                              document.EditMultiple.category)))
      return;
   if ((select == 'vendor') &&
       (! validate_list_field('vendor','Vendor',
                              document.EditMultiple.vendor)))
      return;
   if ((typeof(custom_validate_change_select) != "undefined") &&
       (! custom_validate_change_select(select,document.EditMultiple)))
      return;

   top.enable_current_dialog_progress(true);
   document.EditMultiple.submit();
}

function continue_edit_multiple_onload(width,height)
{
   top.enable_current_dialog_progress(false);
   if (width && height) top.resize_current_dialog(width,height);
   else top.resize_current_dialog(200,100);
   top.grow_current_dialog();
}

function update_multiple_products()
{
   top.enable_current_dialog_progress(true);
   var fields = "cmd=updatemultiple&" + build_form_data(document.EditMultiple);
   call_ajax(script_name,fields,true,finish_update_multiple_products,600);
}

function finish_update_multiple_products(ajax_request)
{
   top.enable_current_dialog_progress(false);
   if (ajax_request.timeout) {
      alert('Timeout while updating products');   return;
   }
   var status = ajax_request.get_status();
// TODO:  if (cache_catalog_pages) generate_cached_page();
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_product_status()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to change status');   return;
   }
   var selected_rows = products_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = products_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   products_grid.table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=changeproductstatus';
   if (num_selected > 0) url += '&ids=' + ids;
   top.create_dialog('change_product_status',null,null,600,100,false,
                     url,null);
}

function update_product_status()
{
   var select = get_selected_radio_button('select');
   if ((select == 'category') &&
       (! validate_list_field('category','Category',
                              document.ChangeProductStatus.category)))
      return;
   if ((select == 'vendor') &&
       (! validate_list_field('vendor','Vendor',
                              document.ChangeProductStatus.vendor)))
      return;
   if ((typeof(custom_validate_change_select) != "undefined") &&
       (! custom_validate_change_select(select,document.ChangeProductStatus)))
      return;
   var status = get_selected_list_value('status');
   if (status == '') {
      alert('You must select a Status');
      document.ChangeProductStatus.status.focus();
      return;
   }
   top.enable_current_dialog_progress(true);
   var fields = "cmd=updateproductstatus&" +
                build_form_data(document.ChangeProductStatus);
   call_ajax(script_name,fields,true,finish_update_product_status,600);
}

function finish_update_product_status(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_prices()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to change prices');   return;
   }
   var selected_rows = products_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = products_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   products_grid.table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=changeprices';
   if (num_selected > 0) url += '&ids=' + ids;
   top.create_dialog('change_prices',null,null,600,100,false,url,null);
}

function update_prices()
{
   var select = get_selected_radio_button('select');
   if ((select == 'category') &&
       (! validate_list_field('category','Category',
                              document.ChangePrices.category)))
      return;
   if ((select == 'vendor') &&
       (! validate_list_field('vendor','Vendor',
                              document.ChangePrices.vendor)))
      return;
   if ((typeof(custom_validate_change_select) != "undefined") &&
       (! custom_validate_change_select(select,document.ChangePrices)))
      return;
   if (! validate_list_field('dest','Price Field',
                              document.ChangePrices.dest)) return;
   var src_price = get_selected_list_value('src');
   var calc = get_selected_list_value('calc');
   var factor = document.ChangePrices.factor.value;
   if ((src_price == '') && (calc != '')) {
      alert('You must select a Source Price');
      document.ChangePrices.src.focus();
      return;
   }
   if ((src_price != '') && (calc == '') && (factor != '')) {
      alert('You must select a Calculation');
      document.ChangePrices.calc.focus();
      return;
   }
   if ((src_price != '') && (calc != '') && (factor == '')) {
      alert('You must select specify a Factor');
      document.ChangePrices.factor.focus();
      return;
   }
   if ((src_price == '') && (calc == '')) {
      var list_field = document.ChangePrices.dest;
      var label = list_field.options[list_field.selectedIndex].text;
      if (factor == '') factor = '""';
      var prompt = 'Are you sure you want to set the '+label+' to '+factor+'?';
      if (! confirm(prompt)) return;
   }
   top.enable_current_dialog_progress(true);
   var fields = "cmd=updateprices&" + build_form_data(document.ChangePrices);
   call_ajax(script_name,fields,true,finish_update_prices,600);
}

function finish_update_prices(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_shopping_publish()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no '+products_label+' to change shopping publish flags');
      return;
   }
   var selected_rows = products_grid.grid._rowsSelected;
   var ids = '';   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = products_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
      num_selected++;
   }
   products_grid.table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=changeshoppingpublish';
   if (num_selected > 0) url += '&ids=' + ids;
   top.create_dialog('change_shopping_publish',null,null,600,100,false,
                     url,null);
}

function update_shopping_publish()
{
   var select = get_selected_radio_button('select');
   if ((select == 'category') &&
       (! validate_list_field('category','Category',
                              document.ChangeShoppingPublish.category)))
      return;
   if ((select == 'vendor') &&
       (! validate_list_field('vendor','Vendor',
                              document.ChangeShoppingPublish.vendor)))
      return;
   if ((typeof(custom_validate_change_select) != "undefined") &&
       (! custom_validate_change_select(select,document.ChangeShoppingPublish)))
      return;
   top.enable_current_dialog_progress(true);
   var fields = "cmd=updateshoppingpublish&" +
                build_form_data(document.ChangeShoppingPublish);
   call_ajax(script_name,fields,true,finish_update_shopping_publish,600);
}

function finish_update_shopping_publish(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      top.get_content_frame().reload_grid();
      top.close_current_dialog();
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function rebuild_product_cache()
{
   top.display_status("Rebuild Cache","Rebuilding "+product_label +
                      " Cache, Please Wait...",600,100,null);
   call_ajax(script_name,"cmd=rebuildcache",true,
             finish_rebuild_product_cache,0);
}

function finish_rebuild_product_cache(ajax_request)
{
   var status = ajax_request.get_status();
   top.remove_status();
   if (status == 201) alert(product_label+' Cache Rebuilt');
   else if (status == 202) alert(product_label+' Cache Rebuild Submitted');
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function change_tab(tab,content_id)
{
   var show_sep = false;
   if (typeof(enable_image_buttons) != 'undefined') {
      if (content_id == 'image_content') {
         enable_image_buttons(true);   show_sep = true;
      }
      else enable_image_buttons(false);
   }
   if (typeof(enable_callout_buttons) != 'undefined') {
      if (content_id == 'image_content') enable_callout_buttons(true);
      else enable_callout_buttons(false);
   }
   if (top.skin) {
      if (content_id == 'attributes_content') attribute_list.resize(false);
      else if (content_id == 'categories_content') categories.resize(false);
      for (var related_type in related_types) {
         if (content_id == 'related_' + related_type + '_content')
            window['related_' + related_type].resize(false);
      }
   }
   var add_set_button = document.getElementById('add_set');
   var delete_all_button = document.getElementById('delete_all');
   if (content_id == 'attributes_content') {
      add_set_button.style.display = '';
      delete_all_button.style.display = '';
      show_sep = true;
   }
   else {
      add_set_button.style.display = 'none';
      delete_all_button.style.display = 'none';
   }
   if ((content_id != 'inventory_content') &&
       (typeof(enable_inventory_buttons) != "undefined"))
      enable_inventory_buttons(false,false);
   if (typeof(enable_discount_buttons) != "undefined") {
      if (content_id == 'qtydiscounts_content') {
         enable_discount_buttons(true);   show_sep = true;
      }
      else enable_discount_buttons(false);
   }
   if (typeof(enable_price_break_buttons) != "undefined") {
      if (content_id == 'pricebreaks_content') {
         enable_price_break_buttons(true);   show_sep = true;
      }
      else enable_price_break_buttons(false);
   }
   var new_category_button = document.getElementById('new_category');
   if (new_category_button) {
      if (content_id == 'categories_content') {
         new_category_button.style.display = '';   show_sep = true;
      }
      else new_category_button.style.display = 'none';
   }
   var new_product_button = document.getElementById('new_product');
   if (new_product_button) {
      if ((content_id == 'related_content') ||
          (content_id == 'subproducts_content')) {
         new_product_button.style.display = '';   show_sep = true;
      }
      else new_product_button.style.display = 'none';
   }
   var add_review_button = document.getElementById('add_review');
   if (add_review_button) {
      var edit_review_button = document.getElementById('edit_review');
      var delete_review_button = document.getElementById('delete_review');
      if (content_id == 'reviews_content') {
         add_review_button.style.display = '';
         edit_review_button.style.display = '';
         delete_review_button.style.display = '';
         show_sep = true;
      }
      else {
         add_review_button.style.display = 'none';
         edit_review_button.style.display = 'none';
         delete_review_button.style.display = 'none';
      }
   }
   var add_popular_button = document.getElementById('add_popular');
   if (add_popular_button) {
      var delete_popular_button = document.getElementById('delete_popular');
      if (content_id == 'popular_content') {
         add_popular_button.style.display = '';
         delete_popular_button.style.display = '';
         show_sep = true;
      }
      else {
         add_popular_button.style.display = 'none';
         delete_popular_button.style.display = 'none';
      }
   }
   if (typeof(custom_change_product_tab) != "undefined")
      custom_change_product_tab(tab,content_id);
   var product_buttons_row = document.getElementById('product_buttons_row');
   if (product_buttons_row) {
      if (show_sep) product_buttons_row.style.display = '';
      else product_buttons_row.style.display = 'none';
   }
   tab_click(tab,content_id);
   if (content_id == 'product_content') {
      if (typeof(data_grids[1]) != 'undefined')
         data_grids[1].resize_column_headers();
   }
   if (content_id == 'specs_content') {
      if (typeof(specs_tab_onload) != 'undefined') specs_tab_onload();
   }
   else if (content_id == 'inventory_content') inventory_tab_onload();
   else if (content_id == 'image_content') image_tab_onload();
   else if (content_id == 'attributes_content')
      attribute_list.resize_column_headers();
   else if (content_id == 'categories_content')
      categories.resize_column_headers();
   for (var related_type in related_types) {
      if (content_id == 'related_' + related_type + '_content')
         window['related_' + related_type].resize_column_headers();
   }

   top.grow_current_dialog();
   resize_tabs();
}

function convert_data(col,row,text)
{
   if (col == type_column) {
      var type = parse_int(text);
      if (typeof(product_types[type]) == 'undefined') return text;
      return product_types[type];
   }
   else if (col == description_column) return cleanup_grid_html(text);
   else if (col == status_column) {
      var status = parse_int(text);
      if (typeof(product_status_values[status]) == 'undefined') return text;
      return product_status_values[status];
   }
   else if (col == taxable_column) {
      if (parse_int(text) == 1) return 'Yes';
      else return 'No';
   }
   return text;
}

function search_products()
{
   var query = document.SearchForm.query.value.trim();
   if (query == '') {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status("Search","Searching "+products_label+"...",350,100,null);
   window.setTimeout(function() {
      if (typeof(product_search_fields) != 'undefined')
         var where = build_search_query(product_search_fields,query);
      else if (typeof(product_search_where) != 'undefined')
         var where = product_search_where.replace(/\$query\$/g,query);
      else {
         var fields = ['p.name','p.display_name','p.short_description',
                       'p.long_description','p.seo_url','p.id',
                       'p.shopping_gtin','p.shopping_mpn'];
         var where = build_search_query(fields,query);
         if (use_part_numbers)
            where += " or p.id in (select parent from product_inventory where " +
                     build_search_query(['part_number'],query) + ")";
      }
      if ((typeof(top.website) != "undefined") && (top.website != 0))
         where = "(" + where + ") and find_in_set('" + top.website +
                 "',websites)";
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      products_grid.set_where(where);
      products_grid.table.reset_data(false);
      products_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search()
{
   top.display_status("Search","Loading All "+products_label+"...",350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      if ((typeof(top.website) != "undefined") && (top.website != 0))
         var where = 'find_in_set("' + top.website + '",websites)';
      else var where = '';
      if (filter_where) {
         if (where) where = '(' + where + ') and ';
         where += filter_where;
      }
      products_grid.set_where(where);
      products_grid.table.reset_data(false);
      products_grid.grid.refresh();
      top.remove_status();
   },0);
}

function get_product_filter_value(field_name)
{
    var list = document.getElementById(field_name);
    if (! list) return '';
    if (list.selectedIndex == -1) return '';
    return list.options[list.selectedIndex].value;
}

function filter_products()
{
    filter_where = '';
    var product_type = get_product_filter_value('product_type');
    if (product_type) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(p.product_type=' + product_type + ')';
    }
    var status = get_product_filter_value('status');
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(p.status=' + status + ')';
    }
    var vendor = get_product_filter_value('vendor');
    if (vendor) {
       if (filter_where) filter_where += ' and ';
       if (vendor == '~')
          filter_where += '((p.vendor="") or isnull(p.vendor))';
       else filter_where += '(p.vendor=' + vendor + ')';
    }
    if (typeof(get_custom_product_filters) != 'undefined')
       filter_where += get_custom_product_filters();
    var query = document.SearchForm.query.value;
    if (query) search_products();
    else {
       top.display_status('Search','Searching Products...',350,100,null);
       window.setTimeout(function() {
          products_grid.set_where(filter_where);
          products_grid.table.reset_data(false);
          products_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function change_select_product(row)
{
   var sample_image_div = document.getElementById('sample_image_div');
   if (! sample_image_div) return;
   var grid = products_grid.grid;
   var product_id = grid.getCellText(0,row);

   var ajax_request = new Ajax('../cartengine/products.php',
                               'cmd=listimages&id='+product_id,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(list_product_images,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function convert_product_image_filename(filename)
{
   return filename.replace(/&amp;/g,'&');
}

function list_product_images(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   product_images = new Array();
   eval(ajax_request.request.responseText);

   var grid = products_grid.grid;
   var sample_image_div = document.getElementById('sample_image_div');
   sample_image_div.style.width = grid.width + 'px';
   if (product_images.length < 1) {
      sample_image_div.innerHTML = '';   return;
   }
   var html = '';
   var current_time = (new Date()).getTime();
   for (var index in product_images) {
      if (index == '$') continue;
      image = product_images[index];
      if (image) {
         html += '<div id="sample_image_' + index + '" class="sample_image" ' +
                 'onClick="product_image_onclick(' + index + ');" ' +
                 'onDblClick="select_product_image();"><img src="';
         if (dynamic_images) {
            if (dynamic_image_url) html += dynamic_image_url;
            else html += admin_path + 'image.php';
            html += '?cmd=loadimage&filename=' +
                    convert_image_filename(image) + '&size=' +
                    sample_image_size + '&';
         }
         else {
            var filename = convert_product_image_filename(image);
            if (image_subdir_prefix > 0)
               filename = filename.substr(0,image_subdir_prefix)+'/'+filename;
            html += '../images/' + sample_image_size + '/' + filename + '?';
         }
         html += 'v=' + current_time;
         html += '"></div>';
      }
   }
   sample_image_div.innerHTML = html;
}

function product_image_onclick(index)
{
   if (selected_product_image != -1) {
      var sample_image = document.getElementById('sample_image_' +
                                                 selected_product_image);
      if (sample_image) sample_image.className = 'sample_image';
   }
   if (index != -1) {
      var sample_image = document.getElementById('sample_image_' + index);
      if (sample_image)
         sample_image.className = 'sample_image selected_sample_image';
   }
   selected_product_image = index;
}

function select_product_image()
{
   if (selected_product_image == -1) {
      alert('You must select an image');   return;
   }
   var image = product_images[selected_product_image];
   var iframe = top.get_dialog_frame(select_frame).contentWindow;
   iframe.process_select_product_image(image);
   top.close_current_dialog();
}

function select_product()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no products to select');   return;
   }
   var iframe = top.get_dialog_frame(select_frame).contentWindow;
   var grid = products_grid.grid;
   var selected_rows = products_grid.grid._rowsSelected;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      if (typeof(get_select_product_data) != 'undefined')
         var product_data = get_select_product_data(grid,grid_row);
      else var product_data = {
         id: grid.getCellText(0,grid_row),
         name: grid.getCellText(1,grid_row),
         description: grid.getCellText(2,grid_row),
         status: grid.getCellText(3,grid_row),
         display_name: grid.getCellText(4,grid_row),
         part_number: grid.getCellText(5,grid_row),
         list_price: grid.getCellText(6,grid_row),
         price: grid.getCellText(7,grid_row),
         sale_price: grid.getCellText(8,grid_row),
         price_break_type: grid.getCellText(9,grid_row),
         cost: grid.getCellText(10,grid_row),
         account_discount: grid.getCellText(11,grid_row),
         flags: grid.getCellText(12,grid_row),
         order_name: grid.getCellText(13,grid_row)
      };
      if (typeof(inventory_grid) != 'undefined') {
         if (inventory_grid.table._num_rows < 1) {
            alert('There are no inventory items to select');   return;
         }
         var inv_grid = inventory_grid.grid;
         var inv_grid_row = inv_grid.getCurrentRow();
         product_data.inv_id = inv_grid.getCellText(0,inv_grid_row);
         product_data.attributes = inv_grid.getCellText(1,inv_grid_row);
         product_data.part_number = inv_grid.getCellText(2,inv_grid_row);
      }
      if (select_product_change_function)
         iframe.window[select_product_change_function](product_data);
      else iframe.change_product(product_data);
   }
   if (typeof(iframe.finish_change_product) != 'undefined')
      iframe.finish_change_product();
   top.close_current_dialog();
}

function add_attribute_set()
{
   if (document.AddProduct) {
      var product_id = document.AddProduct.id.value;
      var frame = 'add_product';
   }
   else {
      var product_id = document.EditProduct.id.value;
      var frame = 'edit_product';
   }
   var url = '../cartengine/attributes.php?cmd=selectset&Product=' +
             product_id + '&Frame=' + frame;
   top.create_dialog('attribute_sets',null,null,530,300,false,url,null);
}

function delete_all_attributes()
{
    var response = confirm('Are you sure you want to delete all attributes ' +
                           'for this product?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    if (document.AddProduct) var product_id = document.AddProduct.id.value;
    else var product_id = document.EditProduct.id.value;
    call_ajax('products.php','cmd=deleteallattributes&id=' + product_id,true,
              finish_delete_all_attributes);
}

function finish_delete_all_attributes(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) attribute_list.reload_left_sublist_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function new_category()
{
   cancel_add_category = true;
   top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   var ajax_request = new Ajax(url + categories_script_name,
                               "cmd=createcategory",true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_add_category,null);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_add_category(ajax_request,data)
{
   if (ajax_request.state != 4) return;

   top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var category_id = -1;
   eval(ajax_request.request.responseText);

   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += categories_script_name+'?cmd=addcategory&id=' + category_id;
   if (update_window == '') update_window = categories.frame_name;
   var dialog_name = 'add_category_' + (new Date()).getTime();
   url += '&frame='+dialog_name+'&sublist='+categories.name +
          '&side=both&updatewindow='+update_window;
   top.create_dialog(dialog_name,null,null,900,500,false,url,null);
}

function add_product_data_grid(id,data_type,url,frame,form_name,single_label,
                               multi_label,height,no_prompt)
{
   var grid_size = get_default_grid_size();
   if (top.skin) var grid_width = -100;
   else if (no_prompt) var grid_width = grid_size.width - 150;
   else var grid_width = grid_size.width - 230;
   if (top.skin) var value_width = 250;
   else var value_width = grid_width - 250;
   if ((! top.skin) && (navigator.userAgent.indexOf('MSIE') != -1))
      grid_width -= 135;
   var data_grid = new Grid("images",grid_width,height);
   data_grid.set_columns(["Id","Seq","Label","Value"]);
   data_grid.set_column_widths([0,30,200,value_width]);
   data_grid.set_field_names(["id","sequence","label","data_value"]);
   var query = "select id,sequence,label,data_value from product_data";
   data_grid.set_query(query);
   data_grid.set_where("(data_type=" + data_type + ") and (parent=" + id + ")");
   data_grid.set_order_by("sequence");
   data_grid.set_id('data_grid_' + data_type);
   data_grid.load(true);
   data_grid.set_double_click_function(function() { edit_data(data_type); });
   add_onload_function(function() {
      data_grid.insert('product_data_' + data_type + '_cell');
   },0);
   data_grid.single_label = single_label;
   data_grid.multi_label = multi_label;
   data_grid.script_url = url;
   data_grid.frame = frame;
   data_grid.form = form_name;
   data_grids[data_type] = data_grid;
   data_grid_info[num_data_grids++] = { 'type':data_type,'no_prompt':no_prompt };
}

function resequence_data(data_type,old_row,new_row)
{
   if (old_row == new_row) return true;
   var id = document.forms[data_grids[data_type].form].id.value;
   var old_sequence = data_grids[data_type].grid.getCellText(1,old_row);
   var new_sequence = data_grids[data_type].grid.getCellText(1,new_row);
   if (old_sequence == '')
      old_sequence = -data_grids[data_type].grid.getCellText(0,old_row)
   if (new_sequence == '')
      new_sequence = -data_grids[data_type].grid.getCellText(0,new_row)
   call_ajax(data_grids[data_type].script_url,"cmd=resequencedata&DataType=" +
             data_type + "&Parent=" + id + "&OldSequence=" + old_sequence +
             "&NewSequence=" + new_sequence,true,function (ajax_request) {
                finish_resequence_data(ajax_request,data_type,new_row);
             });
}

function finish_resequence_data(ajax_request,data_type,new_row)
{
   var status = ajax_request.get_status();
   if (status != 201) {
      if ((status >= 200) && (status < 300)) ajax_request.display_error();
      return false;
   }
   data_grids[data_type].table.reset_data(true);
   data_grids[data_type].grid.refresh(); 
   data_grids[data_type].grid.setSelectedRows([new_row]);
   data_grids[data_type].grid.setCurrentRow(new_row);
   return true;
}

function move_data_top(data_type)
{
   if (data_grids[data_type].table._num_rows < 1) return;
   var grid_row = parse_int(data_grids[data_type].grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_data(data_type,grid_row,0);
}

function move_data_up(data_type)
{
   if (data_grids[data_type].table._num_rows < 1) return;
   var grid_row = parse_int(data_grids[data_type].grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_data(data_type,grid_row,grid_row - 1);
}

function move_data_down(data_type)
{
   var num_rows = data_grids[data_type].table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(data_grids[data_type].grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_data(data_type,grid_row,grid_row + 1);
}

function move_data_bottom(data_type)
{
   var num_rows = data_grids[data_type].table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(data_grids[data_type].grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_data(data_type,grid_row,num_rows - 1);
}

function add_data(data_type)
{
   var id = document.forms[data_grids[data_type].form].id.value;
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   var label = data_grids[data_type].single_label;
   url += data_grids[data_type].script_url + '?cmd=adddata&DataType=' +
          data_type + '&Parent=' + id + '&Frame=' + data_grids[data_type].frame +
          '&Label=' + encodeURIComponent(label);
   var dialog_width = 515 + ((label.length + 4) * 7);
   if (data_type == 1) dialog_width += 20;
   top.create_dialog('add_data',null,null,dialog_width,100,false,url,null);
}

function edit_data(data_type)
{
   if (data_grids[data_type].table._num_rows < 1) {
      alert('There are no ' + data_grids[data_type].multi_label + ' to edit');
      return;
   }
   var grid_row = data_grids[data_type].grid.getCurrentRow();
   var id = data_grids[data_type].grid.getCellText(0,grid_row);
   data_grids[data_type].table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += data_grids[data_type].script_url + '?cmd=editdata&id=' + id +
             '&Frame=' + data_grids[data_type].frame + '&Label=' +
             encodeURIComponent(data_grids[data_type].single_label);
   var dialog_width = 550;
   if (data_type == 1) dialog_width += 20;
   top.create_dialog('edit_data',null,null,dialog_width,100,false,url,null);
}

function delete_data(data_type)
{
   if (data_grids[data_type].table._num_rows < 1) {
      alert('There are no ' + data_grids[data_type].multi_label + ' to delete');
      return;
   }
   var grid_row = data_grids[data_type].grid.getCurrentRow();
   var id = data_grids[data_type].grid.getCellText(0,grid_row);
   var label = data_grids[data_type].grid.getCellText(2,grid_row);
   var value = data_grids[data_type].grid.getCellText(3,grid_row);
   if (! label) label = value;
   var response = confirm('Are you sure you want to delete the ' +
                          data_grids[data_type].single_label + ' "' +
                          label + '"?');
   if (! response) return;
   data_grids[data_type].table.save_position();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += data_grids[data_type].script_url;
   call_ajax(url,"cmd=deletedata&id=" + id + "&Label=" +
             encodeURIComponent(data_grids[data_type].single_label),true,
             function (ajax_request) {
                finish_delete_data(ajax_request,data_type);
             });
}

function finish_delete_data(ajax_request,data_type)
{
   var status = ajax_request.get_status();
   if (status == 201) {
      data_grids[data_type].table.reset_data(true);
      data_grids[data_type].grid.refresh();
      window.setTimeout(function() {
         data_grids[data_type].table.restore_position();
      },0);
   }
   else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function export_inventory()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no products to export inventory for');   return;
   }
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=exportinventory';
   top.create_dialog('export_inventory',null,null,450,85,false,url,null);
}

function import_inventory()
{
   if (products_grid.table._num_rows < 1) {
      alert('There are no products to import inventory for');   return;
   }
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=importinventory';
   top.create_dialog('import_inventory',null,null,410,85,false,url,null);
}

function create_activity_grid(parent)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    activity_grid = new Grid('product_activity',grid_width,650);
    activity_grid.table.set_field_names([]);
    activity_grid.set_columns(['Id','Date','Activity']);
    activity_grid.set_column_widths([0,140,1000]);
    var query = 'select id,activity_date,activity from product_activity';
    activity_grid.set_query(query);
    activity_grid.set_where('parent=' + parent);
    activity_grid.table.set_order_by('activity_date desc,id desc');
    activity_grid.table.set_convert_cell_data(convert_activity_data);
    activity_grid.set_id('activity_grid');
    activity_grid.load(false);
    activity_grid.display();
}

function convert_activity_data(col,row,text)
{
    if (col == 1) {
       if (text == '') return text;
       var activity_date = new Date(parse_int(text) * 1000);
       return activity_date.format('mm/dd/yyyy hh:MM:ss tt');
    }
    return text;
}

function set_activity_parent(parent)
{
    activity_grid.set_where('parent='+parent);
    activity_grid.table.reset_data(false);
    activity_grid.grid.refresh();
}

function create_reviews_grid(product_id)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 710;
    reviews_grid = new Grid("reviews",grid_width,product_dialog_height - 75);
    reviews_grid.set_columns(["ID","Status","Created","First Name",
                              "Last Name","Email","Subject","Rating"]);
    reviews_grid.set_column_widths([0,80,70,100,100,150,200,50]);
    var query = "select id,status,create_date,firstname,lastname,email," +
                "subject,rating from reviews";
    reviews_grid.set_query(query);
    reviews_grid.set_where('parent='+product_id);
    reviews_grid.set_order_by("create_date desc");
    reviews_grid.set_id('reviews_grid');
    reviews_grid.table.set_convert_cell_data(convert_review_data);
    reviews_grid.load(true);
    reviews_grid.set_double_click_function(edit_review);
    reviews_grid.display();
}

function set_reviews_parent(parent)
{
    reviews_grid.set_where('parent='+parent);
    reviews_grid.table.reset_data(true);
    reviews_grid.grid.refresh();
}

function reload_reviews_grid()
{
    reviews_grid.table.reset_data(true);
    reviews_grid.grid.refresh();
    window.setTimeout(function() { reviews_grid.table.restore_position(); },0);
}

function add_review()
{
    reviews_grid.table.save_position();
    if (document.AddProduct) {
      var form = document.AddProduct;   var frame = 'add_product';
    }
    else {
       var form = document.EditProduct;   var frame = 'edit_product';
    }
    var product_id = form.id.value;
    if ((typeof(top.current_tab) == "undefined") &&
       (typeof(admin_path) != "undefined")) var url = admin_path;
    else var url = script_prefix;
    url += 'reviews.php?cmd=addreview&frame='+frame+'&parent='+product_id;
    top.create_dialog('add_review',null,null,660,480,false,url,null);
}

function edit_review()
{
    if (reviews_grid.table._num_rows < 1) {
       alert('There are no reviews to edit');   return;
    }
    var grid_row = reviews_grid.grid.getCurrentRow();
    var id = reviews_grid.grid.getCellText(0,grid_row);
    reviews_grid.table.save_position();
    if (document.AddProduct) var frame = 'add_product';
    else var frame = 'edit_product';
    if ((typeof(top.current_tab) == "undefined") &&
       (typeof(admin_path) != "undefined")) var url = admin_path;
    else var url = script_prefix;
    url += 'reviews.php?cmd=editreview&id=' + id + '&frame=' + frame;
    top.create_dialog('edit_review',null,null,660,480,false,url,null);
}

function delete_review()
{
    if (reviews_grid.table._num_rows < 1) {
       alert('There are no reviews to delete');   return;
    }
    var grid_row = reviews_grid.grid.getCurrentRow();
    var id = reviews_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this review?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    reviews_grid.table.save_position();
    if ((typeof(top.current_tab) == "undefined") &&
       (typeof(admin_path) != "undefined")) var url = admin_path;
    else var url = script_prefix;
    call_ajax(url + "reviews.php","cmd=deletereview&id=" + id,true,
              finish_delete_review);
}

function finish_delete_review(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_reviews_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

var review_status_values = ['Pending','Approved','Disapproved'];

function convert_review_data(col,row,text)
{
    if (col == 1) return review_status_values[parse_int(text)];
    if (col == 2) {
       if (text == '') return text;
       var review_date = new Date(parse_int(text) * 1000);
       var date_string = (review_date.getMonth() + 1) + "/" +
                         review_date.getDate() + "/" +
                         review_date.getFullYear();
       return date_string;
    }
    return text;
}

function add_popular_product()
{
    var table = document.getElementById('popular_table');
    var num_popular = parseInt(document.EditProduct.NumPopular.value);
    var new_row = table.insertRow(num_popular + 1);
    new_row.id = 'popular_row_' + num_popular;
    new_row.vAlign = 'middle';
    var column = 0;

    var new_cell = new_row.insertCell(column++);
    new_cell.align = 'center';
    new_cell.innerHTML = '<input type="radio" class="radio" ' +
                         'name="popular_sel" id="popular_sel" value="' +
                         num_popular + '">'; 

    var new_cell = new_row.insertCell(column++);
    new_cell.align = 'left';
    new_cell.innerHTML = '<input type="text" class="text" ' +
                         'name="popular_name_' + num_popular +
                         '" id="popular_name_' + num_popular + '" size="30">';

    for (var index in popular_attributes) {
       var new_cell = new_row.insertCell(column++);
       new_cell.align = 'center';
       html = '<select class="select" name="popular_attr_' + num_popular +
              '_' + index + '" id="popular_attr_' + num_popular + '_' +
              index + '">';
       html += '<option value="" selected></option>';
       var options = popular_attributes[index];
       for (var option_index in options)
          html += '<option value="' + option_index + '">' +
                  options[option_index] + '</option>';
       html += '</select>';
       new_cell.innerHTML = html;
    }

    var new_cell = new_row.insertCell(column++);
    new_cell.align = 'left';
    html = '<select class="select" name="popular_image_' + num_popular +
           '" id="popular_attr_' + num_popular + '">';
    html += '<option value="" selected></option>';
    var num_images = images_grid.table._num_rows;
    for (var loop = 0;  loop < num_images;  loop++) {
       var filename = images_grid.grid.getCellText(3,loop);
       html += '<option value="' + filename + '">' + filename + '</option>';
    }
    html += '</select>';
    new_cell.innerHTML = html;

    num_popular++;
    document.EditProduct.NumPopular.value = num_popular;
}

function delete_popular_product()
{
    var selected = get_selected_radio_button('popular_sel');
    if (selected === '') {
       alert('You must select a Popular product to delete');   return;
    }
    selected = parseInt(selected);
    var table = document.getElementById('popular_table');
    table.deleteRow(selected + 1);
}

function select_shopping_field(field_name,field_label,dialog_width)
{
   if (document.AddProduct) {
      var form = document.AddProduct;   var frame = 'add_product';
   }
   else {
      var form = document.EditProduct;   var frame = 'edit_product';
   }
   var shopping_field = form[field_name].value;
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=shoppingfield&Frame=' + frame + '&Field=' +
          field_name + '&Label=' + encodeURIComponent(field_label) +
          '&Value=' + encodeURIComponent(shopping_field);
   top.create_dialog('shopping_field',null,null,dialog_width,500,false,
                     url,null);
}

function shopping_field_onload(current_row)
{
   window.setTimeout(function() {
      var scroll_top = current_row - 10;
      if (scroll_top < 0) scroll_top = 0;
      shopping_field_grid.grid.setScrollTop(scroll_top);
      shopping_field_grid.table.set_current_cell(0,current_row);
   },0);
}

var shopping_field_id_field = 'id';
var shopping_field_label_field = 'label';

function load_shopping_field_grid(table,id_field,label_field,label_width)
{
    var grid_size = get_default_grid_size();
    var query = 'select ' + id_field + ' as id,' + label_field +
                ' as label from ' + table;
    shopping_field_id_field = id_field;
    shopping_field_label_field = label_field;
    shopping_field_grid = new Grid(table,grid_size.width,grid_size.height);
    shopping_field_grid.table.set_field_names(['id','name']);
    shopping_field_grid.set_columns(['Id','Name']);
    shopping_field_grid.set_column_widths([0,label_width]);
    shopping_field_grid.table.set_query(query);
    shopping_field_grid.table.set_order_by(label_field);
    shopping_field_grid.set_id('shopping_field_grid');
    shopping_field_grid.load(false);
    shopping_field_grid.set_double_click_function(choose_shopping_field_value);
    shopping_field_grid.display();
}

function choose_shopping_field_value()
{
   if (document.SelectValue.Value) {
      var list_field = document.SelectValue.Value;
      if (list_field.selectedIndex == -1) {
         var value = '';   var text = '';
      }
      var value = list_field.options[list_field.selectedIndex].value;
      var text = list_field.options[list_field.selectedIndex].text;
   }
   else {
      if (shopping_field_grid.table._num_rows < 1) {
         alert('There are no values to select');   return;
      }
      var grid_row = shopping_field_grid.grid.getCurrentRow();
      var value = shopping_field_grid.grid.getCellText(0,grid_row);
      var text = shopping_field_grid.grid.getCellText(1,grid_row);
   }
   var frame = document.SelectValue.Frame.value;
   var field_name = document.SelectValue.Field.value;
   var iframe = top.get_dialog_frame(frame).contentWindow;
   if (document.SelectValue.Form) {
      var form_name = document.SelectValue.Form.value;
      var form = iframe.document[form_name];
   }
   else if (iframe.document.AddProduct) var form = iframe.document.AddProduct;
   else var form = iframe.document.EditProduct;
   form[field_name].value = value;
   if (iframe['update_' + field_name])
      iframe['update_' + field_name](value,text);
   top.close_current_dialog();
}

function search_shopping_fields()
{
   var label = document.SelectValue.Label.value;
   var query = document.SearchForm.query.value.trim();
   if (query == '') {
      reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   top.display_status('Search','Searching '+label+'s...',350,100,null);
   window.setTimeout(function() {
      var where = shopping_field_id_field + ' like "%' + query + '%" or ' +
                  shopping_field_label_field + ' like "%' + query + '%"';
      shopping_field_grid.set_where(where);
      shopping_field_grid.table.reset_data(false);
      shopping_field_grid.grid.refresh();
      top.remove_status();
   },0);
}

function reset_search_shopping_fields()
{
   var label = document.SelectValue.Label.value;
   top.display_status('Search','Loading All '+label+'s...',350,100,null);
   window.setTimeout(function() {
      document.SearchForm.query.value = '';
      shopping_field_grid.set_where('');
      shopping_field_grid.table.reset_data(false);
      shopping_field_grid.grid.refresh();
      top.remove_status();
   },0);
}

function select_change_category()
{
   set_radio_button('select','category');
}

function select_change_vendor()
{
   set_radio_button('select','vendor');
}

function check_all_fields()
{
    var fields = document.getElementsByTagName('input');
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == 'checkbox') &&
           (fields[loop].name.substr(0,6) == 'field_') &&
           (! fields[loop].disabled))
          fields[loop].checked = true;
    }
}

function uncheck_all_fields()
{
    var fields = document.getElementsByTagName('input');
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == 'checkbox') &&
           (fields[loop].name.substr(0,6) == 'field_') &&
           (! fields[loop].disabled))
          fields[loop].checked = false;
    }
    return true;
}

