#!/usr/bin/env bash
# Local SPID lab: install -> metadata -> AuthnRequest -> Responses, with the
# pinned spid_sp_test (tools/spid/versions.env) against a throwaway Laravel
# application built from the distributable archive (.github/scripts/install-check.sh).
#
# Run only against this throwaway application: the Response tests send forged
# and broken SAML Responses to its ACS.
#
# It never contacts the hosted AgID Validator or any real IdP; its evidence is
# local (LOCAL_METADATA_CHECK_*, LOCAL_SAML_TESTS_*), see docs/testing/agid.md.
#
# Topology (Linux and Docker Desktop alike): the app runs on the host
# (php artisan serve on 0.0.0.0:$PORT); a Caddy container on the docker network
# "spid-lab" terminates TLS for https://sp.spid.test with a per-run development
# CA (tls internal) and proxies to host.docker.internal:$PORT; the spid_sp_test
# container joins the same network, so sp.spid.test resolves only there. SPID
# needs HTTPS: italia/spid-laravel keeps the request state in Secure,
# SameSite=None cookies. spid_sp_test does not verify the lab CA (its own
# requests use verify=False); nothing else talks to the lab.
#
# Usage: tools/spid/validate.sh <workdir>   (env: LARAVEL=13 FILAMENT=5 PORT=8125)
set -euo pipefail

LAB="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$LAB/../.." && pwd)"
WORK="${1:?usage: validate.sh <workdir>}"
mkdir -p "$WORK"; WORK="$(cd "$WORK" && pwd)"
PORT="${PORT:-8125}"
LAB_NETWORK=spid-lab
LAB_BASE=https://sp.spid.test
export SPID_SP_TEST_NETWORK="$LAB_NETWORK"
TOOL="$LAB/bin/spid_sp_test"
EVIDENCE="$WORK/evidence"
mkdir -p "$EVIDENCE"

step() { printf '\n==> %s\n' "$*"; }
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }

set -a; . "$LAB/versions.env"; set +a

# The network every lab container joins, created first: the tool wrapper
# runs its containers on it from the first call.
docker network inspect "$LAB_NETWORK" >/dev/null 2>&1 || docker network create "$LAB_NETWORK" >/dev/null

step "spid_sp_test $SPID_SP_TEST_VERSION container"
docker build -q -f "$LAB/Dockerfile.spid-sp-test" \
    --build-arg PYTHON_IMAGE="$PYTHON_IMAGE" --build-arg SPID_SP_TEST_VERSION="$SPID_SP_TEST_VERSION" \
    -t filament-spid/spid-sp-test:local "$LAB" >/dev/null
[ "$("$TOOL" --version 2>/dev/null)" = "$SPID_SP_TEST_VERSION" ] || fail "the lab container does not run spid_sp_test $SPID_SP_TEST_VERSION"

step "Install from the distributable archive (metadata checked with spid_sp_test there too)"
SPID_SP_TEST_BIN="$TOOL" bash "$ROOT/.github/scripts/install-check.sh" "$WORK" | tee "$EVIDENCE/install-check.log"
APP="$WORK/app"
cd "$APP"

step "Trust spid_sp_test's test IdP as italia/spid-laravel's 'test' IdP"
"$TOOL" --idp-metadata > "$WORK/test-idp.xml" 2>/dev/null
[ -s "$WORK/test-idp.xml" ] || fail "spid_sp_test printed no IdP metadata"
php "$LAB/lab-configure.php" "$WORK/test-idp.xml" "$LAB_BASE"
php artisan config:clear >/dev/null

step "HTTPS front for $LAB_BASE (Caddy, development CA)"
cat > "$WORK/Caddyfile" <<CADDY
{
	local_certs
	skip_install_trust
}
sp.spid.test {
	tls internal
	reverse_proxy host.docker.internal:$PORT {
		header_up X-Forwarded-Port 443
	}
}
CADDY
docker rm -f spid-lab-proxy >/dev/null 2>&1 || true
docker run -d --name spid-lab-proxy --network "$LAB_NETWORK" --network-alias sp.spid.test \
    --add-host host.docker.internal:host-gateway -v "$WORK/Caddyfile:/etc/caddy/Caddyfile:ro" "$CADDY_IMAGE" >/dev/null

