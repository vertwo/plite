.PHONY		:	ci
ci		:
	rm -rf composer.lock
	rm -rf vendor
	composer install
	composer dump-autoload -o



.PHONY			: composer_autodump
composer_autodump	:
	composer dump-autoload -o


.PHONY			: test
test			:
	./phpunit --bootstrap vendor/autoload.php test
