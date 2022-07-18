PU			:=	vendor/bin/phpunit



.PHONY			:	composer-install
composer-install	:
	rm -rf vendor
	composer install
	composer dump-autoload -o



.PHONY			:	composer-distclean
	rm -rf composer.lock



.PHONY			: test
test			:
	$(PU) --bootstrap vendor/autoload.php test
