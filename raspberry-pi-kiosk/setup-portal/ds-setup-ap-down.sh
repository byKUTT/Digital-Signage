#!/usr/bin/env bash
# Tears down the temporary setup hotspot so the WiFi radio is free to connect
# to the real network as a client.
set -u
nmcli connection down ds-setup-ap >/dev/null 2>&1 || true
nmcli connection delete ds-setup-ap >/dev/null 2>&1 || true
