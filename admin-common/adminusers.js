/*
      Inroads Control Panel/Shopping Cart - Admin Tab - Admin Users JavaScript Functions

                         Written 2007-2019 by Randall Severy
                          Copyright 2007-2019 Inroads, LLC
*/

var users_grid = null;
var script_prefix = '';
var admin_user = '';
var prefs_cookie = null;
var user_pref_names = [];
var user_dialog_width;

function resize_screen(new_width,new_height)
{
    if (top.skin) {
       resize_grid(users_grid,-1,new_height - get_grid_offset(users_grid));
       return;
    }
    if (navigator.userAgent.indexOf('MSIE') != -1) {
       new_width -= 42;   new_height -= 52;
    }
    else {
       new_width -= 20;   new_height -= 20;
    }
    resize_grid(users_grid,new_width,new_height);
}

function load_grid()
{
    var grid_size = get_default_grid_size();
    users_grid = new Grid("users",grid_size.width,grid_size.height);
    users_grid.set_columns(["Username","First Name","Last Name",
                            "E-Mail Address","Last Login"]);
    users_grid.set_column_widths([0,150,150,250,100]);
    var query = "select username,firstname,lastname,email,last_login " +
                "from users";
    users_grid.set_query(query);
    users_grid.set_where("username<>'default'");
    users_grid.set_order_by("lastname,firstname");
    users_grid.table.set_convert_cell_data(convert_data);
    users_grid.set_id('users_grid');
    users_grid.load(false);
    users_grid.set_double_click_function(edit_user);
    users_grid.display();
    for (var loop = 0;  loop < users_grid.table._num_rows;  loop++) {
       if (users_grid.grid.getCellText(0,loop) == admin_user) {
          users_grid.grid.setSelectedRows([loop]);
          users_grid.grid.setCurrentRow(loop);
          break;
       }
    }
}

function reload_grid()
{
    users_grid.table.reset_data(false);
    users_grid.grid.refresh();
    window.setTimeout(function() { users_grid.table.restore_position(); },0);
}

function convert_data(col,row,text)
{
    if (col == 4) {
       if (text == '') return text;
       var coupon_date = new Date(parse_int(text) * 1000);
       var date_string = (coupon_date.getMonth() + 1) + "/" + coupon_date.getDate() + "/" +
                         coupon_date.getFullYear();
       return date_string;
    }
    return text;
}

function get_user_fullname()
{
    if (document.AddUser) var form = document.AddUser;
    else var form = document.EditUser;
    var user_fullname = form.firstname.value + ' ' + form.lastname.value;
    return user_fullname;
}

function module_permission_onclick(event,checkbox,module_name)
{
    if (! checkbox.checked) {
       var response = confirm('Removing this permission will delete any ' +
          'data associated with ' + get_user_fullname() + ' in the ' +
          module_name +' area of the system, are you sure you want to ' +
          'remove this permission?');
       if (! response) {
          if (event.stopPropagation) {
             event.preventDefault();   event.stopPropagation();
          }
          else event.cancelBubble = true;
          return false;
       }
    }
    return true;
}

function admin_users()
{
    top.create_dialog('admin_users',null,null,400,220,false,
                      script_prefix + 'adminusers.php?cmd=adminusers',null);
}

function add_user()
{
    if (users_grid) users_grid.table.save_position();
    top.create_dialog('add_user',null,null,user_dialog_width,400,false,
                      script_prefix + 'adminusers.php?cmd=adduser',null);
}

function process_add_user()
{
    if (! validate_form_field(document.AddUser.username,"Username")) return;
    if (! validate_form_field(document.AddUser.lastname,"Last Name")) return;
    if (! validate_form_field(document.AddUser.password,"Password")) return;
    if (document.AddUser.password.value != document.AddUser.ConfirmPassword.value) {
       alert('Password and Confirm Password do not match');   return;
    }

    top.enable_current_dialog_progress(true);
    submit_form_data("adminusers.php","cmd=processadduser",document.AddUser,
                     finish_add_user,300);
}

