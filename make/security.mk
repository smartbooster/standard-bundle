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

# The official image ships the release add-ons only: the CORS active scan rule (Zap Alert Id 40040, equivalent of the 150631 from
# Qualys) lives in ascanrulesBeta, which is downloaded into the container. Add-on ids are case sensitive.
DAST_ADDONS      ?= ascanrulesBeta

# Installing in the same run as -autorun does not work: resolving ascanrulesBeta pulls a newer
# commonlib, and swapping it while ZAP is up breaks the scripts that build their metadata from it
# ("Could not initialize class ...ScanRuleMetadata"). Hence two invocations in the same container:
# the first one installs, the second one starts with everything already in place.
ifeq ($(strip $(DAST_ADDONS)),)
DAST_ADDON_CMD   :=
else
DAST_ADDON_CMD   := zap.sh -cmd $(foreach addon,$(DAST_ADDONS),-addoninstall $(addon)) &&
endif

# Generates the plan, named after the make target, then has ZAP execute it.
# $(1) = mode, $(2) = environment declared in targets.json.
# The chmod is there for the CI: ZAP runs under uid 1000 and writes its reports into the
# mounted volume, which the runner creates as root (AccessDeniedException otherwise). The
# plans directory stays untouched, it is mounted read-only.
# zap.log is copied out of the container before it is discarded (--rm): in -cmd mode the log
# never reaches stdout, so it is the only way to read the warnings counted by the report's
# "insight.log.warn". The copy runs after a ";" and not a "&&", and the exit code is saved
# beforehand, so a failing scan - the case where the log matters most - still yields it.
define dast_run
	@mkdir -p $(CURDIR)/$(DAST_PLAN_DIR) $(CURDIR)/$(DAST_REPORT_DIR)
	@chmod 777 $(CURDIR)/$(DAST_REPORT_DIR)
	@$(CURDIR)/dast_scan/build-plan.sh $(1) $(2) $(DAST_TARGETS) $@ \
		> $(CURDIR)/$(DAST_PLAN_DIR)/$@.yaml
	docker run --rm --network host \
		-v $(CURDIR)/$(DAST_PLAN_DIR):/zap/plans:ro \
		-v $(CURDIR)/$(DAST_REPORT_DIR):/zap/wrk:rw \
		$(DAST_IMAGE) sh -c '$(DAST_ADDON_CMD) zap.sh -Xmx$(DAST_JVM_MEM) -cmd -autorun /zap/plans/$@.yaml; RC=$$?; cp -f /home/zap/.ZAP/zap.log /zap/wrk/$@.log 2>/dev/null; exit $$RC'
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
