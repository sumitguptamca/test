<?php
/*
               Inroads Shopping Cart - Amazon Cart Config Functions

                      Written 2018-2019 by Randall Severy
                       Copyright 2018-2019 Inroads, LLC

*/

require_once 'amazon-common.php';

global $amazon_shipping_levels;
$amazon_shipping_levels = array('Standard','Expedited','SecondDay','NextDay');

function amazon_update_config_fields(&$cart_config_fields)
{
    $cart_config_fields[] = 'amazon_merchant_id';
    $cart_config_fields[] = 'amazon_store_url';
    $cart_config_fields[] = 'amazon_last_download';
    $cart_config_fields[] = 'amazon_shipping_map';
    $cart_config_fields[] = 'amazon_dl_status_map';
    $cart_config_fields[] = 'amazon_ul_status_map';
    $cart_config_fields[] = 'amazon_sync_flags';
    $cart_config_fields[] = 'amazon_sync_times';
    $cart_config_fields[] = 'amazon_flags';
}

function amazon_config_section($db,$dialog,$config_values)
{
    global $amazon_shipping_levels,$shipping_modules;
    global $fedex_option_ids,$off_sale_option,$enable_vendor_imports;

    add_shopping_section($dialog,'Amazon');

    $dialog->write('<tr><td colspan="2">'."\n");
    $dialog->start_field_table('amazon_config_table');
    $dialog->write('<tr valign="top"><td width="50%">'."\n");

    $dialog->start_table(null,'amazon_config',0,4);
    $dialog->add_edit_row('Merchant ID:','amazon_merchant_id',
                          $config_values,30);
    $dialog->add_edit_row('Store URL:','amazon_store_url',
                          $config_values,60);
    $amazon_last_download = get_row_value($config_values,'amazon_last_download');
    $dialog->add_hidden_field('amazon_last_download',$amazon_last_download);
    if ($amazon_last_download)
       $dialog->add_text_row('Last Order Download:',date('F j, Y g:i:s a',
                             strtotime($amazon_last_download)));
    $dialog->end_table();

    $dialog->start_table(null,'amazon_shipping',0,4);
    $dialog->write("<tr><td colspan=\"4\" class=\"fieldprompt " .
                   "amazon_shipping_title\">Shipping Level Mapping" .
                   "</td></tr>\n");
    $dialog->write("<script type=\"text/javascript\">\n");
    $shipping_module_labels = array();
    call_shipping_event('module_labels',array(&$shipping_module_labels));
    $module_options = array();
    foreach ($shipping_module_labels as $module => $module_label) {
       $available_methods = $module.'_available_methods';
       if (! function_exists($available_methods)) continue;
       $methods = $available_methods();
       $dialog->write('  var '.$module.'_options = {');   $first_option = true;
       $index = 0;   $module_options[$module] = array();
       foreach ($methods as $option_id => $label) {
          if ($first_option) $first_option = false;
          else $dialog->write(', ');
          $dialog->write('"'.$option_id.'": "'.$label.'"');
          $module_options[$module][$option_id] = $label;
       }
       $dialog->write("};\n");
    }
    $dialog->write("</script>\n");
    $shipping_map = get_row_value($config_values,'amazon_shipping_map');
    $shipping_map = explode('|',$shipping_map);
    $map = array();
    foreach ($shipping_map as $map_entry) {
       if (! $map_entry) continue;
       $map_entry = explode(':',$map_entry);
       $map[$map_entry[0]] = array('carrier'=>$map_entry[1],
                                   'method'=>$map_entry[2]);
    }
    foreach ($amazon_shipping_levels as $level) {
       if (isset($map[$level])) {
          $carrier = $map[$level]['carrier'];
          $method = $map[$level]['method'];
       }
       else {
          $carrier = null;   $method = null;
       }
       $dialog->start_row($level.':','middle');
       $dialog->write('->&nbsp;</td><td>');
       $dialog->start_choicelist('amazon_carrier_'.$level,
                                 'select_amazon_carrier(this,\'' .
                                 $level.'\');');
       $dialog->add_list_item('','',(! $carrier));
       foreach ($shipping_module_labels as $module => $module_label) {
          $dialog->add_list_item($module,$module_label,$module == $carrier);
       }
       $dialog->end_choicelist();
       $dialog->write('</td><td>');
       $dialog->start_choicelist('amazon_method_'.$level);
       if ($carrier && isset($module_options[$carrier])) {
          $dialog->add_list_item('','',(! $method));
          foreach ($module_options[$carrier] as $option_id => $label)
             $dialog->add_list_item($option_id,$label,$option_id == $method);
       }
       $dialog->end_choicelist();
       $dialog->end_row();
    }
    $dialog->end_table();

    $status_values = load_cart_options(PRODUCT_STATUS,$db);

    $dialog->start_table(null,'amazon_dl_status',0,4);
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt " .
                   "amazon_status_title\">Download Status Mapping" .
                   "</td></tr>\n");
    $status_map = get_row_value($config_values,'amazon_dl_status_map');
    $status_map = explode('|',$status_map);
    if ($status_map[0] !== '') $active_status = $status_map[0];
    else $delete_status = 0;
    $dialog->start_row('Active:');
    $dialog->start_choicelist('amazon_dl_status_0');
    $dialog->add_list_item(-1,'Skip',$active_status == -1);
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$active_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    if (isset($status_map[1])) $inactive_status = $status_map[1];
    else $inactive_status = 2;
    $dialog->start_row('Inactive:');
    $dialog->start_choicelist('amazon_dl_status_1');
    $dialog->add_list_item(-1,'Skip',$inactive_status == -1);
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$inactive_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    if (isset($status_map[2])) $incomplete_status = $status_map[2];
    else $incomplete_status = -1;
    $dialog->start_row('Incomplete:');
    $dialog->start_choicelist('amazon_dl_status_2');
    $dialog->add_list_item(-1,'Skip',$incomplete_status == -1);
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$incomplete_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    if (isset($status_map[3])) $delete_status = $status_map[3];
    else $delete_status = 2;
    $dialog->start_row('Deleted:');
    $dialog->start_choicelist('amazon_dl_status_3');
    $dialog->add_list_item(-1,'Skip',$delete_status == -1);
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$delete_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->end_table();

    $dialog->start_table(null,'amazon_ul_status',0,4);
    $dialog->write("<tr><td colspan=\"2\" class=\"fieldprompt " .
                   "amazon_status_title\">Upload Status Mapping" .
                   "</td></tr>\n");
    $status_map = get_row_value($config_values,'amazon_ul_status_map');
    $status_map = explode('|',$status_map);
    if ($status_map[0] !== '') $delete_status = $status_map[0];
    else if (isset($off_sale_option)) $delete_status = $off_sale_option;
    else $delete_status = 1;
    $dialog->start_row('Delete from Amazon:');
    $dialog->start_choicelist('amazon_ul_status_0');
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$delete_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    if (isset($status_map[1])) $inactive_status = $status_map[1];
    else $inactive_status = 2;
    $dialog->start_row('Set Inactive on Amazon:');
    $dialog->start_choicelist('amazon_ul_status_1');
    foreach ($status_values as $index => $label)
       $dialog->add_list_item($index,$label,$inactive_status == $index);
    $dialog->end_choicelist();
    $dialog->end_row();
    $dialog->end_table();

    $dialog->write("</td><td width=\"50%\">\n");

    $sync_labels = array('Download Products','Upload Products',
       'Delete Products','Download Orders','Confirm Shipments');
    $dialog->start_table(null,'amazon_sync_table',0,4);
    $sync_flags = get_row_value($config_values,'amazon_sync_flags');
    $sync_times = get_row_value($config_values,'amazon_sync_times');
    $dialog->add_hidden_field('amazon_sync_times',$sync_times);
    $amazon_flags = get_row_value($config_values,'amazon_flags');
    $sync_times = explode('|',$sync_times);
    $dialog->write('<tr><th class="fieldprompt">Synchronize</th>' .
                   '<th class="fieldprompt">Last Sync Time</th></tr>'."\n");
    $dialog->write("<tr valign=\"top\"><td nowrap style=\"" .
                   "padding-right: 10px;\">\n");
    $end_loop = count($sync_labels);   $index = 0;
    for ($loop = 0;  $loop < $end_loop;  $loop++) {
       $dialog->write('<tr valign="top"><td class="fieldprompt sync_flag">');
       $dialog->add_checkbox_field('amazon_sync_'.$loop,$sync_labels[$loop],
                                   $sync_flags & (1 << $loop));
       if ($loop == 1) {
          $dialog->write('<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
          $dialog->add_checkbox_field('amazon_flag_0',
             'only with ASINs',($amazon_flags & UPLOAD_ONLY_WITH_ASIN));
       }
       $dialog->write('</td><td align="center" nowrap>'."\n");
       if (! empty($sync_times[$loop])) 
          $dialog->write(date('F j, Y g:i:s a',$sync_times[$loop]));
       else $dialog->write('&nbsp;');
       $dialog->end_row();
    }
    if ($sync_flags & 1)
       $dialog->write("<tr><td colspan=\"2\" style=\"padding:5px;\" " .
                      "align=\"center\"><div class=\"buttonwrapper\">" .
                      "<a class=\"ovalbutton\" style=\"width: 180px; " .
                      "float: none;\" onClick=\"download_amazon_products(); " .
                      "return false;\" href=\"#\"><span>" .
                      'Download All Products</span></a></div></td></tr>');
    $dialog->write("<tr><td colspan=\"2\" style=\"padding:5px;\" " .
                   "align=\"center\"><div class=\"buttonwrapper\">" .
                   "<a class=\"ovalbutton\" style=\"width: 180px; " .
                   "float: none;\" onClick=\"update_fba_flags(); " .
                   "return false;\" href=\"#\"><span>" .
                   'Update FBA Flags</span></a></div></td></tr>');
    $dialog->end_table();

    if (! empty($enable_vendor_imports)) {
       $dialog->start_table(null,'amazon_options',0,4);
       $dialog->write("<tr><td class=\"fieldprompt " .
                      "amazon_status_title\">Options" .
                      "</td></tr>\n");
       $dialog->write('<tr><td>');
       $dialog->add_checkbox_field('amazon_flag_1',
                   'Match Vendor Imports to non-Vendor products by ASIN',
                   ($amazon_flags & MATCH_IMPORT_BY_ASIN));
       $dialog->end_row();
       $dialog->write('<tr><td>');
       $dialog->add_checkbox_field('amazon_flag_2',
                                  'Skip matching FBA products',
                                  ($amazon_flags & SKIP_MATCHING_FBA));
       $dialog->end_row();
       $dialog->end_table();
    }

    $dialog->end_row();
    $dialog->end_table();
    $dialog->end_row();
}

