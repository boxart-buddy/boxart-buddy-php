bootstrap: ## Bootstrap to create config files on initial setup (run this 1st)
	php bin/console bootstrap
bootstrap-stage-1: ## Bootstrap but quit early, runs during auto installation script
	php bin/console bootstrap --stage-1
dl-cache: ## Download a pre-compiled cache (run this 2nd)
	php bin/console download-cache
scrape: ## Scrape screenscraper and fill the cache prior to generation (run this 3rd)
	php bin/console scrape
scrape-missing: ## Scrape screenscraper, but only for files that are missing
	php bin/console scrape --onlymissing
build: ## Build artwork (run this 4th)
	php bin/console build-interactive
import-missing-json: ## Import skipped roms from the 'missing.json' file
	php bin/console import-skipped 'json'
import-missing-files: ## Import skipped roms from the data within folders under /skipped
	php bin/console import-skipped 'files'
build-all: ## Builds every combination of template/variant with default options
	php bin/console build-all
build-last-debug: ## Builds last in debug mode
	APP_ENV=dev php bin/console build-last-run -vvv
build-last: ## Builds using the last set of choices you made in the builder
	php bin/console build-last-run
new-template:  ## Creates a new template folder, ready for editing
	php bin/console new-template
theme-to-default: ## Transforms theme data from /themes into default date for overriding make configs
	php bin/console theme-to-default
template-to-hugo: ## Updates data used in documentation from template makefile data
	php bin/console template-to-hugo '../boxart-buddy-docs'
stan: ## run static analysis
	vendor-php/bin/phpstan
update: ## Update Boxart Buddy to latest version
	bash update.sh
portmaster-names: ## Prints a list of portmaster games, useful to find the correct name for config files
	php bin/console portmaster-read-metadata name
validate-install:
	php bin/console validate-install
delete-previews:
	php bin/console delete-previews
crush-cache:
	php bin/console crush-cache
help:
	@egrep -h '\s##\s' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m  %-70s\033[0m %s\n", $$1, $$2}'

.PHONY: bootstrap
.PHONY: dl-cache
.PHONY: scrape
.PHONY: scrape-missing
.PHONY: build
.PHONY: import-missing-json
.PHONY: import-missing-files
.PHONY: build-all
.PHONY: build-last-vvv
.PHONY: build-last
.PHONY: new-template
.PHONY: theme-to-default
.PHONY: template-to-hugo
.PHONY: help
.PHONY: stan
.PHONY: portmaster-names
.PHONY: hugo-server
.PHONY: validate-install
.PHONY: delete-previews
.PHONY: crush-cache
.DEFAULT_GOAL := help
