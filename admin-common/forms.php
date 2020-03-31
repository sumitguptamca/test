<?php
/*
                   Inroads Control Panel/Shopping Cart - Forms Tab

                  Written 2008-2018 by Kevin Rice and Randall Severy
                        Copyright 2008-2018 Inroads, LLC
*/

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once '../engine/modules.php';
if (isset($admin_directory) &&
    file_exists($admin_directory.'forms-config.php')) {
   require_once $admin_directory.'forms-config.php';
   if (file_exists($admin_directory.'custom-config.php'))
      require_once $admin_directory.'custom-config.php';
}
else if (file_exists('forms-config.php')) {
   require_once 'forms-config.php';
   if (file_exists('custom-config.php')) require_once 'custom-config.php';
}
else {
   require_once '../admin/forms-config.php';
   if (file_exists('../admin/custom-config.php'))
      require_once '../admin/custom-config.php';
}

define('HTML_OUTPUT','html');

if (get_current_directory() == 'cartengine') $shopping_cart = true;
else $shopping_cart = false;

class Spreadsheet {
function Spreadsheet($worksheet)
{
    $this->worksheet = $worksheet;
    $this->row = 1;
    $this->column = 0;
}
function new_row()
{
    $this->row++;
    $this->column = 0;
}
function add_cell($cell_value)
{
    $this->worksheet->setCellValueByColumnAndRow($this->column,$this->row,
                                                 $cell_value);
    $this->column++;
}
};

function add_script_prefix(&$screen)
{
    global $shopping_cart;

    if (! $shopping_cart) return;
    $head_block = "<script type=\"text/javascript\">script_prefix='../cartengine/';</script>";
    $screen->add_head_line($head_block);
}

function add_formcraft_forms($db=null)
{
    global $form_info;

    if (! module_installed('formcraft')) return;
    require_once '../admin/modules/formcraft.php';
    $formcraft = new FormCraft();
    $forms = $formcraft->load_forms(true);
    if (! $forms) return;
    foreach ($forms as $form_id => $form_def) {
       $new_form = array();   $columns = array();
       $new_form['label'] = $form_def['title'];
       $new_form['title'] = $form_def['title'];
       if (isset($form_def['fields'])) {
          foreach ($form_def['fields'] as $field_def)
             $columns[$field_def['name']] = $field_def['prompt'];
       }
       $new_form['columns'] = $columns;
       $form_info['form_'.$form_id] = $new_form;
    }
}

