#!/usr/bin/env bash
#
# Digital Signage — Raspberry Pi kiosk installer.
#
# Turns a Raspberry Pi (running Raspberry Pi OS, Lite or Desktop, Bullseye or
# Bookworm) into a dedicated signage player: a systemd service (ds-kiosk,
# no console autologin involved) takes tty1 straight to a minimal X session
# and opens this device's player URL full-screen in Chromium with no browser
# chrome. If Chromium ever crashes or the URL is unreachable at boot, a
# watchdog loop relaunches it.
#
# Also deploys ds-agent, a small background service that lets this device be
# managed remotely from the plugin's admin UI — WiFi network, screen
# rotation, reboot, restart-the-browser and check-for-updates, all from the
# Screen's edit page in wp-admin, no SSH/keyboard needed after this point.
#
# The device generates its own pairing token ONCE and stores it in
# /etc/digital-signage-kiosk.conf — every reboot reuses that same token, so
# the pairing code shown on screen (and the screen's identity once paired)
# never changes just because the Pi restarted. Re-running this installer is
# safe and keeps the existing token; pass --regenerate to force a new one
# (e.g. you're re-purposing this SD card for a different physical screen).
#
# Usage:
#   sudo bash install-kiosk.sh https://yourdomain.com [kiosk-user] [--regenerate] [--resolution WIDTHxHEIGHT] [--browser chromium|firefox]
#
# --resolution is only needed for an uncommon/stretched display (e.g. a bar-
# shaped screen like 1920x440) that the Pi's EDID auto-detection gets wrong
# or that isn't a standard mode at all. Leave it out for normal displays —
# they auto-detect correctly. It can also be set later, per-device, from
# wp-admin (Screen edit page > Device > Custom resolution), no SSH needed.
#
# --browser defaults to chromium. Pass "--browser firefox" to use Firefox
# ESR instead — a genuine alternative for a device where Chromium keeps
# showing its translate popup / other UI despite every flag, policy and
# profile-level override this script already applies against it. Firefox
# has no equivalent built-in "detect language, offer to translate" popup by
# default, and gets its own set of anti-popup prefs below.
#
# Run this ON the Raspberry Pi itself (SSH in, or use a keyboard/monitor).

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root: sudo bash install-kiosk.sh <site-url> [user] [--regenerate] [--resolution WxH] [--browser chromium|firefox]" >&2
	exit 1
fi

SITE_URL="${1:-}"
KIOSK_USER="${2:-${SUDO_USER:-pi}}"
REGENERATE=0
RESOLUTION_ARG=""
BROWSER_ARG=""
prev_flag=""
for arg in "$@"; do
	if [ -n "$prev_flag" ]; then
		case "$prev_flag" in
			--resolution) RESOLUTION_ARG="$arg" ;;
			--browser) BROWSER_ARG="$arg" ;;
		esac
		prev_flag=""
		continue
	fi
	[ "$arg" = "--regenerate" ] && REGENERATE=1
	case "$arg" in
		--resolution|--browser) prev_flag="$arg" ;;
	esac
done
# A second positional arg that's actually a flag shouldn't be treated as a username.
case "$KIOSK_USER" in
	--regenerate|--resolution|--browser) KIOSK_USER="${SUDO_USER:-pi}" ;;
esac

if [ -z "$SITE_URL" ]; then
	echo "Usage: sudo bash install-kiosk.sh <site-url> [kiosk-user] [--regenerate] [--resolution WxH] [--browser chromium|firefox]" >&2
	echo "Example: sudo bash install-kiosk.sh https://example.com pi" >&2
	echo "Example with a custom/uncommon resolution: sudo bash install-kiosk.sh https://example.com pi --resolution 1920x440" >&2
	echo "Example using Firefox instead of Chromium: sudo bash install-kiosk.sh https://example.com pi --browser firefox" >&2
	exit 1
fi
SITE_URL="${SITE_URL%/}"

if [ -n "$RESOLUTION_ARG" ] && ! echo "$RESOLUTION_ARG" | grep -qE '^[0-9]{2,5}x[0-9]{2,5}$'; then
	echo "Invalid --resolution '${RESOLUTION_ARG}' — expected WIDTHxHEIGHT, e.g. 1920x440." >&2
	exit 1
