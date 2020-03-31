#
#       Inroads Shopping Cart - Facebook Commerce Module Build Script
#
#                     Written 2019 by Randall Severy
#                      Copyright 2019 Inroads, LLC
#
mkdir -p ../../../encoded/cartengine/shopping/facebook
/usr/local/ioncube/ioncube_encoder.sh -53 *.php --into ../../../encoded/cartengine/shopping/facebook --ignore installfacebook.php --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
/usr/local/ioncube/ioncube_encoder.sh -53 facebook.php --into ../../encoded/cartengine/shopping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
cd ..
zip -jD facebook.zip shopping/facebook/packing.list shopping/facebook/installfacebook.php shopping/facebook/facebook.sql
cd ../encoded/cartengine
zip ../../cartengine/facebook.zip shopping/facebook.php shopping/facebook/*.php
cd ../../cartengine
zip facebook.zip shopping/facebook/vendors.js shopping/facebook/vendors.css
mv -f facebook.zip ../../packages

