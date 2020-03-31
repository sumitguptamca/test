<?php
/*
              Inroads Control Panel/Shopping Cart - Main Tab Functions

                        Written 2016-2019 by Randall Severy
                         Copyright 2016-2019 Inroads, LLC
*/

$main_tabs = array();
$main_tab_order = array();
$main_tab_defaults = array();

function add_main_tab($new_tab,$label,$url,$perm,$perm_type,$before_order=null,
   $before_default=null,$use_cover=true,$width=null,$click_function=null,
   $level=0,$add_before=true,$reload_tab=true)
{
    global $main_tabs,$main_tab_order,$main_tab_defaults,$cms_available;

    $main_tabs[$new_tab] = array($label,$url,$perm,$perm_type,$use_cover,
                                 $width,$click_function,$level,$reload_tab);
    if ($before_order == null) $main_tab_order[] = $new_tab;
    else {
       if (($before_order == 'admin') && isset($cms_available) &&
           $cms_available && ($new_tab != 'cms')) $before_order = 'cms';
       $insert_pos = -1;   $index = 0;
       $current_level = -1;
       foreach ($main_tab_order as $tab_index => $tab_name) {
          if ($main_tabs[$tab_name][7] <= $current_level) {
             $insert_pos = $index;   break;
          }
          if ($tab_name == $before_order) {
             if ($add_before) $insert_pos = $index;
             else if ($level > $main_tabs[$tab_name][7]) {
                $current_level = $main_tabs[$tab_name][7];
                $index++;   continue;
             }
             else $insert_pos = $index + 1;
             break;
          }
          else $index++;
       }
       if ($insert_pos == -1) $main_tab_order[] = $new_tab;
       else array_splice($main_tab_order,$insert_pos,0,array($new_tab));
    }
    if ($before_default == null) $main_tab_defaults[] = $new_tab;
    else {
       $insert_pos = -1;   $index = 0;
       $current_level = -1;
       foreach ($main_tab_defaults as $tab_index => $tab_name) {
          if ($main_tabs[$tab_name][7] == $current_level) {
             $insert_pos = $index;   break;
          }
          if ($tab_name == $before_default) {
             if ($add_before) $insert_pos = $index;
             else if ($level > $main_tabs[$tab_name][7]) {
                $current_level = $main_tabs[$tab_name][7];
                $index++;   continue;
             }
             else $insert_pos = $index + 1;
             break;
          }
          else $index++;
       }
       if ($insert_pos == -1) $main_tab_defaults[] = $new_tab;
       else array_splice($main_tab_defaults,$insert_pos,0,$new_tab);
    }
}

function remove_main_tab($tab)
{
    global $main_tabs,$main_tab_order,$main_tab_defaults;

    unset($main_tabs[$tab]);
    foreach ($main_tab_order as $index => $tab_name)
       if ($tab_name == $tab) {
          array_splice($main_tab_order,$index,1);   break;
       }
    foreach ($main_tab_defaults as $index => $tab_name)
       if ($tab_name == $tab) {
          array_splice($main_tab_defaults,$index,1);   break;
       }
}

function move_main_tab($tab,$before_order=null,$before_default=null,$level=0,
                       $add_before=true)
{
    global $main_tabs;

    if (! isset($main_tabs[$tab])) return;
    $tab_info = $main_tabs[$tab];
    remove_main_tab($tab);
    add_main_tab($tab,$tab_info[0],$tab_info[1],$tab_info[2],$tab_info[3],
                 $before_order,$before_default,$tab_info[4],$tab_info[5],
                 $tab_info[6],$level,$add_before,$tab_info[8]);
}

function set_tab_label($tab,$label)
{
    global $main_tabs;

    if (! isset($main_tabs[$tab])) return;
    $main_tabs[$tab][0] = $label;
}

