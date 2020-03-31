<?php
/*
                Inroads Control Panel/Shopping Cart - Ticket Dialog

                        Written 2012-2019 by Randall Severy
                         Copyright 2012-2019 Inroads, LLC
*/

define('PUNCH_LIST',0);
define('SUPPORT_REQUEST',1);

if (isset($argc) && ($argc == 2) && ($argv[1] == 'sync'))
   chdir(dirname(__FILE__));

require '../engine/dialog.php';
require '../engine/db.php';
require 'utility.php';
if (file_exists("../cartengine/adminperms.php"))
   require_once '../cartengine/cartconfig-common.php';
else require_once 'countrystate-common.php';
if (file_exists("../admin/custom-config.php"))
   require_once '../admin/custom-config.php';

if (isset($enable_support_requests) && $enable_support_requests) {
   $ticket_type = SUPPORT_REQUEST;   $ticket_label = "Support Request";
}
else {
   $ticket_type = PUNCH_LIST;   $ticket_label = "Punch List Item";
}

$status_values = array('New','Assigned','Completed','Verified','N/A','Future',
                       'Waiting');
$departments = array('Sales','Creative','Engineering');
$priority_values = array('','Critical','High','Medium','Low');

function ticket_record_definition()
{
    $ticket_record = array();
    $ticket_record['id'] = array('type' => INT_TYPE);
    $ticket_record['id']['key'] = true;
    $ticket_record['status'] = array('type' => INT_TYPE);
    $ticket_record['dirty'] = array('type' => INT_TYPE);
    $ticket_record['department'] = array('type' => INT_TYPE);
    $ticket_record['priority'] = array('type' => INT_TYPE);
    $ticket_record['cc'] = array('type' => CHAR_TYPE);
    $ticket_record['title'] = array('type' => CHAR_TYPE);
    $ticket_record['approved'] = array('type' => INT_TYPE);
    $ticket_record['due_date'] = array('type' => INT_TYPE);
    $ticket_record['url'] = array('type' => CHAR_TYPE);
    $ticket_record['description'] = array('type' => CHAR_TYPE);
    $ticket_record['history'] = array('type' => CHAR_TYPE);
    $ticket_record['comments'] = array('type' => CHAR_TYPE);
    $ticket_record['submitted'] = array('type' => INT_TYPE);
    $ticket_record['submitter'] = array('type' => CHAR_TYPE);
    $ticket_record['assigned'] = array('type' => INT_TYPE);
    $ticket_record['assignedto'] = array('type' => CHAR_TYPE);
    $ticket_record['verified'] = array('type' => INT_TYPE);
    $ticket_record['verifier'] = array('type' => CHAR_TYPE);
    return $ticket_record;
}

function attachment_record_definition()
{
    $attachment_record = array();
    $attachment_record['parent'] = array('type' => INT_TYPE);
    $attachment_record['parent']['key'] = true;
    $attachment_record['filename'] = array('type' => CHAR_TYPE);
    $attachment_record['filename']['key'] = true;
    $attachment_record['upload_date'] = array('type' => INT_TYPE);
    return $attachment_record;
}

function load_users($db,$last_first = true)
{
    $result = $db->query("select * from users order by lastname,firstname");
    if (! $result) {
       process_error("Database Error: ".$db->error,-1);   return null;
    }
    $user_list = array();
    while ($user_row = $db->fetch_assoc($result)) {
       $db->decrypt_record('users',$user_row);
       if ($last_first) {
          $full_name = $user_row['lastname'];
          if ($user_row['firstname'] != "")
             $full_name .= ", ".$user_row['firstname'];
       }
       else $full_name = $user_row['firstname']." ".$user_row['lastname'];
       $user_list[$user_row['username']] = $full_name;
    }
    $db->free_result($result);
    return $user_list;
}

if (get_current_directory() == "cartengine") $shopping_cart = true;
else $shopping_cart = false;

function add_script_prefix(&$dialog)
{
    global $shopping_cart;

    if (! $shopping_cart) return;
    $head_block = "<script type=\"text/javascript\">" .
                  "script_prefix='../cartengine/';</script>";
    $dialog->add_head_line($head_block);
}

function display_ticket_dialog()
{
    global $status_values,$ticket_type,$departments,$siteid,$priority_values;

    $db = new DB;
    $user_list = load_users($db,false);

    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet("tickets.css");
    $dialog->add_style_sheet("utility.css");
    $dialog->add_script_file("tickets.js");
    add_script_prefix($dialog);
    $script = "<script type=\"text/javascript\">\n";
    $script .= "       status_values = [";
    foreach ($status_values as $value => $label) {
       if ($value > 0) $script .= ",";
       $script .= "'".$label."'";
    }
    $script .= "];\n";
    $script .= "       departments = [";
    foreach ($departments as $value => $label) {
       if ($value > 0) $script .= ",";
       $script .= "'".$label."'";
    }
    $script .= "];\n";
    $script .= "       priority_values = [";
    foreach ($priority_values as $value => $label) {
       if ($value > 0) $script .= ",";
       $script .= "'".$label."'";
    }
    $script .= "];\n";
    $script .= "       users = [";   $first_user = true;
    foreach ($user_list as $username => $full_name) {
       if ($first_user) $first_user = false;
       else $script .= ",";
       $username = str_replace("'","\\'",$username);
       $full_name = str_replace("'","\\'",$full_name);
       $script .= "['".$username."','".$full_name."']";
    }
    $script .= "];\n";
    $script .= "       ticket_type = ".$ticket_type.";\n";
    $script .= "       var site_id = '".$siteid."';\n";
    $script .= "    </script>";
    $dialog->add_head_line($script);
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog_title = "Support Requests";   $button_label = "Request";
    }
    else {
       $dialog_title = "Punch List";   $button_label = "Item";
    }
    if ($onload_function = get_form_field("on_load"))
       $dialog->set_onload_function($onload_function);
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->set_body_id('support_requests');
       $dialog->set_help('support_requests');
    }
    else {
       $dialog->set_body_id('punch_lists');
       $dialog->set_help('punch_lists');
    }
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(148);
    $dialog->start_button_column();
    $dialog->add_button("Add ".$button_label,"images/AddTicket.png",
                        "add_ticket();",null,true,false,ADD_BUTTON);
    $dialog->add_button("Edit ".$button_label,"images/EditTicket.png",
                        "edit_ticket();",null,true,false,EDIT_BUTTON);
    $dialog->add_button("Delete ".$button_label,"images/DeleteTicket.png",
                        "delete_ticket();",null,true,false,DELETE_BUTTON);
    $dialog->add_button("View ".$button_label,"images/ViewTicket.png",
                        "view_ticket(null);",null,true,false,VIEW_BUTTON);
    $dialog->add_button("Close","images/Update.png",
                        "top.close_current_dialog();");
    add_search_box($dialog,"search_tickets","reset_search");
    $dialog->end_button_column();
    $dialog->write("<script>load_grid();</script>");
    $dialog->end_body();
}

function add_noneeditable_div($field_name,$field_value,$width,$height,$alert)
{
    if (get_browser_type() == FIREFOX) $height -= 11;
    print "<input type=\"hidden\" name=\"".$field_name."\" id=\"" .
          $field_name."\" value=\"";
    write_form_value($field_value);
    print "\">\n";
    UI::process_widgets($field_value);
    print "<div id=\"".$field_name."_div\" width=\"".$width."px\" height=\"";
    print $height."px\" class=\"htmleditor_div\" style=\"height:";
    print $height."px; width:".$width."px; cursor: default;\" onClick=\"alert('" .
          $alert."');\">\n".$field_value."</div>\n";
}

