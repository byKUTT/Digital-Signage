; Tiny launcher for Windows Assigned Access (single-app kiosk mode).
;
; Assigned Access's "KioskModeApp DesktopAppPath" points at one .exe with no
; arguments and no config of its own — it just launches that exact path and
; tracks the process for the account's whole session. kiosk-player.ps1 needs
; a URL and options, so this launcher runs it via powershell.exe and blocks
; (ExecWait) until it exits, which is what makes *this* process the thing
; Assigned Access is watching. kiosk-player.ps1 reads its own settings from
; kiosk-config.json (written by install-kiosk.ps1) when no -Url is given —
; see kiosk-player.ps1's own comments.
;
; $EXEDIR resolves to wherever this .exe itself is running from, so it finds
; kiosk-player.ps1 alongside it without any path baked in at compile time.

Unicode true
Name "Digital Signage Kiosk Launcher"
OutFile "DigitalSignageKioskLauncher.exe"
SilentInstall silent
RequestExecutionLevel user

Section
	ExecWait '"powershell.exe" -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "$EXEDIR\kiosk-player.ps1"'
SectionEnd
