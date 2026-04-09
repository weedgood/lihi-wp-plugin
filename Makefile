POFILES := $(wildcard lihi-shorturl/languages/*.po)
MOFILES := $(POFILES:.po=.mo)

.PHONY: all clean

all: $(MOFILES)

lihi-shorturl/languages/%.mo: lihi-shorturl/languages/%.po
	msgfmt $< -o $@

clean:
	rm -f $(MOFILES)
