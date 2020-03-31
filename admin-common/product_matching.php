<?php
/*
             Inroads Shopping Cart - Duplicated Products Matching

                        Written 2020 by Sergey Sizov
                         Copyright 2008-2020 Inroads, LLC
*/

if (isset($argc) && ($argc > 1)) $bg_command = $argv[1];
else $bg_command = null;

require_once '../engine/screen.php';
require_once '../engine/dialog.php';
require_once '../engine/db.php';
require_once 'image.php';
require_once 'sublist.php';
require_once 'utility.php';
require_once 'seo.php';
require_once 'catalogconfig-common.php';
if (file_exists("../cartengine/adminperms.php")) {
    $shopping_cart = true;
    require_once 'cartconfig-common.php';
    require_once 'inventory.php';
    $features = get_cart_config_value('features');
    if ($features === '') $features = 0;
    if ($features & QTY_DISCOUNTS) $use_discounts = true;
    else $use_discounts = false;
    if ($features & QTY_PRICING) $use_qty_pricing = true;
    else $use_qty_pricing = false;
    if ($features & REGULAR_PRICE_BREAKS) $use_price_breaks = true;
    else $use_price_breaks = false;
}
else {
    $shopping_cart = false;
    require_once 'catalog-common.php';
    $features = $catalog_features;
    $use_discounts = false;
    $use_qty_pricing = false;
    $use_price_breaks = false;
}
require_once 'products-common.php';

if ($bg_command) $default_base_href = $ssl_url;
else $default_base_href = get_current_url();

$products_matching_script_name = basename($_SERVER['PHP_SELF']);

if (! isset($enable_vendors)) $enable_vendors = false;

function findElemsByColumnValueInRows($rows, $column, $val) {
    $elems = array();
    if ($rows == NULL) {
        return $elems;
    }
    foreach ($rows as &$row) {
        if ($row[$column] == $val) {
            $elems[] = $row;
        }
    }
    return $elems;
}

function findElemByColumnValueInRows($rows, $column, $val) {
    if ($rows == NULL) {
        return NULL;
    }
    foreach ($rows as &$row) {
        if ($row[$column] == $val) {
            return $row;
        }
    }
    return NULL;
}

function find_all_duplicated_products($db, $page_size, $page_number, $search_text) {
    $where = '';
    if (($search_text != null) && ($search_text != '')) {
        $where .= ' and (pi.part_number in (select pri.part_number from product_inventory pri
                                            inner join products prod on (prod.id = pri.parent)
                                            where (pri.part_number like ?) or (prod.name like ?) or (prod.short_description like ?))) ';
    }
    $query = 'select count(distinct pi.part_number) as lines_count from product_inventory pi WHERE pi.part_number in ('.
        '       select pi.part_number '.
        '       from product_inventory pi '.
        '       where (pi.part_number is not null) and (pi.part_number <>  "") and (pi.qty > 0) '. $where .
        '       group by pi.part_number '.
        '       having count(pi.id) > 1) ';
    if ($where != '') {
        $query = $db->prepare_query($query, '%'.$search_text.'%', '%'.$search_text.'%', '%'.$search_text.'%');
    } else {
        $query = $db->prepare_query($query);
    }
    $lines_count = $db->get_record($query)["lines_count"];
    $total_pages_count = (int)($lines_count / $page_size);
    if ($total_pages_count * $page_size < $lines_count) {
        $total_pages_count++;
    }

    $query = 'select distinct pi.part_number '.
        '       from product_inventory pi '.
        '       where (pi.part_number is not null) and (pi.part_number <>  "") and (pi.qty > 0) '. $where .
        '       group by pi.part_number '.
        '       having count(pi.id) > 1 ';

    $limit = ' LIMIT '.(($page_number - 1) * $page_size).', '.$page_size;
    $query .= $limit;

    if ($where != '') {
        $query = $db->prepare_query($query, '%'.$search_text.'%', '%'.$search_text.'%', '%'.$search_text.'%');
    } else {
        $query = $db->prepare_query($query);
    }
    $matched_fields = $db->get_records($query);
    $matched_fields = array_column($matched_fields, 'part_number');

    $query = 'select p.id, pi.part_number, p.vendor, p.cost, p.status, v.name as vendor_name, p.name as product_name, pi.qty as product_qty, p.last_modified as product_last_modified '.
                ' from products p '.
                ' inner join vendors v on (v.id = p.vendor) '.
                ' inner join product_inventory pi on (pi.parent = p.id) '.
                ' where (pi.part_number in (?)) and (pi.qty > 0) '.
                ' order by pi.part_number';

    $query = $db->prepare_query($query, $matched_fields);
    $rows = $db->get_records($query);

    $grouped_items = array();
    foreach ($matched_fields as $mfield) {
        $matched = findElemsByColumnValueInRows($rows, 'part_number', $mfield);
        $grouped_items[] = (array) ['matched_field' => $mfield, 'products' => $matched];
    }
    return (array) ['items' => $grouped_items, 'page_number' => $page_number, 'total_pages_count' => $total_pages_count, 'total_lines_count' => $lines_count];
}

