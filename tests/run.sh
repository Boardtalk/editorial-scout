#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# This project owns only disposable test resources; never point it at a live database.
compose=(docker compose -p editorial-scout-tests -f tests/compose.yaml)
if [[ -n "${SCOUT_COMPOSE_EXTRA:-}" ]]; then compose+=(-f "$SCOUT_COMPOSE_EXTRA"); fi
cleanup() {
  if [[ "${SCOUT_KEEP_TESTS:-0}" != 1 ]]; then "${compose[@]}" down -v --remove-orphans; fi
}
trap cleanup EXIT
"${compose[@]}" up -d --wait --wait-timeout 120
"${compose[@]}" exec -T wordpress php /opt/scout-tests/install.php
# Preserve DB resolution before replacing DNS with the deterministic fixture.
"${compose[@]}" exec -T wordpress sh -c 'getent hosts db >> /etc/hosts'
"${compose[@]}" exec -T wordpress openssl req -x509 -newkey rsa:2048 -nodes -keyout /tmp/scout-key.pem -out /tmp/scout-cert.pem -days 1 -subj /CN=feeds.example.org -addext subjectAltName=DNS:feeds.example.org,DNS:dual.example.org 2>/dev/null
"${compose[@]}" exec -d wordpress php /opt/scout-tests/dns.php
"${compose[@]}" exec -d wordpress php /opt/scout-tests/tls.php
"${compose[@]}" exec -T wordpress sh -c 'printf "nameserver 127.0.0.1\noptions timeout:1 attempts:1\n" > /etc/resolv.conf'
"${compose[@]}" exec -T wordpress php /opt/scout-tests/preparation-repro.php
"${compose[@]}" exec -T wordpress php /opt/scout-tests/integration.php
