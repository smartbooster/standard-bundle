// Passive scan rule: detects a phpinfo() output in the body of a response.
//
// The native Hidden File Finder rule only probes four known paths (phpinfo.php,
// info.php, i.php, test.php). This one analyses the content, so it applies to any URL
// reached by the scan, including a plain application route.
//
// Injected into the plan by dast_scan/build-plan.sh, enabled in the three modes.

const ScanRuleMetadata = Java.type("org.zaproxy.addon.commonlib.scanrules.ScanRuleMetadata");

function getMetadata() {
    return ScanRuleMetadata.fromYaml(`
id: 100001
name: Exposed phpinfo() output
description: >
  The response contains the output of the PHP phpinfo() function. It discloses the PHP
  version, the server configuration, the absolute filesystem paths, the loaded extensions
  and sometimes environment variables, which makes it easier to target a later attack.
solution: >
  Remove the call to phpinfo() from the publicly exposed code. If the page is still needed
  for diagnostics, restrict it to authenticated access and to internal IP addresses.
references:
  - https://www.php.net/manual/en/function.phpinfo.php
risk: HIGH
confidence: HIGH
cweId: 200
wascId: 13
status: alpha
`);
}

// The title alone is enough, it is phpinfo()'s signature. Otherwise the PHP version plus
// two labels of its configuration table are required, so as not to alert on a page that
// would merely mention phpinfo().
const TITLE_PATTERN = /<title>\s*phpinfo\(\)\s*<\/title>/i;
const VERSION_PATTERN = /PHP Version\s*(?:<\/[a-z0-9]+>\s*)*[0-9]+\.[0-9]+/i;
const CONFIG_MARKERS = [
    "Configuration File (php.ini) Path",
    "Loaded Configuration File",
    "Server API",
    "Build Date",
];

function findEvidence(body) {
    const title = TITLE_PATTERN.exec(body);
    if (title !== null) {
        return title[0];
    }

    const version = VERSION_PATTERN.exec(body);
    if (version === null) {
        return null;
    }

    let markers = 0;
    for (let i = 0; i < CONFIG_MARKERS.length; i++) {
        if (body.indexOf(CONFIG_MARKERS[i]) !== -1) {
            markers++;
        }
    }
    return markers >= 2 ? version[0] : null;
}

function scan(ps, msg, src) {
    // phpinfo() produces HTML: this avoids analysing assets, images and fonts.
    const contentType = msg.getResponseHeader().getHeader("Content-Type");
    if (contentType !== null && String(contentType).toLowerCase().indexOf("html") === -1) {
        return;
    }

    const body = String(msg.getResponseBody().toString());
    const evidence = findEvidence(body);
    if (evidence === null) {
        return;
    }

    ps.newAlert()
        .setEvidence(evidence)
        .setOtherInfo("Detected by analysing the response body, regardless of the URL.")
        .raise();
}
