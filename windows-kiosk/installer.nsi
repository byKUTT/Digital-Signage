; Digital Signage Windows Kiosk — one-click installer + matching uninstaller.
;
; Installs into Program Files like a normal Windows application. Bundles
; install-kiosk.ps1, kiosk-player.ps1, ds-controller-agent.ps1,
; uninstall-kiosk.ps1 and DigitalSignageKioskLauncher.exe; requests admin
; once (a single UAC prompt), extracts them to a temp staging folder, and
; runs install-kiosk.ps1 — which does the real Program Files install itself
; (auto sign-in, dedicated "Kiosk" account, shell replacement, Windows
; Assigned Access single-app kiosk mode, kiosk hardening, one browser window
; per connected screen). Also writes a normal "Apps & Features" uninstaller
; (Uninstall.exe, in the install folder) that runs uninstall-kiosk.ps1 with
; a full teardown (-DisableAutoLogon -RemoveKioskUser).

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
!define APPDIR "$PROGRAMFILES\Digital Signage Kiosk"
!define UNINST_KEY "Software\Microsoft\Windows\CurrentVersion\Uninstall\DigitalSignageKiosk"

!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES

!insertmacro MUI_LANGUAGE "English"

Section "Install"
	; This is only a staging folder for extraction — install-kiosk.ps1
	; copies its own working set into Program Files, so it's removed again
	; at the end of this section rather than being the real install location.
	SetOutPath "$INSTDIR"
	File "install-kiosk.ps1"
	File "kiosk-player.ps1"
	File "ds-controller-agent.ps1"
	File "uninstall-kiosk.ps1"
	File "DigitalSignageKioskLauncher.exe"

	DetailPrint "Running install-kiosk.ps1 (default site, auto sign-in, kiosk shell, all screens)..."
	nsExec::ExecToLog '"powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\install-kiosk.ps1"'
	Pop $0
	DetailPrint "install-kiosk.ps1 exit code: $0"

	${If} $0 == 0
		DetailPrint "Done. Sign out and back in (or reboot) to start the kiosk."
		WriteUninstaller "${APPDIR}\Uninstall.exe"
		WriteRegStr HKLM "${UNINST_KEY}" "DisplayName" "Digital Signage Kiosk"
		WriteRegStr HKLM "${UNINST_KEY}" "UninstallString" '"${APPDIR}\Uninstall.exe"'
		WriteRegStr HKLM "${UNINST_KEY}" "QuietUninstallString" '"${APPDIR}\Uninstall.exe" /S'
		WriteRegStr HKLM "${UNINST_KEY}" "InstallLocation" "${APPDIR}"
		WriteRegStr HKLM "${UNINST_KEY}" "Publisher" "Digital Signage"
		WriteRegDWORD HKLM "${UNINST_KEY}" "NoModify" 1
		WriteRegDWORD HKLM "${UNINST_KEY}" "NoRepair" 1
	${Else}
		MessageBox MB_ICONEXCLAMATION|MB_OK "install-kiosk.ps1 exited with code $0 — check the log above for details."
	${EndIf}

	RMDir /r "$INSTDIR"
SectionEnd

Section "Uninstall"
	DetailPrint "Running uninstall-kiosk.ps1 -DisableAutoLogon -RemoveKioskUser..."
	nsExec::ExecToLog '"powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "${APPDIR}\uninstall-kiosk.ps1" -DisableAutoLogon -RemoveKioskUser'
	Pop $0
	DetailPrint "uninstall-kiosk.ps1 exit code: $0"

	DeleteRegKey HKLM "${UNINST_KEY}"
	Delete "${APPDIR}\install-kiosk.ps1"
	Delete "${APPDIR}\kiosk-player.ps1"
	Delete "${APPDIR}\ds-controller-agent.ps1"
	Delete "${APPDIR}\uninstall-kiosk.ps1"
	Delete "${APPDIR}\DigitalSignageKioskLauncher.exe"
	Delete "${APPDIR}\Uninstall.exe"
	RMDir "${APPDIR}"
SectionEnd
