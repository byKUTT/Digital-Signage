#!/bin/bash
# Writes digital-signage-kiosk.iso to a USB stick and adds a small
# "persistence" partition in the remaining space, so the kiosk's pairing
# identity (its device token) survives reboots even though the rest of the
# system boots fresh from the read-only image every time.
#
# ⚠️ THIS ERASES EVERYTHING ON THE TARGET DEVICE. Triple-check the device
# path — /dev/sdX is a whole disk, not a partition (no trailing number).
#
# Usage (run on a Linux machine with the USB stick plugged in):
#   sudo ./write-to-usb.sh /dev/sdX [digital-signage-kiosk.iso]
#
# Find the right /dev/sdX with `lsblk` before running this — look for the
# USB stick by its size, not by guessing.

set -euo pipefail

DEVICE="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ISO="${2:-$SCRIPT_DIR/digital-signage-kiosk.iso}"

if [ -z "$DEVICE" ] || [ ! -f "$ISO" ]; then
	echo "Usage: sudo $0 /dev/sdX [path/to/digital-signage-kiosk.iso]" >&2
	echo "" >&2
	echo "Available block devices:" >&2
	lsblk -d -o NAME,SIZE,MODEL,TRAN >&2 || true
	exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
	echo "Run this as root (sudo $0 ...)." >&2
	exit 1
fi

if [ ! -b "$DEVICE" ]; then
	echo "$DEVICE is not a block device." >&2
	exit 1
fi

if [[ "$DEVICE" =~ [0-9]$ ]] && [[ ! "$DEVICE" =~ nvme ]]; then
	echo "$DEVICE looks like a partition (ends in a digit), not a whole disk." >&2
	echo "Pass the whole device, e.g. /dev/sdb, not /dev/sdb1." >&2
	exit 1
fi

echo "About to COMPLETELY ERASE:"
lsblk -o NAME,SIZE,MODEL,TRAN "$DEVICE"
echo ""
read -r -p "Type YES (all caps) to continue: " CONFIRM
if [ "$CONFIRM" != "YES" ]; then
	echo "Aborted."
	exit 1
fi

echo "==> Unmounting any mounted partitions of $DEVICE..."
for p in "$DEVICE"*; do
	[ "$p" = "$DEVICE" ] && continue
	umount "$p" 2>/dev/null || true
done

echo "==> Writing the ISO (this takes a while — it's writing $(du -h "$ISO" | cut -f1))..."
dd if="$ISO" of="$DEVICE" bs=4M status=progress conv=fsync
sync
blockdev --rereadpt "$DEVICE" 2>/dev/null || partprobe "$DEVICE" 2>/dev/null || true
sleep 2

echo "==> Adding a 'persistence' partition in the remaining space..."
# The ISO's own hybrid partition table ends where the ISO's data does;
# parted's 100% target grabs everything after it for the new partition.
parted --script "$DEVICE" -- mkpart primary ext4 "$(du -BMiB "$ISO" | cut -f1 | tr -d 'MiB')MiB" 100%
blockdev --rereadpt "$DEVICE" 2>/dev/null || partprobe "$DEVICE" 2>/dev/null || true
sleep 2

PERSIST_PART=""
for i in 1 2 3 4 5; do
	# The partition we just added is always the last one lsblk lists for
	# this device — simpler and more robust than trying to compute its
	# number from the ISO's own (variable) partition layout.
	CANDIDATE="/dev/$(lsblk -ln -o NAME "$DEVICE" | tail -n 1)"
	if [ -b "$CANDIDATE" ] && [ "$CANDIDATE" != "$DEVICE" ]; then PERSIST_PART="$CANDIDATE"; break; fi
	sleep 1
	blockdev --rereadpt "$DEVICE" 2>/dev/null || true
done
if [ -z "$PERSIST_PART" ] || [ ! -b "$PERSIST_PART" ]; then
	echo "Couldn't determine the new partition's device node automatically." >&2
	echo "Run 'lsblk $DEVICE', find the new (last, largest) partition, then:" >&2
	echo "  mkfs.ext4 -F -L persistence <that partition>" >&2
	echo "  mount <that partition> /mnt && echo '/var/lib/digital-signage-kiosk union' > /mnt/persistence.conf && umount /mnt" >&2
	exit 1
fi

echo "==> Formatting $PERSIST_PART as the persistence partition..."
mkfs.ext4 -F -L persistence "$PERSIST_PART"

MOUNT_TMP="$(mktemp -d)"
mount "$PERSIST_PART" "$MOUNT_TMP"
echo "/var/lib/digital-signage-kiosk union" > "$MOUNT_TMP/persistence.conf"
umount "$MOUNT_TMP"
rmdir "$MOUNT_TMP"

echo ""
echo "✅ USB stick ready: $DEVICE"
echo "   Boot partition: the ISO (Digital Signage Kiosk, GRUB, boots on its own)"
echo "   Data partition: $PERSIST_PART (label 'persistence') — keeps the"
echo "   kiosk's pairing token across reboots."
echo ""
echo "Next: plug it into the target PC, set it first in the boot order (see"
echo "README.md), and boot — no further setup needed."
