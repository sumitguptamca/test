/*

            Inroads Shopping Cart - Reviews Tab JavaScript Functions

                       Written 2014-2017 by Randall Severy
                        Copyright 2014-2017 Inroads, LLC
*/

var reviews_grid = null;
var script_prefix = '';
var admin_path;
var filter_where = '';

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(reviews_grid,-1,new_height -
                   get_grid_offset(reviews_grid));
    else resize_grid(reviews_grid,new_width,new_height)
}

function load_grid()
{
    var grid_size = get_default_grid_size();
    reviews_grid = new Grid('reviews',grid_size.width,grid_size.height);
    reviews_grid.set_columns(['ID','Status','Created','Product','First Name',
                              'Last Name','Email','Subject','Rating']);
    reviews_grid.set_column_widths([0,80,70,200,100,100,150,200,50]);
    var query = 'select id,status,create_date,(select name from products ' +
                'where id=parent) as product,firstname,lastname,email,' +
                'subject,rating from reviews';
    reviews_grid.set_query(query);
    reviews_grid.set_order_by('create_date desc');
    reviews_grid.set_id('reviews_grid');
    reviews_grid.table.set_convert_cell_data(convert_review_data);
    reviews_grid.load(false);
    reviews_grid.set_double_click_function(edit_review);
    reviews_grid.display();
}

function reload_grid()
{
    reviews_grid.table.reset_data(true);
    reviews_grid.grid.refresh();
    window.setTimeout(function() { reviews_grid.table.restore_position(); },0);
}

function select_product()
{
    if (document.AddReview) var frame = 'add_review';
    else var frame = 'edit_review';
    if ((typeof(top.current_tab) == 'undefined') &&
       (typeof(admin_path) != 'undefined')) var url = admin_path;
    else var url = script_prefix;
    url += 'products.php?cmd=selectproduct&frame=' + frame;
    top.create_dialog('select_product',null,null,830,400,false,url,null);
}

function change_product(product_data)
{
    if (document.AddReview) var form = document.AddReview;
    else var form = document.EditReview;
    form.parent.value = product_data.id;
    var display_cell = document.getElementById('parent_display');
    display_cell.innerHTML = product_data.name;
    var select_button = document.getElementById('parent_select_button');
    if (select_button) {
       select_button.style.display = 'none';
       var change_button = document.getElementById('parent_change_button');
       change_button.style.display = '';
    }
    top.close_current_dialog();
}

function add_review()
{
    reviews_grid.table.save_position();
    if ((typeof(top.current_tab) == 'undefined') &&
       (typeof(admin_path) != 'undefined')) var url = admin_path;
    else var url = script_prefix;
    url += 'reviews.php?cmd=addreview';
    top.create_dialog('add_review',null,null,680,480,false,url,null);
}

function process_add_review()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('reviews.php','cmd=processaddreview',
                     document.AddReview,finish_add_review);
}

function finish_add_review(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (document.AddReview.frame)
          top.get_dialog_frame(document.AddReview.frame.value).contentWindow.reload_reviews_grid();
       else top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_review()
{
    if (reviews_grid.table._num_rows < 1) {
       alert('There are no reviews to edit');   return;
    }
    var grid_row = reviews_grid.grid.getCurrentRow();
    var id = reviews_grid.grid.getCellText(0,grid_row);
    reviews_grid.table.save_position();
    if ((typeof(top.current_tab) == 'undefined') &&
       (typeof(admin_path) != 'undefined')) var url = admin_path;
    else var url = script_prefix;
    url += 'reviews.php?cmd=editreview&id=' + id;
    top.create_dialog('edit_review',null,null,680,480,false,url,null);
}

function update_review()
{
    top.enable_current_dialog_progress(true);
    submit_form_data('reviews.php','cmd=updatereview',
                     document.EditReview,finish_update_review);
}

function finish_update_review(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (document.EditReview.frame)
          top.get_dialog_frame(document.EditReview.frame.value).contentWindow.reload_reviews_grid();
       else top.get_content_frame().reload_grid();
       top.close_current_dialog();
    }
}

function delete_review()
{
    if (reviews_grid.table._num_rows < 1) {
       alert('There are no reviews to delete');   return;
    }
    var grid_row = reviews_grid.grid.getCurrentRow();
    var id = reviews_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this review?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    reviews_grid.table.save_position();
    if ((typeof(top.current_tab) == 'undefined') &&
       (typeof(admin_path) != 'undefined')) var url = admin_path;
    else var url = script_prefix;
    call_ajax(url + 'reviews.php','cmd=deletereview&id=' + id,true,
              finish_delete_review);
}

function finish_delete_review(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

var status_values = ['Pending','Approved','Disapproved'];

function convert_review_data(col,row,text)
{
    if (col == 1) return status_values[parse_int(text)];
    if (col == 2) {
       if (text == '') return text;
       var review_date = new Date(parse_int(text) * 1000);
       var date_string = (review_date.getMonth() + 1) + '/' +
                         review_date.getDate() + '/' +
                         review_date.getFullYear();
       return date_string;
    }
    return text;
}

function search_reviews()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching Reviews...',350,100,null);
    window.setTimeout(function() {
       var where = "(subject like '%" + query + "%' or firstname like '%" + query +
                   "%' or lastname like '%" + query + "%' or email like '%" + query +
                   "%' or review like '%" + query + "%')";
       if (filter_where) where += ' and ' + filter_where;
       reviews_grid.set_where(where);
       reviews_grid.table.reset_data(false);
       reviews_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    top.display_status('Search','Loading All Reviews...',350,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       var where = '';
       if (filter_where) {
          if (where) where = '(' + where + ') and ';
          where += filter_where;
       }
       reviews_grid.set_where('parent_type=0');
       reviews_grid.table.reset_data(false);
       reviews_grid.grid.refresh();
       top.remove_status();
    },0);
}

function get_review_filter_value(field_name)
{
    var list = document.getElementById(field_name);
    if (! list) return '';
    if (list.selectedIndex == -1) return '';
    return list.options[list.selectedIndex].value;
}

function filter_reviews()
{
    filter_where = '';
    var status = get_review_filter_value('status');
    if (status) {
       if (filter_where) filter_where += ' and ';
       filter_where += '(status=' + status + ')';
    }
    var query = document.SearchForm.query.value;
    if (query) search_reviews();
    else {
       top.display_status('Search','Searching Reviews...',350,100,null);
       window.setTimeout(function() {
          reviews_grid.set_where(filter_where);
          reviews_grid.table.reset_data(false);
          reviews_grid.grid.refresh();
          top.remove_status();
       },0);
    }
}

