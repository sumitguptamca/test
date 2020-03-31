/*
        Inroads Control Panel/Shopping Cart - Admin Tab - Export Data JavaScript Functions

                              Written 2009-2019 by Randall Severy
                               Copyright 2009-2019 Inroads, LLC
*/

function export_data()
{
   top.create_dialog('export_data',null,null,460,90,false,
                     script_prefix + 'admin.php?cmd=exportdata',null);
}

function select_format()
{
   var format = get_selected_list_value('Format');
   if ((format == '') || (format == 'sql')) var row_display = 'none';
   else var row_display = '';
   var options_row = document.getElementById('options_row');
   options_row.style.display = row_display;
   top.grow_current_dialog();
}

function process_export()
{
   var format = get_selected_list_value('Format');
   if (format == '') {
      alert('You must select a File Format');   return;
   }
   var table = get_selected_list_value('Table');
   if (table == '') {
      alert('You must select a Database Table');   return;
   }

   document.ExportData.submit();
}

