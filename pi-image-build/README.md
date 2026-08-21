# Building a flashable Digital Signage `.img`

For most people, [`../raspberry-pi-kiosk/install-kiosk.sh`](../raspberry-pi-kiosk/)
is the right tool — flash stock Raspberry Pi OS Lite, boot it, run one
script. This folder is for the less common case: you want a pre-baked
`.img` file with everything already included, so flashing an SD card is the
*only* step (useful for provisioning many identical screens at once).

It's a [`pi-gen`](https://github.com/RPi-Distro/pi-gen) configuration — the
same tool the Raspberry Pi Foundation uses to build the official Raspberry
Pi OS images — with one extra stage (`stage-ds-kiosk/`) that bakes in the
kiosk session, `ds-agent` (remote management), and the first-boot WiFi/site
setup portal from `../raspberry-pi-kiosk/`.

## Why this isn't a ready-made `.img` file you can just download here

Building an image means downloading and assembling a full Raspberry Pi OS
root filesystem (about 1–2 GB) inside a `chroot`/loop-mounted disk image,
which needs real internet access and root/loop-device privileges. Neither
is available in the sandboxed environment this was written in, and the
result wouldn't fit comfortably through a chat file transfer anyway. This
config is the real, working recipe for producing that file — you run it
somewhere with those things available: your own Linux machine, or (easiest)
GitHub Actions, which already has both.

**pi-gen note:** this stage config targets pi-gen's current layout as of
2024–2025 (a `STAGE_LIST` env var selecting `stage0 stage1 stage2
stage-ds-kiosk` — i.e. Raspberry Pi OS *Lite* plus our stage, skipping the
Desktop-only stage3–5). If a future pi-gen release changes that mechanism,
check pi-gen's own `README.md` for how stage selection works and adjust
`config` accordingly — the `stage-ds-kiosk/` scripts themselves don't
depend on that detail.

## Option A: GitHub Actions (recommended — no local setup)

Push this repo to GitHub (already done if you're reading this from the
repo) and run the **Build Raspberry Pi Image** workflow
(`.github/workflows/build-pi-image.yml`) from the Actions tab, or let it
run on its normal trigger. It clones `pi-gen`, drops this stage in, and
builds a Raspberry Pi OS Lite–based image with everything pre-installed.
When it finishes, download the `.img.xz` from the workflow run's Artifacts
(or, if you tag a release, from the Release assets — see the workflow file
for how to enable that).

Build time is typically 30–60 minutes; GitHub Actions provides the disk
space and privileges pi-gen needs.

## Option B: Build locally (Linux, root, ~4GB free disk)

```bash
sudo apt-get install -y git quilt qemu-user-static debootstrap zerofree \
  zip dosfstools libarchive-tools libcap2-bin grep rsync xz-utils \
  file kmod bc pigz arch-test

git clone https://github.com/RPi-Distro/pi-gen.git
cp -r pi-image-build/stage-ds-kiosk pi-gen/
cp pi-image-build/config pi-gen/config
cd pi-gen
sudo ./build.sh
```

The finished `.img` appears under `pi-gen/deploy/`.

## What the image does on first boot

Same as running `install-kiosk.sh` interactively, minus the "run a script"
part — the setup portal (`ds-setup.service`) starts automatically:

1. No `/etc/digital-signage-kiosk.conf` exists yet (fresh image), so the Pi
   puts up an open WiFi network `Digital-Signage-Setup-XXXX`.
2. Connect from a phone, open `http://10.42.0.1/`, enter your WiFi network
   and your WordPress site's URL.
3. The Pi connects, generates its permanent pairing token, finishes setup,
   and reboots into its pairing screen (code + QR).
4. Pair it in wp-admin. It's now remotely manageable from the Screen's
   Device panel — see the main [`raspberry-pi-kiosk/README.md`](../raspberry-pi-kiosk/README.md).

## Customizing

- `config` — image name, Raspberry Pi OS release/architecture, default
  hostname.
- `stage-ds-kiosk/00-install-kiosk/00-packages` — packages installed into
  the image.
- `stage-ds-kiosk/00-install-kiosk/00-run-chroot.sh` — what gets configured
  inside the image (kiosk session, autologin, ds-agent, setup portal).
- `stage-ds-kiosk/00-install-kiosk/files/` — copied verbatim from
  `../raspberry-pi-kiosk/` by the GitHub Actions workflow (and by Option B's
  commands above) before the build runs, so there's one source of truth for
  those scripts.
