/*
             Inroads Shopping Cart - Products - Duplicates matching JavaScript Functions

                        Written 2020 by Sergey Sizov
                         Copyright 2008-2020 Inroads, LLC
*/

var products_matching_script_name = 'product_matching.php';
var matching_current_page = 1;
var matching_page_size = 1000;

var ON_SALE_STATUS = 0;
var OFF_SALE_STATUS = 1;

function match_products_dialog()
{
    if ((typeof(top.current_tab) == "undefined") &&
        (typeof(admin_path) != "undefined")) var url = admin_path;
    else var url = script_prefix;
    url += products_matching_script_name + '?cmd=matchproductsdialog';
    var window_width = top.get_document_window_width();
    if (top.dialog_frame_width) window_width -= top.dialog_frame_width;
    else window_width -= top.default_dialog_frame_width;
    url += '&window_width=' + window_width;
    top.create_dialog('match_products_dialog',null,null,product_dialog_width,800,false,url,null);
}

function showDuplicatedPatientsGrid(page_number, page_size) {
    var url = script_prefix;
    url += products_matching_script_name;
    $('#matching_products_div').html('<img alt="Loading.. Please wait." class="ajax-loader" src="images/ajax_loader.gif"/>');
    var query = document.duplicated_products_search.query.value.trim();
    query = query.replace(/'/g,'\\\'');
    call_ajax(url,"cmd=loadduplicatedproductsgrid&query="+ query +"&page_number=" + page_number + "&page_size=" + page_size, true,
        function (ajax_request) {
            var status = ajax_request.get_status();
            if (status == 200 || status == 201) {
                var gridContainer = document.getElementById('matching_products_div');
                if (gridContainer) {
                    matching_current_page = page_number;
                    gridContainer.innerHTML = ajax_request.request.response;
                    var total_pages_count = $(gridContainer).find('table').data('total-pages-count');
                    var total_lines_count = $(gridContainer).find('table').data('total-lines-count');
                    $('#total_duplicated_lines_count').html('<label>Total duplicates: '+ total_lines_count + '</label>');
                    showDuplicatedPatientsPagination(page_number, page_size, total_pages_count);
                    applyAllRules();
                    $(".rule_selector").on('change', function(){
                        var matched_field = $(this).parent().parent().data('matched-field');
                        applyRule(matched_field);
                    });
                    $(".matched_cb").on('change', function(){
                        if (!$(this).is(':checked')) {
                            $("#dm_cb_select_all").prop('checked', false);
                        }
                    });
                    $("#dm_cb_select_all").on('change', function(){
                        $(".matched_cb").prop('checked', $(this).is(':checked'));
                    });
                }
            }
            else if ((status >= 202) && (status < 300)) ajax_request.display_error();
        });
}

function showDuplicatedPatientsPagination(page_number, page_size, total_pages_count) {
    var url = script_prefix;
    url += products_matching_script_name;
    call_ajax(url,"cmd=loadduplicatedproductspagination&page_number=" + page_number + "&page_size=" + page_size + "&total_pages_count=" + total_pages_count, true,
        function (ajax_request) {
            var status = ajax_request.get_status();
            if (status == 200 || status == 201) {
                var paginationContainer = document.getElementById('match_products_pagination');
                if (paginationContainer) {
                    paginationContainer.innerHTML = ajax_request.request.response;
                }
            }
            else if ((status >= 202) && (status < 300)) ajax_request.display_error();
        });
}

function startBackgroundProcessing() {
    var gridContainer = document.getElementById('matching_products_div');
    if (!gridContainer) {
        return;
    }
    var ruleId = $('#common_rule_selector').val();
    var total_pages_count = $(gridContainer).find('table').data('total-pages-count');
    $('#matching_products_div').html('<img alt="Processing" class="ajax-loader" src="images/ajax_loader.gif"/>');
    top.display_status('Total Update', 'Progress: 0%' , 600,100,null);
    processPageInBackground(1, matching_page_size, ruleId, total_pages_count);
}

