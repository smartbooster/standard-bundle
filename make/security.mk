##
## Application security (OWASP ZAP)
## --------------------------------
# Blackbox DAST scans: no authentication, only the public routes of the 4 subdomains
# (see the PUBLIC_ACCESS access_control in config/packages/security.yaml).
# The targets are described in dast_scan/targets.json, the ZAP plan is generated on the
# fly by dast_scan/build-plan.sh. This Makefile knows no URL.
#
# Three levels of increasing invasiveness, each one including the previous:
#   passive : declared routes, with GET  -> no side effect, prod included
#   crawl   : + spider and AJAX spider   -> no form submitted, no attack
#   active  : + forms and active scanner -> disposable environment only

DAST_IMAGE       ?= ghcr.io/zaproxy/zaproxy:stable
DAST_TARGETS     ?= dast_scan/targets.json
DAST_PLAN_DIR    ?= dast_scan/generated
DAST_REPORT_DIR  ?= dast_scan/report

# zap.sh sizes its heap from /proc/meminfo, and only reads the container limit through
# cgroup v1: on a cgroup v2 host it sees the RAM of the machine and reserves a quarter of
# it, well beyond what the CI grants it (SIGKILL, make Error 137). An explicit -Xmx passed
# to zap.sh takes precedence over that computation.
DAST_JVM_MEM     ?= 2g

# Generates the plan, named after the make target, then has ZAP execute it.
# $(1) = mode, $(2) = environment declared in targets.json.
# The chmod is there for the CI: ZAP runs under uid 1000 and writes its reports into the
# mounted volume, which the runner creates as root (AccessDeniedException otherwise). The
# plans directory stays untouched, it is mounted read-only.
define dast_run
	@mkdir -p $(CURDIR)/$(DAST_PLAN_DIR) $(CURDIR)/$(DAST_REPORT_DIR)
	@chmod 777 $(CURDIR)/$(DAST_REPORT_DIR)
	@$(CURDIR)/dast_scan/build-plan.sh $(1) $(2) $(DAST_TARGETS) $@ \
		> $(CURDIR)/$(DAST_PLAN_DIR)/$@.yaml
	docker run --rm --network host \
		-v $(CURDIR)/$(DAST_PLAN_DIR):/zap/plans:ro \
		-v $(CURDIR)/$(DAST_REPORT_DIR):/zap/wrk:rw \
		$(DAST_IMAGE) zap.sh -Xmx$(DAST_JVM_MEM) -cmd -autorun /zap/plans/$@.yaml
endef

.PHONY: dast-blackbox-passive dast-blackbox-crawl dast-blackbox-active dast-blackbox-ci dast-plan

dast-blackbox-passive: ## PASSIVE blackbox DAST scan, locally: replays the routes of targets.json with GET and analyses the responses. No side effect, can be run anywhere.
	$(call dast_run,passive,local)

dast-blackbox-crawl: ## EXPLORATORY blackbox DAST scan, locally: adds spider and AJAX spider to cover assets and XHR calls. No attack, no form submitted.
	$(call dast_run,crawl,local)

dast-blackbox-active: ## ACTIVE blackbox DAST scan, locally: adds form submission and attacks (SQLi, XSS, path traversal). /!\ Disposable environment only.
	$(call dast_run,active,local)

dast-blackbox-ci: ## ACTIVE blackbox DAST scan on the integration environment. /!\ Never on staging nor prod.
	$(call dast_run,active,ci)

dast-plan: ## Prints the generated ZAP plan without running any scan. E.g.: make dast-plan DAST_MODE=active DAST_ENV=ci
	@$(CURDIR)/dast_scan/build-plan.sh $(or $(DAST_MODE),passive) $(or $(DAST_ENV),local) $(DAST_TARGETS)