function display_ticket_fields($db,$dialog,$edit_type,$row)
{
    global $status_values,$departments,$priority_values,$ticket_type;

    $user_list = load_users($db);
    $dialog->add_hidden_field("id",$row);
    $dialog->add_hidden_field("dirty","1");
    $dialog->start_row('Status:','middle');
    $dialog->start_table();
    $dialog->write("<tr><td width=\"190\" id=\"status_cell\">\n");
    $status = get_row_value($row,'status');
    if ($status == '')  $status = 0;
    $dialog->add_hidden_field("oldstatus",$status);
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->add_hidden_field("status",$status);
       $dialog->write($status_values[$status]);
    }
    else {
       $dialog->start_choicelist("status");
       foreach ($status_values as $value => $label)
          $dialog->add_list_item($value,$label,$status == $value);
       $dialog->end_choicelist();
    }
    if ($status == 2)
       $dialog->write("&nbsp;&nbsp;&nbsp;<div class=\"reopen_button\" " .
                        "onClick=\"reopen_ticket();\">Reopen</div>\n");

    $dialog->write("</td>\n<td class=\"fieldprompt\" width=\"75\">" .
                   "Submitted By:</td><td>");
    $dialog->add_hidden_field("submitted",$row);
    $submitter = get_row_value($row,'submitter');
    if (($ticket_type == SUPPORT_REQUEST) && ($edit_type == UPDATERECORD)) {
       $dialog->add_hidden_field("submitter",$submitter);
       if (isset($user_list[$submitter]))
          $dialog->write($user_list[$submitter]);
       else $dialog->write($submitter);
    }
    else {
       $dialog->start_choicelist("submitter","select_user(this,'submitted');");
       $dialog->add_list_item("","",FALSE);
       $submitter_found = false;
       foreach ($user_list as $username => $full_name) {
          $dialog->add_list_item($username,$full_name,$submitter == $username);
          if ($submitter == $username) $submitter_found = true;
       }
       if ($submitter && (! $submitter_found))
          $dialog->add_list_item($submitter,$submitter,true);
       $dialog->end_choicelist();
    }
    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
    $dialog->start_row('Department:','middle');
    $dialog->start_table();
    $dialog->write("<tr><td width=\"190\">\n");
    $department = get_row_value($row,'department');
    if ($department === '') $department = -1;
    $dialog->add_hidden_field("olddepartment",$department);
    if (($ticket_type == SUPPORT_REQUEST) && ($edit_type == UPDATERECORD) &&
        ($department != -1)) {
       $dialog->add_hidden_field("department",$department);
       if (isset($departments[$department]))
          $dialog->write($departments[$department]);
    }
    else {
       $dialog->start_choicelist("department");
       $dialog->add_list_item('','',($department == -1));
       foreach ($departments as $value => $label)
          $dialog->add_list_item($value,$label,$department == $value);
       $dialog->end_choicelist();
    }
    $assignedto = get_row_value($row,'assignedto');
    $dialog->add_hidden_field("oldassignedto",$assignedto);
    $dialog->add_hidden_field("assignedto",$assignedto);
    if ($edit_type == UPDATERECORD) {
       $dialog->write("</td>\n<td class=\"fieldprompt\" width=\"75\">" .
                      "Assigned To:</td><td>");
       $dialog->add_hidden_field("assigned",get_row_value($row,'assigned'));
       if (isset($user_list[$assignedto]))
          $dialog->write($user_list[$assignedto]);
       else $dialog->write($assignedto);
    }
    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->start_row('Priority:','middle');
       $dialog->start_table();
       $dialog->write("<tr><td width=\"190\">\n");
       $priority = get_row_value($row,'priority');
       if ($priority === '') $priority = 0;
       $dialog->add_hidden_field("oldpriority",$priority);
       $dialog->start_choicelist("priority");
       foreach ($priority_values as $value => $label)
          $dialog->add_list_item($value,$label,$priority == $value);
       $dialog->end_choicelist();
       $dialog->write("</td>\n<td class=\"fieldprompt\" width=\"75\">" .
                      "Approved:</td>");
    }
    else $dialog->write("<tr valign=\"middle\"><td class=\"fieldprompt\" " .
                        "nowrap>Approved:</td>\n");
    $dialog->write("<td id=\"approved_cell\">");
    $approved = get_row_value($row,'approved');
    if ($approved == 1) $dialog->write("Yes");
    else $dialog->write("No&nbsp;&nbsp;&nbsp;<div class=\"approve_button\" " .
                        "onClick=\"approve_ticket();\">Approve</div>\n");
    $dialog->end_row();
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->end_table();
       $dialog->end_row();
       $dialog->start_row('Due Date:');
       $dialog->add_date_field("due_date",$row);
       $dialog->end_row();
       $dialog->add_edit_row("CC:","cc",$row,73);
    }
    $dialog->add_edit_row("Title:","title",$row,73);
    $dialog->add_edit_row("URL:","url",$row,73);
    $dialog->start_row('Description:','top');
    $dialog->add_htmleditor_popup_field("description",$row,"Description",
                                        450,160);
    $dialog->end_row();
    $dialog->start_row('Attachments:','top');
    $dialog->write("<div id=\"attachments\"></div>\n");
    $dialog->end_row();
    $dialog->start_row('History:','top');
    if ($ticket_type == SUPPORT_REQUEST)
       add_noneeditable_div("history",get_row_value($row,'history'),
                            450,160,'This field is not editable');
    else $dialog->add_htmleditor_popup_field("history",
                                             get_row_value($row,'history'),
                                             "History",450,160);
    $dialog->end_row();
    $comments = get_row_value($row,'comments');
    $dialog->start_hidden_row('Comments:','new_comments_row',(! $comments),
                              'top');
    $dialog->add_htmleditor_popup_field("comments",$comments,"Comments",
                                        450,160);
    $dialog->end_row();
    $dialog->write("<tr><td></td><td><input type=\"hidden\" " .
                   "name=\"NewComments\" id=\"NewComments\" value=\"\">" .
                   "<div class=\"add_comment_button\" onClick=\"" .
                   "add_comment();\">Add Comment</div></td></tr>\n");
/*
    if ($ticket_type == PUNCH_LIST) {
       $dialog->start_row('Verified:');
       $dialog->write("By:\n");
       $dialog->add_hidden_field("verified",get_row_value($row,'verified'));
       $verifier = get_row_value($row,'verifier');
       $dialog->start_choicelist("verifier","select_user(this,'verified');");
       $dialog->add_list_item("","",FALSE);
       $verifier_found = false;
       foreach ($user_list as $username => $full_name) {
          $dialog->add_list_item($username,$full_name,$verifier == $username);
          if ($verifier == $username) $verifier_found = true;
       }
       if ($verifier && (! $verifier_found))
          $dialog->add_list_item($verifier,$verifier,true);
       $dialog->end_choicelist();
       $dialog->write("&nbsp;on:&nbsp;\n");
       $verified = get_row_value($row,'verified');
       $dialog->add_date_field("verified",$verified,true);
       $dialog->end_row();
    }
*/
}

function add_status_note($db,&$history,$ticket_id,$status_note,$end_flag)
{
    $html = '<p class="status_note">'.$status_note.'</p>';
    if ($ticket_id === null) {
       if ($end_flag) $history .= $html;
       else $history = $html.$history;
    }
    else {
       $query = "update tickets set history=concat(";
       if ($end_flag)
          $query .= "history,'".$db->escape($html)."'";
       else $query .= "'".$db->escape($html)."',history";
       $query .= ") where id=".$ticket_id;
       $db->log_query($query);
       if (! $db->query($query)) return false;
    }
    return true;
}

function get_user_full_name($username,$user_list)
{
   if (isset($user_list[$username])) return $user_list[$username];
   return $username;
}

