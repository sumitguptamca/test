/*

           Inroads Shopping Cart - Accounts Tab JavaScript Functions

                        Written 2011-2018 by Randall Severy
                         Copyright 2011-2018 Inroads, LLC
*/

var accounts_grid = null;
var cancel_add_account = true;
var main_grid_flag;
var products_grid = null;
var inventory_grid = null;
var account_label = '';
var accounts_label = '';
var filter_where = '';
var account_product_prices = false;
var on_account_products = false;
var on_account_column;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(accounts_grid,-1,new_height -
                   get_grid_offset(accounts_grid));
    else resize_grid(accounts_grid,new_width,new_height)
}

/*
function resize_dialog(new_width,new_height)
{
    if (! products_grid) return;
    if (top.skin)
       resize_grid(products_grid,-1,new_height - get_grid_offset(products_grid));
    else resize_grid(products_grid,new_width,new_height)
}
*/

function load_grid(main_screen_flag)
{
    main_grid_flag = main_screen_flag;
    var grid_size = get_default_grid_size();
    accounts_grid = new Grid('accounts',grid_size.width,grid_size.height);
    if (main_screen_flag) {
       if (typeof(accounts_columns) != 'undefined')
          accounts_grid.set_columns(accounts_columns);
       else accounts_grid.set_columns(['Id','Name','Company','City','State',
               'Zip','Country','Phone','Email Address','Status']);
       if (typeof(accounts_column_widths) != 'undefined')
          accounts_grid.set_column_widths(accounts_column_widths);
       else accounts_grid.set_column_widths([0,200,180,90,50,45,80,80,200,50]);
       if (typeof(accounts_query) != 'undefined') var query = accounts_query;
       else var query = 'select a.id,a.name,a.company,a.city,a.state,a.zipcode,' +
                   '(select country from countries c where c.id=a.country) as ' +
                   'country,a.phone,a.email,a.status from accounts a';
    }
    else {
       accounts_grid.set_columns(['Id','Name','City','State','Status','Company',
                                  'Address1','Address2','Zip','Country']);
       accounts_grid.set_column_widths([0,200,90,50,50,00,0,0,0,0]);
       var query = 'select a.id,a.name,a.city,a.state,a.status,a.company,a.address1,' +
                   'a.address2,a.zipcode,(select code from countries c where ' +
                   'c.id=a.country) as country from accounts a';
    }
    accounts_grid.set_query(query);
    if (account_where) accounts_grid.set_where(account_where);
    if (typeof(accounts_info_query) == 'undefined')
       accounts_info_query = 'select count(a.id) from accounts a';
    accounts_grid.set_info_query(accounts_info_query);
    accounts_grid.set_order_by('a.name');
    if (typeof(custom_convert_account_data) != 'undefined')
       accounts_grid.table.set_convert_cell_data(custom_convert_account_data);
    else accounts_grid.table.set_convert_cell_data(convert_account_data);
    accounts_grid.load(true);
    if (main_screen_flag)
       accounts_grid.set_double_click_function(edit_account);
    else accounts_grid.set_double_click_function(select_account);
    accounts_grid.display();
}

function reload_grid()
{
    accounts_grid.table.reset_data(true);
    accounts_grid.grid.refresh();
    window.setTimeout(function() { accounts_grid.table.restore_position(); },0);
}

function add_account()
{
    cancel_add_account = true;
    top.enable_current_dialog_progress(true);
    var ajax_request = new Ajax('../cartengine/accounts.php','cmd=createaccount',true);
    ajax_request.enable_alert();
    ajax_request.enable_parse_response();
    ajax_request.set_callback_function(continue_add_account,null);
    ajax_request.set_timeout(30);
    ajax_request.send();
}

function continue_add_account(ajax_request,ajax_data)
{
    if (ajax_request.state != 4) return;

    top.enable_current_dialog_progress(false);
    var status = ajax_request.get_status();
    if (status != 200) return;

    var account_id = -1;
    eval(ajax_request.request.responseText);

    accounts_grid.table.save_position();
    top.create_dialog('add_account',null,null,800,415,false,
                      '../cartengine/accounts.php?cmd=addaccount&id=' +
                      account_id,null);
}

function add_account_onclose(user_close)
{
    if (cancel_add_account) {
       var account_id = document.AddAccount.id.value;
       call_ajax('accounts.php','cmd=deleteaccount&cancel=true&id=' +
                 account_id,true);
    }
}

function add_account_onload()
{
    top.set_current_dialog_onclose(add_account_onclose);
}

function process_add_account()
{
    if (! validate_form_field(document.AddAccount.name,'Name')) return;
    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/accounts.php','cmd=processaddaccount',
                     document.AddAccount,finish_add_account);
}

