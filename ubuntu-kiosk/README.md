# Ubuntu multi-display kiosk

This controller is for Ubuntu Desktop PCs. It uses Ubuntu's installed Firefox,
an unattended Xorg login, and the Digital Signage controller API. One PC is
paired once; every connected monitor appears as a separate Screen in WordPress
and can be assigned a different channel.

Supported target: Ubuntu Desktop 24.04 LTS or newer with GDM. The installer
supports Ubuntu's normal Snap-packaged Firefox and does not require
`firefox-esr`.

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
sudo bash install-kiosk.sh "https://test.kutt.ee" robin
sudo reboot
```

Replace the site URL and `robin` if needed. Normal displays need no resolution
argument: XRandR supplies every monitor's desktop geometry automatically.

The installer configures GDM Xorg autologin, removes the incompatible Pi
`tty1` startup, installs the controller service, keeps a managed Git checkout
under `/opt/bykutt-digital-signage`, and disables screen blanking while signage
is running. It installs no daily reboot timer or automatic reboot watchdog.

## Pair and assign monitors

After reboot, every detected monitor opens a controller pairing page. Enter its
six-character code under **Digital Signage → Pair a Screen**. WordPress shows
one Controller and creates one Screen per output, such as `DP-1` and `HDMI-1`.
Assign a different Screen/channel to each output on the Controller page.

Connector names are stable assignment keys. Temporarily unplugging a monitor
does not delete its Screen assignment; reconnecting it to the same connector
restores the correct player.

## Update without losing settings

```bash
sudo digital-signage-update
```

The updater accepts only a clean fast-forward update from the configured
repository and branch. It preserves the site URL, kiosk user, controller
identity/token, monitor assignments, Firefox profiles, and other settings. The
same update can be queued from the Controller page in WordPress.

## Status and logs

```bash
systemctl status digital-signage-ubuntu.service --no-pager
sudo journalctl -u digital-signage-ubuntu.service -b -n 150 --no-pager
xrandr --query
```

Firefox players and the controller restart after process crashes. Repeated
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