function find_all_vendors($db) {
    $query = 'select v.id, v.name from vendors v';
    $query = $db->prepare_query($query);
    $vendors = $db->get_records($query);
    return $vendors;
}

function find_all_statuses($db) {
    $query = 'select co.id, co.label as name from cart_options co where co.table_id = 0';
    $query = $db->prepare_query($query);
    $statuses = $db->get_records($query);
    return $statuses;
}

function draw_rules_select($vendors, $vendor_ids, $matched_field) {
    $rules_select = '<select class="rule_selector" id="rule_selector_'.$matched_field.'">';
    $rules_select .= '<option selected="true" value="0">Low cost</option>';
    foreach ($vendors as $vendor)
    {
        if (in_array($vendor["id"], $vendor_ids)) {
            $rules_select .= '<option value="'.$vendor["id"].'">';
            $rules_select .= 'Vendor: ' . $vendor["name"];
            $rules_select .= '</option>';
        }
    }
    $rules_select .= '</select>';
    return $rules_select;
}

function draw_status_select($statuses, $status_id, $product_id) {
    $status_select = '<select class="status_selector" id="status_selector_'.$product_id.'" name="status_selector_'.$product_id.'">';
    foreach ($statuses as $status)
    {
        if ($status["id"] == $status_id) {
            $status_select .= '<option value="' . $status["id"] . '" selected="true">';
        } else {
            $status_select .= '<option value="' . $status["id"] . '">';
        }
        $status_select .= $status["name"];
        $status_select .= '</option>';
    }
    return $status_select;
}

