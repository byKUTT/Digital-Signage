# Windows Kiosk Player

Turns a Windows PC into a Digital Signage player: on sign-in it automatically
opens your player URL full-screen in a chrome-less kiosk browser window — no
address bar, no tabs, no taskbar interaction. Since a kiosk browser normally
can't be closed by a mouse/keyboard user (no window chrome, Alt+F4 is
suppressed by kiosk mode), a **global keyboard shortcut** (default
`Ctrl+Alt+Shift+Q`) closes it back down to the desktop.

Works with **Microsoft Edge** (built into Windows 10/11, default) or
**Google Chrome**. No installer/build step — it's PowerShell, run directly.

## 1. Install

### Fully local / offline install (recommended)

`install-standalone.ps1` is a **single self-contained file** — it has
`install-kiosk.ps1`, `kiosk-player.ps1`, `ds-controller-agent.ps1` and
`uninstall-kiosk.ps1` embedded inside it, so there's nothing to fetch from
GitHub and no folder to copy at install time. Get this one file onto the
kiosk PC however you like (USB stick, network share, email attachment,
`scp`, ...), then paste this single line into `cmd` (or PowerShell) and
press Enter:

```cmd
powershell -ExecutionPolicy Bypass -File .\install-standalone.ps1
```

That's the whole install. It writes the embedded scripts out locally and
runs them against **`https://test.kutt.ee`** (the default site baked into
`install-kiosk.ps1`) — the only network access this needs, before or after
install, is to that signage site itself.

