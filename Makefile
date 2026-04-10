POFILES := $(wildcard lihi-shorturl/languages/*.po)
MOFILES := $(POFILES:.po=.mo)

.PHONY: all clean test

all: $(MOFILES)

lihi-shorturl/languages/%.mo: lihi-shorturl/languages/%.po
	msgfmt $< -o $@

clean:
	rm -f $(MOFILES)

test:
	vendor/bin/phpunit -c phpunit.xml