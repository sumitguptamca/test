<?php
/*
                   Inroads Shopping Cart - Banner Ads Tab

                      Written 2015-2016 by Randall Severy
                       Copyright 2015-2016 Inroads, LLC
*/

require '../engine/screen.php';
require '../engine/dialog.php';
require '../engine/db.php';

function display_banner_ads_screen()
{
    $db = new DB;
    $query = 'select id,name,preview_image from banner_slots order by id ' .
             'limit 1';
    $row = $db->get_record($query);
    if ($row) {
       $slot_id = $row['id'];   $name = $row['name'];
       $preview_image = $row['preview_image'];
    }
    else {
       $slot_id = -1;   $name = '';   $preview_image = '';
    }
    $slot_label = 'Banner Ads for <span id="slot_label">'.$name.'</span>:';
    $preview_div = '<div id="preview_div">';
    if ($preview_image)
       $preview_div .= '<img id="preview_image" src="../images/' .
          'banner-ads-preview/'.$preview_image.'" style="display:none;">';
    $preview_div .= '</div>';

    $screen = new Screen;
    $screen->enable_aw();
    $screen->enable_ajax();
    $screen->add_style_sheet('banners.css');
    $screen->add_script_file('banners.js');
    $screen->start_body();
    $screen->set_button_width(115);
    if ($screen->skin) {
       $screen->start_section();
       $screen->start_title_bar('Banner Slots');
       $screen->end_title_bar();
    }
    $screen->start_button_column();
    $screen->add_button('Add Slot','images/AddUser.png','add_slot();');
    $screen->add_button('Edit Slot','images/EditUser.png','edit_slot();');
    $screen->add_button('Delete Slot','images/DeleteUser.png',
                        'delete_slot();');
    if ($screen->skin) {
       $screen->end_button_column();
       $screen->write("          <script>load_slots_grid();</script>\n");
       $screen->write('          '.$preview_div."\n");
       $screen->end_section();
       $screen->start_section();
       $screen->start_title_bar($slot_label);
       $screen->end_title_bar();
       $screen->start_button_column();
    }
    else {
       $screen->add_button_separator('ads_sep_row',5);
       $screen->write("<td colspan=\"2\"></td></tr>\n");
    }
    $screen->add_button('Add Ad','images/AddUser.png','add_ad();');
    $screen->add_button('Edit Ad','images/EditUser.png','edit_ad();');
    $screen->add_button('Delete Ad','images/DeleteAd.png','delete_ad();');
    $screen->end_button_column();
    if (! $screen->skin) {
       $screen->write("\n          <script>load_slot_grid();</script>\n");
       $screen->write('          '.$preview_div."\n");
       $screen->write('          <br><span class="fieldprompt"' .
                      ' style="text-align: left; font-weight: bold;">' .
                      $slot_label."</span><br>\n");
    }
    $screen->write('          <script>load_ads_grid('.$slot_id .
                   ");</script>\n");
    if ($screen->skin) $screen->end_section(true);
    $screen->end_body();
}

function slot_record_definition()
{
    $slot_record = array();
    $slot_record['id'] = array('type' => INT_TYPE);
    $slot_record['id']['key'] = true;
    $slot_record['name'] = array('type' => CHAR_TYPE);
    $slot_record['width'] = array('type' => INT_TYPE);
    $slot_record['height'] = array('type' => INT_TYPE);
    $slot_record['preview_image'] = array('type' => CHAR_TYPE);
    return $slot_record;
}

function display_slot_fields($dialog,$db,$edit_type,$row)
{
    $slot_id = get_row_value($row,'id');
    $dialog->add_hidden_field('id',$slot_id);
    $dialog->add_edit_row('Name:','name',get_row_value($row,'name'),50);
    $dialog->add_edit_row('Width:','width',get_row_value($row,'width'),3);
    $dialog->add_edit_row('Height:','height',get_row_value($row,'height'),3);
    $preview_image = get_row_value($row,'preview_image');
    if ($edit_type == ADDRECORD) $frame = 'add_slot';
    else $frame = 'edit_slot';
    $dialog->add_browse_row('Preview Image:','preview_image',$preview_image,
                            50,$frame,'/images/banner-ads-preview/',true,
                            false,true,false);
}