function display_forms_screen()
{
    global $form_info,$enable_multisite,$website_cookie;

    add_formcraft_forms();
    if (! isset($enable_multisite)) $enable_multisite = false;
    if ($enable_multisite) {
       if (isset($_COOKIE[$website_cookie]))
          $website = $_COOKIE[$website_cookie];
       else $website = 0;
    }

    $screen = new Screen;
    if (! $screen->skin) $screen->set_body_class('admin_screen_body');
    $screen->enable_calendar();
    $screen->add_style_sheet('forms.css');
    $screen->add_script_file('forms.js');
    add_script_prefix($screen);
    $screen->set_body_id('forms');
    $screen->set_help('forms');
    $screen->start_body();
    if ($screen->skin) {
       $screen->start_title_bar('Forms');
       $screen->end_title_bar();
    }
    $screen->set_button_width(115);
    $screen->start_button_column();
      $screen->add_button('Export Data','images/RunReport.png',
                          'return export_data();','export_data');
    $screen->add_button('Edit Data','images/EditUser.png','edit_data();');
    $screen->end_button_column();
    $screen->start_form('forms.php','Forms');
    $screen->start_field_table();

    $screen->write("<tr valign=\"top\"><td class=\"fieldprompt form_list_cell\">" .
                   "Forms:<br>\n");
    $list_size = count($form_info);
    if ($list_size > 8) $list_size = 8;
    $screen->start_listbox('Form',$list_size,false,'select_form();');
    foreach ($form_info as $form_id => $form_details) {
       if ($enable_multisite && isset($form_details['website']) &&
           ($website != 0) && ($form_details['website'] != $website)) continue;
       $screen->add_list_item($form_id,$form_details['label'],false);
    }
    $screen->end_listbox();
    $screen->write("</td><td class=\"fieldprompt form_options_cell\">" .
                   "Form Options:<br>\n");
    $screen->write("<div class=\"form_options_div\"><table cellspacing=\"0\" cellpadding=\"4\" " .
                   "class=\"form_options_table\">\n");

    $screen->start_hidden_row('Output:','output_row',false,'middle');
    $screen->start_choicelist('Output','select_output();');
    $screen->add_list_item('html','HTML',true);
    $screen->add_list_item('xlsx','Excel Workbook (*.xlsx)',false);
    $screen->add_list_item('xls','Excel 97-2003 Workbook (*.xls)',false);
    $screen->add_list_item('csv','CSV (Comma delimited) (*.csv)',false);
    $screen->add_list_item('txt','Text (Tab delimited) (*.txt)',false);
    $screen->end_choicelist();
    $screen->end_row();

    $screen->start_hidden_row('Destination:','dest_row',false,'middle');
    $screen->add_radio_field('Destination','0','On Screen',true);
    $screen->add_radio_field('Destination','1','New Tab',false);
    $screen->add_radio_field('Destination','2','Dialog',false);
    $screen->add_radio_field('Destination','3','Popup Window',false);
    $screen->end_row();

    $screen->start_hidden_row('Date Range:','form_date_row',false,'middle');
    $screen->write("<table cellspacing=\"0\" cellpadding=\"0\"><tr valign=\"middle\"><td>\n");
    $screen->add_radio_field('form_date','Year','',true,null);
    $screen->write("</td><td>Year:&nbsp;</td><td>\n");
    $screen->start_choicelist('form_year',null);
    $year = date('Y');
    $screen->add_list_item('','All Years',$year == '');
    for ($loop = $year - 5;  $loop < $year + 10;  $loop++)
       $screen->add_list_item($loop,$loop,$year == $loop);
    $screen->end_choicelist();
    $screen->write("</td></tr></table>\n");
    $screen->write("<table cellspacing=\"0\" cellpadding=\"0\"><tr valign=\"middle\"><td>\n");
    $screen->add_radio_field('form_date','Range','',false,null);
    $screen->write("</td><td>or:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td><td>\n");
    $start_date = mktime(0,0,0,1,1,date('y'));
    $screen->add_date_field('start_date',$start_date);
    $screen->write("</td><td>&nbsp;&nbsp;-&nbsp;&nbsp;</td><td>\n");
    $end_date = mktime(12,59,59,12,31,date('y'));
    $screen->add_date_field('end_date',$end_date);
    $screen->write("</td></tr></table>\n");
    $screen->end_row();

    $screen->write("</table></div>\n</td></tr>\n");
    $screen->end_field_table();
    $screen->end_form();
    $screen->end_button_section();

    $screen->write("<iframe id=\"form_iframe\" name=\"form_iframe\" " .
                   "width=\"100%\" height=\"100%\" frameborder=\"0\" " .
                   "style=\"display:none;\"></iframe>\n");

    $screen->finish_body();
}

function build_form_query($form_id,&$query,&$form_details)
{
    global $form_info;

    add_formcraft_forms();
    $form_details = $form_info[$form_id];

    $query = 'select * from forms';
    $date_option = get_form_field('DateOption');
    $year = get_form_field('Year');
    if ($year) {
       $start_date = mktime(0,0,0,1,1,$year);
       $end_date = mktime(23,59,59,12,31,$year);
    }
    else {
       $start_date = get_form_field('StartDate');
       $end_date= get_form_field('EndDate');
       convert_date_range($start_date,$end_date);
    }

    if (isset($year) && ($year == '')) 
       $query .= " where form_id='".$form_id."' order by creation_date";
    else {
       if ($year) {
          $form_details['title'] .= ' for '.$year;
          $query .= " where form_id='".$form_id."' and from_unixtime(creation_date,'%Y') = " .
                    $year.' order by creation_date';
       }
       else {
          $form_details['title'] .= ' for '.date('n/j/y',$start_date) .
                                    ' to '.date('n/j/y',$end_date);
          $query .= " where form_id='".$form_id."' and  creation_date >= " .
                    $start_date." and creation_date <= ".$end_date .
                    ' order by creation_date';
       }
    }
}

function export_data($form_id=null)
{
    global $form_info;

    add_formcraft_forms();
    if ($form_id === null) {
       $form_id = get_form_field('Form');
       $output_type = get_form_field('Output');
       build_form_query($form_id,$query,$form_details);
       $output_header = true;
    }
    else {
       $output_type = 'csv';
       $query = 'select * from forms where form_id="'.$form_id .
                '" order by creation_date';
       $form_details = $form_info[$form_id];
       $output_header = false;
    }
    generate_export($output_type,$query,$form_id,$form_details,$output_header);
}

function output_data($buffer,$delim)
{
    $buffer = str_replace($delim,' ',$buffer);
    $buffer = str_replace("\r",' ',$buffer);
    $buffer = str_replace("\n",' ',$buffer);
    print $buffer;
}

