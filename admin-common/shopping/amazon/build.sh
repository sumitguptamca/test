#
#         Inroads Shopping Cart - Amazon Shopping Module Build Script
#
#                     Written 2018-2019 by Randall Severy
#                      Copyright 2018-2019 Inroads, LLC
#
mkdir -p ../../../encoded/cartengine/shopping/amazon
/usr/local/ioncube/ioncube_encoder.sh -53 *.php --into ../../../encoded/cartengine/shopping/amazon --ignore installamazon.php --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
/usr/local/ioncube/ioncube_encoder.sh -53 amazon.php --into ../../encoded/cartengine/shopping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
zip -jD amazon.zip shopping/amazon/packing.list shopping/amazon/installamazon.php shopping/amazon/*.sql ../upgrade/upgradeutil.php
cd ../encoded/cartengine
zip ../../cartengine/amazon.zip shopping/amazon.php shopping/amazon/*.php
cd ../../cartengine
zip amazon.zip shopping/amazon/admin.js shopping/amazon/config.js shopping/amazon/config.css shopping/amazon/reports.js shopping/amazon/vendors.js shopping/amazon/vendors.css shopping/amazon/templates-config.js
mv -f amazon.zip ../../packages

