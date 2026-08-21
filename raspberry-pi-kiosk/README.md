# Raspberry Pi Kiosk Installer

Turns a Raspberry Pi into a dedicated Digital Signage player: it boots straight
into a full-screen, chrome-less Chromium window — no desktop, no taskbar, no
address bar. If Chromium crashes or the network isn't up yet, a watchdog
relaunches it automatically.

The device generates and remembers **its own pairing identity** — no need to
pre-create a screen in wp-admin first. On first boot it shows a pairing code
and QR code full-screen; scan the QR (or enter the code manually in
**Digital Signage → Pair a Screen**) and it links up. That same identity
persists across every reboot, power cut, or SD card re-insert.

Works on Raspberry Pi OS **Lite** or **Desktop** (Bullseye or Bookworm, 32-
or 64-bit), Pi 3/4/5/Zero 2 W.

## Install

Copy `install-kiosk.sh` to the Pi (via `scp`, a USB stick, or `git clone`),
then run it as root with your WordPress site's URL (not a player URL — the
Pi builds its own):

```bash
sudo bash install-kiosk.sh "https://yourdomain.com"
```

Optionally pass the OS user to autologin as (defaults to the user running
`sudo`, or `pi`):

```bash
sudo bash install-kiosk.sh "https://yourdomain.com" kiosk
```

Reboot when it's done:

```bash
sudo reboot
```

The Pi will boot to a console autologin, start a minimal X session (Openbox
+ Chromium, no desktop environment needed), disable screen blanking, hide
the mouse cursor, and show its pairing screen — code, QR, and step-by-step
instructions — full-screen. Once you pair it in wp-admin, it automatically
starts playing that screen's channel; no need to touch the device again.

## What it sets up

- **Packages**: `xserver-xorg`, `xinit`, `x11-xserver-utils`, `openbox`,
  `unclutter`, and `chromium`/`chromium-browser`.
- **A permanent device token**, generated once on first install and stored
  in `/etc/digital-signage-kiosk.conf` — every reboot reuses it, so the
  screen's identity (and, once paired, which channel/schedule targets it)
  never resets on its own.
- **Console autologin** for the kiosk user via `raspi-config nonint
  do_boot_behaviour B2` (falls back to a systemd `getty@tty1` override on
  systems without `raspi-config`).
- `~/.bash_profile` — starts `startx` automatically the moment the kiosk
  user logs into `tty1`.
- `~/.xinitrc` — disables DPMS/screen blanking, hides the cursor
  (`unclutter`), starts a bare Openbox session, then runs the watchdog.
- `/usr/local/bin/ds-kiosk-loop.sh` — launches Chromium in `--kiosk` mode
  pointed at this device's permanent player URL; if it ever exits, waits 3s
  and relaunches.

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

This removes the autostart hook, watchdog script and config, and restores
normal console/desktop login.

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