function draw_duplicated_items_grid($page_size, $page_number, $query) {
    $db = new DB;

    $vendors = find_all_vendors($db);
    $statuses = find_all_statuses($db);
    $duplicates = find_all_duplicated_products($db, $page_size, $page_number, $query);

    $items = $duplicates['items'];
    $total_pages_count = $duplicates['total_pages_count'];
    $total_lines_count = $duplicates['total_lines_count'];

    $table = '<table cellspacing="0" cellpadding="0" width=100% class="products_matching_grid" data-total-pages-count="'.$total_pages_count.'" data-total-lines-count="'.$total_lines_count.'">';
    $table .= '<thead>';
    $table .= '<tr valign="top">';
    $table .= '<td><input type="checkbox" id="dm_cb_select_all"/></td>';
    $table .= '<td>Part Number</td>';
    $table .= '<td>Rule</td>';
    $table .= '<td>Vendor</td>';
    $table .= '<td>Product Name</td>';
    $table .= '<td>Inventory</td>';
    $table .= '<td class="dm_product_last_update">Last Update</td>';
    $table .= '<td>Cost</td>';
    $table .= '<td>Saving</td>';
    $table .= '<td>Status</td>';
    $table .= '</tr>';
    $table .= '</thead>';
    $table .= '</tbody>';

    $even = false;

    foreach ($items as $item) {
        $matched_field = $item["matched_field"];
        $products = array();
        $vendor_ids = array();
        foreach ($item["products"] as $product)
        {
            $products[] = (array) ['product_id' => $product["id"],
                                   'product_name' => $product["product_name"],
                                   'vendor_id' => $product["vendor"],
                                   'vendor_name' => $product["vendor_name"],
                                   'qty' => $product["product_qty"],
                                   'cost' => $product["cost"],
                                   'status' => $product["status"],
                                   'product_last_modified' => $product["product_last_modified"]
                ];
            $vendor_ids[] = $product["vendor"];
        }
        $products_count = count($products, COUNT_NORMAL);
        $product_num = 1;

        $max_amount = 0;
        foreach ($products as $product)
        {
            if (($product["cost"] != NULL) && ($product["cost"] > $max_amount)) {
                $max_amount = $product["cost"];
            }
        }

        foreach ($products as $product)
        {
            $amount_saving = NULL;
            if (($product["cost"] != NULL) && ($product["cost"] != $max_amount)) {
                $diff = $max_amount - $product["cost"];
                $diff_percent = round(($diff / ($max_amount / 100)));
                $amount_saving = '$' . $diff . ' (' . $diff_percent . '%)';
            }

            $table .= '<tr valign="top" class="'.($even ? 'gray-line' : '').'" id="matched_' . $matched_field.'_'.$product["product_id"].'" data-product-id="'.$product["product_id"].'" '.
                      'data-vendor-id="'.$product["vendor_id"].'" data-cost="'.$product["cost"].'"  data-matched-field="'.$matched_field.'">';

            if ($product_num == 1) {
                $table .= '<td rowspan="' . $products_count . '"><input type="checkbox" class="matched_cb" id="matched_cb_' . $matched_field . '"/></td>';
                $table .= '<td rowspan="' . $products_count . '">' . $matched_field . '</td>';
                $table .= '<td rowspan="' . $products_count . '">' . draw_rules_select($vendors, $vendor_ids, $matched_field) . '</td>';
            }

            $table .= '<td class="width-120">' . $product["vendor_name"].'</td>';
            $table .= '<td>' . $product["product_name"].'</td>';
            $table .= '<td><span class="dm_product_qty">' . $product["qty"] .'</span></td>';
            $table .= '<td><span class="dm_product_last_update">' . date("m/d/Y H:i:s", $product["product_last_modified"]) .'</span></td>';
            $table .= '<td><span class="product_cost">' . ($product["cost"] != NULL ? '$'.$product["cost"] : NULL) .'</span></td>';
            $table .= '<td><span class="amount_saving">' . $amount_saving .'</span></td>';
            $table .= '<td>' . draw_status_select($statuses, $product['status'], $product["product_id"]) . '</td>';

            $table .= '</tr>';

            $product_num++;
        }
        $even = !$even;
    }
    $table .= '</tbody>';
    $table .= '</table>';
    return $table;
}

function draw_pagination($page_number, $page_size, $total_pages_count, $func_name) {
    $pagination_max_size = 8;
    $pagination_start = ($page_number - round($pagination_max_size/2));
    if ($pagination_start <= 0) {
        $pagination_start = 1;
    }
    $pagination_end = ($pagination_start + $pagination_max_size - 1);
    if ($pagination_end > $total_pages_count) {
        $pagination_end = $total_pages_count;
    }
    $content = '<ul class="pagination">';
    if ($page_number > 1) {
        $content .= '<li class="page-item"><a href="javascript:void('.$func_name.'('.($page_number-1).','.$page_size.'));"><span><<</span></a></li>';
    }
    for($i = $pagination_start; $i <= $pagination_end; $i++)
    {
        if ($page_number != $i) {
            $content .= '<li class="page-item"><a href="javascript:void('.$func_name.'('.$i.','.$page_size.'));"><span>'.$i.'</span></a></li>';
        } else {
            $content .= '<li class="page-item disabled"><span>'.$i.'</span></li>';
        }
    }
    if ($page_number < $total_pages_count) {
        $content .= '<li class="page-item"><a href="javascript:void('.$func_name.'('.($page_number+1).','.$page_size.'));"><span>>></span></a></li>';
    }
    $content .= '</ul>';
    return $content;
}

function load_duplicated_products_grid() {
    $page_size = get_form_field('page_size');
    if ($page_size == NULL) {
        $page_size = 100;
    }
    $page_number = get_form_field('page_number');
    if ($page_number == NULL) {
        $page_number = 1;
    }
    $query = get_form_field('query');
    $grid = draw_duplicated_items_grid($page_size, $page_number, $query);
    print($grid);
}

