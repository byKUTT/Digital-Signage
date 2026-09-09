; Digital Signage Windows Kiosk — one-click installer.
;
; Bundles install-kiosk.ps1, kiosk-player.ps1, ds-controller-agent.ps1 and
; uninstall-kiosk.ps1 into a single .exe. Requests admin once (a single UAC
; prompt); install-kiosk.ps1 then sees it's already elevated and runs the
; full unattended install (default site, auto sign-in, kiosk shell
; replacement, kiosk hardening) straight through, no further clicks.

Unicode true
Name "Digital Signage Kiosk Setup"
OutFile "DigitalSignageKioskSetup.exe"
InstallDir "$TEMP\ds-kiosk-install"
RequestExecutionLevel admin
ShowInstDetails show
SilentInstall normal

!include "MUI2.nsh"
!include "LogicLib.nsh"

!define MUI_ABORTWARNING
!define MUI_FINISHPAGE_NOAUTOCLOSE

!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_LANGUAGE "English"

Section "Install"
	SetOutPath "$INSTDIR"
	File "install-kiosk.ps1"
	File "kiosk-player.ps1"
	File "ds-controller-agent.ps1"
	File "uninstall-kiosk.ps1"

	DetailPrint "Running install-kiosk.ps1 (default site, auto sign-in, kiosk shell)..."
	nsExec::ExecToLog '"powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\install-kiosk.ps1"'
	Pop $0
	DetailPrint "install-kiosk.ps1 exit code: $0"

	${If} $0 == 0
		DetailPrint "Done. Sign out and back in (or reboot) to start the kiosk."
	${Else}
		MessageBox MB_ICONEXCLAMATION|MB_OK "install-kiosk.ps1 exited with code $0 — check the log above for details."
	${EndIf}
SectionEnd
