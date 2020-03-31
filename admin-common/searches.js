/*

          Inroads Shopping Cart - Searches Tab JavaScript Functions

                        Written 2014 by Randall Severy
                         Copyright 2014 Inroads, LLC
*/

var searches_grid = null;
var search_engine = null;

function resize_screen(new_width,new_height)
{
    if (top.skin)
       resize_grid(searches_grid,-1,new_height - get_grid_offset(searches_grid));
    else resize_grid(searches_grid,new_width,new_height)
}

function load_grid()
{
    var grid_size = get_default_grid_size();
    searches_grid = new Grid('searches',grid_size.width,grid_size.height);
    searches_grid.set_columns(['ID','Query','IP Address','Date']);
    searches_grid.set_column_widths([0,400,100,140]);
    var query = 'select id,query,ip_address,search_date from searches';
    searches_grid.set_query(query);
    searches_grid.set_order_by('search_date desc');
    searches_grid.table.set_convert_cell_data(convert_search_data);
    searches_grid.load(false);
    searches_grid.display();
}

function delete_search()
{
    if (searches_grid.table._num_rows < 1) {
       alert('There are no searches to delete');   return;
    }
    var grid_row = searches_grid.grid.getCurrentRow();
    var id = searches_grid.grid.getCellText(0,grid_row);
    var response = confirm('Are you sure you want to delete this search?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    var status = call_ajax('../cartengine/searches.php',
                           'cmd=deletesearch&id=' + id,true);
    top.enable_current_dialog_progress(false);
    searches_grid.table.reset_data(true);
    searches_grid.grid.refresh();
}

function convert_search_data(col,row,text)
{
    if (col == 3) {
       if (text == '') return text;
       var search_date = new Date(parse_int(text) * 1000);
       return search_date.format("mm/dd/yyyy hh:MM:ss tt");
    }
    return text;
}

function search_searches()
{
    var query = document.SearchForm.query.value;
    if (query == '') {
       reset_search();   return;
    }
    top.display_status('Search','Searching Searches...',350,100,null);
    window.setTimeout(function() {
       var where = "query like '%" + query + "%'";
       searches_grid.set_where(where);
       searches_grid.table.reset_data(false);
       searches_grid.grid.refresh();
       top.remove_status();
    },0);
}

function reset_search()
{
    top.display_status('Search','Loading All Searches...',350,100,null);
    window.setTimeout(function() {
       document.SearchForm.query.value = '';
       searches_grid.set_where('');
       searches_grid.table.reset_data(false);
       searches_grid.grid.refresh();
       top.remove_status();
    },0);
}

var synonyms_grid;

function synonyms()
{
    top.create_dialog('synonyms',null,null,600,300,false,
                      '../cartengine/searches.php?cmd=synonyms',null);
}

function load_synonyms_grid()
{
    var grid_size = get_default_grid_size();
    synonyms_grid = new Grid("search_synonyms",grid_size.width,
                             grid_size.height);
    synonyms_grid.table.set_field_names([]);
    synonyms_grid.set_columns(["Id","Synonym","Keyword"]);
    synonyms_grid.set_column_widths([0,200,200]);
    var query = "select id,synonym,keyword from search_synonyms";
    synonyms_grid.table.set_query(query);
    synonyms_grid.table.set_order_by("synonym");
    synonyms_grid.set_id('synonyms_grid');
    synonyms_grid.load(false);
    synonyms_grid.set_double_click_function(edit_synonym);
    synonyms_grid.display();
}

function reload_synonyms_grid()
{
    synonyms_grid.table.reset_data(false);
    synonyms_grid.grid.refresh();
    window.setTimeout(function() { synonyms_grid.table.restore_position(); },0);
}

function add_synonym()
{
    synonyms_grid.table.save_position();
    top.create_dialog('add_synonym',null,null,500,90,false,
                      '../cartengine/searches.php?cmd=addsynonym',null);
}

function process_add_synonym()
{
    if (! validate_form_field(document.AddSynonym.synonym,"Synonym")) return;
    if (! validate_form_field(document.AddSynonym.keyword,"Keyword")) return;

    top.enable_current_dialog_progress(true);
    submit_form_data("../cartengine/searches.php","cmd=processaddsynonym",
                     document.AddSynonym,finish_add_synonym);
}

function finish_add_synonym(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('synonyms').contentWindow.reload_synonyms_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_synonym()
{
    if (synonyms_grid.table._num_rows < 1) {
       alert('There are no Synonyms to edit');   return;
    }
    var grid_row = synonyms_grid.grid.getCurrentRow();
    var id = synonyms_grid.grid.getCellText(0,grid_row);
    synonyms_grid.table.save_position();
    top.create_dialog('edit_synonym',null,null,480,90,false,
                      '../cartengine/searches.php?cmd=editsynonym&id=' + id,null);
}

function update_synonym()
{
    if (! validate_form_field(document.EditSynonym.synonym,"Synonym")) return;
    if (! validate_form_field(document.EditSynonym.keyword,"Keyword")) return;

    top.enable_current_dialog_progress(true);
    submit_form_data("../cartengine/searches.php","cmd=updatesynonym",
                     document.EditSynonym,finish_update_synonym);
}

function finish_update_synonym(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       top.get_dialog_frame('synonyms').contentWindow.reload_synonyms_grid();
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_synonym()
{
    if (synonyms_grid.table._num_rows < 1) {
       alert('There are no Synonyms to delete');   return;
    }
    var grid_row = synonyms_grid.grid.getCurrentRow();
    var id = synonyms_grid.grid.getCellText(0,grid_row);
    var synonym = synonyms_grid.grid.getCellText(1,grid_row);
    var response = confirm('Are you sure you want to delete the Synonym "' +
                           synonym + '"?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax("../cartengine/searches.php","cmd=deletesynonym&id=" + id,
              true,finish_delete_synonym);
}

function finish_delete_synonym(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) reload_synonyms_grid();
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_ad()
{
    top.using_admin_top = true;
    var close_funct = 'top.get_content_frame().close_edit_ad();'
    var cancel_funct = 'top.get_content_frame().cancel_edit_ad();'
    var filename = '/catalogengine/search-engines/' + search_engine +
                   '/ad.html';
    var url = top.cms_base_url + '?SmartEdit=WSDLiveBody&FullPage=false' +
              '&Filename=' + filename + '&EditOnlineType=WYSIWYG' +
              '&_UpdateCloseFunction=' + close_funct +
              '&_CancelFunction=' + cancel_funct +
              '&_iFrameDialog=Yes&iFrameTime=' + (new Date()).getTime();
    top.create_dialog('edit_ad',null,null,920,400,false,url,null);
}

function close_edit_ad()
{
    top.close_current_dialog();
    top.using_admin_top = false;
}

function cancel_edit_ad()
{
    window.setTimeout(function() {
       top.using_admin_top = false;
    },0);
}

