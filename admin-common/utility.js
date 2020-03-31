/*
               Inroads Shopping Cart - Utility JavaScript Functions

                        Written 2010-2012 by Randall Severy
                         Copyright 2010-2012 Inroads, LLC
*/

function update_server_filename()
{
   var filename = document.UploadFile.Filename.value;
   var server_field = document.UploadFile.ServerFilename;
   if ((server_field.value == '') && (filename != '')) {
      var slash_pos = filename.lastIndexOf('/');
      if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
      var slash_pos = filename.lastIndexOf('\\');
      if (slash_pos != -1) filename = filename.substring(slash_pos + 1);
      filename = filename.replace(/&|;|`|'|\\|"|\||\*|\?|<|>|\^|\[|\]|\{|\}|\n|\r| /g,'');
      server_field.value = filename;
   }
}

function validate_server_filename()
{
   var server_field = document.UploadFile.ServerFilename;
   if (/&|;|`|'|\\|"|\||\*|\?|<|>|\^|\[|\]|\{|\}|\n|\r/.test(server_field.value))
      return false;
   return true;
}

function process_upload_file()
{
   var filename = document.UploadFile.Filename.value;
   if (filename == '') {
      alert('You must select a File to Upload');   return;
   }
   update_server_filename();
   if (! validate_server_filename()) {
      document.UploadFile.ServerFilename.focus();
      alert('Invalid characters in Server Filename');   return;
   }
   top.enable_current_dialog_progress(true);
   document.UploadFile.submit();
}

function check_all_websites(field_suffix)
{
   var fields = document.getElementsByTagName('input');
   for (var loop = 0;  loop < fields.length;  loop++) {
      if ((fields[loop].type == 'checkbox') &&
          (fields[loop].name.substring(0,8) == 'website_') &&
          (! fields[loop].disabled)) {
         if (field_suffix &&
             (fields[loop].name.substr(-field_suffix.length) != field_suffix))
            continue;
         fields[loop].checked = true;
      }
   }
}

function uncheck_all_websites(field_suffix)
{
   var fields = document.getElementsByTagName('input');
   for (var loop = 0;  loop < fields.length;  loop++) {
      if ((fields[loop].type == 'checkbox') &&
          (fields[loop].name.substring(0,8) == 'website_') &&
          (! fields[loop].disabled)) {
         if (field_suffix &&
             (fields[loop].name.substr(-field_suffix.length) != field_suffix))
            continue;
         fields[loop].checked = false;
      }
   }
}

