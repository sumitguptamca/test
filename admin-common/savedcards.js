/*
           Inroads Shopping Cart - Customer Credit Cards Tab JavaScript Functions

                           Written 2015 by Randall Severy
                            Copyright 2015 Inroads, LLC
*/

var cards_grid = null;

function create_saved_cards_grid(parent)
{
    if (top.skin) var grid_width = -100;
    else var grid_width = 500;
    cards_grid = new Grid('saved_cards',grid_width,250);
    cards_grid.set_columns(['Id','Card Number','Exp Month','Exp Year',
                            'Name on Card']);
    cards_grid.set_column_widths([0,150,75,75,200]);
    var query = 'select id,card_number,card_month,card_year,card_name ' +
                'from saved_cards';
    cards_grid.set_query(query);
    cards_grid.set_where('parent=' + parent);
    cards_grid.set_order_by('card_year desc,card_month desc');
    cards_grid.table.set_convert_cell_data(convert_card_data);
    cards_grid.set_id('cards_grid');
    cards_grid.load(true);
    cards_grid.set_double_click_function(edit_card);
    cards_grid.display();
}

function reload_cards_grid()
{
    cards_grid.table.reset_data(true);
    cards_grid.grid.refresh();
    window.setTimeout(function() { cards_grid.table.restore_position(); },0);
}

function convert_card_data(col,row,text)
{
    return text;
}

function enable_card_buttons(enable_flag)
{
    var add_card_button = document.getElementById('add_card');
    var edit_card_button = document.getElementById('edit_card');
    var delete_card_button = document.getElementById('delete_card');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    add_card_button.style.display = display_style;
    edit_card_button.style.display = display_style;
    delete_card_button.style.display = display_style;
}

function change_cards_tab(tab,content_id)
{
    var show_sep = false;
    if (content_id == 'cards_content') {
       enable_card_buttons(true);   show_sep = true;
    }
    else enable_card_buttons(false);
    var cards_buttons_row = document.getElementById('cards_buttons_row');
    if (cards_buttons_row) {
       if (show_sep) cards_buttons_row.style.display = '';
       else cards_buttons_row.style.display = 'none';
    }
}

function add_card()
{
    var customer_id = document.EditCustomer.id.value;
    top.create_dialog('add_card',null,null,600,500,false,
                      '../cartengine/customers.php?cmd=addsavedcard&parent=' +
                      customer_id,null);
}

function process_add_card()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('customers.php','cmd=processaddsavedcard',
                     document.AddCard,finish_add_card);
}

function finish_add_card(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('edit_customer').contentWindow;
       iframe.reload_cards_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function edit_card()
{
    if (cards_grid.table._num_rows < 1) {
       alert('There are no Credit Cards to edit');   return;
    }
    var grid_row = cards_grid.grid.getCurrentRow();
    var id = cards_grid.grid.getCellText(0,grid_row);
    top.create_dialog('edit_card',null,null,600,500,false,
                      '../cartengine/customers.php?cmd=editsavedcard&id=' + id,
                      null);
}

function update_card()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('customers.php','cmd=updatesavedcard',document.EditCard,
                     finish_update_card);
}

function finish_update_card(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var iframe = top.get_dialog_frame('edit_customer').contentWindow;
       iframe.reload_cards_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function delete_card()
{
    if (cards_grid.table._num_rows < 1) {
       alert('There are no Credit Cards to delete');   return;
    }
    var grid_row = cards_grid.grid.getCurrentRow();
    var id = cards_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete the ' +
                           'selected credit card?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax('customers.php','cmd=deletesavedcard&id=' + id,true,
              finish_delete_card);
}

function finish_delete_card(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_cards_grid();
    else if ((status >= 200) && (status < 300)) ajax_request.display_error();
}

function select_country(country_list)
{
    var country = 0;
    if (country_list.selectedIndex != -1)
       country = country_list.options[country_list.selectedIndex].value;
    var city_prompt = document.getElementById('city_prompt');
    var state_row = document.getElementById('state_row');
    var province_row = document.getElementById('province_row');
    var canada_province_row = document.getElementById('canada_province_row');
    var zip_cell = document.getElementById('zip_cell');
    if (country == 1) {
       state_row.style.display = '';   province_row.style.display = 'none';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Zip Code:';
    }
    else if (country == 29) {
       state_row.style.display = 'none';   province_row.style.display = 'none';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'Parish:';   zip_cell.innerHTML = 'Postal Code:';
    }
    else if (country == 43) {
       state_row.style.display = 'none';   province_row.style.display = 'none';
       canada_province_row.style.display = '';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Postal Code:';
    }
    else {
       state_row.style.display = 'none';   province_row.style.display = '';
       canada_province_row.style.display = 'none';
       city_prompt.innerHTML = 'City:';   zip_cell.innerHTML = 'Postal Code:';
    }
}