function load_wordpress_engine_tabs()
{
    global $prefs_cookie;

    $current_dir = getcwd();
    chdir('../engine/wordpress');
    require_once 'wp-content/engine/wpengine.php';
    $wpengine = new WPEngine();
    $menu = $wpengine->load_admin_menu();
    foreach ($menu as $menu_id => $menu_info) {
       if ($menu_id == 'settings') continue;
       $url = '../engine/wordpress/wp-admin/'.$menu_info['url'];
       if (isset($prefs_cookie)) {
          if (isset($menu_info['submenu'])) $url = null;
          add_main_tab($menu_id,$menu_info['label'],$url,0,0,'plugins_tab',
                       null,false,null,null,1,false);
          if (isset($menu_info['submenu'])) {
             foreach ($menu_info['submenu'] as $index => $submenu_item) {
                $submenu_id = $menu_id.'-'.$index;
                $url = '../engine/wordpress/wp-admin/'.$submenu_item['url'];
                add_main_tab($submenu_id,$submenu_item['label'],$url,0,0,
                             $menu_id,null,false,null,null,2,false);
             }
          }
       }
       else add_main_tab($menu_id,$menu_info['label'],$url,0,0,'admin',
                         'templates',false,null);
    }
    if (! empty($menu['settings'])) {
       foreach ($menu['settings'] as $menu_id => $menu_info) {
          $menu_id .= '-settings';
          $url = '../engine/wordpress/wp-admin/'.$menu_info['url'];
          if (isset($prefs_cookie))
             add_main_tab($menu_id,$menu_info['label'],$url,0,0,'system_tab',
                          null,false,null,null,1,false);
          else add_main_tab($menu_id,$menu_info['label'],$url,0,0,'admin',
                            'templates',false,null);
       }
    }
    chdir($current_dir);
}