fi
if [ -n "$BROWSER_ARG" ] && [ "$BROWSER_ARG" != "chromium" ] && [ "$BROWSER_ARG" != "firefox" ]; then
	echo "Invalid --browser '${BROWSER_ARG}' — expected 'chromium' or 'firefox'." >&2
	exit 1
fi

if ! id "$KIOSK_USER" &>/dev/null; then
	echo "User '$KIOSK_USER' does not exist on this system." >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USER_HOME=$(getent passwd "$KIOSK_USER" | cut -d: -f6)
CONF_FILE="/etc/digital-signage-kiosk.conf"
WATCHDOG="/usr/local/bin/ds-kiosk-loop.sh"
KIOSK_PROFILE="standard"
PI_MODEL="$(tr -d '\000' < /proc/device-tree/model 2>/dev/null || true)"
if echo "$PI_MODEL" | grep -qi 'Raspberry Pi 3'; then
	KIOSK_PROFILE="pi3"
	if [ -f "${SCRIPT_DIR}/optimize-pi.sh" ]; then
		echo "==> Raspberry Pi 3 detected — installing the low-power acceleration profile"
		bash "${SCRIPT_DIR}/optimize-pi.sh"
		install -m 755 "${SCRIPT_DIR}/optimize-pi.sh" /usr/local/bin/ds-optimize-pi.sh
	else
		echo "   ⚠ optimize-pi.sh was not found; continuing without system tuning."
	fi
fi

# --browser wins if given; otherwise reuse whatever this device already used
# (so re-running the installer for other reasons doesn't silently switch a
# working Firefox setup back to Chromium), defaulting to chromium if this is
# a brand-new install.
BROWSER="chromium"
if [ -f "$CONF_FILE" ]; then
	# shellcheck disable=SC1090
	EXISTING_BROWSER=$(. "$CONF_FILE" 2>/dev/null; echo "${DS_KIOSK_BROWSER:-}")
	[ -n "$EXISTING_BROWSER" ] && BROWSER="$EXISTING_BROWSER"
fi
[ -n "$BROWSER_ARG" ] && BROWSER="$BROWSER_ARG"

echo "==> Installing packages (this can take a few minutes)…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y

if [ "$BROWSER" = "firefox" ]; then
	apt-get install -y --no-install-recommends \
		xserver-xorg xinit x11-xserver-utils openbox unclutter python3 firefox-esr
	BROWSER_BIN=$(command -v firefox-esr || true)
	if [ -z "$BROWSER_BIN" ]; then
		echo "Could not find firefox-esr after install." >&2
		exit 1
	fi

	# --- Firefox enterprise policy + prefs: the equivalent of the Chromium
	# section below, in Firefox's own mechanisms. Firefox has no built-in
	# "detect page language, offer to translate" popup enabled by default the
	# way Chromium does — its opt-in Translations feature only ever shows an
	# icon in the (hidden, in kiosk mode) address bar, never an unprompted
	# overlay — but it's still turned off explicitly here, along with the
	# other first-run/update/telemetry prompts a kiosk has no use for. ---
	echo "==> Writing Firefox policy to disable translate/popups"
	mkdir -p /etc/firefox/policies
	cat > /etc/firefox/policies/policies.json <<'POLICY'
{
	"policies": {
		"DisableTelemetry": true,
		"DisableFirefoxAccounts": true,
		"DisablePocket": true,
		"DisableFormHistory": true,
		"OfferToSaveLogins": false,
		"PasswordManagerEnabled": false,
		"DontCheckDefaultBrowser": true,
		"DisableFirefoxStudies": true,
		"UserMessaging": {
			"WhatsNew": false,
			"ExtensionRecommendations": false,
			"FeatureRecommendations": false
		}
	}
}
POLICY

	# Keep Firefox separate from any Chromium profile left by an older install.
	# Firefox is launched as the unprivileged kiosk user below, so that user must
	# own its profile; running Firefox as root is unstable/unsupported on several
	# Raspberry Pi OS + Firefox ESR combinations and caused a 3-second crash loop.
	FIREFOX_PROFILE_DIR="/var/lib/digital-signage-kiosk-firefox-profile"
	mkdir -p "$FIREFOX_PROFILE_DIR"
	cat > "${FIREFOX_PROFILE_DIR}/user.js" <<'USERJS'
