##
## Qualimetry
## ----------
.PHONY: checkstyle cs
checkstyle: ## Symfony Coding Standards Checkstyle
	vendor/bin/php-cs-fixer check -v
cs: checkstyle
checkstyle-diff: ## Symfony Coding Standards Checkstyle with diff
	vendor/bin/php-cs-fixer check -v --diff
cs-diff: checkstyle-diff
checkstyle-fix: ## Symfony Coding Standards Checkstyle fix
	vendor/bin/php-cs-fixer fix -v --diff
cs-fix: checkstyle-fix
checkstyle-report: ## Symfony Coding Standards Checkstyle command that generate a json report and follow up command to get the number of occurence
	vendor/bin/php-cs-fixer check --format=json -v > php_cs_fixer_report.json
	# Then run the following outside of docker : cat php_cs_fixer_report.json | jq -r '.files[] | select(.appliedFixers != null) | .appliedFixers[]' | sort | uniq -c | sort -nr
cs-report: checkstyle-report

.PHONY: lint-php lint-twig lint-yaml lint-container
lint-php: ## Linter PHP
	@files=$$(find src tests config -type f -name '*.php'); \
	total=$$(printf "%s\n" "$$files" | wc -l); \
	current=0; \
	for file in $$files; do \
		current=$$((current + 1)); \
		printf "\rLint PHP [%d/%d]" $$current $$total; \
		output=$$(php -l "$$file"); \
		status=$$?; \
		if [ $$status -ne 0 ]; then \
			printf "\n%s\n" "$$output"; \
			exit $$status; \
		fi; \
	done; \
	printf "\rLint PHP [%d/%d] OK\n" $$total $$total
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
	vendor/bin/phpstan analyse src tests --level=10 --memory-limit=1G -v -c phpstan.neon

.PHONY: phpstan-generate-baseline pgb
phpstan-generate-baseline: ## Launch PHP Static Analysis to generate the baseline
	vendor/bin/phpstan analyse src tests --level=10 --memory-limit=1G -vv -c phpstan.neon --allow-empty-baseline --generate-baseline
pgb: phpstan-generate-baseline

.PHONY: psalm
psalm: ## Psalm taint analysis (SQL injection, XSS...)
	vendor/bin/psalm
pst: psalm

.PHONY: psalm-generate-baseline psgb
psalm-generate-baseline: ## Generate/update the Psalm taint baseline
	vendor/bin/psalm --set-baseline=psalm-taint-baseline.xml
psgb: psalm-generate-baseline

.PHONY: psalm-ci
psalm-ci: ## Psalm taint analysis applying the baseline (only reports new taint findings outside the committed baseline)
	vendor/bin/psalm --use-baseline=psalm-taint-baseline.xml

.PHONY: qualimetry qa
qualimetry: phpstan psalm-ci checkstyle lint-php lint-twig lint-yaml lint-container composer-validate ## Launch all qualimetry rules. Shortcut "make qa"
qa: qualimetry
