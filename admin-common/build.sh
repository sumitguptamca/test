#
#      Inroads QuikWeb Platform - QuikWeb Cart Engine Package Build Script
#
#                      Written 2017-2019 by Randall Severy
#                       Copyright 2017-2019 Inroads, LLC
#
cd ..
/usr/local/ioncube/ioncube_encoder.sh -53 cartengine --into encoded --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
zip -jD cartengine.zip cartengine/packing.list
zip cartengine.zip modules/cms.php upgrade/upgradecartsite.php upgrade/upgradeutil.php upgrade/import_customer_activity.php upgrade/import_product_activity.php upgrade/upgradeorders.php upgrade/updatecartsite.sql
mv cartengine.zip encoded
cd encoded
zip cartengine.zip cartengine/*.php
mv cartengine.zip ..
cd ..
zip cartengine.zip cartengine/*.js cartengine/*.css
mv cartengine.zip cartengine
cd cartengine
zip -r cartengine.zip images/* admin/*.php RazorFlow/*
mv cartengine.zip ../admin
cd ../admin
zip -r cartengine.zip help/* skins/* plugins/*.php
mv -f cartengine.zip ../../packages