// Digital Signage kiosk profile — belt-and-suspenders against any popup on
// an unattended screen with no input device to dismiss one with.
user_pref("browser.translations.enable", false);
user_pref("browser.translations.automaticallyPopup", false);
user_pref("browser.translations.panelShown", true);
user_pref("intl.accept_languages", "et,en-US,en");
user_pref("browser.shell.checkDefaultBrowser", false);
user_pref("browser.aboutwelcome.enabled", false);
user_pref("datareporting.policy.dataSubmissionEnabled", false);
user_pref("toolkit.telemetry.reportingpolicy.firstRun", false);
user_pref("signon.rememberSignons", false);
user_pref("geo.enabled", false);
user_pref("dom.push.enabled", false);
user_pref("browser.sessionstore.resume_from_crash", false);
user_pref("browser.tabs.warnOnClose", false);
user_pref("app.update.auto", false);
// Raspberry Pi OS Bookworm's Firefox ESR supports accelerated compositing and
// V4L2 H.264 decode. The old kiosk profile disabled all of these and forced the
// Pi 3 CPU to render/decode everything in software.
user_pref("gfx.webrender.all", true);
user_pref("gfx.webrender.enabled", true);
user_pref("gfx.webrender.software", false);
user_pref("gfx.x11-egl.force-enabled", true);
user_pref("widget.dmabuf.force-enabled", true);
user_pref("layers.acceleration.disabled", false);
user_pref("media.hardware-video-decoding.enabled", true);
user_pref("media.hardware-video-decoding.force-enabled", true);
user_pref("media.ffmpeg.vaapi.enabled", true);
user_pref("media.rdd-process.enabled", true);
user_pref("media.av1.enabled", false);
user_pref("media.memory_cache_max_size", 32768);
USERJS
	chown -R "$KIOSK_USER":"$KIOSK_USER" "$FIREFOX_PROFILE_DIR"

	# Debian/Raspberry Pi Firefox ESR reads policies from its distribution
	# directory. Keep /etc/firefox/policies too for releases that support it.
	mkdir -p /usr/lib/firefox-esr/distribution
	cp /etc/firefox/policies/policies.json /usr/lib/firefox-esr/distribution/policies.json
else
	# chromium package name differs across Raspberry Pi OS releases.
	CHROMIUM_PKG="chromium-browser"
	if ! apt-cache show chromium-browser >/dev/null 2>&1; then
		CHROMIUM_PKG="chromium"
	fi
	apt-get install -y --no-install-recommends \
		xserver-xorg xinit x11-xserver-utils openbox unclutter python3 "$CHROMIUM_PKG"

	BROWSER_BIN=$(command -v chromium-browser || command -v chromium || true)
	if [ -z "$BROWSER_BIN" ]; then
		echo "Could not find a chromium binary after install." >&2
		exit 1
	fi

	# --- Chromium enterprise policy: belt-and-suspenders against popups. ---
	# Command-line flags (--disable-translate etc., below) cover most of this,
	# but an enterprise policy file always wins regardless of Chromium version
	# quirks or flag-parsing edge cases — this is the same mechanism managed
	# corporate/school Chromebooks use, just pointed at a single kiosk device.
	# The policy directory differs by package name (chromium vs chromium-browser)
	# and doesn't always match the binary name cleanly across Raspberry Pi OS
	# releases, so write to both — Chromium only ever reads the one that
	# actually matches how it was packaged; the other stays inert.
	echo "==> Writing Chromium policy to disable translate/popups"
	POLICY_JSON='{
		"TranslateEnabled": false,
		"DefaultBrowserSettingEnabled": false,
		"BrowserSignin": 0,
		"SyncDisabled": true,
		"PasswordManagerEnabled": false,
		"AutofillAddressEnabled": false,
		"AutofillCreditCardEnabled": false,
		"PromptForDownloadLocation": false,
		"BackgroundModeEnabled": false,
		"DefaultNotificationsSetting": 2,
		"DefaultGeolocationSetting": 2,
		"AlternateErrorPagesEnabled": false,
		"SearchSuggestEnabled": false,
		"SpellcheckEnabled": false
	}'
	for policy_dir in /etc/chromium/policies/managed /etc/chromium-browser/policies/managed; do
		mkdir -p "$policy_dir"
		echo "$POLICY_JSON" > "${policy_dir}/digital-signage-kiosk.json"
	done
