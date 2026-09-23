// Passive scan rule: sensitive form field left with browser autocompletion enabled.
//
// ZAP retired its own rule (10012, "Password Autocomplete in browser") in pscanrules 22
// (2018, issue 4215) because browsers ignore autocomplete="off" on password fields.
// Qualys still reports it as QID 150112, category Vulnerabilities, so the rule is kept here
// for audit parity: the point is to be warned before an external audit is, not to claim the
// finding is exploitable.
//
// A field is considered covered when either the field itself or its enclosing form carries
// an autocomplete value that turns the feature off.
//
// Only type="password" is reported. Guessing sensitivity from the field name was tried and
// dropped: a Symfony form scopes its field names, so the plain e-mail input of the "forgot
// password" form is named forgot_password[email] and matched every "password" heuristic.

const ScanRuleMetadata = Java.type("org.zaproxy.addon.commonlib.scanrules.ScanRuleMetadata");

function getMetadata() {
    return ScanRuleMetadata.fromYaml(`
id: 9000001
name: Sensitive form field has not disabled autocomplete
description: >
  A password input is served without an autocomplete attribute that turns browser autocompletion
  off, neither on the field nor on its enclosing form. On a shared workstation the browser may
  offer to store and replay the value.
solution: >
  Add autocomplete="off" (or "new-password" / "current-password" where a password manager is
  wanted) to the field, or autocomplete="off" to the form that contains it.
references:
  - https://cwe.mitre.org/data/definitions/200.html
risk: LOW
confidence: HIGH
cweId: 200
wascId: 15
alertTags:
  QUALYS_QID: "150112"
status: alpha
`);
}

const FORM_OPEN_PATTERN = /<form\b[^>]*>/gi;
const INPUT_PATTERN = /<input\b[^>]*>/gi;

// Values that actually disable the browser's own storage of the value.
const SAFE_VALUES = ["off", "new-password", "current-password"];

function attribute(tag, name) {
    const match = new RegExp(name + '\\s*=\\s*("([^"]*)"|\'([^\']*)\'|([^\\s">]+))', "i").exec(tag);
    if (match === null) {
        return null;
    }
    const value = match[2] !== undefined ? match[2] : (match[3] !== undefined ? match[3] : match[4]);
    return value === undefined ? null : value;
}

function isSensitive(tag) {
    return (attribute(tag, "type") || "").toLowerCase() === "password";
}

function autocompleteDisabled(tag) {
    const value = (attribute(tag, "autocomplete") || "").toLowerCase();
    return SAFE_VALUES.indexOf(value) !== -1;
}

// Splits the body into segments, each one carrying the <form> tag it belongs to (null outside
// of any form), so that an autocomplete="off" set on the form covers the fields it contains.
function segments(body) {
    const result = [];
    let lastIndex = 0;
    let lastForm = null;

    FORM_OPEN_PATTERN.lastIndex = 0;
    let match;
    while ((match = FORM_OPEN_PATTERN.exec(body)) !== null) {
        result.push({ form: lastForm, html: body.substring(lastIndex, match.index) });
        lastForm = match[0];
        lastIndex = FORM_OPEN_PATTERN.lastIndex;
    }
    result.push({ form: lastForm, html: body.substring(lastIndex) });

    return result;
}

function scan(ps, msg, src) {
    if (!msg.getResponseHeader().isHtml()) {
        return;
    }

    const body = String(msg.getResponseBody().toString());
    const parts = segments(body);

    for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        if (part.form !== null && autocompleteDisabled(part.form)) {
            continue;
        }

        INPUT_PATTERN.lastIndex = 0;
        let match;
        while ((match = INPUT_PATTERN.exec(part.html)) !== null) {
            const tag = match[0];
            if (!isSensitive(tag) || autocompleteDisabled(tag)) {
                continue;
            }

            ps.newAlert()
                .setParam(attribute(tag, "name") || attribute(tag, "id") || "")
                .setEvidence(tag)
                .setOtherInfo("Qualys reports this as QID 150112 (CWE-200).")
                .setMessage(msg)
                .raise();
        }
    }
}
