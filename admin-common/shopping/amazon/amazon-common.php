<?php
/*
              Inroads Shopping Cart - Amazon Common Functions and Data

                        Written 2018-2019 by Randall Severy
                         Copyright 2018-2019 Inroads, LLC

*/

define('AMAZON_FLAG',0);

define('AMAZON_RESULTS',200);

define('UPLOAD_ONLY_WITH_ASIN',1);
define('MATCH_IMPORT_BY_ASIN',2);
define('SKIP_MATCHING_FBA',4);

global $amazon_category_types;
$amazon_category_types = array(
   'AutoAccessory' => 'Automotive & Powersports (Parts & Accessories)',
   'TiresAndWheels' => 'Automative & Powersports (Tires & Wheels)',
   'Baby' => 'Baby',
   'Beauty' => 'Beauty',
   'BookLoader' => 'Books',
   'CameraAndPhoto' => 'Camera & Photo',
   'Wireless' => 'Cell Phones & Accessories (Wireless)',
   'Clothing' => 'Clothing & Accessories, Eyewear, Luggage & Travel Accessories',
   'Coins' => 'Collectible Coins',
   'Computers' => 'Computers',
   'ConsumerElectronics' => 'Consumer Electronics',
   'EntertainmentCollectibles' => 'Entertainment Collectibles',
   'FineArt' => 'Fine Art',
   'GiftCards' => 'Gift Cards',
   'FoodAndBeveragest' => 'Grocery & Gourmet Food',
   'Health' => 'Health & Personal Care',
   'Home' => 'Home, Home Decor, Kitchen & Garden, Furniture',
   'MechanicalFasteners' => 'Industrial & Scientific (Fasteners)',
   'FoodServiceAndJanSan' => 'Industrial & Scientific (Food Service and Janitorial, Sanitation, & Safety)',
   'LabSupplies' => 'Industrial & Scientific (Lab & Scientific Supplies)',
   'PowerTransmission' => 'Industrial & Scientific (Power Transmission)',
   'RawMaterials' => 'Industrial & Scientific (Raw Materials)',
   'Industrial' => 'Industrial & Scientific (Industrial and Other)',
   'Jewelry' => 'Jewelry',
   'Lighting' => 'Lighting',
   'Music' => 'Music',
   'MusicalInstruments' => 'Musical Instruments',
   'Office' => 'Office Products',
   'Outdoors' => 'Outdoors (Outdoor Gear, Outdoor Sports Apparel, Cycling, and Action Sports)',
   'PetSupplies' => 'Pet Supplies',
   'Shoes' => 'Shoes, Handbags, Eyewear, and Shoe Accessories',
   'SWVG' => 'Software & Video Games',
   'Sports' => 'Sports (Exercise & Fitness, Hunting Accessories, Team Sports, etc.)',
   'SportsMemorabilia' => 'Sports Collectibles',
   'HomeImprovement' => 'Tools & Home Improvement',
   'Toys' => 'Toys & Games',
   'TradingCards' => 'Trading Cards',
   'Video' => 'Video & DVD',
   'Watches' => 'Watches'
);



?>
