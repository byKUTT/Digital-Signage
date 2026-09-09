# Digital Signage — Bootable Kiosk USB

A **non-Windows, boot-from-USB** version of the kiosk: a small Linux live
image that boots straight into a fullscreen browser pointed at the signage
play URL — no desktop, no login screen, no OS install on the PC at all. The
PC's own drive (Windows, whatever) is left completely untouched; plug the
USB in and it plays the signage, unplug it and the PC boots normally off its
own drive.

## 1. Get the image

A pre-built `digital-signage-kiosk.iso` may already be attached alongside
this folder — if so, skip to [step 2](#2-write-it-to-a-usb-stick). Otherwise
build it yourself on any Debian/Ubuntu Linux machine:

```bash
sudo apt-get install -y debootstrap squashfs-tools xorriso \
    grub-pc-bin grub-efi-amd64-bin grub-common mtools dosfstools
sudo ./build-kiosk-usb.sh https://yourdomain.com
```

Takes a few minutes (mostly downloading packages and compressing the
filesystem) and produces `digital-signage-kiosk.iso` (~1.3 GB) in this
folder. Omit the URL to default to `https://test.kutt.ee`, matching the
Windows and Raspberry Pi installers.

## 2. Write it to a USB stick

**On Linux/macOS**, use the included script — it writes the ISO *and* sets
up the small persistence partition the kiosk needs to keep its pairing
identity across reboots (see [below](#persistence-why-there-are-two-partitions)):

```bash
lsblk                                          # find your USB stick, e.g. /dev/sdb
sudo ./write-to-usb.sh /dev/sdb digital-signage-kiosk.iso
```

⚠️ This **completely erases** the target device. Triple-check the device
path — `/dev/sdb` is the whole disk; `/dev/sdb1` is a partition on it. The
script shows you what it's about to erase and requires typing `YES` to
proceed.

**On Windows**, use [Rufus](https://rufus.ie/) or
[balenaEtcher](https://etcher.balena.io/) to write the `.iso` in **DD/raw
image mode** (not "ISO mode" — the image needs to be written byte-for-byte,
not have its filesystem extracted). Neither tool sets up the persistence
partition automatically; without it the kiosk still works, it just
generates a new pairing identity (and needs re-pairing in wp-admin) on every
reboot. To add persistence afterward from Windows, shrink/resize is fiddly
— easiest is to run `write-to-usb.sh` from any Linux machine (or a Linux
live USB) against the same stick afterward, or accept the re-pair-every-boot
behavior for a quick test.

## 3. Set the PC to boot from USB

This is a one-time, per-PC setting made in the PC's own firmware (BIOS/UEFI)
setup screen — there's no way to script or automate this from outside the
PC itself:

1. Plug in the USB stick and (re)start the PC.
2. Press the firmware's setup/boot-menu key during startup — commonly `F2`,
   `F10`, `F12`, `Del`, or `Esc` (varies by manufacturer; it's usually shown
   briefly on screen at power-on, or check the PC/motherboard's manual).
3. Either:
   - Use a one-time **boot menu** (often `F12`) and pick the USB stick —
     doesn't change anything permanently, only affects this one boot, or
   - Go into the full **BIOS/UEFI setup** and reorder the **boot priority**
     so the USB drive (or "Removable Devices" / "USB HDD") is *above* the
     internal drive — this makes it permanent: with the stick plugged in,
     the kiosk boots every time; unplugged, it boots the internal drive
     (Windows) as before.
4. Save and exit (commonly `F10`).

If the PC doesn't boot the stick at all, check: **Secure Boot** may need to
be disabled for a non-Microsoft-signed boot image on some UEFI firmwares
(setting lives in the same setup screen); on older BIOS-only PCs there's no
Secure Boot to worry about — the image boots via legacy BIOS too (it's a
hybrid ISO, built to support both).

## What it does

- Boots a minimal Ubuntu-based Linux system with just enough installed to
  run one browser window: Xorg, [openbox](http://openbox.org/) (window
  manager, used only to force the browser fullscreen/undecorated),
  [qutebrowser](https://qutebrowser.org/) (a genuine Chromium-engine
  browser via QtWebEngine — not a snap, so it works fully offline with no
  extra fetch on first run), and `live-boot` (what makes a plain squashfs +
  kernel bootable as "boot=live" from a USB/CD).
- Signs in automatically (`kiosk` user, no password, tty1 autologin) and
  starts the browser fullscreen, no window chrome, no taskbar, cursor
  hidden — the same "no desktop, ever" outcome as the Windows/Pi kiosks,
  achieved with different OS mechanisms (`live-boot` + `agetty --autologin`
  + `startx` instead of `AutoAdminLogon` + shell replacement).
- Generates and remembers this device's own pairing token (`/signage/play/
  <token>/`), exactly like the Windows and Raspberry Pi installers — same
  pairing code / QR flow in **Digital Signage → Pair a Screen**.
- Watches the browser process and relaunches it if it ever exits (crash,
  OOM) — the Linux equivalent of `kiosk-player.ps1`'s watchdog.
- Networking: `NetworkManager` with DHCP — wired Ethernet works
  out of the box; Wi-Fi needs a one-time `nmcli` or `nmtui` setup (there's
  no credential to bake in generically). SSH is not installed; use the
  physical console (see below) for anything that needs a shell.

### Persistence: why there are two partitions

The live image itself is a **read-only** squashfs — every boot starts from
the exact same, unmodified filesystem, which is a deliberate reliability
property (nothing on the boot media can get corrupted by a bad shutdown,
malware, or a failed update). But the kiosk's pairing token needs to
*survive* reboots, or every restart would look like a brand-new, unpaired
screen in wp-admin.

`write-to-usb.sh` resolves this the standard Debian-Live way: it writes the
ISO to the front of the USB stick, then creates a second, plain ext4
partition (labeled `persistence`) in the remaining space. `live-boot`
detects that partition automatically at boot (via the `persistence` kernel
parameter already baked into this image's GRUB config) and overlays just
`/var/lib/digital-signage-kiosk` — where the device token lives — onto it,
read-write, while everything else stays on the read-only squashfs. Losing
or reformatting that second partition just means the device re-pairs as if
it were new; it never affects whether the system boots.

## Closing / recovering the kiosk

There's no close hotkey (there's no keyboard) and no desktop to get to —
this is intentionally a single-purpose appliance image, not a general
desktop OS. For maintenance:

- **Switch to a text console**: `Ctrl+Alt+F2` (if a keyboard is plugged
  in) drops to a login prompt on another virtual console; log in as
  `kiosk` (no password) — it has full passwordless `sudo`. `Ctrl+Alt+F1`
  switches back to the kiosk.
- **The "troubleshooting console" GRUB entry**: shown for 1 second at boot
  (long enough to interrupt with an arrow key if a keyboard is attached)
  boots to a root shell instead of starting the kiosk session.
- **To go back to Windows**: unplug the USB stick and reboot, or change the
  boot order back — the internal drive was never touched.

## Rebuilding after changes

Edit `build-kiosk-usb.sh` (it contains the actual kiosk configuration —
autologin, `.xinitrc`, `qutebrowser` config, the watchdog script, etc., all
inline) and re-run it. There's no separate "patch an existing image" path —
each run debootstraps a fresh chroot from scratch and produces a new,
complete `digital-signage-kiosk.iso`; write that to the USB stick again with
`write-to-usb.sh` to update a deployed one (this re-does the persistence
partition too, so the pairing token resets — that's an inherent tradeoff of
"boots from an entirely fresh read-only image every time").

## Testing notes (read before deploying to real hardware)

This image was built and boot-tested in an automated sandbox with **no
hardware virtualization available** (no `/dev/kvm`), so full QEMU emulation
of the graphical session was too slow to watch end-to-end in that
environment. What *was* verified there:

- The ISO boots correctly via legacy BIOS in QEMU, GRUB loads the kernel
  and initrd, and `live-boot` successfully mounts the squashfs root
  (confirmed via kernel log: `overlayfs: "xino" feature enabled`).
  systemd starts and reaches a working login prompt.
- Every script/config file (the watchdog, `.xinitrc`, `qutebrowser`'s
  `config.py`, openbox's `rc.xml`, the sudoers entry) was checked directly
  inside the built filesystem for syntax errors, and every required binary
  (`Xorg`, `openbox`, `unclutter`, `qutebrowser`, `startx`, `agetty`) was
  confirmed present and executable.
- `Xwrapper.config` (`allowed_users=console`) confirmed the `kiosk` user
  can start `Xorg` from its own console login without needing root.

What was **not** visually confirmed end-to-end in that sandbox: the
tty1-autologin → `startx` → `openbox` → `qutebrowser` chain actually
painting a fullscreen browser window on screen, and real network access to
a signage site (the sandbox's own network policy blocks the test VM's
outbound traffic). If something in that chain doesn't come up on real
hardware, the troubleshooting console GRUB entry and `Ctrl+Alt+F2` (above)
are the way to get a shell and check `/var/lib/digital-signage-kiosk/
kiosk.log` and `qutebrowser.log`, and `journalctl -b`.
