/*
                Inroads Shopping Cart - Image JavaScript Functions

                        Written 2008-2018 by Randall Severy
                         Copyright 2008-2018 Inroads, LLC
*/

var images_grid;
var script_url;
var frame_name;
var form_name;
var grid_width;
var parent_type;
var image_url;
var image_dir;
var grid_column;
var delayed_image_grid_load = false;
var selected_image = -1;
var cms_url;
var crop_ratio = null;
var dynamic_images = false;
var use_dynamic_images = false;
var image_subdir_prefix = 0;
var dynamic_image_url = null;
var sample_image_size = 'small';
var use_callout_groups = false;
var callout_groups = {};
var opened_group_col = null;
var opened_group_row = null;

function convert_image_filename(filename)
{
   return filename.replace(/&amp;/g,'&');
}

function init_images(url,frame,name,width,type)
{
   script_url = url;
   frame_name = frame;
   form_name = name;
   grid_width = width;
   parent_type = type;
   if (type == 2) {
      image_url = "../attrimages";   image_dir = "/attrimages";
      grid_column = 6;   images_grid = options_grid;
   }
   else {
      image_url = "../images";   image_dir = "/images";
      grid_column = 2;
   }
}

function enable_image_buttons(enable_flag)
{
   var add_image_button = document.getElementById('add_image');
   var upload_images_button = document.getElementById('upload_images');
   var edit_image_file_button = document.getElementById('edit_image_file');
   var edit_image_info_button = document.getElementById('edit_image_info');
   var delete_image_button = document.getElementById('delete_image');
   if (enable_flag) var display_style = '';
   else var display_style = 'none';
   add_image_button.style.display = display_style;
   if (upload_images_button)
      upload_images_button.style.display = display_style;
   if (edit_image_file_button)
      edit_image_file_button.style.display = display_style;
   if (edit_image_info_button)
      edit_image_info_button.style.display = display_style;
   delete_image_button.style.display = display_style;
}

function set_image_parent(parent)
{
   if (parent == -1) var where = 'parent_type=-1';
   else var where = '(parent_type=' + parent_type +
                    ') and (parent=' + parent + ')';
   images_grid.set_where(where);
}

function refresh_group_column()
{
   var num_rows = images_grid.table._num_rows;
   var last_row = document.getElementById('images_grid-row-' + (num_rows - 1));
   if (! last_row) {
      window.setTimeout(refresh_group_column,1);   return;
   }
   for (var row = 0;  row < num_rows;  row++)
      images_grid.grid.getRowTemplate(row).getItemTemplate(6).refresh();
}

function setup_callout_group_column()
{
   try {
   var combo = new AW.UI.Combo;
   combo.setId('callout_group_combo');
   var num_options = 1;
   var item_values = [0];
   var item_text = [''];
   column_width = 0;
   for (var index in callout_groups) {
      item_values[num_options] = index;
      item_text[num_options] = callout_groups[index];
      var option_length = (callout_groups[index].length * 6) + 10;
      if (option_length > column_width) column_width = option_length;
      num_options++;
   }
   if (column_width < 75) column_width = 75;
   combo.setItemValue(item_values);
   combo.setItemText(item_text);
   combo.setItemCount(num_options);
   combo.getPopupTemplate().setStyle('width',''+column_width+'px');
   var select_height = num_options * 17;
   if (select_height > product_dialog_height)
      select_height = product_dialog_height;
   combo.getPopupTemplate().setStyle('height',''+select_height+'px');
   var edit_box = combo.getContent("box/text");
   edit_box.setAttribute("readonly",true);
   edit_box.setEvent("onclick",function() { this.showPopup(); });
   edit_box.setStyle('width',''+column_width+'px');
   combo.AW_showPopup = combo.showPopup;
   combo.showPopup = function() {
      inventory_grid.grid.setCurrentRow(this.$1);
      if ((this.$0 == opened_group_col) && (this.$1 == opened_group_row))
         return;
      this.AW_showPopup();
      var selected_item = this.getSelectedItems();
      var AW_onCurrentItemChanged = this.onCurrentItemChanged;
      this.onCurrentItemChanged = null;
      this.setCurrentItem(selected_item);
      this.onCurrentItemChanged = AW_onCurrentItemChanged;
      opened_group_col = this.$0;
      opened_group_row = this.$1;
   }
   combo.onControlEditStarted = function() {
      var element = this.getContent("box/text").element();
      element.contentEditable = false;
   }
   combo.onItemClicked = function(event,i) {
      try {
         var value = this.getItemValue(i);
         var text = this.getItemText(i);
         this.setControlValue(value);
         this.setControlText(text);
         this.$owner.table.process_cell_change(this.$owner.table,value,
                                               this.$0,this.$1);
         AW.$popup.hidePopup();
         opened_group_col = null;
         opened_group_row = null;
      } catch(e) {
         alert('Script Error (combo.onItemClicked): '+e.message);
      }
   }
   combo.AW_refresh = combo.refresh;
   combo.refresh = function() {
      var col = this.$0;
      var row = this.$1;
      var value = this.$owner.table.getData(col,row);
      var option_id = parse_int(value);
      var text = '';
      for (var index in callout_groups) {
         if (index == option_id) {
            text = callout_groups[index];   break;
         }
      }
      if (! text) text = '';
      var combo_length = this.getItemCount();
      for (var loop = 0;  loop < combo_length;  loop++) {
         if (this.getItemValue(loop) == value) {
            this.setSelectedItems([loop]);
            this.setCurrentItem(loop);
            break;
         }
      }
      this.setControlValue(value);
      this.setControlText(text);
      this.AW_refresh();
   }
   images_grid.grid.setCellEditable(true,6);
   images_grid.grid.setCellTemplate(combo,6);
   images_grid.grid.setColumnWidth(column_width + 8,6);
   images_grid.grid.onControlRefreshed = refresh_group_column;
   } catch(e) {
      alert('Script Error (setup_callout_group_column): '+e.message);
   }
}

