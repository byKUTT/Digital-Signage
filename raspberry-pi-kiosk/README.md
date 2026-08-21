# Raspberry Pi Kiosk Installer

Turns a Raspberry Pi into a dedicated Digital Signage player: it boots straight
into a full-screen, chrome-less Chromium window showing your player URL — no
desktop, no taskbar, no address bar. If Chromium crashes or the network isn't
up yet, a watchdog relaunches it automatically.

Works on Raspberry Pi OS **Lite** or **Desktop** (Bullseye or Bookworm, 32-
or 64-bit), Pi 3/4/5/Zero 2 W.

## 1. Get your player URL

In wp-admin, go to **Digital Signage → Pair a Screen**, open the generated
player URL on this Pi once to get a pairing code, and confirm it in the
admin. Or just grab the URL from **Digital Signage → Screens** once a screen
already exists — it looks like:

```
https://yourdomain.com/signage/play/<token>/
```

## 2. Install

Copy `install-kiosk.sh` to the Pi (via `scp`, a USB stick, or `git clone`),
then run it as root with your player URL:

```bash
sudo bash install-kiosk.sh "https://yourdomain.com/signage/play/<token>/"
```

Optionally pass the OS user to autologin as (defaults to the user running
`sudo`, or `pi`):

```bash
sudo bash install-kiosk.sh "https://yourdomain.com/signage/play/<token>/" kiosk
```

Reboot when it's done:

```bash
sudo reboot
```

The Pi will boot to a console autologin, start a minimal X session (Openbox
+ Chromium, no desktop environment needed), disable screen blanking, hide
the mouse cursor, and open your player URL full-screen.

## What it sets up

- **Packages**: `xserver-xorg`, `xinit`, `x11-xserver-utils`, `openbox`,
  `unclutter`, and `chromium`/`chromium-browser`.
- **Console autologin** for the kiosk user via `raspi-config nonint
  do_boot_behaviour B2` (falls back to a systemd `getty@tty1` override on
  systems without `raspi-config`).
- `~/.bash_profile` — starts `startx` automatically the moment the kiosk
  user logs into `tty1`.
- `~/.xinitrc` — disables DPMS/screen blanking, hides the cursor
  (`unclutter`), starts a bare Openbox session, then runs the watchdog.
- `/usr/local/bin/ds-kiosk-loop.sh` — launches Chromium in `--kiosk` mode
  pointed at your URL; if it ever exits, waits 3s and relaunches.
- `/etc/digital-signage-kiosk.conf` — the URL and Chromium binary path, so
  you can change the URL later without re-running the whole installer.

## Changing the URL later

Edit the config and reboot:

```bash
sudo nano /etc/digital-signage-kiosk.conf   # edit DS_KIOSK_URL=
sudo reboot
```

Or just re-run `install-kiosk.sh` with the new URL.

## Uninstalling

```bash
sudo bash uninstall-kiosk.sh
sudo reboot
```

This removes the autostart hook, watchdog script and config, and restores
normal console/desktop login.

## Troubleshooting

- **Black screen / stuck on console**: SSH in and check
  `/tmp/ds-kiosk-chromium.log` for Chromium errors, and confirm
  `/etc/digital-signage-kiosk.conf` has a reachable URL.
- **Wrong user logs in**: pass the correct username as the 2nd argument to
  `install-kiosk.sh`, or re-run `sudo raspi-config` → *System Options* →
  *Boot / Auto Login* → *Console Autologin* and pick the right user.
- **Want a visible cursor for touch-screen kiosks**: remove the `-- -nocursor`
  flag from the `startx` line in `~/.bash_profile`.
