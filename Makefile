POFILES := $(wildcard lihi-short-url/languages/*.po)
MOFILES := $(POFILES:.po=.mo)

.PHONY: all clean

all: $(MOFILES)

lihi-short-url/languages/%.mo: lihi-short-url/languages/%.po
	msgfmt $< -o $@

clean:
	rm -f $(MOFILES)
