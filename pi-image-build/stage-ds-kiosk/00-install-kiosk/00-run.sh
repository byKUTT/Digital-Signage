#!/bin/bash -e
# Runs on the HOST during the pi-gen build (pi-gen's convention for a plain
# "NN-run.sh" — as opposed to "NN-run-chroot.sh", which runs inside the
# target chroot). Copies our scripts onto the target filesystem via
# $ROOTFS_DIR; 01-run-chroot.sh (which runs after this, inside the chroot)
# installs them into their final locations and enables the services.
#
# The GitHub Actions workflow (and Option B's manual steps in ../../README.md)
# populate ./files/ from ../../../raspberry-pi-kiosk/ before pi-gen runs, so
# that folder — not this repo layout — is the single source of truth for
# ds-agent.py, the setup portal, and the two installer scripts.

install -d "${ROOTFS_DIR}/opt/digital-signage-kiosk/ds-agent"
install -d "${ROOTFS_DIR}/opt/digital-signage-kiosk/setup-portal"

install -m 755 files/install-kiosk.sh "${ROOTFS_DIR}/opt/digital-signage-kiosk/install-kiosk.sh"
install -m 755 files/uninstall-kiosk.sh "${ROOTFS_DIR}/opt/digital-signage-kiosk/uninstall-kiosk.sh"

install -m 755 files/ds-agent/ds-agent.py "${ROOTFS_DIR}/opt/digital-signage-kiosk/ds-agent/ds-agent.py"
install -m 644 files/ds-agent/ds-agent.service "${ROOTFS_DIR}/opt/digital-signage-kiosk/ds-agent/ds-agent.service"

install -m 755 files/setup-portal/ds-setup-portal.py "${ROOTFS_DIR}/opt/digital-signage-kiosk/setup-portal/ds-setup-portal.py"
install -m 755 files/setup-portal/ds-setup-ap-up.sh "${ROOTFS_DIR}/opt/digital-signage-kiosk/setup-portal/ds-setup-ap-up.sh"
install -m 755 files/setup-portal/ds-setup-ap-down.sh "${ROOTFS_DIR}/opt/digital-signage-kiosk/setup-portal/ds-setup-ap-down.sh"
install -m 644 files/setup-portal/ds-setup.service "${ROOTFS_DIR}/opt/digital-signage-kiosk/setup-portal/ds-setup.service"