function generate_export($output_type,$query,$form_id,$form_details,
                         $output_header=true)
{
    $filename = 'form.'.$output_type;
    if ($output_type == 'xls') {
       $output_format = 'Excel5';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($output_type == 'xlsx') {
       $output_format = 'Excel2007';   $mime_type = 'application/vnd.ms-excel';
    }
    else if ($output_type == 'csv') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }
    else if ($output_type == 'txt') {
       $output_format = 'CSV';   $mime_type = 'text/csv';
    }

    $db = new DB;
    if (function_exists('update_forms_query'))
       update_forms_query($form_id,$query,$output_type);
    $result = $db->query($query);
    if (! $result) {
       log_error($query);
       process_error('Database Error: '.$db->error,-1);   return;
    }
    $num_records = $db->num_rows($result);
    $column_info = reset($form_details['columns']);
    if (is_array($column_info)) $expanded_columns = true;
    else $expanded_columns = false;

    if ($output_type == HTML_OUTPUT) {
       $destination = get_form_field('Destination');
       print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">\n";
       print "<html>\n";
       print "  <head>\n";
       print '    <title>'.$form_details['title']."</title>\n";
       print "    <link rel=\"stylesheet\" href=\"form-export.css?v=" .
             filemtime('form-export.css')."\" type=\"text/css\">\n";
       if (isset($form_details['css']))
          print "    <style type=\"text/css\">\n      ".$form_details['css'] .
                "\n    </style>\n";
       if ($destination == 'dialog') {
          print "    <script type=\"text/javascript\" src=\"../engine/dialog.js?v=" .
                filemtime('../engine/dialog.js')."\"></script>\n";
          print "    <script type=\"text/javascript\">set_current_dialog_title('" .
                str_replace("'","\\'",$form_details['title'])."');</script>\n";
       }
       print "  </head>\n";
       print '  <body';
       if ($destination == 'dialog')
          print " onLoad=\"dialog_onload(document,window,null);\"";
       print " class=\"form_body\">\n";
       if (isset($form_details['logo']))
          print "    <img class=\"form_logo\" src=\"".$form_details['logo']."\">\n";
       print "    <table cellpadding=\"0\" cellspacing=\"0\" class=\"print_close_table\">\n";
       print "      <tr>\n";
       print "        <td><a href=\"\" onClick=\"window.print(); return false;\"><img\n";
       print "         src=\"images/print-page.jpg\" border=\"0\" alt=\"Print This Page\" " .
             "title=\"Print This Page\"></a></td>\n";
       print "        <td valign=\"middle\" style=\"padding-left:5px\" nowrap><a " .
             "class=\"print_close_links\"\n";
       print "         href=\"\" onClick=\"window.print(); return false;\">Print Page</a></td>\n";
       if ($destination != 'onscreen') {
          if ($destination == 'dialog') $close_funct = 'top.close_current_dialog();';
          else $close_funct = 'window.close();';
          print "        <td style=\"padding-left:10px;\"><a href=\"\" onClick=\"".$close_funct .
                " return false;\"><img\n";
          print "         src=\"images/close.png\" border=\"0\" alt=\"Close This Window\" " .
                "title=\"Close This Window\"></a></td>\n";
          print "        <td valign=\"middle\" style=\"padding-left:5px\" nowrap><a " .
                "class=\"print_close_links\"\n";
          print "         href=\"\" onClick=\"".$close_funct." return false;\">Close " .
                "Window</a></td>\n";
       }
       print "      </tr>\n";
       print "    </table>\n";
       print "    <h1 class=\"form_h1\">".$form_details['title']."</h1>\n";
       print "    <h3 class=\"form_h3\">as of ".date("m/d/Y g:i:s a")."</h3>\n";
       print "<p>\n";
    }
    else {
       require_once '../engine/excel.php';
       $excel = new PHPExcel();
       $worksheet = $excel->getActiveSheet();
       $spreadsheet = new Spreadsheet($worksheet);
       if ($output_header) {
          header('Content-Type: '.$mime_type);
          header('Content-Disposition: attachment; filename="'.$filename.'"');
          header('Cache-Control: no-cache');
       }
    }

    if ($num_records == 0) {
       if ($output_type == HTML_OUTPUT)
           print "<h2 class=\"form_h2\">No Results Found</h2>\n";
    }
    else {
       if ($output_type == HTML_OUTPUT) {
          print "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" ";
          print "align=\"center\" class=\"form_table\">\n";
          print "<tr valign=\"bottom\" class=\"form_header_row\">";
          if (isset($form_details['headers'])) {
             foreach ($form_details['headers'] as $header_info) {
                print '<th ';
                if (isset($header_info['width']))
                   print "width=\"".$header_info['width']."\" ";
                print 'nowrap>'.$header_info['header']."</th>\n";
             }
          }
          else {
             if (! isset($form_details['columns']['creation_date']))
                print "<th>Date/Time</th>\n";
             foreach ($form_details['columns'] as $column_info) {
                if ($expanded_columns) $header = $column_info['header'];
                else $header = $column_info;
                print '<th ';
                if ($expanded_columns && isset($column_info['width']))
                   print "width=\"".$column_info['width']."\" ";
                print 'nowrap>'.$header."</th>\n";
             }
          }
          print "</tr>\n";
       }
       else {
          if (! isset($form_details['columns']['creation_date']))
             $spreadsheet->add_cell('Date/Time');
          foreach ($form_details['columns'] as $column_info) {
             if ($expanded_columns) $header = $column_info['header'];
             else $header = $column_info;
             $spreadsheet->add_cell($header);
          }
          $spreadsheet->new_row();
       }
    }

    $row_num = 0;
    while ($row = $db->fetch_assoc($result)) {
       if ($output_type == HTML_OUTPUT) {
          print "<tr valign=top class=\"form_row ";
          if (($row_num % 2) == 0) print 'form_row_even';
          else print 'form_row_odd';
          print "\">";
       }
       $fields = explode('|',$row['form_fields']);
       if (function_exists('update_forms_row'))
          update_forms_row($form_id,$row,$fields,$output_type);
       $form_fields = array();
       $curr_field = 0;
       while (isset($fields[$curr_field])) {
          if (isset($fields[$curr_field + 1])) {
             $form_fields[$fields[$curr_field]] = $fields[$curr_field + 1];
             $curr_field += 2;
          }
          else $curr_field++;
       }
       if (($output_type == HTML_OUTPUT) && isset($form_details['headers'])) {
          foreach ($form_details['headers'] as $index => $header_info) {
             print '<td>';   $first_row = true;   $last_row_num = -1;
             foreach ($form_details['columns'] as $column_name => $column_info) {
                if (isset($column_info['column']) &&
                    ($column_info['column'] != $index)) continue;
                if (isset($form_fields[$column_name]))
                   $field_value = $form_fields[$column_name];
                else $field_value = '';
                $cleanup_data = true;
                if (isset($column_info['conversion_function']))
                   $field_value = $column_info['conversion_function']($column_name,$field_value,
                                                                      $form_fields,$output_type,
                                                                      $cleanup_data);
                else if ($column_name == 'creation_date')
                   $field_value = date('m/d/Y g:i:s a',$row['creation_date']);
                else if ($column_name == 'id') $field_value = $row['id'];
                if ($field_value == '') continue;
                if ($first_row) {
                   $first_row = false;
                   if (isset($column_info['row']))
                      $last_row_num = $column_info['row'];
                }
                else {
                   if (isset($column_info['row'])) {
                      if ($column_info['row'] == $last_row_num) print ' ';
                      else {
                         print "<br>\n";   $last_row_num = $column_info['row'];
                      }
                   }
                   else {
                      print "<br>\n";   $last_row_num = -1;
                   }
                }
                if (isset($column_info['printlabel'])) {
                   if (isset($column_info['labelstyle']))
                      print "<span style=\"".$column_info['labelstyle']."\">";
                   print $column_info['header'].': ';
                   if (isset($column_info['labelstyle'])) print '</span>';
                }
                else if (isset($column_info['htmllabel'])) {
                   if (isset($column_info['labelstyle']))
                      print "<span style=\"".$column_info['labelstyle']."\">";
                   print $column_info['htmllabel'].': ';
                   if (isset($column_info['labelstyle'])) print '</span>';
                }
                if ($cleanup_data) {
                   $field_value = str_replace('<','&lt;',$field_value);
                   $field_value = str_replace('>','&gt;',$field_value);
                   $field_value = str_replace("\n",'<br>',$field_value);
                }
                if (isset($column_info['style']))
                   print "<span style=\"".$column_info['style']."\">";
                print $field_value;
                if (isset($column_info['style'])) print '</span>';
             }
             print "</td>\n";
          }
       }
       else {
          if (! isset($form_details['columns']['creation_date'])) {
             $creation_date = date('m/d/Y g:i:s a',$row['creation_date']);
             if ($output_type == HTML_OUTPUT)
                print '<td>'.$creation_date."</td>\n";
             else $spreadsheet->add_cell($creation_date);
          }
          if ($expanded_columns) {
             foreach ($form_details['columns'] as $column_name => $column_info) {
                if (isset($form_fields[$column_name]))
                   $field_value = $form_fields[$column_name];
                else $field_value = '';
                if ($column_name == 'creation_date')
                   $field_value = date('m/d/Y g:i:s a',$row['creation_date']);
                else if ($column_name == 'id') $field_value = $row['id'];
                $cleanup_data = true;
                if (isset($column_info['conversion_function']))
                   $field_value = $column_info['conversion_function']($column_name,$field_value,
                                                                      $form_fields,$output_type,
                                                                      $cleanup_data);
                if ($output_type == HTML_OUTPUT) {
                   if ($cleanup_data) {
                      $field_value = str_replace('<','&lt;',$field_value);
                      $field_value = str_replace('>','&gt;',$field_value);
                      $field_value = str_replace("\n",'<br>',$field_value);
                   }
                   print '<td>'.$field_value."</td>\n";
                }
                else {
                   if ($cleanup_data) {
                      $field_value = str_replace("\r",' ',$field_value);
                      $field_value = str_replace("\n",' ',$field_value);
                   }
                   $spreadsheet->add_cell($field_value);
                }
             }
          }
          else foreach ($form_fields as $field_name => $field_value) {
             $cleanup_data = true;
             if (isset($column_info['conversion_function']))
                $field_value = $column_info['conversion_function']($column_name,$field_value,
                                                                   $form_fields,$output_type,
                                                                   $cleanup_data);
             if ($output_type == HTML_OUTPUT) {
                if ($cleanup_data) {
                   $field_value = str_replace('<','&lt;',$field_value);
                   $field_value = str_replace('>','&gt;',$field_value);
                   $field_value = str_replace("\n",'<br>',$field_value);
                }
                print '<td>'.$field_value."</td>\n";
             }
             else {
                if ($cleanup_data) {
                   $field_value = str_replace("\r",' ',$field_value);
                   $field_value = str_replace("\n",' ',$field_value);
                }
                $spreadsheet->add_cell($field_value);
             }
          }
       }
       if ($output_type == HTML_OUTPUT) print "</tr>\n";
       else $spreadsheet->new_row();
       $row_num++;
    }
    $db->free_result($result);

    if ($output_type == HTML_OUTPUT) print "</table>\n";
    else {
       $excel->setActiveSheetIndex(0);
       $writer = PHPExcel_IOFactory::createWriter($excel,$output_format);
       if ($output_type == 'txt') $writer->setDelimiter("\t");
       $writer->save('php://output');
    }
    log_activity('Exported Form Data '.$form_details['label'].' ('.$form_id.')');
}