fi

# --- Fourth, network-level layer: make Google's translate service
# unreachable outright. TranslateEnabled (policy + Preferences) and
# --disable-features=Translate,TranslateUI should already be enough on
# their own, but if a given Chromium build ignores all three of those, it
# still needs to actually reach translate.google(apis).com to offer/perform
# a translation — black-holing those hostnames in /etc/hosts makes that
# impossible regardless of anything Chromium-side. Idempotent: removes any
# entry this installer wrote before, then re-adds it, so re-running never
# duplicates lines.
echo "==> Blocking Google Translate's hostnames in /etc/hosts"
sed -i '/# digital-signage-kiosk: block translate/d' /etc/hosts
for host in translate.google.com translate.googleapis.com translate-pa.googleapis.com; do
	echo "0.0.0.0 ${host} # digital-signage-kiosk: block translate" >> /etc/hosts
done

# --- One-time cache reset for the 2.8.4 pairing-token recovery release. ---
# Remove only browser-generated cache directories; profiles, pairing token,
# preferences and all other kiosk configuration remain intact. The marker
# makes this destructive cleanup run exactly once on each device.
CACHE_RESET_MARKER="/var/lib/digital-signage-kiosk-cache-reset-284"
if [ ! -f "$CACHE_RESET_MARKER" ]; then
	echo "==> Clearing old kiosk URL caches (one-time 2.8.4 reset)"
	rm -rf /var/lib/digital-signage-kiosk-firefox-profile/cache2
	rm -rf /var/lib/digital-signage-kiosk-firefox-profile/startupCache
	rm -rf /var/lib/digital-signage-kiosk-chromium-profile/Default/Cache
	rm -rf /var/lib/digital-signage-kiosk-chromium-profile/Default/Code\ Cache
	rm -rf /var/lib/digital-signage-kiosk-profile/cache2
	rm -rf /var/lib/digital-signage-kiosk-profile/Default/Cache
	touch "$CACHE_RESET_MARKER"
fi

# --- Persistent device token: generate once, reuse forever (until --regenerate). ---
TOKEN=""
ROTATION="normal"
RESOLUTION=""
if [ -f "$CONF_FILE" ] && [ "$REGENERATE" -eq 0 ]; then
	# shellcheck disable=SC1090
	source "$CONF_FILE"
	TOKEN="${DS_KIOSK_TOKEN:-}"
	ROTATION="${DS_KIOSK_ROTATION:-normal}"
	RESOLUTION="${DS_KIOSK_RESOLUTION:-}"
fi
# An explicit --resolution on the command line always wins over whatever was
# already in the config (that's how you change it later by re-running this
# script; wp-admin's "Custom resolution" field is the other way to change it
# without SSH at all).
if [ -n "$RESOLUTION_ARG" ]; then
	RESOLUTION="$RESOLUTION_ARG"
fi
if [ -z "$TOKEN" ]; then
	# pipefail note: `head -c 40` closes the pipe as soon as it has enough
	# bytes, which sends tr a SIGPIPE while it's still trying to write into
	# /dev/urandom's endless stream. With pipefail on, that counts as the
	# pipeline failing (even though $TOKEN comes out correct) and set -e
	# would silently kill the whole script right here. Disable pipefail for
	# just this one line.
	set +o pipefail
	TOKEN=$(tr -dc 'a-zA-Z0-9' < /dev/urandom | head -c 40)
	set -o pipefail
	echo "==> Generated a new device token (this screen's permanent identity)."
else
	echo "==> Reusing existing device token from ${CONF_FILE}."
fi

# ?kiosk=1 tells the player/pairing screens this browser is already OS-level
# fullscreen (started with --kiosk below) with no chrome to hide and no input
# device to click a "tap to start" prompt with, so they skip that entirely.
URL="${SITE_URL}/signage/play/${TOKEN}/?kiosk=1&profile=${KIOSK_PROFILE}&cv=295"

