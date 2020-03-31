/*
        Inroads Shopping Cart - Products Tab - Inventory SubTab JavaScript Functions

                            Written 2008-2019 by Randall Severy
                             Copyright 2008-2019 Inroads, LLC
*/

var inventory_grid;
var inv_links_grid;
var link_inventory_grid;
var attributes = [];
var options = [];
var column_widths = null;
var attr_columns = [];
var num_attributes = 0;
var initial_inventory_grid_load = false;
var initial_attributes;
var current_attributes;
var dynamic_columns = false;
var dynamic_no_options = false;
var rows_changed = false;
var num_rows;
var num_columns;
var opened_combo_col = null;
var opened_combo_row = null;
var checkbox_template = null;
var avail_column = -1;
var backorder_column = -1;
var edit_inventory_width = 450;

function get_inventory_column_names()
{
   var column_names = new Array();
   column_names[0] = 'Id';
   column_names[1] = 'Seq';
   column_names[2] = 'Attributes';
   var index = 3;
   for (var id in attributes) column_names[index++] = attributes[id];
   for (var loop = 0;  loop < col_names.length;  loop++)
      column_names[index++] = col_names[loop];
   num_columns = index;
   return column_names;
}

function get_inventory_field_names()
{
   var field_names = new Array();
   field_names[0] = 'id';
   field_names[1] = 'sequence';
   field_names[2] = 'attributes';
   var index = 3;
   var offset = 0;
   for (var id in attributes) {
      field_names[index++] = 'attr_' + offset;
      offset++;
   }
   for (var loop = 0;  loop < fld_names.length;  loop++)
      field_names[index++] = fld_names[loop];
   return field_names;
}

function get_inventory_column_widths()
{
   var column_widths = new Array();
   column_widths[0] = 0;
   column_widths[1] = 30;
   column_widths[2] = 0;
   var index = 3;
   for (var id in attributes) {
      var column_width = (attributes[id].length * 6) + 10;
      var max_option_length = 0;
      var num_options = 1;
      for (var option_index in options[id]) {
         var option_length = (options[id][option_index][1].length * 6) + 10;
         if (option_length > max_option_length) max_option_length = option_length;
         num_options++;
      }
      if (num_options > 6) max_option_length += 20;
      if (max_option_length > column_width) column_width = max_option_length;
      column_widths[index++] = column_width;
   }
   for (var loop = 0;  loop < col_widths.length;  loop++)
      column_widths[index++] = col_widths[loop];
   return column_widths;
}

function create_inventory_grid(parent)
{
   if (top.skin) var grid_width = -100;
   else var grid_width = 710;
   inventory_grid = new Grid('product_inventory',grid_width,
                             product_dialog_height - 75);
   var field_names = get_inventory_field_names();
   inventory_grid.table.set_field_names(field_names);
   var column_names = get_inventory_column_names();
   inventory_grid.set_columns(column_names);
   column_widths = get_inventory_column_widths();
   inventory_grid.set_column_widths(column_widths);
   inventory_grid.set_order_by('sequence');
   inventory_grid.table.set_convert_cell_data(convert_inventory_data);
   inventory_grid.table.set_convert_cell_update(convert_inventory_update);
   inventory_grid.table._url = 'products.php';
   inventory_grid.table.setURL(inventory_grid.table._url);
   inventory_grid.table.setParameter('cmd','updateinventorydata');
   inventory_grid.table.setParameter('parent',parent);
   inventory_grid.table.add_update_parameter('cmd','updateinventorydata');
   inventory_grid.table.add_update_parameter('parent',parent);
   inventory_grid.load(false);
   inventory_grid.grid.setId('inventory_grid');
   inventory_grid.table.set_table_size(1,inventory_grid.columns.length);
   inventory_grid.grid.setCellEditable(true);
   inventory_grid.grid.setSelectionMode('single-cell');
   inventory_grid.set_double_click_function(edit_inventory);
   inventory_grid.grid.setVirtualMode(false);
   inventory_grid.grid.onHeaderClicked = function() {
      window.setTimeout(refresh_dynamic_columns,0);
   }
   set_grid_navigation(inventory_grid.grid);
   add_onload_function(function() {
      inventory_grid.insert('inventory_grid_div');
      initial_inventory_grid_load = true;
      load_inventory_grid(parent,false);
   },0);
   if (typeof(custom_update_inventory_grid) != 'undefined')
      custom_update_inventory_grid();
}

