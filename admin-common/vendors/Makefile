#
#                Inroads Shopping Cart - Vendor Modules Makefile
#
#                      Written 2018-2019 by Randall Severy
#                       Copyright 2018-2019 Inroads, LLC

PACKAGES= ../../../packages/kering.zip ../../../packages/luxottica.zip \
	../../../packages/marchon.zip ../../../packages/safilo.zip
GLASSES_FILES=glasses/packing.list glasses/glasses-common.php

all: $(PACKAGES)

../../../packages/kering.zip: kering.php Makefile $(GLASSES_FILES)
	mkdir -p ../../encoded/cartengine/vendors
	/usr/local/ioncube/ioncube_encoder.sh -53 kering.php --into ../../encoded/cartengine/vendors --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	zip -jD ../kering.zip $(GLASSES_FILES)
	cd ../../encoded/cartengine; zip ../../cartengine/kering.zip vendors/kering.php
	mv -f ../kering.zip ../../../packages

../../../packages/luxottica.zip: luxottica.php Makefile $(GLASSES_FILES)
	mkdir -p ../../encoded/cartengine/vendors
	/usr/local/ioncube/ioncube_encoder.sh -53 luxottica.php --into ../../encoded/cartengine/vendors --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	zip -jD ../luxottica.zip $(GLASSES_FILES)
	cd ../../encoded/cartengine; zip ../../cartengine/luxottica.zip vendors/luxottica.php
	mv -f ../luxottica.zip ../../../packages

../../../packages/marchon.zip: marchon.php Makefile $(GLASSES_FILES)
	mkdir -p ../../encoded/cartengine/vendors
	/usr/local/ioncube/ioncube_encoder.sh -53 marchon.php --into ../../encoded/cartengine/vendors --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	zip -jD ../marchon.zip $(GLASSES_FILES)
	cd ../../encoded/cartengine; zip ../../cartengine/marchon.zip vendors/marchon.php
	mv -f ../marchon.zip ../../../packages

../../../packages/safilo.zip: safilo.php Makefile $(GLASSES_FILES)
	mkdir -p ../../encoded/cartengine/vendors
	/usr/local/ioncube/ioncube_encoder.sh -53 safilo.php --into ../../encoded/cartengine/vendors --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	zip -jD ../safilo.zip $(GLASSES_FILES)
	cd ../../encoded/cartengine; zip ../../cartengine/safilo.zip vendors/safilo.php
	mv -f ../safilo.zip ../../../packages

