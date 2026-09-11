# VIDAA 9 private TV player

- [x] Add a rewrite-independent `/signage/tv/` fallback after live-site 404 verification.

- [x] Add the `/signage/tv/` launcher route and persistent device pairing.
- [x] Add VIDAA remote, fullscreen, resume and heartbeat compatibility.
- [x] Update plugin version, architecture notes and setup documentation.
- [x] Run available JavaScript/static checks and browser-flow tests (PHP CLI unavailable in this runtime).
- [x] Rebuild and inspect `digital-signage.zip`.
- [x] Commit and publish the VIDAA update to GitHub.
# Ubuntu multi-display controller 3.2.0

- [x] Confirm separate WordPress screen/channel per physical monitor.
- [x] Remove daily and watchdog computer reboot requirements.
- [x] Add Ubuntu-native installer and black-screen migration recovery.
- [x] Add multi-output Firefox controller using the existing controller REST API.
- [x] Add settings-preserving `digital-signage-update` command.
- [x] Add safe Ubuntu uninstaller and systemd units with no auto-reboot behavior.
- [x] Document install, pairing, update, recovery, and troubleshooting.
- [x] Update WordPress controller telemetry UI and bump all plugin versions to 3.2.0.
- [x] Add controller tests and run shell/Python/static verification (PHP CLI and ShellCheck unavailable locally).
- [x] Rebuild and byte-verify Ubuntu and WordPress ZIP packages.
- [x] Update architecture notes, commit, and publish fast-forward-only to GitHub.

---

# Ubuntu Chrome boot autostart 3.2.1

- [x] Replace early system-service startup with GDM graphical-session autostart.
- [x] Install/detect Chrome and migrate preserved Ubuntu controller settings.
- [x] Launch one isolated Chrome kiosk process at each XRandR output geometry.
- [x] Add controller single-instance locking and graphical-session crash recovery.
- [x] Update the settings-preserving updater and uninstaller.
- [x] Update Ubuntu and WordPress version/documentation metadata to 3.2.1.
- [x] Run shell, Python, static, multi-monitor, and no-auto-reboot verification.
- [x] Rebuild and byte-verify both ZIP archives.
- [x] Commit and publish the fast-forward update to GitHub.
