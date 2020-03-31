<?php
/*
                    Inroads Shopping Cart - SubList Functions

                        Written 2008-2019 by Randall Severy
                         Copyright 2008-2019 Inroads, LLC
*/

global $php_url,$script_url,$sublist_parent_type,$sublist_update_parent_type;

$php_url = "";
$script_url = "";
$sublist_parent_type = -1;
$sublist_parent_types = array("Category","Product");
$sublist_update_parent_type = false;

function init_sublists($php_url_param,$script_url_param,
                       $parent_type_param=null,$update_parent_type=false)
{
    global $php_url,$script_url,$sublist_parent_type;
    global $sublist_update_parent_type;

    $php_url = $php_url_param;
    $script_url = $script_url_param;
    $sublist_parent_type = $parent_type_param;
    $sublist_update_parent_type = $update_parent_type;
}

function add_filter_row($db,$dialog,$filters,$sublist_name)
{
    if (! $db) $db = new DB;
    $dialog->write('        <div class="sublist_filters">'."\n");
    $num_filters = count($filters);
    $filter_width = 200 / $num_filters;
    foreach ($filters as $filter_info) {
       if (empty($filter_info['query']) && empty($filter_info['values']))
          continue;
       $dialog->write("            <div class=\"filter\">\n");
       $dialog->write('<span>'.$filter_info['prompt'].'</span>');
       if (isset($filter_info['all_value']))
          $all_value = $filter_info['all_value'];
       else $all_value = '';
       $onchange = $sublist_name.'.filter(\''.$filter_info['field'].'\',\'' .
                   $all_value.'\');';
       $class = 'select" style="width:'.$filter_width.'px;';
       $dialog->start_choicelist($sublist_name.'_filter_' .
                                 $filter_info['field'],$onchange,$class);
       if (! empty($filter_info['all_label'])) {
          $all_label = $filter_info['all_label'];
          $dialog->add_list_item($all_value,$all_label,false);
       }
       if (! empty($filter_info['query'])) {
          $rows = $db->get_records($filter_info['query']);
          if ($rows) foreach ($rows as $row)
             $dialog->add_list_item($row['id'],$row['label'],false);
       }
       else foreach ($filter_info['values'] as $value => $text)
          $dialog->add_list_item($value,$text,false);
       $dialog->end_choicelist();
       $dialog->write("            </div>\n");
    }
    $dialog->write('         </div>'."\n");
}