function load_duplicated_products_pagination() {
    $page_size = get_form_field('page_size');
    if ($page_size == NULL) {
        $page_size = 100;
    }
    $page_number = get_form_field('page_number');
    if ($page_number == NULL) {
        $page_number = 1;
    }
    $total_pages_count = get_form_field('total_pages_count');
    if ($total_pages_count == NULL) {
        $total_pages_count = 1;
    }
    $pagination = draw_pagination($page_number, $page_size, $total_pages_count, 'showDuplicatedPatientsGrid');
    print($pagination);
}

function apply_new_statuses_to_duplicated() {
    global $error, $product_label;

    set_time_limit(0);
    $db = new DB;
    $statusesJson = get_form_field('statuses');
    $product_statuses = json_decode($statusesJson, TRUE);
    $id_array = array_column($product_statuses, 'productId');
    if (count($id_array) == 0) {
        http_response(201,'No products to change');
        return;
    }
    $activity_user = get_product_activity_user($db);
    $product_record = product_record_definition();
    foreach ($id_array as $product_id) {
        if (!fill_product_record($db,$product_id,$product_record,$error)) {
            http_response(422,$error);   return;
        }

        $status = findElemByColumnValueInRows($product_statuses, 'productId', $product_id);
        if ($status != NULL) {
            if ($product_record['status']['value']  != $status['statusId']) {
                $product_record['status']['value'] = $status['statusId'];
                $product_record['last_modified']['value'] = time();
                if (!update_product_record($db, $product_record, $error, null, true)) {
                    http_response(422, $error);
                    return;
                }
                log_activity('Match Products - Update Status for product ' . $product_label . ' ' .
                    $product_record['name']['value'] . ' (#' . $product_id . ')');
                write_product_activity('Match Products - ' . $product_label . ' Status set to ' . $status['statusId'] . ' by ' .
                    $activity_user, $product_id, $db);
            }
        }
    }
    http_response(201,$product_label.' Status Changed');
}

function update_products_in_background() {
    global $error, $product_label;

    $ON_SALE_STATUS = 0;
    $OFF_SALE_STATUS = 1;

    $db = new DB;
    set_time_limit(0);
    $search_query = get_form_field('query');
    $page_size = get_form_field('page_size');
    $page_number = get_form_field('page_number');
    $ruleId = get_form_field('ruleId');
    if ($page_size == NULL || $page_number == NULL || $ruleId == null) {
        http_response(422,'Incorrect input parameters');
        return;
    }
    $duplicates = find_all_duplicated_products($db, $page_size, $page_number, $search_query);
    $items = $duplicates['items'];

    $activity_user = get_product_activity_user($db);

    foreach ($items as $item) {
        $selectedProductId = NULL;
        if ($ruleId == 0) { //low cost rule
            $low_cost = 99999999;
            foreach ($item["products"] as $product) {
                if ($product["cost"] != NULL && $product["cost"] < $low_cost) {
                    $low_cost = $product["cost"];
                    $selectedProductId = $product["id"];
                }
            }
        } else { //vendor rule
            foreach ($item["products"] as $product) {
                if ($product["vendor"] == $ruleId) {
                    $selectedProductId = $product["id"];
                }
            }
        }

        if ($selectedProductId != NULL) { //apply rule
            foreach ($item["products"] as $product) {
                $product_record = product_record_definition();

                if (fill_product_record($db,$product["id"],$product_record,$error)) {
                    $statusId = ($product["id"] == $selectedProductId) ? $ON_SALE_STATUS : $OFF_SALE_STATUS;

                    if ($product_record['status']['value']  != $statusId) {
                        $product_record['status']['value'] = $statusId;
                        $product_record['last_modified']['value'] = time();

                        if (update_product_record($db, $product_record, $error, null, true)) {
                            log_activity('Match Products - Update Status for product ' . $product_label . ' ' . $product_record['name']['value'] . ' (#' . $product["id"] . ')');
                            write_product_activity('Match Products - ' . $product_label . ' Status set to ' . $statusId . ' by ' . $activity_user, $product["id"], $db);
                        }
                    }
                }
            }
        }
    }
    http_response(201,$product_label.' Status Changed');
}