function create_images_grid(parent,container)
{
   images_grid = new Grid('images',grid_width,200);
   if (grid_width > 0) var desc_width = grid_width - 315;
   else var desc_width = 650;
   if (use_callout_groups) {
      desc_width -= 250;   desc_width = Math.round(desc_width / 3);
      var columns = ['Id','Seq','Filename','Caption','Title','Alt Text',
                     'Callout Group'];
      var column_widths = [0,30,180,desc_width,desc_width,desc_width,200];
      var field_names = ['id','sequence','filename','caption','title',
                         'alt_text','callout_group'];
      var query = 'select id,sequence,filename,caption,title,alt_text,' +
                  'callout_group from images';
   }
   else {
      desc_width = Math.round(desc_width / 3);
      var columns = ['Id','Seq','Filename','Caption','Title','Alt Text'];
      var column_widths = [0,30,210,desc_width,desc_width,desc_width];
      var field_names = ['id','sequence','filename','caption','title',
                         'alt_text'];
      var query = 'select id,sequence,filename,caption,title,alt_text ' +
                  'from images';
   }
   images_grid.set_columns(columns);
   images_grid.set_column_widths(column_widths);
   images_grid.set_field_names(field_names);
   images_grid.set_query(query);
   set_image_parent(parent);
   images_grid.set_order_by('sequence,id');
   images_grid.set_id('images_grid');
   images_grid.table._url = script_url;
   images_grid.table.add_update_parameter('cmd','updateimage');
   images_grid.load(true);
   images_grid.grid.setCellEditable(true,3);
   images_grid.grid.setCellEditable(true,4);
   images_grid.grid.setCellEditable(true,5);
   images_grid.grid.setSelectionMode('single-cell');
   images_grid.grid.setVirtualMode(false);
//   images_grid.enable_drag_and_drop(resequence_images,null,false);
   images_grid.grid.onCurrentRowChanged = select_image;
   images_grid.set_double_click_function(edit_image);
   set_grid_navigation(images_grid.grid);
   if (use_callout_groups) setup_callout_group_column();
   if (typeof(container) != 'undefined') {
      delayed_image_grid_load = true;
      add_onload_function(function() {
         images_grid.insert(container);
         load_sample_images();
         if (images_grid.table._num_rows > 0) select_image(0);
      },0);
   }
   else images_grid.display();
}


function reload_images_grid(current_image)
{
   images_grid.table.reset_data(true);
   images_grid.grid.refresh();
   if ((current_image > 0) && (images_grid.table._num_rows > current_image)) {
      images_grid.grid.setSelectedRows([current_image]);
      images_grid.grid.setCurrentRow(current_image);
   }
   load_sample_images();
   if (current_image > -1) {
      if (images_grid.table._num_rows > current_image)
         select_image(current_image);
      else select_image(-1);
   }
}

