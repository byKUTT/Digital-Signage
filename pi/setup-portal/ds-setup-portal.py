#!/usr/bin/env python3
"""
ds-setup-portal — first-boot WiFi + WordPress site setup for the Digital
Signage Raspberry Pi image.

If this device has never been configured (no /etc/digital-signage-kiosk.conf
yet — see ConditionPathExists in ds-setup.service, which is what keeps this
from ever running again once setup completes), it puts up a temporary open
WiFi hotspot ("Digital-Signage-Setup-XXXX") and serves a one-page form over
HTTP on that hotspot's IP: pick/enter a WiFi network + password, and enter
the WordPress site URL. On submit it connects to that WiFi, verifies it can
reach the site, hands off to install-kiosk.sh to do the rest (generate this
device's permanent pairing token, enable console autologin, deploy the
kiosk + ds-agent), and reboots straight into the pairing screen.

Standard library only. Runs as root (see ds-setup.service).
"""

import html
import json
import re
import socket
import subprocess
import sys
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, HTTPServer

CONFIG_FILE = "/etc/digital-signage-kiosk.conf"
INSTALL_SCRIPT = "/usr/local/bin/install-kiosk.sh"
AP_UP_SCRIPT = "/usr/local/bin/ds-setup-ap-up.sh"
AP_DOWN_SCRIPT = "/usr/local/bin/ds-setup-ap-down.sh"
PORTAL_PORT = 80


def log(message):
	print("[ds-setup-portal] %s" % message, flush=True)


def run(cmd, timeout=30):
	try:
		result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, check=False)
		return result.returncode, result.stdout.strip(), result.stderr.strip()
	except Exception as e:  # noqa: BLE001
		return 1, "", str(e)


def scan_networks():
	"""Best-effort list of nearby SSIDs to prefill the form; failures just mean
	the user types the network name manually instead."""
	run(["nmcli", "device", "wifi", "rescan"], timeout=10)
	code, out, _ = run(["nmcli", "-t", "-f", "SSID,SIGNAL", "device", "wifi", "list"], timeout=15)
	if code != 0:
		return []
	seen = set()
	networks = []
	for line in out.splitlines():
		parts = line.split(":")
		if not parts or not parts[0] or parts[0] in seen:
			continue
		seen.add(parts[0])
		signal = parts[1] if len(parts) > 1 else ""
		networks.append((parts[0], signal))
	networks.sort(key=lambda n: -int(n[1] or 0))
	return networks[:15]