function create_sublist_grids($sublist_name,$dialog,$id,$left_title,$right_title,
                              $reverse_list=false,$search_field=null,
                              $search_label=null,$include_sequence_buttons=true,
                              $multiple_add_function=null,$filters=null,$db=null)
{
    $dialog->start_table(null,'sublistTable');
    $dialog->write("        <tr valign=\"top\">\n");
    $dialog->write("          <td align=\"center\"><span id=\"".$sublist_name .
                   "_left_title\" class=\"fieldprompt\" ");
    $dialog->write("style=\"text-align:center;\">".$left_title."</span>\n");
    if (! $dialog->skin) $dialog->write("<br>\n");
    $dialog->write("            <div id=\"".$sublist_name."_left_grid_div\" " .
                   "class=\"sublistGrid\"><script>\n");
    if ($search_field) {
       $dialog->write('               '.$sublist_name.".search_form = true;\n");
       $dialog->write('               '.$sublist_name.".search_field = '" .
                      $search_field."';\n");
       $dialog->write('               '.$sublist_name.".search_label = '" .
                      $search_label."';\n");
    }
    if ($filters)
       $dialog->write('               '.$sublist_name.".filter_row = true;\n");
    $dialog->write("               ".$sublist_name.".create_left_sublist_grid(" .
                   $id.");\n" .
                   "             </script></div>\n" .
                   "          </td>\n");
    if ($dialog->skin)
       $dialog->write("          <td class=\"miniButtons\">\n");
    else $dialog->write("          <td width=\"80\" nowrap align=\"center\" " .
                        "style=\"padding-top:10px;\">\n");
    if ($include_sequence_buttons) {
       $dialog->add_dialog_button('Top','images/MoveTop.png',
                                  $sublist_name.'.move_top(); return false;',
                                  true,false,'miniTopButton');
       $dialog->add_dialog_button('Up','images/MoveUp.png',
                                  $sublist_name.'.move_up(); return false;',
                                  true,false,'miniUpButton');
       $dialog->add_dialog_button('Down','images/MoveDown.png',
                                  $sublist_name.'.move_down(); return false;',
                                  true,false,'miniDownButton');
       $dialog->add_dialog_button('Bottom','images/MoveBottom.png',
                                  $sublist_name.'.move_bottom(); return false;',
                                  true,false,'miniBottomButton');
    }
    if ($right_title) {
       $dialog->add_dialog_button('Add','images/AddSubList.png',
                                  $sublist_name.'.add_sublist_item(); return false;',
                                  true,false,'miniAddButton');
       if ($multiple_add_function)
          $dialog->add_dialog_button('Multiple<br>Add','images/AddSubList.png',
                                     $multiple_add_function.' return false;',
                                     true,false,'miniMultiAddButton');
       $dialog->add_dialog_button('Delete','images/DeleteSubList.png',
                                  $sublist_name.'.delete_sublist_item(); return false;',
                                  true,false,'miniDeleteButton');
    }
    $dialog->write("          </td>\n");
    if ($right_title) {
       $dialog->write("          <td align=\"center\"><span id=\"".$sublist_name .
                      "_right_title\" class=\"fieldprompt\" ");
       $dialog->write("style=\"text-align:center;\">".$right_title."</span>\n");
       if (! $dialog->skin) $dialog->write("<br>\n");
       $dialog->write("            <div id=\"".$sublist_name."_right_grid_div\" " .
                      "class=\"sublistGrid\"><script>".$sublist_name .
                      '.create_right_sublist_grid(');
       if ($multiple_add_function) $dialog->write('true');
       else $dialog->write('false');
       $dialog->write(");</script></div>\n");
       if ($filters) add_filter_row($db,$dialog,$filters,$sublist_name);
       if ($search_field) add_small_search_box($dialog,$search_field,
                                               $sublist_name.'.search',
                                               $sublist_name.'.reset_search');
       $dialog->write("          </td>\n");
    }
    $dialog->write("        </tr>\n");
    $dialog->end_table();
}

function sublist_record_definition($parent_field='parent',$related_type=null)
{
    global $sublist_update_parent_type;

    $sublist_record = array();
    $sublist_record['id'] = array('type' => INT_TYPE);
    $sublist_record['id']['key'] = true;
    if ($related_type !== null) {
       $sublist_record['related_type'] = array('type' => INT_TYPE);
       $sublist_record['related_type']['value'] = $related_type;
    }
    $sublist_record[$parent_field] = array('type' => INT_TYPE);
    if ($sublist_update_parent_type)
       $sublist_record['parent_type'] = array('type' => INT_TYPE);
    $sublist_record['related_id'] = array('type' => INT_TYPE);
    $sublist_record['sequence'] = array('type' => INT_TYPE);
    return $sublist_record;
}

function build_sublist_query(&$query,$first_field,$related_type)
{
    global $sublist_update_parent_type,$sublist_parent_type;

    $args = array($first_field);
    if ($sublist_update_parent_type) {
       $query .= ' and (parent_type=?)';   $args[] = $sublist_parent_type;
    }
    if ($related_type !== null) {
       $query .= ' and (related_type=?)';   $args[] = $related_type;
    }
    return $args;
}

function log_sublist_activity($action,$db,$table,$parent,$id=null,
   $sequence=null,$new_sequence=null,$new_parent=null)
{
    $log_flag = get_form_field('Log');
    if ($log_flag == 'false') return;

    $activity = null;
    switch ($action) {
       case ADDRECORD:
          log_activity('Added '.$table.' Related #'.$id.' (Sequence #' .
                       $sequence.') to Parent #'.$parent);
          if ($table == 'category_products') {
             $activity = 'Added to Category #'.$parent.' (Sequence #' .
                         $sequence.')';
             $product_id = $id;
          }
          break;
       case DELETERECORD:
          log_activity('Deleted '.$table.' Related #'.$id.' (Sequence #' .
                       $sequence.') from Parent #'.$parent);
          if ($table == 'category_products') {
             $activity = 'Deleted from Category #'.$parent.' (Sequence #' .
                         $sequence.')';
             $product_id = $id;
          }
          break;
       case COPYRECORD:
          log_activity('Copied '.$table.' Related Items from Parent #' .
                       $parent.' to Parent #'.$new_parent);
           break;
       case -1:
          log_activity('Resequenced '.$table.' Related ID #'.$id .
                       ' from Sequence #'.$sequence.' to Sequence #' .
                       $new_sequence.' in Parent #'.$parent);
          break;
       case -2:
          if ($parent)
             log_activity('Deleted Related Items for '.$table.' Parent #' .
                          $parent);
          else log_activity('Deleted Parent Items for '.$table.' Related ID #' .
                            $id);
          break;
    }
    if ($activity) {
       global $shopping_cart;
       if (file_exists("../cartengine/adminperms.php")) $shopping_cart = true;
       else $shopping_cart = false;
       require_once 'products-common.php';
       require_once 'utility.php';
       write_product_activity($activity,$product_id,$db);
    }
}