function enable_inventory_buttons(enable_flag,edit_enable_flag)
{
   var add_inventory_button = document.getElementById('add_inventory');
   var edit_inventory_button = document.getElementById('edit_inventory');
   var delete_inventory_button = document.getElementById('delete_inventory');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_inventory_button.style.display = display_style;
   if (edit_enable_flag)
      edit_inventory_button.style.display = '';
   else edit_inventory_button.style.display = 'none';
   delete_inventory_button.style.display = display_style;
   if (enable_flag) {
      var product_buttons_row = document.getElementById('product_buttons_row');
      if (product_buttons_row) product_buttons_row.style.display = '';
   }

}

function set_inventory_parent(parent)
{
   inventory_grid.table.setParameter('parent',parent);
   inventory_grid.table.add_update_parameter('parent',parent);
   initial_inventory_grid_load = true;
   load_inventory_grid(parent,false);
}

function load_inventory_grid(parent,refresh_flag)
{
   if (! initial_inventory_grid_load) top.enable_current_dialog_progress(true);
   var ajax_request = new Ajax('products.php','cmd=loadinventory&parent=' + parent,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(finish_load_inventory,refresh_flag);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function set_inventory_columns(num_attributes,dynamic)
{
   for (var loop = 0;  loop < num_attributes;  loop++)
      inventory_grid.grid.setCellEditable(dynamic,loop + 3);
   for (var loop = 0;  loop < col_names.length;  loop++)
      inventory_grid.grid.setCellEditable(true,loop + 3 + num_attributes);
}

function getCSSRule(ruleName,deleteFlag)
{
   ruleName=ruleName.toLowerCase();
   if (document.styleSheets) {
      for (var i=0; i<document.styleSheets.length; i++) {
         var styleSheet=document.styleSheets[i];
         var ii=0;
         var cssRule=false;
         do {
            if (styleSheet.cssRules) cssRule = styleSheet.cssRules[ii];
            else cssRule = styleSheet.rules[ii];
            if (cssRule)  {
               if (cssRule.selectorText &&
                   (cssRule.selectorText.toLowerCase() == ruleName)) {
                  if (deleteFlag == 'delete') {
                     if (styleSheet.cssRules) styleSheet.deleteRule(ii);
                     else styleSheet.removeRule(ii);
                     return true;
                  }
                  else return cssRule;
               }
            }
            ii++;
         } while (cssRule)
      }
   }
   return false;
}

function addCSSRule(ruleName)
{
   if (document.styleSheets) {
      if (! getCSSRule(ruleName)) {
         var last_sheet = document.styleSheets.length - 1;
         var stylesheet = document.styleSheets[last_sheet];
         if (stylesheet.addRule) stylesheet.addRule(ruleName,null);
         else stylesheet.insertRule(ruleName+' { }',
                                    stylesheet.cssRules.length);
      }
   }
   return getCSSRule(ruleName);
}

function enable_dynamic_columns()
{
   try {
   dynamic_columns = true;
   column_widths = get_inventory_column_widths();
   var index = 3;
   for (var id in attributes) {
      if (options[id].length == 0) {
         dynamic_no_options = true;
         index++;   continue;
      }
      var combo = new AW.UI.Combo;
      combo.setId('combo_'+index);
      var num_options = 1;
      var item_values = [];
      item_values[0] = -1;
      var item_text = [];
      item_text[0] = '';
      for (var option_index in options[id]) {
         item_values[num_options] = options[id][option_index][0];
         item_text[num_options] = options[id][option_index][1];
         num_options++;
      }
      combo.setItemValue(item_values);
      combo.setItemText(item_text);
      combo.setItemCount(num_options);
      combo.getPopupTemplate().setStyle('width',''+column_widths[index]+'px');
      var select_height = num_options * 17;
      if (select_height > product_dialog_height)
         select_height = product_dialog_height;
      combo.getPopupTemplate().setStyle('height',''+select_height+'px');
      var edit_box = combo.getContent('box/text');
      edit_box.setAttribute('readonly',true);
      edit_box.setEvent('onclick',function() { this.showPopup(); });
      combo.AW_showPopup = combo.showPopup;
      combo.showPopup = function() {
         inventory_grid.grid.setCurrentRow(this.$1);
         if ((this.$0 == opened_combo_col) && (this.$1 == opened_combo_row))
            return;
         this.AW_showPopup();
         var selected_item = this.getSelectedItems();
         var AW_onCurrentItemChanged = this.onCurrentItemChanged;
         this.onCurrentItemChanged = null;
         this.setCurrentItem(selected_item);
         this.onCurrentItemChanged = AW_onCurrentItemChanged;
         opened_combo_col = this.$0;
         opened_combo_row = this.$1;
      }
      combo.onControlEditStarted = function() {
         var element = this.getContent('box/text').element();
         element.contentEditable = false;
      }
      combo.onItemClicked = function(event,i) {
         try {
            var value = this.getItemValue(i);
            var text = this.getItemText(i);
            this.setControlValue(value);
            this.setControlText(text);
            this.$owner.table.process_cell_change(this.$owner.table,value,this.$0,this.$1);
//            this.hidePopup();
            AW.$popup.hidePopup();
            opened_combo_col = null;
            opened_combo_row = null;
         } catch(e) {
            alert('Script Error (combo.onItemClicked): '+e.message);
         }
      }
      combo.AW_refresh = combo.refresh;
      combo.refresh = function() {
         var col = this.$0;
         var value = this.$owner.table.getData(col,this.$1);
         var id = attr_columns[col - 3];
         var option_id = parse_int(value);
         var text = '';
         for (var option_index in options[id]) {
            if (options[id][option_index][0] == option_id) {
               text = options[id][option_index][1];   break;
            }
         }
         if (! text) text = '';
         var combo_length = this.getItemCount();
         for (var loop = 0;  loop < combo_length;  loop++)
            if (this.getItemValue(loop) == value) {
               this.setSelectedItems([loop]);
               this.setCurrentItem(loop);
            }
         this.setControlValue(value);
         this.setControlText(text);
         this.AW_refresh();
      }
//      var combo_style = addCSSRule('#' + combo.getId() + ' .aw-edit-control');
//      combo_style.style.background='highlight';
      inventory_grid.grid.setCellTemplate(combo,index); 
      index++;
   }
   } catch(e) {
      alert('Script Error (enable_dynamic_columns): '+e.message);
   }
}

function setup_available_column()
{
   var avail_index = fld_names.indexOf('available');
   if (avail_index == -1) return;
   avail_column = avail_index + num_attributes + 3;
   if (! checkbox_template) {
      checkbox_template = new AW.Templates.Checkbox;
      var checkbox = new AW.HTML.SPAN;
      checkbox.setContent('html',function() { return ''; });
      checkbox_template.setContent('box/text',checkbox);
   }
   var column_style = addCSSRule('#inventory_grid .aw-column-' +
                                 avail_column);
   column_style.style.textAlign = 'center';
   inventory_grid.grid.setCellTemplate(checkbox_template,avail_column);
   inventory_grid.grid.setCellEditable(false,avail_column);
}

function setup_backorder_column()
{
   var backorder_index = fld_names.indexOf('backorder');
   if (backorder_index == -1) return;
   backorder_column = backorder_index + num_attributes + 3;
   if (! checkbox_template) {
      checkbox_template = new AW.Templates.Checkbox;
      var checkbox = new AW.HTML.SPAN;
      checkbox.setContent('html',function() { return ''; });
      checkbox_template.setContent('box/text',checkbox);
   }
   var column_style = addCSSRule('#inventory_grid .aw-column-' +
                                 backorder_column);
   column_style.style.textAlign = 'center';
   inventory_grid.grid.setCellTemplate(checkbox_template,backorder_column);
   inventory_grid.grid.setCellEditable(false,backorder_column);
}

function refresh_dynamic_columns()
{
   var last_row = document.getElementById('inventory_grid-row-' + (num_rows - 1));
   if (! last_row) {
      window.setTimeout(refresh_dynamic_columns,1);   return;
   }
   for (var loop = 0;  loop < num_attributes;  loop++)
      for (var row = 0;  row < num_rows;  row++)
         inventory_grid.grid.getRowTemplate(row).getItemTemplate(loop + 3).refresh();
}

function finish_load_inventory(ajax_request,refresh_flag)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status != 200) return;

   attributes = [];
   options = [];
   attr_columns = [];
   num_attributes = 0;
   num_rows = 1;
   rows_changed = false;

   eval(ajax_request.request.responseText);

   inventory_grid.table.reset_row_count(num_rows);
   inventory_grid.table._row_data = null;
   inventory_grid.table._updates = [];
   inventory_grid.table._current_row = -1;
   inventory_grid.grid.setCellText([]);
   for (var id in attributes) attr_columns[num_attributes++] = id;
   var field_names = get_inventory_field_names();
   inventory_grid.table.set_field_names(field_names);
   var column_names = get_inventory_column_names();
   inventory_grid.set_columns(column_names);
   inventory_grid.table.set_table_size(num_rows,inventory_grid.columns.length);
   inventory_grid.table.setParameter('numattributes',num_attributes);
   inventory_grid.table.add_update_parameter('numattributes',num_attributes);
   if (dynamic_no_options)
      inventory_grid.table.add_update_parameter('nooptions',true);
   if (! dynamic_columns) column_widths = get_inventory_column_widths();
   inventory_grid.set_column_widths(column_widths);
   inventory_grid.grid.setHeaderText(column_names);
   process_column_widths(column_widths,inventory_grid.grid);
   if ((! (features & MAINTAIN_INVENTORY)) || enable_inventory_available)
      setup_available_column();
   if (enable_inventory_backorder) setup_backorder_column();
   inventory_grid.grid.refresh();
   if (refresh_flag && dynamic_columns) refresh_dynamic_columns();
   if (initial_inventory_grid_load) {
      initial_inventory_grid_load = false;
      initial_attributes = current_attributes;
   }
   else {
      top.enable_current_dialog_progress(false);
      inventory_grid.grid.focus_start_cell();
   }
   if (typeof(custom_finish_load_inventory) != 'undefined')
      custom_finish_load_inventory();
}

