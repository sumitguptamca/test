#
#     Inroads Shopping Cart - Facebook Commerce Module MySQL Database Schema
#
#                        Written 2019 by Randall Severy
#                         Copyright 2019 Inroads, LLC
#

alter table products add google_shopping_cat varchar(255) after shopping_flags;