function match_products_dialog() {
    global $products_matching_script_name;

    $db = new DB;
    $vendors = find_all_vendors($db);

    $dialog = new Dialog;
    $dialog->write('<script type="text/javascript" src="../admin/skins/2016/jquery-2.1.4.min.js"></script>');
    $dialog->add_script_file('product_matching.js');
    setup_product_change_dialog($dialog);
    $dialog->set_body_id('match_products_dialog');
    $dialog->set_help('match_products_dialog');
    $dialog->start_body('Duplicated Products');
    $dialog->set_button_width(180);
    $dialog->start_button_column();
    $dialog->add_button('Update Page','images/Update.png', 'apply_new_statuses_to_duplicated();');
    $dialog->add_button('Cancel','images/Update.png', 'top.close_current_dialog();');
    $dialog->write('<div>&nbsp;</div>');
    $dialog->add_button('Set for Selected','images/Update.png', 'matching_set_for_selected();');
    $dialog->write('<div><span class="fieldprompt">Select Rule</span></div>');
    $dialog->start_choicelist('common_rule_selector', null);
    $dialog->add_list_item("0", "Low cost", true);
    foreach ($vendors as $vendor)
    {
        $dialog->add_list_item($vendor["id"], 'Vendor: '.$vendor["name"], false);
    }
    $dialog->end_choicelist();
    $dialog->add_button('Total Update','images/Update.png', 'startBackgroundProcessing();');
    add_search_box($dialog,"search_duplicated_products","reset_search_duplicated_products", "duplicated_products_search");
    $dialog->write('<div class="pagination_section"><div><span class="fieldprompt"># of Pages</span></div>');
    $dialog->write('<div id="match_products_pagination"></div>');
    $dialog->write('<div id="total_duplicated_lines_count"></div></div>');
    $dialog->end_button_column();
    $dialog->start_form($products_matching_script_name,'ApplyNewStatusesToDuplicated');
    $dialog->write('<div id="matching_products_div" class="fieldSection"><img alt="Loading.. Please wait." class="ajax-loader" src="images/ajax_loader.gif"/></div>');
    $dialog->write('<script>showDuplicatedPatientsGrid(matching_current_page, matching_page_size);</script>');
    $dialog->end_form();
    $dialog->end_body();
}

$cmd = get_form_field('cmd');

if ($cmd == 'loadduplicatedproductsgrid') {
    load_duplicated_products_grid();
} else if ($cmd == 'applynewstatusestoduplicated') {
    apply_new_statuses_to_duplicated();
} else if ($cmd == 'matchproductsdialog') {
    match_products_dialog();
} else if ($cmd == 'loadduplicatedproductspagination') {
    load_duplicated_products_pagination();
} else if ($cmd == 'updateproductsinbackground') {
    update_products_in_background();
}/* else {
    print('<html><body><head>' .
        '<link rel="stylesheet" href="../engine/ui.css?v=1512850736" type="text/css">'.
        '<link rel="stylesheet" href="../engine/topscreen.css?v=1512850736" type="text/css">'.
        '<link rel="stylesheet" href="../engine/dhtmlwindow.css?v=1512850736" type="text/css">' .
        '<script type="text/javascript" src="../admin/skins/2016/jquery-2.1.4.min.js?v=1512851180"></script>' .
        '<script src="../js/bootstrap.min.js?v=1570687402"></script>' .
        '<script type="text/javascript" src="./ui.js"></script>' .
        '<script type="text/javascript" src="./product_matching.js"></script>' .
        '</head>');

    print('<form method="POST" action="product_matching.php" name="ApplyNewStatusesToDuplicated" id="ApplyNewStatusesToDuplicated"><div id="dialog_div">'.
        '<div><input type="button" value="Update" onclick="apply_new_statuses_to_duplicated();"></div>'.
        '<div id="matching_buttons">'.
        '<div><input type="button" onclick="matching_set_for_selected();" value="Set for selected"></div>'.
        '<div><select class="common_rule_selector" id="common_rule_selector"></select></div>'.
        '</div>'.
        '<div id="matching_products_div" class="fieldSection">Loading... please wait</div></div></form>');
        '<script>showDuplicatedPatientsGrid(matching_current_page, matching_page_size);</script>');
    print('</body></html>');
}*/
?>