function delete_old_inventory(parent)
{
   var status = call_ajax('products.php','cmd=deleteinventory&parent=' +
                          parent,true);
   return status;
}

function convert_inventory_data(col,row,text)
{
   if ((col == avail_column) || (col == backorder_column)) {
      if (parse_int(text) == 1) return true;
      else return false;
   }
   if (dynamic_columns || (num_attributes == 0) || (col < 3) ||
       (col > (num_attributes + 2)))
      return text;

   if (typeof(attr_columns[col - 3]) == 'undefined') return '';
   var id = attr_columns[col - 3];
   if (typeof(options[id]) == 'undefined') return '';
   var option_id = parse_int(text);
   var text = '';
   for (var option_index in options[id]) {
      if (options[id][option_index][0] == option_id) {
         text = options[id][option_index][1];   break;
      }
   }
   return text;
}

function convert_inventory_update(col,row,value)
{
   if ((col == avail_column) || (col == backorder_column)) {
      if (value) new_value = 1;
      else new_value = 0;
      return new_value;
   }
   return value;
}

function inventory_tab_onload()
{
   inventory_grid.resize_column_headers();
   if (dynamic_columns) {
      enable_inventory_buttons(true,true);
      refresh_dynamic_columns();
   }
   else enable_inventory_buttons(false,true);
   if (attribute_list && attribute_list.changed) {
      var parent = document.forms[attribute_list.form_name].id.value;
      load_inventory_grid(parent,false);
      attribute_list.changed = false;
   }
   else inventory_grid.grid.focus_start_cell();
}

