#!/bin/bash
# Builds digital-signage-kiosk.iso: a bootable, non-Windows (Debian/Ubuntu-
# based) Linux live image that boots straight into a fullscreen kiosk
# browser pointed at a Digital Signage play URL — no desktop, no login
# screen, no window chrome. Write it to a USB stick with write-to-usb.sh,
# set that stick first in the target PC's boot order (see README.md), and
# it plays the signage on every boot with the USB plugged in.
#
# Must run on a Debian/Ubuntu (or derivative) Linux machine with root
# access — it uses debootstrap, mksquashfs, and grub-mkrescue, none of
# which need to exist on the *target* kiosk PC, only on the machine
# building the image.
#
# Usage:
#   sudo ./build-kiosk-usb.sh [https://yourdomain.com]
#
# With no site given, it defaults to https://test.kutt.ee (this project's
# own test site) — same default as the Windows and Raspberry Pi installers.
#
# Idempotent-ish: safe to re-run; BUILD_DIR is wiped and rebuilt from
# scratch each time (a full run takes several minutes and needs network
# access to archive.ubuntu.com).

set -euo pipefail

SITE="${1:-https://test.kutt.ee}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${BUILD_DIR:-/tmp/ds-kiosk-build}"
CHROOT="$BUILD_DIR/chroot"
ISO_DIR="$BUILD_DIR/iso"
OUT_ISO="$SCRIPT_DIR/digital-signage-kiosk.iso"
SUITE="noble" # Ubuntu 24.04 LTS

if [ "$(id -u)" -ne 0 ]; then
	echo "Run this as root (sudo $0 ...) — it needs debootstrap/chroot/mount." >&2
	exit 1
fi

for tool in debootstrap mksquashfs grub-mkrescue xorriso; do
	command -v "$tool" >/dev/null || {
		echo "Missing $tool. On Debian/Ubuntu:" >&2
		echo "  apt-get install -y debootstrap squashfs-tools xorriso grub-pc-bin grub-efi-amd64-bin grub-common mtools dosfstools" >&2
		exit 1
	}
done

echo "==> Site: $SITE"
echo "==> Build dir: $BUILD_DIR"

cleanup() {
	for m in dev/pts dev proc sys; do
		mountpoint -q "$CHROOT/$m" 2>/dev/null && umount "$CHROOT/$m" || true
	done
}
trap cleanup EXIT

rm -rf "$BUILD_DIR"
mkdir -p "$CHROOT"

echo "==> Debootstrapping Ubuntu $SUITE (minimal base)..."
debootstrap --arch=amd64 --variant=minbase "$SUITE" "$CHROOT" http://archive.ubuntu.com/ubuntu

cat > "$CHROOT/etc/apt/sources.list" <<EOF
deb http://archive.ubuntu.com/ubuntu $SUITE main restricted universe multiverse
deb http://archive.ubuntu.com/ubuntu $SUITE-updates main restricted universe multiverse
deb http://security.ubuntu.com/ubuntu $SUITE-security main restricted universe multiverse
EOF
cp /etc/resolv.conf "$CHROOT/etc/resolv.conf"
echo "ds-kiosk" > "$CHROOT/etc/hostname"

mount --bind /dev "$CHROOT/dev"
mount --bind /dev/pts "$CHROOT/dev/pts"
mount -t proc proc "$CHROOT/proc"
mount -t sysfs sysfs "$CHROOT/sys"

echo "==> Installing packages (kernel, live-boot, X, openbox, qutebrowser)..."
chroot "$CHROOT" apt-get update
chroot "$CHROOT" env DEBIAN_FRONTEND=noninteractive apt-get install -y \
	linux-image-generic live-boot systemd-sysv \
	xserver-xorg xinit openbox unclutter \
	qutebrowser fonts-noto-color-emoji fonts-dejavu-core \
	network-manager \
	sudo nano less iproute2 iputils-ping ca-certificates \
	pulseaudio