PAGE_TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Digital Signage Setup</title>
<style>
	* {{ box-sizing: border-box; }}
	body {{ margin:0; padding: 32px 20px; background:#0b0e14; color:#e4e7ee; font-family:-apple-system,'Segoe UI',Roboto,sans-serif; }}
	.card {{ max-width: 420px; margin: 0 auto; }}
	h1 {{ font-size: 22px; font-weight: 600; margin: 0 0 6px; }}
	p.sub {{ color:#8b93a7; margin: 0 0 28px; font-size: 14px; }}
	label {{ display:block; font-size: 13px; font-weight: 600; margin: 18px 0 6px; color:#cdd4e0; }}
	input, select {{ width:100%; padding: 11px 12px; border-radius: 8px; border: 1px solid #2a3040; background:#161b26; color:#fff; font-size: 15px; }}
	button {{ width:100%; margin-top: 28px; padding: 13px; border-radius: 8px; border: none; background:#2271b1; color:#fff; font-size: 15px; font-weight: 600; cursor:pointer; }}
	.hint {{ color:#6b7690; font-size: 12px; margin-top: 6px; }}
	.msg {{ background:#1c2230; border-left: 3px solid #2271b1; padding: 10px 14px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }}
	.msg-error {{ border-left-color: #d63638; }}
</style>
</head>
<body>
	<div class="card">
		<h1>Set up this screen</h1>
		<p class="sub">Connect it to WiFi and your WordPress site, once.</p>
		{message}
		<form method="post" action="/setup">
			<label for="ssid">WiFi network</label>
			<input list="networks" id="ssid" name="ssid" autocomplete="off" required />
			<datalist id="networks">{network_options}</datalist>
			<label for="password">WiFi password</label>
			<input type="password" id="password" name="password" autocomplete="off" />
			<div class="hint">Leave blank for an open network.</div>
			<label for="site">WordPress site URL</label>
			<input type="url" id="site" name="site" placeholder="https://yourdomain.com" required />
			<button type="submit">Connect &amp; Continue</button>
		</form>
	</div>
</body>
</html>"""


def render_page(message_html=""):
	networks = scan_networks()
	options = "".join('<option value="%s">' % html.escape(ssid) for ssid, _ in networks)
	return PAGE_TEMPLATE.format(message=message_html, network_options=options).encode("utf-8")


WAITING_PAGE = """<!DOCTYPE html>
<html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Connecting…</title>
<style>
body{margin:0;padding:60px 20px;background:#0b0e14;color:#e4e7ee;font-family:-apple-system,sans-serif;text-align:center;}
h1{font-weight:300;font-size:22px;}
p{color:#8b93a7;}
</style></head>
<body><h1>Connecting…</h1><p>This screen will restart automatically once set up. You can close this page.</p></body></html>"""


class Handler(BaseHTTPRequestHandler):
	def log_message(self, fmt, *args):  # noqa: A003 - quiet the default access log
		log(fmt % args)

	def _send_html(self, body, status=200):
		self.send_response(status)
		self.send_header("Content-Type", "text/html; charset=utf-8")
		self.send_header("Content-Length", str(len(body)))
		self.end_headers()
		self.wfile.write(body)

	def do_GET(self):  # noqa: N802 - BaseHTTPRequestHandler naming
		# Answer every path with the setup form (helps trigger captive-portal
		# auto-popup on phones/laptops that probe a fixed URL for connectivity).
		self._send_html(render_page())

	def do_POST(self):  # noqa: N802
		length = int(self.headers.get("Content-Length", 0))
		body = self.rfile.read(length).decode("utf-8")
		fields = urllib.parse.parse_qs(body)
		ssid = (fields.get("ssid") or [""])[0].strip()
		password = (fields.get("password") or [""])[0]
		site = (fields.get("site") or [""])[0].strip()

		if not ssid or not site or not re.match(r"^https?://", site):
			msg = '<div class="msg msg-error">Enter a WiFi network and a valid site URL (starting with http:// or https://).</div>'
			self._send_html(render_page(msg), status=400)
			return

		self._send_html(WAITING_PAGE.encode("utf-8"))
		# Do the actual work after responding, so the phone's browser doesn't hang
		# waiting for a response on a WiFi connection we're about to tear down.
		threading_apply(ssid, password, site)

	def do_HEAD(self):  # noqa: N802 - some captive-portal probes use HEAD
		self.send_response(200)
		self.send_header("Content-Length", "0")
		self.end_headers()


def threading_apply(ssid, password, site):
	import threading

	def worker():
		apply_setup(ssid, password, site)

	threading.Thread(target=worker, daemon=True).start()


def apply_setup(ssid, password, site):
	log("Tearing down setup hotspot, connecting to '%s'…" % ssid)
	run([AP_DOWN_SCRIPT], timeout=20)
	time.sleep(2)

	if password:
		code, out, err = run(["nmcli", "device", "wifi", "connect", ssid, "password", password], timeout=45)
	else:
		code, out, err = run(["nmcli", "device", "wifi", "connect", ssid], timeout=45)

	if code != 0:
		log("WiFi connect failed (%s) — bringing the setup hotspot back up." % (err or out))
		run([AP_UP_SCRIPT], timeout=20)
		return

	if not wait_for_connectivity(site, attempts=10):
		log("Connected to WiFi but couldn't reach %s — bringing the setup hotspot back up." % site)
		run([AP_UP_SCRIPT], timeout=20)
		return

	log("Connectivity confirmed. Running install-kiosk.sh…")
	code, out, err = run([INSTALL_SCRIPT, site, "pi"], timeout=600)
	if code != 0:
		log("install-kiosk.sh failed: %s" % (err or out))
		return

	log("Setup complete — rebooting into the kiosk.")
	run(["reboot"], timeout=5)


def wait_for_connectivity(site, attempts=10):
	host = urllib.parse.urlparse(site).hostname or site
	for _ in range(attempts):
		try:
			socket.setdefaulttimeout(5)
			socket.gethostbyname(host)
			return True
		except Exception:  # noqa: BLE001
			time.sleep(3)
	return False


def already_configured():
	try:
		with open(CONFIG_FILE, "r", encoding="utf-8") as f:
			return "DS_KIOSK_SITE=" in f.read()
	except FileNotFoundError:
		return False


def main():
	if already_configured():
		log("Already configured — nothing to do.")
		sys.exit(0)

	log("Not configured yet — starting setup hotspot + portal on port %d." % PORTAL_PORT)
	run([AP_UP_SCRIPT], timeout=30)

	server = HTTPServer(("0.0.0.0", PORTAL_PORT), Handler)
	try:
		server.serve_forever()
	except KeyboardInterrupt:
		pass


if __name__ == "__main__":
	main()
