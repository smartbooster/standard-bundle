// Passive scan rule: link opening a third-party page in a new tab without rel="noopener".
//
// The native rule 10108 (Blank link target) only alerts when rel explicitly contains "opener"
// without "noopener": a plain <a target="_blank"> carrying no rel at all - the exact pattern
// Qualys reports as QID 150222 - never triggers it, at any threshold.

const ScanRuleMetadata = Java.type("org.zaproxy.addon.commonlib.scanrules.ScanRuleMetadata");

function getMetadata() {
    return ScanRuleMetadata.fromYaml(`
id: 9000002
name: Link to another domain opened with target="_blank" and no rel="noopener"
description: >
  The page links to another domain with target="_blank" but without rel="noopener" nor
  rel="noreferrer". The opened page can then reach the opener through window.opener and
  redirect the original tab, for instance to a phishing page (reverse tabnabbing).
solution: >
  Add rel="noopener noreferrer" to every link carrying target="_blank".
references:
  - https://cwe.mitre.org/data/definitions/1022.html
risk: LOW
confidence: HIGH
cweId: 1022
wascId: 15
alertTags:
  QUALYS_QID: "150222"
status: alpha
`);
}

const ANCHOR_PATTERN = /<(?:a|area)\b[^>]*>/gi;

function attribute(tag, name) {
    const match = new RegExp(name + '\\s*=\\s*("([^"]*)"|\'([^\']*)\'|([^\\s">]+))', "i").exec(tag);
    if (match === null) {
        return null;
    }
    const value = match[2] !== undefined ? match[2] : (match[3] !== undefined ? match[3] : match[4]);
    return value === undefined ? null : value;
}

function hostOf(url) {
    const match = /^https?:\/\/([^/?#]+)/i.exec(url);
    return match === null ? null : match[1].toLowerCase();
}

function scan(ps, msg, src) {
    if (!msg.getResponseHeader().isHtml()) {
        return;
    }

    const host = String(msg.getRequestHeader().getHostName()).toLowerCase();
    const body = String(msg.getResponseBody().toString());
    const reported = {};

    ANCHOR_PATTERN.lastIndex = 0;
    let match;
    while ((match = ANCHOR_PATTERN.exec(body)) !== null) {
        const tag = match[0];

        const target = (attribute(tag, "target") || "").toLowerCase();
        if (target !== "_blank") {
            continue;
        }

        const rel = (attribute(tag, "rel") || "").toLowerCase();
        if (rel.indexOf("noopener") !== -1 || rel.indexOf("noreferrer") !== -1) {
            continue;
        }

        // Relative links stay on the same origin: only absolute links to another host matter.
        const href = attribute(tag, "href");
        if (href === null) {
            continue;
        }
        const linkHost = hostOf(href);
        if (linkHost === null || linkHost === host) {
            continue;
        }

        // One alert per target URL is enough, a menu can repeat the same link many times.
        if (reported[href]) {
            continue;
        }
        reported[href] = true;

        ps.newAlert()
            .setParam(href)
            .setEvidence(tag)
            .setOtherInfo("Qualys reports this as QID 150222 (CWE-1022).")
            .setMessage(msg)
            .raise();
    }
}
