#
#     Inroads Shopping Cart - Google Shopping Module MySQL Database Schema
#
#                        Written 2018 by Randall Severy
#                         Copyright 2018 Inroads, LLC
#

alter table products add google_shopping_id varchar(80) after shopping_flags;
alter table products add google_shopping_type varchar(255) after google_shopping_id;
alter table products add google_shopping_cat varchar(255) after google_shopping_type;
alter table products add google_adwords varchar(255) after google_shopping_cat;
alter table products add google_shopping_updated int after google_adwords;
alter table products add google_shopping_error text after google_adwords;
alter table products add google_shopping_warnings text after google_shopping_error;