echo "==> Writing config to ${CONF_FILE}"
cat > "$CONF_FILE" <<EOF
# Digital Signage kiosk configuration.
# DS_KIOSK_TOKEN is this device's permanent identity — do not edit unless you
# intend to re-pair this device as a different screen. Change DS_KIOSK_SITE
# and reboot if the WordPress site moves to a new domain. DS_KIOSK_ROTATION
# and DS_KIOSK_RESOLUTION are normally managed remotely from wp-admin
# (Screen edit page > Device) — DS_KIOSK_RESOLUTION is only needed for an
# uncommon/stretched display; leave it blank for normal auto-detected ones.
# DS_KIOSK_BROWSER is "chromium" or "firefox" — change it by re-running
# install-kiosk.sh with --browser, not by editing this line directly.
DS_KIOSK_TOKEN="${TOKEN}"
DS_KIOSK_SITE="${SITE_URL}"
DS_KIOSK_URL="${URL}"
DS_KIOSK_BROWSER="${BROWSER}"
DS_KIOSK_BROWSER_BIN="${BROWSER_BIN}"
DS_KIOSK_USER="${KIOSK_USER}"
DS_KIOSK_USER_HOME="${USER_HOME}"
DS_KIOSK_PROFILE="${KIOSK_PROFILE}"
DS_KIOSK_ROTATION="${ROTATION}"
DS_KIOSK_RESOLUTION="${RESOLUTION}"
EOF

echo "==> Writing watchdog launcher to ${WATCHDOG}"
cat > "$WATCHDOG" <<'EOF'
#!/usr/bin/env bash
# Relaunches the kiosk browser (Chromium or Firefox — see DS_KIOSK_BROWSER
# below) in kiosk mode if it ever exits/crashes, so an unattended screen
# recovers on its own instead of showing a black screen. Runs as root
# (ds-kiosk.service — see install-kiosk.sh), which is why Chromium needs
# --no-sandbox; that's fine here since this is a dedicated, single-purpose
# kiosk device showing one fixed URL, not general browsing.
set -u
source /etc/digital-signage-kiosk.conf

# Give the network a moment on cold boot before the first load.
sleep 5

# Apply a custom/uncommon resolution set via install-kiosk.sh --resolution or
# remotely from wp-admin (Screen edit page > Device > Custom resolution) —
# e.g. a bar-shaped display like 1920x440 that EDID auto-detection gets
# wrong or that isn't a standard mode the GPU already knows about. cvt
# computes a fitting modeline for the exact size and xrandr adds it as a new
# mode before selecting it. Skipped entirely for normal displays (blank
# DS_KIOSK_RESOLUTION), which keep auto-detecting as before.
if [ -n "${DS_KIOSK_RESOLUTION:-}" ]; then
	res_w="${DS_KIOSK_RESOLUTION%%x*}"
	res_h="${DS_KIOSK_RESOLUTION##*x}"
	# Strip the double quotes cvt wraps the mode name in — left in place, they'd
	# end up as literal characters in the mode name --newmode registers, which
	# then wouldn't match the unquoted $mode_name used below for --addmode/--mode.
	modeline=$(cvt "$res_w" "$res_h" 60 2>/dev/null | grep -o 'Modeline.*' | sed 's/^Modeline //' | tr -d '"')
	mode_name=$(echo "$modeline" | awk '{print $1}')
	if [ -n "$modeline" ]; then
		for output in $(xrandr --listmonitors 2>/dev/null | awk '/ /{print $NF}'); do
			xrandr --newmode $modeline 2>/dev/null || true
			xrandr --addmode "$output" "$mode_name" 2>/dev/null || true
			xrandr --output "$output" --mode "$mode_name" 2>/dev/null || true
		done
	fi
fi

# Apply any rotation set remotely from wp-admin (Screen edit page > Device)
# before the browser starts, so it opens already in the right orientation.
if [ "${DS_KIOSK_ROTATION:-normal}" != "normal" ]; then
	for output in $(xrandr --listmonitors 2>/dev/null | awk '/ /{print $NF}'); do
		xrandr --output "$output" --rotate "$DS_KIOSK_ROTATION" 2>/dev/null || true
	done
