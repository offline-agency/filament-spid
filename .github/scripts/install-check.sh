#!/usr/bin/env bash
# Install the distributable archive of this package into a fresh Laravel app,
# following docs/snippets/quickstart.sh (the commands the README publishes),
# then check the result over HTTP.
#
# What differs from a user's install, and why:
# - The package comes from `composer archive` of HEAD (so .gitattributes
#   export-ignore applies, as on Packagist) through an artifact repository.
#   The archive gets "version": "$PACKAGE_VERSION" written into its
#   composer.json, because an artifact repository needs one; that version is
#   never published. The quickstart line `composer require
#   offline-agency/filament-spid:^1.0` is the only line replaced, by one
#   requiring that version. After a release, set PACKAGE_SOURCE=packagist to
#   run the quickstart unchanged against Packagist.
# - A synthetic, short-lived SP key pair is generated for the run.
#
# Usage: install-check.sh <workdir>   (env: LARAVEL=13 FILAMENT=5)
set -euo pipefail

PACKAGE_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${1:?usage: install-check.sh <workdir>}"
LARAVEL="${LARAVEL:-13}"
FILAMENT="${FILAMENT:-5}"
PACKAGE_VERSION="${PACKAGE_VERSION:-1.0.0-RC999}"
PACKAGE_SOURCE="${PACKAGE_SOURCE:-archive}"
PORT="${PORT:-8123}"
QUICKSTART="$PACKAGE_ROOT/docs/snippets/quickstart.sh"
REQUIRE_LINE='composer require offline-agency/filament-spid:^1.0'

step() { printf '\n==> %s\n' "$*"; }
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }

mkdir -p "$WORK"
WORK="$(cd "$WORK" && pwd)"
APP="$WORK/app"
rm -rf "$APP" "$WORK/artifacts"

if [ "$PACKAGE_SOURCE" = archive ]; then
    step "Archive of HEAD as distributed (version $PACKAGE_VERSION, never published)"
    mkdir -p "$WORK/artifacts" "$WORK/archive"
    (cd "$PACKAGE_ROOT" && composer archive --format=zip --dir="$WORK/archive" --file=filament-spid --no-interaction >/dev/null)
    rm -rf "$WORK/archive/x" && mkdir "$WORK/archive/x"
    (cd "$WORK/archive/x" && unzip -q ../filament-spid.zip)
    php -r '$f=$argv[1]; $j=json_decode(file_get_contents($f), true); $j["version"]=$argv[2]; file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");' "$WORK/archive/x/composer.json" "$PACKAGE_VERSION"
    (cd "$WORK/archive/x" && zip -qr "$WORK/artifacts/filament-spid-$PACKAGE_VERSION.zip" .)
    for excluded in tests .github phpstan.neon.dist phpunit.xml.dist; do
        [ ! -e "$WORK/archive/x/$excluded" ] || fail "$excluded is in the distributed archive"
    done
    for shipped in resources/boost/guidelines/core.blade.php resources/dist/filament-spid-button.js examples/setup.public.json; do
        [ -e "$WORK/archive/x/$shipped" ] || fail "$shipped is missing from the distributed archive"
    done
fi

step "Fresh Laravel $LARAVEL application with a Filament $FILAMENT panel"
composer create-project "laravel/laravel:^$LARAVEL.0" "$APP" --no-interaction --prefer-dist --quiet
cd "$APP"
if [ "$PACKAGE_SOURCE" = archive ]; then
    composer config repositories.filament-spid-candidate artifact "$WORK/artifacts"
fi
composer require "filament/filament:^$FILAMENT.0" --no-interaction --quiet
php artisan filament:install --panels --no-interaction >/dev/null

step "Quickstart, as published in docs/snippets/quickstart.sh"
grep -qxF "$REQUIRE_LINE" "$QUICKSTART" || fail "the quickstart no longer contains: $REQUIRE_LINE"
SCRIPT="$WORK/quickstart.sh"
if [ "$PACKAGE_SOURCE" = archive ]; then
    # The one substitution: the candidate archive instead of Packagist.
    awk -v line="$REQUIRE_LINE" -v with="composer require offline-agency/filament-spid:$PACKAGE_VERSION" '$0 == line { print with; next } { print }' "$QUICKSTART" > "$SCRIPT"
else
    cp "$QUICKSTART" "$SCRIPT"
fi

# Synthetic SP key pair and setup.json, the inputs the quickstart expects.
mkdir -p storage/app/private/spid
openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 30 -subj "/CN=sp.example.test/O=Synthetic/C=IT/L=Roma" \
    -keyout storage/app/private/spid/sp.key -out storage/app/private/spid/sp.crt 2>/dev/null
chmod 600 storage/app/private/spid/sp.key
php -r '$j=json_decode(file_get_contents($argv[1]), true); $j["sp"]["certificate_file"]=$argv[2]."/sp.crt"; $j["sp"]["private_key_file"]=$argv[2]."/sp.key"; file_put_contents("setup.json", json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' \
    "$PACKAGE_ROOT/examples/setup.public.json" "$APP/storage/app/private/spid"

git init -q && git add -A && git -c user.email=ci@example.test -c user.name=ci commit -qm "before filament-spid" >/dev/null
COMPOSER_EXIT_ON_PATCH_FAILURE=1 bash -euo pipefail "$SCRIPT"

