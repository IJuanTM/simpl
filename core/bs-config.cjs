// CommonJS on purpose (.cjs, not .js): package.json sets "type": "module".
// browser-sync require()s this file, which throws ERR_REQUIRE_ESM under plain .js in an ESM package.
const fs = require('fs');
const path = require('path');

// APP_URL's scheme decides whether this dev server should speak TLS at all, not just which cert to use.
// A plain WAMP/XAMPP setup has no HTTPS anywhere to match.
// Forcing HTTPS on this port anyway would trade Docker's old ERR_SSL_PROTOCOL_ERROR bug for an unprompted self-signed-cert warning on a site that never uses HTTPS.
function isHttps() {
  try {
    const env = fs.readFileSync(path.join(__dirname, 'src', '.env'), 'utf8');
    return (env.match(/^APP_URL\s*=\s*(\S+)/m)?.[1] ?? '').startsWith('https://');
  } catch {
    return true;
  }
}

// Falls back to browser-sync's own self-signed cert if mkcert hasn't been run (docker/README.md's optional step).
// Reusing the same cert the app container serves matters once HSTS is in play: once the browser trusts the app's own HTTPS URL, it refuses any port on that host without a trusted cert, including this dev server's, with no click-through warning to fall back on.
const key = path.join(__dirname, 'docker', 'certs', 'simpl.key');
const cert = path.join(__dirname, 'docker', 'certs', 'simpl.crt');
const hasTrustedCert = fs.existsSync(key) && fs.existsSync(cert);

// Uses URL.hostname, not URL.host: host keeps any non-default APP_URL port, but browser-sync's `host` option
// always appends its own port on top, and the resulting double-port URL (e.g. host:8092:3000) silently breaks `open: 'external'`.
function appHost() {
  try {
    return new URL('@app-url/').hostname;
  } catch {
    return undefined;
  }
}

module.exports = {
  proxy: '@app-url/',
  host: appHost(),
  files: ['src/public/js/**/*.js', 'src/public/css/**/*.css', 'src/views/**/*.phtml'],
  notify: false,
  ui: false,
  open: 'external',
  https: isHttps() ? (hasTrustedCert ? {key, cert} : true) : false
};
