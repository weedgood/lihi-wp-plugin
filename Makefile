POFILES := $(wildcard lihi-short-url/languages/*.po)
MOFILES := $(POFILES:.po=.mo)
COMPOSER_WORKDIR := /app
CODE_DIR := /app/code
PHPUNIT := /app/vendor/bin/phpunit

.PHONY: all clean test test74 test82 coverage coverage74 coverage82

all: $(MOFILES)

lihi-short-url/languages/%.mo: lihi-short-url/languages/%.po
	msgfmt $< -o $@

clean:
	rm -f $(MOFILES)

test: test74 test82

coverage: coverage74 coverage82

test74:
	docker compose --profile test exec phpunit74 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit74 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml'

test82:
	docker compose --profile test exec phpunit82 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit82 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml'

coverage74:
	docker compose --profile test exec phpunit74 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit74 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml --coverage-text --coverage-html /app/coverage/php74'

coverage82:
	docker compose --profile test exec phpunit82 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit82 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml --coverage-text --coverage-html /app/coverage/php82'
