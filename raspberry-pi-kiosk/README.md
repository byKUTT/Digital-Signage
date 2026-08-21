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

---

## Complete install guide, start to finish

Every step, in order, from a blank SD card:

1. **Flash the SD card.** Install [Raspberry Pi
   Imager](https://www.raspberrypi.com/software/) on any computer, insert
   the SD card, choose **Raspberry Pi OS Lite** (32- or 64-bit) as the OS.
2. **Pre-configure it (saves a keyboard/monitor step).** In Raspberry Pi
   Imager, click the gear/⚙️ icon ("Edit Settings") before writing:
   - Set a hostname (e.g. `signage-lobby`).
   - Set a username and password (note them down).
   - Configure your WiFi network (SSID + password) if not using Ethernet.
   - Enable SSH ("Use password authentication" is simplest).
   Click **Save**, then **Write**, and wait for it to finish.
3. **Boot the Pi.** Insert the SD card, connect power (and Ethernet, if
   using it). Wait about a minute for first boot.
4. **Connect to it.** From another computer on the same network:
   ```bash
   ssh <username>@<hostname>.local
   # e.g.: ssh pi@signage-lobby.local
   ```
   If `.local` doesn't resolve, find its IP address instead (check your
   router's device list) and use `ssh <username>@<ip-address>`.
5. **Get this installer onto the Pi** — see **Getting the code (git)** below
   for the actual commands; short version:
   ```bash
   git clone https://github.com/byKUTT/Digital-Signage.git
   cd Digital-Signage/raspberry-pi-kiosk
   ```
6. **Run the installer** with your WordPress site's URL:
   ```bash
   sudo bash install-kiosk.sh "https://yourdomain.com"
   ```
   This takes a few minutes (mostly `apt-get install`). It installs
   Chromium and the kiosk session, generates this device's permanent
   pairing token, and deploys `ds-agent` for remote management.

   Got an uncommon or stretched display (a bar-shaped screen like
   `1920x440`, for example) that doesn't auto-detect correctly? Add
   `--resolution WIDTHxHEIGHT`:
   ```bash
   sudo bash install-kiosk.sh "https://yourdomain.com" pi --resolution 1920x440
   ```
   This can also be set later from wp-admin (Screen edit page > Device >
   Custom resolution) without touching the Pi again.

   Chromium's translate popup (or other browser UI) still showing despite
   everything this installer already does to suppress it? Switch this
   device to Firefox ESR instead with `--browser firefox`:
   ```bash
   sudo bash install-kiosk.sh "https://yourdomain.com" pi --browser firefox
   ```
   Firefox has no equivalent auto-popping "translate this page?" prompt.
   Re-run without `--browser` (or with `--browser chromium`) to switch back.
7. **Reboot:**
   ```bash
   sudo reboot
   ```
8. **Watch the screen.** It should show a pairing code and QR code
   full-screen within about 30 seconds of boot. If it doesn't, see
   **Troubleshooting** below — don't guess, gather the diagnostics listed
   there so any follow-up fix is based on the actual error.
9. **Pair it.** Scan the QR code with your phone, or in wp-admin go to
   **Digital Signage → Pair a Screen** and enter the code shown. Name the
   screen and confirm.
10. **Assign content.** In wp-admin, create/assign a **Channel** to this
    screen (**Digital Signage → Screens** → open it → *Assigned channel*).
    It starts playing automatically — no further action on the device
    itself, ever again. From here on, WiFi, rotation, reboots and updates
    are all done remotely from that screen's **Device** panel in wp-admin.

---

## Getting the code (git) — manual commands

**First time — clone the repository:**
```bash
git clone https://github.com/byKUTT/Digital-Signage.git
cd Digital-Signage/raspberry-pi-kiosk
```

**Already cloned it before, want the latest fixes:**
```bash
cd Digital-Signage
git pull
cd raspberry-pi-kiosk
```

**No `git` installed** (rare on Raspberry Pi OS, but just in case):
```bash
sudo apt-get update && sudo apt-get install -y git
```

**No internet access on the Pi, or you'd rather not clone the whole repo**:
copy just the `raspberry-pi-kiosk` folder from your computer instead, e.g.
with `scp` from the machine that has it:
```bash
scp -r raspberry-pi-kiosk pi@<hostname-or-ip>:~/
ssh pi@<hostname-or-ip>
cd raspberry-pi-kiosk
```

**Check what you actually have** (useful when reporting a bug — include
this in any troubleshooting message):
```bash
cd Digital-Signage && git log -1 --format='%H %ci'
```

---

## Already have a Pi flashed and booted — condensed version

If you did steps 1–4 above already (or used your own method to get a Pi
booted and reachable over SSH), skip straight to:

```bash
git clone https://github.com/byKUTT/Digital-Signage.git
cd Digital-Signage/raspberry-pi-kiosk
sudo bash install-kiosk.sh "https://yourdomain.com"
sudo reboot
```

> Only copied `install-kiosk.sh` by itself, not the whole folder? It still
> installs the kiosk fine, but skips `ds-agent` (remote management) since
> `ds-agent/ds-agent.py` isn't next to it — grab the full `raspberry-pi-kiosk/`
> folder to get that too.

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
Bookworm and newer — see **Troubleshooting** below if your image predates
that.

## Remote device management (ds-agent)

Once paired, a screen running `ds-agent` shows a **Device** panel on its
edit page in wp-admin:

- **WiFi** — enter a network name + password; the device switches to it
  within seconds. The password is sent once, applied via `nmcli`, and never
  stored on the device or in WordPress afterward.
- **Screen rotation** — Normal / Left / Right / Upside-down, applied by
  restarting the kiosk service so it always takes effect reliably (a brief
  black screen, then back up rotated).
- **Custom resolution** — for an uncommon or stretched display the Pi's
  EDID auto-detection gets wrong (a bar-shaped screen like `1920x440`, for
  example), enter it as `WIDTHxHEIGHT` and apply. Uses `cvt` to generate a
  matching mode and restarts the kiosk service, same as rotation. Leave
  blank and apply to go back to auto-detect. Can also be set up-front at
  install time with `--resolution WIDTHxHEIGHT` (see below).
- **Restart Browser** — kills just Chromium; the watchdog relaunches it
  (~3s), useful if a page gets stuck without a full reboot.
- **Reboot Device** — restarts the whole Pi.
- **Check for Updates** — runs `apt-get update && apt-get upgrade` in the
  background.
- **Live status** — WiFi network + signal, CPU temperature, free disk
  space, current rotation/resolution, agent version — refreshed every 10
  seconds (`ds-agent`'s heartbeat interval).
- **Recent activity** — the agent's own log (commands it applied, errors),
  right there on the Screen edit page — no SSH needed to see what a device
  has been doing.

Commands are queued in WordPress and delivered the next time the device's
heartbeat is answered (≤10s later) — no inbound connection to the Pi is
ever required, so it works behind NAT/firewalls with no port forwarding.

## What it sets up

- **Packages**: `xserver-xorg`, `xinit`, `x11-xserver-utils`, `openbox`,
  `unclutter`, `python3`, and `chromium`/`chromium-browser`.
- **A permanent device token**, generated once on first install and stored
  in `/etc/digital-signage-kiosk.conf` — every reboot reuses it.
- **`ds-kiosk.service`** — a systemd service that takes ownership of `tty1`
  directly (masks `getty@tty1.service` so nothing else can claim the
  console) and runs `startx` there **as root**. Running as root is
  deliberate: it sidesteps an entire class of "works on some Pi OS builds,
  not others" failures around VT/console permissions and login sessions
  that both the console-autologin and the non-root-systemd approaches this
  installer used previously could hit. The one consequence is Chromium
  needs `--no-sandbox` when run as root (already set in
  `ds-kiosk-loop.sh`) — an acceptable trade for a single-purpose device
  showing one fixed URL, not a general-purpose browser.
- `~/.xinitrc` — the X session `ds-kiosk.service` runs: a minimal Openbox
  session with screen blanking and the cursor disabled, applying any
  remotely-set rotation before Chromium opens.
- `/usr/local/bin/ds-kiosk-loop.sh` — the watchdog: relaunches Chromium if
  it ever exits.
- `ds-agent.service` — the remote-management agent (`ds-agent/ds-agent.py`),
  reporting device telemetry and applying queued commands every 30s.
- `ds-setup.service` — the WiFi-hotspot re-provisioning portal
  (`setup-portal/`), which only ever runs while the device is unconfigured
  (see "Re-provisioning" above) — inert and harmless otherwise.

## Re-running the installer

Running `install-kiosk.sh` again (e.g. to change the site URL, or after an
OS update) **keeps the existing device token** — safe to re-run any time,
and it self-repairs by removing any older/broken version of the autostart
setup first.

To deliberately re-pair this device as a *different* screen (e.g. you're
reusing an SD card on new hardware), force a fresh identity:

```bash
sudo bash install-kiosk.sh "https://yourdomain.com" kiosk --regenerate
```

## Uninstalling — remove everything

```bash
sudo bash uninstall-kiosk.sh
sudo reboot
```

This removes `ds-kiosk.service`, `ds-agent`, the setup portal, the
watchdog script, `~/.xinitrc`, the WiFi setup hotspot connection profile,
and `/etc/digital-signage-kiosk.conf` (so this device forgets its pairing
identity entirely), and restores a normal console login on `tty1`. It does
**not** remove the apt packages it installed (chromium, xserver-xorg,
openbox, etc.), since other things on the system might use them — remove
those yourself with `sudo apt-get remove <package>` if you want them gone
too.

To remove for a specific user (if you installed for someone other than
`pi`):
```bash
sudo bash uninstall-kiosk.sh <username>
```

## Building an actual bootable `.img`

The steps above install onto an already-running Raspberry Pi OS SD card —
the fastest path, and what most people want. If you specifically need a
pre-baked, flash-and-go `.img` file (e.g. mass-provisioning many identical
screens), see [`../pi-image-build/`](../pi-image-build/): a
[`pi-gen`](https://github.com/RPi-Distro/pi-gen) configuration (the same
tool the Raspberry Pi Foundation itself uses) plus a GitHub Actions workflow
that builds and publishes the `.img` automatically.

## Troubleshooting

Start here if the kiosk doesn't appear after `sudo reboot` — gather this
before trying more fixes, since it tells you *which* of several possible
causes you're actually hitting:

```bash
sudo systemctl status ds-kiosk
sudo journalctl -u ds-kiosk -e --no-pager
cat /tmp/ds-kiosk-chromium.log
cat /var/log/Xorg.0.log 2>/dev/null | tail -40
```

- **`ds-kiosk.service` shows "inactive" or "failed"**: it didn't even try
  to start, or crashed immediately. `journalctl -u ds-kiosk -e` will show
  why (a common one: `startx: command not found` — means `xinit` didn't
  install correctly, re-run `sudo apt-get install --reinstall xinit`).
- **Service is "active (running)" but the screen is still black/terminal**:
  check the Xorg log for the actual server error, and
  `/tmp/ds-kiosk-chromium.log` for a Chromium-side error (e.g. a bad/
  unreachable `DS_KIOSK_URL` in `/etc/digital-signage-kiosk.conf`).
- **Start it right now without rebooting**: `sudo systemctl start ds-kiosk`
  (works whether or not it's enabled for boot).
- **Not installed yet on this device at all** (a fresh SD card, or one from
  before this fix): just run the installer — see the guide above — then
  reboot.
- **Still stuck after checking all of the above**: run
  `cd Digital-Signage && git log -1 --format='%H %ci'` to confirm you have
  the latest fix, then share the four diagnostic outputs above — that's
  enough to pinpoint the exact failure rather than guessing at another fix.
- **Want a visible cursor for touch-screen kiosks**: edit
  `/etc/systemd/system/ds-kiosk.service`, remove `-nocursor` from the
  `ExecStart` line, then `sudo systemctl daemon-reload && sudo systemctl
  restart ds-kiosk`.
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