function add_inventory()
{
   var highest_sequence = -1;
   for (var loop = 0;  loop < num_rows;  loop++) {
      var sequence = inventory_grid.get_cell_value(1,loop);
      if (isNaN(sequence)) continue;
      sequence = parseInt(sequence,10);
      if (sequence > highest_sequence) highest_sequence = sequence;
   }
/*
   var row_data = [];
   row_data[0] = -1;   row_data[1] = highest_sequence + 1;   row_data[2] = '';
   for (var loop = 3;  loop < num_columns;  loop++) row_data[loop] = '';
   inventory_grid.table.add_row(row_data);
   num_rows++;
   refresh_dynamic_columns();
*/
   if (document.AddProduct) {
      var frame_name = 'add_product';
      var parent = document.AddProduct.id.value;
   }
   else {
      var frame_name='edit_product';
      var parent = document.EditProduct.id.value;
   }
   inventory_grid.table.process_updates(false);
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=addinventory&&Frame=' + frame_name +
          '&Parent=' + parent + '&sequence=' + highest_sequence;
   if (dynamic_no_options) url += '&nooptions=true';
   top.create_dialog('add_inventory',null,null,450,150,false,url,null);
}

function process_add_inventory()
{
   top.enable_current_dialog_progress(true);
   submit_form_data(script_name,'cmd=processaddinventory',
                    document.AddInventory,finish_add_inventory);
}

