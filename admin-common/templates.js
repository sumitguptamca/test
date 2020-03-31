/*
        Inroads Control Panel/Shopping Cart - Templates Tab JavaScript Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

var template_names = [];
var template_types = [];
var template_ids = '';
var templates_grid = null;
var custom_templates_grid = null;
var editor_base_path;
var browse_base_url;
var templates_path;
var toolbar_prefix;
var admin_path;
var script_prefix = '';
var insert_lists = [];
var inside_cms = false;
var template_url = null;
var html_editor;
var extra_plugins = null;
var toolbar_buttons = null;
var custom_template_start = 0;
var cancel_add_template = true;
var attach_dir = null;
var orders = false;
var emailbuilder_installed = false;

function resize_screen(new_width,new_height)
{
    if (top.skin) {
       if (custom_template_start != 0) {
          if (top.buttons == 'left') {
             var button_column1 = document.getElementById('buttonColumn1');
             var button_column2 = document.getElementById('buttonColumn2');
             var offset = get_body_height() -
                          Math.max(templates_grid.height,button_column1.offsetHeight) -
                          Math.max(custom_templates_grid.height,button_column2.offsetHeight);
          }
          else var offset = get_body_height() - templates_grid.height -
                            custom_templates_grid.height;
          var grid_height = Math.floor((new_height - offset) / 2);
          if (grid_height < 0) grid_height = 0;
          if (top.buttons == 'left') {
             resize_grid(templates_grid,templates_grid.width,
                         Math.max(grid_height,button_column1.offsetHeight));
             resize_grid(custom_templates_grid,custom_templates_grid.width,
                         Math.max(grid_height,button_column2.offsetHeight));
          }
          else {
             resize_grid(templates_grid,templates_grid.width,grid_height);
             resize_grid(custom_templates_grid,custom_templates_grid.width,grid_height);
          }
       }
       else resize_grid(templates_grid,-1,new_height - get_grid_offset(templates_grid));
       return;
    }
    if (custom_template_start != 0) {
       new_height = ((new_height + 20) / 2) - 15;
       if (top.buttons == 'top') new_height -= 40;
       resize_grid(templates_grid,new_width,new_height)
       resize_grid(custom_templates_grid,new_width,new_height);
       var sep_row = document.getElementById('templates_sep_row');
       var row_height = new_height - 125;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
    else {
       if (navigator.userAgent.indexOf('MSIE') != -1) {
          new_width -= 45;   new_height -= 42;
       }
       else {
          new_width -= 20;   new_height -= 0;
       }
       resize_grid(templates_grid,new_width,new_height-10);
    }
}

var type_values = ['Order','Quote','Invoice','Sales Order'];

function add_extra_rows()
{
    var loaded_ids = [];
    for (var loop = 0;  loop < templates_grid.table._num_rows;  loop++)
       loaded_ids[templates_grid.grid.getCellText(0,loop)] = true;
    for (var id in template_names) {
       if (typeof(loaded_ids[id]) == 'undefined') {
          var row_data = [];
          row_data[0] = id;   row_data[1] = template_names[id];
          if (orders) row_data[2] = type_values[template_types[id]];
          else {
             row_data[2] = '';   row_data[3] = '';
             row_data[4] = '';   row_data[5] = 0;
          }
          templates_grid.table.add_row(row_data);
       }
    }
}

function load_standard_grid()
{
    var grid_size = get_default_grid_size();
    if (custom_template_start != 0) {
       var grid_height = Math.floor(grid_size.height / 2) - 15;
       if (top.buttons == 'top') grid_height -= 40;
    }
    else var grid_height = grid_size.height;
    if (orders) {
       templates_grid = new Grid(null,grid_size.width,grid_height);
       templates_grid.set_columns(['ID','Template','Type']);
       templates_grid.set_column_widths([0,200,100]);
       templates_grid.set_id('templates_grid');
       templates_grid.table.num_records = 0;
       templates_grid.load(false);
    }
    else {
       templates_grid = new Grid('templates',grid_size.width,grid_height);
       templates_grid.set_columns(['ID','Template','Subject','From','To',
                                   '# Attachments']);
       templates_grid.set_column_widths([0,200,250,220,220,85]);
       var query = 'select type,type as template,subject,from_addr,to_addr,' +
                   '(select count(template_type) from attachments where ' +
                   'template_type=type) as num_attachments from templates';
       templates_grid.set_query(query);
       templates_grid.set_where('type in (' + template_ids + ')');
       templates_grid.set_order_by('type');
       templates_grid.table.set_convert_cell_data(convert_standard_data);
       templates_grid.set_id('templates_grid');
       templates_grid.load(true);
    }
    templates_grid.set_double_click_function(edit_template);
    templates_grid.display();
    add_extra_rows();
    if ((custom_template_start != 0) && (! top.skin)) {
       var sep_row = document.getElementById('templates_sep_row');
       var row_height = grid_height - 105;
       if (row_height < 0) row_height = 0;
       sep_row.style.height = '' + row_height + 'px';
    }
}

function reload_standard_grid()
{
    templates_grid.table.reset_data(true);
    templates_grid.grid.refresh();
    add_extra_rows();
    window.setTimeout(function() { templates_grid.table.restore_position(); },0);
}

function convert_standard_data(col,row,text)
{
    if (col == 1) {
       var template = parse_int(text);
       if (typeof(template_names[template]) != 'undefined')
          return template_names[template];
    }
    return text;
}

function filter_templates()
{
    document.Templates.submit();
}

function edit_template()
{
    if (templates_grid.table._num_rows < 1) {
       alert('There are no templates to edit');   return;
    }
    var grid_row = templates_grid.grid.getCurrentRow();
    var template = templates_grid.grid.getCellText(0,grid_row);
    templates_grid.table.save_position();
    var url = script_prefix + 'templates.php?cmd=edittemplate&Template=' +
              template;
    if (orders) {
       var label = templates_grid.grid.getCellText(1,grid_row);
       url += '&orders=true&Label='+encodeURIComponent(label);
    }
    top.create_dialog('edit_template',null,null,900,600,false,url,null);
}

function load_custom_grid()
{
    var grid_size = get_default_grid_size();
    if (custom_template_start != 0) {
       var grid_height = Math.floor(grid_size.height / 2) - 15;
       if (top.buttons == 'top') grid_height -= 40;
    }
    else var grid_height = grid_size.height;
    custom_templates_grid = new Grid('templates',grid_size.width,grid_height);
    custom_templates_grid.set_columns(['ID','Template','Subject','From','To',
                                '# Attachments']);
    custom_templates_grid.set_column_widths([0,200,250,220,220,85]);
    var query = 'select type,name,subject,from_addr,to_addr,' +
                '(select count(template_type) from attachments where ' +
                'template_type=type) as num_attachments from templates';
    custom_templates_grid.set_query(query);
    custom_templates_grid.set_where('type >= '+custom_template_start);
    custom_templates_grid.set_order_by('type');
    custom_templates_grid.table.set_convert_cell_data(convert_custom_data);
    custom_templates_grid.set_id('custom_templates_grid');
    custom_templates_grid.load(true);
    custom_templates_grid.set_double_click_function(edit_custom_template);
    custom_templates_grid.display();
}

function reload_custom_grid()
{
    custom_templates_grid.table.reset_data(true);
    custom_templates_grid.grid.refresh();
    window.setTimeout(function() { custom_templates_grid.table.restore_position(); },0);
}

function convert_custom_data(col,row,text)
{
    return text;
}

function add_template()
{
    cancel_add_template = true;
    top.enable_current_dialog_progress(true);
    var ajax_request = new Ajax('templates.php','cmd=createtemplate',true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_template,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_template(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status != 200) return;

    var template = -1;
    eval(ajax_request.request.responseText);

    custom_templates_grid.table.save_position();
    top.create_dialog('add_template',null,null,900,600,false,
                      script_prefix + 'templates.php?cmd=addtemplate&Template=' +
                      template,null);
}

function add_template_onclose(user_close)
{
    if (cancel_add_template) {
       var template = document.EditOnline.Template.value;
       call_ajax('templates.php','cmd=deletetemplate&Template=' +
                 template,true);
    }
}

function add_template_onload()
{
    top.set_current_dialog_onclose(add_template_onclose);
    document.EditOnline.name.focus();
}

function process_add_template()
{
    if (! validate_form_field(document.EditOnline.name,'Template Name'))
       return;
    if (! validate_form_field(document.EditOnline.subject,'Subject')) return;

    top.enable_current_dialog_progress(true);
    var format = get_selected_radio_button('format');
    if (format == 2) {
       if (html_editor == 'ckeditor') {
          var field_value = CKEDITOR.instances.content.getData();
          document.EditOnline.content.value = field_value;
       }
       else {
          var editor = FCKeditorAPI.GetInstance('content');
          editor.UpdateLinkedField();
       }
    }
    submit_form_data('templates.php','cmd=processaddtemplate',document.EditOnline,
                     finish_add_template);
}

function finish_add_template(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_template = false;
       top.get_content_frame().reload_custom_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_custom_template()
{
    if (custom_templates_grid.table._num_rows < 1) {
       alert('There are no templates to edit');   return;
    }
    var grid_row = custom_templates_grid.grid.getCurrentRow();
    var template = custom_templates_grid.grid.getCellText(0,grid_row);
    custom_templates_grid.table.save_position();
    top.create_dialog('edit_template',null,null,900,600,false,
                      script_prefix + 'templates.php?cmd=edittemplate&Template=' +
                      template,null);
}

function delete_template()
{
    if (custom_templates_grid.table._num_rows < 1) {
       alert('There are no templates to delete');   return;
    }
    var grid_row = custom_templates_grid.grid.getCurrentRow();
    var template = custom_templates_grid.grid.getCellText(0,grid_row);
    var name = custom_templates_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the '+name +
                           ' template?');
    if (! response) return;
    custom_templates_grid.table.save_position();
    top.display_status('Delete','Deleting Template...',350,100,null);
    call_ajax('templates.php','cmd=deletetemplate&Template=' + template,true,
              finish_delete_template);
}

function finish_delete_template(ajax_request)
{
    top.remove_status();
    var status = ajax_request.get_status();
    if (status == 201) reload_custom_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

var current_template_format = 1;
var editor_loaded = false;
var current_template_field = null;
var edit_dialog_width = 900;
var edit_dialog_height = 600;

function get_num_attachments()
{
    var attachments_div = document.getElementById('attachments');
    if (! attachments_div) return 0;
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    if (! upload_list) return 0;
    var attach_rows = upload_list.getElementsByTagName('li');
    if (! attach_rows) return 0;
    return attach_rows.length;
}

function resize_dialog(new_width,new_height)
{
    if (! document.EditOnline) return;
    edit_dialog_width = new_width;
    edit_dialog_height = new_height;
    var attach_height = get_num_attachments() * 19;
    if (! document.EditOnline.format) var height_offset = 84;
    else var height_offset = 225;
    if (current_template_format == 2) {
       if (html_editor == 'ckeditor') {
          var editor_toolbar = document.getElementById('cke_1_top');
          var toolbar_height = editor_toolbar.offsetHeight;
          var editor_cell = document.getElementById('cke_1_contents');
          var editor_frame = editor_cell.firstChild.nextSibling;
          var editor_container = document.getElementById('cke_content');
          var editor_table = editor_container.firstChild.nextSibling;
          editor_cell.style.height = '' +
             (new_height - height_offset - 62 - attach_height - toolbar_height) + 'px';
          editor_table.style.height = '' +
             (new_height - attach_height - height_offset - 33) + 'px';
          if (editor_frame) {
             var frame_height = '' +
                (new_height - height_offset - 62 - attach_height - toolbar_height) + 'px';
             editor_frame.style.height = frame_height;
          }
          if (! top.skin)
             editor_container.style.width = '' + (new_width - 15) + 'px';
       }
       else {
          var editor = FCKeditorAPI.GetInstance('content');
          var editor_frame = document.getElementById('content___Frame');
          if (! top.skin) {
             editor.Width = new_width - 30;
             editor.Height = new_height - attach_height - height_offset + 5;
             editor_frame.style.width = '' + (new_width - 30) + 'px';
             editor_frame.style.height = '' +
                (new_height - attach_height - height_offset) + 'px';
          }
       }
    }
    var content_div = document.getElementById('content_div');
    if (! content_div) return;
    var textarea = document.getElementById('content');
    if (top.skin) {
       content_div.style.height = new_height + 'px';
       var content_height = new_height -
          (get_dialog_body_height() - content_div.offsetHeight);
       content_div.style.height = content_height + 'px';
       if ((current_template_format == 2) && (html_editor == 'fckeditor'))
          editor_frame.style.height = content_height + 'px';
       textarea.style.height = '' + content_height + 'px';
    }
    else {
       content_div.style.width = '' + (new_width - 30) + 'px';
       content_div.style.height = '' +
          (new_height - attach_height - height_offset) + 'px';
       textarea.style.width = '' + (new_width - 30) + 'px';
       textarea.style.height = '' +
          (new_height - attach_height - height_offset) + 'px';
    }
}

function update_template_format()
{
    if (! document.EditOnline.format) var format = 2;
    else var format = get_selected_radio_button('format');
    if ((format == 2) && (current_template_format != 2)) {
       if (editor_loaded) {
          if (html_editor == 'ckeditor') {
             var field_value = document.EditOnline.content.value;
             CKEDITOR.instances.content.setData(field_value);
             var editor_div = document.getElementById('cke_content');
             editor_div.style.display = '';
          }
          else {
             var editor = FCKeditorAPI.GetInstance('content');
             editor.SetHTML(editor.LinkedField.value);
             var editor_frame = document.getElementById('content___Frame');
             editor_frame.style.display = '';
          }
          var textarea = document.getElementById('content');
          textarea.style.display = 'none';
       }
       else if (html_editor == 'ckeditor') {
          var ckeditor_toolbar = [
               ['Source'],
               ['Copy','Paste','PasteText','PasteFromWord','-','SpellChecker',
                'Scayt','-','WSDWidget','Templates'],
               ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat',
                'CKCss'],
               ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
               ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote',
                'CreateDiv'],
               ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
               ['Link','Unlink','Anchor'],
               ['Image','Flash','MediaEmbed','Table','HorizontalRule','Smiley',
                'SpecialChar','PageBreak'],
               ['Form','Checkbox','Radio','TextField','Textarea','Select','Button',
                'ImageButton','HiddenField'],
               ['Format','Font','FontSize'],
               ['TextColor','BGColor'],
               ['Maximize','ShowBlocks','-']
          ];
          if (emailbuilder_installed) ckeditor_toolbar[0].splice(0,0,'EMailBuilder');
          if (! templates_path) ckeditor_toolbar[1].splice(9,1);
          if (toolbar_buttons) ckeditor_toolbar.splice(2,0,toolbar_buttons);
          var ckeditor_config = {
             skin: 'Default',
             language: 'en',
             uiColor: '#EFEFDE',
             extraPlugins: extra_plugins,
             removePlugins: 'showborders',
             allowedContent: true,
             browserContextMenuOnCtrl: true,
             startupFocus: true,
             filebrowserImageBrowseUrl: browse_base_url +
                '?BrowseFiles=Go&Mime=image/*&Dir=&_JavaScript=Yes',
             filebrowserImageUploadUrl: browse_base_url + '/browseupload?UploadDir=',
             filebrowserImageBrowseLinkUrl: browse_base_url + '?BrowseFiles=Go&_JavaScript=Yes',
             filebrowserBrowseUrl: browse_base_url + '?BrowseFiles=Go&_JavaScript=Yes',
             filebrowserUploadUrl: browse_base_url + '/browseupload?UploadDir=',
             filebrowserFlashBrowseUrl: browse_base_url + '?BrowseFiles=Go&_JavaScript=Yes',
             filebrowserFlashUploadUrl: browse_base_url + '/browseupload?UploadDir=',
             font_names: 'Arial, Helvetica, sans-serif;Comic Sans MS;Courier New;Tahoma;Times New Roman;Verdana',
             protectedSource: [/<noedit>[\s\S]*?<\/noedit>/gi,
                               /<\?[\s\S]*?\?>/g,
                               /<%[\s\S]*?%>/g,
                               /<\$[\s\S]*?>/g],
             fontSize_sizes: '8px;9px;10px;11px;12px;13px;14px;15px;16px;18px;20px;24px;28px;36px;48px;',
             toolbar: ckeditor_toolbar
          };
          if (top.skin) ckeditor_config.width = '100%';
          else ckeditor_config.width = '' + (edit_dialog_width - 30) + 'px';
          if (! document.EditOnline.format) var offset = 79;
          else var offset = 220;
          var editor_height = edit_dialog_height - offset;
          editor_height -= (get_num_attachments() * 19);
          ckeditor_config.height = '' + editor_height + 'px';
          if (templates_path) {
             ckeditor_config.templates_files = ['xml:'+templates_path];
             ckeditor_config.templates_replaceContent = false;
          }
          if (navigator.userAgent.indexOf('Chrome') != -1)
             ckeditor_config.startupFocus = false;
          if (emailbuilder_installed) {
             CKEDITOR.plugins.addExternal('iframedialog','/editor-support/ckeditor/plugins/iframedialog/');
             CKEDITOR.plugins.addExternal('emailbuilder','/editor-support/ckeditor/plugins/emailbuilder/');
          }
          CKEDITOR.replace('content',ckeditor_config);
          CKEDITOR.instances.content.on('dataReady',ckeditor_template_onload);
          editor_loaded = true;
       }
       else {
          var editor = new FCKeditor('content');
          editor.BasePath = editor_base_path;
          editor.Config['EnableXHTML'] = true;
          editor.Config['EnableSourceXHTML'] = true;
          editor.Config['FillEmptyBlocks'] = false;
          editor.Config['FormatSource'] = true;
          editor.Config['FormatOutput'] = true;
          if (navigator.userAgent.indexOf('Chrome') == -1)
             editor.Config['StartupFocus'] = true;
          else editor.Config['StartupFocus'] = false;
          if (htmleditor_url) var toolbar_url = htmleditor_url;
          else var toolbar_url = toolbar_prefix + '/engine/htmleditor.php';
          toolbar_url += '?cmd=toolbar&set=Inroads';
//          toolbar_url = admin_path + 'editor.js';
          editor.Config['CustomConfigurationsPath'] = toolbar_url;
          editor.Config['ImageBrowser'] = true;
          editor.Config['ImageBrowserURL'] = browse_base_url +
             '?BrowseFiles=Go&Mime=image/*&Dir=&_JavaScript=Yes&Absolute=Yes';
          editor.Config['ImageUpload'] = true;
          editor.Config['ImageUploadURL'] = browse_base_url + '/browseupload?UploadDir=';
          editor.Config['LinkBrowser'] = true;
          editor.Config['LinkBrowserURL'] = browse_base_url +
             '?BrowseFiles=Go&_JavaScript=Yes&Absolute=Yes';
          editor.Config['LinkUpload'] = true;
          editor.Config['LinkUploadURL'] = browse_base_url + '/browseupload?UploadDir=';
          editor.Config['FlashBrowser'] = true;
          editor.Config['FlashBrowserURL'] = browse_base_url +
             '?BrowseFiles=Go&_JavaScript=Yes&Absolute=Yes';
          editor.Config['FlashUpload'] = true;
          editor.Config['FlashUploadURL'] = browse_base_url + '/browseupload?UploadDir=';
          editor.ToolbarSet = 'Inroads';
          if (top.skin) editor.Width = '100%';
          else editor.Width = edit_dialog_width - 30;
          if (top.skin) var editor_height = edit_dialog_height - 240;
          else var editor_height = edit_dialog_height - 220;
          editor.Height = (editor_height - (get_num_attachments() * 19));
          editor.ReplaceTextarea();
          editor_loaded = true;
          var editor_frame = document.getElementById('content___Frame');
          editor_frame.style.display = 'none';
       }
    }
    else if (current_template_format == 2) {
       if (html_editor == 'ckeditor') {
          var field_value = CKEDITOR.instances.content.getData();
          document.EditOnline.content.value = field_value;
          var editor_div = document.getElementById('cke_content');
          editor_div.style.display = 'none';
       }
       else {
          var editor = FCKeditorAPI.GetInstance('content');
          editor.UpdateLinkedField();
          var editor_frame = document.getElementById('content___Frame');
          editor_frame.style.display = 'none';
       }
       var textarea = document.getElementById('content');
       textarea.style.display = '';
       textarea.style.visibility = 'visible';
    }
    current_template_format = format;
}

function edit_template_onclose(user_close)
{
    top.num_dialogs--;
}

function edit_template_onload()
{
    if (inside_cms && (cms_top() != top)) {
       var cms = cms_top();
       cms.dialog_onload(document,window);
       var dialog_window = cms.dialog_windows[cms.num_dialogs - 1];
       var dialog_name = cms.dialog_names[cms.num_dialogs - 1];
       top.dialog_windows[top.num_dialogs] = dialog_window;
       top.dialog_names[top.num_dialogs] = dialog_name;
       top.num_dialogs++;
       top.set_dialog_title(dialog_name,'Edit Template');
       top.dialog_onload(document,window);
       top.set_current_dialog_onclose(edit_template_onclose);
    }
    var input_fields = document.getElementsByTagName('INPUT');
    var num_fields = input_fields.length;
    for (var loop = 0;  loop < num_fields;  loop++)
       if (input_fields[loop].type == 'text')
          input_fields[loop].onfocus = function() { current_template_field = this; }
    var content_field = document.getElementById('content');
    content_field.onfocus = function() { current_template_field = this; }
    if (navigator.userAgent.indexOf('Chrome') == -1) {
       try {
          content_field.focus();
       } catch(e) {}
    }
    update_template_format();
}

function build_attachment_html(parent,filename,file_size,index)
{
    html = '<span class="qq-upload-file">';
    if (template_url) {
       if (attach_dir) var url = attach_dir + filename;
       else var url = template_url+'/'+parent + '-'+filename;
       html += '<a href="'+url+'" target="_blank">';
    }
    html += filename;
    if (template_url) html += '</a>';
    html += '</span><span class="qq-upload-size" style="display: inline;">' +
           file_size+'</span><span class="qq-delete-file">(<a href="#" ' +
           'onClick="';
    if (document.EditOnline.SendData) 
       html += 'remove_attachment('+index+');">Remove';
    else html += 'delete_attachment('+parent+','+index+');">Delete';
    html += '</a>)</span><span class="qq-upload-failed-text">' +
           'Failed</span>';
    return html;
}

function create_uploader(parent,attachments)
{
    var attachments_div = document.getElementById('attachments');
    var template = '<div class="qq-uploader">' + 
                   '<div class="qq-upload-drop-area"><span>Drop files here to upload</span></div>' +
                   '<div class="qq-upload-button">Attach File</div>';
    if (navigator.userAgent.indexOf('MSIE') == -1)
       template += '<div class="qq-drop-label">(or drop files here to upload)</div>';
    template += '<ul class="qq-upload-list"></ul></div>';
    var uploader = new qq.FileUploader({
       element: attachments_div,
       action: 'templates.php',
       params: { cmd: 'uploadattachment', parent: parent, AttachDir: attach_dir },
       onComplete: finish_uploaded_file,
       template: template,
       debug: false
    });
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    upload_list.parent_id = parent;
    for (var index in attachments) {
       var filename = attachments[index].filename;
       var file_size = attachments[index].size;
       var attach_row = document.createElement('li');
       attach_row.className = 'qq-upload-success';
       attach_row.id = 'attach_' + index;
       attach_row.filename = filename;
       attach_row.innerHTML = build_attachment_html(parent,filename,file_size,
                                                    index);
       upload_list.appendChild(attach_row);
    }
    top.grow_current_dialog();
}

function finish_uploaded_file(id,filename,response)
{
    var attachments_div = document.getElementById('attachments');
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    var attach_rows = upload_list.getElementsByTagName('li');
    var attach_index = -1;
    for (var index=0,length=attach_rows.length; index < length; index++) {
       var row_filename = attach_rows[index].getElementsByTagName('span')[0].firstChild.innerHTML;
       if (filename == row_filename) {
          attach_index = index;   break;
       }
    }
    if (attach_index == -1) return;
    var attach_row = attach_rows[attach_index];
    if (typeof(response.success) != 'undefined') {
       attach_row.id = 'attach_' + attach_index;
       attach_row.filename = filename;
       var file_size = attach_row.getElementsByTagName('span')[1].innerHTML;
       attach_row.innerHTML = build_attachment_html(upload_list.parent_id,
                                                    filename,file_size,
                                                    attach_index);
       top.grow_current_dialog();
    }
    else attach_row.parentNode.removeChild(attach_row);
}

function add_attachment(filename,file_size)
{
    var attachments_div = document.getElementById('attachments');
    var upload_list = attachments_div.getElementsByTagName('ul')[0];
    var attach_rows = upload_list.getElementsByTagName('li');
    for (var index=0,length=attach_rows.length; index < length; index++) {
       var row_filename = attach_rows[index].getElementsByTagName('span')[0].firstChild.innerHTML;
       if (filename == row_filename) return;
    }
    var new_index = attach_rows.length;
    var attach_row = document.createElement('li');
    attach_row.className = 'qq-upload-success';
    attach_row.id = 'attach_' + new_index;
    attach_row.filename = filename;
    attach_row.innerHTML = build_attachment_html(upload_list.parent_id,
                                                 filename,file_size,new_index);
    upload_list.appendChild(attach_row);
    top.grow_current_dialog();
}

var delete_row;

function delete_attachment(parent,index)
{
    delete_row = document.getElementById('attach_' + index);
    var response = confirm('Are you sure you want to delete the "' +
                           delete_row.filename + '" attachment?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=deleteattachment&parent=' + parent +
                 '&filename=' + encodeURIComponent(delete_row.filename);
    if (attach_dir) fields += '&AttachDir=' + encodeURIComponent(attach_dir);
    call_ajax('templates.php',fields,true,finish_delete_attachment);
}

function finish_delete_attachment(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) delete_row.parentNode.removeChild(delete_row);
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function remove_attachment(index)
{
    var remove_row = document.getElementById('attach_' + index);
    var response = confirm('Are you sure you want to remove the "' +
                           remove_row.filename + '" attachment?');
    if (! response) return;
    remove_row.parentNode.removeChild(remove_row);
}

function close_edit_template()
{
    if (inside_cms && (cms_top() != top))
       cms_top().close_current_dialog();
    else top.close_current_dialog();
}

function FCKeditor_OnComplete(editor) 
{ 
    editor.Events.AttachEvent('OnFocus',FCKeditor_OnFocus);
    set_htmleditor_styles(editor);
} 

function FCKeditor_OnFocus(editor)
{
    current_template_field = null;
}

function ckeditor_template_onload()
{
    ckeditor_onload();
    current_template_field = null;
}

function update_template()
{
    if (! validate_form_field(document.EditOnline.subject,'Subject')) return;
    if ((! inside_cms) || (cms_top() == top))
       top.enable_current_dialog_progress(true);
    var format = get_selected_radio_button('format');
    if (format == 2) {
       if (html_editor == 'ckeditor') {
          var field_value = CKEDITOR.instances.content.getData();
          document.EditOnline.content.value = field_value;
       }
       else {
          var editor = FCKeditorAPI.GetInstance('content');
          editor.UpdateLinkedField();
       }
    }
    submit_form_data('templates.php','cmd=updatetemplate',document.EditOnline,
                     finish_update_template);
}

function finish_update_template(ajax_request)
{
    var status = ajax_request.get_status();
    if ((! inside_cms) || (cms_top() == top))
       top.enable_current_dialog_progress(false);
    if (status == 201) {
       var template = document.EditOnline.Template.value;
       if ((custom_template_start != 0) && (template >= custom_template_start))
          top.get_content_frame().reload_custom_grid();
       else {
          var frame = top.get_content_frame().get_content_frame();
          if (frame) frame.reload_standard_grid();
          else top.get_content_frame().reload_standard_grid();
       }
       close_edit_template();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function send_email()
{
    if (! validate_form_field(document.EditOnline.subject,'Subject')) return;
    top.enable_current_dialog_progress(true);
    var format = get_selected_radio_button('format');
    if (format == 2) {
       if (html_editor == 'ckeditor') {
          var field_value = CKEDITOR.instances.content.getData();
          document.EditOnline.content.value = field_value;
       }
       else {
          var editor = FCKeditorAPI.GetInstance('content');
          editor.UpdateLinkedField();
       }
    }
    var attachments_div = document.getElementById('attachments');
    if (attachments_div) {
       var upload_list = attachments_div.getElementsByTagName('ul')[0];
       if (upload_list) {
          var attachments = '';
          var attach_rows = upload_list.getElementsByTagName('li');
          if (attach_rows) for (var index in attach_rows) {
             if (isNaN(index)) continue;
             var attach_row = attach_rows[index];
             var anchors = attach_row.getElementsByTagName('a');
             if (anchors) {
                var href = anchors[0].href;
                var filename = href.substring(href.lastIndexOf('/') + 1);
                if (attachments) attachments += '|';
                attachments += filename;
             }
          }
       }
    }

    var fields = 'cmd=sendemail';
    if (attach_dir) fields += '&AttachDir=' + encodeURIComponent(attach_dir);
    if (attachments)
       fields += '&Attachments=' + encodeURIComponent(attachments);
    submit_form_data('templates.php',fields,document.EditOnline,
                     finish_send_email);
}

function finish_send_email(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (document.EditOnline.FinishFunction)
          eval(document.EditOnline.FinishFunction.value);
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function update_order_template()
{
    top.enable_current_dialog_progress(true);
    if (html_editor == 'ckeditor') {
       var field_value = CKEDITOR.instances.content.getData();
       document.EditOnline.content.value = field_value;
    }
    else {
       var editor = FCKeditorAPI.GetInstance('content');
       editor.UpdateLinkedField();
    }
    submit_form_data('templates.php','cmd=updateordertemplate',
                     document.EditOnline,finish_update_order_template);
}

function finish_update_order_template(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) top.close_current_dialog();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function insert_template_field()
{
    if (inside_cms) var url = admin_path;
    else var url = script_prefix;
    if (document.EditOnline.Template)
       var template = document.EditOnline.Template.value;
    else var template = -1;
    url += 'templates.php?cmd=inserttemplatefield&Template='+template;
    if (inside_cms) url += '&insidecms=true';
    top.create_dialog('insert_template_field',null,null,400,230,false,url,null);
}

function load_insert_lists()
{
    for (var table_name in insert_lists) {
       var list_field = document.InsertField[table_name];
       if (! list_field) continue;
       var list_length = list_field.options.length;
       if (typeof(insert_lists[table_name]) == 'undefined') {
          alert('Option list for table '+table_name+' is missing');   return;
       }
       var insert_list = insert_lists[table_name];
       for (var field_index in insert_list) {
          var field_info = insert_list[field_index];
          if (! field_info) {
             alert('Missing field info for index '+field_index+' in table ' +
                   table_name);
             return;
          }
          else {
             var new_option = new Option(field_info[1],field_info[0]);
             list_field.options[list_length++] = new_option;
          }
       }
    }
    top.grow_current_dialog();
}

function insert_field(table_name)
{
    var list_field = document.InsertField[table_name];
    if (list_field.selectedIndex == 0) return;
    var field_name = list_field.options[list_field.selectedIndex].value;
    var insert_text = '{' + table_name + ':' + field_name + '}';

    if (inside_cms) 
       var edit_template_dialog = cms_top().get_dialog_frame('smartedit').contentWindow;
    else var edit_template_dialog = top.get_dialog_frame('edit_template').contentWindow;
    var current_field = edit_template_dialog.current_template_field;
    if (! edit_template_dialog.document.EditOnline.format) var format = 2;
    else var format = edit_template_dialog.get_selected_radio_button('format');
    html_editor = edit_template_dialog.html_editor;
    if ((format == 2) && (! current_field)) {
       if (html_editor == 'ckeditor') {
          var editor = edit_template_dialog.CKEDITOR.instances.content;
          if (editor.mode != 'wysiwyg') alert('You must be on WYSIWYG mode!');
          else editor.insertHtml(insert_text);
       }
       else {
          var editor = edit_template_dialog.FCKeditorAPI.GetInstance('content');
          if (editor.EditMode != edit_template_dialog.FCK_EDITMODE_WYSIWYG)
             alert('You must be on WYSIWYG mode!');
          else editor.InsertHtml(insert_text);
       }
    }
    else {
       //IE support
       if (document.selection) {
          current_field.focus();
          sel = document.selection.createRange();
          sel.text = insert_text;
          current_field.focus();
       }
       //MOZILLA/NETSCAPE support
       else if (current_field.selectionStart || current_field.selectionStart == '0') {
          var startPos = current_field.selectionStart;
          var endPos = current_field.selectionEnd;
          var scrollTop = current_field.scrollTop;
          current_field.value = current_field.value.substring(0, startPos) +
             insert_text + current_field.value.substring(endPos,current_field.value.length);
          current_field.focus();
          current_field.selectionStart = startPos + insert_text.length;
          current_field.selectionEnd = startPos + insert_text.length;
          current_field.scrollTop = scrollTop;
       } else {
          current_field.value += insert_text;
          current_field.focus();
       }
    }
    top.close_current_dialog();
}

