#!/usr/bin/env bash
# Brings up a temporary open WiFi hotspot ("Digital-Signage-Setup-XXXX") using
# NetworkManager's built-in hotspot support — no hostapd/dnsmasq needed, and
# it won't conflict with NetworkManager's own control of the WiFi interface.
# NM's "shared" IPv4 method also runs its own DHCP/DNS, handing out 10.42.0.1
# as the gateway, so ds-setup-portal.py is reachable at http://10.42.0.1/.
set -u

IFACE="${1:-wlan0}"
MAC_SUFFIX=$(cat /sys/class/net/"$IFACE"/address 2>/dev/null | tr -d ':' | tail -c 5)
SSID="Digital-Signage-Setup-${MAC_SUFFIX:-0000}"

nmcli connection delete ds-setup-ap >/dev/null 2>&1 || true

nmcli connection add type wifi ifname "$IFACE" con-name ds-setup-ap autoconnect no ssid "$SSID"
nmcli connection modify ds-setup-ap 802-11-wireless.mode ap 802-11-wireless.band bg ipv4.method shared
nmcli connection up ds-setup-ap

echo "Setup hotspot up: $SSID (open network, no password) — connect and open http://10.42.0.1/"