**Windows auto sign-in is turned on by default** (see [section
2](#2-windows-auto-sign-in-on-by-default) below), so this needs
Administrator: a **UAC prompt appears automatically** — approve it, then
type the current account's password when asked (used only to configure
Windows's sign-in, nothing is sent anywhere). The PC then boots straight to
the desktop and into the kiosk on every restart, no keyboard/mouse needed.
Don't want that? Add `-EnableAutoLogon:$false` and it installs unelevated,
just launching the kiosk after whoever signs in normally:

```cmd
powershell -ExecutionPolicy Bypass -File .\install-standalone.ps1 -EnableAutoLogon:$false
```

Either way, sign out and back in (or reboot) and the kiosk starts. On first
launch it shows a pairing code and QR code full-screen; scan the QR (or
enter the code manually in **Digital Signage → Pair a Screen**) and it links
up. The same identity persists across every reboot.

Pass options through exactly like `install-kiosk.ps1` — they're forwarded
as-is:

```powershell
.\install-standalone.ps1 -Site "https://yourdomain.com"
.\install-standalone.ps1 -Site "https://yourdomain.com" -MultiDisplay
```

If you edit `install-kiosk.ps1`, `kiosk-player.ps1`,
`ds-controller-agent.ps1` or `uninstall-kiosk.ps1`, regenerate the embedded
copy with `.\build-standalone.ps1` before redistributing
`install-standalone.ps1` — don't hand-edit its base64 blobs.

### Remote one-line install (needs internet access to GitHub)

If the kiosk PC has internet access and you'd rather not carry a file over,
this fetches the same scripts straight from GitHub instead of embedding
them:

```cmd
powershell -NoProfile -ExecutionPolicy Bypass -Command "irm https://raw.githubusercontent.com/byKUTT/Digital-Signage/claude/wonderful-feynman-bm3zws/windows-kiosk/bootstrap.ps1 | iex"
```

To install against a different site or pass other options (e.g.
`-MultiDisplay`), download `bootstrap.ps1` first and call it with arguments
instead of piping straight into `iex`:

```powershell
irm https://raw.githubusercontent.com/byKUTT/Digital-Signage/claude/wonderful-feynman-bm3zws/windows-kiosk/bootstrap.ps1 -OutFile bootstrap.ps1
.\bootstrap.ps1 -Site "https://yourdomain.com"
```

### Manual install (from a local copy)

This device generates and remembers **its own pairing identity** — no need
to pre-create a screen in wp-admin first. Copy this `windows-kiosk` folder
to the PC and run:

```powershell
cd path\to\windows-kiosk
powershell -ExecutionPolicy Bypass -File .\install-kiosk.ps1 -Site "https://yourdomain.com"
```

Auto sign-in is on by default (see [section
2](#2-windows-auto-sign-in-on-by-default)), so this triggers a UAC prompt —
approve it and enter the account password when asked. Add
`-EnableAutoLogon:$false` to skip that and install unelevated instead.

That's it — sign out and back in (or reboot) and the kiosk starts
automatically, hidden, with no console window. On first launch it shows a
pairing code and QR code full-screen; scan the QR (or enter the code
manually in **Digital Signage → Pair a Screen**) and it links up. The same
identity persists across every reboot.

Already paired a screen in wp-admin and have its exact player URL? Use
`-Url` instead of `-Site` and no local token is generated:

```powershell
.\install-kiosk.ps1 -Url "https://yourdomain.com/signage/play/<token>/"
```

### Custom close hotkey

Default is `Ctrl+Alt+Shift+Q`. To use something else:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -CloseModifiers Ctrl,Alt -CloseKey X
```

`CloseModifiers` accepts any combination of `Ctrl`, `Alt`, `Shift`, `Win`.
`CloseKey` is a single key name (letters, numbers, function keys like `F9`).

### Use Chrome instead of Edge

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -Browser chrome
```

### Re-pairing this PC as a different screen

Re-running the installer keeps the existing device token. To force a new
one (e.g. this PC is being re-purposed for a different physical screen):

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -Regenerate
```

## 2. Windows auto sign-in (on by default)

Windows has no built-in equivalent of a Linux console autologin — a user
account still has to sign in before anything in their Startup/Run entries
can run. So **every install above turns this on automatically**, using
Windows's built-in `AutoAdminLogon`: the PC boots straight to the desktop
and immediately into the kiosk browser — no keyboard, mouse, or monitor
needed after that.

This needs Administrator, which the installer gets itself — if it isn't
already running elevated, it relaunches itself with a **UAC prompt**;
approve it, then enter the current account's password when asked (unless
you passed `-AutoLogonPassword` as a `SecureString`).

Don't want auto sign-in? Pass `-EnableAutoLogon:$false` and the installer
stays unelevated — the kiosk still launches automatically, just after
whoever signs in normally:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -EnableAutoLogon:$false
```

⚠️ **Security note**: `AutoAdminLogon` is a Windows OS feature, not
something specific to this script — it works by storing the account's
password in the registry in a form Windows itself can read back in
cleartext. Only use it on a **dedicated, low-privilege kiosk account** with
no sensitive access (not your everyday Windows login), on hardware that's
physically secured. To turn it back off later:

```powershell
.\uninstall-kiosk.ps1 -DisableAutoLogon   # from an elevated PowerShell
```

## What it does

- `kiosk-player.ps1` — the player itself. Launches the browser with
  `--kiosk`, hides its own console window, registers the global close
  hotkey via the Win32 `RegisterHotKey` API (works even while the browser
  has focus), and watches the browser process — if it ever exits on its
  own (crash/update), it's relaunched automatically after 2 seconds.
- `install-kiosk.ps1` — copies `kiosk-player.ps1` to
  `%ProgramData%\DigitalSignageKiosk\`, generates and saves a permanent
  device token to `device-token.txt` (when using `-Site`), and adds a
  `HKCU...\Run` registry entry so the kiosk launches hidden on every
  sign-in for the current user — the URL is baked into that registry
  command, so it stays fixed across reboots without re-running the
  installer.
- `uninstall-kiosk.ps1` — stops any running kiosk session, removes the
  registry entry and the installed files; pass `-DisableAutoLogon` (elevated)
  to also turn off Windows auto sign-in if `-EnableAutoLogon` was used.
- `ds-controller-agent.ps1` — the `-MultiDisplay` controller: detects every
  connected monitor and opens an isolated kiosk browser profile on each one.
- `install-standalone.ps1` — single-file, offline version of the install
  above with all four scripts embedded (base64); regenerate it with
  `build-standalone.ps1` after editing any of them.
- `bootstrap.ps1` — fetches the four scripts from GitHub and runs
  `install-kiosk.ps1`, for the remote one-line install.

## Closing the kiosk

Press the configured hotkey (default `Ctrl+Alt+Shift+Q`). This closes the
browser window and exits the player script back to the normal desktop. To
start it again without signing out, either sign back in, or run:

```powershell
Start-Process powershell -ArgumentList '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "%ProgramData%\DigitalSignageKiosk\kiosk-player.ps1" -Url "https://yourdomain.com/signage/play/<token>/"'
```

## Uninstalling

```powershell
powershell -ExecutionPolicy Bypass -File .\uninstall-kiosk.ps1
```

## Notes & troubleshooting

- **Run this on a dedicated kiosk PC.** The close hotkey kills only the
  specific browser process the kiosk launched (by PID, not by name), but a
  kiosk-mode browser window still takes over the whole screen while
  running — it isn't meant to share a machine with everyday browsing.
- **"Could not find edge/chrome"**: pass `-Browser chrome` if only Chrome
  is installed, or install Edge/Chrome first.
- **Hotkey doesn't fire**: another app may already be using that exact
  combination. Re-run `install-kiosk.ps1` with different
  `-CloseModifiers`/`-CloseKey` values.
- **Script blocked by execution policy**: the install/uninstall commands
  above already pass `-ExecutionPolicy Bypass` for that single run; this
  doesn't change your system-wide PowerShell policy.
