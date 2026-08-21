#!/bin/bash -e
# Runs INSIDE the target image's chroot (pi-gen's convention for
# "NN-run-chroot.sh"), after 00-run.sh has copied our files onto the target
# filesystem at /opt/digital-signage-kiosk/ (see that script's comment).
# Installs them into their final locations and enables the services.
#
# No /etc/digital-signage-kiosk.conf is written here — its absence is what
# makes ds-setup.service's ConditionPathExists fire on first boot and put up
# the WiFi setup hotspot. install-kiosk.sh (called by the portal once you
# submit the form) writes it and takes it from there. See
# ../../README.md for the full first-boot flow.

install -m 755 /opt/digital-signage-kiosk/ds-agent/ds-agent.py /usr/local/bin/ds-agent.py
install -m 755 /opt/digital-signage-kiosk/setup-portal/ds-setup-portal.py /usr/local/bin/ds-setup-portal.py
install -m 755 /opt/digital-signage-kiosk/setup-portal/ds-setup-ap-up.sh /usr/local/bin/ds-setup-ap-up.sh
install -m 755 /opt/digital-signage-kiosk/setup-portal/ds-setup-ap-down.sh /usr/local/bin/ds-setup-ap-down.sh
install -m 755 /opt/digital-signage-kiosk/install-kiosk.sh /usr/local/bin/install-kiosk.sh

install -m 644 /opt/digital-signage-kiosk/ds-agent/ds-agent.service /etc/systemd/system/ds-agent.service
install -m 644 /opt/digital-signage-kiosk/setup-portal/ds-setup.service /etc/systemd/system/ds-setup.service

systemctl enable ds-agent.service
systemctl enable ds-setup.service
