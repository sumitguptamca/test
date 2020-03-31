/*

     Inroads Control Panel/Shopping Cart - Media Tab JavaScript Functions

                     Written 2014-2018 by Randall Severy
                      Copyright 2014-2018 Inroads, LLC
*/

var script_prefix = '';
var libraries_grid = null;
var sections_grid = null;
var section_docs_grid = null;
var documents_grid = null;
var users_grid = null;
var media_no_filename = false;
var media_no_format = false;
var library_id = -1;
var library_type = -1;
var library_flags = 0;
var document_label = null;
var documents_label = null;
var documents_main_screen_flag = true;
var library_types = ['All Types','Images Only','Videos Only',
                     'Images and Videos'];
var section_types = ['Grid View','List View','Static Page'];
var menus = [];

var ALL_TYPES = 0;
var IMAGES_ONLY = 1;
var VIDEOS_ONLY = 2;
var IMAGES_VIDEOS = 3;

var USE_SUBSECTIONS = 1;
var MULTIPLE_DIRS = 2;
var USERS_DOWNLOADS = 4;

function resize_screen(new_width,new_height)
{
    if (top.skin) {
       if (top.buttons == 'left') {
          var button_column1 = document.getElementById('buttonColumn1');
          var button_column2 = document.getElementById('buttonColumn2');
          var offset = get_body_height() -
                       Math.max(sections_grid.height,button_column1.offsetHeight) -
                       Math.max(section_docs_grid.height,button_column2.offsetHeight);
       }
       else var offset = get_body_height() - sections_grid.height -
                         section_docs_grid.height;
       var grid_height = Math.floor((new_height - offset) / 2);
       if (grid_height < 0) grid_height = 0;
       if (top.buttons == 'left') {
          resize_grid(sections_grid,sections_grid.width,
                      Math.max(grid_height,button_column1.offsetHeight));
          resize_grid(section_docs_grid,section_docs_grid.width,
                      Math.max(grid_height,button_column2.offsetHeight));
       }
       else {
          resize_grid(sections_grid,sections_grid.width,grid_height);
          resize_grid(section_docs_grid,section_docs_grid.width,grid_height);
       }
    }
    else {
       var grid_height = ((new_height + 20) / 2) - 15;
       if (top.buttons == 'top') grid_height -= 40;
       resize_grid(sections_grid,new_width,grid_height)
       resize_grid(section_docs_grid,new_width,grid_height);
    }

    var titlebar2 = document.getElementById('titlebar2');
    var titlebar_height = top.get_outer_height(titlebar2);

    if (top.skin) {
       new_width = -1;
       new_height -= (offset - titlebar_height);
    }
    else {
       new_height -= 45;   new_width -= 15;
    }

    if (! top.skin) {
       var add_button = document.getElementById('add_section');
       var add_button_height = top.get_outer_height(add_button);
       var edit_button = document.getElementById('edit_section');
       var button_height = top.get_outer_height(edit_button);
       var sep_row = document.getElementById('section_docs_sep_row');
       var sep_height = grid_height - add_button_height - (button_height * 2) +
                        titlebar_height - 4;
       if (sep_height < 0) sep_height = 0;
       sep_row.style.height = '' + sep_height + 'px';
    }

    resize_grid(documents_grid,new_width,new_height);
    resize_grid(users_grid,new_width,new_height);
}

function resize_dialog(new_width,new_height)
{
    if (! libraries_grid) return;
    if (top.skin)
       resize_grid(libraries_grid,-1,new_height - get_grid_offset(libraries_grid));
    else resize_grid(libraries_grid,new_width,new_height)
}

function create_libraries_grid()
{
    var grid_size = get_default_grid_size();
    libraries_grid = new Grid('media_libraries',grid_size.width,grid_size.height);
    libraries_grid.set_columns(['ID','Name','Type','Menu','Menu Name']);
    libraries_grid.set_column_widths([0,200,110,120,190]);
    var query = 'select id,name,type,parent_menu,menu_name from media_libraries';
    libraries_grid.set_query(query);
    libraries_grid.set_order_by('name');
    libraries_grid.table.set_convert_cell_data(convert_library_data);
    libraries_grid.set_id('libraries_grid');
    libraries_grid.load(true);
    libraries_grid.set_double_click_function(edit_library);
    libraries_grid.display();
}

