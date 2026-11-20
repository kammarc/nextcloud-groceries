# Groceries – build & release helpers
app_name := groceries
version := $(shell sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
build_dir := build
release := $(build_dir)/$(app_name)-$(version).tar.gz

.PHONY: all deps build test test-php test-js lint appstore clean

all: build

deps:
	npm ci
	composer install

build:
	npm run build

test: test-js test-php

test-js:
	npm test
	npm run typecheck

test-php:
	composer test:unit

lint:
	composer lint

# Release tarball for the Nextcloud app store (contains the built frontend, no dev files)
appstore: build
	rm -rf $(build_dir)/$(app_name)
	mkdir -p $(build_dir)/$(app_name)
	cp -r appinfo lib templates img js css serviceworker l10n README.md CHANGELOG.md LICENSE THIRD-PARTY-NOTICES.md $(build_dir)/$(app_name)/
	tar -czf $(release) -C $(build_dir) $(app_name)
	@echo "created $(release)"
	@echo "sign with: openssl dgst -sha512 -sign ~/.nextcloud/certificates/$(app_name).key $(release) | openssl base64"

clean:
	rm -rf $(build_dir) js css