function get_next_sublist_sequence($db,$table,$parent,$parent_field='parent',
                                   $related_type=null)
{
    $query = 'select sequence from '.$db->escape($table).' where (' .
             $db->escape($parent_field).'=?)';
    $args = build_sublist_query($query,$parent,$related_type);
    $query .= ' order by sequence desc limit 1';
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $row = $db->get_record($query);
    if (! $row) {
       if (isset($db->error)) return 0;
       $sequence = 0;
    }
    else $sequence = $row['sequence'];
    $sequence++;
    return $sequence;
}

function add_sublist_item()
{
    global $sublist_update_parent_type,$sublist_parent_type;

    $parent = get_form_field('Parent');
    if ($parent === null) $parents = get_form_field('Parents');
    $id = get_form_field('Id');
    if ($id === null) $ids = get_form_field('Ids');
    $table = get_form_field('Table');
    $parent_field = get_form_field('ParentField');
    $related_type = get_form_field('RelatedType');

    $db = new DB;
    if ($parent !== null) {
       $sequence = get_next_sublist_sequence($db,$table,$parent,$parent_field,
                                             $related_type);
       if (! $sequence) {
          http_response(422,$db->error);   return;
       }
    }

    if ($parent === null) {
       $parent_array = explode(',',$parents);
       foreach ($parent_array as $parent) {
          $sequence = get_next_sublist_sequence($db,$table,$parent,$parent_field,
                                                $related_type);
          if (! $sequence) {
             http_response(422,$db->error);   return;
          }
          $sublist_record = sublist_record_definition($parent_field,
                                                      $related_type);
          $sublist_record[$parent_field]['value'] = $parent;
          if ($sublist_update_parent_type) 
             $sublist_record['parent_type']['value'] = $sublist_parent_type;
          $sublist_record['related_id']['value'] = $id;
          $sublist_record['sequence']['value'] = $sequence;
          if (! $db->insert($table,$sublist_record)) {
             http_response(422,$db->error);   return;
          }
          log_sublist_activity(ADDRECORD,$db,$table,$parent,$id,$sequence);
       }
       http_response(201,'Sublist Items Added');
    }
    else if ($id === null) {
       $id_array = explode(',',$ids);
       foreach ($id_array as $id) {
          $sublist_record = sublist_record_definition($parent_field,
                                                      $related_type);
          $sublist_record[$parent_field]['value'] = $parent;
          if ($sublist_update_parent_type) 
             $sublist_record['parent_type']['value'] = $sublist_parent_type;
          $sublist_record['related_id']['value'] = $id;
          $sublist_record['sequence']['value'] = $sequence;
          if (! $db->insert($table,$sublist_record)) {
             http_response(422,$db->error);   return;
          }
          log_sublist_activity(ADDRECORD,$db,$table,$parent,$id,$sequence);
          $sequence++;
       }
       http_response(201,'Sublist Items Added');
    }
    else {
       $sublist_record = sublist_record_definition($parent_field,
                                                   $related_type);
       $sublist_record[$parent_field]['value'] = $parent;
       if ($sublist_update_parent_type) 
          $sublist_record['parent_type']['value'] = $sublist_parent_type;
       $sublist_record['related_id']['value'] = $id;
       $sublist_record['sequence']['value'] = $sequence;
       if (! $db->insert($table,$sublist_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Sublist Item Added');
       log_sublist_activity(ADDRECORD,$db,$table,$parent,$id,$sequence);
    }
}

function delete_sublist_item()
{
    $id = get_form_field('Id');
    if ($id === null) $ids = get_form_field('Ids');
    $table = get_form_field('Table');
    $related_type = get_form_field('RelatedType');

    $db = new DB;
    $sublist_record = sublist_record_definition('parent',$related_type);
    if ($id === null) {
       $id_array = explode(',',$ids);
       $query = 'select id,parent,related_id,sequence from '.$table .
                ' where id in (?)';
       $query = $db->prepare_query($query,$id_array);
       $rows = $db->get_records($query,'id');
       if (! $rows) {
          http_response(422,'Database Error: '.$db->error);
          return;
       }
       foreach ($id_array as $id) {
          $sublist_record['id']['value'] = $id;
          if (! $db->delete($table,$sublist_record)) {
             http_response(422,$db->error);   return;
          }
          $parent = $rows[$id]['parent'];
          $related_id = $rows[$id]['related_id'];
          $sequence = $rows[$id]['sequence'];
          log_sublist_activity(DELETERECORD,$db,$table,$parent,$related_id,
                               $sequence);
       }
       http_response(201,'Sublist Items Deleted');
    }
    else {
       $query = 'select id,parent,related_id,sequence from '.$table .
                ' where id=?';
       $query = $db->prepare_query($query,$id);
       $row = $db->get_record($query);
       if (! $row) {
          http_response(422,'Database Error: '.$db->error);
          return;
       }
       $sublist_record['id']['value'] = $id;
       if (! $db->delete($table,$sublist_record)) {
          http_response(422,$db->error);   return;
       }
       http_response(201,'Sublist Item Deleted');
       log_sublist_activity(DELETERECORD,$db,$table,$row['parent'],
                            $row['related_id'],$row['sequence']);
    }
}

function resequence_sublist()
{
    $table = get_form_field('Table');
    $parent = get_form_field('Parent');
    $old_sequence = get_form_field('OldSequence');
    $new_sequence = get_form_field('NewSequence');
    $parent_field = get_form_field('ParentField');
    $related_type = get_form_field('RelatedType');

    $db = new DB;

    $query = 'select id,related_id,sequence from '.$db->escape($table) .
             ' where '.$db->escape($parent_field).'=?';
    $args = build_sublist_query($query,$parent,$related_type);
    $query .= ' order by sequence';
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) http_response(422,'Database Error: '.$db->error);
       return false;
    }
    $related_id = 0;
    foreach ($rows as $row) {
       $current_sequence = $row['sequence'];
       $updated_sequence = $current_sequence;
       if ($current_sequence == $old_sequence) {
          $updated_sequence = $new_sequence;
          $related_id = $row['related_id'];
       }
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
          $query = 'update '.$db->escape($table).' set sequence=? where id=?';
          $query = $db->prepare_query($query,$updated_sequence,$row['id']);
          $db->log_query($query);
          if (! $db->query($query)) {
             http_response(422,'Database Error: '.$db->error);   return;
          }
       }
    }

    http_response(201,'Sublist Resequenced');
    log_sublist_activity(-1,$db,$table,$parent,$related_id,$old_sequence,
                         $new_sequence);
}

