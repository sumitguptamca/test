/*
               Inroads Shopping Cart - SubList JavaScript Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

function SubList()
{
   this.left_grid = null;
   this.right_grid = null;
   this.script_url = null;
   this.frame_name = null;
   this.form_name = null;
   this.grid_width = null;
   this.grid_height = 300;
   this.parent_field = 'parent';
   this.parent_type = null;
   this.related_type = null;
   this.left_table = null;
   this.left_titles = null;
   this.left_widths = null;
   this.left_fields = 'r.name';
   this.left_query = null;
   this.left_info_query = null;
   this.left_where = null;
   this.left_order = null;
   this.left_label = null;
   this.right_table = null;
   this.right_titles = null;
   this.right_widths = null;
   this.right_fields = 'name';
   this.right_query = null;
   this.right_info_query = null;
   this.right_where = null;
   this.right_order = null;
   this.right_label = null;
   this.right_single_label = null;
   this.changed = false;
   this.reverse_list = false;
   this.enable_double_click = false;
   this.double_click_function = null;
   this.search_form = false;
   this.search_where = null;
   this.filter_row = false;
   this.filter_where = '';
   this.filter_fields = new Array();
   this.all_values = new Array();
   this.extra_where = '';
   this.products_script_name = 'products.php';
   this.log_activity = true;
}

SubList.prototype.resize = function(resizing)
{
   if (! this.left_grid.grid.element()) return;
   var left_width = this.left_grid.grid.element().offsetWidth;
   if (left_width == 0) {
      if (! resizing) {
         var sublist = this;
         window.setTimeout(function() { sublist.resize(resizing); },100);
      }
      return;
   }
   if (this.grid_width == left_width) return;

   this.grid_width = left_width;
   var num_left_columns = this.left_grid.column_widths.length;
   var width_total = 0;
   for (var index in this.left_grid.column_widths) {
      if (index == (num_left_columns - 1)) {
         var last_width = left_width - width_total - 20;
         this.left_grid.column_widths[index] = last_width;
         this.left_grid.grid.setColumnWidth(last_width,index);
      }
      else width_total += this.left_grid.column_widths[index];
   }

   var right_width = this.right_grid.grid.element().offsetWidth;
   var num_right_columns = this.right_grid.column_widths.length;
   width_total = 0;
   for (var index in this.right_grid.column_widths) {
      if (index == (num_right_columns - 1)) {
         var last_width = right_width - width_total - 20;
         this.right_grid.column_widths[index] = last_width;
         this.right_grid.grid.setColumnWidth(last_width,index);
      }
      else width_total += this.right_grid.column_widths[index];
   }
}

SubList.prototype.create_left_sublist_grid = function(parent)
{
   if (typeof(update_sublist) != 'undefined') update_sublist(this);
   this.left_grid = new Grid(this.left_table,this.grid_width,this.grid_height);
   var column_names = new Array();
   column_names[0] = 'Id';
   column_names[1] = 'Related';
   var column_widths = new Array();
   column_widths[0] = 0;
   column_widths[1] = 0;
   var index = 2;   var width_offset = 30;
   if (this.reverse_list) {
      var related_field = 'parent';   var parent_field = 'related_id';
      var sequence_field = '';
   }
   else {
      var related_field = 'related_id';   var parent_field = this.parent_field;
      var sequence_field = ',l.sequence';   width_offset += 30;
      column_names[2] = 'Seq';   column_widths[2] = 30;   index++;
   }
   var num_titles = this.left_titles.length;
   for (var loop = 0;  loop < num_titles;  loop++) {
      column_names[index] = this.left_titles[loop];
      if (this.left_widths) {
         if (this.left_widths[loop] == -1)
            column_widths[index] = this.grid_width - width_offset;
         else {
            column_widths[index] = this.left_widths[loop];
            width_offset += this.left_widths[loop];
         }
      }
      else column_widths[index] = this.grid_width - width_offset;
      index++;
   }
   this.left_grid.set_columns(column_names);
   this.left_grid.set_column_widths(column_widths);
   if (this.left_query) var query = this.left_query;
   else var query = 'select l.id,l.' + related_field + sequence_field + ',' +
                    this.left_fields + ' from ' + this.left_table + ' l left join ' +
                    this.right_table + ' r on l.' + related_field + '=r.id';
   this.left_grid.set_query(query);
   if (this.left_where) var where = this.left_where;
   else if (parent == -1) var where = 'l.' + parent_field + '=-1';
   else var where = 'l.' + parent_field + '=' + parent;
   if (this.parent_type !== null)
      where += ' and (parent_type=' + this.parent_type + ')';
   if (this.related_type !== null)
      where += ' and (related_type=' + this.related_type + ')';
   this.left_grid.set_where(where);
   if (this.left_info_query) var info_query = this.left_info_query;
   else var info_query = 'select count(l.id) from ' + this.left_table +
                         ' l left join ' + this.right_table + ' r on l.' +
                         related_field + '=r.id';
   this.left_grid.set_info_query(info_query);
   if (this.left_order) this.left_grid.set_order_by(this.left_order);
   else if (this.reverse_list) this.left_grid.set_order_by('r.name');
   else this.left_grid.set_order_by('l.sequence,r.name');
   this.left_grid.table.set_convert_cell_data(this.convert_left_sublist_data);
   this.left_grid.load(false);
   this.left_grid.grid.sublist = this;
//   if (AW.ie && (! this.reverse_list))
//      this.left_grid.enable_drag_and_drop(this.resequence_sublist,this,false);
   if (this.enable_double_click)
      this.left_grid.set_double_click_function(this.double_click);
   this.left_grid.grid.clearSelectedModel();
   this.left_grid.grid.setSelectionMode('multi-row');
   var sublist_obj = this;
   add_onload_function(function() {
      sublist_obj.left_grid.insert(sublist_obj.name+'_left_grid_div');
   },0);
}

SubList.prototype.set_parent = function(parent)
{
   if (this.reverse_list) var parent_field = 'related_id';
   else var parent_field = this.parent_field;
   if (parent == -1) var where = 'l.' + parent_field + '=-1';
   else var where = '(l.' + parent_field + '=' + parent + ')';
   if (this.parent_type !== null)
      where += ' and (parent_type=' + this.parent_type + ')';
   if (this.related_type !== null)
      where += ' and (related_type=' + this.related_type + ')';
   this.left_grid.set_where(where);
}

SubList.prototype.reload_left_sublist_grid = function()
{
   this.left_grid.table.reset_data(false);
   this.left_grid.grid.refresh();
   this.changed = false;
}

SubList.prototype.resequence_sublist = function(sub_list,old_row,new_row)
{
   if (old_row == new_row) return true;
   var id = document.forms[sub_list.form_name].id.value;
   var old_sequence = sub_list.left_grid.grid.getCellText(2,old_row);
   var new_sequence = sub_list.left_grid.grid.getCellText(2,new_row);
   top.enable_current_dialog_progress(true);
   var fields = 'cmd=resequencesublist&Table=' + sub_list.left_table +
                '&Parent=' + id + '&OldSequence=' +
                old_sequence + '&NewSequence=' + new_sequence +
                '&ParentField=' + sub_list.parent_field;
   if (sub_list.parent_type !== null)
      fields += '&ParentType='+sub_list.parent_type;
   if (sub_list.related_type !== null)
      fields += '&RelatedType='+sub_list.related_type;
   if (! this.log_activity) fields += '&Log=false';
   call_ajax(sub_list.script_url,fields,true,
             function (ajax_request) {
                finish_resequence_sublist(ajax_request,sub_list,new_row);
             });
}

function finish_resequence_sublist(ajax_request,sub_list,new_row)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status != 201) {
      ajax_request.display_error();
      return false;
   }
   sub_list.left_grid.table.reset_data(false);
   sub_list.changed = true;
   sub_list.left_grid.grid.refresh(); 
   sub_list.left_grid.grid.setSelectedRows([new_row]);
   sub_list.left_grid.grid.setCurrentRow(new_row);
   return true;
}

SubList.prototype.num_left_selected = function()
{
   var selected_rows = this.left_grid.grid._rowsSelected;
   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      num_selected++;
   }
   return num_selected;
}

SubList.prototype.selected_left_ids = function()
{
   var selected_rows = this.left_grid.grid._rowsSelected;
   var ids = '';
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      var id = this.left_grid.grid.getCellText(0,grid_row);
      if (ids != '') ids += ',';
      ids += id;
   }
   return ids;
}

SubList.prototype.move_top = function()
{
   if (this.left_grid.table._num_rows < 1) return;
   if (this.num_left_selected() > 1) {
      alert('You can not move more than one row at a time');   return;
   }
   var grid_row = parse_int(this.left_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   this.resequence_sublist(this,grid_row,0);
}

SubList.prototype.move_up = function()
{
   if (this.left_grid.table._num_rows < 1) return;
   if (this.num_left_selected() > 1) {
      alert('You can not move more than one row at a time');   return;
   }
   var grid_row = parse_int(this.left_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   this.resequence_sublist(this,grid_row,grid_row - 1);
}

SubList.prototype.move_down = function()
{
   var num_rows = this.left_grid.table._num_rows;
   if (num_rows < 1) return;
   if (this.num_left_selected() > 1) {
      alert('You can not move more than one row at a time');   return;
   }
   var grid_row = parse_int(this.left_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   this.resequence_sublist(this,grid_row,grid_row + 1);
}

SubList.prototype.move_bottom = function()
{
   var num_rows = this.left_grid.table._num_rows;
   if (num_rows < 1) return;
   if (this.num_left_selected() > 1) {
      alert('You can not move more than one row at a time');   return;
   }
   var grid_row = parse_int(this.left_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   this.resequence_sublist(this,grid_row,num_rows - 1);
}

SubList.prototype.create_right_sublist_grid = function(enable_multiple)
{
   var height = this.grid_height;
   if (this.search_form) height -= 30;
   if (this.filter_row) height -= 30;
   this.right_grid = new Grid(this.right_table,this.grid_width,height);
   var column_names = new Array();
   column_names[0] = 'Id';
   var column_widths = new Array();
   column_widths[0] = 0;
   var index = 1;   var width_offset = 30;
   var num_titles = this.right_titles.length;
   for (var loop = 0;  loop < num_titles;  loop++) {
      column_names[index] = this.right_titles[loop];
      if (this.right_widths) {
         if (this.right_widths[loop] == -1)
            column_widths[index] = this.grid_width - width_offset;
         else {
            column_widths[index] = this.right_widths[loop];
            width_offset += this.right_widths[loop];
         }
      }
      else column_widths[index] = this.grid_width - width_offset;
      index++;
   }
   this.right_grid.set_columns(column_names);
   this.right_grid.set_column_widths(column_widths);
   if (this.right_query) var query = this.right_query;
   else var query = 'select id,' + this.right_fields + ' from ' +
                    this.right_table;
   this.right_grid.set_query(query);
   if (this.right_info_query)
      this.right_grid.set_info_query(this.right_info_query);
   if (this.right_where) this.right_grid.set_where(this.right_where);
   if (this.right_order) this.right_grid.set_order_by(this.right_order);
   else this.right_grid.set_order_by('name,id');
   this.right_grid.table.set_convert_cell_data(this.convert_right_sublist_data);
   this.right_grid.load(false);
   this.right_grid.grid.sublist = this;
   if (this.enable_double_click)
      this.right_grid.set_double_click_function(this.double_click);
   this.right_grid.grid.clearSelectedModel();
   this.right_grid.grid.setSelectionMode('multi-row');
   var sublist_obj = this;
   add_onload_function(function() {
      sublist_obj.right_grid.insert(sublist_obj.name+'_right_grid_div');
   },0);
}

SubList.prototype.reload_right_sublist_grid = function()
{
   this.right_grid.table.reset_data(false);
   this.right_grid.grid.refresh();
}

SubList.prototype.num_right_selected = function()
{
   var selected_rows = this.right_grid.grid._rowsSelected;
   var num_selected = 0;
   for (var grid_row in selected_rows) {
      if (grid_row == '$') continue;
      num_selected++;
   }
   return num_selected;
}

SubList.prototype.add_item = function(id)
{
   var parent = document.forms[this.form_name].id.value;
   var fields = 'cmd=addsublist&';
   if (this.reverse_list) fields += 'Parent=' + id + '&Id=' + parent;
   else fields += 'Parent=' + parent + '&Id=' + id;
   fields += '&Table=' + this.left_table + '&ParentField=' + this.parent_field;
   if (this.parent_type !== null) fields += '&ParentType='+this.parent_type;
   if (this.related_type !== null) fields += '&RelatedType='+this.related_type;
   if (! this.log_activity) fields += '&Log=false';
   top.enable_current_dialog_progress(true);
   var self = this;
   call_ajax(this.script_url,fields,true,
             function (ajax_request) {
                self.finish_add_item(ajax_request,self);
             });
}

SubList.prototype.add_items = function(ids)
{
   var parent = document.forms[this.form_name].id.value;
   var fields = 'cmd=addsublist&';
   if (this.reverse_list) fields += 'Parents=' + ids + '&Id=' + parent;
   else fields += 'Parent=' + parent + '&Ids=' + ids;
   fields += '&Table=' + this.left_table + '&ParentField=' + this.parent_field;
   if (this.parent_type !== null) fields += '&ParentType='+this.parent_type;
   if (this.related_type !== null) fields += '&RelatedType='+this.related_type;
   if (! this.log_activity) fields += '&Log=false';
   top.enable_current_dialog_progress(true);
   var self = this;
   call_ajax(this.script_url,fields,true,
             function (ajax_request) {
                self.finish_add_item(ajax_request,self);
             });
}

SubList.prototype.finish_add_item = function(ajax_request,sublist_obj)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      sublist_obj.left_grid.table.reset_data(false);
      sublist_obj.left_grid.grid.refresh();
      sublist_obj.changed = true;
   }
   else ajax_request.display_error();
}

SubList.prototype.add_sublist_item = function()
{
   if (this._id) {
      var grid_id = this._id.substr(0,this._id.indexOf('-row'));
      var grid = AW.object(grid_id);
      var sublist = grid.sublist;
   }
   else var sublist = this;
   if (sublist.right_grid.table._num_rows < 1) {
      alert('There are no ' + sublist.right_label + ' to add');   return;
   }
   var num_selected = this.num_right_selected();
   if (num_selected > 1) {
      var selected_rows = sublist.right_grid.grid._rowsSelected;
      var ids = '';
      for (var grid_row in selected_rows) {
         if (grid_row == '$') continue;
         var id = sublist.right_grid.grid.getCellText(0,grid_row);
         for (var loop = 0;  loop < sublist.left_grid.table._num_rows;  loop++) {
            if (sublist.left_grid.table.getData(1,loop) == id) {
               alert('One of the selected ' + sublist.right_label +
                     ' is already in the list!');
               return;
            }
         }
         if (ids != '') ids += ',';
         ids += id;
      }
      sublist.add_items(ids);
   }
   else {
      var grid_row = sublist.right_grid.grid.getCurrentRow();
      var id = sublist.right_grid.grid.getCellText(0,grid_row);
      for (var loop = 0;  loop < sublist.left_grid.table._num_rows;  loop++) {
         if (sublist.left_grid.table.getData(1,loop) == id) {
            alert('That ' + sublist.right_single_label +
                  ' is already in the list!');   return;
         }
      }
      sublist.add_item(id);
   }
}

SubList.prototype.delete_sublist_item = function()
{
   if (this.left_grid.table._num_rows < 1) {
      alert('There are no ' + this.left_label + ' to delete');   return;
   }
   var num_selected = this.num_left_selected();
   if (num_selected > 1) var ids = this.selected_left_ids();
   else {
      var grid_row = this.left_grid.grid.getCurrentRow();
      var id = this.left_grid.grid.getCellText(0,grid_row);
   }
   top.enable_current_dialog_progress(true);
   var self = this;
   var fields = 'cmd=deletesublist&Table=' + this.left_table;
   if (num_selected > 1) fields += '&Ids=' + ids;
   else fields += '&Id=' + id;
   if (! this.log_activity) fields += '&Log=false';
   call_ajax(this.script_url,fields,true,
             function (ajax_request) {
                self.finish_delete_sublist_item(ajax_request,self);
             });
}

SubList.prototype.finish_delete_sublist_item = function(ajax_request,sublist_obj)
{
   var status = ajax_request.get_status();
   top.enable_current_dialog_progress(false);
   if (status == 201) {
      sublist_obj.left_grid.table.reset_data(false);
      sublist_obj.left_grid.grid.refresh();
      sublist_obj.changed = true;
   }
   else ajax_request.display_error();
}

SubList.prototype.double_click = function()
{
   var grid_id = this._id.substr(0,this._id.indexOf('-row'));
   var grid = AW.object(grid_id);
   var sublist = grid.sublist;
   if (grid == sublist.left_grid.grid) {
      var sublist_grid = sublist.left_grid;   var index = 1;   var side='left';
   }
   else {
      var sublist_grid = sublist.right_grid;   var index = 0;   var side='right';
   }
   if (sublist_grid.table._num_rows < 1) {
      alert('There are no '+sublist.right_label+' to edit');   return -1;
   }
   var grid_row = grid.getCurrentRow();
   var id = grid.getCellText(index,grid_row);
   if (typeof(update_window) == 'undefined')
      var update_window = sublist.frame_name;
   if (sublist.double_click_function)
      sublist.double_click_function(sublist,id,side,update_window);
   else if (sublist.categories) {
      var dialog_name = 'edit_category_' + (new Date()).getTime();
      var url = script_prefix + 'categories.php?cmd=editcategory&id=' + id +
                '&frame=' + dialog_name + '&sublist=' + sublist.name +
                '&side=' + side + '&updatewindow=' + update_window;
      top.create_dialog(dialog_name,null,null,900,500,false,url,null);
   }
   else {
      var product_dialog_height = top.get_content_frame().product_dialog_height;
      if (top.skin) var dialog_width = 1100;
      else var dialog_width = 900;
      var dialog_name = 'edit_product_' + (new Date()).getTime();
      var url = script_prefix + sublist.products_script_name +
                '?cmd=editproduct&id=' + id + '&frame=' + dialog_name +
                '&sublist=' + sublist.name + '&side=' + side +
                '&updatewindow=' + update_window;
      var window_width = top.get_document_window_width();
      if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
      else window_width -= top.default_dialog_frame_width;
      url += '&window_width=' + window_width;
      top.create_dialog(dialog_name,null,null,dialog_width,
                        product_dialog_height,false,url,null);
   }
}

SubList.prototype.update = function(side,id)
{
   if (side == 'left') {
      this.left_grid.table.reset_data(false);
      this.left_grid.grid.refresh();
   }
   else if (side == 'right') {
      this.right_grid.table.reset_data(false);
      this.right_grid.grid.refresh();
   }
   else {
      this.add_item(id);
      this.right_grid.table.reset_data(false);
      this.right_grid.grid.refresh();
   }
}

SubList.prototype.search = function()
{
   var query = document.forms[this.form_name].elements[this.search_field].value;
   if (query == '') {
      this.reset_search();   return;
   }
   query = query.replace(/'/g,'\\\'');
   var right_grid = this.right_grid;
   if (this.search_where)
      var where = this.search_where.replace(/\$query\$/g,query);
   else var where = 'name like "%' + query + '%" or display_name like "%' +
                    query + '%" or short_description like "%' +
                    query + '%" or long_description like "%' + query + '%"';
   if (! isNaN(query)) where += ' or id=' + query;
   if (this.extra_where)
      where = '(' + where + ') and (' + this.extra_where + ')';
   if (this.filter_where)
      where = '(' + where + ') and (' + this.filter_where + ')';
   top.display_status('Search','Searching ' + this.search_label + '...',
                      350,100,null);
   window.setTimeout(function() {
      right_grid.set_where(where);
      right_grid.table.reset_data(false);
      right_grid.grid.refresh();
      top.remove_status();
   },0);
}

SubList.prototype.reset_search = function()
{
   top.display_status('Search','Loading All ' + this.search_label + '...',350,100,null);
   document.forms[this.form_name].elements[this.search_field].value = '';
   var right_grid = this.right_grid;
   var where = this.extra_where;
   if (this.filter_where) {
      if (where) where = '(' + where + ') and ';
      where += '(' + this.filter_where + ')';
   }
   window.setTimeout(function() {
      right_grid.set_where(where);
      right_grid.table.reset_data(false);
      right_grid.grid.refresh();
      top.remove_status();
   },0);
}

SubList.prototype.filter = function(filter_field,all_value)
{
   var list_name = this.name + '_filter_' + filter_field;
   var field_value = get_selected_list_value(list_name);
   this.filter_fields[filter_field] = field_value;
   this.all_values[filter_field] = all_value;
   this.filter_where = '';
   for (var field_name in this.filter_fields) {
      field_value = this.filter_fields[field_name];
      if (field_value != this.all_values[field_name]) {
         if (this.filter_where) this.filter_where += ' and ';
         if (! field_value)
            this.filter_where += '((' + field_name + '="") or isnull(' +
                                 field_name + '))';
         else this.filter_where += '(' + field_name + '="' + field_value + '")';
      }
   }
   this.search();
}

SubList.prototype.convert_left_sublist_data = function(col,row,text)
{
   if (col == 4) return cleanup_grid_html(strip_html(text));
   return text;
}

SubList.prototype.convert_right_sublist_data = function(col,row,text)
{
   if (col == 2) return cleanup_grid_html(strip_html(text));
   return text;
}

SubList.prototype.resize_column_headers = function()
{
   this.left_grid.resize_column_headers();
   this.right_grid.resize_column_headers();
}
