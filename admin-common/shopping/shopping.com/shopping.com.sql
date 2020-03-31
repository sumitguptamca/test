#
#   Inroads Shopping Cart - Shopping.com Shopping Module MySQL Database Schema
#
#                        Written 2018 by Randall Severy
#                         Copyright 2018 Inroads, LLC
#

create table if not exists shopping_categories (
    id                int not null primary key,
    category          varchar(255)
);
create table if not exists shopping_product_types (
    category          varchar(255),
    product_type      varchar(255)
);

alter table products add shopping_cat varchar(255) after shopping_flags;
alter table products add shopping_type varchar(255) after shopping_cat;

