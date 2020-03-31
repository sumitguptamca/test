#
#     Inroads Shopping Cart - Amazon Shopping Module MySQL Database Schema
#
#                     Written 2018-2019 by Randall Severy
#                      Copyright 2018-2019 Inroads, LLC
#

alter table products add amazon_asin varchar(80) after shopping_flags;
alter table products add amazon_sku varchar(200) after amazon_asin;
alter table products add amazon_type varchar(80) after amazon_sku;
alter table products modify amazon_type varchar(80);
alter table products add amazon_item_type varchar(255) after amazon_type;
alter table products add amazon_price decimal(8,2) after amazon_item_type;
alter table products add amazon_fba_flag int after amazon_price;
alter table products add amazon_downloaded int after amazon_fba_flag;
alter table products add amazon_updated int after amazon_downloaded;
alter table products add amazon_error text after amazon_updated;
alter table products add amazon_warning text after amazon_error;

create table if not exists amazon_pending_deletes (
    id                int not null auto_increment primary key,
    product_id        int,
    part_number       varchar(200)
);

create table if not exists amazon_pending_image_deletes (
    id                int not null auto_increment primary key,
    product_id        int,
    amazon_sku        varchar(200),
    filename          varchar(255)
);

create table if not exists amazon_cached_asins (
    id                int not null auto_increment primary key,
    cache_type        int,
    cache_value       varchar(80),
    asin              varchar(80),
    expire_date       int,
    index cache_index(cache_type,cache_value)
);

