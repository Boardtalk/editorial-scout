#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# This project owns only disposable test resources; never point it at a live database.
compose=(docker compose -p editorial-scout-tests -f tests/compose.yaml)
trap '"${compose[@]}" down -v --remove-orphans' EXIT
"${compose[@]}" up -d --wait --wait-timeout 120
"${compose[@]}" exec -T wordpress php wp-content/plugins/editorial-scout/tests/install.php
# Preserve DB resolution before replacing DNS with the deterministic fixture.
"${compose[@]}" exec -T wordpress sh -c 'getent hosts db >> /etc/hosts'
"${compose[@]}" exec -T wordpress openssl req -x509 -newkey rsa:2048 -nodes -keyout /tmp/scout-key.pem -out /tmp/scout-cert.pem -days 1 -subj /CN=feeds.example.org -addext subjectAltName=DNS:feeds.example.org 2>/dev/null
"${compose[@]}" exec -d wordpress php wp-content/plugins/editorial-scout/tests/dns.php
"${compose[@]}" exec -d wordpress php wp-content/plugins/editorial-scout/tests/tls.php
"${compose[@]}" exec -T wordpress sh -c 'printf "nameserver 127.0.0.1\noptions timeout:1 attempts:1\n" > /etc/resolv.conf'
"${compose[@]}" exec -T wordpress php wp-content/plugins/editorial-scout/tests/integration.php