echo "==> Creating the kiosk user and locking down root..."
chroot "$CHROOT" useradd -m -s /bin/bash -G video,audio,plugdev,netdev kiosk
chroot "$CHROOT" passwd -d kiosk
chroot "$CHROOT" passwd -l root
echo 'kiosk ALL=(ALL) NOPASSWD: ALL' > "$CHROOT/etc/sudoers.d/kiosk"
chmod 440 "$CHROOT/etc/sudoers.d/kiosk"

echo "==> Configuring tty1 autologin..."
mkdir -p "$CHROOT/etc/systemd/system/getty@tty1.service.d"
cat > "$CHROOT/etc/systemd/system/getty@tty1.service.d/override.conf" <<'EOF'
[Service]
ExecStart=
ExecStart=-/sbin/agetty --autologin kiosk --noclear %I $TERM
EOF

echo "==> Writing the kiosk session (X, openbox, qutebrowser, watchdog)..."
mkdir -p "$CHROOT/etc/digital-signage-kiosk"
printf '%s\n' "$SITE" > "$CHROOT/etc/digital-signage-kiosk/site.conf"

cat > "$CHROOT/home/kiosk/.bash_profile" <<'EOF'
# Auto-start the kiosk X session on the first virtual console only, so a
# second login (e.g. via SSH, or switching to another tty for maintenance)
# doesn't also try to launch it.
if [ -z "$DISPLAY" ] && [ "$(tty)" = "/dev/tty1" ]; then
	exec startx -- -nocursor
fi
EOF

cat > "$CHROOT/home/kiosk/.xinitrc" <<'EOF'
#!/bin/sh
# Never blank/lock the screen or let it sleep — there's no input device to
# wake it back up with.
xset s off
xset s noblank
xset -dpms

# Hide the mouse cursor when idle (there's no mouse, but a stray USB one
# shouldn't leave a cursor sitting on the signage either).
unclutter --timeout 1 --jitter 5 &

openbox &

exec /usr/local/bin/ds-kiosk-watchdog
EOF
chmod +x "$CHROOT/home/kiosk/.xinitrc"

cat > "$CHROOT/usr/local/bin/ds-kiosk-watchdog" <<'WATCHDOG'
#!/bin/bash
# Digital Signage kiosk watchdog (Linux).
#
# Generates/reuses this device's own permanent pairing token (persisted
# under /var/lib/digital-signage-kiosk, which is the one path this
# otherwise-immutable, read-only live system keeps across reboots — see
# the "persistence" partition set up by write-to-usb.sh), builds the play
# URL, and keeps a fullscreen qutebrowser window pointed at it — relaunching
# it if it ever exits on its own (crash, OOM, etc.), the same watchdog
# pattern used by the Windows and Raspberry Pi players.
set -u

STATE_DIR=/var/lib/digital-signage-kiosk
SITE_CONF=/etc/digital-signage-kiosk/site.conf
TOKEN_FILE="$STATE_DIR/device-token"

mkdir -p "$STATE_DIR"

SITE="$(cat "$SITE_CONF" 2>/dev/null | tr -d '[:space:]')"
SITE="${SITE:-https://test.kutt.ee}"
SITE="${SITE%/}"

if [ -s "$TOKEN_FILE" ]; then
	TOKEN="$(cat "$TOKEN_FILE")"
else
	# 40 random alphanumeric characters — this device's permanent identity.
	TOKEN="$(tr -dc 'a-zA-Z0-9' < /dev/urandom | head -c 40)"
	echo -n "$TOKEN" > "$TOKEN_FILE"
fi

URL="$SITE/signage/play/$TOKEN/?kiosk=1"

echo "$(date -Is) Digital Signage kiosk starting: $URL" >> "$STATE_DIR/kiosk.log"

while true; do
	qutebrowser --target window "$URL" >> "$STATE_DIR/qutebrowser.log" 2>&1
	echo "$(date -Is) qutebrowser exited (code $?) — restarting in 2s" >> "$STATE_DIR/kiosk.log"
	sleep 2
done
WATCHDOG
chmod +x "$CHROOT/usr/local/bin/ds-kiosk-watchdog"

mkdir -p "$CHROOT/home/kiosk/.config/qutebrowser"
cat > "$CHROOT/home/kiosk/.config/qutebrowser/config.py" <<'EOF'
# Digital Signage kiosk config for qutebrowser.
config.load_autoconfig(False)

