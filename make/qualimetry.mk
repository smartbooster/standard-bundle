
##
## Qualimetry
## ----------
.PHONY: checkstyle cs
checkstyle: ## PHP Checkstyle
	vendor/bin/phpcs
cs: checkstyle

.PHONY: symfony-checkstyle sfcs
symfony-checkstyle: ## Symfony Coding Standards Checkstyle
	vendor/bin/php-cs-fixer check -v
sfcs: symfony-checkstyle
symfony-checkstyle-diff: ## Symfony Coding Standards Checkstyle with diff
	vendor/bin/php-cs-fixer check -v --diff
sfcs-diff: symfony-checkstyle-diff
symfony-checkstyle-report: ## Symfony Coding Standards Checkstyle command that generate a json report and follow up command to get the number of occurence
	vendor/bin/php-cs-fixer check --format=json -v > php_cs_fixer_report.json
	# Then run the following outside of docker : cat php_cs_fixer_report.json | jq -r '.files[] | select(.appliedFixers != null) | .appliedFixers[]' | sort | uniq -c | sort -nr
sfcs-report: symfony-checkstyle-report

.PHONY: code-beautifier cbf
code-beautifier: ## Code beautifier (Checkstyle fixer)
	vendor/bin/phpcbf
cbf: code-beautifier

.PHONY: lint-php lint-twig lint-yaml lint-container
lint-php: ## Linter PHP
	find config src tests -type f -name "*.php" -exec php -l {} \;
lint-twig: ## Linter Twig
	$(CONSOLE) lint:twig templates
lint-yaml: ## Linter Yaml
	$(CONSOLE) lint:yaml config translations
lint-container: ## Linter Container service definitions
	$(CONSOLE) lint:container

.PHONY: composer-validate
composer-validate: ## Validate composer.json and composer.lock
	composer validate composer.json
	composer audit --abandoned=report

.PHONY: metrics
metrics: ## Build static analysis from the php in src. Repports available in ./build/index.html
	cd src && ../vendor/bin/phpmetrics --report-html=../build/phpmetrics .

.PHONY: phpstan
phpstan: ## Launch PHP Static Analysis
	vendor/bin/phpstan analyse src tests --level=10 --memory-limit=1G -c phpstan.neon

.PHONY: qualimetry qa
qualimetry: phpstan checkstyle symfony-checkstyle lint-php lint-twig lint-yaml lint-container composer-validate ## Launch all qualimetry rules. Shortcut "make qa"
qa: qualimetry