fi

if [ "${DS_KIOSK_BROWSER:-chromium}" = "firefox" ]; then
	PROFILE_DIR="/var/lib/digital-signage-kiosk-firefox-profile"
	KIOSK_USER="${DS_KIOSK_USER:-pi}"
	KIOSK_HOME="${DS_KIOSK_USER_HOME:-/home/$KIOSK_USER}"
	KIOSK_UID=$(id -u "$KIOSK_USER")
	KIOSK_RUNTIME_DIR="/run/user/$KIOSK_UID"

	# X itself owns tty1 as root for reliability, but Firefox must not run as
	# root. Grant only the configured local kiosk user access to this X server
	# and provide a correctly owned runtime directory/profile.
	install -d -m 700 -o "$KIOSK_USER" -g "$KIOSK_USER" "$KIOSK_RUNTIME_DIR"
	chown -R "$KIOSK_USER":"$KIOSK_USER" "$PROFILE_DIR"
	xhost +SI:localuser:"$KIOSK_USER" >/dev/null 2>&1 || true

	# Firefox ESR kiosk launch. WebRender and Raspberry Pi OS's hardware H.264
	# path remain enabled; if the driver cannot initialize, Firefox falls back
	# automatically. A modest relaunch backoff prevents crash-refresh loops.
	while true; do
		runuser -u "$KIOSK_USER" -- env \
			HOME="$KIOSK_HOME" \
			USER="$KIOSK_USER" \
			LOGNAME="$KIOSK_USER" \
			DISPLAY="${DISPLAY:-:0}" \
			XDG_RUNTIME_DIR="$KIOSK_RUNTIME_DIR" \
			MOZ_WEBRENDER=1 \
			MOZ_X11_EGL=1 \
			MOZ_ENABLE_WAYLAND=0 \
			"$DS_KIOSK_BROWSER_BIN" \
			-kiosk \
			-no-remote \
			-profile "$PROFILE_DIR" \
			"$DS_KIOSK_URL" \
			>/tmp/ds-kiosk-firefox.log 2>&1 || true
		sleep 15
	done
else
	PROFILE_DIR="/var/lib/digital-signage-kiosk-chromium-profile"
	# Keep Chromium's rendering buffers in /dev/shm. The previous disk-backed
	# fallback prevented one low-memory failure mode but causes visible stutter
	# and extra SD-card I/O during video playback and continuous animation.
	#
	# --user-data-dir instead of --incognito: incognito keeps its whole profile
	# (cache included) in RAM, adding to the same memory pressure. A small
	# disk-backed profile — fine here, this is a single-purpose kiosk showing
	# one fixed URL, not a shared/general-purpose browser — reduces RAM use.
	#
	# Force translate off directly in that profile's own Preferences file, as a
	# THIRD, independent layer alongside the --disable-features flag and the
	# enterprise policy JSON: this is the same underlying pref those other two
	# are ultimately trying to set, written directly so it takes effect
	# regardless of whether policy file discovery or this exact flag are being
	# honored by this particular Chromium build. Runs on every start (not just
	# once) and merges into whatever Preferences already exists — including one
	# Chromium already created for this profile on an earlier run — rather than
	# only writing a fresh file, since a pre-existing file would otherwise never
	# get this override retrofitted onto it.
	mkdir -p "${PROFILE_DIR}/Default"
	python3 - "${PROFILE_DIR}/Default/Preferences" <<'PYEOF' || true
import json, os, sys

path = sys.argv[1]
data = {}
if os.path.exists( path ):
	try:
		with open( path ) as f:
			data = json.load( f )
	except Exception:
		data = {}

data.setdefault( "translate", {} )[ "enabled" ] = False
data[ "translate_blocked_languages" ] = [ "et", "en" ]
data.setdefault( "intl", {} )[ "accept_languages" ] = "et,en-US,en"

with open( path, "w" ) as f:
	json.dump( data, f )
