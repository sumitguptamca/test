<?php
/*
                     Inroads Shopping Cart - Customer RMAs Tab

                        Written 2015 by Randall Severy
                         Copyright 2015 Inroads, LLC
*/

function add_rmas_tab($dialog)
{
    $dialog->add_tab('rmas_tab','RMAs','rmas_tab',
                     'rmas_content','change_tab',true,null,LAST_TAB);
}

function add_rmas_tab_section($dialog,$id,$db)
{
    global $rma_status_list;

    if (! isset($rma_status_list)) $rma_status_list = RMA_STATUS;
    $status_values = load_cart_options($rma_status_list,$db);
    $dialog->start_tab_content("rmas_content",false);
    if ($dialog->skin)
       $dialog->write("        <div class=\"fieldSection\">\n");
    else $dialog->write("        <div style=\"padding: 4px;\">\n");
    $dialog->write("        <script>\n");
    $dialog->write("          enable_rmas = true;\n");
    $dialog->write("          var rma_status_values = [");
    $max_status = max(array_keys($status_values));
    for ($loop = 0;  $loop <= $max_status;  $loop++) {
       if ($loop > 0) $dialog->write(",");
       if (isset($status_values[$loop]))
          $dialog->write("\"".$status_values[$loop]."\"");
       else $dialog->write("\"\"");
    }
    $dialog->write("];\n");
    $dialog->write("          create_rmas_grid(".$id.");\n");
    $dialog->write("        </script>\n");
    $dialog->write("        </div>\n");
    $dialog->end_tab_content();
}

function add_rma_buttons($dialog)
{
    $dialog->add_button_separator("rma_buttons_row",20);
    $dialog->add_button("View RMA","images/ViewRMA.png",
                        "view_rma();","view_rma",null,false);
}