function finish_add_user(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       var frame = top.get_content_frame();
       if (frame && frame.users_grid && frame.reload_grid) frame.reload_grid();
       else top.reload_dialog('admin_users');
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_user()
{
    if (users_grid) {
       if (users_grid.table._num_rows < 1) {
          alert('There are no users to edit');   return;
       }
       var grid_row = users_grid.grid.getCurrentRow();
       var username = users_grid.grid.getCellText(0,grid_row);
       users_grid.table.save_position();
    }
    else {
       var user_list = document.AdminUsers.UserID;
       if (user_list.selectedIndex == -1) {
          alert('You must select a User');   return;
       }
       var username = user_list.options[user_list.selectedIndex].value;
   }
   top.create_dialog('edit_user',null,null,user_dialog_width,400,false,
                     script_prefix + 'adminusers.php?cmd=edituser&username=' +
                     username,null);
}

function update_user()
{
    if (document.EditUser.username.value != 'default') {
       if (! validate_form_field(document.EditUser.username,"Username"))
          return;
       if (! validate_form_field(document.EditUser.lastname,"Last Name"))
          return;
    }

    top.enable_current_dialog_progress(true);
    submit_form_data("adminusers.php","cmd=updateuser",document.EditUser,
                     finish_update_user,300);
}

function build_prefs_cookie(form)
{
    var prefs_string = '';
    for (var index in user_pref_names) {
       if (prefs_string != '') prefs_string += '|';
       var name = user_pref_names[index];
       var value = form[name].value;
       prefs_string += name + '|' + value;
    }
    return prefs_string;
}

function finish_update_user(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (document.EditUser.username.value == admin_user) {
/*
          var today = new Date();
          var expires_date = new Date(today.getTime() + 86400000);
          document.cookie = prefs_cookie + "=" +
             escape(build_prefs_cookie(document.EditUser)) +
             ";path=/;expires=" + expires_date.toGMTString();
*/
          top.location.reload();
          return;
       }
       var frame = top.get_content_frame();
       if (frame && frame.users_grid && frame.reload_grid) frame.reload_grid();
       else top.reload_dialog('admin_users');
       top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function change_password()
{
    if (users_grid) {
       if (users_grid.table._num_rows < 1) {
          alert('There are no users to change password for');   return;
       }
       var grid_row = users_grid.grid.getCurrentRow();
       var username = users_grid.grid.getCellText(0,grid_row);
    }
    else {
       var user_list = document.AdminUsers.UserID;
       if (user_list.selectedIndex == -1) {
          alert('You must select a User');   return;
       }
       var username = user_list.options[user_list.selectedIndex].value;
   }
   top.create_dialog('change_password',null,null,user_dialog_width,115,false,
                     script_prefix + 'adminusers.php?cmd=changepw&username=' +
                     username,null);
}

function update_password()
{
    if (document.ChangePassword.username.value != 'default') {
       if (! validate_form_field(document.ChangePassword.password,"Password"))
          return;
       if (document.ChangePassword.password.value !=
           document.ChangePassword.ConfirmPassword.value) {
          alert('Password and Confirm Password do not match');   return;
       }
    }

    top.enable_current_dialog_progress(true);
    submit_form_data("adminusers.php","cmd=updatepw",document.ChangePassword,
                     finish_change_password,0);
}

function finish_change_password(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (document.ChangePassword.username.value == admin_user)
          top.location.reload();
       else top.close_current_dialog();
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function delete_user()
{
    if (users_grid) {
       if (users_grid.table._num_rows < 1) {
          alert('There are no users to edit');   return;
       }
       var grid_row = users_grid.grid.getCurrentRow();
       var username = users_grid.grid.getCellText(0,grid_row);
       users_grid.table.save_position();
    }
    else {
       var user_list = document.AdminUsers.UserID;
       if (user_list.selectedIndex == -1) {
          alert('You must select a User');   return;
       }
       var username = user_list.options[user_list.selectedIndex].value;
    }
    var response = confirm('Are you sure you want to delete user "' +
                           username + '"?');
    if (! response) return;
    top.enable_current_dialog_progress(true);
    call_ajax("adminusers.php","cmd=deleteuser&username=" + username,
              true,finish_delete_user,300);
}

function finish_delete_user(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
       if (users_grid) reload_grid();
       else top.reload_dialog('admin_users');
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function edit_defaults()
{
   top.create_dialog('edit_user',null,null,480,350,false,
                     script_prefix +
                     'adminusers.php?cmd=edituser&username=default',null);
}

function check_all()
{
    var fields = document.getElementsByTagName("input");
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == "checkbox") &&
           (fields[loop].name.substr(0,5) == 'perm_') &&
           (! fields[loop].disabled))
          fields[loop].checked = true;
    }
}

function uncheck_all()
{
    if (document.AddUser) var form = document.AddUser;
    else var form = document.EditUser;
    if (form.firstname) {
       var response = confirm('Removing these permissions will delete any ' +
             'data associated with ' + get_user_fullname() +
             ' in the selected areas of the system, are you sure you want ' +
             'to remove these permissions?');
       if (! response) {
          if (event.stopPropagation) {
             event.preventDefault();   event.stopPropagation();
          }
          else event.cancelBubble = true;
          return false;
       }
    }
    var fields = document.getElementsByTagName('input');
    for (var loop = 0;  loop < fields.length;  loop++) {
       if ((fields[loop].type == 'checkbox') &&
           (fields[loop].name.substr(0,5) == 'perm_') &&
           (! fields[loop].disabled))
          fields[loop].checked = false;
    }
    return true;
}

function update_userprefs()
{
    top.enable_current_dialog_progress(true);
    submit_form_data("adminusers.php","cmd=updateuserprefs",document.UserPrefs,
                     finish_update_userprefs);
}

function finish_update_userprefs(ajax_request)
{
    var status = ajax_request.get_status();
    top.enable_current_dialog_progress(false);
    if (status == 201) {
/*
       var today = new Date();
       var expires_date = new Date(today.getTime() + 86400000);
       var cookie_string = prefs_cookie + "=" +
          escape(build_prefs_cookie(document.UserPrefs)) +
          ";path=/;expires=" + expires_date.toGMTString();
       document.cookie = cookie_string;
*/
       top.location.reload();
       return;
    }
    else if ((status >= 200) && (status < 300))
       ajax_request.process_error(ajax_request.request.responseText);
}

function change_tab(row,content_id)
{
    tab_click(row,content_id);
    top.grow_current_dialog();
}

function user_preferences_onload()
{
    change_notify();
}

function change_skin(skin_list)
{
    var skin = skin_list.options[skin_list.selectedIndex].value;
    var pref_button_row = document.getElementById('pref_button_row');
    if (skin == '') {
       pref_button_row.style.display = 'none';
       set_radio_button('buttons','left');
    }
    else pref_button_row.style.display = '';
}

function change_notify()
{
    var notify = get_selected_radio_button('notify');
    var row = document.getElementById('grant_permission');
    if (! row) return;
    if (('Notification' in window) &&
        (window.Notification.permission !== 'granted') && (notify == 'yes'))
       row.style.display = '';
    else row.style.display = 'none';
    top.grow_current_dialog();
}

function grant_notify_permissions()
{
    window.Notification.requestPermission(function (permission) {
       change_notify();
    });
}
