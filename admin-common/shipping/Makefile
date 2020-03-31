#
#                Inroads Shopping Cart - Shipping Modules Makefile
#
#                      Written 2018-2019 by Randall Severy
#                       Copyright 2018-2019 Inroads, LLC

PACKAGES= ../../../packages/dhl.zip ../../../packages/endicia.zip \
	../../../packages/fedex.zip ../../../packages/g4si.zip \
	../../../packages/gls.zip ../../../packages/manual-shipping.zip \
	../../../packages/stamps.zip ../../../packages/ups.zip \
	../../../packages/usps.zip

all: $(PACKAGES)

../../../packages/dhl.zip: dhl.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 dhl.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD dhl.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/dhl.zip shipping/dhl.php
	mv -f ../dhl.zip ../../../packages

../../../packages/endicia.zip: endicia.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 endicia.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD endicia.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/endicia.zip shipping/endicia.php
	mv -f ../endicia.zip ../../../packages

../../../packages/fedex.zip: fedex.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 fedex.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD fedex.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/fedex.zip shipping/fedex.php
	mv -f ../fedex.zip ../../../packages

../../../packages/g4si.zip: g4si.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 g4si.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD g4si.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/g4si.zip shipping/g4si.php
	mv -f ../g4si.zip ../../../packages

../../../packages/gls.zip: gls.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 gls.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD gls.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/gls.zip shipping/gls.php
	mv -f ../gls.zip ../../../packages

../../../packages/manual-shipping.zip: manual.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 manual.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD manual-shipping.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/manual-shipping.zip shipping/manual.php
	mv -f ../manual-shipping.zip ../../../packages

../../../packages/stamps.zip: stamps.php stamps.wsdl Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 stamps.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD stamps.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/stamps.zip shipping/stamps.php shipping/stamps.wsdl
	mv -f ../stamps.zip ../../../packages

../../../packages/ups.zip: ups.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 ups.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD ups.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/ups.zip shipping/ups.php
	mv -f ../ups.zip ../../../packages

../../../packages/usps.zip: usps.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/shipping
	/usr/local/ioncube/ioncube_encoder.sh -53 usps.php --into ../../encoded/cartengine/shipping --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD usps.zip shipping/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/usps.zip shipping/usps.php
	mv -f ../usps.zip ../../../packages