function processPageInBackground(page_number, page_size, ruleId, total_pages_count) {
    var url = script_prefix;
    url += products_matching_script_name;
    var query = document.duplicated_products_search.query.value.trim();
    query = query.replace(/'/g,'\\\'');
    call_ajax(url,"cmd=updateproductsinbackground&query="+ query +"&page_number=" + page_number + "&page_size=" + page_size + "&ruleId=" + ruleId, true,
        function (ajax_request) {
            var status = ajax_request.get_status();
            if (status == 200 || status == 201) {
                var progress = Math.round(page_number * 100 / total_pages_count);
                top.remove_status();
                top.display_status('Total Update', 'Progress: ' + progress + '%' , 600,100,null);
                if (page_number < total_pages_count) {
                    processPageInBackground(page_number + 1, page_size, ruleId, total_pages_count);
                } else {
                    top.remove_status();
                    showDuplicatedPatientsGrid(1, matching_page_size);
                }
            }
            else if ((status >= 202) && (status < 300)) ajax_request.display_error();
        });
}

function applyRule(matched_field) {
    var ruleSelector = $("#rule_selector_" + matched_field);
    if (ruleSelector.length) {
        var rows = $("tr[id^='matched_" + matched_field + "_']");
        if (rows.length) {
            var selectedProductId = null;
            var vendorId = ruleSelector.val();
            if (vendorId == '0') { //low cost rule
                var lowCost = 9999999;
                rows.each(function(idx, row) {
                    var cost = $(row).data('cost');
                    if (cost == '') {
                        cost = 99999990;
                    }
                    cost = Number(cost);
                    if (cost <= lowCost) {
                        lowCost = cost;
                        selectedProductId = $(row).data('product-id');
                    }
                });
            } else { //Vendor rule
                rows.each(function(idx, row) {
                    var productVendorId = $(row).data('vendor-id');
                    if (productVendorId == vendorId) {
                        selectedProductId = $(row).data('product-id');
                    }
                });
            }
            //apply rule
            rows.find('.status_selector').val(OFF_SALE_STATUS);
            rows.find('#status_selector_' + selectedProductId).val(ON_SALE_STATUS);
        }
    }
}

function applyAllRules() {
    var rows = $("tr[id^='matched_']");
    if (rows.length) {
        rows.each(function(idx, row) {
            matched_field = $(row).data('matched-field');
            applyRule(matched_field);
        });
    }
}

function isEleByIdInArray(arr, id) {
    var found = false;
    $.each(arr, function(idx, e) {
        if (e.id == id) {
            return found = true;
        }
    });
    return found;
}

function search_duplicated_products()
{
    showDuplicatedPatientsGrid(1, matching_page_size);
}

function reset_search_duplicated_products()
{
    document.duplicated_products_search.query.value = '';
    search_duplicated_products();
}

function matching_set_for_selected() {
    var selectedMatchedFieldsCbs = $(".matched_cb:checked");
    if (selectedMatchedFieldsCbs.length) {
        var commonRuleId = $("#common_rule_selector").val();
        selectedMatchedFieldsCbs.each(function(idx, ele) {
            var matched_field = $(ele).parent().parent().data('matched-field');
            var possibleRuleIds = $("#rule_selector_" + matched_field + " > option").map(function() { return $(this).val(); });
            if ($.inArray(commonRuleId, possibleRuleIds) >= 0) {
                $("#rule_selector_" + matched_field).val($("#common_rule_selector").val());
                applyRule(matched_field);
            }
        });
    }
}

function apply_new_statuses_to_duplicated() {
    var form = $("#ApplyNewStatusesToDuplicated");
    var fields = "cmd=applynewstatusestoduplicated";
    var statusSelectors = $(".status_selector");
    var statuses = [];
    statusSelectors.each(function(idx, ele) {
        var productId = $(ele).parent().parent().data('product-id');
        var statusId = $(ele).val();
        statuses.push({'productId':productId, 'statusId':statusId});
    })
    fields += '&statuses='+JSON.stringify(statuses);
    var url = script_prefix;
    url += products_matching_script_name;
    call_ajax(url,fields,true,finish_apply_new_statuses_to_duplicated,600);
}

function finish_apply_new_statuses_to_duplicated(ajax_request) {
    var status = ajax_request.get_status();
    if (status == 200 || status == 201) {
        showDuplicatedPatientsGrid(matching_current_page, matching_page_size);
    }
    else if ((status >= 202) && (status < 300)) ajax_request.display_error();
}