php artisan serve --host=0.0.0.0 --port="$PORT" >"$WORK/lab-serve.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true; docker rm -f spid-lab-proxy >/dev/null 2>&1 || true; docker network rm "$LAB_NETWORK" >/dev/null 2>&1 || true' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PORT/up" && break; sleep 0.2; done
for _ in $(seq 1 50); do
    docker run --rm --network "$LAB_NETWORK" --entrypoint python filament-spid/spid-sp-test:local \
        -c "import requests,sys; sys.exit(0 if requests.get('$LAB_BASE/up', verify=False).status_code == 200 else 1)" >/dev/null 2>&1 && break
    sleep 1
done

curl -s -o "$WORK/lab-metadata.xml" "http://127.0.0.1:$PORT/spid/metadata"
LOGIN_URL="$LAB_BASE/admin/login"

step "AuthnRequest from the real Filament form, checked by spid_sp_test"
set +e
"$TOOL" --metadata-url "file://$WORK/lab-metadata.xml" --authn-url "$LOGIN_URL" \
    -ap filament_spid_lab.FilamentSpidLogin --extra -rf json -o "$EVIDENCE/authn-request.json" >"$EVIDENCE/authn-request.log" 2>&1
AUTHN=$?
set -e
tail -1 "$EVIDENCE/authn-request.log"

step "SAML Responses (positive and negative) to the ACS, panel checked after each"
set +e
"$TOOL" --metadata-url "file://$WORK/lab-metadata.xml" --authn-url "$LOGIN_URL" \
    -ap filament_spid_lab.FilamentSpidLogin -tr -rf json -o "$EVIDENCE/responses.json" >"$EVIDENCE/responses.log" 2>&1
RESPONSES=$?
set -e
tail -1 "$EVIDENCE/responses.log"

step "Sentinel: a citizen the panel refuses must not pass the positive Response"
php artisan tinker --execute='App\Models\User::query()->update(["spid_lab_allowed" => false]);' >/dev/null
set +e
"$TOOL" --metadata-url "file://$WORK/lab-metadata.xml" --authn-url "$LOGIN_URL" \
    -ap filament_spid_lab.FilamentSpidLogin -tr -tn 1 -rf json -o "$EVIDENCE/sentinel-gate.json" >"$EVIDENCE/sentinel-gate.log" 2>&1
GATE=$?
set -e
php artisan tinker --execute='App\Models\User::query()->update(["spid_lab_allowed" => true]);' >/dev/null

step "Sentinels: metadata the adapter must reject"
sentinel() {
    local name="$1" expected="$2" file="$3"; local rc
    set +e
    php artisan filament-spid:metadata:validate --file="$file" --profile=spid-sp-public --tool="$TOOL" --json >"$EVIDENCE/sentinel-$name.json" 2>/dev/null
    rc=$?
    set -e
    echo "  $name: exit $rc (expected $expected)"
    [ "$rc" = "$expected" ] || fail "sentinel $name exited $rc, expected $expected"
}
sed 's/entityID="https:\/\/sp.example.test"/entityID="https:\/\/tampered.example.test"/' "$WORK/metadata.xml" > "$WORK/tampered.xml"
sed 's#<spid:IPACode>[^<]*</spid:IPACode>##' "$WORK/metadata.xml" > "$WORK/no-ipa.xml"
printf '<!DOCTYPE html><html><body>Login</body></html>' > "$WORK/login-page.html"
: > "$WORK/empty.xml"
sentinel tampered-after-signing 1 "$WORK/tampered.xml"
sentinel public-without-ipa-code 1 "$WORK/no-ipa.xml"
sentinel html-page 2 "$WORK/login-page.html"
sentinel empty-file 2 "$WORK/empty.xml"
sentinel valid 0 "$WORK/metadata.xml"

php "$LAB/lab-summary.php" "$EVIDENCE" "$AUTHN" "$RESPONSES" "$GATE" "$SPID_SP_TEST_VERSION" | tee "$EVIDENCE/summary.json"
php -r '$s=json_decode(file_get_contents($argv[1]), true); exit($s["gate"] === "pass" ? 0 : 1);' "$EVIDENCE/summary.json" || fail "the lab gate failed (see $EVIDENCE/summary.json)"
echo "PASS SPID lab"