function load_sample_images()
{
   var sample_image_div = document.getElementById('sample_image_div');
   if (! sample_image_div) return;
   sample_image_div.style.width = grid_width + 'px';
   if (images_grid.table._num_rows < 1) {
      sample_image_div.innerHTML = '';   return;
   }
   var num_rows = images_grid.table._num_rows;
   var html = '';
   var current_time = (new Date()).getTime();
   for (var loop = 0;  loop < num_rows;  loop++) {
      var image = images_grid.grid.getCellText(grid_column,loop);
      if (image) {
         html += '<div id="sample_image_' + loop + '" class="sample_image" ' +
                 'onClick="sample_image_onclick(' + loop + ');"><img src="';
         if (dynamic_images) {
            if (dynamic_image_url) html += dynamic_image_url;
            else html += admin_path + 'image.php';
            html += '?cmd=loadimage&filename=' +
                    convert_image_filename(image) + '&size=' +
                    sample_image_size + '&';
         }
         else {
            var filename = convert_image_filename(image);
            if (image_subdir_prefix > 0)
               filename = filename.substr(0,image_subdir_prefix)+'/'+filename;
            html += image_url + '/' + sample_image_size + '/' + filename + '?';
         }
         html += 'v=' + current_time;
         html += '"></div>';
      }
   }
   sample_image_div.innerHTML = html;
}

function select_image(row)
{
   if (selected_image != -1) {
      var sample_image = document.getElementById('sample_image_' +
                                                 selected_image);
      if (sample_image) sample_image.className = 'sample_image';
   }
   if (row != -1) {
      var sample_image = document.getElementById('sample_image_' + row);
      if (sample_image)
         sample_image.className = 'sample_image selected_sample_image';
   }
   selected_image = row;
   if (typeof(select_image_callouts) != 'undefined')
      select_image_callouts(row);
}

function sample_image_onclick(index)
{
   select_image(index);
   var current_cell = images_grid.grid.getCellTemplate(1,index).element();
   current_cell.focus();
   current_cell.focus();
   images_grid.grid.setSelectedRows([index]);
   images_grid.grid.setCurrentRow(index);
}

function resequence_images(update_data,old_row,new_row)
{
   if (old_row == new_row) return true;
   var id = document.forms[form_name].id.value;
   var old_sequence = images_grid.grid.getCellText(1,old_row);
   var new_sequence = images_grid.grid.getCellText(1,new_row);
   call_ajax(script_url,"cmd=resequenceimages&ParentType=" + parent_type +
             "&Parent=" + id + "&OldSequence=" + old_sequence + "&NewSequence=" +
             new_sequence,true,function (ajax_request) {
                finish_resequence_images(ajax_request,new_row);
             });
}

function finish_resequence_images(ajax_request,new_row)
{
   var status = ajax_request.get_status();
   if (status != 201) {
      ajax_request.display_error();
      return false;
   }
   reload_images_grid(new_row);
   return true;
}

