#
#         Inroads Shopping Cart - Google Shopping Module Build Script
#
#                     Written 2018-2019 by Randall Severy
#                      Copyright 2018-2019 Inroads, LLC
#
mkdir -p ../../../encoded/cartengine/shopping/google
/usr/local/ioncube/ioncube_encoder.sh -53 *.php --into ../../../encoded/cartengine/shopping/google --ignore installgoogle.php --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
/usr/local/ioncube/ioncube_encoder.sh -53 google.php --into ../../encoded/cartengine/shopping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
zip -jD google.zip shopping/google/packing.list shopping/google/installgoogle.php shopping/google/google.sql
cd ../encoded/cartengine
zip ../../cartengine/google.zip shopping/google.php shopping/google/*.php
cd ../../cartengine
zip google.zip shopping/google/admin.js shopping/google/config.js shopping/google/reports.js shopping/google/vendors.js shopping/google/vendors.css
mv -f google.zip ../../packages