function copy_sublist_items($table,$old_parent,$new_parent,$db=null,
                            $parent_field=null,$related_type=null)
{
    if (! $db) $db = new DB;
    if (! $parent_field) $parent_field = 'parent';
    $query = 'select * from '.$db->escape($table).' where (' .
             $db->escape($parent_field).'=?)';
    $args = build_sublist_query($query,$old_parent,$related_type);
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $rows = $db->get_records($query);
    if (! $rows) {
       if (isset($db->error)) {
          http_response(422,'Database Error: '.$db->error);   return false;
       }
       return true;
    }
    $sublist_record = sublist_record_definition($parent_field,$related_type);
    foreach ($rows as $row) {
       $sublist_record[$parent_field]['value'] = $new_parent;
       $sublist_record['related_id']['value'] = $row['related_id'];
       $sublist_record['sequence']['value'] = $row['sequence'];
       if (! $db->insert($table,$sublist_record)) {
          http_response(422,$db->error);   return false;
       }
    }
    log_sublist_activity(COPYRECORD,$db,$table,$old_parent,null,null,null,
                         $new_parent);
    return true;
}

function delete_sublist_items($table,$parent,$db=null,$parent_field=null,
                              $related_type=null)
{
    $log_flag = get_form_field('Log');
    if ($log_flag == 'false') $log_flag = false;
    else $log_flag = true;
    if (! $db) $db = new DB;
    if (! $parent_field) $parent_field = 'parent';
    $query = 'delete from '.$db->escape($table).' where (' .
             $db->escape($parent_field).'=?)';
    $args = build_sublist_query($query,$parent,$related_type);
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $db->log_query($query);
    if (! $db->query($query)) return false;
    log_sublist_activity(-2,$db,$table,$parent);
    return true;
}