step "The SPID patch reached php-saml"
test -f vendor/onelogin/php-saml/PATCHES.txt || fail "PATCHES.txt missing"
grep -q "Compatibility with SPID" vendor/onelogin/php-saml/PATCHES.txt || fail "SPID patch not listed"
composer audit --no-interaction

step "Running the installer again changes nothing"
git add -A && git -c user.email=ci@example.test -c user.name=ci commit -qm "after filament-spid" >/dev/null
php artisan filament-spid:install --panel=admin --input=setup.json --no-interaction | tee "$WORK/second-run.log"
git status --porcelain | grep -v '^?? setup.json$' && fail "the second run changed files" || true
[ "$(grep -c 'SpidPlugin::make()' app/Providers/Filament/AdminPanelProvider.php)" = 1 ] || fail "plugin registered more than once"
[ "$(ls database/migrations/*_add_spid_fields_to_users_table.php | wc -l | tr -d ' ')" = 1 ] || fail "migration published more than once"

# The CSRF probe for the HTTP checks (part of this test only, not of the guide).
printf "\nRoute::post('/csrf-probe', fn () => 'reached');\n" >> routes/web.php

step "Caches"
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan filament-spid:doctor --json > "$WORK/doctor.json" || true
php -r '$r=json_decode(file_get_contents($argv[1]), true) ?? exit(1); foreach ($r["checks"] as $c) { if ($c["status"]==="fail") { fwrite(STDERR, "doctor: {$c["id"]} failed: {$c["message"]}\n"); exit(1); } } echo "doctor: {$r["overall_status"]}\n";' "$WORK/doctor.json" || fail "the doctor reports a failure"

step "HTTP checks"
php artisan serve --port="$PORT" >"$WORK/serve.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PORT/up" && break; sleep 0.2; done
BASE="http://127.0.0.1:$PORT"
JAR="$WORK/cookies.txt"; rm -f "$JAR"

LOGIN=$(curl -s -c "$JAR" -b "$JAR" -w '\n%{http_code}' "$BASE/admin/login")
[ "$(echo "$LOGIN" | tail -1)" = 200 ] || fail "GET /admin/login is not 200"
echo "$LOGIN" | grep -q 'data-spid-button' || fail "the login page has no SPID button"
TOKEN=$(echo "$LOGIN" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//')
[ -n "$TOKEN" ] || fail "no CSRF token in the SPID form"

curl -s -o "$WORK/metadata.xml" -w '%{http_code}' "$BASE/spid/metadata" | grep -qx 200 || fail "GET /spid/metadata is not 200"
grep -q 'AttributeConsumingService index="0"' "$WORK/metadata.xml" || fail "metadata without the SPID patch (AttributeConsumingService index)"
grep -q 'isDefault="true"' "$WORK/metadata.xml" || fail "metadata without the SPID patch (isDefault)"

# The AuthnRequest the library builds from the real form (cookies + CSRF),
# sent to the local test IdP: decoded from the redirect, never sent anywhere.
LOCATION=$(curl -s -o /dev/null -c "$JAR" -b "$JAR" -w '%{redirect_url}' -X POST --data-urlencode "_token=$TOKEN" --data-urlencode "provider=test" "$BASE/spid/login")
case "$LOCATION" in *SAMLRequest=*) ;; *) fail "POST /spid/login did not redirect with an AuthnRequest ($LOCATION)";; esac
php -r 'parse_str(parse_url($argv[1], PHP_URL_QUERY), $q); $xml = gzinflate(base64_decode($q["SAMLRequest"])); foreach (["AttributeConsumingServiceIndex=\"0\"", "Format=\"urn:oasis:names:tc:SAML:2.0:nameid-format:entity\"", "https://www.spid.gov.it/SpidL2"] as $needle) { if (! str_contains($xml, $needle)) { fwrite(STDERR, "AuthnRequest lacks $needle\n"); exit(1); } } echo "AuthnRequest: SPID patch applied, SpidL2 requested\n";' "$LOCATION" || fail "AuthnRequest check"

# An ACS POST with no CSRF token and no assertion: past CSRF (not 419), and
# refused by the library, leaving nobody signed in.
ACS=$(curl -s -o "$WORK/acs.html" -c "$JAR" -b "$JAR" -w '%{http_code}' -X POST "$BASE/spid/acs")
[ "$ACS" != 419 ] || fail "the ACS is not exempt from CSRF"
case "$ACS" in 2*) fail "an empty ACS POST was accepted ($ACS)";; esac
ADMIN=$(curl -s -o /dev/null -c "$JAR" -b "$JAR" -w '%{http_code} %{redirect_url}' "$BASE/admin")
case "$ADMIN" in 302*login*) ;; *) fail "after the ACS POST /admin is not a redirect to login ($ADMIN)";; esac

# Any other POST without a token is still refused: a probe route added to the
# throwaway app only, with no auth middleware in front of CSRF verification.
OTHER=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/csrf-probe")
[ "$OTHER" = 419 ] || fail "POST /csrf-probe without a token answered $OTHER, not 419: CSRF protection is too wide"

if [ -n "${SPID_SP_TEST_BIN:-}" ]; then
    step "spid_sp_test on the served metadata"
    php artisan filament-spid:metadata:validate --file="$WORK/metadata.xml" --profile=spid-sp-public --tool="$SPID_SP_TEST_BIN" --report="$WORK/metadata-report.json"
fi

echo
echo "PASS install-check (Laravel $LARAVEL, Filament $FILAMENT, source $PACKAGE_SOURCE)"
