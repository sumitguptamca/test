<?php
/*
               Inroads Control Panel/Shopping Cart - Media Tab

                     Written 2014-2018 by Randall Severy
                      Copyright 2014-2018 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
if (file_exists('../cartengine/utility.php')) {
   $shopping_cart = true;   $utility_prefix = '../cartengine/';
}
else {
   $shopping_cart = false;   $utility_prefix = '';
   if (file_exists('products.php')) $catalog_site = true;
   else $catalog_site = false;
}
require_once 'media-common.php';
if (file_exists('../admin/custom-config.php'))
   require_once '../admin/custom-config.php';

define('ALL_TYPES',0);
define('IMAGES_ONLY',1);
define('VIDEOS_ONLY',2);
define('IMAGES_VIDEOS',3);

define('USE_SUBSECTIONS',1);
define('MULTIPLE_DIRS',2);
define('USERS_DOWNLOADS',4);

require_once $utility_prefix.'utility.php';
if (! isset($media_no_filename)) $media_no_filename = false;
if (! isset($media_no_format)) $media_no_format = false;
if (! isset($media_external_urls)) $media_external_urls = false;
require_once $utility_prefix.'sublist.php';

function library_record_definition()
{
    $library_record = array();
    $library_record['id'] = array('type' => INT_TYPE);
    $library_record['id']['key'] = true;
    $library_record['name'] = array('type' => CHAR_TYPE);
    $library_record['type'] = array('type' => INT_TYPE);
    $library_record['flags'] = array('type' => INT_TYPE);
    $library_record['doc_dir'] = array('type' => CHAR_TYPE);
    $library_record['thumb_dir'] = array('type' => CHAR_TYPE);
    $library_record['url_dir'] = array('type' => CHAR_TYPE);
    $library_record['template_dir'] = array('type' => CHAR_TYPE);
    $library_record['parent_menu'] = array('type' => CHAR_TYPE);
    $library_record['menu_name'] = array('type' => CHAR_TYPE);
    $library_record['cookie_name'] = array('type' => CHAR_TYPE);
    $library_record['image_size_slarge'] = array('type' => CHAR_TYPE);
    $library_record['image_size_ssmall'] = array('type' => CHAR_TYPE);
    $library_record['image_size_doc'] = array('type' => CHAR_TYPE);
    $library_record['image_size_thumb'] = array('type' => CHAR_TYPE);
    return $library_record;
}

function page_record_definition()
{
    $page_record = array();
    $page_record['id'] = array('type' => INT_TYPE);
    $page_record['id']['key'] = true;
    $page_record['parent'] = array('type' => INT_TYPE);
    $page_record['filename'] = array('type' => CHAR_TYPE);
    return $page_record;
}

function section_record_definition()
{
    $section_record = array();
    $section_record['id'] = array('type' => INT_TYPE);
    $section_record['id']['key'] = true;
    $section_record['library'] = array('type' => INT_TYPE);
    $section_record['name'] = array('type' => CHAR_TYPE);
    $section_record['display_name'] = array('type' => CHAR_TYPE);
    $section_record['title'] = array('type' => CHAR_TYPE);
    $section_record['subtitle'] = array('type' => CHAR_TYPE);
    $section_record['type'] = array('type' => INT_TYPE);
    $section_record['large_image'] = array('type' => CHAR_TYPE);
    $section_record['small_image'] = array('type' => CHAR_TYPE);
    $section_record['sequence'] = array('type' => INT_TYPE);
    $section_record['summary'] = array('type' => CHAR_TYPE);
    $section_record['content'] = array('type' => CHAR_TYPE);
    return $section_record;
}

function document_record_definition()
{
    $document_record = array();
    $document_record['id'] = array('type' => INT_TYPE);
    $document_record['id']['key'] = true;
    $document_record['library'] = array('type' => INT_TYPE);
    $document_record['filename'] = array('type' => CHAR_TYPE);
    $document_record['image'] = array('type' => CHAR_TYPE);
    $document_record['format'] = array('type' => CHAR_TYPE);
    $document_record['title'] = array('type' => CHAR_TYPE);
    $document_record['subtitle'] = array('type' => CHAR_TYPE);
    $document_record['doc_date'] = array('type' => CHAR_TYPE);
    $document_record['description'] = array('type' => CHAR_TYPE);
    $document_record['url'] = array('type' => CHAR_TYPE);
    $document_record['content'] = array('type' => CHAR_TYPE);
    return $document_record;
}

function download_record_definition()
{
    $download_record = array();
    $download_record['id'] = array('type' => INT_TYPE);
    $download_record['id']['key'] = true;
    $download_record['document'] = array('type' => INT_TYPE);
    $download_record['user'] = array('type' => INT_TYPE);
    $download_record['download_date'] = array('type' => INT_TYPE);
    return $download_record;
}

function load_tab_containers($db)
{
    global $main_tabs,$main_tab_order,$prefs_cookie,$shopping_cart;

    require_once 'adminperms.php';
    require_once 'maintabs.php';
    require_once '../engine/modules.php';
    if ($shopping_cart) require_once 'cartconfig-common.php';
    load_main_tabs($db,~0,~0,~0,array(),false);

    $tabs = array();
    foreach ($main_tab_order as $index => $tab_name) {
       if ($main_tabs[$tab_name][3] == TAB_CONTAINER)
          $tabs[$tab_name] = $main_tabs[$tab_name][0];
    }
    return $tabs;
}

function display_libraries()
{
    global $shopping_cart;

    $db = new DB;
    $tabs = load_tab_containers($db);

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_script_file('media.js');
    if (file_exists('../admin/custom-config.js'))
       $dialog->add_script_file('../admin/custom-config.js');
    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart) $head_block .= "      script_prefix='../cartengine/';\n";
    foreach ($tabs as $tab => $tab_name)
       $head_block .= "      menus['".$tab."'] = '".$tab_name."';\n";
    $head_block .= '    </script>';
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('media_libraries');
    $dialog->set_help('media_libraries');
    $dialog->start_body('Media Libraries');
    $dialog->set_button_width(140);
    $dialog->start_button_column();
    $dialog->add_button('Add Library','images/AddCategory.png',
                        'add_library();');
    $dialog->add_button('Edit Library','images/EditCategory.png',
                        'edit_library();');
    $dialog->add_button('Delete Library','images/DeleteCategory.png',
                        'delete_library();');
    $dialog->add_button('Close','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->write("        <script>create_libraries_grid();</script>\n");
    $dialog->end_body();
}

function add_image_size_row($dialog,$prompt,$row,$field_name)
{
    $sizes = get_row_value($row,$field_name);
    if ($sizes == '') $sizes = array('','');
    else $sizes = explode('|',$sizes);
    $dialog->start_row($prompt.':','middle');
    $dialog->write("Width:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                   $field_name."_width\" size=5 value=\"".$sizes[0] .
                   "\">&nbsp;&nbsp;&nbsp;\n");
    $dialog->write("Height:&nbsp;<input type=\"text\" class=\"text\" name=\"" .
                   $field_name."_height\" size=5 value=\"".$sizes[1] .
                   "\">\n");
    $dialog->end_row();
}

function display_library_fields($dialog,$edit_type,$row,$db)
{
    $tabs = load_tab_containers($db);

    if ($edit_type == UPDATERECORD) {
       $id = get_row_value($row,'id');
       $dialog->add_hidden_field('id',$id);
    }
    $dialog->add_edit_row('Name:','name',$row,50);
    $parent_menu = get_row_value($row,'parent_menu');
    $dialog->start_row('Menu:','middle');
    $dialog->start_choicelist('parent_menu');
    $dialog->add_list_item('','',(! $parent_menu));
    foreach ($tabs as $tab => $tab_name)
       $dialog->add_list_item($tab,$tab_name,$parent_menu == $tab);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->add_edit_row('Menu Name:','menu_name',$row,50);

    $type = get_row_value($row,'type');
    $dialog->start_row('Library Type:','middle');
    $dialog->start_choicelist('type');
    $dialog->add_list_item('0','All Types',$type == ALL_TYPES);
    $dialog->add_list_item('1','Images Only',$type == IMAGES_ONLY);
    $dialog->add_list_item('2','Videos Only',$type == VIDEOS_ONLY);
    $dialog->add_list_item('3','Images and Videos',$type == IMAGES_VIDEOS);
    $dialog->end_choicelist();
    $dialog->end_row();

    $flags = get_row_value($row,'flags');
    $dialog->start_row('Options:','top');
    $dialog->add_checkbox_field('flag1','Use SubSections',
                                $flags & USE_SUBSECTIONS);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('flag2','Multiple Directories',
                                $flags & MULTIPLE_DIRS);
    $dialog->write("<br>\n");
    $dialog->add_checkbox_field('flag4','Users and Downloads',
                                $flags & USERS_DOWNLOADS);
    $dialog->end_row();

    $dialog->add_edit_row('Document Directory:','doc_dir',$row,50);
    $dialog->add_edit_row('Thumbnail Directory:','thumb_dir',$row,50);
    $dialog->add_edit_row('URL Directory:','url_dir',$row,50);
    $dialog->add_edit_row('Template Directory:','template_dir',$row,50);
    $dialog->add_edit_row('Cookie Name:','cookie_name',$row,50);

    add_image_size_row($dialog,'Section Large Image',$row,'image_size_slarge');
    add_image_size_row($dialog,'Section Small Image',$row,'image_size_ssmall');
    add_image_size_row($dialog,'Document Image',$row,'image_size_doc');
    add_image_size_row($dialog,'Thumbnail Image',$row,'image_size_thumb');
}

function parse_image_size($field_name,&$library_record)
{
    $width = get_form_field($field_name.'_width');
    $height = get_form_field($field_name.'_height');
    $library_record[$field_name]['value'] = $width.'|'.$height;
}

function parse_library_fields($db,&$library_record)
{
    $db->parse_form_fields($library_record);
    $flags = 0;
    if (get_form_field('flag1') == 'on') $flags |= USE_SUBSECTIONS;
    if (get_form_field('flag2') == 'on') $flags |= MULTIPLE_DIRS;
    if (get_form_field('flag4') == 'on') $flags |= USERS_DOWNLOADS;
    $library_record['flags']['value'] = $flags;
    parse_image_size('image_size_slarge',$library_record);
    parse_image_size('image_size_ssmall',$library_record);
    parse_image_size('image_size_doc',$library_record);
    parse_image_size('image_size_thumb',$library_record);
}

function load_library($db,$library,&$error)
{
    $query = 'select * from media_libraries where id=?';
    $query = $db->prepare_query($query,$library);
    $library_info = $db->get_record($query);
    if (! $library_info) {
       if (isset($db->error)) $error = $db->error;
       else $error = 'Media Library #'.$library.' not found';
       return null;
    }
    switch ($library_info['type']) {
       case ALL_TYPES: $d_label = 'Document';   $ds_label = 'Documents';
                       break;
       case IMAGES_ONLY: $d_label = 'Image';   $ds_label = 'Images';   break;
       case VIDEOS_ONLY: $d_label = 'Video';   $ds_label = 'Videos';   break;
       case IMAGES_VIDEOS: $d_label = 'Image/Video';
                           $ds_label = 'Images and Videos';   break;
    }
    $library_info['document_label'] = $d_label;
    $library_info['documents_label'] = $ds_label;
    return $library_info;
}

function add_library()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_script_file('media.js');
    $dialog->set_body_id('add_library');
    $dialog->set_help('add_library');
    $dialog->start_body('Add Media Library');
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Add Library','images/AddCategory.png',
                        'process_add_library();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','AddLibrary');
    $dialog->start_field_table();
    display_library_fields($dialog,ADDRECORD,array(),$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_library()
{
    $db = new DB;
    $library_record = library_record_definition();
    parse_library_fields($db,$library_record);
    if (! $db->insert('media_libraries',$library_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Media Library Added');
    log_activity('Added Media Library '.$library_record['name']['value'] .
                 ' (#'.$db->insert_id().')');
}

function edit_library()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = load_library($db,$id,$error);
    if (! $row) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_script_file('media.js');
    $dialog_title = 'Edit Media Library (#'.$id.')';
    $dialog->set_body_id('edit_library');
    $dialog->set_help('edit_library');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(135);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_library();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','EditLibrary');
    $dialog->start_field_table();
    display_library_fields($dialog,UPDATERECORD,$row,$db);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_library()
{
    $db = new DB;
    $library_record = library_record_definition();
    parse_library_fields($db,$library_record);
    if (! $db->update('media_libraries',$library_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Media Library Updated');
    log_activity('Updated Media Library '.$library_record['name']['value'] .
                 ' (#'.$library_record['id']['value'].')');
}

function delete_library()
{
    $id = get_form_field('id');
    $db = new DB;
    $library_record = library_record_definition();
    $library_record['id']['value'] = $id;
    if (! $db->delete('media_libraries',$library_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Media Library Deleted');
    log_activity('Deleted Media Library #'.$id);
}

function add_head_block(&$screen,$library_info)
{
    global $shopping_cart;

    $head_block = "<script type=\"text/javascript\">\n";
    if ($shopping_cart)
       $head_block .= "      script_prefix='../cartengine/';\n";
    $head_block .= '      library_id = '.$library_info['id'].";\n";
    $head_block .= '      library_type = '.$library_info['type'].";\n";
    $head_block .= '      library_flags = '.$library_info['flags'].";\n";
    $head_block .= "      document_label = '" .
                   $library_info['document_label']."';\n";
    $head_block .= "      documents_label = '" .
                   $library_info['documents_label']."';\n";
    $head_block .= '    </script>';
    $screen->add_head_line($head_block);
}

function add_main_tabs($screen,$library_info)
{
    $screen->start_tab_section();
    $screen->start_tab_row('sections_tab','sections_content');
    $screen->add_tab('sections_tab','Sections','sections_tab',
                     'sections_content','change_tab',true,null,FIRST_TAB);
    $screen->add_tab('documents_tab',$library_info['documents_label'],
                     'documents_tab','documents_content','change_tab');
    if ($library_info['flags'] & USERS_DOWNLOADS)
       $screen->add_tab('users_tab','Users','users_tab','users_content',
                        'change_tab');
    $screen->end_tab_row();
}


function display_media_screen()
{
    global $utility_prefix;

    $library = get_form_field('library');
    if (! $library) $library = 0;
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $query = 'select id,name from media_sections where library=? order by ' .
             'sequence limit 1';
    $query = $db->prepare_query($query,$library);
    $row = $db->get_record($query);
    if ($row) {
       $section_id = $row['id'];   $name = $row['name'];
    }
    else {
       $section_id = -1;   $name = '';
    }
    $docs_label = $library_info['documents_label'].' in Section <span id="' .
                  'section_label">'.$name.'</span>:';
    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet($utility_prefix.'utility.css');
    $screen->add_style_sheet('media.css');
    $screen->add_script_file('media.js');
    add_head_block($screen,$library_info);
    $screen->set_body_id('media');
    $screen->set_help('media');
    $screen->set_onload_function('media_screen_onload();');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar($library_info['menu_name']);
       add_main_tabs($screen,$library_info);
       $screen->start_title_filters();
       add_search_box($screen,'search_documents','reset_documents_search',
                      'documents_search',false);
       add_search_box($screen,'search_users','reset_users_search',
                      'users_search',false);
       $screen->end_title_filters();
       $screen->end_title_bar();
       $screen->start_tab_content('sections_content',true);
       $screen->start_section();
    }
    $screen->set_button_width(170);
    $screen->start_button_column();
    if (! $screen->skin) $screen->add_button_separator('buttons_row',40,true);

    $screen->add_button('Add Section','images/AddCategory.png',
                        'add_section('.$library.');','add_section',true);
    $screen->add_button('Edit Section','images/EditCategory.png',
                        'edit_section();','edit_section',true);
    $screen->add_button('Delete Section','images/DeleteCategory.png',
                        'delete_section();','delete_section',true);
    if (! $screen->skin) {
        $screen->add_button_separator('section_docs_sep_row',300,true);
        $screen->add_button('New '.$library_info['document_label'],
                            'images/AddProduct.png','new_document('.$library.');',
                            'new_document',true);
        $screen->add_button('Add '.$library_info['document_label'],
                            'images/AddProduct.png','add_section_doc('.$library.');',
                            'add_section_doc',true);
        $screen->add_button('Edit '.$library_info['document_label'],
                            'images/EditDocument.png','edit_section_doc();',
                            'edit_section_doc',true);
        $screen->add_button('Remove '.$library_info['document_label'],
                            'images/DeleteDocument.png','remove_document(' .
                            $library.');','remove_document',true);
        $screen->add_button('Delete '.$library_info['document_label'],
                            'images/DeleteDocument.png','delete_section_doc();',
                            'delete_section_doc',true);
    }
    $screen->end_button_column();
    if (! $screen->skin) {
       $screen->start_field_table();
       add_main_tabs($screen);
    }

    $screen->write("          <table cellspacing=\"0\" cellpadding=\"0\" " .
                   "class=\"sections_table\"><tr valign=\"top\">\n");
    $screen->write("            <td class=\"sections_grid_cell\">\n");
    if ($screen->skin)
       $screen->write("        <div class=\"fieldSection\">\n");
    else $screen->write("        <div style=\"padding: 4px;\">\n");
    $screen->write("        <script type=\"text/javascript\">\n");
    $screen->write("           load_sections_grid();\n");
    $screen->write("        </script>\n");
    $screen->write("        </div>\n");
    $screen->write("        </td>\n");
    if ($screen->skin)
       $screen->write("          <td class=\"miniButtons\">\n");
    else $screen->write("          <td width=\"80\" nowrap align=\"center\" " .
                        "style=\"padding-top:10px;\">\n");
    $screen->add_dialog_button("Top","images/MoveTop.png","move_section_top();",
                               true,false,"miniTopButton");
    $screen->add_dialog_button("Up","images/MoveUp.png","move_section_up();",
                               true,false,"miniUpButton");
    $screen->add_dialog_button("Down","images/MoveDown.png","move_section_down();",
                               true,false,"miniDownButton");
    $screen->add_dialog_button("Bottom","images/MoveBottom.png",
                               "move_section_bottom();",true,false,
                               "miniBottomButton");
    $screen->write("          </td></tr></table>\n");
    if ($screen->skin) {
       $screen->end_section();
       $screen->start_section();
    }
    $screen->start_title_bar($docs_label);
    $screen->end_title_bar();
    if ($screen->skin) {
       $screen->start_button_column();
       $screen->add_button('New '.$library_info['document_label'],
                           'images/AddProduct.png','new_document('.$library.');',
                           'new_document',true);
       $screen->add_button('Add '.$library_info['document_label'],
                           'images/AddProduct.png','add_section_doc('.$library.');',
                           'add_section_doc',true);
       $screen->add_button('Edit '.$library_info['document_label'],
                           'images/EditDocument.png','edit_section_doc();',
                           'edit_section_doc',true);
       $screen->add_button('Remove '.$library_info['document_label'],
                           'images/DeleteDocument.png','remove_document();',
                           'remove_document',true);
       $screen->add_button('Delete '.$library_info['document_label'],
                           'images/DeleteDocument.png','delete_section_doc();',
                           'delete_section_doc',true);
       $screen->end_button_column();
    }
    $screen->write("          <table cellspacing=\"0\" cellpadding=\"0\" " .
                   "class=\"section_docs_table\"><tr valign=\"top\">\n");
    $screen->write("            <td class=\"section_docs_grid_cell\">" .
                   "<div class=\"section_documents_grid\"><script>\n");
    $screen->write("             load_section_docs_grid(".$section_id.");\n");
    $screen->write("          </script></div></td>\n");
    if ($screen->skin)
       $screen->write("          <td class=\"miniButtons\">\n");
    else $screen->write("          <td width=\"80\" nowrap align=\"center\" " .
                        "style=\"padding-top:10px;\">\n");
    $screen->add_dialog_button("Top","images/MoveTop.png","move_doc_top();",true,
                               false,"miniTopButton");
    $screen->add_dialog_button("Up","images/MoveUp.png","move_doc_up();",true,
                               false,"miniUpButton");
    $screen->add_dialog_button("Down","images/MoveDown.png","move_doc_down();",
                               true,false,"miniDownButton");
    $screen->add_dialog_button("Bottom","images/MoveBottom.png",
                               "move_doc_bottom();",true,false,"miniBottomButton");
    $screen->write("          </td></tr></table>\n");
    if ($screen->skin) $screen->end_section();
    else $screen->write("        </div>\n");
    $screen->end_tab_content();

    $screen->start_tab_content('documents_content',false);
    $screen->start_button_column();
    $screen->add_button('Add '.$library_info['document_label'],
                        'images/AddProduct.png','add_document();',
                        'add_document',false);
    $screen->add_button('Edit '.$library_info['document_label'],
                        'images/EditDocument.png','edit_document();',
                        'edit_document',false);
    $screen->add_button('Delete '.$library_info['document_label'],
                        'images/DeleteDocument.png','delete_document();',
                        'delete_document',false);
    if (! $screen->skin)
       add_search_box($screen,'search_documents','reset_documents_search',
                      'documents_search',true);
    $screen->end_button_column();

    if ($screen->skin)
       $screen->write("        <div class=\"fieldSection\">\n");
    else $screen->write("        <div style=\"padding: 4px;\">\n");
    $screen->write("        <script type=\"text/javascript\">\n");
    $screen->write("           load_documents_grid(true);\n");
    $screen->write("        </script>\n");
    $screen->write("        </div>\n");
    $screen->end_section(! ($library_info['flags'] & USERS_DOWNLOADS));
    $screen->end_tab_content();

    if ($library_info['flags'] & USERS_DOWNLOADS) {
       $screen->start_tab_content('users_content',false);
       $screen->start_button_column();
       $screen->add_button('Add User','images/AddUser.png',
                           'add_user();','add_user',false);
       $screen->add_button('Edit User','images/EditUser.png',
                           'edit_user();','edit_user',false);
       $screen->add_button('Delete User','images/DeleteUser.png',
                           'delete_user();','delete_user',false);
       if (! $screen->skin)
          add_search_box($screen,'search_users','reset_users_search',
                         'users_search',false);
       $screen->end_button_column();
       if ($screen->skin)
          $screen->write("        <div class=\"fieldSection\">\n");
       else $screen->write("        <div style=\"padding: 4px;\">\n");
       $screen->write("        <script type=\"text/javascript\">\n");
       $screen->write("           load_users_grid();\n");
       $screen->write("        </script>\n");
       $screen->write("        </div>\n");
       $screen->end_section(true);
       $screen->end_tab_content();
    }

    if (! $screen->skin) $screen->end_field_table();
    $screen->end_body();
}

function display_section_fields($dialog,$edit_type,$row,$library_info)
{
    global $media_no_filename;

    if ($edit_type == UPDATERECORD) $frame_name = 'edit_section';
    else $frame_name = 'add_section';
    $thumb_dir = $library_info['thumb_dir'];
    if (substr($thumb_dir,-1) != '/') $thumb_dir .= '/';

    $section_types = array('Grid View','List View','Static Page');
    if ($edit_type == UPDATERECORD) {
       $id = get_row_value($row,'id');
       $dialog->add_hidden_field('id',$id);
    }
    $dialog->add_hidden_field('library',$library_info['id']);

    if ($edit_type == UPDATERECORD) {
       $dialog->start_tab_section('tab_section');
       $dialog->start_tab_row('section_tab','section_content');
       $dialog->add_tab('section_tab','Section','section_tab','section_content',
                        null,true,null,FIRST_TAB);
       if ($library_info['flags'] & USE_SUBSECTIONS) {
          $dialog->add_tab('subsection_tab','SubSections','subsection_tab',
                           'subsection_content');
          $dialog->add_tab('parentsection_tab','Parent Sections','parentsection_tab',
                           'parentsection_content');
       }
       $dialog->add_tab('media_documents_tab',$library_info['documents_label'],
                        'media_documents_tab','media_documents_content',
                        null,true,null,LAST_TAB);
       $dialog->end_tab_row('tab_row_middle');

       $dialog->start_tab_content('section_content',true);
       $dialog->start_field_table('content_table');
    }
    $dialog->add_edit_row('Section Name:','name',$row,85);
    $dialog->add_edit_row('Display Name:','display_name',$row,85);
    $dialog->add_textarea_row('Title:','title',$row,2,86,WRAP_SOFT);
    $dialog->add_textarea_row('SubTitle:','subtitle',$row,2,86,WRAP_SOFT);
    if ($library_info['type'] == ALL_TYPES) {
       $dialog->start_row('Type:','middle');
       $dialog->start_choicelist('type');
       $type = get_row_value($row,'type');
       if ($type === null) $type = 0;
       foreach ($section_types as $section_type => $type_label)
          $dialog->add_list_item($section_type,$type_label,$type == $section_type);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    $multiple_dirs = ($library_info['flags'] & MULTIPLE_DIRS);
    $dialog->add_browse_row('Large Image:','large_image',$row,45,$frame_name,
       $thumb_dir,(! $multiple_dirs),true,true,
       $multiple_dirs,false);
    $dialog->add_browse_row('Small Image:','small_image',$row,45,$frame_name,
       $thumb_dir,(! $multiple_dirs),true,true,
       $multiple_dirs,false);
    $dialog->start_row('Summary:','top');
    $dialog->add_htmleditor_popup_field('summary',$row,'Summary',
                                        500,50,954,300,'media-tab-summary');
    $dialog->end_row();
    $dialog->start_row('Header/Page Content:','top');
    $dialog->add_htmleditor_popup_field('content',$row,'Header/Page Content',
                                        500,150,954,500,'media-tab-content');
    $dialog->end_row();

    if ($edit_type == UPDATERECORD) {
       $dialog->end_field_table();
       $dialog->end_tab_content();

       if ($library_info['flags'] & USE_SUBSECTIONS) {
          $dialog->start_tab_content('subsection_content',false);
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          $dialog->write("        <script type=\"text/javascript\">\n");
          $dialog->write("           var subsections = new SubList();\n");
          $dialog->write("           subsections.name = 'subsections';\n");
          $dialog->write("           subsections.script_url = 'media.php';\n");
          $dialog->write("           subsections.frame_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("edit_section';\n");
          else $dialog->write("add_section';\n");
          $dialog->write("           subsections.form_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("EditSection';\n");
          else $dialog->write("AddSection';\n");
          if ($dialog->skin) {
             $dialog->write("           subsections.grid_width = 0;\n");
             $dialog->write("           subsections.left_widths = [225];\n");
             $dialog->write("           subsections.right_widths = [280];\n");
          }
          else $dialog->write("           subsections.grid_width = 300;\n");
          $dialog->write("           subsections.grid_height = 340;\n");
          $dialog->write("           subsections.left_table = 'media_subsections';\n");
          $dialog->write("           subsections.left_titles = ['Section Name'];\n");
          $dialog->write("           subsections.left_label = 'SubSections';\n");
          $dialog->write("           subsections.right_table = 'media_sections';\n");
          $dialog->write("           subsections.right_titles = ['Section Name'];\n");
          $dialog->write("           subsections.right_label = 'Sections';\n");
          $dialog->write("           subsections.right_single_label = 'Section';\n");
          $dialog->write("           subsections.default_frame = 'edit_section';\n");
          $dialog->write("           subsections.categories = false;\n");
          $dialog->write('           subsections.search_where = ' .
                         "\"name like '%\$query\$%'\";\n");
          $dialog->write("        </script>\n");
          create_sublist_grids('subsections',$dialog,$id,'SubSections',
                               'All Sections',false,'SubsectionQuery',
                               'Sections',true);
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();

          $dialog->start_tab_content('parentsection_content',false);
          if ($dialog->skin)
             $dialog->write("        <div class=\"fieldSection\">\n");
          else $dialog->write("        <div style=\"padding: 4px;\">\n");
          $dialog->write("        <script type=\"text/javascript\">\n");
          $dialog->write("           var parentsections = new SubList();\n");
          $dialog->write("           parentsections.name = 'parentsections';\n");
          $dialog->write("           parentsections.script_url = 'media.php';\n");
          $dialog->write("           parentsections.frame_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("edit_section';\n");
          else $dialog->write("add_section';\n");
          $dialog->write("           parentsections.form_name = '");
          if ($edit_type == UPDATERECORD) $dialog->write("EditSection';\n");
          else $dialog->write("AddSection';\n");
          if ($dialog->skin) {
             $dialog->write("           parentsections.grid_width = 0;\n");
             $dialog->write("           parentsections.left_widths = [255];\n");
             $dialog->write("           parentsections.right_widths = [280];\n");
          }
          else $dialog->write("           parentsections.grid_width = 300;\n");
          $dialog->write("           parentsections.grid_height = 340;\n");
          $dialog->write("           parentsections.left_table = 'media_subsections';\n");
          $dialog->write("           parentsections.left_titles = ['Section Name'];\n");
          $dialog->write("           parentsections.left_label = 'SubSections';\n");
          $dialog->write("           parentsections.right_table = 'media_sections';\n");
          $dialog->write("           parentsections.right_titles = ['Section Name'];\n");
          $dialog->write("           parentsections.right_label = 'Sections';\n");
          $dialog->write("           parentsections.right_single_label = 'Section';\n");
          $dialog->write("           parentsections.default_frame = 'edit_section';\n");
          $dialog->write("           parentsections.reverse_list = true;\n");
          $dialog->write("           parentsections.categories = false;\n");
          $dialog->write('           parentsections.search_where = ' .
                         "\"name like '%\$query\$%'\";\n");
          $dialog->write("        </script>\n");
          create_sublist_grids('parentsections',$dialog,$id,'Parent Sections',
                               'All Sections',false,'ParentSectionQuery',
                               'Sections',false);
          $dialog->write("        </div>\n");
          $dialog->end_tab_content();
       }

       $dialog->start_tab_content('media_documents_content',false);
       if ($library_info['type'] == IMAGES_ONLY)
          $title_label = 'Title/Alt Text';
       else $title_label = 'Title';
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script>\n");
       $dialog->write("           var documents = new SubList();\n");
       $dialog->write("           documents.name = 'documents';\n");
       $dialog->write("           documents.script_url = 'media.php';\n");
       $dialog->write("           documents.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_section';\n");
       else $dialog->write("add_section';\n");
       $dialog->write("           documents.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditSection';\n");
       else $dialog->write("AddSection';\n");
       if ($dialog->skin)
          $dialog->write("           documents.grid_width = 0;\n");
       else $dialog->write("           documents.grid_width = 300;\n");
       $dialog->write("           documents.grid_height = 340;\n");
       $dialog->write("           documents.left_table = 'media_section_docs';\n");
       $dialog->write("           documents.left_fields = 'r.filename,r.title';\n");
       $dialog->write("           documents.left_order = 'l.sequence,r.filename';\n");
       $dialog->write("           documents.left_titles = ['" .
                      $library_info['document_label'] ."','".$title_label."'];\n");
       $dialog->write('           documents.left_widths = [');
       if ($media_no_filename) $dialog->write('0,340');
       else $dialog->write('100,260');
       $dialog->write("];\n");
       $dialog->write("           documents.left_label = '" .
                      $library_info['documents_label']."';\n");
       $dialog->write("           documents.right_table = 'media_documents';\n");
       $dialog->write("           documents.right_fields = 'filename,title';\n");
       $dialog->write("           documents.right_order = 'filename,id';\n");
       $dialog->write("           documents.right_titles = ['" .
                      $library_info['document_label']."','".$title_label."'];\n");
       $dialog->write('           documents.right_widths = [');
       if ($media_no_filename) $dialog->write('0,340');
       else $dialog->write('100,260');
       $dialog->write("];\n");
       $dialog->write("           documents.right_label = '" .
                      $library_info['documents_label']."';\n");
       $dialog->write("           documents.right_single_label = '" .
                      $library_info['document_label']."';\n");

       $dialog->write("           documents.search_where = 'filename like " .
                      "\"%\$query\$%\" or title like \"%\$query\$%\" or description " .
                      "like \"%\$query\$%\"';\n");
       $dialog->write("           documents.default_frame = 'edit_section';\n");
       $dialog->write("           documents.categories = false;\n");
       $dialog->write("        </script>\n");
       create_sublist_grids('documents',$dialog,$id,$library_info['documents_label'],
                            'All '.$library_info['documents_label'],false,'DocQuery',
                            $library_info['documents_label'],true);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();

       $dialog->end_tab_section();
    }
}

function add_section()
{
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('media.js');
    $dialog->set_body_id('add_media_section');
    $dialog->set_help('add_media_section');
    $dialog->start_body('Add Section');
    $dialog->set_button_width(120);
    $dialog->start_button_column();
    $dialog->add_button('Add Section','images/AddCategory.png',
                        'process_add_section();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','AddSection');
    $dialog->start_field_table();
    display_section_fields($dialog,ADDRECORD,array(),$library_info);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_section()
{
    $db = new DB;
    $section_record = section_record_definition();
    $db->parse_form_fields($section_record);
    $query = 'select max(sequence) as last_sequence ' .
             'from media_sections where library=?';
    $query = $db->prepare_query($query,$section_record['library']['value']);
    $row = $db->get_record($query);
    if ($row === null) {
       http_response(422,$db->error);   return;
    }
    if ($row) $sequence = intval($row['last_sequence']);
    else $sequence = 0;
    $section_record['sequence']['value'] = $sequence + 1;
    if (! $db->insert('media_sections',$section_record)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'Section Added');
    log_activity('Added Section '.$section_record['name']['value'] .
                 ' (#'.$db->insert_id().')');
}

function edit_section()
{
    global $utility_prefix;

    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from media_sections where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Section not found',0);
       return;
    }
    $library_info = load_library($db,$row['library'],$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('media.js');
    $dialog->add_style_sheet($utility_prefix.'utility.css');
    $dialog->add_script_file($utility_prefix.'sublist.js');
    $dialog_title = 'Edit Section '.$row['name'].' (#'.$id.')';
    $dialog->set_body_id('edit_media_section');
    $dialog->set_help('edit_media_section');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_section();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','EditSection');
    if (! $dialog->skin) $dialog->start_field_table();
    display_section_fields($dialog,UPDATERECORD,$row,$library_info);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_section()
{
    $db = new DB;
    $section_record = section_record_definition();
    $db->parse_form_fields($section_record);
    if (! $db->update('media_sections',$section_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Section Updated');
    log_activity('Updated Section '.$section_record['name']['value'] .
                 ' (#'.$section_record['id']['value'].')');
}

function delete_section()
{
    $id = get_form_field('id');
    $db = new DB;
    $row = $db->get_record('select * from media_sections where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Section not found',0);
       return;
    }

    $section_record = section_record_definition();
    $section_record['id']['value'] = $id;
    if (! $db->delete('media_sections',$section_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Section Deleted');
    log_activity('Deleted Section '.$row['name'].' (#'.$id.')');
}

function resequence_section()
{
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');

    $db = new DB;

    $query = 'select id,sequence from media_sections order by sequence';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,"Database Error: ".$db->error);
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
          $query = 'update media_sections set sequence='.$updated_sequence .
                   ' where id='.$row['id'];
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,"Database Error: ".$db->error);
             return;
          }
       }
    }

    http_response(201,'Sections Resequenced');
    log_activity('Resequenced Sections from #'.$old_sequence.' to #' .
                 $new_sequence);
}

function select_document()
{
    global $utility_prefix;

    $section_id = get_form_field('Section');
    $section_name = get_form_field('Name');
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_style_sheet($utility_prefix.'utility.css');
    $dialog->add_script_file('media.js');
    add_head_block($dialog,$library_info);
    $dialog->set_body_id('select_document');
    $dialog->set_help('select_document');
    $title = 'Add '.$library_info['document_label'].' to Section ' .
             $section_name;
    $dialog->set_body_id('select_media_document');
    $dialog->set_help('select_media_document');
    $dialog->start_body($title);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button("Select","images/Update.png",'select_document();');
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    add_search_box($dialog,'search_documents','reset_documents_search',
                   'documents_search');
    $dialog->end_button_column();
    $dialog->start_form('media.php','SelectDocument');
    $dialog->add_hidden_field('Library',$library);
    $dialog->add_hidden_field('Section',$section_id);
    $dialog->write("\n          <script>\n");
    $dialog->write("             load_documents_grid(false);\n");
    $dialog->write("          </script>\n");
    $dialog->end_form();
    $dialog->end_body();
}

function process_select_document()
{
    $doc_id = get_form_field('id');
    $section_id = get_form_field('Section');
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }
    $query = 'select max(sequence) as last_sequence from media_section_docs ' .
             'where parent=?';
    $query = $db->prepare_query($query,$section_id);
    $row = $db->get_record($query);
    if ($row && $row['last_sequence'])
       $last_sequence = intval($row['last_sequence']);
    else $last_sequence = 0;
    $sequence = $last_sequence + 1;
    $query = 'insert into media_section_docs (parent,related_id,sequence) ' .
             'values(?,?,?)';
    $query = $db->prepare_query($query,$section_id,$doc_id,$sequence);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Document Added to Section');
    log_activity('Added '.$library_info['document_label'].' #'.$doc_id .
                 ' to Section #'.$section_id);
}

function remove_document()
{
    $doc_id = get_form_field('id');
    $section_id = get_form_field('Section');
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }
    $query = 'delete from media_section_docs where parent=? and related_id=?';
    $query = $db->prepare_query($query,$section_id,$doc_id);
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Document Removed from Section');
    log_activity('Removed '.$library_info['document_label'].' #'.$doc_id .
                 ' from Section #'.$section_id);
}

function display_document_fields($db,$dialog,$edit_type,$row,$library_info)
{
    global $cms_base_url,$base_url;
    global $media_external_urls,$media_no_filename,$media_no_format;

    if (($library_info['type'] == IMAGES_ONLY) ||
        ($library_info['type'] == IMAGES_VIDEOS)) {
       $filename_label = 'Large Image:';
       $image_label = 'Thumbnail Image:';
       if ($library_info['type'] == IMAGES_VIDEOS) $title_label = 'Title:';
       else $title_label = 'Title/Alt Text:';
    }
    else if ($library_info['type'] == VIDEOS_ONLY) {
       $filename_label = 'Video:';
       $image_label = 'Thumbnail Image:';
       $title_label = 'Title:';
    }
    else {
       $filename_label = 'Filename:';
       $image_label = 'Image:';
       $title_label = 'Title:';
    }
    if ($edit_type == UPDATERECORD) {
       $document_id = get_row_value($row,'id');
       $dialog->add_hidden_field('id',$document_id);
    }
    $dialog->add_hidden_field('library',$library_info['id']);
    $doc_dir = $library_info['doc_dir'];
    if (! $doc_dir) $doc_dir = '/';
    else if (substr($doc_dir,-1) != '/') $doc_dir .= '/';
    $thumb_dir = $library_info['thumb_dir'];
    if (substr($thumb_dir,-1) != '/') $thumb_dir .= '/';
    $url_dir = $library_info['url_dir'];
    if (! $url_dir) $url_dir = '/';
    else if (substr($url_dir,-1) != '/') $url_dir .= '/';
    $video_conf = get_plugin_dir().'/video/video.conf';
    if (file_exists($video_conf)) {
       require_once $video_conf;
       if (substr($video_dir,-1) != '/') $video_dir .= '/';
    }
    else $video_dir = '/';
    if ($edit_type == UPDATERECORD) $frame_name = 'edit_document';
    else $frame_name = 'add_document';
    $format = get_row_value($row,'format');
    if ($library_info['type'] == VIDEOS_ONLY) $format = 'Video';

    $dialog->start_tab_section('tab_section');
    $dialog->start_tab_row('info_tab','info_content');
    $dialog->add_tab('info_tab','Info','info_tab','info_content',
                     'change_doc_tab',true,null,FIRST_TAB);
    if ($edit_type == ADDRECORD) $tab_sequence = LAST_TAB;
    else $tab_sequence = 0;
    $dialog->add_tab('content_tab','Content','content_tab',
                     'content_content','change_doc_tab',($format == 'Content'),
                     null,$tab_sequence);
    if ($edit_type == UPDATERECORD) {
       if (! ($library_info['flags'] & USERS_DOWNLOADS))
          $tab_sequence = LAST_TAB;
       else $tab_sequence = 0;
       $dialog->add_tab('sections_tab','Sections','sections_tab',
                        'sections_content','change_doc_tab',true,null,
                        $tab_sequence);
       if ($library_info['flags'] & USERS_DOWNLOADS)
          $dialog->add_tab('downloads_tab','Downloads','downloads_tab',
                           'downloads_content','change_doc_tab',true,
                           null,LAST_TAB);
    }
    $dialog->end_tab_row('tab_row_middle');

    $dialog->start_tab_content('info_content',true);
    $dialog->start_field_table('info_table');
    if ($media_no_format) {}
    else if ($library_info['type'] == VIDEOS_ONLY) {
       $dialog->add_text_row('File Format:','Video');
       $dialog->add_hidden_field('format','Video');
    }
    else {
       switch ($library_info['type']) {
          case ALL_TYPES:
             $file_formats = array('Content'=>'Content','HTML'=>'HTML',
                'PDF'=>'PDF','JPG'=>'Image/JPG','PNG'=>'Image/PNG',
                'GIF'=>'Image/GIF','Video'=>'Video');
             break;
          case IMAGES_ONLY:
             $file_formats = array('JPG'=>'Image/JPG','PNG'=>'Image/PNG',
                                   'GIF'=>'Image/GIF');
             break;
          case VIDEOS_ONLY: $file_formats = array('Video'=>'Video');   break;
          case IMAGES_VIDEOS:
             $file_formats = array('JPG'=>'Image/JPG','PNG'=>'Image/PNG',
                                   'GIF'=>'Image/GIF','Video'=>'Video');
             break;
       }
       $dialog->start_row('File Format:','middle');
       $dialog->start_choicelist('format','switch_document_type();');
       $dialog->add_list_item('','',(! $format));
       foreach ($file_formats as $file_format => $label)
          $dialog->add_list_item($file_format,$label,$format == $file_format);
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    if ($format == 'Video') {
       $show_filename = false;
       $row['video'] = $row['filename'];
    }
    else $show_filename = true;
    $multiple_dirs = ($library_info['flags'] & MULTIPLE_DIRS);
    $dialog->add_browse_row('Video:','video',$row,45,$frame_name,$video_dir,
       (! $multiple_dirs),true,false,$multiple_dirs,false,null,
       null,null,false,0,null,'video_row',$show_filename);
    if (! $media_no_filename)
       $dialog->add_browse_row($filename_label,'filename',$row,45,$frame_name,
          $doc_dir,(! $multiple_dirs),true,
          (($library_info['type'] == IMAGES_ONLY) ||
           ($library_info['type'] == IMAGES_VIDEOS)),
          $multiple_dirs,false,null,null,null,false,0,null,'filename_row',
          (! $show_filename));
    $dialog->add_browse_row($image_label,'image',$row,45,$frame_name,
       $thumb_dir,(! $multiple_dirs),true,true,
       $multiple_dirs,false);
    $dialog->add_textarea_row($title_label,'title',$row,3,86,WRAP_SOFT);
    $dialog->add_textarea_row('SubTitle:','subtitle',$row,3,86,WRAP_SOFT);
    $dialog->add_edit_row('Date:','doc_date',$row,85);
    if ($library_info['type'] == ALL_TYPES) {
       $dialog->start_row('Description:','top');
       $dialog->add_htmleditor_popup_field('description',
          get_row_value($row,'description'),'Description',526,150);
       $dialog->end_row();
    }
    if ($library_info['type'] != VIDEOS_ONLY) {
       $url_base = $base_url;
       if (substr($url_base,-1) == '/') $url_base = substr($url_base,0,-1);
       $dialog->start_hidden_row('URL:','url_row',(! $show_filename),'middle');
       if ($media_external_urls) $size = 65;
       else {
          $dialog->write($url_base);   $size = 25;
       }
       $dialog->add_input_field('url',$row,$size);
       $dialog->write("<input type='button' value='Browse' class='url_button' ");
       $dialog->write("onClick=\"return browse_row_browse_server('url','" .
          $dialog->escape_js_data($cms_base_url)."','" .
          $dialog->escape_js_data($url_dir)."','".$frame_name."'," .
          ($multiple_dirs?'false':'true').",false," .
          ($multiple_dirs?'true':'false').",false,null,null,false);\">\n");
       $dialog->write("<input type='button' value='Add' class='url_button' " .
          "onClick=\"add_cms_document('".$dialog->escape_js_data($cms_base_url) .
          "','".$dialog->escape_js_data($url_dir)."','".$frame_name .
          "');\">\n");
       $dialog->end_row();
    }

    $dialog->end_field_table();
    $dialog->end_tab_content();

    $dialog->start_tab_content('content_content',false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->add_htmleditor_popup_field('content',
                get_row_value($row,'content'),'Content',640,420,
                "'100%'","'100%'");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();

    if ($edit_type == UPDATERECORD) {
       $dialog->start_tab_content('sections_content',false);
       if ($dialog->skin)
          $dialog->write("        <div class=\"fieldSection\">\n");
       else $dialog->write("        <div style=\"padding: 4px;\">\n");
       $dialog->write("        <script type=\"text/javascript\">\n");
       $dialog->write("           var section_docs = new SubList();\n");
       $dialog->write("           section_docs.name = 'section_docs';\n");
       $dialog->write("           section_docs.script_url = 'media.php';\n");
       $dialog->write("           section_docs.frame_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("edit_document';\n");
       else $dialog->write("add_document';\n");
       $dialog->write("           section_docs.form_name = '");
       if ($edit_type == UPDATERECORD) $dialog->write("EditDocument';\n");
       else $dialog->write("AddDocument';\n");
       if ($dialog->skin) {
          $dialog->write("           section_docs.grid_width = 0;\n");
          $dialog->write("           section_docs.left_widths = [240];\n");
          $dialog->write("           section_docs.right_widths = [280];\n");
       }
       else $dialog->write("           section_docs.grid_width = 300;\n");
       $dialog->write("           section_docs.grid_height = 340;\n");
       $dialog->write("           section_docs.left_table = 'media_section_docs';\n");
       $dialog->write("           section_docs.left_titles = ['Section Name'];\n");
       $dialog->write("           section_docs.left_label = 'Document Sections';\n");
       $dialog->write("           section_docs.right_table = 'media_sections';\n");
       $dialog->write("           section_docs.right_titles = ['Section Name'];\n");
       $dialog->write("           section_docs.right_label = 'Sections';\n");
       $dialog->write("           section_docs.right_single_label = 'Section';\n");
       $dialog->write("           section_docs.default_frame = 'edit_document';\n");
       $dialog->write("           section_docs.reverse_list = true;\n");
       $dialog->write("           section_docs.categories = false;\n");
       $dialog->write('           section_docs.search_where = ' .
                      "\"name like '%\$query\$%'\";\n");
       $dialog->write("        </script>\n");
       create_sublist_grids('section_docs',$dialog,$document_id,'Sections',
                            'All Sections',false,'SectionDocQuery',
                            'Sections',true);
       $dialog->write("        </div>\n");
       $dialog->end_tab_content();
    }

    if (($edit_type == UPDATERECORD) &&
        ($library_info['flags'] & USERS_DOWNLOADS)) {
       $dialog->start_tab_content('downloads_content',false);
       $dialog->start_field_table('downloads_table');
       $query = 'select u.firstname,u.lastname,u.email,d.download_date from ' .
                'media_users u left join media_downloads d on d.user=u.id ' .
                'where d.document=? order by d.download_date';
       $query = $db->prepare_query($query,$document_id);
       $downloads = $db->get_records($query);
       if ($downloads && (count($downloads) > 0)) {
          $dialog->start_row('Downloads:','top');
          foreach ($downloads as $row)
             $dialog->write($row['firstname'].' '.$row['lastname'].' (' .
                            $row['email'].') - '.date('F j, Y g:i:s a',
                            $row['download_date'])."<br>\n");
          $dialog->end_row();
       }
       else $dialog->add_text_row('Downloads:','No Downloads Found');
       $dialog->end_field_table();
       $dialog->end_tab_content();
    }

    $dialog->end_tab_section();
}

function parse_document_fields($db,&$document_record,$library_info)
{
    $db->parse_form_fields($document_record);
    if ($library_info['type'] == IMAGES_VIDEOS) {
       $format = $document_record['format']['value'];
       if ($format == 'Video')
          $document_record['filename']['value'] = get_form_field('video');
    }
}

function add_document()
{
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_script_file('media.js');
    add_head_block($dialog,$library_info);
    $section_id = get_form_field('Section');
    if ($section_id) {
       $section_name = get_form_field('Name');
       $title = 'Add New '.$library_info['document_label'].' to Section ' .
                $section_name;
    }
    else $title = 'Add '.$library_info['document_label'];
    $dialog->set_body_id('add_media_document');
    $dialog->set_help('add_media_document');
    $dialog->start_body($title);
    $dialog->set_button_width(150);
    $dialog->start_button_column();
    $dialog->add_button('Add '.$library_info['document_label'],
                        'images/AddProduct.png','process_add_document();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','AddDocument');
    if (! $dialog->skin) $dialog->start_field_table();
    if ($section_id) $dialog->add_hidden_field('Section',$section_id);
    display_document_fields($db,$dialog,ADDRECORD,array(),$library_info);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_document()
{
    $db = new DB;
    $library = get_form_field('library');
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }
    $document_record = document_record_definition();
    parse_document_fields($db,$document_record,$library_info);
    if (! $db->insert('media_documents',$document_record)) {
       http_response(422,$db->error);   return;
    }
    log_activity('Added '.$library_info['document_label'].' ' .
                 $document_record['filename']['value'].' (#' .
                 $db->insert_id().')');

    $section_id = get_form_field('Section');
    if ($section_id) {
       $doc_id = $db->insert_id();
       $query = 'select max(sequence) as last_sequence from ' .
                'media_section_docs where parent=?';
       $query = $db->prepare_query($query,$section_id);
       $row = $db->get_record($query);
       if ($row && $row['last_sequence'])
          $last_sequence = intval($row['last_sequence']);
       else $last_sequence = 0;
       $sequence = $last_sequence + 1;
       $query = 'insert into media_section_docs (parent,related_id,sequence) ' .
                'values(?,?,?)';
       $query = $db->prepare_query($query,$section_id,$doc_id,$sequence);
       $db->log_query($query);
       if (! $db->query($query)) {
          http_response(422,$db->error);   return;
       }
       log_activity('Added '.$library_info['document_label'].' #'.$doc_id .
                    ' to Section #'.$section_id);
    }

    http_response(201,'Document Added');
}

function edit_document()
{
    global $utility_prefix;

    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from media_documents where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Document not found',0);
       return;
    }
    $library_info = load_library($db,$row['library'],$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('media.css');
    $dialog->add_script_file('media.js');
    $dialog->add_style_sheet($utility_prefix.'utility.css');
    $dialog->add_script_file($utility_prefix.'sublist.js');
    add_head_block($dialog,$library_info);
    $dialog_title = 'Edit '.$library_info['document_label'].' (#'.$id.')';
    $dialog->set_body_id('edit_media_document');
    $dialog->set_help('edit_media_document');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_document();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','EditDocument');
    if (! $dialog->skin) $dialog->start_field_table();
    display_document_fields($db,$dialog,UPDATERECORD,$row,$library_info);
    if (! $dialog->skin) $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_document()
{
    $db = new DB;
    $library = get_form_field('library');
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }
    $document_record = document_record_definition();
    parse_document_fields($db,$document_record,$library_info);
    if (! $db->update('media_documents',$document_record,$library_info)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Document Updated');
    log_activity('Updated '.$library_info['document_label'].' ' .
                 $document_record['filename']['value'] .
                 ' (#'.$document_record['id']['value'].')');
}

function delete_document()
{
    $id = get_form_field('id');
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }
    $row = $db->get_record('select * from media_documents where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Document not found',0);
       return;
    }

    $document_record = document_record_definition();
    $document_record['id']['value'] = $id;
    if (! $db->delete('media_documents',$document_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Document Deleted');
    log_activity('Deleted '.$library_info['document_label'].' ' .
                 $row['filename'].' (#'.$id.')');
}

function resequence_document()
{
    $section = get_form_field('Section');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');
    $library = get_form_field('Library');

    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       http_response(422,$error);   return;
    }

    $query = 'select id,sequence from media_section_docs where parent=? ' .
             'order by sequence';
    $query = $db->prepare_query($query,$section);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,"Database Error: ".$db->error);
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
          $query = 'update media_section_docs set sequence='.$updated_sequence .
                   ' where id='.$row['id'];
          $db->log_query($query);
          $update_result = $db->query($query);
          if (! $update_result) {
             http_response(422,"Database Error: ".$db->error);
             return;
          }
       }
    }

    http_response(201,'Section Documents Resequenced');
    log_activity('Resequenced '.$library_info['document_label'] .
                 ' in Section #'.$section.' from #'.$old_sequence.' to #' .
                 $new_sequence);
}

function display_user_fields($db,$dialog,$edit_type,$row,$library_info)
{
    if ($edit_type == UPDATERECORD) {
       $user_id = get_row_value($row,'id');
       $dialog->add_hidden_field('id',$user_id);
    }
    $dialog->add_hidden_field('library',$library_info['id']);
    $dialog->add_edit_row('Username:','username',
                          get_row_value($row,'username'),60);
    $dialog->add_edit_row('Password:','password',
                          get_row_value($row,'password'),60);
    $dialog->add_edit_row('First Name:','firstname',
                          get_row_value($row,'firstname'),60);
    $dialog->add_edit_row('Last Name:','lastname',
                          get_row_value($row,'lastname'),60);
    $dialog->add_edit_row('E-Mail Address:','email',
                          get_row_value($row,'email'),60);
    if (($edit_type == UPDATERECORD) &&
        ($library_info['flags'] & USERS_DOWNLOADS)) {
       $query = 'select do.filename,dl.download_date from ' .
                'media_documents do left join media_downloads dl on do.id=' .
                'dl.document where dl.user='.$user_id .
                ' order by dl.download_date';
       $result = $db->query($query);
       if ($result) {
          if ($db->num_rows($result) > 0) {
             $dialog->write("<tr valign=\"top\"><td class=\"fieldprompt\" " .
                            "nowrap>Downloads:</td><td>\n");
             while ($row = $db->fetch_assoc($result))
                $dialog->write($row['filename'].' - '.date('F j, Y g:i:s a',
                               $row['download_date'])."<br>\n");
             $dialog->write("</td></tr>\n");
          }
          $db->free_result($result);
       }
    }
}

function add_user()
{
    $library = get_form_field('Library');
    $db = new DB;
    $library_info = load_library($db,$library,$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('media.js');
    $dialog->set_body_id('add_media_user');
    $dialog->set_help('add_media_user');
    $dialog->start_body('Add User');
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Add User','images/AddUser.png',
                        'process_add_user();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','AddUser');
    $dialog->start_field_table();
    display_user_fields(null,$dialog,ADDRECORD,array(),$library_info);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_user()
{
    $db = new DB;
    $user_record = user_record_definition();
    $db->parse_form_fields($user_record);
    if (! $db->insert('media_users',$user_record)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'User Added');
    log_activity('Added User '.$user_record['username']['value'] .
                 ' (#'.$db->insert_id().')');
}

function edit_user()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from media_users where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('User not found',0);
       return;
    }
    $library_info = load_library($db,$row['library'],$error);
    if (! $library_info) {
       process_error($error,0);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('media.js');
    $dialog_title = 'Edit User (#'.$id.')';
    $dialog->set_body_id('edit_media_user');
    $dialog->set_help('edit_media_user');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(110);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_user();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('media.php','EditUser');
    $dialog->start_field_table();
    display_user_fields($db,$dialog,UPDATERECORD,$row,$library_info);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_user()
{
    $db = new DB;
    $user_record = user_record_definition();
    $db->parse_form_fields($user_record);
    if (! $db->update('media_users',$user_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'User Updated');
    log_activity('Updated User '.$user_record['username']['value'] .
                 ' (#'.$user_record['id']['value'].')');
}

function delete_user()
{
    $id = get_form_field('id');
    $db = new DB;
    $row = $db->get_record('select * from media_users where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('User not found',0);
       return;
    }

    $user_record = user_record_definition();
    $user_record['id']['value'] = $id;
    if (! $db->delete('media_users',$user_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'User Deleted');
    log_activity('Deleted User '.$row['username'].' (#'.$id.')');
}

function resequence_sections()
{
    $db = new DB;
    $query = 'select id,library,name,sequence from media_sections order by ' .
             'library,sequence,name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       else print "No Sections Found to Resequence<br>\n";
       return;
    }
    $current_library = -1;
    foreach ($rows as $row) {
       if ($row['library'] != $current_library) {
          $sequence = 1;   $current_library = $row['library'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update media_sections set sequence='.$sequence .
                   ' where id='.$row['id'];
          if (! $db->query($query)) {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced Media Sections');
    print "Resequenced Media Sections<br>\n";
}

function resequence_subsections()
{
    $db = new DB;
    $query = 'select su.id,su.parent,su.sequence from media_subsections su ' .
             'left join media_sections s on su.related_id=s.id order by ' .
             'su.parent,su.sequence,s.name';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       else print "No SubSections Found to Resequence<br>\n";
       return;
    }
    $current_parent = -1;
    foreach ($rows as $row) {
       if ($row['parent'] != $current_parent) {
          $sequence = 1;   $current_parent = $row['parent'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update media_subsections set sequence='.$sequence .
                   ' where id='.$row['id'];
          if (! $db->query($query)) {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced Media SubSections');
    print "Resequenced Media SubSections<br>\n";
}

function resequence_documents()
{
    $db = new DB;
    $query = 'select sd.id,sd.parent,sd.sequence from media_section_docs sd ' .
             'left join media_documents d on sd.related_id=d.id order by ' .
             'sd.parent,sd.sequence,d.filename';
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          print 'Query: '.$query."<br>\n";
          print 'Database Error: '.$db->error."<br>\n";
       }
       else print "No Sections Found to Resequence<br>\n";
       return;
    }
    $current_parent = -1;
    foreach ($rows as $row) {
       if ($row['parent'] != $current_parent) {
          $sequence = 1;   $current_parent = $row['parent'];
       }
       else $sequence++;
       if ($row['sequence'] != $sequence) {
          $query = 'update media_section_docs set sequence='.$sequence .
                   ' where id='.$row['id'];
          if (! $db->query($query)) {
             print 'Query: '.$query."<br>\n";
             print 'Database Error: '.$db->error."<br>\n";
             return;
          }
       }
    }
    log_activity('Resequenced Media Documents');
    print "Resequenced Media Documents<br>\n";
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'libraries') display_libraries();
else if ($cmd == 'addlibrary') add_library();
else if ($cmd == 'processaddlibrary') process_add_library();
else if ($cmd == 'editlibrary') edit_library();
else if ($cmd == 'updatelibrary') update_library();
else if ($cmd == 'deletelibrary') delete_library();
else if ($cmd == 'addsection') add_section();
else if ($cmd == 'processaddsection') process_add_section();
else if ($cmd == 'editsection') edit_section();
else if ($cmd == 'updatesection') update_section();
else if ($cmd == 'deletesection') delete_section();
else if ($cmd == 'resequencesection') resequence_section();
else if ($cmd == 'selectdocument') select_document();
else if ($cmd == 'processselectdocument') process_select_document();
else if ($cmd == 'removedocument') remove_document();
else if ($cmd == 'adddocument') add_document();
else if ($cmd == 'processadddocument') process_add_document();
else if ($cmd == 'editdocument') edit_document();
else if ($cmd == 'updatedocument') update_document();
else if ($cmd == 'deletedocument') delete_document();
else if ($cmd == 'resequencedocument') resequence_document();
else if ($cmd == 'adduser') add_user();
else if ($cmd == 'processadduser') process_add_user();
else if ($cmd == 'edituser') edit_user();
else if ($cmd == 'updateuser') update_user();
else if ($cmd == 'deleteuser') delete_user();
else if ($cmd == 'resequencesections') resequence_sections();
else if ($cmd == 'resequencesubsections') resequence_subsections();
else if ($cmd == 'resequencedocuments') resequence_documents();
else if (process_sublist_command($cmd)) {}
else display_media_screen();

?>
