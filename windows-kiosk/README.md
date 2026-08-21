# Windows Kiosk Player

Turns a Windows PC into a Digital Signage player: on sign-in it automatically
opens your player URL full-screen in a chrome-less kiosk browser window — no
address bar, no tabs, no taskbar interaction. Since a kiosk browser normally
can't be closed by a mouse/keyboard user (no window chrome, Alt+F4 is
suppressed by kiosk mode), a **global keyboard shortcut** (default
`Ctrl+Alt+Shift+Q`) closes it back down to the desktop.

Works with **Microsoft Edge** (built into Windows 10/11, default) or
**Google Chrome**. No installer/build step — it's PowerShell, run directly.

## 1. Get your player URL

In wp-admin, go to **Digital Signage → Pair a Screen** and follow the
pairing flow to get:

```
https://yourdomain.com/signage/play/<token>/
```

## 2. Install

Copy this `windows-kiosk` folder to the PC, open **PowerShell as
Administrator is NOT required** (it installs for the current user only),
and run:

```powershell
cd path\to\windows-kiosk
powershell -ExecutionPolicy Bypass -File .\install-kiosk.ps1 -Url "https://yourdomain.com/signage/play/<token>/"
```

That's it — sign out and back in (or reboot) and the kiosk starts
automatically, hidden, with no console window.

### Custom close hotkey

Default is `Ctrl+Alt+Shift+Q`. To use something else:

```powershell
.\install-kiosk.ps1 -Url "https://yourdomain.com/signage/play/<token>/" -CloseModifiers Ctrl,Alt -CloseKey X
```

`CloseModifiers` accepts any combination of `Ctrl`, `Alt`, `Shift`, `Win`.
`CloseKey` is a single key name (letters, numbers, function keys like `F9`).

### Use Chrome instead of Edge

```powershell
.\install-kiosk.ps1 -Url "https://yourdomain.com/signage/play/<token>/" -Browser chrome
```

## 3. (Optional) True "boots straight to signage" kiosk PC

Windows has no built-in equivalent of a Linux console autologin — a user
account still has to sign in before anything in their Startup/Run entries
can run. For an unattended kiosk PC, set up **Windows auto-logon** for a
dedicated, low-privilege account:

1. Press `Win+R`, run `netplwiz`.
2. Uncheck *"Users must enter a user name and password to use this
   computer"*.
3. Select the kiosk account and enter its password when prompted.

The PC will now boot straight to the desktop and immediately into the
kiosk browser.

## What it does

- `kiosk-player.ps1` — the player itself. Launches the browser with
  `--kiosk`, hides its own console window, registers the global close
  hotkey via the Win32 `RegisterHotKey` API (works even while the browser
  has focus), and watches the browser process — if it ever exits on its
  own (crash/update), it's relaunched automatically after 2 seconds.
- `install-kiosk.ps1` — copies `kiosk-player.ps1` to
  `%ProgramData%\DigitalSignageKiosk\` and adds a `HKCU...\Run` registry
  entry so it launches hidden on every sign-in for the current user.
- `uninstall-kiosk.ps1` — stops any running kiosk session, removes the
  registry entry and the installed files.

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
