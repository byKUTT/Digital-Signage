# Raspberry Pi Kiosk Installer

Turns a Raspberry Pi into a dedicated Digital Signage player: it boots straight
into a full-screen, chrome-less Chromium window — no desktop, no taskbar, no
address bar. If Chromium crashes or the network isn't up yet, a watchdog
relaunches it automatically. It also installs **ds-agent**, a small
background service so the device can be managed remotely from the plugin's
admin UI afterward — WiFi network, screen rotation, reboot, restart-the-
browser, check-for-updates — no SSH or keyboard needed again.

The device generates and remembers **its own pairing identity** — no need
to pre-create a screen in wp-admin first. On first boot it shows a pairing
code and QR code full-screen; scan the QR (or enter the code manually in
**Digital Signage → Pair a Screen**) and it links up. That same identity
persists across every reboot, power cut, or SD card re-insert.

Works on Raspberry Pi OS **Lite** or **Desktop** (Bullseye or Bookworm, 32-
or 64-bit), Pi 3/4/5/Zero 2 W.

## You already have a Pi flashed and booted — install now

SSH into the Pi (`ssh pi@<its-ip-address>`, or `raspberrypi.local` if mDNS
resolves on your network), or plug in a keyboard/monitor and log in, then:

```bash
# Get this folder onto the Pi. Either clone the repo:
git clone https://github.com/byKUTT/Digital-Signage.git
cd Digital-Signage/raspberry-pi-kiosk

# ...or if you only copied the zip, unzip it and cd into raspberry-pi-kiosk/
```

Then run the installer with your WordPress site's URL:

```bash
sudo bash install-kiosk.sh "https://yourdomain.com"
```

This installs Chromium + the kiosk session, generates this device's
permanent pairing token, and deploys **ds-agent** for remote management —
all in one pass (a few minutes, mostly `apt-get install`). When it finishes:

```bash
sudo reboot
```

The Pi boots to a full-screen pairing code + QR code. Scan it (or enter the
code in **Digital Signage → Pair a Screen**) and it starts playing
automatically. From then on, open that screen in wp-admin
(**Digital Signage → Screens**) to see a **Device** panel — WiFi, rotation,
reboot, restart, and update, all from the browser.

> Only copied `install-kiosk.sh` by itself, not the whole folder? It still
> installs the kiosk fine, but skips `ds-agent` (remote management) since
> `ds-agent/ds-agent.py` isn't next to it — grab the full `raspberry-pi-kiosk/`
> folder to get that too.

## Starting from a blank SD card instead

1. Flash **Raspberry Pi OS Lite** with [Raspberry Pi
   Imager](https://www.raspberrypi.com/software/) (use its gear/⚙️ "Edit
   Settings" screen to set a hostname, enable SSH, and set a username/
   password before writing — saves a keyboard/monitor step).
2. Boot it, SSH in, and follow "You already have a Pi flashed and booted"
   above.

## Re-provisioning by WiFi hotspot instead of SSH

If this device's `/etc/digital-signage-kiosk.conf` doesn't exist yet (a
never-configured device, or one you deliberately reset — see below), it
puts up a temporary open WiFi network **`Digital-Signage-Setup-XXXX`**
instead of the kiosk. Connect to it from a phone or laptop and open
`http://10.42.0.1/` — a one-page form asks for the WiFi network to join and
your WordPress site URL. Submit it and the device connects, provisions
itself (same as running `install-kiosk.sh` above), and reboots straight
into its pairing screen. No SSH, no keyboard, no monitor.

To reset a device back into this mode (e.g. moving it to a new site/WiFi
without SSH access):

```bash
sudo rm /etc/digital-signage-kiosk.conf
sudo reboot
```

This needs a `wlan0` WiFi radio (built into Pi 3/4/5/Zero 2 W) and requires
`nmcli`/NetworkManager, the default network stack on Raspberry Pi OS
Bookworm and newer — see **Notes & troubleshooting** below if your image
predates that.

## Remote device management (ds-agent)

Once paired, a screen running `ds-agent` shows a **Device** panel on its
edit page in wp-admin:

- **WiFi** — enter a network name + password; the device switches to it
  within seconds. The password is sent once, applied via `nmcli`, and never
  stored on the device or in WordPress afterward.
- **Screen rotation** — Normal / Left / Right / Upside-down, applied live if
  the kiosk is running, or on its next start otherwise.
