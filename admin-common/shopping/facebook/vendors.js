/*
   Inroads Shopping Cart - Facebook Commerce Vendor JavaScript Functions

                    Written 2019 by Randall Severy
                     Copyright 2019 Inroads, LLC
*/

function select_facebook_shopping_field(field_name,field_label)
{
   if (document.AddImport) {
      var form = document.AddImport;   var form_name = 'AddImport';
      var frame = 'add_import';
   }
   else {
      var form = document.EditImport;   var form_name = 'EditImport';
      var frame = 'edit_import';
   }
   var shopping_field = form[field_name].value;
   var url = top.script_prefix + 'products.php?cmd=shoppingfield&Frame=' +
             frame + '&Field=' + field_name + '&Label=' +
             encodeURIComponent(field_label) + '&Form=' + form_name +
             '&Value=' + encodeURIComponent(shopping_field);
   top.create_dialog('shopping_field',null,null,1100,500,false,url,null);
}

