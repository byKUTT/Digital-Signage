# Ubuntu multi-display kiosk

This controller is for Ubuntu Desktop PCs. It uses Google Chrome, an unattended
Xorg login, and the Digital Signage controller API. One PC is
paired once; every connected monitor appears as a separate Screen in WordPress
and can be assigned a different channel.

Supported target: Ubuntu Desktop 24.04 LTS or newer with GDM. On amd64, the
installer uses an existing Google Chrome Stable or downloads the official
Google Chrome package. Chromium is the fallback on architectures where Google
Chrome is unavailable. `firefox-esr` is not required.

## Recover the black screen created by the Pi installer

Try `Ctrl+Alt+F3` (or `Ctrl+Alt+Fn+F3`) and sign in. Then run:

```bash
sudo systemctl disable --now ds-kiosk.service
sudo systemctl unmask getty@tty1.service
sudo systemctl set-default graphical.target
sudo reboot
```

If no console appears, boot Ubuntu recovery mode from GRUB, select the root
shell, run `mount -o remount,rw /`, then run the four commands above. The new
Ubuntu installer also performs this migration automatically once it can run.

## Install from Git

```bash
sudo apt update
sudo apt install -y git
cd ~
git clone https://github.com/byKUTT/Digital-Signage.git
cd Digital-Signage/ubuntu-kiosk
sudo bash install-kiosk.sh "https://screens.kutt.ee" robin
sudo reboot
```

`https://screens.kutt.ee` is the built-in default, so `sudo bash install-kiosk.sh`
also works when run by the intended kiosk user through sudo. Replace `robin` if needed. Normal displays need no resolution
argument: XRandR supplies every monitor's desktop geometry automatically.

The installer configures GDM Xorg autologin, removes the incompatible Pi
`tty1` startup, installs graphical-session autostart, keeps a managed Git
checkout under `/opt/bykutt-digital-signage`, and hard-disables screen locking,
blanking, automatic suspend, and hibernation. It installs no daily reboot timer
or automatic reboot watchdog. Chrome starts automatically after every boot and
is launched once for every detected monitor.

The cursor is hidden in kiosk mode with both an invisible X cursor and an
immediate cursor-hider fallback, then restored in desktop mode. Chrome runs without
sign-in, sync, saved passwords, autofill, profile selection, or GNOME login
keyring access, so an unattended screen does not wait for authentication.

## Pair and assign monitors

After reboot, every detected monitor opens a controller pairing page. Scan its
QR code or enter the six-character code under **Signage Manager → Pair controller**.
You must sign in and have an active group before the controller can be claimed. WordPress shows
one Controller and creates one Screen per output, such as `DP-1` and `HDMI-1`.
Assign a different Screen/channel to each output on the Controller page.

Connector names are stable assignment keys. Temporarily unplugging a monitor
does not delete its Screen assignment; reconnecting it to the same connector
restores the correct player.

## Update without losing settings

```bash
sudo digital-signage-update
```

The updater validates the configured repository and branch, backs up tracked
changes inside the managed source checkout, and synchronizes it to the newest
remote revision. It preserves the site URL, kiosk user, controller identity/token,
monitor assignments, schedules, Chrome profiles, and other settings. Routine
upgrades do not refresh Ubuntu package indexes. The
same update can be queued from the Controller page in WordPress. It runs in a
non-blocking system service, so controller heartbeats continue and the latest
update status/result appears in telemetry. A successful remote update reboots
the controller so the new version starts cleanly; settings and identity remain
unchanged.

If an update fails, the controller returns the exact failing stage and the end
of the service log instead of only an exit code. Site administrators can also
open the controller in Signage Manager and run one of four read-only remote
diagnostics: update service/log, controller process, network, or system resources.
Their output is stored in command history. Arbitrary terminal commands are never
accepted from WordPress.

The installer creates and validates passwordless sudo access only for
`/usr/local/sbin/digital-signage-root-command`. That helper has a fixed action
allowlist, so the unattended account cannot use it to run arbitrary root commands.
The Ubuntu login password is never stored in or sent through WordPress.

## Display sleep schedule and URL migration

Open `/signage-manager/`, choose **Controllers**, then select the device. The
Sleep schedule powers the connected monitors off and on at the chosen local
times without suspending Ubuntu. **Switch URL** requires the current hostname
to be typed as confirmation. It saves the new WordPress URL, clears the old
pairing identity, acknowledges the old controller, and reboots; the device then
shows a fresh pairing code on the new site.

Schedule times are always evaluated against the WordPress server clock and
WordPress timezone, not the Ubuntu computer's local clock. Times use 24-hour
`HH:MM` format. A failed X11/DPMS command is reported in controller telemetry
and logs instead of being treated as a successful screen-off action.

## Kiosk and desktop mode

The WordPress Controller page provides **Close Screens / Show Desktop** for
local coding or setup and **Start Kiosk Screens** to launch all assigned outputs
again. The chosen mode is saved across reboot. Desktop mode restores the mouse
cursor; kiosk mode hides it. **Restart Players** is the manual restart command.

## Status and logs

```bash
pgrep -af ds_ubuntu_controller.py
pgrep -af 'google-chrome|chromium'
xrandr --query
```

There is deliberately no Chrome window detection and no browser-health
relaunch. At boot the controller issues one Chrome launch per assigned output
and trusts it. A player is relaunched only by an explicit Start/Restart command,
an assignment URL or geometry change, or reconnecting an output. Controller
errors stay in the logs and WordPress telemetry; they never trigger an
automatic computer reboot.

## Uninstall

```bash
cd /opt/bykutt-digital-signage/ubuntu-kiosk
sudo bash uninstall-kiosk.sh
sudo reboot
```

This restores the pre-install GDM configuration and retains the identity for a
possible reinstall. Add `--purge` to remove the identity and managed Git clone.
