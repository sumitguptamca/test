<?php
/*
                       Inroads Shopping Cart - Searches Tab

                        Written 2014-2015 by Randall Severy
                         Copyright 2014-2015 Inroads, LLC
*/

require '../engine/screen.php';
require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';
require 'searches-public.php';

function display_searches_screen()
{
    global $docroot,$search_engine;

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('utility.css');
    $screen->add_script_file('searches.js');
    $head_block = "<script type=\"text/javascript\">\n";
    $head_block .= "      search_engine = '".$search_engine."';\n";
    $head_block .= "    </script>";
    $screen->add_head_line($head_block);
    $screen->set_body_id('searches');
    $screen->set_help('searches');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Searches');
       $screen->start_title_filters();
       add_search_box($screen,'search_searches','reset_search');
       $screen->end_title_filters();
       $screen->end_title_bar();
    }
    $screen->set_button_width(135);
    $screen->start_button_column();
    $screen->add_button('Delete Search','images/DeleteUser.png',
                        'delete_search();');
    $screen->add_button_separator("searches_buttons_row",20,true);
    $screen->add_button("Synonyms","images/AdminUsers.png",
                        "synonyms();");
    $ad_filename = $docroot.'/catalogengine/search-engines/'.$search_engine .
                   '/ad.html';
    if (file_exists($ad_filename)) {
       $screen->add_button_separator("searches_buttons_row2",20,true);
       $screen->add_button("Edit Search Ad","images/AdminUsers.png",
                           "edit_ad();");
    }
    if (! $screen->skin)
       add_search_box($screen,'search_searches','reset_search');
    $screen->end_button_column();
    $screen->write("          <script>load_grid();</script>\n");
    $screen->end_body();
}

function delete_search()
{
    $id = get_form_field('id');
    $db = new DB;
    $search_record = search_record_definition();
    $search_record['id']['value'] = $id;
    if (! $db->delete('searches',$search_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(200,'Search Deleted');
    log_activity('Deleted Search #'.$id);
}

function synonym_record_definition()
{
    $synonym_record = array();
    $synonym_record['id'] = array('type' => INT_TYPE);
    $synonym_record['id']['key'] = true;
    $synonym_record['synonym'] = array('type' => CHAR_TYPE);
    $synonym_record['keyword'] = array('type' => CHAR_TYPE);
    return $synonym_record;
}

function display_synonym_fields($db,$dialog,$edit_type,$row)
{
    if ($edit_type == UPDATERECORD)
       $dialog->add_hidden_field("id",$row['id']);
    $dialog->add_edit_row("Synonym:","synonym",
                          get_row_value($row,'synonym'),40);
    $dialog->add_edit_row("Keyword:","keyword",
                          get_row_value($row,'keyword'),40);
}

function display_synonyms()
{
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file("searches.js");
    $dialog->set_body_id('synonyms');
    $dialog->set_help('synonyms');
    $dialog->start_body("Synonyms");
    $dialog->set_button_width(140);
    $dialog->start_button_column();
    $dialog->add_button("Add Synonym","images/AddUser.png",
                        "add_synonym();");
    $dialog->add_button("Edit Synonym","images/EditUser.png",
                        "edit_synonym();");
    $dialog->add_button("Delete Synonym","images/DeleteUser.png",
                        "delete_synonym();");
    $dialog->add_button("Close","images/Update.png","top.close_current_dialog();");
    $dialog->end_button_column();
    $dialog->write("\n          <script>load_synonyms_grid();</script>\n");
    $dialog->end_body();
}

function add_synonym()
{
    $db = new DB;
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file("searches.js");
    $dialog->set_body_id('add_synonym');
    $dialog->set_help('add_synonym');
    $dialog->start_body("Add Synonym");
    $dialog->set_button_width(130);
    $dialog->start_button_column();
    $dialog->add_button("Add Synonym","images/AddUser.png",
                        "process_add_synonym();");
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    $dialog->end_button_column();
    $dialog->start_form("searches.php","AddSynonym");
    $dialog->start_field_table();
    display_synonym_fields($db,$dialog,ADDRECORD,array());
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_synonym()
{
    $db = new DB;
    $synonym_record = synonym_record_definition();
    $db->parse_form_fields($synonym_record);
    if (! $db->insert("search_synonyms",$synonym_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,"Synonym Added");
    log_activity('Added Synonym "'.$synonym_record['synonym']['value'] .
                 '" (#'.$db->insert_id().')');
}

function edit_synonym()
{
    $db = new DB;
    $id = get_form_field("id");
    $row = $db->get_record("select * from search_synonyms where id=".$id);
    if (! $row) {
       process_error("Database Error: ".$db->error,-1);   return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_script_file("searches.js");
    $dialog->set_body_id('edit_synonym');
    $dialog->set_help('edit_synonym');
    $dialog->start_body("Edit Synonym ".$row['synonym']." (#".$id.")");
    $dialog->start_button_column();
    $dialog->add_button("Update","images/Update.png",
                        "update_synonym();");
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    $dialog->end_button_column();
    $dialog->start_form("searches.php","EditSynonym");
    $dialog->start_field_table();
    display_synonym_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_synonym()
{
    $db = new DB;
    $synonym_record = synonym_record_definition();
    $db->parse_form_fields($synonym_record);
    if (! $db->update("search_synonyms",$synonym_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,"Synonym Updated");
    log_activity('Updated Synonym "'.$synonym_record['synonym']['value'] .
                 '" (#'.$synonym_record['id']['value'].')');
}

function delete_synonym()
{
    $db = new DB;
    $synonym_record = synonym_record_definition();
    $id = get_form_field('id');
    $synonym_record['id']['value'] = $id;
    if (! $db->delete("search_synonyms",$synonym_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,"Synonym Deleted");
    log_activity("Deleted Synonym #".$id);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'deletesearch') delete_search();
else if ($cmd == "synonyms") display_synonyms();
else if ($cmd == "addsynonym") add_synonym();
else if ($cmd == "processaddsynonym") process_add_synonym();
else if ($cmd == "editsynonym") edit_synonym();
else if ($cmd == "updatesynonym") update_synonym();
else if ($cmd == "deletesynonym") delete_synonym();
else display_searches_screen();

?>