function amazon_update_config_field($field_name,&$new_field_value,$db)
{
    global $amazon_shipping_levels;

    if ($field_name == 'amazon_shipping_map') {
       $new_field_value = '';
       foreach ($amazon_shipping_levels as $level) {
          $carrier = get_form_field('amazon_carrier_'.$level);
          $method = get_form_field('amazon_method_'.$level);
          if ($new_field_value) $new_field_value .= '|';
          $new_field_value .= $level.':'.$carrier.':'.$method;
       }
       return true;
    }
    else if ($field_name == 'amazon_dl_status_map') {
       $new_field_value = get_form_field('amazon_dl_status_0').'|' .
                          get_form_field('amazon_dl_status_1').'|' .
                          get_form_field('amazon_dl_status_2');
       return true;
    }
    else if ($field_name == 'amazon_ul_status_map') {
       $new_field_value = get_form_field('amazon_ul_status_0').'|' .
                          get_form_field('amazon_ul_status_1');
       return true;
    }
    else if ($field_name == 'amazon_sync_flags') {
       $sync_flags = 0;
       for ($loop = 0;  $loop < 5;  $loop++) {
          if (get_form_field('amazon_sync_'.$loop) == 'on')
             $sync_flags |= (1 << $loop);
       }
       $new_field_value = $sync_flags;
       return true;
    }
    else if ($field_name == 'amazon_flags') {
       $flags = 0;
       for ($loop = 0;  $loop < 3;  $loop++) {
          if (get_form_field('amazon_flag_'.$loop) == 'on')
             $flags |= (1 << $loop);
       }
       $new_field_value = $flags;
       return true;
    }    
    return false;
}

?>