function process_status_changes($db,&$ticket_record,$edit_type)
{
    global $login_cookie,$status_values,$departments,$priority_values;
    global $ticket_type;

    $user_list = load_users($db,false);
    $admin_user = get_cookie($login_cookie);
    if ($edit_type == ADDRECORD) {
       $submitter = $ticket_record['submitter']['value'];
       $submitted = $ticket_record['submitted']['value'];
       $status_note = "Submitted by ".get_user_full_name($submitter,$user_list) .
                      " ".date("M j, Y h:i a",$submitted);
       add_status_note($db,$ticket_record['history']['value'],null,
                       $status_note,false);
    }
    $oldassignedto = get_form_field("oldassignedto");
    if ($oldassignedto != $ticket_record['assignedto']['value']) {
       $assignedto = $ticket_record['assignedto']['value'];
       $assigned = $ticket_record['assigned']['value'];
       $status_note = "Assigned to ".get_user_full_name($assignedto,$user_list) .
                      " by ".get_user_full_name($admin_user,$user_list) .
                      " ".date("M j, Y h:i a",$assigned);
       add_status_note($db,$ticket_record['history']['value'],null,
                       $status_note,true);
    }
    $olddepartment = get_form_field("olddepartment");
    if ($olddepartment != $ticket_record['department']['value']) {
       $department = $ticket_record['department']['value'];
       $status_note = "Department changed to ".$departments[$department] .
                      " by ".get_user_full_name($admin_user,$user_list) .
                      " ".date("M j, Y h:i a");
       add_status_note($db,$ticket_record['history']['value'],null,
                       $status_note,true);
    }
    $oldstatus = get_form_field("oldstatus");
    if (isset($ticket_record['status']['value']) &&
        ($oldstatus != $ticket_record['status']['value'])) {
       $status = $ticket_record['status']['value'];
       $status_note = "Status changed to ".$status_values[$status] .
                      " by ".get_user_full_name($admin_user,$user_list) .
                      " ".date("M j, Y h:i a");
       add_status_note($db,$ticket_record['history']['value'],null,
                       $status_note,true);
    }
    if ($ticket_type == SUPPORT_REQUEST) {
       $oldpriority = get_form_field("oldpriority");
       if ($oldpriority != $ticket_record['priority']['value']) {
          $priority = $ticket_record['priority']['value'];
          $status_note = "Priority changed to ".$priority_values[$priority] .
                         " by ".get_user_full_name($admin_user,$user_list) .
                         " ".date("M j, Y h:i a");
          add_status_note($db,$ticket_record['history']['value'],null,
                          $status_note,true);
       }
    }
}

