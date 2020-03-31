/*
       Inroads Shopping Cart - Amazon Vendor JavaScript Functions

                    Written 2018 by Randall Severy
                     Copyright 2018 Inroads, LLC
*/

function select_amazon_shopping_field()
{
   if (document.AddImport) {
      var form = document.AddImport;   var form_name = 'AddImport';
      var frame = 'add_import';
   }
   else {
      var form = document.EditImport;   var form_name = 'EditImport';
      var frame = 'edit_import';
   }
   var shopping_field = form.amazon_item_type.value;
   var url = top.script_prefix + 'products.php?cmd=shoppingfield&Frame=' +
             frame + '&Field=amazon_item_type&Label=Amazon%20Item%20Type' +
             '&Form=' + form_name + '&Value=' +
             encodeURIComponent(shopping_field);
   top.create_dialog('shopping_field',null,null,1000,500,false,url,null);
}