function edit_data()
{
    global $form_info;

    add_formcraft_forms();
    $form_id = get_form_field('Form');
    $dialog = new Dialog;
    $dialog->enable_aw();
    $dialog->enable_ajax();
    $dialog->add_script_file('forms.js');
    add_script_prefix($dialog);
    $head_block = "<style>\n  .fieldprompt { text-align: left; width: 50px; }\n" .
                  "  .fieldtable { width: 100%; }</style>";
    $dialog->add_head_line($head_block);
    $dialog->set_body_id('edit_form_data');
    $dialog->set_help('edit_form_data');
    $dialog->start_body('Edit Form Data');
    $dialog->set_button_width(126);
    $dialog->start_button_column();
    $dialog->add_button('View Entry','images/ViewEntry.png','view_entry();',
                        null,true,false,VIEW_BUTTON);
    $dialog->add_button('Add Entry','images/AddEntry.png','add_entry();',
                        null,true,false,ADD_BUTTON);
    $dialog->add_button('Edit Entry','images/EditEntry.png','edit_entry();',
                        null,true,false,EDIT_BUTTON);
    $dialog->add_button('Delete Entry','images/DeleteEntry.png','delete_entry();',
                        null,true,false,DELETE_BUTTON);
    $dialog->add_button('Return','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('forms.php','EditData');
    $dialog->add_hidden_field('FormId',$form_id);
    $dialog->start_field_table();
    $form_title = str_replace('<br>',' ',$form_info[$form_id]['title']);
    $dialog->add_text_row('Form:',$form_title);
    $dialog->write("<tr valign=top><td colspan=\"2\">\n");
    $dialog->write("  <script>\n");
    $column_info = reset($form_info[$form_id]['columns']);
    if (is_array($column_info)) $expanded_columns = true;
    else $expanded_columns = false;
    $dialog->write("    var field_info = {\n");
    $first_field = true;   $column_index = 0;
    foreach ($form_info[$form_id]['columns'] as $column_name => $column_info) {
       if ($first_field) $first_field = false;
       else $dialog->write(",\n");
       $dialog->write("      ".$column_index.": { name:'");
       if (! $expanded_columns) $column_name = $column_info;
       $column_name = strip_tags($column_name);
       if (strlen($column_name) > 80)
          $column_name = substr($column_name,0,80).'...';
       $column_name = str_replace("'","\\'",$column_name);
       $column_name = str_replace("\n",' ',$column_name);
       $dialog->write($column_name);
       $dialog->write("', label:'");
       if ($expanded_columns) $label = $column_info['header'];
       else $label = $column_info;
       $label = strip_tags($label);
       if (strlen($label) > 80)
          $label = substr($label,0,80).'...';
       $label = str_replace("'","\\'",$label);
       $label = str_replace("\n",' ',$label);
       $dialog->write($label);
       $dialog->write("' }");
       $column_index++;
    }
    $dialog->write("\n    };\n");
    $dialog->write("    create_data_grid('".$form_id."');\n");
    $dialog->write("  </script>\n");
    $dialog->write("</td></tr>\n");
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function form_data_record_definition()
{
    $form_data_record = array();
    $form_data_record['id'] = array('type' => INT_TYPE);
    $form_data_record['id']['key'] = true;
    $form_data_record['form_id'] = array('type' => CHAR_TYPE);
    $form_data_record['form_fields'] = array('type' => CHAR_TYPE);
    $form_data_record['creation_date'] = array('type' => INT_TYPE);
    return $form_data_record;
}

function view_entry()
{
    global $form_info;

    $db = new DB;
    add_formcraft_forms($db);
    $id = get_form_field('id');
    $row = $db->get_record('select * from forms where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Form Record not found',0);
       return;
    }
    $form_id = $row['form_id'];
    $dialog = new Dialog;
    $dialog->set_doctype("<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.0 Strict//EN\">");
    $dialog->add_style_sheet('forms.css');
    $dialog->add_script_file('forms.js');
    $label = get_form_field('label');
    if (! $label) $label = 'Entry';
    $dialog_title = 'View '.$label.' (#'.$id.')';
    $dialog->set_body_id('view_form_entry');
    $dialog->set_help('view_form_entry');
    $dialog->start_body($dialog_title);
    $dialog->start_content_area(true);
    $dialog->set_field_padding(1);
    $dialog->write("<table cellspacing=\"0\" cellpadding=\"2\" " .
                   "class=\"fieldtable\" width=\"560px\" align=\"center\">\n");
    $form_fields = get_row_value($row,'form_fields');
    $field_array = explode('|',$form_fields);
    $column_array = $form_info[$form_id]['columns'];
    $column_info = reset($column_array);
    if (is_array($column_info)) $expanded_columns = true;
    else {
       $expanded_columns = false;   $column_index = 0;
    }
    $creation_date = date('F j, Y g:i:s a',get_row_value($row,'creation_date'));
    if (isset($column_array['creation_date'])) {
       if ($expanded_columns)
          $field_label = $column_array['creation_date']['header'];
       else $field_label = $column_array['creation_date'];
    }
    else $field_label = 'Date/Time';
    $dialog->add_text_row($field_label.':',$creation_date,'bottom',false);
    if (isset($column_array['id'])) {
       if ($expanded_columns)
          $field_label = $column_array['id']['header'];
       else $field_label = $column_array['id'];
       $dialog->add_text_row($field_label.':',get_row_value($row,'id'));
    }
    $curr_field = 0;
    if (count($field_array) > 1) while (isset($field_array[$curr_field])) {
       $field_name = $field_array[$curr_field];   $curr_field++;
       if (isset($field_array[$curr_field])) {
          $field_value = $field_array[$curr_field];   $curr_field++;
       }
       else continue;
       if ($field_value == '') {
          if (! $expanded_columns) $column_index++;
          continue;
       }
       if ($expanded_columns) {
          if (isset($column_array[$field_name]))
             $field_label = $column_array[$field_name]['header'];
          else $field_label = $field_name;
       }
       else if (isset($column_array[$field_name]))
          $field_label = $column_array[$field_name];
       else if (isset($column_array[$column_index]))
          $field_label = $column_array[$column_index];
       else $field_label = $field_name;
       if (strlen($field_value) > 60)
          $dialog->add_text_row($field_label.':',$field_value,'top');
       else $dialog->add_text_row($field_label.':',$field_value);
       if (! $expanded_columns) $column_index++;
    }

    if ($dialog->skin) $dialog->start_bottom_buttons();
    else {
       $dialog->write("<tr height=\"10px\"><td colspan=2></td></tr>\n");
       $dialog->write("<tr><td colspan=2 align=\"center\">\n");
       $dialog->write("<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>");
       $dialog->write("  <td style=\"padding-right: 10px;\">\n");
    }
    $dialog->add_dialog_button('Print','images/Update.png',
                               'window.print(); return false;');
    if (! $dialog->skin)
       $dialog->write("  </td><td style=\"padding-left: 10px;\">\n");
    $dialog->add_dialog_button('Close','images/Update.png',
                               'top.close_current_dialog();');
    if ($dialog->skin) $dialog->end_bottom_buttons();
    else $dialog->write("</td></tr></table>\n</td></tr>\n");
    $dialog->write("</table>\n");
    $dialog->end_content_area(true);
    $dialog->end_body();
    log_activity('Viewed Data Record #'.$id);
}

function display_form_data_fields($db,$dialog,$edit_type,$row)
{
    global $form_info;

    add_formcraft_forms($db);
    $form_fields = get_row_value($row,'form_fields');
    if ($form_fields) $field_array = explode('|',$form_fields);
    else $field_array = null;
    $form_id = get_row_value($row,'form_id');
    $column_array = $form_info[$form_id]['columns'];
    $column_info = reset($column_array);
    if (is_array($column_info)) {
       $expanded_columns = true;
       $curr_field = 0;   $form_fields = array();
       if (count($field_array) > 1) while (isset($field_array[$curr_field])) {
          $field_name = $field_array[$curr_field];   $curr_field++;
          if (isset($field_array[$curr_field])) {
             $field_value = $field_array[$curr_field];   $curr_field++;
          }
          else $field_value = '';
          $form_fields[$field_name] = $field_value;
       }
    }
    else {
       $expanded_columns = false;   $column_index = 0;
    }

    if (($edit_type == UPDATERECORD) && (! isset($column_array['id'])))
       $dialog->add_hidden_field('id',get_row_value($row,'id'));
    $dialog->add_hidden_field('form_id',$form_id);
    if ($edit_type == ADDRECORD) $creation_date = time();
    else $creation_date = get_row_value($row,'creation_date');
    $dialog->start_row('Date:');
    $dialog->add_date_time_field('creation_date',$creation_date,false,'Time:');
    $dialog->end_row();

    foreach ($column_array as $column_name => $column_info) {
       if ($expanded_columns) {
          if ($column_name == 'creation_date') continue;
          $field_name = $column_name;   $field_label = $column_info['header'];
          if ($field_name == 'id') $field_value = get_row_value($row,'id');
          else if (isset($form_fields[$field_name]))
             $field_value = $form_fields[$field_name];
          else $field_value = '';
       }
       else {
          $field_label = $column_info;
          if ($edit_type == UPDATERECORD) {
             if ((! isset($field_array[$column_index * 2])) ||
                 (! isset($field_array[($column_index * 2) + 1]))) break;
             $field_name = $field_array[$column_index * 2];
             $field_value = $field_array[($column_index * 2) + 1];
             $column_index++;
          }
          else {
             $field_name = $column_name;   $field_value = '';
          }
       }
       if (strlen($field_value) > 60) {
          $dialog->start_row($field_label.':','top');
          $dialog->start_textarea_field($field_name,10,42,WRAP_SOFT);
          $dialog->write($field_value);
          $dialog->end_textarea_field();
          $dialog->end_row();
       }
       else $dialog->add_edit_row($field_label.':',$field_name,$field_value,50);
    }
}

function parse_form_data_fields($db,&$form_data_record)
{
    global $form_info;

    add_formcraft_forms($db);
    $db->parse_form_fields($form_data_record);
    $form_id = $form_data_record['form_id']['value'];
    $form_fields = '';   $first_field = true;
    $column_array = $form_info[$form_id]['columns'];
    $column_info = reset($column_array);
    if (is_array($column_info)) {
       $expanded_columns = true;
       foreach ($column_array as $field_name => $column_info) {
          if ($field_name == 'creation_date') continue;
          $field_value = get_form_field($field_name);
          if (($field_value !== null) && ($field_value != '')) {
             if ($first_field) $first_field = false;
             else $form_fields .= '|';
             $form_fields .= $field_name.'|'.$field_value;
          }
       }
    }
    else {
       $form_field_data = get_form_fields();
       foreach ($form_field_data as $field_name => $field_value) {
          if ($field_name == 'cmd') continue;
          if ($field_name == 'form_id') continue;
          if ($field_name == 'creation_date_string') continue;
          if ($field_name == 'creation_time') continue;
          if ($first_field) $first_field = false;
          else $form_fields .= '|';
          $form_fields .= $field_name.'|'.$field_value;
       }
    }
    $form_data_record['form_fields']['value'] = $form_fields;
    $creation_date = $form_data_record['creation_date']['value'];
    $creation_time = get_form_field('creation_time');
    $time_info = explode(':',$creation_time);
    if (count($time_info) == 3) {
       $creation_date = mktime(intval($time_info[0]),intval($time_info[1]),
                               intval($time_info[2]),date('n',$creation_date),
                               date('j',$creation_date),date('Y',$creation_date));
       $form_data_record['creation_date']['value'] = $creation_date;
    }
}

function add_entry()
{
    $db = new DB;
    $form_id = get_form_field('Form');
    $row = array();
    $row['form_id'] = $form_id;

    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('forms.css');
    $dialog->add_script_file('forms.js');
    $dialog->set_body_id('add_form_entry');
    $dialog->set_help('add_form_entry');
    $dialog->start_body('Add Entry');
    $dialog->set_button_width(120);
    $dialog->start_button_column(false,true);
    $dialog->add_button('Add Entry','images/AddEntry.png','process_add_entry();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('forms.php','AddEntry');
    $dialog->start_field_table();
    display_form_data_fields($db,$dialog,ADDRECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function process_add_entry()
{
    $db = new DB;
    $form_data_record = form_data_record_definition();
    parse_form_data_fields($db,$form_data_record);
    if (! $db->insert('forms',$form_data_record)) {
       http_response(422,'Database Error: '.$db->error);   return;
    }

    http_response(201,'Form Data Record Added');
    log_activity('Added Form Data Record '.$form_data_record['form_fields']['value'] .
                 ' to Form #'.$form_data_record['form_id']['value']);
}

function edit_entry()
{
    $db = new DB;
    $id = get_form_field('id');
    $row = $db->get_record('select * from forms where id='.$id);
    if (! $row) {
       if (isset($db->error)) process_error('Database Error: '.$db->error,0);
       else process_error('Form Record not found',0);
       return;
    }
    $dialog = new Dialog;
    $dialog->enable_ajax();
    $dialog->enable_calendar();
    $dialog->add_style_sheet('forms.css');
    $dialog->add_script_file('forms.js');
    $dialog_title = 'Edit Entry (#'.$id.')';
    $dialog->set_body_id('edit_form_entry');
    $dialog->set_help('edit_form_entry');
    $dialog->start_body($dialog_title);
    $dialog->set_button_width(120);
    $dialog->start_button_column(false,true);
    $dialog->add_button('Update','images/Update.png','update_entry();');
    $dialog->add_button('Cancel','images/Update.png','top.close_current_dialog();');
    $dialog->end_button_column();
    $dialog->start_form('forms.php','EditEntry');
    $dialog->start_field_table();
    display_form_data_fields($db,$dialog,UPDATERECORD,$row);
    $dialog->end_field_table();
    $dialog->end_form();
    $dialog->end_body();
}

function update_entry()
{
    $db = new DB;
    $form_data_record = form_data_record_definition();
    parse_form_data_fields($db,$form_data_record);
    if (! $db->update('forms',$form_data_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Form Data Record Updated');
    log_activity('Updated Form Data Record '.$form_data_record['form_fields']['value']);
}

function delete_entry()
{
    $id = get_form_field('id');

    $db = new DB;
    $form_data_record = form_data_record_definition();
    $form_data_record['id']['value'] = $id;
    if (! $db->delete('forms',$form_data_record)) {
       http_response(422,$db->error);   return;
    }
    http_response(201,'Form Data Record Deleted');
    log_activity('Deleted Form Data Record #'.$id);
}

if (isset($argc) && ($argc > 2) && ($argv[1] == 'exportdata')) {
   export_data($argv[2]);   DB::close_all();   exit(0);
}

if (! check_login_cookie()) exit;

$cmd = get_form_field('cmd');

if ($cmd == 'exportdata') export_data();
else if ($cmd == 'editdata') edit_data();
else if ($cmd == 'viewentry') view_entry();
else if ($cmd == 'addentry') add_entry();
else if ($cmd == 'processaddentry') process_add_entry();
else if ($cmd == 'editentry') edit_entry();
else if ($cmd == 'updateentry') update_entry();
else if ($cmd == 'deleteentry') delete_entry();
else display_forms_screen();

DB::close_all();

?>
