# Windows Kiosk Player

Turns a Windows PC into a Digital Signage player: on sign-in it automatically
opens your player URL full-screen — in a chrome-less kiosk browser window on
**every connected monitor**, not just the primary one — with no address bar,
no tabs, no taskbar interaction, and by default (see
[section 2](#2-windows-auto-sign-in-on-by-default)) **no desktop at all** —
the kiosk *is* the account's Windows shell. Since a kiosk browser normally
can't be closed by a mouse/keyboard user (no window chrome, Alt+F4 is
suppressed by kiosk mode), a **global keyboard shortcut** (default
`Ctrl+Alt+Shift+Q`) closes it — which, with no desktop underneath, ends the
Windows session and (with auto sign-in on) immediately restarts the kiosk,
i.e. it's a "restart the kiosk" hotkey rather than a way out to a desktop.

Works with **Microsoft Edge** (built into Windows 10/11, default) or
**Google Chrome**. No build step for the scripts themselves — it's
PowerShell, run directly; the `.exe` below is just a convenience wrapper.

## 0. One-click .exe installer

`DigitalSignageKioskSetup.exe` bundles all four scripts into a single
double-clickable Windows installer (built from `installer.nsi` with
[NSIS](https://nsis.sourceforge.io/)). Copy it to the kiosk PC and
double-click it:

1. One UAC prompt appears — approve it.
2. A small progress window shows `install-kiosk.ps1` running live, against
   the default site with auto sign-in and kiosk shell replacement all on.
3. It closes itself when done. Sign out and back in (or reboot) and the
   kiosk starts on every screen — with no desktop ever showing, per
   [section 2](#2-windows-auto-sign-in-on-by-default).

Just like a normal Windows application, it installs into **Program Files**
(`%ProgramFiles%\Digital Signage Kiosk`) and writes a real **`Uninstall.exe`**
there, registered under **Settings → Apps** (or Control Panel → Programs) as
"Digital Signage Kiosk" — uninstall from there, or run `Uninstall.exe`
directly; either way it undoes the *entire* install (auto sign-in, the
dedicated `Kiosk` account, shell replacement, kiosk hardening, and its own
files), matching how thoroughly the installer set it up in the first place.

To install against a different site or with different options, use one of
the script-based installs below instead — the `.exe` always runs
`install-kiosk.ps1` with no arguments (its defaults). Rebuild it after
editing any of the four scripts with:

```
makensis installer.nsi
```

## 1. Install (scripts)

### Fully local / offline install (recommended)

`install-standalone.ps1` is a **single self-contained file** — it has
`install-kiosk.ps1`, `kiosk-player.ps1`, `ds-controller-agent.ps1`,
`uninstall-kiosk.ps1` and `DigitalSignageKioskLauncher.exe` embedded inside
it, so there's nothing to fetch from GitHub and no folder to copy at
install time. Get this one file onto the
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
Administrator: a **UAC prompt appears automatically** — approving it is the
only thing to click. No password prompt: it assumes the account has no
password (the normal setup for a dedicated kiosk account) and configures
auto sign-in with a blank one; if the account *does* have a password, pass
`-AutoLogonPassword` yourself (see `install-kiosk.ps1`'s parameter docs). The
PC then boots straight to the desktop and into the kiosk on every restart —
no keyboard, mouse, or monitor needed after the one-time install. Don't want
that? Add `-EnableAutoLogon:$false` and it installs unelevated, just
launching the kiosk after whoever signs in normally:

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
approve it, no password prompt follows (a passwordless account gets a blank
AutoAdminLogon password automatically; pass `-AutoLogonPassword` if the
account has a real one). Add `-EnableAutoLogon:$false` to skip that and
install unelevated instead.

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
approving it is the only click needed. There's no password prompt: an
account with no password (the normal setup for a dedicated kiosk account)
gets a blank AutoAdminLogon password, which is exactly what it needs — pass
`-AutoLogonPassword` as a `SecureString` yourself if the account has a real
password.

Don't want auto sign-in? Pass `-EnableAutoLogon:$false` and the installer
stays unelevated — the kiosk still launches automatically, just after
whoever signs in normally:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -EnableAutoLogon:$false
```

### A dedicated "Kiosk" account (on by default)

Auto sign-in above signs into a **dedicated local account** (`-KioskUsername`,
default `Kiosk`) that the installer creates for you, with no password,
rather than whichever account happened to run the installer — so a normal
user account (e.g. the one used to set the machine up) never becomes the
thing that auto-signs-in and boots into the kiosk.

That account may never have actually signed in yet, so its shell/screen-saver
settings can't be written to its own registry hive the normal way (it
doesn't exist until first sign-in) — the installer instead writes them into
Windows's **Default Profile template**, which every new local profile is
seeded from, so they take effect the instant the account signs in for the
first time (which `AutoAdminLogon` does automatically on the next reboot).

Prefer to auto sign into the account you're already running the installer
as, instead of creating a new one? Pass `-CreateKioskUser:$false`:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -CreateKioskUser:$false
```

### No keyboard or mouse, ever

Enabling auto sign-in also hardens the PC for running with **no input
device attached at all** — since there's nobody there to click through
anything, nothing on the machine should be able to end up waiting for a
click:

- Sleep, hibernate, and display-off are all disabled (`powercfg`) — nothing
  needs to be woken up.
- The screen saver is turned off, so it can never leave the kiosk behind a
  lock/sign-in screen nobody can dismiss.
- Windows Error Reporting's "this program has stopped working" dialog is
  suppressed.
- A pending Windows Update won't pop an interactive "we're restarting, save
  your work" prompt — it still updates and reboots on its own schedule,
  straight back into the kiosk via `AutoAdminLogon`.
- Windows Spotlight / "suggested content" / tips overlays (the occasional
  full-screen takeover after sign-in or a feature update) are disabled.

These are one-way conveniences applied alongside `AutoAdminLogon`;
`.\uninstall-kiosk.ps1 -DisableAutoLogon` reverts them together with auto
sign-in itself.

### No desktop, ever ("force kiosk mode")

By default (`-ReplaceShell`, on by default) the kiosk is installed as the
account's **Windows shell** — the actual replacement for `explorer.exe` —
instead of just something that launches on top of a normal desktop. Combined
with auto sign-in, this means the PC never shows a desktop, taskbar, Start
menu, or "default user" sign-in screen at all: from power-on it goes
straight to the kiosk and nothing else is reachable.

Don't want that (e.g. while testing, or to keep normal desktop access for
maintenance)? Pass `-ReplaceShell:$false` and the kiosk goes back to
launching via the Run key on top of a normal desktop instead:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -ReplaceShell:$false
```

**Emergency recovery**: with no desktop, the usual ways back in (right-click
desktop, Win key, Alt+Tab) don't exist. `Ctrl+Shift+Esc` still opens Task
Manager, though — it's a raw Windows hotkey handled independently of the
shell — and from there, **File → Run new task → `explorer.exe`** (or
`powershell.exe`) gets a normal desktop back without uninstalling anything.
`.\uninstall-kiosk.ps1` always restores `explorer.exe` as the shell.

⚠️ **Security note**: `AutoAdminLogon` is a Windows OS feature, not
something specific to this script — it works by storing the account's
password in the registry in a form Windows itself can read back in
cleartext. Only use it on a **dedicated, low-privilege kiosk account** with
no sensitive access (not your everyday Windows login), on hardware that's
physically secured. To turn it back off later (and delete the dedicated
`Kiosk` account too, if `-CreateKioskUser` made one):

```powershell
.\uninstall-kiosk.ps1 -DisableAutoLogon -RemoveKioskUser   # from an elevated PowerShell
```

### Real Windows "kiosk mode" (Assigned Access)

Windows has an actual, official single-app kiosk feature — **Assigned
Access** — behind **Settings → Accounts → Family & other users → Set up a
kiosk → Choose a kiosk app**. That page's own app picker only lists Store
apps, so a Win32 app like this one can never be *chosen* through that
specific screen — but the feature itself has fully supported Win32 apps
since Windows 10 1809, and by default (`-UseAssignedAccess`) this installer
configures it directly, the same way an MDM (e.g. Intune) would: via the
local WMI Bridge provider, no Store app, MDM enrollment, or manual wizard
click-through needed.

Requires **Windows 10/11 Pro, Enterprise, or Education** — Assigned Access
doesn't exist on Home. If the edition doesn't support it, or anything else
about it fails, the installer skips it with a yellow warning and moves on;
`-ReplaceShell` + `AutoAdminLogon` (configured either way) already deliver
the same practical outcome — no desktop, boots straight to the kiosk — on
every edition, so nothing about the kiosk *depends* on this succeeding. It's
an extra, "make it official" layer on top, not a requirement.

Under the hood: install-kiosk.ps1 points Assigned Access at
`DigitalSignageKioskLauncher.exe`, a tiny fixed-arguments wrapper that runs
`kiosk-player.ps1` — Assigned Access always launches one exe with no
arguments of its own, so `kiosk-player.ps1` reads its URL/browser/hotkey
from `kiosk-config.json` (written by install-kiosk.ps1) instead of the
command line whenever none are passed. Don't want this at all? Pass
`-UseAssignedAccess:$false`:

```powershell
.\install-kiosk.ps1 -Site "https://yourdomain.com" -UseAssignedAccess:$false
```

`.\uninstall-kiosk.ps1 -DisableAutoLogon` clears the Assigned Access
configuration alongside everything else.

## What it does

- `kiosk-player.ps1` — the player itself. Launches one `--kiosk` browser
  window per connected monitor (positioned/sized to exactly cover it),
  hides its own console window, registers the global close hotkey via the
  Win32 `RegisterHotKey` API (works even while a browser window has focus),
  and watches each browser process — if any of them exits on its own
  (crash/update), just that one is relaunched automatically after 2 seconds.
- `install-kiosk.ps1` — copies itself, `kiosk-player.ps1`,
  `ds-controller-agent.ps1`, and `uninstall-kiosk.ps1` to
  `%ProgramFiles%\Digital Signage Kiosk\` (per-device state like the pairing
  token stays in `%ProgramData%\DigitalSignageKiosk\device-token.txt`
  instead), registers a normal "Apps & Features" uninstall entry, generates
  and saves a permanent device token (when using `-Site`), and (by default,
  `-ReplaceShell`) sets it as the account's `...\Winlogon\Shell` in place of
  `explorer.exe` — or, with `-ReplaceShell:$false`, adds a `...\Run` entry
  instead so it launches hidden on top of a normal desktop. Either way the
  URL is baked into that registry command, so it stays fixed across reboots
  without re-running the installer.
- `uninstall-kiosk.ps1` — stops any running kiosk session, restores
  `explorer.exe` as the shell (in the `Kiosk` account's own or Default
  profile, offline, if that's where it was set), removes the registry
  entries and the installed files (Program Files app folder included); pass
  `-DisableAutoLogon` (elevated) to also turn off Windows auto sign-in and
  the kiosk-hardening settings, and/or `-RemoveKioskUser` (elevated) to
  delete the dedicated account and its
  profile, if `-EnableAutoLogon`/`-CreateKioskUser` were used.
- `ds-controller-agent.ps1` — the `-MultiDisplay` controller: detects every
  connected monitor and opens an isolated kiosk browser profile on each one.
- `install-standalone.ps1` — single-file, offline version of the install
  above with the four scripts and `DigitalSignageKioskLauncher.exe` all
  embedded (base64); regenerate it with `build-standalone.ps1` after
  editing any of them.
- `bootstrap.ps1` — fetches the four scripts from GitHub and runs
  `install-kiosk.ps1`, for the remote one-line install.
- `installer.nsi` / `DigitalSignageKioskSetup.exe` — the one-click `.exe`
  installer ([section 0](#0-one-click-exe-installer)); the `.nsi` is the
  NSIS source, rebuilt into the `.exe` with `makensis installer.nsi`.
- `kiosk-launcher.nsi` / `DigitalSignageKioskLauncher.exe` — the tiny,
  fixed-arguments wrapper Windows Assigned Access points at (see [Real
  Windows "kiosk mode"](#real-windows-kiosk-mode-assigned-access) above);
  rebuild with `makensis kiosk-launcher.nsi`.

## Closing the kiosk

Press the configured hotkey (default `Ctrl+Alt+Shift+Q`). This closes every
browser window (all screens) and exits the player script. With `-ReplaceShell` (the
default), that ends the Windows session entirely — auto sign-in immediately
signs back in and restarts the kiosk. With `-ReplaceShell:$false`, it exits
back to the normal desktop underneath. To start it again without signing
out, either sign back in, or run:

```powershell
Start-Process powershell -ArgumentList '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "%ProgramFiles%\Digital Signage Kiosk\kiosk-player.ps1" -Url "https://yourdomain.com/signage/play/<token>/"'
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