function create_slot()
{
    $db = new DB;
    $slot_record = slot_record_definition();
    $slot_record['name']['value'] = 'New Banner Slot';
    if (! $db->insert('banner_slots',$slot_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'slot_id = '.$id.';';
    log_activity('Created New Banner Slot #'.$id);
}

function add_slot()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from banner_slots where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Banner Slot not found',0);
       return;
    }
    $row['name'] = '';
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('banners.css');
    $dialog->add_script_file('banners.js');
    $dialog->set_onload_function('add_slot_onload();');
    $dialog_title = 'Add Banner Slot (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(100);
    $dialog->start_button_column();
    $dialog->add_button('Add Slot','images/AddUser.png',
                        'process_add_slot();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('banners.php','AddSlot');
    $dialog->start_field_table();
    display_slot_fields($dialog,$db,ADDRECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_slot()
{
    $db = new DB;
    $slot_record = slot_record_definition();
    $db->parse_form_fields($slot_record);
    if (! $db->update('banner_slots',$slot_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Banner Slot Added');
    log_activity('Added Banner Slot '.$slot_record['name']['value'].' (#' .
                 $slot_record['id']['value'].')');
}

function edit_slot()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from banner_slots where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Banner Slot not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('banners.css');
    $dialog->add_script_file('banners.js');
    $dialog_title = 'Edit Banner Slot (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_slot();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('banners.php','EditSlot');
    $dialog->start_field_table();
    display_slot_fields($dialog,$db,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_slot()
{
    $db = new DB;
    $slot_record = slot_record_definition();
    $db->parse_form_fields($slot_record);
    if (! $db->update('banner_slots',$slot_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Banner Slot Updated');
    log_activity('Updated Banner Slot '.$slot_record['name']['value'].' (#' .
                 $slot_record['id']['value'].')');
}

function delete_slot()
{
    $id = get_form_field('id');
    $db = new DB;
    $slot_record = slot_record_definition();
    $slot_record['id']['value'] = $id;
    if (! $db->delete('banner_slots',$slot_record)) {
       http_response(422,$db->error);   return;
    }
    $query = 'delete from banner_ads where parent='.$id;
    $db->log_query($query);
    if (! $db->query($query)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'Banner Slot Deleted');
    log_activity('Deleted Banner Slot #'.$id);
}

function ad_record_definition()
{
    $ad_record = array();
    $ad_record['id'] = array('type' => INT_TYPE);
    $ad_record['id']['key'] = true;
    $ad_record['parent'] = array('type' => INT_TYPE);
    $ad_record['name'] = array('type' => CHAR_TYPE);
    $ad_record['image'] = array('type' => CHAR_TYPE);
    $ad_record['url'] = array('type' => CHAR_TYPE);
    $ad_record['clicks'] = array('type' => INT_TYPE);
    $ad_record['views'] = array('type' => INT_TYPE);
    return $ad_record;
}

function display_ad_fields($dialog,$db,$edit_type,$row)
{
    $width = get_form_field('width');
    $height = get_form_field('height');
    $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $dialog->add_edit_row('Name:','name',get_row_value($row,'name'),50);
    $image = get_row_value($row,'image');
    if ($edit_type == ADDRECORD) $frame = 'add_ad';
    else $frame = 'edit_ad';
    $dialog->add_browse_row('Ad Image:','image',$image,50,$frame,
                            '/images/banner-ads/',true,false,true,false);
    $dialog->write("<tr><td></td><td nowrap>\n");
    $dialog->write('Note: Ad Image must be '.$width.' pixels wide and ' .
                   $height.' pixels high');
    $dialog->end_row();
    $dialog->add_edit_row('URL:','url',get_row_value($row,'url'),50);
    $dialog->add_text_row('# Clicks:',get_row_value($row,'clicks'));
    $dialog->add_text_row('# Views:',get_row_value($row,'views'));
}

function create_ad()
{
    $parent = get_form_field('parent');
    $db = new DB;
    $ad_record = ad_record_definition();
    $ad_record['parent']['value'] = $parent;
    $ad_record['name']['value'] = 'New Banner Ad';
    if (! $db->insert('banner_ads',$ad_record)) {
       http_response(422,$db->error);   return;
    }
    $id = $db->insert_id();
    print 'ad_id = '.$id.';';
    log_activity('Created New Banner Ad #'.$id.' for Banner Slot #'.$parent);
}

function add_ad()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from banner_ads where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Banner Ad not found',0);
       return;
    }
    $row['name'] = '';
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('banners.css');
    $dialog->add_script_file('banners.js');
    $dialog->set_onload_function('add_ad_onload();');
    $dialog_title = 'Add Banner Ad (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(100);
    $dialog->start_button_column();
    $dialog->add_button('Add Ad','images/AddUser.png',
                        'process_add_ad();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('banners.php','AddAd');
    $dialog->start_field_table();
    display_ad_fields($dialog,$db,ADDRECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_ad()
{
    $db = new DB;
    $ad_record = ad_record_definition();
    $db->parse_form_fields($ad_record);
    if (! $db->update('banner_ads',$ad_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Banner Ad Added');
    log_activity('Added Banner Ad '.$ad_record['name']['value'].' (#' .
                 $ad_record['id']['value'].')');
}

function edit_ad()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from banner_ads where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Banner Ad not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_style_sheet('banners.css');
    $dialog->add_script_file('banners.js');
    $dialog_title = 'Edit Banner Ad (#'.$id.')';
    $dialog->start_body($dialog_title);
    $dialog->start_button_column();
    $dialog->add_button('Update','images/Update.png','update_ad();');
    $dialog->add_button('Cancel','images/Update.png',
                        'top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('banners.php','EditAd');
    $dialog->start_field_table();
    display_ad_fields($dialog,$db,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_ad()
{
    $db = new DB;
    $ad_record = ad_record_definition();
    $db->parse_form_fields($ad_record);
    if (! $db->update('banner_ads',$ad_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Banner Ad Updated');
    log_activity('Updated Banner Ad '.$ad_record['name']['value'].' (#' .
                 $ad_record['id']['value'].')');
}

function delete_ad()
{
    $id = get_form_field('id');
    $db = new DB;
    $ad_record = ad_record_definition();
    $ad_record['id']['value'] = $id;
    if (! $db->delete('banner_ads',$ad_record)) {
       http_response(422,$db->error);   return;
    }

    http_response(201,'Banner Ad Deleted');
    log_activity('Deleted Banner Ad #'.$id);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');
if ($cmd == 'createslot') create_slot();
else if ($cmd == 'addslot') add_slot();
else if ($cmd == 'processaddslot') process_add_slot();
else if ($cmd == 'editslot') edit_slot();
else if ($cmd == 'updateslot') update_slot();
else if ($cmd == 'deleteslot') delete_slot();
else if ($cmd == 'createad') create_ad();
else if ($cmd == 'addad') add_ad();
else if ($cmd == 'processaddad') process_add_ad();
else if ($cmd == 'editad') edit_ad();
else if ($cmd == 'updatead') update_ad();
else if ($cmd == 'deletead') delete_ad();
else display_banner_ads_screen();

?>
