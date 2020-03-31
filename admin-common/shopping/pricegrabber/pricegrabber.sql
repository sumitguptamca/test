#
#  Inroads Shopping Cart - PriceGrabber Shopping Module MySQL Database Schema
#
#                        Written 2018 by Randall Severy
#                         Copyright 2018 Inroads, LLC
#

alter table products add pricegrabber_cat varchar(255) after shopping_flags;