function finish_add_account(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       cancel_add_account = false;
       if (products_grid) products_grid.table.process_updates(false);
       if (inventory_grid) inventory_grid.table.process_updates(false);
       top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_account()
{
    if (accounts_grid.table._num_rows < 1) {
       alert('There are no '+accounts_label.toLowerCase()+' to edit');   return;
    }
    var grid_row = accounts_grid.grid.getCurrentRow();
    var id = accounts_grid.grid.getCellText(0,grid_row);
    accounts_grid.table.save_position();
    top.create_dialog('edit_account',null,null,800,415,false,
                      '../cartengine/accounts.php?cmd=editaccount&id=' + id,
                      null);
}

function validate_product_prices()
{
    if (products_grid.table._num_rows < 1) return true;
    var col = products_grid.grid.getCurrentColumn();
    var row = products_grid.grid.getCurrentRow();
    var text = products_grid.grid.getCellText(col,row);
    products_grid.table.get_row_data(row);
    var compare_text = products_grid.table._row_data[col];
    if (text != compare_text)
       products_grid.table.process_cell_change(products_grid.table,
                                               text,col,row);
     var updates = products_grid.table._updates;
     for (var update_row in updates) {
        products_grid.table.get_row_data(update_row);
        if (typeof(updates[update_row][3]) != 'undefined')
           var price = updates[update_row][3];
        else var price = products_grid.table._row_data[3];
        if (typeof(updates[update_row][4]) != 'undefined')
           var discount = updates[update_row][4];
        else var discount = products_grid.table._row_data[4];
        if ((price !== '') && (discount !== '')) {
           alert('You can not specify both a Price AND a Discount Rate ' +
                 'for a Product');
           products_grid.table.set_current_cell(3,update_row);
           return false;
        }
     }
     return true;
}

function update_account()
{
    if (! validate_form_field(document.EditAccount.name,'Name')) return;
    if (products_grid && (account_product_prices === 'both') &&
        (! validate_product_prices())) return;

    top.enable_current_dialog_progress(true);
    submit_form_data('../cartengine/accounts.php','cmd=updateaccount',
                     document.EditAccount,finish_update_account);
}

function finish_update_account(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (products_grid) products_grid.table.process_updates(false);
       if (inventory_grid) inventory_grid.table.process_updates(false);
       top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function copy_account()
{
    if (accounts_grid.table._num_rows < 1) {
       alert('There are no '+accounts_label.toLowerCase()+' to copy');   return;
    }
    var grid_row = accounts_grid.grid.getCurrentRow();
    var id = accounts_grid.grid.getCellText(0,grid_row);
    var name = accounts_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to copy the '+name+' ' +
                           account_label.toLowerCase()+'?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    accounts_grid.table.save_position();
    call_ajax('../cartengine/accounts.php','cmd=copyaccount&id=' + id,true,
              finish_copy_account,60);
}

function finish_copy_account(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_account()
{
    if (accounts_grid.table._num_rows < 1) {
       alert('There are no '+accounts_label.toLowerCase()+' to delete');   return;
    }
    var grid_row = accounts_grid.grid.getCurrentRow();
    var id = accounts_grid.grid.getCellText(0,grid_row);
    var name = accounts_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the '+name+' ' +
                           accounts_label.toLowerCase()+'?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    accounts_grid.table.save_position();
    call_ajax('../cartengine/accounts.php','cmd=deleteaccount&id=' + id,true,
              finish_delete_account);
}

function finish_delete_account(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function enable_product_buttons(enable_flag)
{
    var row = document.getElementById('account_row_sep');
    var add_button = document.getElementById('add_account_product');
    var delete_button = document.getElementById('delete_account_product');
    if (enable_flag) var display_style = '';
    else var display_style = 'none';
    if (row) row.style.display = display_style;
    if (add_button) add_button.style.display = display_style;
    if (delete_button) delete_button.style.display = display_style;
}

function change_tab(tab,content_id)
{
    if (content_id == 'products_content') enable_product_buttons(true);
    else enable_product_buttons(false);
    tab_click(tab,content_id);
    top.grow_current_dialog();
}

function convert_account_data(col,row,text)
{
    if (((col == 9) && main_grid_flag) ||
        ((col == 4) && (! main_grid_flag)))
       return account_status_values[parse_int(text)];
    return text;
}

function search_accounts()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching '+accounts_label+'...',
                       350,100,null);
    window.setTimeout(function() {
       var where = '(a.name like "%' + query + '%") or ' +
                   '(a.email like "%' + query + '%") or ' +
                   '(a.company like "%' + query + '%") or ' +
                   '(a.id like "%' + query + '%")';
       if (filter_where) {
          if (where) where = '(' + where + ') and ';
          where += filter_where;
       }
       accounts_grid.set_where(where);
       accounts_grid.table.reset_data(true);
       accounts_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    top.display_status('Search','Loading All '+accounts_label+'...',
                       350,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       var where = '';
       if (filter_where) {
          if (where) where = '(' + where + ') and ';
          where += filter_where;
       }
       accounts_grid.set_where('');
       accounts_grid.table.reset_data(true);
       accounts_grid.grid.refresh();
       top.remove_status();
    },0);
}

function filter_accounts()
{
    filter_where = '';
    var status_list = document.getElementById('status');
    if (status_list.selectedIndex == -1) var status = null;
    else var status = status_list.options[status_list.selectedIndex].value;
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(status=' + status + ')';
    }
    var query = document.SearchForm.query.value;
    if (query) search_accounts();
    else {
       top.display_status('Search','Searching '+accounts_label+'...',
                          350,100,null);
       window.setTimeout(function() {
          accounts_grid.set_where(filter_where);
          accounts_grid.table.reset_data(true);
          accounts_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

function create_products_grid(account_id,use_inventory)
{
    if (use_inventory) var grid_height = 300;
    else var grid_height = 350;
    products_grid = new Grid('account_products',640,grid_height);
    var columns = ['Id','Product ID','Product'];
    var field_names = ['id','related_id','name'];
    var column_widths = [0,0,605];
    var query = 'select id,related_id,(select name from products where ' +
                'products.id=account_products.related_id) as name';
    var num_columns = 3;
    if (! use_inventory) {
       if (account_product_prices === 'both') {
          columns.push('Price&nbsp;&nbsp;or');
          columns.push('Discount Rate (%)');
          field_names.push('price');
          field_names.push('discount');
          column_widths[2] -= 170;
          column_widths.push(60);
          column_widths.push(110);
          query += ',price,discount';
          num_columns += 2;
       }
       else {
          if (account_product_prices) var discount_label = 'Price';
          else var discount_label = 'Discount Rate (%)';
          columns.push(discount_label);
          field_names.push('discount');
          column_widths[2] -= 110;
          column_widths.push(110);
          query += ',discount';
          num_columns++;
       }
       if (on_account_products) {
          columns.push('On Account');
          field_names.push('on_account');
          column_widths[2] -= 75;
          column_widths.push(75);
          query += ',on_account';
          on_account_column = num_columns;
          num_columns++;
          products_grid.table.set_convert_cell_data(convert_on_account_data);
          products_grid.table.set_convert_cell_update(convert_on_account_update);
       }
    }
    products_grid.set_columns(columns);
    products_grid.set_field_names(field_names);
    products_grid.set_column_widths(column_widths);
    query += ' from account_products';
    products_grid.set_query(query);
    var where = 'parent=' + account_id;
    products_grid.set_where(where);
    products_grid.set_order_by('name');
    products_grid.set_id('products_grid');
    if (! use_inventory) {
       products_grid.table._url = 'accounts.php';
       products_grid.table.add_update_parameter('cmd','updateaccountproduct');
       products_grid.table.add_update_parameter('parent',account_id);
    }
    products_grid.load(true);
    if (! use_inventory) {
       products_grid.grid.setCellEditable(true,3);
       if (account_product_prices === 'both')
          products_grid.grid.setCellEditable(true,4);
       products_grid.grid.setSelectionMode('single-cell');
       products_grid.grid.setVirtualMode(false);
       set_grid_navigation(products_grid.grid);
    }
    else products_grid.grid.onCurrentRowChanged = select_product;
    if ((! use_inventory) && on_account_products) {
       var checkbox_template = new AW.Templates.Checkbox;
       var checkbox = new AW.HTML.SPAN;
       checkbox.setContent('html',function() { return ''; });
       checkbox_template.setContent('box/text',checkbox);
       products_grid.grid.setCellTemplate(checkbox_template,on_account_column);
    }
    products_grid.display();
}

var next_grid_row = 0;

function add_account_product()
{
    if (document.AddAccount) var frame = 'add_account';
    else var frame = 'edit_account';
    top.create_dialog('select_product',null,null,830,400,false,
                      '../cartengine/products.php?cmd=selectproduct&frame=' +
                      frame+'&multiple=true',null);
    next_grid_row = products_grid.table._num_rows;
}

function change_product(product_data)
{
    var row_data = [];
    row_data[0] = '';   row_data[1] = product_data.id;
    row_data[2] = product_data.name;   row_data[3] = '';
    if (account_product_prices === 'both') row_data[4] = '';
    products_grid.table.add_row(row_data);
}

function finish_change_product()
{
    products_grid.grid.focus_cell(3,next_grid_row);
    if (inventory_grid) select_product(next_grid_row);
}

function delete_account_product()
{
    if (products_grid.table._num_rows < 1) {
       alert('There are no products to delete');   return;
    }
    products_grid.table.delete_row();
}

function convert_on_account_data(col,row,text)
{
   if (col == on_account_column) {
      if (parse_int(text) == 1) return true;
      else return false;
   }
   return text;
}

function convert_on_account_update(col,row,value)
{
   if (col == on_account_column) {
      if (value) new_value = 1;
      else new_value = 0;
      return new_value;
   }
   return value;
}

function set_inventory_query(account_id,product_id)
{
    var query = 'select ai.id,i.id as related_id,i.part_number,ai.discount,' +
       'i.sequence from account_inventory ai join product_inventory i on ' +
       'i.id=ai.related_id where (ai.parent=' + account_id + ') and ' +
       '(i.parent=' + product_id + ') union select null,id as related_id,' +
       'part_number,null,sequence from product_inventory where (parent=' +
       product_id + ') and (id not in (select related_id from ' +
       'account_inventory where parent=' + account_id + '))';
    inventory_grid.set_query(query);
}

function create_inventory_grid(account_id,product_id)
{
    inventory_grid = new Grid('account_inventory',440,300);
    if (account_product_prices) var discount_label = 'Price';
    else var discount_label = 'Discount Rate (%)';
    inventory_grid.set_columns(['Id','InvID','Part Number',discount_label,
                                'Sequence']);
    inventory_grid.set_field_names(['id','related_id','part_number','discount',
                                    'sequence']);
    inventory_grid.set_column_widths([0,0,295,110,0]);
    set_inventory_query(account_id,product_id);
    inventory_grid.set_order_by('sequence');
    inventory_grid.set_id('inventory_grid');
    inventory_grid.table._url = 'accounts.php';
    inventory_grid.table.add_update_parameter('cmd','updateaccountinventory');
    inventory_grid.table.add_update_parameter('parent',account_id);
    inventory_grid.load(true);
    inventory_grid.grid.setCellEditable(true,3);
    inventory_grid.grid.setSelectionMode('single-cell');
    inventory_grid.grid.setVirtualMode(false);
    set_grid_navigation(inventory_grid.grid);
    inventory_grid.display();
}

function select_product(row)
{
    if (document.AddAccount) var account_id = document.AddAccount.id.value;
    else var account_id = document.EditAccount.id.value;
    var product_id = products_grid.grid.getCellText(1,row);
    set_inventory_query(account_id,product_id);
    inventory_grid.table.reset_data(true);
    inventory_grid.grid.refresh();
}

function select_account()
{
    if (accounts_grid.table._num_rows < 1) {
       alert('There are no accounts to select');   return;
    }
    var grid_row = accounts_grid.grid.getCurrentRow();
    var id = accounts_grid.grid.getCellText(0,grid_row);
    var name = accounts_grid.grid.getCellText(1,grid_row);
    var company = accounts_grid.grid.getCellText(5,grid_row);
    var address1 = accounts_grid.grid.getCellText(6,grid_row);
    var address2 = accounts_grid.grid.getCellText(7,grid_row);
    var city = accounts_grid.grid.getCellText(2,grid_row);
    var state = accounts_grid.grid.getCellText(3,grid_row);
    var zip = accounts_grid.grid.getCellText(8,grid_row);
    var country = accounts_grid.grid.getCellText(9,grid_row);
    var iframe = top.get_dialog_frame(select_frame).contentWindow;
    var account_data = name + '<br>' + company + '<br>' + address1 + '<br>';
    if (address2) account_data += address2 + '<br>';
    account_data += city + ', ' + state + ' ' + zip + ' ' + country;
    iframe.change_account(id,account_data);
}

function select_country(country_list)
{
    var country = 0;
    if (country_list.selectedIndex != -1)
       country = country_list.options[country_list.selectedIndex].value;
    var state_row = document.getElementById('state_row');
    var province_row = document.getElementById('province_row');
    var zip_cell = document.getElementById('zip_cell');
    if (country == 1) {
       state_row.style.display = '';   province_row.style.display = 'none';
       zip_cell.innerHTML = 'Zip Code:';
    }
    else {
       state_row.style.display = 'none';   province_row.style.display = '';
       zip_cell.innerHTML = 'Postal Code:';
    }
}

