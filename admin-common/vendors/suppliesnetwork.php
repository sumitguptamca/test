<?php
/*
             Inroads Shopping Cart - Supplies Network Vendor Module

                         Written 2019 by Randall Severy
                          Copyright 2019 Inroads, LLC
*/

function suppliesnetwork_install($db)
{
    global $vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
}

function suppliesnetwork_upgrade($db)
{
    global $vendor_product_fields;

    require_once 'catalogconfig-common.php';
    require_once '../admin/office-common.php';

    if (! add_catalog_fields($db,'products','specs',$vendor_product_fields))
       return;
}

function suppliesnetwork_update_catalog_filename(&$catalog_file,&$extension,
                                                 $import_file,$files)
{
    $latest_file = null;   $latest_mtime = 0;
    foreach ($files as $filename => $file_info) {
       if (substr($filename,0,29) != 'eContent_Basic_CommaDelimited') continue;
       if ($file_info['mtime'] > $latest_mtime) {
          $latest_file = $filename;   $latest_mtime = $file_info['mtime'];
       }
    }
    if (! $latest_file) {
       process_import_error('Catalog File not found');   return 'skip';
    }
    $catalog_file = $latest_file;
    return true;
}

function suppliesnetwork_convert_catalog_file(&$import_file,$local_filename,
                                              &$import,$ftp)
{
    $images_dir = '/Product Web Images';
    $dirs = load_ftp_dir($ftp,$import,$images_dir);
    if (! $dirs) {
       $error = 'Unable to get directory list for '.$images_dir;
       process_import_error($error);   return;
    }
    $latest_dir = null;   $latest_mtime = 0;
    foreach ($dirs as $dirname => $dir_info) {
       if ($dir_info['size'] != '<DIR>') continue;
       if ($dir_info['mtime'] > $latest_mtime) {
          $latest_dir = $dirname;   $latest_mtime = $dir_info['mtime'];
       }
    }
    if (! $latest_dir) {
       process_import_error('Unable to find Images Directory');   return;
    }
    $images_dir .= '/'.$latest_dir;
    $files = load_ftp_dir($ftp,$import,$images_dir);
    if (! $files) {
       $error = 'Unable to get directory list for '.$images_dir;
       process_import_error($error);   return;
    }
    $zip_filename = null;
    foreach ($files as $filename => $file_info) {
       if (substr($filename,0,8) == '600x600_') {
          $zip_filename = $filename;   break;
       }
    }
    if (! $zip_filename) {
       process_import_error('Unable to find Images Zip File');   return;
    }
    $local_zip_filename = '../admin/vendors/SuppliesNetworkImages.zip';
    if (file_exists($local_zip_filename) &&
        ($files[$zip_filename]['mtime'] == filemtime($local_zip_filename)) &&
        ($files[$zip_filename]['size'] == filesize($local_zip_filename)))
       return;
    if (gettype($ftp) == 'resource')
       $return = ftp_get($ftp,$local_zip_filename,$zip_filename,FTP_BINARY);
    else $return = $ftp->get($zip_filename,$local_zip_filename);
    if (! $return) {
       $error = 'Unable to download Images Zip File '.$zip_filename;
       process_import_error($error);   return false;
    }
    touch($local_zip_filename,$files[$zip_filename]['mtime']);
    log_vendor_activity('Downloaded New Images Zip File '.$zip_filename .
                        ' to '.$local_zip_filename);
}

?>
