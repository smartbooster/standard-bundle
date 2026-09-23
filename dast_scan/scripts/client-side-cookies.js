// Passive scan rule: cookies written by client-side JavaScript.
//
// A cookie written through document.cookie never appears in a Set-Cookie response header, so
// the native rules 10010 (HttpOnly) and 10011 (Secure) are structurally blind to it - as is any
// scanner that only looks at responses. Qualys sees those cookies because its crawler reads them
// from the browser, and reports them as QID 150122 (no Secure) and QID 150123 (no HttpOnly).
//
// This rule works on what is always observable: the JavaScript actually served. A cookie written
// there can never be HttpOnly, and its Secure attribute depends solely on client code, so it is
// worth an explicit review - and the rule fires whether or not the crawl happened to trigger the
// write, which is what makes it reliable.
//
// Only writes naming the cookie are reported. A bare "document.cookie = someVariable" is the
// plumbing of every cookie library and would alert on each bundle embedding one. A name built
// from a template literal - cookies.set(`${s}`, ...), as shipped by swagger-ui - is the same
// case: what the regex captures is the template, not a cookie name.

const ScanRuleMetadata = Java.type("org.zaproxy.addon.commonlib.scanrules.ScanRuleMetadata");

function getMetadata() {
    return ScanRuleMetadata.fromYaml(`
id: 9000003
name: Cookie written by client-side JavaScript
description: >
  The served JavaScript writes a cookie from the browser. Such a cookie cannot carry the HttpOnly
  attribute (it would break the code reading it back), and its Secure attribute is set by the
  client code alone, not by the server: both have to be reviewed in the code rather than assumed.
solution: >
  Keep client-side cookies free of any sensitive value, and make sure the code setting them forces
  the Secure attribute when the page is served over HTTPS. Move to a server-set cookie whenever the
  value does not have to be read by JavaScript.
references:
  - https://cwe.mitre.org/data/definitions/1004.html
  - https://cwe.mitre.org/data/definitions/614.html
risk: LOW
confidence: HIGH
cweId: 1004
wascId: 13
alertTags:
  QUALYS_QID: "150122 / 150123"
status: alpha
`);
}

// cookies.set("name", ...) / $cookies.set('name') / Cookies.set(`name`) - the call sites of the
// usual libraries (vue-cookies, js-cookie...), which survive minification.
const LIBRARY_WRITE_PATTERN = /(?:\$?cookies|cookieStore)\s*\.\s*set\s*\(\s*["'`]([^"'`]+)["'`]/gi;

// document.cookie = "name=..." - the raw write, when the name is a literal.
const RAW_WRITE_PATTERN = /document\s*\.\s*cookie\s*=\s*["'`]([^="'`]+)=/gi;

function isJavaScript(msg) {
    const contentType = msg.getResponseHeader().getHeader("Content-Type");
    return contentType !== null && String(contentType).toLowerCase().indexOf("javascript") !== -1;
}

// A ${...} left in the captured text means the name is assembled at runtime: nothing to report.
function isDynamic(name) {
    return name.indexOf("${") !== -1;
}

function collect(body, pattern, names) {
    pattern.lastIndex = 0;
    let match;
    while ((match = pattern.exec(body)) !== null) {
        const name = match[1].trim();
        if (name.length > 0 && !isDynamic(name) && names.indexOf(name) === -1) {
            names.push(name);
        }
    }
}

function scan(ps, msg, src) {
    if (!msg.getResponseHeader().isHtml() && !isJavaScript(msg)) {
        return;
    }

    const body = String(msg.getResponseBody().toString());
    const names = [];
    collect(body, LIBRARY_WRITE_PATTERN, names);
    collect(body, RAW_WRITE_PATTERN, names);

    for (let i = 0; i < names.length; i++) {
        ps.newAlert()
            .setParam(names[i])
            .setEvidence(names[i])
            .setOtherInfo(
                "Cookie written from JavaScript, so it can carry neither HttpOnly (Qualys QID 150123) "
                + "nor a server-controlled Secure attribute (Qualys QID 150122). Check the code that "
                + "sets it."
            )
            .setMessage(msg)
            .raise();
    }
}