function add_dialog_script($db,&$dialog)
{
    global $login_cookie,$ticket_url,$ticket_type,$htmleditor_url;

    $admin_user = get_cookie($login_cookie);
    $query = 'select * from users where username=';
    if ($db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $db->prepare_query($query,$admin_user);
    $row = $db->get_record($query);
    if (! $row) $full_name = $admin_user;
    else {
       $db->decrypt_record('users',$row);
       $full_name = $row['firstname']." ".$row['lastname'];
    }
    $script = "<script type=\"text/javascript\">\n";
    $script .= "       ticket_url = '".$ticket_url."';\n";
    $script .= "       ticket_type = ".$ticket_type.";\n";
    $script .= "       full_name = '".$full_name."';\n";
    if (isset($htmleditor_url))
       $script .= "       htmleditor_url = '".$htmleditor_url."';\n";
    $script .= "    </script>";
    $dialog->add_head_line($script);
}

function create_ticket()
{
    global $ticket_label,$login_cookie,$ticket_type;

    $db = new DB;
    $admin_user = get_cookie($login_cookie);
    $ticket_record = ticket_record_definition();
    $ticket_record['title']['value'] = "New ".$ticket_label;
    $ticket_record['dirty']['value'] = 1;
    $ticket_record['submitter']['value'] = $admin_user;
    $ticket_record['submitted']['value'] = time();
    $ticket_record['approved']['value'] = 1;
    if (! $db->insert("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print "ticket_id = ".$id.";";
    log_activity("Created New ".$ticket_label." #".$id);
}

function add_ticket()
{
    global $base_url,$ticket_label,$ticket_type;

    $db = new DB;
    $id = get_form_field("id");
    $default_base_href = get_current_url();
    $row = $db->get_record("select * from tickets where id=".$id);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error($ticket_label." not found",0);
       return;
    }
    $row['title'] = '';

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet("fileuploader.css");
    $dialog->add_style_sheet("tickets.css");
    $dialog->add_script_file("fileuploader.js");
    $dialog->add_script_file("tickets.js");
    $dialog->set_onload_function("add_ticket_onload(); create_uploader(" .
                                 $id.",{});");
    add_dialog_script($db,$dialog);
    add_base_href($dialog,$default_base_href,false);
    $dialog_title = "Add ".$ticket_label." (#".$id.")";
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->set_body_id('add_support_request');
       $dialog->set_help('add_support_request');
    }
    else {
       $dialog->set_body_id('add_punch_list');
       $dialog->set_help('add_punch_list');
    }
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(120);
    $dialog->start_button_column(false,true);
    if ($ticket_type == SUPPORT_REQUEST) $button_label = "Request";
    else $button_label = "Item";
    $dialog->add_button("Add ".$button_label,"images/AddTicket.png",
                        "process_add_ticket();");
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    $dialog->end_button_column();
    add_base_href($dialog,$base_url,true);
    $dialog->start_form("tickets.php","AddTicket");
    $dialog->start_field_table();
    display_ticket_fields($db,$dialog,ADDRECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    add_base_href($dialog,$default_base_href,true);
    $dialog->end_body();
}

function process_add_ticket()
{
    global $ticket_label,$ticket_type;

    $db = new DB;
    $ticket_record = ticket_record_definition();
    $db->parse_form_fields($ticket_record);
    $old_assigned = get_form_field("oldassignedto");
    if ($old_assigned != $ticket_record['assignedto']['value'])
       $ticket_record['assigned']['value'] = time();
    process_status_changes($db,$ticket_record,ADDRECORD);
    if (! $db->update("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$ticket_label." Added");
    log_activity("Added ".$ticket_label." #".$ticket_record['id']['value'] .
                 " (".$ticket_record['title']['value'].")");
    if ($ticket_type == SUPPORT_REQUEST) spawn_program("tickets.php sync");
}

function load_attachments($db,$parent)
{
    global $ticket_dir;

    $result = $db->query("select filename from ticket_attachments " .
                         "where parent=".$parent);
    if (! $result) return;
    $attachments = '{';   $index = 0;
    while ($attach_row = $db->fetch_assoc($result)) {
       $filename = $attach_row['filename'];
       $full_filename = $ticket_dir."/".$parent."/".$filename;
       $file_size = filesize($full_filename);
       $size_index = 0;
       while ($file_size > 99) {
          $file_size = $file_size / 1024;   $size_index++;
       }
       if ($file_size < 0.1) $file_size = 0.1;
       $sizes = array('B','kB','MB','GB','TB','PB','EB');
       $file_size = sprintf("%.1f",$file_size).$sizes[$size_index];
       $filename = str_replace("'","\\'",$filename);
       if ($index > 0) $attachments .= ",";
       $attachments .= $index.":{filename:'".$filename."', size:'".$file_size."'}";
       $index++;
    }
    $db->free_result($result);
    $attachments .= '}';
    return $attachments;
}

function edit_ticket()
{
    global $base_url,$ticket_label,$ticket_type;

    $default_base_href = get_current_url();
    $db = new DB;

    $id = get_form_field("id");
    $row = $db->get_record("select * from tickets where id=".$id);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error($ticket_label." not found",0);
       return;
    }
    $attachments = load_attachments($db,$id);
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet("fileuploader.css");
    $dialog->add_style_sheet("tickets.css");
    $dialog->add_script_file("fileuploader.js");
    $dialog->add_script_file("tickets.js");
    $dialog->set_onload_function("create_uploader(".$id.",".$attachments.");");
    add_dialog_script($db,$dialog);
    add_base_href($dialog,$default_base_href,false);
    $dialog_title = "Edit ".$ticket_label." (#".$id.")";
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->set_body_id('edit_support_request');
       $dialog->set_help('edit_support_request');
    }
    else {
       $dialog->set_body_id('edit_punch_list');
       $dialog->set_help('edit_punch_list');
    }
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(90);
    $dialog->start_button_column(false,true);
    $dialog->add_button("Update","images/Update.png","update_ticket();");
    $dialog->add_button("Cancel","images/Update.png",
                        "top.close_current_dialog();");
    $dialog->end_button_column();
    add_base_href($dialog,$base_url,true);
    $dialog->start_form("tickets.php","EditTicket");
    $dialog->add_hidden_field("viewing",get_form_field("viewing"));
    $dialog->start_field_table();
    display_ticket_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    add_base_href($dialog,$default_base_href,true);
    $dialog->end_body();
}

function update_ticket()
{
    global $ticket_label;

    $db = new DB;
    $ticket_record = ticket_record_definition();
    $db->parse_form_fields($ticket_record);
    $old_assigned = get_form_field("oldassignedto");
    if ($old_assigned != $ticket_record['assignedto']['value'])
       $ticket_record['assigned']['value'] = time();
    process_status_changes($db,$ticket_record,UPDATERECORD);

    $row = $db->get_record("select * from tickets where id=" .
                           $ticket_record['id']['value']);
    if (! $row) {
       if (isset($db->error)) http_response(422,$db->error);
       else http_response(410,"Ticket not found");
       return;
    }
    $ticket_modified = false;
    foreach ($row as $field_name => $field_value) {
       if ($field_name == 'dirty') continue;
       if (! isset($ticket_record[$field_name]['value'])) continue;
       if ($field_value != $ticket_record[$field_name]['value']) {
          $ticket_modified = true;   break;
       }
    }
    if (! $ticket_modified) {
       http_response(201,"Ticket Not Changed");   return;
    }

    if (! $db->update("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$ticket_label." Updated");
    log_activity("Updated ".$ticket_label." #".$ticket_record['id']['value'] .
                 " (".$ticket_record['title']['value'].")");
}

function add_comment()
{
    global $ticket_label,$ticket_type;

    $db = new DB;
    $ticket_record = ticket_record_definition();
    $db->parse_form_fields($ticket_record);
    unset($ticket_record['billable']);
    unset($ticket_record['approved']);
    $ticket_record['dirty']['value'] = 1;
    if (! $db->update("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    log_activity("Added Comment to ".$ticket_label." #" .
                 $ticket_record['id']['value']);
    if ($ticket_type == SUPPORT_REQUEST) {
       if (! sync_tickets(true)) return;
    }
    http_response(201,$ticket_label." Updated");
}

function approve_ticket()
{
    global $ticket_label,$ticket_type;

    $db = new DB;
    $ticket_record = ticket_record_definition();
    $db->parse_form_fields($ticket_record);
    unset($ticket_record['billable']);
    $ticket_record['approved']['value'] = 1;
    $ticket_record['dirty']['value'] = 1;
    if (! $db->update("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$ticket_label." Updated");
    log_activity("Approved ".$ticket_label." #" .
                 $ticket_record['id']['value']);
    if ($ticket_type == SUPPORT_REQUEST) spawn_program("tickets.php sync");
}

function write_upload_response($response)
{
    print htmlspecialchars(json_encode($response),ENT_NOQUOTES);
}

function get_attach_filename($parent,$filename)
{
    global $ticket_dir,$new_dir_perms;

    $ticket_subdir = $ticket_dir."/".$parent;
    if (! file_exists($ticket_subdir)) {
       mkdir($ticket_subdir);
       if (isset($new_dir_perms) && (! chmod($ticket_subdir,$new_dir_perms))) {
          write_upload_response(array('error'=>'Unable to set permissions on ' .
                                      $ticket_subdir));
          return null;
       }
    }
    $full_filename = $ticket_dir."/".$parent."/".$filename;
    if (file_exists($full_filename)) {
       write_upload_response(array('error'=>'That file is already attached'));
       return null;
    }
    return $full_filename;
}

function upload_attachment()
{
    global $login_cookie;

    $parent = get_form_field("parent");
    if (isset($_GET['qqfile'])) {
       $filename = $_GET['qqfile'];
       $attach_filename = get_attach_filename($parent,$filename);
       if (! $attach_filename) return;
       $input = fopen("php://input","r");
       $output = fopen($attach_filename,"w");
       stream_copy_to_stream($input,$output);
       fclose($input);   fclose($output);
    }
    else if (isset($_FILES['qqfile'])) {
       $filename = $_FILES['qqfile']['name'];
       $attach_filename = get_attach_filename($parent,$filename);
       if (! $attach_filename) return;
       if (! move_uploaded_file($_FILES['qqfile']['tmp_name'],
                                $attach_filename)) {
          write_upload_response(array('error'=>'Unable to save uploaded file'));
          return;
       }
    }
    else {
       write_upload_response(array('error'=>'No files were uploaded'));
       return;
    }

    $db = new DB;
    $attachment_record = attachment_record_definition();
    $attachment_record['parent']['value'] = $parent;
    $attachment_record['filename']['value'] = $filename;
    $attachment_record['upload_date']['value'] = time();
    if (! $db->insert("ticket_attachments",$attachment_record)) {
       write_upload_response(array('error'=>$db->error));   return;
    }
    $user_list = load_users($db,false);
    $admin_user = get_cookie($login_cookie);
    $status_note = "Attachment added by ".get_user_full_name($admin_user,$user_list) .
                   " ".date("M j, Y h:i a: ").$filename;
    $history = null;
    if (! add_status_note($db,$history,$parent,$status_note,true)) {
       write_upload_response(array('error'=>$db->error));   return;
    }
    $query = "update tickets set dirty=1 where id=".$parent;
    $db->log_query($query);
    if (! $db->query($query)) {
       write_upload_response(array('error'=>$db->error));   return;
    }
    write_upload_response(array('success'=>true));
    log_activity("Uploaded Attachment ".$filename." for Ticket #".$parent);
}

function delete_attachment()
{
    global $ticket_dir,$login_cookie;

    $parent = get_form_field('parent');
    $filename = get_form_field('filename');
    $db = new DB;
    $attachment_record = attachment_record_definition();
    $attachment_record['parent']['value'] = $parent;
    $attachment_record['filename']['value'] = $filename;
    if (! $db->delete("ticket_attachments",$attachment_record)) {
       http_response(422,$db->error);   return;
    }
    $full_filename = $ticket_dir."/".$parent."/".$filename;
    if (file_exists($full_filename)) unlink($full_filename);
    $user_list = load_users($db,false);
    $admin_user = get_cookie($login_cookie);
    $status_note = "Attachment deleted by ".get_user_full_name($admin_user,$user_list) .
                   " ".date("M j, Y h:i a: ").$filename;
    $history = null;
    if (! add_status_note($db,$history,$parent,$status_note,true)) {
       http_response(422,$db->error);   return;
    }
    $query = "update tickets set dirty=1 where id=".$parent;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,"Attachment Deleted");
    log_activity("Deleted Attachment ".$filename." from Ticket #".$parent);
}

function delete_attachments($db,$ticket_id)
{
    global $ticket_dir;

    $ticket_subdir = $ticket_dir."/".$ticket_id;
    if (file_exists($ticket_subdir)) {
       $query = "delete from ticket_attachments where parent=".$ticket_id;
       $db->log_query($query);
       if (! $db->query($query)) return false;
       $tickets_dir = @opendir($ticket_subdir);
       if ($tickets_dir) {
          while (($filename = readdir($tickets_dir)) !== false) {
             if (($filename == '.') || ($filename == '..')) continue;
             unlink($ticket_subdir.'/'.$filename);
          }
          closedir($tickets_dir);
       }
       rmdir($ticket_subdir);
    }
    return true;
}

function delete_ticket()
{
    global $ticket_label;

    $id = get_form_field('id');
    $db = new DB;
    if (! delete_attachments($db,$id)) {
       http_response(422,$db->error);   return;
    }
    $ticket_record = ticket_record_definition();
    $ticket_record['id']['value'] = $id;
    if (! $db->delete("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,$ticket_label." Deleted");
    log_activity("Deleted ".$ticket_label." #".$id);
}

function view_ticket()
{
    global $status_values,$departments,$base_url,$prefix,$ticket_label;
    global $shopping_cart,$priority_values,$ticket_type;

    $default_base_href = get_current_url();
    $db = new DB;
    $user_list = load_users($db,false);

    $id = get_form_field("id");
    $row = $db->get_record("select * from tickets where id=".$id);
    if (! $row) {
       if (isset($db->error)) process_error("Database Error: ".$db->error,0);
       else process_error($ticket_label." not found",0);
       return;
    }
    $attachments = load_attachments($db,$id);
    $dialog = new Dialog;
    $dialog->set_doctype("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Strict//EN\">");
    $dialog->add_style_sheet("fileuploader.css");
    $dialog->add_style_sheet("tickets.css");
    $dialog->add_script_file("fileuploader.js");
    $dialog->add_script_file("tickets.js");
    $styles = "<style>\n";
    $styles .= "      .fieldtable { margin-left: 10px; }\n";
    $styles .= "      p { margin: 0px 0px 12px 0px; }\n";
    if (function_exists("custom_view_ticket_styles"))
       custom_view_ticket_styles($styles,$row,$db);
    $styles .= "    </style>";
    $dialog->add_head_line($styles);
    add_dialog_script($db,$dialog);
    add_script_prefix($dialog);
    $dialog->set_onload_function("create_uploader(".$id.",".$attachments.");");
    add_base_href($dialog,$default_base_href,false);
    $dialog_title = "View ".$ticket_label." (#".$id.")";
    if ($ticket_type == SUPPORT_REQUEST) {
       $dialog->set_body_id('view_support_request');
       $dialog->set_help('view_support_request');
    }
    else {
       $dialog->set_body_id('view_punch_list');
       $dialog->set_help('view_punch_list');
    }
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->set_field_padding(1);
    add_base_href($dialog,$base_url,true);

    $title = get_row_value($row,'title');
    $dialog->write("<div class=\"header_div\"><h1>Ticket " .
                   $id." - ".$title."</h1></div>\n");
    $dialog->start_form("tickets.php","ViewTicket");
    $dialog->add_hidden_field("id",$id);
    $dialog->start_field_table();
    $status = get_row_value($row,'status');
    if ($status === '') $status = 0;
    $dialog->write("<tr valign=\"bottom\"><td class=\"fieldprompt\" nowrap>" .
                   "Status:</td>\n<td id=\"status_cell\">" .
                   $status_values[$status]."</td></tr>\n");
    $department = get_row_value($row,'department');
    if (($department !== '') && isset($departments[$department]))
       $dialog->add_data_row("Department:",$departments[$department]);
    if ($ticket_type == SUPPORT_REQUEST) {
       $priority = get_row_value($row,'priority');
       if (($priority !== '') && isset($priority_values[$priority]))
          $dialog->add_data_row("Priority:",$priority_values[$priority]);
    }
    $approved = get_row_value($row,'approved');
    if ($approved == 1) $approved = 'Yes';
    else $approved = 'No';
    $dialog->add_data_row("Approved:",$approved);
    if ($ticket_type == SUPPORT_REQUEST) {
       $cc = get_row_value($row,'cc');
       if ($cc) $dialog->add_data_row("CC:",$cc);
    }
    $dialog->add_data_row("Title:",$title);
    $url = get_row_value($row,'url');
    if ($url) {
       $dialog->start_row('URL:');
       $dialog->write("<tt>\n<a target=\"_blank\" href=\"");
       $dialog->write($url);
       $dialog->write("\">");
       $dialog->write($url);
       $dialog->write("</a></tt>\n");
       $dialog->end_row();
    }
    $description = get_row_value($row,'description');
    if ($description) {
       $dialog->start_row('Description:','top');
       $dialog->write(get_row_value($row,'description'));
       $dialog->end_row();
    }
    $dialog->start_row('Attachments:','top');
    $dialog->write("<div id=\"attachments\"></div>\n");
    $dialog->end_row();
    $dialog->write("            <tr style=\"height:10px;\">" .
                   "<td colspan=\"2\"></td></tr>\n");
    $history = get_row_value($row,'history');
    if ($history) {
       $dialog->start_row('History:','top');
       $dialog->write("<div id=\"history_div\">");
       $dialog->write(get_row_value($row,'history'));
       $dialog->write("</div>\n");
       $dialog->end_row();
    }
    $dialog->end_field_table();
    $dialog->add_hidden_field("history",$history);
    $comments = get_row_value($row,'comments');
    $dialog->add_hidden_field("comments",$comments);
    $dialog->add_hidden_field("NewComments","");
    $dialog->end_form();

    if (function_exists("custom_view_ticket_section"))
       custom_view_ticket_section($dialog,$row,$db);
    $dialog->end_content_area(true);

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else $dialog->write("<div class=\"button_div\">\n");
    $dialog->start_table(null,'button_table');
    $dialog->write("<tr valign=\"middle\">\n");
    $dialog->write("<td class=\"fieldprompt view_actions\">Actions<br>\n");
    if ($status == 2) {
       $dialog->write("<a href=\"#\" onClick=\"reopen_ticket(); " .
                      "return false;\">Reopen</a>\n");
       $dialog->write("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n");
    }
    $dialog->write("<a href=\"#\" onClick=\"add_comment(); return false;\">" .
                   "Add Comment</a>\n");
    $dialog->write("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n");
    $dialog->write("<a href=\"#\" onClick=\"window.print(); return false;\">" .
                   "Print</a>\n");
    $dialog->write("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n");
    $dialog->write("<a href=\"#\" onClick=\"edit_ticket(".$id."); " .
                   "return false;\">Edit</a>\n");
    $dialog->write("</td>\n<td width=\"30px\">&nbsp;</td>\n<td>\n");
    if ($shopping_cart)
       $button_image = $prefix."/cartengine/images/Update.png";
    else $button_image = $prefix."/admin/images/Update.png";
    $dialog->add_dialog_button("Close",$button_image,
                               "top.close_current_dialog();");
    $dialog->end_row();
    $dialog->end_table();
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</div>\n");
    add_base_href($dialog,$default_base_href,true);
    $dialog->end_body();
    log_activity("Viewed ".$ticket_label." #".$id." - ".$title);
}

function reopen_ticket()
{
    global $ticket_label,$ticket_type,$login_cookie;

    $db = new DB;
    $ticket_record = ticket_record_definition();
    $db->parse_form_fields($ticket_record);
    unset($ticket_record['billable']);
    unset($ticket_record['approved']);
    $ticket_record['dirty']['value'] = 1;
    $ticket_record['status']['value'] = 0;
    if (! $db->update("tickets",$ticket_record)) {
       http_response(422,$db->error);   return;
    }
    $ticket_id = $ticket_record['id']['value'];
    if (! isset($ticket_record['history']['value'])) {
       $user_list = load_users($db,false);
       $admin_user = get_cookie($login_cookie);
       $status_note = "Reopened by ".get_user_full_name($admin_user,$user_list) .
                      " ".date("M j, Y h:i a");
       $history = null;
       if (! add_status_note($db,$history,$ticket_id,$status_note,true)) {
          http_response(422,$db->error);   return;
       }
    }
    http_response(201,$ticket_label." Updated");
    log_activity("Reopened ".$ticket_label." #" .
                 $ticket_record['id']['value']);
    if ($ticket_type == SUPPORT_REQUEST) spawn_program("tickets.php sync");
}

function template_scan_ticket_variables(&$email,$prefix,$content)
{
    if ($prefix == "ticket") {
       $var_pos = strpos($content,"{ticket:");
       if ($var_pos !== false) $email->tables['ticket'] = true;
    }
}

function template_load_ticket_table(&$email,$prefix)
{
    if (($prefix == "ticket") && isset($email->tables['ticket'])) {
       if (! isset($email->data['ticket'])) {
          $email->error = "Ticket Data is required for the " .
                          $email->name." template";
          return false;
       }
    }
    return true;
}

function get_ticket_field_value($email,$field_name)
{
    if (! isset($email->data['ticket'][$field_name])) return '';
    else if (is_array($email->data['ticket'][$field_name])) {
       if (isset($email->data['ticket'][$field_name]['value']))
          return $email->data['ticket'][$field_name]['value'];
       else return '';
    }
    return $email->data['ticket'][$field_name];
}

function get_ticket_user_info(&$email,$username)
{
    if (! isset($email->data['users'])) $email->data['users'] = array();
    if (isset($email->data['users'][$username]))
       return $email->data['users'][$username];
    $query = 'select * from users where username=';
    if ($email->db->check_encrypted_field('users','username'))
       $query .= '%ENCRYPT%(?)';
    else $query .= '?';
    $query = $email->db->prepare_query($query,$username);
    $row = $email->db->get_record($query);
    if (! $row) return null;
    $email->db->decrypt_record('users',$row);
    $email->data['users'][$username] = $row;
    return $row;
}

function build_user_email($user_info)
{
    $email = '"'.$user_info['firstname'].' '.$user_info['lastname'].'" <'.
             $user_info['email'].'>';
    return $email;
}

function template_lookup_ticket_variable(&$email,$prefix,$field_name)
{
    global $status_values,$departments,$priority_values,$base_url;

    if ($prefix == "ticket") {
       $field_value = get_ticket_field_value($email,$field_name);
       if (($field_name == 'submitteremail') ||
           ($field_name == 'assignedtoemail')) {
          if ($field_name == 'submitteremail')
             $username = get_ticket_field_value($email,'submitter');
          else $username = get_ticket_field_value($email,'assignedto');
          if (! $username) $field_value = '';
          else {
             $user_info = get_ticket_user_info($email,$username);
             if (! $user_info) $field_value = '';
             else $field_value = build_user_email($user_info);
          }
       }
       else if (($field_name == 'submitter') || ($field_name == 'assignedto') ||
                ($field_name == 'verifier')) {
          $user_info = get_ticket_user_info($email,$field_value);
          if ($user_info)
             $field_value = $user_info['firstname'].' '.$user_info['lastname'];
       }
       else if (($field_name == 'submitted') || ($field_name == 'assigned') ||
                ($field_name == 'verified') || ($field_name == 'date')) {
          if ($field_value != '') $field_value = date("F j, Y",$field_value);
       }
       else if ($field_name == 'type') {
          if ($field_value == PUNCH_LIST) $field_value = "Punch List";
          else if ($field_value == SUPPORT_REQUEST)
             $field_value = "Support Request";
       }
       else if ($field_name == 'status') {
          if (isset($status_values[$field_value]))
             $field_value = $status_values[$field_value];
       }
       else if (($field_name == 'dirty') || ($field_name == 'approved')) {
          if ($field_value == 1) $field_value = 'Yes';
          else $field_value = 'No';
       }
       else if ($field_name == 'department') {
          if (isset($departments[$field_value]))
             $field_value = $departments[$field_value];
       }
       else if ($field_name == 'priority') {
          if (isset($priority_values[$field_value]))
             $field_value = $priority_values[$field_value];
       }
       else if ($field_name == 'viewurl') {
          $ticket_id = get_ticket_field_value($email,"id");
          if (isset($prefs_cookie))
             $field_value = $base_url."admin/index.php?tab=admin&" .
                            "query=admintab%3Dother%26query%3Don_load%3D" .
                            "view_ticket%28".$ticket_id."%29;";
          else $field_value = $base_url."admin/index.php?tab=admin&" .
                              "query=on_load%3Dview_ticket%28".$ticket_id."%29;";
       }
       $field_value = str_replace("<p>",
                                  "<p style=\"font-style:italic;margin:0px 0px 12px 0px;\">",
                                  $field_value);
       $field_value = str_replace("</p>","</p>\n",$field_value);
       $field_value = str_replace("class=\"status_note\"",
                                  "style=\"font-style:italic;margin:0px 0px 6px 0px;\"",
                                  $field_value);
       if (($field_value == '') && ($field_name != 'cc') &&
           (substr($field_name,-5) != 'email'))
          $field_value = '&nbsp;';
    }
    else $field_value = null;

    return $field_value;
}

function xml_decode($data)
{
    $tag_pos = strpos($data,"&lt;");
    while ($tag_pos !== false) {
       $data = substr($data,0,$tag_pos)."<".substr($data,$tag_pos + 4);
       $tag_pos = strpos($data,"&lt;",$tag_pos);
    }

    $tag_pos = strpos($data,"&gt;");
    while ($tag_pos !== false) {
       $data = substr($data,0,$tag_pos).">".substr($data,$tag_pos + 4);
       $tag_pos = strpos($data,"&gt;",$tag_pos);
    }

    $tag_pos = strpos($data,"&amp;");
    while ($tag_pos !== false) {
       $data = substr($data,0,$tag_pos)."&".substr($data,$tag_pos + 5);
       $tag_pos = strpos($data,"&amp;",$tag_pos);
    }

    return $data;
}

function parse_xml_arrays($result,$bodytag,$key_name)
{
    $array_list = array();
    $body_length = strlen($bodytag) + 2;
    $start_pos = strpos($result,"<".$bodytag.">");
    if ($key_name == "") $index = 0;
    do {
       $array_info = array();   $key_value = "";
       if ($start_pos !== false) $start_pos += $body_length;
       $tag_pos = strpos($result,"<",$start_pos);
       do {
          if ($tag_pos !== false) {
             $array_key = "";   $array_value = "";
             $end_pos = strpos($result,">",$tag_pos);
             if ($end_pos !== false) {
                $array_key = substr($result,$tag_pos + 1,
                                    $end_pos - $tag_pos - 1);
                $start_pos = $end_pos + 1;
                $end_pos = strpos($result,"</".$array_key.">",$start_pos);
                if ($end_pos !== false) {
                   $array_value = xml_decode(substr($result,$start_pos,
                                             $end_pos - $start_pos));
                   $array_info[$array_key] = $array_value;
                   if ($array_key == $key_name) $key_value = $array_value;
                   $start_pos = $end_pos + strlen($array_key) + 3;
                }
             }
             else $start_pos = $tag_pos + 1;
          }
          $tag_pos = strpos($result,"<",$start_pos);
       } while (($tag_pos !== false) &&
                (substr($result,$tag_pos,$body_length + 1) != "</".$bodytag.">"));
       if ($key_name == "") $array_list[$index++] = $array_info;
       else if ($key_value != '') $array_list[$key_value] = $array_info;
       if ($start_pos >= strlen($result)) $start_pos = false;
       else $start_pos = strpos($result,"<".$bodytag.">",$start_pos + 1);
    } while ($start_pos !== false);
    return $array_list;
}

function build_ticket_xml($row,$attachments,$user_list,$users)
{
    global $ticket_dir,$ticket_type;

    $xml = '<ticket><id>'.$row['id'].'</id><status>'.$row['status'].'</status>';
    $xml .= '<department>'.$row['department'].'</department>';
    $xml .= '<title>'.encode_xml_data($row['title']).'</title>';
    $xml .= '<due_date>'.encode_xml_data($row['due_date']).'</due_date>';
    $xml .= '<url>'.encode_xml_data($row['url']).'</url>';
    $xml .= '<description>'.encode_xml_data($row['description']).'</description>';
    $xml .= '<history>'.encode_xml_data($row['history']).'</history>';
    $xml .= '<comments>'.encode_xml_data($row['comments']).'</comments>';
    $xml .= '<submitted>'.$row['submitted'].'</submitted>';
    $submitter = $row['submitter'];
    $xml .= '<submitter>'.encode_xml_data($submitter).'</submitter>';
    if (isset($user_list[$submitter]))
       $xml .= '<submittername>'.encode_xml_data($user_list[$submitter]) .
               '</submittername>';
    $xml .= '<assigned>'.$row['assigned'].'</assigned>';
    $assignedto = $row['assignedto'];
    $xml .= '<assignedto>'.encode_xml_data($assignedto).'</assignedto>';
    if (isset($user_list[$assignedto]))
       $xml .= '<assignedtoname>'.encode_xml_data($user_list[$assignedto]) .
               '</assignedtoname>';
    $xml .= '<verified>'.$row['verified'].'</verified>';
    $verifier = $row['verifier'];
    $xml .= '<verifier>'.encode_xml_data($verifier).'</verifier>';
    if (isset($user_list[$verifier]))
       $xml .= '<verifiername>'.encode_xml_data($user_list[$verifier]) .
               '</verifiername>';
    if ($ticket_type == SUPPORT_REQUEST) {
       if (isset($users[$submitter]))
          $xml .= '<submitteremail>' .
                  encode_xml_data(build_user_email($users[$submitter])) .
                  '</submitteremail>';
       $xml .= '<priority>'.$row['priority'].'</priority>';
       $xml .= '<cc>'.encode_xml_data($row['cc']).'</cc>';
    }
    $xml .= '<approved>'.$row['approved'].'</approved>';
    $xml .= '<attachments>';
    $parent = $row['id'];
    foreach ($attachments as $index => $attach_info) {
       if ($attach_info['parent'] == $parent) {
          $filename = $attach_info['filename'];
          $full_filename = $ticket_dir."/".$parent."/".$filename;
          $data_file = @fopen($full_filename,'rb');
          if ($data_file) {
             $data = fread($data_file,filesize($full_filename));
             fclose($data_file);
             $xml .= '<attachment><filename>'.encode_xml_data($filename) .
                     '</filename><upload_date>'.$attach_info['upload_date'] .
                     '</upload_date><data>'.base64_encode($data) .
                     '</data></attachment>';
          }
       }
    }
    $xml .= '</attachments>';
    $xml .= '</ticket>';
    return $xml;
}

function send_tickets($ticket_ids,$ticket_data)
{
    global $siteid,$ticket_type,$command_center;

    $post_string = 'cmd=synctickets&SiteID='.$siteid.'&Type=' .
                   $ticket_type.'&Tickets='.$ticket_ids.'&XML=' .
                   urlencode($ticket_data);
    require_once '../engine/http.php';

    if (empty($command_center)) $url = 'https://www.inroads.us/admin';
    else $url = $command_center;
    $url .= '/support.php';
    $http = new HTTP($url);
    $response = $http->call($post_string);
    if (! $response)
       log_error('Ticket Sync Error: '.$http->error.' ('.$http->status.' ' .
                 $http->status_string.')');
    else if (($http->status != 100) && ($http->status != 200)) {
       log_activity('Ticket Sync Response = '.$response);   return null;
    }
    return $response;
}

function sync_tickets($interactive)
{
    global $ticket_label,$ticket_dir,$ticket_type,$new_dir_perms;

    set_time_limit(0);
    ini_set('memory_limit',-1);
    set_remote_user('sync');
    $db = new DB;

    $attachments = $db->get_records('select * from ticket_attachments');
    if (! $attachments) {
       if (isset($db->error)) {
          if ($interactive) http_response(422,$db->error);
          $db->close();   return false;
       }
       else $attachments = array();
    }
    $user_list = load_users($db,false);
    $users = $db->get_records('select * from users','username');
    if (! $users) {
       if ($interactive) {
          if (isset($db->error)) http_response(422,$db->error);
          else http_response(410,'No Users Found');
       }
       $db->close();   return false;
    }
    $db->decrypt_records('users',$users);

    $result = $db->query("select * from tickets order by id");
    if (! $result) {
       if ($interactive) http_response(422,$db->error);
       $db->close();   return false;
    }
    $tickets = array();   $ticket_ids = '';   $ticket_data = '<tickets>';
    $updated_tickets = false;
    while ($row = $db->fetch_assoc($result)) {
       $tickets[$row['id']] = $row;
       if ($ticket_ids != '') $ticket_ids .= '|';
       $ticket_ids .= $row['id'];
       if ($row['dirty'] == 1) {
          $ticket_data .= build_ticket_xml($row,$attachments,$user_list,$users);
          $updated_tickets = true;
       }
    }
    $db->free_result($result);
    $ticket_data .= '</tickets>';
    $db->close();

    $update_data = send_tickets($ticket_ids,$ticket_data);
    if (! $update_data) {
       if ($update_data !== null) log_activity('No Sync Response');
       if ($interactive) http_response(422,'Sync Error');
       return false;
    }

    $db = new DB;
    if ($updated_tickets) {
       $query = 'update tickets set comments=null,dirty=0';
       $db->log_query($query);
       if (! $db->query($query)) {
          if ($interactive) http_response(422,$db->error);
          $db->close();   return false;
       }
    }

    if ($update_data == 'No Updates') {
       $db->close();   return true;
    }

    $start_pos = strpos($update_data,"<ticket_ids>");
    $end_pos = strpos($update_data,"</ticket_ids>");
    if (($start_pos === false) || ($end_pos === false)) {
       log_activity('No Sync Response');
       if ($interactive) http_response(422,'Sync Error');
       $db->close();   return false;
    }
    $ticket_ids = substr($update_data,$start_pos + 12,
                         $end_pos - $start_pos - 12);
    $ticket_ids = explode('|',$ticket_ids);
    $server_tickets = parse_xml_arrays($update_data,"ticket","id");

    foreach ($tickets as $ticket_id => $ticket) {
       if ((! in_array($ticket_id,$ticket_ids)) && ($ticket['dirty'] == 0)) {
          if (! delete_attachments($db,$ticket['id'])) {
             if ($interactive) http_response(422,$db->error);
             $db->close();   return false;
          }
          $ticket_record = ticket_record_definition();
          $ticket_record['id']['value'] = $ticket['id'];
          if (! $db->delete("tickets",$ticket_record)) {
             if ($interactive) http_response(422,$db->error);
             $db->close();   return false;
          }
          log_activity("Deleted ".$ticket_label." #".$ticket['id']);
          unset($tickets[$ticket_id]);
       }
    }

    foreach ($server_tickets as $index => $ticket) {
       $ticket_record = ticket_record_definition();
       $ticket_record['status']['value'] = $ticket['status'];
       $ticket_record['dirty']['value'] = 0;
       $ticket_record['department']['value'] = $ticket['department'];
       $ticket_record['title']['value'] = $ticket['title'];
       $ticket_record['url']['value'] = $ticket['url'];
       $ticket_record['description']['value'] = $ticket['description'];
       $ticket_record['history']['value'] = $ticket['history'];
       $ticket_record['comments']['value'] = null;
       $ticket_record['submitted']['value'] = $ticket['submitted'];
       $submitter = $ticket['submitter'];
       if ((! isset($user_list[$submitter])) &&
           isset($ticket['submittername']))
          $submitter = $ticket['submittername'];
       $ticket_record['submitter']['value'] = $submitter;
       $ticket_record['assigned']['value'] = $ticket['assigned'];
       $assignedto = $ticket['assignedto'];
       if ((! isset($user_list[$assignedto])) &&
           isset($ticket['assignedtoname']))
          $assignedto = $ticket['assignedtoname'];
       $ticket_record['assignedto']['value'] = $assignedto;
/*
       $ticket_record['verified']['value'] = $ticket['verified'];
       $verifier = $ticket['verifier'];
       if ((! isset($user_list[$verifier])) &&
           isset($ticket['verifiername']))
          $verifier = $ticket['verifiername'];
       $ticket_record['verifier']['value'] = $verifier;
*/
       $ticket_record['approved']['value'] = $ticket['approved'];
       $ticket_id = $ticket['id'];
       $ticket_record['id']['value'] = $ticket_id;
       if (isset($tickets[$ticket['id']])) {
          $new_ticket = false;
          $client_ticket = $tickets[$ticket['id']];
          if (! $db->update("tickets",$ticket_record)) {
             if ($interactive) http_response(422,$db->error);
             $db->close();   return false;
          }
          log_activity("Updated ".$ticket_label." #".$ticket_id." (" .
                       $ticket['title'].")");
       }
       else {
          $new_ticket = true;
          if (! $db->insert("tickets",$ticket_record)) {
             if ($interactive) http_response(422,$db->error);
             $db->close();   return false;
          }
          log_activity("Added ".$ticket_label." #".$ticket_id." (" .
                       $ticket['title'].")");
       }
       if ($ticket['attachments']) {
          $server_attachments = parse_xml_arrays($ticket['attachments'],
                                                 "attachment","filename");
          $attachment_record = attachment_record_definition();
          foreach ($server_attachments as $filename => $attach_info) {
             if ($new_ticket) $attachment_found = false;
             else {
                $attachment_found = false;
                foreach ($attachments as $index => $compare_attach_info) {
                   if (($compare_attach_info['parent'] == $ticket_id) &&
                       ($compare_attach_info['filename'] == $filename)) {
                      unset($attachments[$index]);
                      $attachment_found = true;   break;
                   }
                }
             }
             $ticket_subdir = $ticket_dir."/".$ticket_id;
             if (! file_exists($ticket_subdir)) {
                mkdir($ticket_subdir);
                if (isset($new_dir_perms) &&
                    (! chmod($ticket_subdir,$new_dir_perms))) {
                   $error = "Unable to set permissions on ".$ticket_subdir;
                   if ($interactive) http_response(422,$error);
                   log_error($error);   $db->close();   return false;
                }
             }
             $full_filename = $ticket_dir."/".$ticket_id."/".$filename;
             $data_file = fopen($full_filename,'wb');
             fwrite($data_file,base64_decode($attach_info['data']));
             fclose($data_file);
             if (! $attachment_found) {
                $attachment_record['parent']['value'] = $ticket_id;
                $attachment_record['filename']['value'] = $filename;
                $attachment_record['upload_date']['value'] =
                   $attach_info['upload_date'];
                if (! $db->insert("ticket_attachments",$attachment_record)) {
                   if ($interactive) http_response(422,$db->error);
                   $db->close();   return false;
                }
                log_activity("Added Attachment ".$filename." to ".$ticket_label .
                             " #".$ticket_id." (".$ticket['title'].")");
             }
             else log_activity("Updated Attachment ".$filename." for " .
                               $ticket_label." #".$ticket_id." (" .
                               $ticket['title'].")");
          }
       }
       if (! $new_ticket) {
          foreach ($attachments as $index => $attach_info) {
             if ($attach_info['parent'] == $ticket_id) {
                $filename = $attach_info['filename'];
                $attachment_record = attachment_record_definition();
                $attachment_record['parent']['value'] = $ticket_id;
                $attachment_record['filename']['value'] = $filename;
                if (! $db->delete("ticket_attachments",$attachment_record)) {
                   if ($interactive) http_response(422,$db->error);
                   $db->close();   return false;
                }
                $full_filename = $ticket_dir."/".$ticket_id."/".$filename;
                if (file_exists($full_filename)) unlink($full_filename);
                log_activity("Deleted Attachment ".$filename." from " .
                             $ticket_label." #".$ticket_id." (" .
                             $ticket['title'].")");
             }
          }
          if (($ticket_type == SUPPORT_REQUEST) || ($ticket['billable'] == 1)) {
             require_once '../engine/email.php';
             if (($client_ticket['status'] != 2) &&
                 ($ticket_record['status']['value'] == 2))
                $template = TICKET_COMPLETED;
             else if ($ticket['comments']) $template = TICKET_COMMENTS;
             else $template = null;
             if ($template) {
                $ticket_record['priority'] = $client_ticket['priority'];
                $ticket_record['cc'] = $client_ticket['cc'];
                $ticket_record['comments'] = $ticket['comments'];
                $email = new Email($template,array('ticket' => $ticket_record));
                if (! $email->send()) log_error($email->error);
             }
          }
       }
    }
    $db->close();
    return true;
}

function cleanup_tickets()
{
    global $ticket_label,$ticket_dir;

    $db = new DB;

    $where = "where status in (2,3,4)";
    $query = "select * from ticket_attachments where parent in " .
             "(select id from tickets ".$where.")";
    $result = $db->query($query);
    if (! $result) {
       print "Database Error: ".$db->error;   return;
    }
    $ids = array();
    while ($row = $db->fetch_assoc($result)) {
       $ids[$row['parent']] = true;
       $full_filename = $ticket_dir."/".$row['parent']."/".$row['filename'];
       if (file_exists($full_filename)) unlink($full_filename);
       log_activity("Deleted Attachment ".$row['filename']." from " .
                    $ticket_label." #".$row['parent']);
    }
    $db->free_result($result);

    foreach ($ids as $parent => $flag) {
       $ticket_subdir = $ticket_dir."/".$parent;
       if (file_exists($ticket_subdir)) rmdir($ticket_subdir);
    }

    $query = "delete from ticket_attachments where parent in " .
             "(select id from tickets ".$where.")";
    $db->log_query($query);
    if (! $db->query($query)) {
       print "Database Error: ".$db->error;   return;
    }

    $query = "delete from tickets ".$where;
    $db->log_query($query);
    if (! $db->query($query)) {
       print "Database Error: ".$db->error;   return;
    }

    $query = "update tickets set dirty=1";
    $db->log_query($query);
    if (! $db->query($query)) {
       print "Database Error: ".$db->error;   return;
    }

    print "Ticket Cleanup Completed";
    log_activity("Cleaned up all completed tickets");
}

function get_warranty_info()
{
    global $siteid,$command_center;

    if (empty($command_center)) $url = 'https://www.inroads.us/admin';
    else $url = $command_center;
    $url .= '/support.php?cmd=getwarrantyinfo&SiteID='.urlencode($siteid);
    $warranty_info = file_get_contents($url);
    print $warranty_info;
}

function show_warranty()
{
    global $ticket_label,$siteid;

    $client_type = get_form_field("Type");
    $minutes = get_form_field("Minutes");
    $expire = get_form_field("Expire");
    $url = 'https://www.inroads.us/admin/support.php?cmd=viewwarrantyinfo&SiteID=' .
           urlencode($siteid)."&Type=".$client_type."&Minutes=".$minutes .
           "&Expire=".$expire;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->add_style_sheet("tickets.css");
    $dialog->add_script_file("tickets.js");
    $dialog->set_onload_function("warranty_onload();");
    $dialog_title = "Add ".$ticket_label;
    $dialog->set_body_id('show_warranty');
    $dialog->set_help('show_warranty');
    $dialog->start_body($dialog_title);
    $dialog->start_field_table();
    $dialog->write("<tr><td>");
    $warranty_info = file_get_contents($url);
    print $warranty_info;
    $dialog->write("</td></tr>\n");
    $dialog->write("<tr><td align=\"center\" style=\"padding-top: 10px;\">\n");
    $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\"><tr><td>\n");
    $dialog->add_dialog_button("Approve","images/AddTicket.png",
                               "approve_out_of_warranty();");
    $dialog->write("</td>\n<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>\n<td>");
    $dialog->add_dialog_button("Cancel","images/Update.png",
                               "top.close_current_dialog();");
    $dialog->write("</td></tr></table>\n");
    $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_body();
}

if (isset($argc) && ($argc == 2) && ($argv[1] == "sync")) {
   sync_tickets(false);   DB::close_all();   exit(0);
}
if (getenv('PATH_INFO') == '/synctickets') {
   sync_tickets(false);   DB::close_all();   exit(0);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field("cmd");

if ($cmd == "createticket") create_ticket();
else if ($cmd == "addticket") add_ticket();
else if ($cmd == "processaddticket") process_add_ticket();
else if ($cmd == "editticket") edit_ticket();
else if ($cmd == "updateticket") update_ticket();
else if ($cmd == "addcomment") add_comment();
else if ($cmd == "approveticket") approve_ticket();
else if ($cmd == "uploadattachment") upload_attachment();
else if ($cmd == "deleteattachment") delete_attachment();
else if ($cmd == "deleteticket") delete_ticket();
else if ($cmd == "viewticket") view_ticket();
else if ($cmd == "reopenticket") reopen_ticket();
else if ($cmd == "synctickets") sync_tickets(true);
else if ($cmd == "cleanup") cleanup_tickets();
else if ($cmd == "getwarrantyinfo") get_warranty_info();
else if ($cmd == "showwarranty") show_warranty();
else display_ticket_dialog();

DB::close_all();

?>