function delete_related_items($table,$related_id,$db,$related_type=null)
{
    $log_flag = get_form_field('Log');
    if ($log_flag == 'false') $log_flag = false;
    else $log_flag = true;
    if (! $db) $db = new DB;
    $query = 'delete from '.$db->escape($table).' where (related_id=?)';
    $args = build_sublist_query($query,$related_id,$related_type);
    array_unshift($args,$query);
    $query = call_user_func_array(array($db,'prepare_query'),$args);
    $db->log_query($query);
    if (! $db->query($query)) return false;
    log_sublist_activity(-2,$db,$table,null,$related_id);
    return true;
}

function set_table_sequences($db,$table_name,$parent_type=null,
                             $parent_field=null,$related_type=null)
{
    $args = array();   $where = '';
    if ($parent_type) {
       $where = '(parent_type=?)';   $args[] = $parent_type;
    }
    if ($related_type !== null) {
       if ($where) $where .= ' and ';
       $where .= '(related_type=?)';   $args[] = $related_type;
    }
    if (! $parent_field) $parent_field = 'parent';
    $query = 'select '.$db->escape($parent_field).',max(sequence) as ' .
             'last_sequence from '.$db->escape($table_name);
    if ($where) $query .= ' where '.$where;
    $query .= ' group by '.$db->escape($parent_field);
    if ($where) {
       $query_args = $args;
       array_unshift($query_args,$query);
       $query = call_user_func_array(array($db,'prepare_query'),$query_args);
    }
    $rows = $db->get_records($query);
    if (! $rows) return;
    $last_sequences = array();
    foreach ($rows as $row) {
       $parent = $row[$parent_field];
       $last_sequence = $row['last_sequence'];
       if (! $last_sequence) $last_sequence = 0;
       $last_sequences[$parent] = $last_sequence;
    }
    $query = 'select id,'.$db->escape($parent_field).' from ' .
             $db->escape($table_name).' where isnull(sequence)';
    if ($where) $query .= ' and '.$where;
    $query .= ' order by '.$db->escape($parent_field).',id';
    if ($where) {
       $query_args = $args;
       array_unshift($query_args,$query);
       $query = call_user_func_array(array($db,'prepare_query'),$query_args);
    }
    $rows = $db->get_records($query);
    if (! $rows) return;
    foreach ($rows as $row) {
       $parent = $row[$parent_field];
       $last_sequences[$parent]++;
       $next_sequence = $last_sequences[$parent];
       $query = 'update '.$db->escape($table_name) .
                ' set sequence=? where id=?';
       $query = $db->prepare_query($query,$next_sequence,$row['id']);
       $db->log_query($query);
       if (! $db->query($query)) return;
    }
}

function set_sequences()
{
    global $related_types;

    $db = new DB;

    set_table_sequences($db,'subcategories');
    set_table_sequences($db,'category_products');
    if (isset($related_types)) {
       foreach ($related_types as $related_type => $label)
          set_table_sequences($db,'related_products',null,'parent',
                              $related_type);
    }
    set_table_sequences($db,'product_attributes');
    set_table_sequences($db,'product_inventory');
    set_table_sequences($db,'images','0');
    set_table_sequences($db,'images','1');
    set_table_sequences($db,'images','2');
    log_activity('Set Table Sequences');
    print "All empty sequences set\n";
}

function process_sublist_command($cmd)
{
    if ($cmd == 'addsublist') add_sublist_item();
    else if ($cmd == 'deletesublist') delete_sublist_item();
    else if ($cmd == 'resequencesublist') resequence_sublist();
    else if ($cmd == 'setsequences') set_sequences();
    else return false;
    return true;
}

?>