function finish_add_inventory(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var frame_name = document.AddInventory.Frame.value;
      var iframe = top.get_dialog_frame(frame_name).contentWindow;
      var parent = document.AddInventory.parent.value;
      iframe.load_inventory_grid(parent,true);
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function edit_inventory()
{
   if (inventory_grid.table._num_rows < 1) {
      alert('There are no inventory records to edit');   return;
   }
   var grid_row = inventory_grid.grid.getCurrentRow();
   var id = inventory_grid.grid.getCellText(0,grid_row);
   if (document.AddProduct) {
      var frame_name = 'add_product';
      var parent = document.AddProduct.id.value;
   }
   else {
      var frame_name='edit_product';
      var parent = document.EditProduct.id.value;
   }
   inventory_grid.table.process_updates(false);
   if ((typeof(top.current_tab) == 'undefined') &&
      (typeof(admin_path) != 'undefined')) var url = admin_path;
   else var url = script_prefix;
   url += script_name + '?cmd=editinventory&id=' + id + '&Frame=' +
          frame_name + '&Parent=' + parent;
   if (id == -1) {
      var sequence = inventory_grid.grid.getCellText(1,grid_row);
      var attributes = inventory_grid.grid.getCellText(2,grid_row);
      url += '&Sequence=' + sequence + '&Attributes=' +
             encodeURIComponent(attributes);
   }
   if (dynamic_no_options) url += '&nooptions=true';
   top.create_dialog('edit_inventory',null,null,edit_inventory_width,150,
                     false,url,null);
}

function update_inventory()
{
   top.enable_current_dialog_progress(true);
   submit_form_data(script_name,'cmd=updateinventory',
                    document.EditInventory,finish_update_inventory);
}

function finish_update_inventory(ajax_request)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      var frame_name = document.EditInventory.Frame.value;
      var iframe = top.get_dialog_frame(frame_name).contentWindow;
      var parent = document.EditInventory.parent.value;
      iframe.load_inventory_grid(parent,true);
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function delete_inventory()
{
   if (inventory_grid.table._num_rows <= 1) {
      alert('You cannot delete the last inventory record');   return;
   }
   var response = confirm('Are you sure you want to delete the selected ' +
                          'inventory record?');
   if (! response) return;
   inventory_grid.table.delete_row();
   num_rows--;
   refresh_dynamic_columns();
   inventory_grid.table.process_updates(false);
   rows_changed = true;
}

function reset_inventory_rows()
{
   for (var loop = 0;  loop < num_rows;  loop++) {
      inventory_grid.grid.setCellText(-1,0,loop);
      inventory_grid.table.process_cell_change(inventory_grid.table,-1,0,loop);
   }
}

function process_export_inventory()
{
    var format = get_selected_list_value('Format');
    if (format == '') {
       alert('You must select a File Format');
       document.ExportInventory.Format.focus();
       return;
    }
    document.ExportInventory.submit();
}

function process_import_inventory()
{
   var filename = document.ImportInventory.Filename.value;
   if (filename == '') {
      alert('You must select an Import File');   return;
   }
   top.enable_current_dialog_progress(true);
   top.display_status('Import','Importing Inventory...',500,100,null);
   document.ImportInventory.submit();
}

function finish_import_inventory()
{
   top.remove_status();
   top.enable_current_dialog_progress(false);
   alert('Import Completed');
   window.setTimeout(function() {
      top.close_current_dialog();
   },0);
}

function create_select_inventory_grid(product_id)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2);
    inventory_grid = new Grid('product_inventory',grid_size.width,grid_height);
    inventory_grid.set_columns(['Id','Attributes','Part Number']);
    inventory_grid.set_field_names(['id','attributes','part_number']);
    inventory_grid.set_column_widths([0,0,600]);
    var query = 'select id,attributes,part_number from product_inventory';
    inventory_grid.set_query(query);
    inventory_grid.set_where('parent=' + product_id);
    inventory_grid.set_order_by('sequence');
    inventory_grid.set_id('select_inventory_grid');
    inventory_grid.load(true);
    inventory_grid.display();
    inventory_grid.set_double_click_function(select_product);
    products_grid.grid.onCurrentRowChanged = select_inventory_product;
}

function select_inventory_product(row)
{
    var product_id = products_grid.grid.getCellText(0,row);
    inventory_grid.set_where('parent=' + product_id);
    inventory_grid.table.reset_data(true);
    inventory_grid.grid.refresh();
}

function manage_links(id)
{
    var url = '../cartengine/products.php?cmd=managelinks&id=' + id;
    top.create_dialog('manage_links',null,null,1000,200,false,url,null);
}