function load_main_tabs($db,$user_perms,$module_perms,$custom_perms,
                        $user_prefs,$main_flag)
{
    global $shopping_cart,$catalog_site,$cms_site,$categories_label,$product_label;
    global $products_label,$enable_wholesale,$enable_punch_list,$enable_multisite;
    global $enable_support_requests,$cpanel_base,$admin_directory,$siteid;
    global $show_dashboard,$google_email,$use_development_site,$on_live_site;
    global $dev_site_hostname,$live_site_hostname,$enable_rmas,$enable_reviews;
    global $enable_banner_ads,$enable_vendors,$search_engine,$enable_quotes;
    global $enable_dashboard,$disable_catalog_config,$enable_product_flags;
    global $account_label,$accounts_label,$wishlist_cookie,$registry_cookie;
    global $use_callout_groups,$enable_vendor_category_mapping,$prefs_cookie;
    global $enable_desktop,$enable_testimonials,$order_templates,$enable_order_terms;
    global $enable_invoices,$enable_salesorders;

    if (get_form_field('logstartup')) $log_startup = true;
    else $log_startup = false;
    if ($shopping_cart) $path_prefix = '../cartengine/';
    else $path_prefix = '';
    if (! isset($admin_directory)) $admin_directory = __DIR__.'/../admin/';
    if (! isset($show_dashboard)) {
       if (isset($siteid) && $siteid) $show_dashboard = true;
       else $show_dashboard = false;
    }
    if (! isset($enable_desktop)) $enable_desktop = false;
    if (! isset($use_development_site)) $use_development_site = false;
    if (! isset($enable_dashboard)) $enable_dashboard = false;
    if (isset($on_live_site)) $use_development_site = true;
    else {
       if ($use_development_site) $on_live_site = false;
       else $on_live_site = true;
    }
    if (! isset($disable_catalog_config)) $disable_catalog_config = false;
    if (! isset($enable_product_flags)) $enable_product_flags = false;
    if (isset($user_prefs['skin'])) $skin = $user_prefs['skin'];
    else $skin = null;

    if ($enable_desktop && $main_flag && $skin) {
       if ($shopping_cart) $desktop_url = '../cartengine/desktop.php';
       else $desktop_url = 'desktop.php';
       add_main_tab('desktop','Desktop',$desktop_url,0,0,null,null,true,null);
    }

    if ($shopping_cart) {
       $features = get_cart_config_value('features',$db);
       if (! isset($categories_label)) $categories_label = 'Categories';
       if (! isset($products_label)) {
          if (! isset($product_label)) $product_label = 'Product';
          $products_label = $product_label.'s';
       }
       if (! empty($enable_wholesale) && (! isset($accounts_label))) {
          if (! isset($account_label)) $account_label = 'Account';
          $accounts_label = $account_label.'s';
       }
       if (isset($prefs_cookie)) {
          add_main_tab('cart_tab','Cart',null,0,TAB_CONTAINER,null,null,
                       false,'50px');
          add_main_tab('orders','Orders','../cartengine/orders.php',
                       ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($enable_quotes))
             add_main_tab('quotes','Quotes','../cartengine/orders.php?ordertype=1',
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($enable_salesorders))
             add_main_tab('salesorders','Sales Orders',
                          '../cartengine/orders.php?ordertype=3',
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($enable_invoices))
             add_main_tab('invoices','Invoices',
                          '../cartengine/orders.php?ordertype=2',
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          add_main_tab('pending_carts','Pending Carts','../cartengine/carts.php',
                       ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($wishlist_cookie))
             add_main_tab('wish_lists','Wish Lists',
                          '../cartengine/carts.php?wishlists=true',
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (isset($enable_rmas) && $enable_rmas)
             add_main_tab('rmas','RMAs','../cartengine/rmas.php',
                          RMAS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (isset($enable_wholesale) && $enable_wholesale)
             add_main_tab('accounts',$accounts_label,'../cartengine/accounts.php',
                          ACCOUNTS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          add_main_tab('customers','Customers','../cartengine/customers.php',
                       CUSTOMERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if ($features & USE_COUPONS)
             add_main_tab('coupons','Coupons','../cartengine/coupons.php',
                          COUPONS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($registry_cookie))
             add_main_tab('registries','Registries','../cartengine/registries.php',
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if ((! $use_development_site) || (! $on_live_site)) {
             add_main_tab('catalog_tab','Catalog',null,0,TAB_CONTAINER,null,null,
                          false,'65px');
             add_main_tab('categories',$categories_label,'../cartengine/categories.php',
                          CATEGORIES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             add_main_tab('products',$products_label,'../cartengine/products.php',
                          PRODUCTS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             add_main_tab('attributes','Attributes','../cartengine/attributes.php',
                          ATTRIBUTES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             if (isset($enable_vendors) && $enable_vendors) {
                add_main_tab('vendors','Vendors','../cartengine/vendors.php',
                             VENDORS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
                if (isset($enable_vendor_category_mapping) &&
                    $enable_vendor_category_mapping)
                   add_main_tab('mapping','Category Mapping','../cartengine/mapping.php',
                                VENDORS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             }
             if (isset($enable_testimonials) && $enable_testimonials)
                add_main_tab('testimonials','Testimonials','../cartengine/testimonials.php',
                             REVIEWS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             if (isset($enable_reviews) && $enable_reviews)
                add_main_tab('reviews','Reviews','../cartengine/reviews.php',
                             REVIEWS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
             if (isset($search_engine))
                add_main_tab('searches','Searches','../cartengine/searches.php',
                             SEARCHES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          }
       }
       else {
          add_main_tab('orders','Orders','../cartengine/orders.php',
                       ORDERS_TAB_PERM,USER_PERM,null,null,true,'58px');
          if (isset($enable_rmas) && $enable_rmas)
             add_main_tab('rmas','RMAs','../cartengine/rmas.php',
                          RMAS_TAB_PERM,USER_PERM,null,null,true,'50px');
          if (isset($enable_wholesale) && $enable_wholesale)
             add_main_tab('accounts',$accounts_label,'../cartengine/accounts.php',
                          ACCOUNTS_TAB_PERM,USER_PERM,null,null,true,'72px');
          add_main_tab('customers','Customers','../cartengine/customers.php',
                       CUSTOMERS_TAB_PERM,USER_PERM,null,null,true,'78px');
          add_main_tab('categories',$categories_label,'../cartengine/categories.php',
                       CATEGORIES_TAB_PERM,USER_PERM,null,null,true,'78px');
          add_main_tab('products',$products_label,'../cartengine/products.php',
                       PRODUCTS_TAB_PERM,USER_PERM,null,null,true,'68px');
          add_main_tab('attributes','Attributes','../cartengine/attributes.php',
                       ATTRIBUTES_TAB_PERM,USER_PERM,null,null,true,'72px');
          if (isset($enable_vendors) && $enable_vendors) {
             add_main_tab('vendors','Vendors','../cartengine/vendors.php',
                          VENDORS_TAB_PERM,USER_PERM,null,null,true,'60px');
             if (isset($enable_vendor_category_mapping) &&
                 $enable_vendor_category_mapping)
                add_main_tab('mapping','Category Mapping','../cartengine/mapping.php',
                             VENDORS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          }
          if (isset($enable_reviews) && $enable_reviews)
             add_main_tab('reviews','Reviews','../cartengine/reviews.php',
                          REVIEWS_TAB_PERM,USER_PERM,null,null,true,'60px');
       }
    }
    else if ($catalog_site) {
       if (! isset($categories_label)) $categories_label = 'Categories';
       if (! isset($products_label)) {
          if (! isset($product_label)) $product_label = 'Product';
          $products_label = $product_label.'s';
       }
       if ($use_development_site && $on_live_site) {}
       else if (isset($prefs_cookie)) {
          add_main_tab('catalog_tab','Catalog',null,0,TAB_CONTAINER,null,null,
                       false,'65px');
          add_main_tab('categories',$categories_label,'categories.php',
                       CATEGORIES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          add_main_tab('products',$products_label,'products.php',
                       PRODUCTS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (isset($enable_reviews) && $enable_reviews)
             add_main_tab('reviews','Reviews','../cartengine/reviews.php',
                          REVIEWS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
       }
       else {
          add_main_tab('categories',$categories_label,'categories.php',
                       CATEGORIES_TAB_PERM,USER_PERM,null,null,true,'78px');
          add_main_tab('products',$products_label,'products.php',
                       PRODUCTS_TAB_PERM,USER_PERM,null,null,true,'68px');
          if (isset($enable_reviews) && $enable_reviews)
             add_main_tab('reviews','Reviews','../cartengine/reviews.php',
                          REVIEWS_TAB_PERM,USER_PERM,null,null,true,'60px');
       }
    }
    if (isset($prefs_cookie)) {
       add_main_tab('management_tab','Management',null,0,TAB_CONTAINER,null,
                    null,false,'90px');
       add_main_tab('modules_tab','Modules',null,0,TAB_CONTAINER,null,null,
                    false,'65px');
       add_main_tab('plugins_tab','Plugins',null,0,TAB_CONTAINER,null,null,
                    false,'65px');
       add_main_tab('content_tab','Content',null,0,TAB_CONTAINER,null,null,
                    false,'65px');
       if ((! $use_development_site) || (! $on_live_site)) {
          add_main_tab('templates','E-Mail Templates',$path_prefix.'templates.php',
                       TEMPLATES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
          if (! empty($order_templates))
            add_main_tab('order_templates','Order Templates',$path_prefix .
                         'templates.php?orders=true',TEMPLATES_TAB_PERM,
                         USER_PERM,null,null,true,null,null,1);
       }
       add_main_tab('forms','Forms',$path_prefix.'forms.php',FORMS_TAB_PERM,
                    USER_PERM,null,null,false,null,null,1);
       if ($shopping_cart && isset($enable_banner_ads) && $enable_banner_ads)
          add_main_tab('banner_ads_tab','Banner Ads','../cartengine/banners.php',
                       TEMPLATES_TAB_PERM,USER_PERM,null,null,true,null,null,1);
       add_main_tab('marketing_tab','Marketing',null,0,TAB_CONTAINER,null,null,
                    false,'75px');
       add_main_tab('bi_tab','Business Intelligence',null,0,TAB_CONTAINER,null,null,
                    false,'130px');
       if ($enable_dashboard)
         add_main_tab('dashboard','Dashboard','../cartengine/dashboard.php',
                      REPORTS_TAB_PERM,USER_PERM,null,null,true,null,null,1);
       add_main_tab('reports','Reports',$path_prefix.'reports.php',
                    REPORTS_TAB_PERM,USER_PERM,null,null,false,null,null,1);
       if ((! $use_development_site) || (! $on_live_site))
          add_main_tab('resources_tab','Resources',null,0,TAB_CONTAINER,null,null,
                       false,'75px');
       if ($show_dashboard && ((! $use_development_site) || (! $on_live_site))) {
          add_main_tab('whatsnew','What\'s New',
                       'https://www.inroads.us/siteadmin/whatsnew.php?siteid=' .
                       $siteid,0,0,null,null,false,null,null,1);
          add_main_tab('marketing','Marketing',
                       'https://www.inroads.us/siteadmin/marketing.php?siteid=' .
                       $siteid,0,0,null,null,false,null,null,1);
          add_main_tab('forum','Forum','https://www.inroads.us/phpbb/',
                       0,0,null,null,false,null,null,1);
          add_main_tab('knowledgebase','Knowledge Base',
                       'https://www.inroads.us/siteadmin/kb/index.php',0,0,
                        null,null,false,null,null,1);
/*
          add_main_tab('tutorials','Video Tutorials',
                       'https://www.inroads.us/siteadmin/tutorials.php?siteid=' .
                       $siteid,0,0,null,null,false,null,null,1);
*/
       }
       add_main_tab('system_tab','System',null,0,TAB_CONTAINER,null,null,
                    false,'65px');
       if ($use_development_site && $on_live_site) {
          add_main_tab('exportdata','Export Data',null,EXPORT_DATA_BUTTON_PERM,
                       USER_PERM,null,null,false,null,'export_data();',1);
          $dev_url = 'http://'.$dev_site_hostname.'/admin';
          add_main_tab('dev_site','Development Site',null,
                          0,0,null,null,false,null,
                          'location.href=\''.$dev_url.'\';',0);
       }
       else {
          add_main_tab('adminusers','Admin Users',null,ADMIN_USERS_TAB_PERM,
                       USER_PERM,null,null,false,null,'admin_users();',1);
          add_main_tab('importdata','Import Data',null,IMPORT_DATA_BUTTON_PERM,
                       USER_PERM,null,null,false,null,'import_data();',1);
          add_main_tab('exportdata','Export Data',null,EXPORT_DATA_BUTTON_PERM,
                       USER_PERM,null,null,false,null,'export_data();',1);
          add_main_tab('systemconfig','System Config',null,
                       SYSTEM_CONFIG_BUTTON_PERM,USER_PERM,null,null,false,null,
                       'system_config();',1);
          if ($shopping_cart)
             add_main_tab('cartconfig','Cart Config',null,CART_CONFIG_BUTTON_PERM,
                          USER_PERM,null,null,false,null,'cart_config();',1);
          if ((! $disable_catalog_config) && ($shopping_cart || $catalog_site))
             add_main_tab('catalogconfig','Catalog Config',null,CART_CONFIG_BUTTON_PERM,
                          USER_PERM,null,null,false,null,'catalog_config();',1);
          if (isset($use_callout_groups) && $use_callout_groups)
             add_main_tab('calloutgroups','Callout Groups',
                          '../cartengine/admin.php?cmd=calloutgroups',
                          SYSTEM_CONFIG_BUTTON_PERM,USER_PERM,null,null,true,null,
                          null,1);
          if ($enable_product_flags)
             add_main_tab('productflags','Product Flags',null,CART_CONFIG_BUTTON_PERM,
                          USER_PERM,null,null,false,null,'product_flags();',1);
          if ($enable_multisite)
             add_main_tab('websites','Web Sites',null,WEB_SITES_BUTTON_PERM,
                          USER_PERM,null,null,false,null,'web_sites();',1);
          add_main_tab('media','Media Libraries',null,MEDIA_TAB_PERM,USER_PERM,
                       null,null,false,null,'media_libraries();',1);
          if (file_exists('../engine/wordpress/wp-admin/plugins.php'))
             add_main_tab('wp_plugins','Plugins',null,
                          SYSTEM_CONFIG_BUTTON_PERM,USER_PERM,null,null,
                          false,null,'open_wp_plugins();',1);
          if (isset($google_email))
             add_main_tab('googleanalytics','Google Analytics',null,
                          GOOGLE_BUTTON_PERM,USER_PERM,null,null,false,null,
                          'open_google(true);',1);
          if ($enable_punch_list)
             add_main_tab('punchlist','Punch List',null,TICKETS_BUTTON_PERM,
                          USER_PERM,null,null,false,null,'open_tickets();',1);
          else if ($enable_support_requests)
             add_main_tab('supportrequests','Support Requests',null,
                          TICKETS_BUTTON_PERM,USER_PERM,null,null,false,null,
                          'open_tickets();',1);
          if ($use_development_site) {
             add_main_tab('publishlivesite','Publish Live Site',null,
                          PUBLISH_BUTTON_PERM,USER_PERM,null,null,false,null,
                          'publish_live_site();',1);
             add_main_tab('copylivedata','Copy Live Data',null,
                          PUBLISH_BUTTON_PERM,USER_PERM,null,null,false,null,
                          'copy_live_data();',1);
             $live_url = 'http://'.$live_site_hostname.'/admin';
             add_main_tab('live_site','Live Site',null,
                             0,0,null,null,false,null,
                             'location.href=\''.$live_url.'\';',0);
          }
          if (! empty($enable_order_terms))
             add_main_tab('manage_terms','Manage Order Terms',null,
                          ORDERS_TAB_PERM,USER_PERM,null,null,true,null,
                          'manage_order_terms(null);',1);
       }
    }
    else {
       if (! $cms_site) {
          add_main_tab('templates','Templates',$path_prefix.'templates.php',
                       TEMPLATES_TAB_PERM,USER_PERM,null,null,true,'76px');
          add_main_tab('reports','Reports',$path_prefix.'reports.php',
                       REPORTS_TAB_PERM,USER_PERM,null,null,true,'60px');
       }
       if ($shopping_cart)
          add_main_tab('coupons','Coupons','../cartengine/coupons.php',
                       COUPONS_TAB_PERM,USER_PERM,null,null,true,'66px');
       if (! $cms_site)
          add_main_tab('forms','Forms',$path_prefix.'forms.php',FORMS_TAB_PERM,
                       USER_PERM,null,null,true,'53px');
       if ($shopping_cart)
          add_main_tab('admin','Admin','../cartengine/admin.php',ADMIN_TAB_PERM,
                       USER_PERM,null,null,true,'53px');
       else if ($catalog_site)
          add_main_tab('admin','Admin','admin.php',ADMIN_TAB_PERM,USER_PERM,
                       null,'categories',true,'53px');
       else add_main_tab('admin','Admin','admin.php',ADMIN_TAB_PERM,USER_PERM,
                         null,isset($prefs_cookie)?'admin':'templates',true,'53px');
    }
    if ($shopping_cart) {
       if ($log_startup) log_activity('Loading Analytics Tabs');
       require_once 'analytics.php';
       add_analytics_main_tabs($db);
    }
    if ($log_startup) log_activity('Loading Media Library Tabs');
    $query = 'select id,parent_menu,menu_name from media_libraries order by id';
    $libraries = $db->get_records($query);
    if ($libraries) {
       foreach ($libraries as $library)
          add_main_tab('media_'.$library['id'],$library['menu_name'],
                       $path_prefix.'media.php?library='.$library['id'],
                       MEDIA_TAB_PERM,USER_PERM,$library['parent_menu'],
                       null,true,null,null,1,false);
    }
    if ($log_startup) log_activity('Calling main_tabs event');
    call_module_event('main_tabs',array($user_perms,$module_perms,
                                        $custom_perms,$user_prefs));
    if (file_exists('../engine/wordpress/wp-content/engine/wpengine.php')) {
       if ($log_startup) log_activity('Loading WordPress Engine Tabs');
       load_wordpress_engine_tabs();
    }
    if (function_exists('setup_custom_tabs')) {
       if ($log_startup) log_activity('Setting up Custom Tabs');
       setup_custom_tabs($user_perms,$module_perms,$custom_perms);
    }
    if (isset($prefs_cookie))
       add_main_tab('about','About',null,0,0,'system_tab',null,false,null,
                    'display_about();',1,false);
    if ($log_startup) log_activity('Finished Loading Main Tabs');
}

function setup_main_tabs(&$start_tab,&$start_tab_label,&$start_top_tab,
   &$initial_cover,$user_perms,$module_perms,$custom_perms)
{
    global $main_tabs,$main_tab_order,$main_tab_defaults;

    foreach ($main_tab_order as $index => $tab_name) {
       if ($main_tabs[$tab_name][2] == 0) continue;
       if (($main_tabs[$tab_name][3] == USER_PERM) &&
           ($user_perms & $main_tabs[$tab_name][2])) continue;
       if (($main_tabs[$tab_name][3] == MODULE_PERM) &&
           ($module_perms & $main_tabs[$tab_name][2])) continue;
       if (($main_tabs[$tab_name][3] == CUSTOM_PERM) &&
           ($custom_perms & $main_tabs[$tab_name][2])) continue;
       remove_main_tab($tab_name);
    }

    $initial_cover = true;
    $start_tab = get_form_field('tab');
    if ($start_tab) {
       if (! isset($main_tabs[$start_tab])) $start_tab = '';
       else $initial_cover = $main_tabs[$start_tab][4];
    }
    if (! $start_tab) {
       foreach ($main_tab_defaults as $index => $tab_name) {
          $current_tab = $main_tabs[$tab_name];
          if ($current_tab[3] == TAB_CONTAINER) continue;
          break;
       }
       $start_tab = $tab_name;
       if (isset($main_tabs[$tab_name]))
          $initial_cover = $main_tabs[$tab_name][4];
       else $initial_cover = false;
    }
    $current_top_tab = null;
    foreach ($main_tab_order as $index => $tab_name) {
       $current_tab = $main_tabs[$tab_name];
       if ($current_tab[7] == 0) $current_top_tab = $tab_name;
       if ($start_tab == $tab_name) {
          $start_top_tab = $current_top_tab;   break;
       }
    }
    $start_tab_label = $main_tabs[$start_tab][0];
}

function process_main_tabs($screen)
{
    global $main_tabs,$main_tab_order;

    end($main_tab_order);   $last_tab = key($main_tab_order);
    $first_tab = true;
    foreach ($main_tab_order as $index => $tab_name) {
       $tab_sequence = 0;
       if ($first_tab) {
          $tab_sequence |= FIRST_TAB;   $first_tab = false;
       }
       if ($index == $last_tab) $tab_sequence |= LAST_TAB;
       $current_tab = $main_tabs[$tab_name];
       if (isset($main_tab_order[$index + 1])) {
          $next_tab = $main_tabs[$main_tab_order[$index + 1]];
          $next_level = $next_tab[7];
       }
       else {
          $next_tab = null;   $next_level = -1;
       }
       if (($current_tab[3] == TAB_CONTAINER) &&
           (($next_level < 1) ||
            ($next_tab[3] == TAB_CONTAINER))) continue;
       $leave_open = false;
       if ($next_level == -1) {}
       else if ($next_level > $current_tab[7]) $leave_open = true;
       $screen->add_tab($tab_name,$current_tab[0],$current_tab[1],
                        $current_tab[4],$current_tab[6],$current_tab[5],
                        $tab_sequence,$leave_open,$current_tab[8]);
       if ($leave_open) $screen->start_tab_menu($next_level);
       else if ($next_level == -1) {
          while ($current_tab[7] > 0) {
             $screen->end_tab_menu();   $screen->end_tab();
             $current_tab[7]--;
          }
       }
       else if ($next_level < $current_tab[7]) {
          for ($loop = $current_tab[7];  $loop > $next_level;  $loop--) {
             $screen->end_tab_menu();   $screen->end_tab();
          }
       }
    }
}

?>