function reload_libraries_grid()
{
    libraries_grid.table.reset_data(true);
    libraries_grid.grid.refresh();
    window.setTimeout(function() { libraries_grid.table.restore_position(); },0);
}

function add_library()
{
    libraries_grid.table.save_position();
    top.create_dialog('add_library',null,null,630,410,false,
                      script_prefix + 'media.php?cmd=addlibrary',null);
}

function process_add_library()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=processaddlibrary',document.AddLibrary,
                     finish_add_library);
}

function finish_add_library(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('libraries').contentWindow.reload_libraries_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_library()
{
    if (libraries_grid.table._num_rows < 1) {
       alert('There are no media libraries to edit');   return;
    }
    var grid_row = libraries_grid.grid.getCurrentRow();
    var id = libraries_grid.grid.getCellText(0,grid_row);
    libraries_grid.table.save_position();
    top.create_dialog('edit_library',null,null,630,410,false,
                      script_prefix + 'media.php?cmd=editlibrary&id=' + id,
                      null);
}

function update_library()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=updatelibrary',document.EditLibrary,
                      finish_update_library);
}

function finish_update_library(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('libraries').contentWindow.reload_libraries_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_library()
{
    if (libraries_grid.table._num_rows < 1) {
       alert('There are no media libraries to delete');   return;
    }
    var grid_row = libraries_grid.grid.getCurrentRow();
    var id = libraries_grid.grid.getCellText(0,grid_row);
    var library = libraries_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the "' + library +
                           '" library?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    libraries_grid.table.save_position();
    call_ajax('media.php','cmd=deletelibrary&id=' + id,true,
              finish_delete_library);
}

function finish_delete_library(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_libraries_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function convert_library_data(col,row,text)
{
    if (col == 2) {
       var type = parse_int(text);
       if (typeof(library_types[type]) == 'undefined') return text;
       return library_types[type];
    }
    if (col == 3) {
       if (typeof(menus[text]) == 'undefined') return text;
       return menus[text];
    }
    return text;
}

function media_screen_onload()
{
    var new_document = document.getElementById('new_document');
    new_document.title = 'Upload New '+document_label+' to this Section';
    var add_section_doc = document.getElementById('add_section_doc');
    add_section_doc.title = 'Select Existing '+document_label +
                            ' from the Server to Add to this Section';
    var remove_document = document.getElementById('remove_document');
    remove_document.title = 'Remove the selected '+document_label +
                            ' from this Section but leave it on the Server';
    var delete_section_doc = document.getElementById('delete_section_doc');
    delete_section_doc.title = 'Remove the selected '+document_label +
                      ' from this Section *and* delete it from the Server';
}

function enable_media_button(button_id,state)
{
    var button = document.getElementById(button_id);
    if (! button) return;
    if (state) button.style.display = '';
    else button.style.display = 'none';
}

function enable_sections_buttons(state)
{
    enable_media_button('add_section',state);
    enable_media_button('edit_section',state);
    enable_media_button('delete_section',state);
    enable_media_button('section_docs_sep_row',state);
    enable_media_button('new_document',state);
    enable_media_button('add_section_doc',state);
    enable_media_button('edit_section_doc',state);
    enable_media_button('remove_document',state);
    enable_media_button('delete_section_doc',state);
}

function enable_documents_buttons(state)
{
    enable_media_button('add_document',state);
    enable_media_button('edit_document',state);
    enable_media_button('delete_document',state);
    enable_media_button('documents_search_sep_row',state);
    enable_media_button('documents_search_row',state);
}

function enable_user_buttons(state)
{
    enable_media_button('add_user',state);
    enable_media_button('edit_user',state);
    enable_media_button('delete_user',state);
    enable_media_button('users_search_sep_row',state);
    enable_media_button('users_search_row',state);
}

var current_content_id = 'sections_content';

function change_tab(tab,content_id)
{
    if (content_id == 'sections_content') {
       if (current_content_id == 'sections_content') reload_sections_grid();
       else enable_sections_buttons(true);
    }
    else enable_sections_buttons(false);
    if (content_id == 'documents_content') {
       if (current_content_id == 'documents_content') reload_documents_grid();
       else enable_documents_buttons(true);
    }
    else enable_documents_buttons(false);
    if (library_flags & USERS_DOWNLOADS) {
       if (content_id == 'users_content') {
          if (current_content_id == 'users_content') reload_users_grid();
          else enable_user_buttons(true);
       }
       else enable_user_buttons(false);
    }
    tab_click(tab,content_id);
    current_content_id = content_id;
}

function load_sections_grid()
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    var grid_width = grid_size.width;
    if (! top.skin) {
       grid_height -= 30;   grid_width -= 15;
    }
    if (top.buttons == 'top') grid_height -= 40;
    sections_grid = new Grid('media_sections',grid_width,grid_height);
    var columns = ['ID','Seq','Section Name','Display Name'];
    var column_widths = [0,30,300,150];
    var query = 'select id,sequence,name,display_name';
    if (library_type == ALL_TYPES) {
       columns.push('Type');   column_widths.push(60);
       query += ',type';
    }
    columns.push('# ' + documents_label);   column_widths.push(75);
    query += ',(select count(id) from media_section_docs where parent=' +
             'media_sections.id) as num_sections';
    if (library_flags & USE_SUBSECTIONS) {
       columns.push('# SubSections');
       columns.push('Parent Section');
       column_widths.push(75);
       column_widths.push(250);
       query += ',(select count(id) from media_subsections where parent=' +
                'media_sections.id) as num_subsections,(select s.name from ' +
                'media_sections s join media_subsections ms on ms.parent=' +
                's.id where ms.related_id=media_sections.id limit 1) as ' +
                'parent_section';
    }
    query += ' from media_sections';
    sections_grid.set_columns(columns);
    sections_grid.set_column_widths(column_widths);
    sections_grid.set_query(query);
    sections_grid.set_where('library=' + library_id);
    sections_grid.set_order_by('sequence');
    sections_grid.set_id('sections_grid');
    sections_grid.table.set_convert_cell_data(convert_sections_data);
    sections_grid.load(false);
    sections_grid.set_double_click_function(edit_section);
    sections_grid.grid.onCurrentRowChanged = select_section;
    sections_grid.display();
}

function reload_sections_grid()
{
    sections_grid.table.reset_data(false);
    sections_grid.grid.refresh();
    window.setTimeout(function() {
       sections_grid.table.restore_position();
       var grid_row = sections_grid.grid.getCurrentRow();
       select_section(grid_row);
    },0);
}

function select_section(row)
{
    var section_id = sections_grid.grid.getCellText(0,row);
    var name = sections_grid.grid.getCellText(2,row);
    var section_label = document.getElementById('section_label');
    section_label.innerHTML = name;
    section_docs_grid.set_where('sd.parent=' + section_id);
    section_docs_grid.table.reset_data(true);
    section_docs_grid.grid.refresh();
}

function add_section(library)
{
    sections_grid.table.save_position();
    var url = script_prefix + 'media.php?cmd=addsection&Library=' + library_id;
    top.create_dialog('add_section',null,null,800,430,false,url,null);
}

function process_add_section()
{
    if (! validate_form_field(document.AddSection.name,'Section Name'))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=processaddsection',
                     document.AddSection,finish_add_section);
}