var attributes_query = '(select group_concat(concat(coalesce(nullif(' +
   'a.display_name,""),a.name),": ",o.name) separator ", ") from ' +
   'attributes a join attribute_options o on o.parent=a.id where ' +
   'find_in_set(o.id,if(locate("|",i.attributes)=0,replace(' +
   'i.attributes,"-",","),replace(i.attributes,"|",",")))) as attributes';

function load_inv_links_grid(id)
{
    var grid_size = get_default_grid_size();
    inv_links_grid = new Grid('inventory_link',grid_size.width,grid_size.height);
    inv_links_grid.set_columns(['ID','Product','Attributes','Part Number']);
    inv_links_grid.set_column_widths([0,300,390,150]);
    var query = 'select l.id,p.name,' + attributes_query + ',i.part_number ' +
       'from inventory_link l join product_inventory i on i.id=l.linked_id ' +
       'join products p on p.id=i.parent';
    inv_links_grid.set_query(query);
    var info_query = 'select count(l.id) from inventory_link l';
    inv_links_grid.set_info_query(info_query);
    inv_links_grid.set_where('l.primary_id=' + id);
    inv_links_grid.set_order_by('id');
    inv_links_grid.table.set_convert_cell_data(convert_inv_links_data);
    inv_links_grid.set_id('inv_links_grid');
    inv_links_grid.load(false);
    inv_links_grid.display();
}

function reload_inv_links_grid()
{
    inv_links_grid.table.reset_data(false);
    inv_links_grid.grid.refresh();
    window.setTimeout(function() { inv_links_grid.table.restore_position(); },0);
}

function convert_inv_links_data(col,row,text)
{
   return text;
}

function add_inv_link(id)
{
    inv_links_grid.table.save_position();
    var url = '../cartengine/products.php?cmd=addinvlink&id=' + id;
    top.create_dialog('add_link',null,null,1000,600,false,url,null);
}

function create_link_inventory_grid(parent)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2);
    link_inventory_grid = new Grid('product_inventory',grid_size.width,
                                   grid_height);
    link_inventory_grid.set_columns(['ID','Attributes','Part Number']);
    link_inventory_grid.set_field_names(['id','attributes','part_number']);
    link_inventory_grid.set_column_widths([0,545,250]);
    var query = 'select i.id,' + attributes_query + ',i.part_number from ' +
                'product_inventory i';
    link_inventory_grid.set_query(query);
    var info_query = 'select count(i.id) from product_inventory i';
    link_inventory_grid.set_info_query(info_query);
    link_inventory_grid.set_where('i.parent='+parent);
    link_inventory_grid.set_order_by('sequence');
    link_inventory_grid.set_id('link_inventory_grid');
    link_inventory_grid.load(true);
    link_inventory_grid.set_double_click_function(process_add_inv_link);
    link_inventory_grid.display();
    select_inv_product(0)
}

function select_inv_product(row)
{
    var product_id = products_grid.grid.getCellText(0,row);
    link_inventory_grid.set_where('parent='+product_id);
    link_inventory_grid.table.reset_data(true);
    link_inventory_grid.grid.refresh();
}

function process_add_inv_link()
{
    if (link_inventory_grid.table._num_rows < 1) {
       alert('There are no inventory records to select');   return;
    }
    var grid_row = link_inventory_grid.grid.getCurrentRow();
    var inv_id = link_inventory_grid.grid.getCellText(0,grid_row);
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=processaddinvlink&primary_id=' +
                 link_inventory_primary_id + '&linked_id=' + inv_id;
    call_ajax('products.php',fields,true,finish_add_inv_link);
}

function finish_add_inv_link(ajax_request,ajax_data)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('manage_links').contentWindow;
       iframe.reload_inv_links_grid();
       var dialog_obj = top.get_dialog_object('edit_inventory');
       dialog_obj.loaded = false;
       top.reload_dialog('edit_inventory');
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function remove_inv_link()
{
    if (inv_links_grid.table._num_rows < 1) {
       alert('There are no inventory links to remove');   return;
    }
    var response = confirm('Are you sure you want to remove the selected ' +
                           'linked inventory?');
    if (! response) return;
    var grid_row = inv_links_grid.grid.getCurrentRow();
    var link_id = inv_links_grid.grid.getCellText(0,grid_row);
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=removeinvlink&link_id=' + link_id;
    call_ajax('products.php',fields,true,finish_remove_inv_link);
}

function finish_remove_inv_link(ajax_request,ajax_data)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       reload_inv_links_grid();
       top.reload_dialog('edit_inventory');
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