# No browser chrome at all — this *is* the signage, not a browser.
c.tabs.show = 'never'
c.statusbar.show = 'never'
c.scrolling.bar = 'never'
c.window.hide_decoration = True

# No popups/dialogs that would need a click nobody's there to give.
c.content.notifications.enabled = False
c.content.geolocation = False
c.content.autoplay = True
c.confirm_quit = ['always']  # requires confirmation, but nothing sends :quit anyway
c.content.pdfjs = True

# Keep going even if something on the page misbehaves.
c.content.javascript.can_close_tabs = False
EOF

mkdir -p "$CHROOT/home/kiosk/.config/openbox"
cat > "$CHROOT/home/kiosk/.config/openbox/rc.xml" <<'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<openbox_config xmlns="http://openbox.org/3.4/rc">
  <applications>
    <application class="qutebrowser">
      <fullscreen>yes</fullscreen>
      <maximized>yes</maximized>
      <decor>no</decor>
      <focus>yes</focus>
      <layer>above</layer>
    </application>
  </applications>
  <keyboard>
    <!-- No keybindings at all: there's no keyboard, and the ones openbox
         ships by default (e.g. Alt+F4) would otherwise let a stray input
         device close the kiosk. -->
  </keyboard>
  <mouse>
  </mouse>
</openbox_config>
EOF

chroot "$CHROOT" chown -R kiosk:kiosk /home/kiosk

echo "==> Setting console (non-graphical) as the default boot target..."
chroot "$CHROOT" systemctl set-default multi-user.target

echo "Etc/UTC" > "$CHROOT/etc/timezone"
ln -sf /usr/share/zoneinfo/Etc/UTC "$CHROOT/etc/localtime"

echo "==> Cleaning up apt caches..."
chroot "$CHROOT" apt-get clean
rm -f "$CHROOT"/var/lib/apt/lists/*_Packages "$CHROOT"/var/lib/apt/lists/*_Translation-en 2>/dev/null || true
rm -rf "$CHROOT"/tmp/* "$CHROOT"/var/tmp/* 2>/dev/null || true

cleanup
trap - EXIT

echo "==> Assembling the ISO..."
rm -rf "$ISO_DIR"
mkdir -p "$ISO_DIR/live" "$ISO_DIR/boot/grub"

KVER="$(cd "$CHROOT/boot" && ls vmlinuz-* | sed 's/vmlinuz-//' | sort -V | tail -1)"
cp "$CHROOT/boot/vmlinuz-$KVER" "$ISO_DIR/live/vmlinuz"
cp "$CHROOT/boot/initrd.img-$KVER" "$ISO_DIR/live/initrd.img"
chmod 644 "$ISO_DIR/live/vmlinuz" "$ISO_DIR/live/initrd.img"

echo "==> Building filesystem.squashfs (this is the slow part, a couple of minutes)..."
mksquashfs "$CHROOT" "$ISO_DIR/live/filesystem.squashfs" -comp xz -e boot

cat > "$ISO_DIR/boot/grub/grub.cfg" <<'EOF'
set default=0
set timeout=1

insmod all_video
insmod gfxterm

menuentry "Digital Signage Kiosk" {
	linux /live/vmlinuz boot=live persistence persistence-encryption=none quiet loglevel=3 nosplash
	initrd /live/initrd.img
}

menuentry "Digital Signage Kiosk (troubleshooting console)" {
	linux /live/vmlinuz boot=live persistence persistence-encryption=none single
	initrd /live/initrd.img
}
EOF

echo "==> Building the hybrid BIOS+UEFI ISO..."
grub-mkrescue -o "$OUT_ISO" "$ISO_DIR"

echo ""
echo "✅ Built: $OUT_ISO ($(du -h "$OUT_ISO" | cut -f1))"
echo "   Write it to a USB stick with: sudo ./write-to-usb.sh /dev/sdX $OUT_ISO"
echo "   (⚠ that ERASES the target device — see write-to-usb.sh / README.md)"