- **Restart Browser** — kills just Chromium; the watchdog relaunches it
  (~3s), useful if a page gets stuck without a full reboot.
- **Reboot Device** — restarts the whole Pi.
- **Check for Updates** — runs `apt-get update && apt-get upgrade` in the
  background.
- **Live status** — WiFi network + signal, CPU temperature, free disk
  space, current rotation, agent version — refreshed every 30 seconds
  (`ds-agent`'s heartbeat interval), the same cadence the player already
  uses to check for content updates.

Commands are queued in WordPress and delivered the next time the device's
heartbeat is answered (≤30s later) — no inbound connection to the Pi is
ever required, so it works behind NAT/firewalls with no port forwarding.

## What it sets up

- **Packages**: `xserver-xorg`, `xinit`, `x11-xserver-utils`, `openbox`,
  `unclutter`, `python3`, and `chromium`/`chromium-browser`.
- **A permanent device token**, generated once on first install and stored
  in `/etc/digital-signage-kiosk.conf` — every reboot reuses it.
- **Console autologin** for the kiosk user via `raspi-config nonint
  do_boot_behaviour B2` (falls back to a systemd `getty@tty1` override on
  systems without `raspi-config`).
- `~/.bash_profile` / `~/.xinitrc` — auto-starts a minimal Openbox + Chromium
  kiosk session on console login, with screen blanking and the cursor
  disabled, applying any remotely-set rotation before Chromium opens.
- `/usr/local/bin/ds-kiosk-loop.sh` — the watchdog: relaunches Chromium if
  it ever exits.
- `ds-agent.service` — the remote-management agent (`ds-agent/ds-agent.py`),
  reporting device telemetry and applying queued commands every 30s.
- `ds-setup.service` — the WiFi-hotspot re-provisioning portal
  (`setup-portal/`), which only ever runs while the device is unconfigured
  (see "Re-provisioning" above) — inert and harmless otherwise.

## Re-running the installer

Running `install-kiosk.sh` again (e.g. to change the site URL, or after an
OS update) **keeps the existing device token** — safe to re-run any time.

To deliberately re-pair this device as a *different* screen (e.g. you're
reusing an SD card on new hardware), force a fresh identity:

```bash
sudo bash install-kiosk.sh "https://yourdomain.com" kiosk --regenerate
```

## Uninstalling

```bash
sudo bash uninstall-kiosk.sh
sudo reboot
```

This removes the kiosk autostart, `ds-agent`, the setup portal, and the
config, and restores normal console/desktop login.

## Building an actual bootable `.img`

The steps above install onto an already-running Raspberry Pi OS SD card —
the fastest path, and what most people want. If you specifically need a
pre-baked, flash-and-go `.img` file (e.g. mass-provisioning many identical
screens), see [`../pi-image-build/`](../pi-image-build/): a
[`pi-gen`](https://github.com/RPi-Distro/pi-gen) configuration (the same
tool the Raspberry Pi Foundation itself uses) plus a GitHub Actions workflow
that builds and publishes the `.img` automatically.

## Troubleshooting

- **Black screen / stuck on console**: SSH in and check
  `/tmp/ds-kiosk-chromium.log` for Chromium errors, and confirm
  `/etc/digital-signage-kiosk.conf` has a reachable `DS_KIOSK_URL`.
- **Wrong user logs in**: pass the correct username as the 2nd argument to
  `install-kiosk.sh`, or re-run `sudo raspi-config` → *System Options* →
  *Boot / Auto Login* → *Console Autologin* and pick the right user.
- **Want a visible cursor for touch-screen kiosks**: remove the `-- -nocursor`
  flag from the `startx` line in `~/.bash_profile`.
- **Screen moved to a different WordPress site**: edit `DS_KIOSK_SITE` (and
  `DS_KIOSK_URL`) in `/etc/digital-signage-kiosk.conf`, or just re-run the
  installer with the new site URL (keeps the same device token).
- **WiFi panel doesn't do anything**: `ds-agent` needs `nmcli`
  (NetworkManager) to change WiFi. Older Raspberry Pi OS Bullseye images use
  `dhcpcd`/`wpa_supplicant` instead — `apt-get install network-manager`
  yourself if you want remote WiFi control on such a system (and be aware
  the switch can briefly interrupt SSH — do it from a keyboard/monitor if
  in doubt), or upgrade to a current Bookworm-based image.
- **Check the agent's own logs**: `sudo journalctl -u ds-agent -f`.
