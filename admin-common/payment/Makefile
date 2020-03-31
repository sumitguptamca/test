#
#                Inroads Shopping Cart - payment Modules Makefile
#
#                     Written 2018-2019 by Randall Severy
#                      Copyright 2018-2019 Inroads, LLC

PACKAGES= ../../../packages/authorize.zip ../../../packages/chase.zip \
	../../../packages/firstdata.zip ../../../packages/googlepay.zip \
	../../../packages/ibis.zip ../../../packages/isecure.zip \
	../../../packages/linkpoint.zip ../../../packages/manual-payment.zip \
	../../../packages/multicards.zip ../../../packages/payflowpro.zip \
	../../../packages/paypal.zip ../../../packages/sage.zip \
	../../../packages/virtualmerchant.zip ../../../packages/thebigcoin.zip \
	../../../packages/converge.zip ../../../packages/cardknox.zip

all: $(PACKAGES)

../../../packages/authorize.zip: authorize.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 authorize.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD authorize.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/authorize.zip payment/authorize.php
	mv -f ../authorize.zip ../../../packages

../../../packages/chase.zip: chase.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 chase.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD chase.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/chase.zip payment/chase.php
	mv -f ../chase.zip ../../../packages

../../../packages/firstdata.zip: firstdata.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 firstdata.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD firstdata.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/firstdata.zip payment/firstdata.php
	mv -f ../firstdata.zip ../../../packages

../../../packages/googlepay.zip: googlepay.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 googlepay.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD googlepay.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/googlepay.zip payment/googlepay.php
	mv -f ../googlepay.zip ../../../packages

../../../packages/ibis.zip: ibis.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 ibis.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD ibis.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/ibis.zip payment/ibis.php
	mv -f ../ibis.zip ../../../packages

../../../packages/isecure.zip: isecure.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 isecure.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD isecure.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/isecure.zip payment/isecure.php
	mv -f ../isecure.zip ../../../packages

../../../packages/linkpoint.zip: linkpoint.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 linkpoint.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD linkpoint.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/linkpoint.zip payment/linkpoint.php
	mv -f ../linkpoint.zip ../../../packages

../../../packages/manual-payment.zip: manual.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 manual.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD manual-payment.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/manual-payment.zip payment/manual.php
	mv -f ../manual-payment.zip ../../../packages

../../../packages/multicards.zip: multicards.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 multicards.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD multicards.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/multicards.zip payment/multicards.php
	mv -f ../multicards.zip ../../../packages

../../../packages/payflowpro.zip: payflowpro.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 payflowpro.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD payflowpro.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/payflowpro.zip payment/payflowpro.php
	mv -f ../payflowpro.zip ../../../packages

../../../packages/paypal.zip: paypal.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 paypal.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD paypal.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/paypal.zip payment/paypal.php
	mv -f ../paypal.zip ../../../packages

../../../packages/sage.zip: sage.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 sage.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD sage.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/sage.zip payment/sage.php
	mv -f ../sage.zip ../../../packages

../../../packages/virtualmerchant.zip: virtualmerchant.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 virtualmerchant.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD virtualmerchant.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/virtualmerchant.zip payment/virtualmerchant.php
	mv -f ../virtualmerchant.zip ../../../packages

../../../packages/thebigcoin.zip: thebigcoin.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 thebigcoin.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD thebigcoin.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/thebigcoin.zip payment/thebigcoin.php
	mv -f ../thebigcoin.zip ../../../packages

../../../packages/converge.zip: converge.php Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 converge.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD converge.zip payment/packing.list
	cd ../../encoded/cartengine; zip ../../cartengine/converge.zip payment/converge.php
	mv -f ../converge.zip ../../../packages

../../../packages/cardknox.zip: cardknox.php cardknox-ifields.js Makefile packing.list
	mkdir -p ../../encoded/cartengine/payment
	/usr/local/ioncube/ioncube_encoder.sh -53 cardknox.php --into ../../encoded/cartengine/payment --ignore '*/' --ignore-strict-warnings --only-include-encoded-files --replace-target --add-comment="Copyright 2019 by Inroads, LLC"
	cd ..; zip -jD cardknox.zip payment/packing.list; zip cardknox.zip payment/cardknox-ifields.js
	cd ../../encoded/cartengine; zip ../../cartengine/cardknox.zip payment/cardknox.php
	mv -f ../cardknox.zip ../../../packages