function finish_add_section(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_sections_grid();
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function edit_section()
{
    if (sections_grid.table._num_rows < 1) {
       alert('There are no Sections to edit');   return;
    }
    var grid_row = sections_grid.grid.getCurrentRow();
    var id = sections_grid.grid.getCellText(0,grid_row);
    sections_grid.table.save_position();
    top.create_dialog('edit_section',null,null,800,430,false,
                      script_prefix + 'media.php?cmd=editsection&id=' + id,
                      null);
}

function update_section()
{
    if (! validate_form_field(document.EditSection.name,'Section Name'))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=updatesection',
                     document.EditSection,finish_update_section);
}

function finish_update_section(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_sections_grid();
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function delete_section()
{
    if (sections_grid.table._num_rows < 1) {
       alert('There are no Sections to delete');   return;
    }
    var grid_row = sections_grid.grid.getCurrentRow();
    var id = sections_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this Section?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    sections_grid.table.save_position();
    call_ajax('media.php','cmd=deletesection&id=' + id,true,
              finish_delete_section);
}

function finish_delete_section(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) reload_sections_grid();
    else ajax_request.display_error();
}

function convert_sections_data(col,row,text)
{
    if (library_type != ALL_TYPES) return text;
    if (col == 4) {
      var type = parse_int(text);
      if (typeof(section_types[type]) == 'undefined') return text;
      return section_types[type];
    }
    return text;
}