function move_image_top()
{
   if (images_grid.table._num_rows < 1) return;
   var grid_row = parse_int(images_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_images(null,grid_row,0);
}

function move_image_up()
{
   if (images_grid.table._num_rows < 1) return;
   var grid_row = parse_int(images_grid.grid.getCurrentRow());
   if (grid_row == 0) return;
   resequence_images(null,grid_row,grid_row - 1);
}

function move_image_down()
{
   var num_rows = images_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(images_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_images(null,grid_row,grid_row + 1);
}

function move_image_bottom()
{
   var num_rows = images_grid.table._num_rows;
   if (num_rows < 1) return;
   var grid_row = parse_int(images_grid.grid.getCurrentRow());
   if (grid_row == num_rows - 1) return;
   resequence_images(null,grid_row,num_rows - 1);
}

function images_onload()
{
   if (! images_grid) return;
   if (! delayed_image_grid_load) {
      load_sample_images();
      if (images_grid.table._num_rows > 0) select_image(0);
   }
   if (images_grid.table._num_rows > 1) {
      var button_cell = document.getElementById('image_sequence_buttons');
      if (button_cell) button_cell.style.display = '';
   }
}

function image_tab_onload()
{
   if (use_callout_groups) images_grid.grid.refresh();
   images_grid.resize_column_headers();
}

/*   Where is this used?
function reload_images_grid(section,section_id)
{
   images_grid.set_where("(section=" + section + ") and (section_id=" +
                         section_id + ")");
   images_grid.table.reset_data(true);
   images_grid.refresh();
   load_sample_images();
}
*/

function add_image()
{
   if (parent_type == 2) {
      if (images_grid.table._num_rows < 1) {
         alert('There are no options to add an image to');   return;
      }
      var grid_row = images_grid.grid.getCurrentRow();
      var filename = images_grid.grid.getCellText(6,grid_row);
      if (filename) {
         alert('That option already has an image');   return;
      }
      id = images_grid.grid.getCellText(0,grid_row);
   }
   else var id = document.forms[form_name].id.value;
   images_grid.table.process_updates(false);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url + '?cmd=addimage&Parent=' + id + '&Frame=' + frame_name;
   top.create_dialog('add_image',null,null,590,170,false,url,null);
}

function update_server_filename()
{
   var filename = document.AddImage.Filename.value;
   var server_field = document.AddImage.ServerFilename;
   if ((server_field.value == "") && (filename != "")) {
      var slash_pos = filename.lastIndexOf('/');
      if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
      var slash_pos = filename.lastIndexOf('\\');
      if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
      filename = filename.replace(/&|;|`|'|\\|"|\||\*|\?|<|>|\^|\[|\]|\{|\}|\n|\r| /g,'');
      server_field.value = filename;
   }
}

function close_browse_server(user_close)
{
   top.using_admin_top = false;   top.adding_image = false;
}

var adding_image = false;

function browse_server()
{
   top.using_admin_top = true;   top.adding_image = true;
   frame_name = document.AddImage.Frame.value;
   var url = cms_url + '?BrowseFiles=Go&Dir=' + image_dir +
             '/original/&Mime=image/*&Frame=add_image&_JavaScript=Yes' +
             '&_iFrameDialog=Yes&NoDelete=true&NoCreateDir=true';
   if (use_dynamic_images) url += '&View=List&NoThumbs=Yes';
   if (image_subdir_prefix > 0)
      url += '&WorkingDir=' + image_dir + '/original/';
   else url += '&NoDirs=true&HideCurrDir=true';
   url += '&UploadFinishFunction=top.get_dialog_frame(\'' + frame_name +
          '\').contentWindow.finish_upload_image' +
          '&CopyFinishFunction=top.get_dialog_frame(\'' + frame_name +
          '\').contentWindow.finish_upload_image' +
          '&UpdateFinishFunction=top.get_dialog_frame(\'' + frame_name +
          '\').contentWindow.update_image_file' +
          '&iFrameTime=' + (new Date()).getTime();
   if (crop_ratio) url += '&CropRatio=' + crop_ratio;
   top.create_dialog('browse_files',null,null,'80%','80%',false,url,null);
   top.set_dialog_onclose('browse_files',close_browse_server);
}

function get_current_file()
{
   return document.AddImage.ServerFilename.value;
}

function set_current_file(filename)
{
   var slash_pos = filename.lastIndexOf('/');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   var slash_pos = filename.lastIndexOf('\\');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   document.AddImage.ServerFilename.value = filename;
}

function update_image_file(filename)
{
   var slash_pos = filename.lastIndexOf('/');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   var slash_pos = filename.lastIndexOf('\\');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url;
   call_ajax(url,"cmd=updateimagefile&Filename=" +
             encodeURIComponent(filename),true,finish_update_image_file,60);
}

function finish_update_image_file(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) {}
   else ajax_request.display_error();
}

function select_product_image()
{
   top.create_dialog('select_product',null,null,830,400,false,
                     '../cartengine/products.php?cmd=selectproduct&' +
                     'selectimage=true&frame=add_image',null);
}

function process_select_product_image(filename)
{
   document.AddImage.ServerFilename.value = filename;
}

function validate_server_filename()
{
   var server_field = document.AddImage.ServerFilename;
   if (/&|;|`|'|\\|"|\||\*|\?|<|>|\^|\[|\]|\{|\}|\n|\r/.test(server_field.value))
      return false;
   return true;
}

function process_add_image()
{
   var filename = document.AddImage.Filename.value;
   var server_filename = document.AddImage.ServerFilename.value;
   if ((filename == '') && (server_filename == '')) {
      alert('You must select a File to Add');   return;
   }
   if (filename != '') update_server_filename();
   if (! validate_server_filename()) {
      document.AddImage.ServerFilename.focus();
      alert('Invalid characters in Server Filename');   return;
   }
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(true);
   document.AddImage.submit();
}

function finish_add_image()
{
   var iframe = top.get_dialog_frame(frame_name).contentWindow;
   var images_grid = iframe.images_grid;
   if (parent_type == 2) var grid_row = images_grid.grid.getCurrentRow();
   images_grid.table.reset_data(true);
   images_grid.grid.refresh();
   if (parent_type == 2) var last_row = grid_row;
   else var last_row = images_grid.table._num_rows - 1;
   images_grid.grid.setSelectedRows([last_row]);
   images_grid.grid.setCurrentRow(last_row);
   iframe.load_sample_images();
   iframe.select_image(last_row);
   if (images_grid.table._num_rows > 1) {
      var button_cell = document.getElementById('image_sequence_buttons');
      if (button_cell) button_cell.style.display = '';
   }
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(false);
   top.close_current_dialog();
}

function close_upload_images(user_close)
{
   top.using_admin_top = false;
}

function upload_images()
{
   top.using_admin_top = true;
   var url = cms_url + '?UploadDocument=Go&_CurrentDir=' + image_dir +
             '/original&HideCurrDir=true&HideMasthead=true&_JavaScript=Yes' +
             '&_iFrameDialog=Yes&_OkFunction=top.get_dialog_frame(\'' +
             frame_name +'\').contentWindow.finish_upload_images();' +
             '&_FinishFunction=top.get_dialog_frame(\'' + frame_name +
             '\').contentWindow.finish_upload_image' +
             '&iFrameTime=' + (new Date()).getTime();
   top.create_dialog('UploadDocument',null,null,800,175,false,url,null);
   top.set_dialog_onclose('UploadDocument',close_upload_images);
}

function finish_upload_image(filename)
{
   var slash_pos = filename.lastIndexOf('/');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   var slash_pos = filename.lastIndexOf('\\');
   if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
   if (parent_type == 2) {
      if (images_grid.table._num_rows < 1) return;
      var grid_row = images_grid.grid.getCurrentRow();
      var existing_filename = images_grid.grid.getCellText(6,grid_row);
      if (existing_filename) return;
      id = images_grid.grid.getCellText(0,grid_row);
   }
   else var id = document.forms[form_name].id.value;
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url;
   var fields = "cmd=processuploadedimage&Parent=" + id + "&Filename=" +
                encodeURIComponent(filename);
   if (top.adding_image) fields += "&NoUpdate=true";
   call_ajax(url,fields,true,finish_process_uploaded_image,60);
}

function finish_process_uploaded_image(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) reload_images_grid(selected_image);
   else ajax_request.display_error();
}

function finish_upload_images()
{
   top.close_current_dialog();
}

function edit_image()
{
   if (images_grid.table._num_rows < 1) {
      alert('There are no images to edit');   return;
   }
   var grid_row = images_grid.grid.getCurrentRow();
   if (parent_type == 2) {
      var filename = images_grid.grid.getCellText(6,grid_row);
      if (! filename) {
         alert('That option does not have an image to edit');   return;
      }
   }
   images_grid.table.process_updates(false);
   var id = images_grid.grid.getCellText(0,grid_row);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url + '?cmd=editimage&id=' + id + '&Frame=' + frame_name;
   top.create_dialog('edit_image',null,null,580,130,false,url,null);
}

function update_image()
{
   var iframe = top.get_dialog_frame(document.EditImage.Frame.value).contentWindow;
   var script_url = iframe.script_url;
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(true);
   submit_form_data(script_url,"cmd=updateimage",document.EditImage,
                    finish_update_image_record);
}

function finish_update_image_record(ajax_request)
{
   var status = ajax_request.get_status();
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(false);
   if (status == 201) {
      var iframe = top.get_dialog_frame(document.EditImage.Frame.value).contentWindow;
      iframe.reload_images_grid(-1);
      top.close_current_dialog();
   }
   else ajax_request.display_error();
}

function close_edit_image_file(user_close)
{
   top.using_admin_top = false;
}

function edit_image_file()
{
   if (images_grid.table._num_rows < 1) {
      alert('There are no images to edit');   return;
   }
   var grid_row = images_grid.grid.getCurrentRow();
   if (parent_type == 2) {
      var filename = images_grid.grid.getCellText(6,grid_row);
      if (! filename) {
         alert('That option does not have an image to edit');   return;
      }
   }
   images_grid.table.process_updates(false);
   var id = images_grid.grid.getCellText(0,grid_row);
   var filename = images_grid.grid.getCellText(grid_column,grid_row);
   var fields = 'cmd=getimageinfo&Filename=' +encodeURIComponent(filename);
   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(true);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url;
   var ajax_request = new Ajax(url,fields,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(continue_edit_image_file,filename);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function continue_edit_image_file(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   if ((! inside_cms) || (cms_top() == top))
      top.enable_current_dialog_progress(false);
   var status = ajax_request.get_status();
   if (status != 200) return;

   var image_filename = null;
   var image_width = -1;
   var image_height = -1;
   var crop_ratio = null;
   eval(ajax_request.request.responseText);
   if ((! image_filename) || (image_width == -1) || (image_height == -1)) {
      alert('Unable to get size information for image ' + ajax_data);   return;
   }

   top.using_admin_top = true;
   var window_width = top.get_document_window_width();
   if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
   else window_width -= top.default_dialog_frame_width;
   if (typeof(cms_top().window.innerHeight) != 'undefined')
      var window_height = top.window.innerHeight;
   else var window_height = top.get_document_window_height();
   if (top.dialog_frame_height) window_height -= top.dialog_frame_height;
   else window_height -= top.default_dialog_frame_height;

   var max_image_width = window_width - 330;
   var max_image_height = window_height - 60;
   image_width = parseInt(image_width);   image_height = parseInt(image_height);
   if ((image_width > max_image_width) || (image_height > max_image_height)) {
      var width_factor = image_width / max_image_width;
      var height_factor = image_height / max_image_height;
      if (width_factor > height_factor) {
         var display_image_width = max_image_width;
         var display_image_height = Math.round(image_height / width_factor);
      }
      else {
         var display_image_height = max_image_height;
         var display_image_width = Math.round(image_width / height_factor);
      }
   }
   else {
      var display_image_width = image_width;
      var display_image_height = image_height;
   }

   var dialog_width = display_image_width + 310;
   if (display_image_height > 370) var dialog_height = display_image_height + 50;
   else var dialog_height = 420;
   var url = cms_url + '?EditImage=Go&Filename=' + encodeURIComponent(image_filename) +
             '&_iFrameDialog=Yes&Frame=' + frame_name + '&MaxWidth=' +
             max_image_width + '&MaxHeight=' + max_image_height;
   if (crop_ratio) url += '&CropRatio=' + crop_ratio;
   url += '&iFrameTime=' + (new Date()).getTime();
   top.create_dialog('edit_image',null,null,dialog_width,dialog_height,
                     false,url,null);
   top.set_dialog_onclose('edit_image',close_edit_image_file);
}

function finish_update_image(file_info)
{
   top.close_current_dialog();
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url;
   var fields = "cmd=updateimagefile&Filename=" +
                encodeURIComponent(file_info['name']);
   var ajax_request = new Ajax(url,fields,true);
   ajax_request.enable_alert();
   ajax_request.enable_parse_response();
   ajax_request.set_callback_function(reload_image,file_info['name']);
   ajax_request.set_timeout(30);
   ajax_request.send();
}

function reload_image(ajax_request,ajax_data)
{
   if (ajax_request.state != 4) return;

   var status = ajax_request.get_status();
   if (status == 201) {
      var sample_image = document.getElementById('sample_image_' +
                                                 selected_image);
      if (! sample_image) return;
      if (dynamic_images)
         var url = admin_path + 'image.php?cmd=loadimage&filename=' +
                 convert_image_filename(image) + '&size=' +
                 sample_image_size + '&';
      else var url = image_url + '/' + sample_image_size + '/' +
                     convert_image_filename(ajax_data) + '?';
      url += 'v=' + (new Date()).getTime();
      sample_image.firstChild.src = url;
   }
   else ajax_request.display_error();
}

function delete_image()
{
   if (images_grid.table._num_rows < 1) {
      alert('There are no images to delete');   return;
   }
   var grid_row = images_grid.grid.getCurrentRow();
   var filename = images_grid.grid.getCellText(grid_column,grid_row);
   if ((parent_type == 2) && (! filename)) {
      alert('That option does not have an image to edit');   return;
   }
   filename = convert_image_filename(filename);
   var id = images_grid.grid.getCellText(0,grid_row);
   var response = confirm('Are you sure you want to delete the image "' +
                          filename + '"?');
   if (! response) return;
   images_grid.table.process_updates(false);
   if ((typeof(top.current_tab) == "undefined") &&
      (typeof(admin_path) != "undefined")) var url = admin_path;
   else var url = '';
   url += script_url;
   call_ajax(url,"cmd=deleteimage&id=" + id + "&Filename=" +
             encodeURIComponent(filename),true,finish_delete_image);
}

function finish_delete_image(ajax_request)
{
   var status = ajax_request.get_status();
   if (status == 201) reload_images_grid(0);
   else ajax_request.display_error();
}