PYEOF

	while true; do
		"$DS_KIOSK_BROWSER_BIN" \
			--kiosk \
			--noerrdialogs \
			--disable-infobars \
			--disable-session-crashed-bubble \
			--disable-translate \
			--disable-features=Translate,TranslateUI \
			--disable-pinch \
			--overscroll-history-navigation=0 \
			--no-first-run \
			--fast --fast-start \
			--check-for-update-interval=31536000 \
			--autoplay-policy=no-user-gesture-required \
			--user-data-dir="$PROFILE_DIR" \
			--enable-gpu-rasterization \
			--enable-zero-copy \
			--ignore-gpu-blocklist \
			--lang=et-EE \
			--no-sandbox \
			"$DS_KIOSK_URL" \
			>/tmp/ds-kiosk-chromium.log 2>&1 || true
		sleep 3
	done
fi
EOF
chmod +x "$WATCHDOG"

echo "==> Writing ~/.xinitrc for ${KIOSK_USER}"
cat > "${USER_HOME}/.xinitrc" <<'EOF'
#!/usr/bin/env bash
# Minimal kiosk X session: no panel, no window borders, no screen blanking.
xset -dpms
xset s off
xset s noblank
unclutter -idle 0.5 -root &
openbox-session &
exec /usr/local/bin/ds-kiosk-loop.sh
EOF
chmod +x "${USER_HOME}/.xinitrc"
chown "$KIOSK_USER":"$KIOSK_USER" "${USER_HOME}/.xinitrc"

# --- Clean up any older autostart mechanism this installer has used, so
# re-running it always converges on the current, most reliable setup:
#   v1: console autologin + a ~/.bash_profile hook running 'exec startx'.
#       Depended on the login shell actually sourcing .bash_profile, which
#       turned out not to fire reliably on every Raspberry Pi OS build.
#   v2: a systemd service running startx AS the kiosk user via
#       PAMName=login/TTYPath, to get VT permissions without root. Still
#       depends on logind granting that session the console correctly,
#       which isn't guaranteed on every setup either.
# v3 (current): the kiosk service runs as root. Root can always open the
# console/framebuffer/DRM devices directly — no PAM session, no VT
# permission grant, no getty race to get right. This trades a bit of
# isolation (Chromium needs --no-sandbox as root — see ds-kiosk-loop.sh)
# for actually starting reliably on a single-purpose kiosk device. ---
PROFILE_FILE="${USER_HOME}/.bash_profile"
if [ -f "$PROFILE_FILE" ] && grep -q "Digital Signage kiosk autostart" "$PROFILE_FILE"; then
	sed -i '/# --- Digital Signage kiosk autostart ---/,/# --- end Digital Signage kiosk autostart ---/d' "$PROFILE_FILE"
fi
if command -v raspi-config >/dev/null 2>&1; then
	raspi-config nonint do_boot_behaviour B1 >/dev/null 2>&1 || true
fi
rm -f /etc/systemd/system/getty@tty1.service.d/autologin.conf

echo "==> Installing ds-kiosk.service (runs as root, owns tty1, starts X directly)"
cat > /etc/systemd/system/ds-kiosk.service <<EOF
[Unit]
Description=Digital Signage kiosk (X + ${BROWSER})
After=network-online.target getty@tty1.service
Wants=network-online.target
Conflicts=getty@tty1.service

[Service]
Type=simple
ExecStartPre=-/usr/bin/chvt 1
ExecStart=/usr/bin/startx ${USER_HOME}/.xinitrc -- vt1 -nocursor
Restart=always
RestartSec=3
Nice=-5
IOSchedulingClass=best-effort
IOSchedulingPriority=2
OOMScoreAdjust=-500
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Mask (not just disable) the getty on tty1: getty.target's own [Unit] file
# wants it regardless of enablement, so plain 'disable' doesn't reliably keep
# it from starting and racing our service for the console. Masking does.
systemctl mask getty@tty1.service >/dev/null 2>&1 || true
systemctl daemon-reload
systemctl enable ds-kiosk.service