function resequence_sections(old_row,new_row)
{
    if (old_row == new_row) return true;
    var old_sequence = sections_grid.grid.getCellText(1,old_row);
    var new_sequence = sections_grid.grid.getCellText(1,new_row);
    var fields = 'cmd=resequencesection&OldSequence=' + old_sequence +
                 '&NewSequence=' + new_sequence;
    call_ajax('media.php',fields,true,
              function (ajax_request) {
                 finish_resequence_sections(ajax_request,new_row);
              });
}

function finish_resequence_sections(ajax_request,new_row)
{
    var status = ajax_request.get_status();
    if (status != 201) return false;
    sections_grid.table.reset_data(true);
    sections_grid.grid.refresh();
    sections_grid.grid.setSelectedRows([new_row]);
    sections_grid.grid.setCurrentRow(new_row);
    return true;
}

function move_section_top()
{
    if (sections_grid.table._num_rows < 1) return;
    var grid_row = parse_int(sections_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_sections(grid_row,0);
}

function move_section_up()
{
    if (sections_grid.table._num_rows < 1) return;
    var grid_row = parse_int(sections_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_sections(grid_row,grid_row - 1);
}

function move_section_down()
{
    var num_rows = sections_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(sections_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_sections(grid_row,grid_row + 1);
}

function move_section_bottom()
{
    var num_rows = sections_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(sections_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_sections(grid_row,num_rows - 1);
}

function setup_document_grid(doc_grid,main_screen_flag,include_sequence)
{
    var columns = ['ID'];
    var column_widths = [0];
    var query = 'select d.id';
    if (include_sequence) {
       columns.push('Seq');   column_widths.push(30);
       query += ',sd.sequence';
    }
    columns.push(document_label);
    if (media_no_filename) column_widths.push(0);
    else column_widths.push(200);
    query += ',d.filename';
    if (library_type == IMAGES_VIDEOS) {
       columns.push('Type');   columns.push('Title');
       if (media_no_format) column_widths.push(0);
       else column_widths.push(80);
       column_widths.push(300);
       query += ',d.format,d.title';
    }
    else if (library_type == IMAGES_ONLY) {
       columns.push('Title/Alt Text');   column_widths.push(300);
       query += ',d.title';
    }
    else if (library_type == VIDEOS_ONLY) {
       columns.push('Title');   column_widths.push(300);
       query += ',d.title';
    }
    else {
       columns.push('File Format');   columns.push('Title');
       if (media_no_format) column_widths.push(0);
       else column_widths.push(80);
       if (media_no_filename) column_widths.push(600);
       else column_widths.push(400);
       query += ',d.format,d.title';
    }
    if (main_screen_flag) {
       columns.push('Sections');   column_widths.push(150);
       query += ',(select group_concat(s.name separator ", ") from ' +
                'media_sections s join media_section_docs msd on msd.parent=' +
                's.id where msd.related_id=d.id) as sections';
       if (library_flags & USERS_DOWNLOADS) {
          columns.push('# Downloads');   column_widths.push(80);
          query += ',(select count(id) from media_downloads where document=' +
                   'd.id) as num_downloads';
       }
    }
    query += ' from media_documents d';
    if (include_sequence)
       query += ' join media_section_docs sd on sd.related_id=d.id';
    doc_grid.set_columns(columns);
    doc_grid.set_column_widths(column_widths);
    doc_grid.set_query(query);
}

function load_section_docs_grid(section_id)
{
    var grid_size = get_default_grid_size();
    var grid_height = Math.floor(grid_size.height / 2) - 15;
    if (top.buttons == 'top') grid_height -= 40;
    section_docs_grid = new Grid('media_documents',grid_size.width,
                                 grid_height);
    setup_document_grid(section_docs_grid,true,true);
    section_docs_grid.set_where('sd.parent=' + section_id);
    section_docs_grid.table.set_order_by('sd.sequence,d.id');
    section_docs_grid.table.set_convert_cell_data(convert_section_docs_data);
    if (library_type == IMAGES_VIDEOS)
       section_docs_grid.set_id('section_images_videos_grid');
    else if (library_type == IMAGES_ONLY)
       section_docs_grid.set_id('section_images_grid');
    else if (library_type == VIDEOS_ONLY)
       section_docs_grid.set_id('section_videos_grid');
    else section_docs_grid.set_id('section_docs_grid');
    section_docs_grid.load(true);
    section_docs_grid.set_double_click_function(edit_section_doc);
    section_docs_grid.display();
}

function reload_section_docs_grid()
{
    section_docs_grid.table.reset_data(true);
    section_docs_grid.grid.refresh();
    window.setTimeout(function() {
       section_docs_grid.table.restore_position();
    },0);
}

function resequence_section_docs(old_row,new_row)
{
    if (old_row == new_row) return true;
    var grid_row = sections_grid.grid.getCurrentRow();
    var section_id = sections_grid.grid.getCellText(0,grid_row);
    var old_sequence = section_docs_grid.grid.getCellText(1,old_row);
    var new_sequence = section_docs_grid.grid.getCellText(1,new_row);
    var fields = 'cmd=resequencedocument&Section=' + section_id +
                 '&OldSequence=' + old_sequence + '&NewSequence=' +
                 new_sequence + '&Library=' + library_id;
    call_ajax('media.php',fields,true,
              function (ajax_request) {
                 finish_resequence_section_docs(ajax_request,new_row);
              });
}

function finish_resequence_section_docs(ajax_request,new_row)
{
    var status = ajax_request.get_status();
    if (status != 201) return false;
    section_docs_grid.table.reset_data(true);
    section_docs_grid.grid.refresh();
    section_docs_grid.grid.setSelectedRows([new_row]);
    section_docs_grid.grid.setCurrentRow(new_row);
    return true;
}

function move_doc_top()
{
    if (section_docs_grid.table._num_rows < 1) return;
    var grid_row = parse_int(section_docs_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_section_docs(grid_row,0);
}

function move_doc_up()
{
    if (section_docs_grid.table._num_rows < 1) return;
    var grid_row = parse_int(section_docs_grid.grid.getCurrentRow());
    if (grid_row == 0) return;
    resequence_section_docs(grid_row,grid_row - 1);
}

function move_doc_down()
{
    var num_rows = section_docs_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(section_docs_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_section_docs(grid_row,grid_row + 1);
}

function move_doc_bottom()
{
    var num_rows = section_docs_grid.table._num_rows;
    if (num_rows < 1) return;
    var grid_row = parse_int(section_docs_grid.grid.getCurrentRow());
    if (grid_row == num_rows - 1) return;
    resequence_section_docs(grid_row,num_rows - 1);
}

function new_document(library)
{
    if (sections_grid.table._num_rows < 1) {
       alert('There are no Sections to add a new document to');   return;
    }
    var grid_row = sections_grid.grid.getCurrentRow();
    var id = sections_grid.grid.getCellText(0,grid_row);
    var name = sections_grid.grid.getCellText(2,grid_row);
    sections_grid.table.save_position();
    add_document(library,id,name);
}

function add_section_doc(library)
{
    if (sections_grid.table._num_rows < 1) {
       alert('There are no Sections to add a document to');   return;
    }
    var grid_row = sections_grid.grid.getCurrentRow();
    var id = sections_grid.grid.getCellText(0,grid_row);
    var name = sections_grid.grid.getCellText(2,grid_row);
    sections_grid.table.save_position();
    section_docs_grid.table.save_position();
    var url = script_prefix + 'media.php?cmd=selectdocument&Section=' + id +
              '&Name=' + encodeURIComponent(name) + '&Library=' + library_id;
    top.create_dialog('add_section_doc',null,null,900,800,false,url,null);
}

function edit_section_doc()
{
    if (section_docs_grid.table._num_rows < 1) {
       alert('There are no Documents to edit');   return;
    }
    var grid_row = section_docs_grid.grid.getCurrentRow();
    var id = section_docs_grid.grid.getCellText(0,grid_row);
    section_docs_grid.table.save_position();
    if ((library_type == IMAGES_ONLY) || (library_type == IMAGES_VIDEOS)) {
       var dialog_width = 825;   var dialog_height = 185;
    }
    else {
       var dialog_width = 785;   var dialog_height = 380;
    }
    top.create_dialog('edit_document',null,null,dialog_width,dialog_height,
                      false,script_prefix + 'media.php?cmd=editdocument&id=' +
                      id,null);
}

function remove_document(library)
{
    if (section_docs_grid.table._num_rows < 1) {
       alert('There are no Documents to delete');   return;
    }
    var grid_row = sections_grid.grid.getCurrentRow();
    var section_id = sections_grid.grid.getCellText(0,grid_row);
    grid_row = section_docs_grid.grid.getCurrentRow();
    var doc_id = section_docs_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to remove this Document ' +
                           'from this section?');
    if (! response) return;
    sections_grid.table.save_position();
    section_docs_grid.table.save_position();
    var fields = 'cmd=removedocument&id=' + doc_id + '&Section=' + section_id +
                 '&Library=' + library_id;
    call_ajax('media.php',fields,true,finish_remove_document);
}

function finish_remove_document(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var status = ajax_request.get_status();
    if (status == 201) reload_sections_grid();
}

function delete_section_doc()
{
    if (section_docs_grid.table._num_rows < 1) {
       alert('There are no Documents to delete');   return;
    }
    var grid_row = section_docs_grid.grid.getCurrentRow();
    var id = section_docs_grid.grid.getCellText(0,grid_row);
    var response = confirm('This will permanently delete the document from ' +
       'the server.  Use "Remove Document" to remove the document from only ' +
       'this section.  Are you sure you want to delete this Document?');
    if (! response) return;
    sections_grid.table.save_position();
    section_docs_grid.table.save_position();
    call_ajax('media.php','cmd=deletedocument&id=' + id,true,
              finish_delete_document);
}

function convert_section_docs_data(col,row,text)
{
    if (col == 4) return strip_html(text);
    return text;
}

function load_documents_grid(main_screen_flag)
{
    documents_main_screen_flag = main_screen_flag;
    var grid_size = get_default_grid_size();
    if (top.skin || (! main_screen_flag)) {
       var grid_height = grid_size.height;   var grid_width = grid_size.width;
    }
    else {
       var grid_height = grid_size.height - 45;
       var grid_width = grid_size.width - 15;
    }
    documents_grid = new Grid('media_documents',grid_size.width,grid_size.height);
    setup_document_grid(documents_grid,main_screen_flag,false);
    documents_grid.set_where('library=' + library_id);
    documents_grid.set_order_by('d.filename');
    if (library_type == IMAGES_VIDEOS)
       documents_grid.set_id('images_videos_grid');
    else if (library_type == IMAGES_ONLY)
       documents_grid.set_id('images_grid');
    else if (library_type == VIDEOS_ONLY) documents_grid.set_id('videos_grid');
    else documents_grid.set_id('documents_grid');
    documents_grid.table.set_convert_cell_data(convert_documents_data);
    documents_grid.load(false);
    if (main_screen_flag)
       documents_grid.set_double_click_function(edit_document);
    else documents_grid.set_double_click_function(select_document);
    documents_grid.display();
}

function reload_documents_grid()
{
    documents_grid.table.reset_data(false);
    documents_grid.grid.refresh();
    window.setTimeout(function() { documents_grid.table.restore_position(); },0);
}

function select_document()
{
    if (documents_grid.table._num_rows < 1) {
       alert('There are no Documents to select');   return;
    }
    var grid_row = documents_grid.grid.getCurrentRow();
    var id = documents_grid.grid.getCellText(0,grid_row);
    var library = document.SelectDocument.Library.value;
    var section_id = document.SelectDocument.Section.value;
    top.enable_current_dialog_progress(true);
    var fields = 'cmd=processselectdocument&id=' + id + '&Section=' +
                 section_id + '&Library=' + library;
    call_ajax('media.php',fields,true,finish_select_document);
}

function finish_select_document(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_sections_grid();
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function switch_document_type()
{
    var format = get_selected_list_value('format');
    var filename_row = document.getElementById('filename_row');
    var video_row = document.getElementById('video_row');
    var url_row = document.getElementById('url_row');
    if (format == 'Video') {
       filename_row.style.display = 'none';
       url_row.style.display = 'none';
       video_row.style.display = '';
    }
    else {
       filename_row.style.display = '';
       url_row.style.display = '';
       video_row.style.display = 'none';
    }
    if (top.skin) var content_tab = document.getElementById('content_tab');
    else var content_tab = document.getElementById('content_tab_cell');
    if (format == 'Content') content_tab.style.display = '';
    else content_tab.style.display = 'none';
}

function close_add_cms_document(user_close)
{
    top.using_admin_top = false;
}

function add_cms_document(cms_url,dir,frame_name)
{
    top.using_admin_top = true;
    var url = cms_url + '?AddDocument=Go&_JavaScript=Yes&_iFrameDialog=Yes' +
              '&_CreateOnly=Yes&_FinishFunction=cms_top().get_dialog_frame(\'' +
              frame_name + '\').contentWindow.finish_add_cms_document(\'FILENAME\')' +
              '&_CurrentDir=' + encodeURIComponent(dir) + '&iFrameTime=' +
              (new Date()).getTime();
    top.create_dialog('add_cms_document',null,null,800,175,false,url,null);
    top.set_dialog_onclose('add_cms_document',close_add_cms_document);
}

function finish_add_cms_document(new_filename)
{
    url = new_filename.replace(/\\/g,'/');
    if (document.AddDocument) var form = document.AddDocument;
    else var form = document.EditDocument;
    form.url.value = url;
    top.close_current_dialog();
}

function add_document(section_id,section_name)
{
    documents_grid.table.save_position();
    var url = script_prefix + 'media.php?cmd=adddocument&Library=' +
              library_id;
    if (typeof(section_id) != 'undefined')
       url += '&Section=' + section_id + '&Name=' +
              encodeURIComponent(section_name);
    top.create_dialog('add_document',null,null,805,185,false,url,null);
}

function process_add_document()
{
    if (document.AddDocument.format &&
        (! validate_form_field(document.AddDocument.format,'File Format')))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=processadddocument',
                     document.AddDocument,finish_add_document);
}

function finish_add_document(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_documents_grid();
       top.get_content_frame().reload_sections_grid();
       top.close_current_dialog();
    }
}

function edit_document()
{
    if (documents_grid.table._num_rows < 1) {
       alert('There are no Documents to edit');   return;
    }
    var grid_row = documents_grid.grid.getCurrentRow();
    var id = documents_grid.grid.getCellText(0,grid_row);
    documents_grid.table.save_position();
    top.create_dialog('edit_document',null,null,785,185,false,
                      script_prefix + 'media.php?cmd=editdocument&id=' + id,
                      null);
}

function update_document()
{
    if (document.EditDocument.format &&
        (! validate_form_field(document.EditDocument.format,'File Format')))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=updatedocument',
                     document.EditDocument,finish_update_document);
}

function finish_update_document(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_documents_grid();
       top.get_content_frame().reload_section_docs_grid();
       top.close_current_dialog();
    }
}

function delete_document(library)
{
    if (documents_grid.table._num_rows < 1) {
       alert('There are no Documents to delete');   return;
    }
    var grid_row = documents_grid.grid.getCurrentRow();
    var id = documents_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this Document?');
    if (! response) return;
    documents_grid.table.save_position();
    var url = 'cmd=deletedocument&id=' + id + '&Library=' + library_id;
    call_ajax('media.php',url,true,finish_delete_document);
}

function finish_delete_document(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    var status = ajax_request.get_status();
    if (status == 201) {
       reload_documents_grid();
       reload_sections_grid();
    }
}

function convert_documents_data(col,row,text)
{
    if (col == 3) return strip_html(text);
    return text;
}

function search_documents()
{
    var query = document.documents_search.query.value;
    if (query == '') {
       reset_search();   return;
    }
    var search_label = 'Searching ' + documents_label + '...';
    top.display_status('Search',search_label,350,100,null);
    window.setTimeout(function() {
       var where = "filename like '%" + query + "%' or title like '%" +
                   query + "%' or description like '%" + query + "%'";
       documents_grid.set_where(where);
       documents_grid.table.reset_data(false);
       documents_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_documents_search()
{
    if (library_type == IMAGES_ONLY)
    var search_label = 'Loading All ' + documents_label + '...';
    top.display_status('Search',search_label,350,100,null);
    window.setTimeout(function() {
       document.documents_search.query.value = '';
       documents_grid.set_where('');
       documents_grid.table.reset_data(false);
       documents_grid.grid.refresh();
       top.remove_status();
    },0);
}

function change_doc_tab(tab,content_id)
{
    tab_click(tab,content_id);
    top.grow_current_dialog();
}

function load_users_grid()
{
    var grid_size = get_default_grid_size();
    if (top.skin) {
       var grid_height = grid_size.height;   var grid_width = grid_size.width;
    }
    else {
       var grid_height = grid_size.height - 45;
       var grid_width = grid_size.width - 15;
    }
    users_grid = new Grid('media_users',grid_size.width,grid_size.height);
    users_grid.set_columns(['ID','Username','First Name','Last Name',
                            'E-Mail Address','# Downloads']);
    users_grid.set_column_widths([0,100,100,100,200,100]);
    var query = 'select id,username,firstname,lastname,email,' +
                '(select count(id) from media_downloads where user=' +
                'media_users.id) as num_downloads from media_users';
    users_grid.set_query(query);
    users_grid.set_where('library=' + library_id);
    users_grid.set_order_by('lastname,firstname');
    users_grid.set_id('users_grid');
    users_grid.load(false);
    users_grid.set_double_click_function(edit_user);
    users_grid.display();
}

function reload_users_grid()
{
    users_grid.table.reset_data(false);
    users_grid.grid.refresh();
    window.setTimeout(function() { users_grid.table.restore_position(); },0);
}

function add_user()
{
    users_grid.table.save_position();
    var url = script_prefix + 'media.php?cmd=adduser&Library=' + library_id;
    top.create_dialog('add_user',null,null,580,175,false,url,null);
}

function process_add_user()
{
    if (! validate_form_field(document.AddUser.username,'Username'))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=processadduser',
                     document.AddUser,finish_add_user);
}

function finish_add_user(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_users_grid();
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function edit_user()
{
    if (users_grid.table._num_rows < 1) {
       alert('There are no Users to edit');   return;
    }
    var grid_row = users_grid.grid.getCurrentRow();
    var id = users_grid.grid.getCellText(0,grid_row);
    users_grid.table.save_position();
    top.create_dialog('edit_user',null,null,580,175,false,
                      script_prefix + 'media.php?cmd=edituser&id=' + id,null);
}

function update_user()
{
    if (! validate_form_field(document.EditUser.name,'Username'))
       return;
    top.enable_current_dialog_progress(true);
    submit_form_data('media.php','cmd=updateuser',
                     document.EditUser,finish_update_user);
}

function finish_update_user(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) {
       top.get_content_frame().reload_users_grid();
       top.close_current_dialog();
    }
    else ajax_request.display_error();
}

function delete_user()
{
    if (users_grid.table._num_rows < 1) {
       alert('There are no Users to delete');   return;
    }
    var grid_row = users_grid.grid.getCurrentRow();
    var id = users_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this User?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    users_grid.table.save_position();
    call_ajax('media.php','cmd=deleteuser&id=' + id,true,
              finish_delete_user);
}

function finish_delete_user(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status == 201) reload_users_grid();
    else ajax_request.display_error();
}

function search_users()
{
    var query = document.users_search.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching Users...',350,100,null);
    window.setTimeout(function() {
       var where = "username like '%" + query + "%' or firstname like '%" +
                   query + "%' or lastname like '%" + query +
                   "%' or email like '%" + query + "%'";
       users_grid.set_where(where);
       users_grid.table.reset_data(false);
       users_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_users_search()
{
    top.display_status('Search','Loading All Users...',350,100,null);
    window.setTimeout(function() {
       document.users_search.query.value = '';
       users_grid.set_where('');
       users_grid.table.reset_data(false);
       users_grid.grid.refresh();
       top.remove_status();
    },0);
}

