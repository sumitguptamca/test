/*
           Inroads Shopping Cart - Chase Paymentech API Module JavaScript Functions

                        Written 2009-2018 by Randall Severy
                         Copyright 2009-2018 Inroads, LLC
*/

function enable_chase_config_rows(prefix,num_rows,enable_flag)
{
   for (var loop = 0;  loop < num_rows;  loop++) {
      var row = document.getElementById(prefix + '_' + loop);
      if (! row) continue;
      if (enable_flag) row.style.display = '';
      else row.style.display = 'none';
   }
}

function select_chase()
{
   var chase_interface = get_selected_radio_button('chase_interface');
   if (chase_interface == 0) {
      enable_chase_config_rows('chase_0',5,true);
      enable_chase_config_rows('chase_1',5,false);
   }
   else {
      enable_chase_config_rows('chase_0',5,false);
      enable_chase_config_rows('chase_1',5,true);
   }
}