# --- Remote device management agent (WiFi, rotation, reboot, updates from wp-admin) ---
AGENT_INSTALLED=0
if [ -f "${SCRIPT_DIR}/ds-agent/ds-agent.py" ]; then
	echo "==> Installing the device-management agent (ds-agent)…"
	install -m 755 "${SCRIPT_DIR}/ds-agent/ds-agent.py" /usr/local/bin/ds-agent.py
	install -m 644 "${SCRIPT_DIR}/ds-agent/ds-agent.service" /etc/systemd/system/ds-agent.service
	systemctl daemon-reload
	systemctl enable --now ds-agent.service
	AGENT_INSTALLED=1

	if ! command -v nmcli >/dev/null 2>&1; then
		echo "   ⚠ nmcli not found — remote WiFi changes need NetworkManager"
		echo "     (the default network stack on Raspberry Pi OS Bookworm and newer)."
		echo "     Rotation, reboot, restart-browser and update commands still work."
	fi
else
	echo "==> Skipping ds-agent install (ds-agent/ directory not found next to this script)."
	echo "    Download the full raspberry-pi-kiosk folder (not just install-kiosk.sh) to get"
	echo "    remote WiFi/rotation/reboot management from wp-admin."
fi

# --- First-boot setup portal (only ever fires again if the config file above is
#     removed — e.g. wiping the SD card's config to re-provision this device by
#     WiFi hotspot instead of SSH). Harmless to install even when unused. ---
if [ -f "${SCRIPT_DIR}/setup-portal/ds-setup-portal.py" ]; then
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-portal.py" /usr/local/bin/ds-setup-portal.py
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-ap-up.sh" /usr/local/bin/ds-setup-ap-up.sh
	install -m 755 "${SCRIPT_DIR}/setup-portal/ds-setup-ap-down.sh" /usr/local/bin/ds-setup-ap-down.sh
	install -m 644 "${SCRIPT_DIR}/setup-portal/ds-setup.service" /etc/systemd/system/ds-setup.service
	# Also make this installer itself reachable by that portal's handoff step.
	install -m 755 "${SCRIPT_DIR}/install-kiosk.sh" /usr/local/bin/install-kiosk.sh
	if [ -f "${SCRIPT_DIR}/optimize-pi.sh" ]; then
		install -m 755 "${SCRIPT_DIR}/optimize-pi.sh" /usr/local/bin/ds-optimize-pi.sh
	fi
	systemctl daemon-reload
	systemctl enable ds-setup.service >/dev/null 2>&1 || true
fi

echo ""
echo "✅ Installed. Player URL (permanent for this device): ${URL}"
echo "   Browser:                ${BROWSER} (${BROWSER_BIN})"
echo "   Kiosk user:            ${KIOSK_USER}"
echo "   Performance profile:   ${KIOSK_PROFILE}"
echo "   Config file:           ${CONF_FILE}"
echo "   Watchdog script:       ${WATCHDOG}"
echo "   Kiosk service:         ds-kiosk.service (owns tty1, starts X directly)"
if [ "$AGENT_INSTALLED" -eq 1 ]; then
	echo "   Device agent:          installed and running (ds-agent.service)"
fi
echo ""
echo "On first boot this screen will show a pairing code + QR code full-screen —"
echo "scan it or open wp-admin > Digital Signage > Pair a Screen to link it."
echo "The same code/token stays valid across every reboot."
if [ "$AGENT_INSTALLED" -eq 1 ]; then
	echo ""
	echo "Once paired, open this screen in wp-admin (Digital Signage > Screens) to see a"
	echo "'Device' panel — change its WiFi network, rotate the display, restart the"
	echo "browser, reboot, or check for updates, all remotely."
fi
echo ""
echo "Reboot now to start the kiosk: sudo reboot"
echo "Already rebooted and stuck at a terminal from an older install? Start it now"
echo "without rebooting again: sudo systemctl start ds-kiosk"
echo ""
echo "If the kiosk still doesn't appear after that, gather diagnostics:"
echo "  sudo systemctl status ds-kiosk"
echo "  sudo journalctl -u ds-kiosk -e --no-pager"
echo "  cat /tmp/ds-kiosk-firefox.log 2>/dev/null || cat /tmp/ds-kiosk-chromium.log"
echo "  cat /var/log/Xorg.0.log 2>/dev/null | tail -40"
echo "To re-pair this device as a different screen: sudo bash install-kiosk.sh ${SITE_URL} ${KIOSK_USER} --regenerate"
echo "To completely remove: sudo bash uninstall-kiosk.sh ${KIOSK_USER}"
