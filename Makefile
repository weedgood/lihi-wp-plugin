POFILES := $(wildcard lihi-short-url/languages/*.po)
MOFILES := $(POFILES:.po=.mo)
COMPOSER_WORKDIR := /app
CODE_DIR := /app/code
PHPUNIT := /app/vendor/bin/phpunit

.PHONY: all clean stop-dev-services test test74 test82 coverage coverage74 coverage82

all: $(MOFILES)

lihi-short-url/languages/%.mo: lihi-short-url/languages/%.po
	msgfmt $< -o $@

clean:
	rm -f $(MOFILES)

stop-dev-services:
	docker compose stop wordpress db

test: test74 test82
	docker compose --profile test stop db_test

coverage: coverage74 coverage82
	docker compose --profile test stop db_test

test74: stop-dev-services
	docker compose --profile test stop phpunit82
	docker compose --profile test up -d --build db_test phpunit74
	docker compose --profile test exec phpunit74 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit74 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml'
	docker compose --profile test stop phpunit74

test82: stop-dev-services
	docker compose --profile test stop phpunit74
	docker compose --profile test up -d --build db_test phpunit82
	docker compose --profile test exec phpunit82 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit82 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml'
	docker compose --profile test stop phpunit82

coverage74: stop-dev-services
	docker compose --profile test stop phpunit82
	docker compose --profile test up -d --build db_test phpunit74
	docker compose --profile test exec phpunit74 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit74 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml --coverage-text --coverage-html /app/coverage/php74'
	docker compose --profile test stop phpunit74

coverage82: stop-dev-services
	docker compose --profile test stop phpunit74
	docker compose --profile test up -d --build db_test phpunit82
	docker compose --profile test exec phpunit82 composer install --working-dir=$(COMPOSER_WORKDIR)
	docker compose --profile test exec phpunit82 sh -lc 'cd $(CODE_DIR) && $(PHPUNIT) -c phpunit.xml --coverage-text --coverage-html /app/coverage/php82'
	docker compose --profile test stop phpunit82
