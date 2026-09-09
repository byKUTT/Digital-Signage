<#
.SYNOPSIS
    Installs the Digital Signage kiosk player to auto-start on Windows sign-in,
    optionally with a fully unattended Windows auto sign-in too.

.DESCRIPTION
    Copies kiosk-player.ps1 into %ProgramData%\DigitalSignageKiosk. By
    default (-ReplaceShell) it's installed as this account's Windows shell,
    replacing explorer.exe entirely: on sign-in there is no desktop, no
    taskbar, no Start menu, nothing but the kiosk browser — just a Run-key
    entry that launches it alongside the normal desktop instead, if
    -ReplaceShell:$false is passed.

    Windows auto sign-in (via the built-in AutoAdminLogon mechanism) is
    turned on by default — the PC boots straight to the kiosk with no
    keyboard/mouse needed at all, the Windows equivalent of the Raspberry Pi
    installer's console autologin. This needs an elevated (Administrator)
    PowerShell; if the shell running this script isn't already elevated, it
    automatically relaunches itself with a UAC prompt — that one click is the
    only interaction this installer needs. No password prompt: if the account
    already has no password (typical for a dedicated kiosk account), nothing
    further is asked and an empty password is written, which is exactly what
    AutoAdminLogon needs for a passwordless account; pass -AutoLogonPassword
    for an account that does have one. Pass -EnableAutoLogon:$false to skip
    auto sign-in entirely and stay unelevated. Enabling it writes this
    account's password (or a blank one) to the registry in a form Windows can
    read back in cleartext — that's an inherent limitation of AutoAdminLogon,
    not something this script can avoid, so only use it on a dedicated,
    low-privilege kiosk account with no sensitive access, physically secured
    hardware.

    Enabling auto sign-in also hardens the PC for running with no keyboard or
    mouse ever attached: sleep/hibernate/monitor-off are disabled, the screen
    saver is turned off, Windows Error Reporting's crash dialog is
    suppressed, a pending Windows Update won't pop an interactive "restart
    now?" prompt, and Windows Spotlight/suggested-content overlays are
    disabled — none of these need someone to click through them for the
    kiosk to keep running.

    This device generates and remembers its own pairing identity: pass -Site
    (your WordPress site's URL) and a permanent device token is created once,
    saved to %ProgramData%\DigitalSignageKiosk\device-token.txt, and reused on
    every future run/reboot — the pairing code shown on screen never resets.
    Pass -Url instead if you already paired a screen in wp-admin and just want
    to point this PC at that exact player URL (no local token is generated).

.PARAMETER Site
    Your WordPress site's base URL, e.g. https://yourdomain.com — the device
    builds and remembers its own player URL from this. Mutually exclusive
    with -Url.

.PARAMETER Url
    A specific, already-paired player URL, e.g.
    https://yourdomain.com/signage/play/TOKEN/. Mutually exclusive with -Site.

.PARAMETER Regenerate
    Force a brand-new device token even if one already exists (e.g. this PC
    is being re-purposed as a different physical screen). Only applies with
    -Site.

.PARAMETER EnableAutoLogon
    Configure Windows to sign in to this account automatically on every boot
    — the PC goes from power-on straight to the kiosk. Enabled by default;
    pass -EnableAutoLogon:$false to turn it off. Requires an elevated
    PowerShell — the script self-elevates (UAC prompt) if it isn't already
    running elevated. Fully unattended otherwise: no password prompt: an
    account with no password gets an empty AutoAdminLogon password (which is
    what it needs), or pass -AutoLogonPassword for an account that has one.

.PARAMETER ReplaceShell
    Make the kiosk this account's Windows shell (HKCU ...\Winlogon\Shell)
    instead of explorer.exe, so sign-in goes straight to the kiosk with no
    desktop, taskbar, or Start menu ever reachable. Enabled by default; pass
    -ReplaceShell:$false to keep the normal desktop and launch the kiosk via
    the Run key on top of it instead (useful while testing, or for
    maintenance access). Doesn't need elevation on its own (HKCU), but is
    only really useful paired with -EnableAutoLogon. Emergency recovery if
    something's wrong with the kiosk: Ctrl+Shift+Esc still opens Task
    Manager (a raw Windows hotkey, independent of the shell) — File > Run
    new task > explorer.exe (or powershell.exe) gets you back to a normal
    desktop without uninstalling anything.

.PARAMETER AutoLogonUsername
    The account to auto sign in as. Defaults to the account running this
    script (recommended: run this script while logged into the dedicated
    kiosk account you want auto-signed-in).

.PARAMETER AutoLogonPassword
    SecureString password for -AutoLogonUsername, for an account that
    actually has one. Omit it for a passwordless account (the default
    assumption) — no prompt either way, this installer never asks.

.PARAMETER Browser
    "edge" (default) or "chrome".

.PARAMETER CloseModifiers
    Hotkey modifiers to close the kiosk. Default: Ctrl,Alt,Shift

.PARAMETER CloseKey
    Hotkey key to close the kiosk. Default: Q

.EXAMPLE
    # Fully unattended kiosk PC by default: signs in and starts playing with no one touching the keyboard.
    .\install-kiosk.ps1 -Site "https://example.com"

.EXAMPLE
    # Skip auto sign-in and stay unelevated — just the kiosk app on normal sign-in.
    .\install-kiosk.ps1 -Site "https://example.com" -EnableAutoLogon:$false

.EXAMPLE
    .\install-kiosk.ps1 -Url "https://example.com/signage/play/abc/" -CloseModifiers Ctrl,Alt -CloseKey X
#>

param(
	[string]$Site,

	[string]$Url,

	[switch]$Regenerate,

	[switch]$EnableAutoLogon = $true,

	[switch]$ReplaceShell = $true,

	[switch]$MultiDisplay,

	[string]$AutoLogonUsername = $env:USERNAME,

	[System.Security.SecureString]$AutoLogonPassword,

	[ValidateSet('edge', 'chrome')]
	[string]$Browser = 'edge',

	[string[]]$CloseModifiers = @('Ctrl', 'Alt', 'Shift'),

	[string]$CloseKey = 'Q'
)

$ErrorActionPreference = 'Stop'

if ( -not $Site -and -not $Url ) {
	# No -Site/-Url given (e.g. run via the one-line bootstrap install) — default
	# to the site this deployment is paired to.
	$Site = 'https://test.kutt.ee'
}
if ( $Site -and $Url ) {
	throw "Pass only one of -Site or -Url, not both."
}

# MultiDisplay and (the now-default-on) EnableAutoLogon both need an elevated
# PowerShell. Rather than making the caller remember to run one, self-elevate:
# relaunch this exact script with a UAC prompt, forward every parameter that
# was actually passed in, and let the elevated copy do the real work.
$isElevated = ( [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent() ).IsInRole( [Security.Principal.WindowsBuiltInRole]::Administrator )

if ( ( $EnableAutoLogon -or $MultiDisplay ) -and -not $isElevated ) {
	$why = if ( $MultiDisplay ) { '-MultiDisplay' } else { 'auto sign-in (on by default — pass -EnableAutoLogon:$false to skip it and stay unelevated)' }
	Write-Host "==> $why needs Administrator — a UAC prompt will appear; approve it to continue." -ForegroundColor Yellow

	$forward = New-Object System.Collections.Generic.List[string]
	foreach ( $key in $PSBoundParameters.Keys ) {
		$val = $PSBoundParameters[$key]
		if ( $val -is [System.Management.Automation.SwitchParameter] ) {
			$forward.Add( "-{0}:`${1}" -f $key, $val.IsPresent )
		}
		elseif ( $val -is [System.Security.SecureString] ) {
			# Can't hand a password to another process securely — the elevated
			# copy will just prompt for it itself.
			continue
		}
		elseif ( $val -is [array] ) {
			$forward.Add( "-$key" )
			$forward.Add( ($val -join ',') )
		}
		else {
			$forward.Add( "-$key" )
			$forward.Add( "`"$val`"" )
		}
	}

	$argLine = '-NoProfile -ExecutionPolicy Bypass -File "{0}" {1}' -f $MyInvocation.MyCommand.Path, ($forward -join ' ')
	Start-Process -FilePath 'powershell.exe' -ArgumentList $argLine -Verb RunAs -Wait
	exit $LASTEXITCODE
}

$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
New-Item -ItemType Directory -Path $installDir -Force | Out-Null

Copy-Item -Path (Join-Path $PSScriptRoot 'kiosk-player.ps1') -Destination $installDir -Force
Copy-Item -Path (Join-Path $PSScriptRoot 'ds-controller-agent.ps1') -Destination $installDir -Force
$scriptPath = Join-Path $installDir 'kiosk-player.ps1'

if ( $MultiDisplay ) {
	if ( -not $Site ) {
		throw '-MultiDisplay requires -Site.'
	}
	if ( $Site -notmatch '^https://' ) {
		throw '-MultiDisplay requires an HTTPS WordPress site.'
	}
	$isElevated = ( [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent() ).IsInRole( [Security.Principal.WindowsBuiltInRole]::Administrator )
	if ( -not $isElevated ) {
		throw '-MultiDisplay must be installed from an elevated PowerShell.'
	}
	$controllerScript = Join-Path $installDir 'ds-controller-agent.ps1'
	$controllerArgs = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -Site "{1}" -Browser {2}' -f $controllerScript, $Site.TrimEnd('/'), $Browser
	$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $controllerArgs
	$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
	$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Highest
	$settings = New-ScheduledTaskSettingsSet -RestartCount 99 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)
	Register-ScheduledTask -TaskName 'DigitalSignageController' -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
	Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'DigitalSignageKiosk' -ErrorAction SilentlyContinue
	Write-Host 'Installed Digital Signage Windows multi-display controller 3.0.0.' -ForegroundColor Green
	Write-Host 'Sign out and back in. Every connected monitor will receive its own Screen after pairing.'
	exit 0
}

if ( $Site ) {
	$Site = $Site.TrimEnd('/')
	$tokenFile = Join-Path $installDir 'device-token.txt'

	$token = $null
	if ( ( Test-Path $tokenFile ) -and -not $Regenerate ) {
		$token = ( Get-Content -Path $tokenFile -Raw ).Trim()
	}
	if ( -not $token ) {
		# 40 random alphanumeric characters — this device's permanent identity.
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
		$token = -join ( 1..40 | ForEach-Object { $chars[ (Get-Random -Maximum $chars.Length) ] } )
		Set-Content -Path $tokenFile -Value $token -NoNewline
		Write-Host "==> Generated a new device token (this screen's permanent identity)." -ForegroundColor Cyan
	} else {
		Write-Host "==> Reusing existing device token from $tokenFile." -ForegroundColor Cyan
	}

	$Url = "$Site/signage/play/$token/?kiosk=1"
}

# ?kiosk=1 tells the player/pairing screens this browser is already OS-level
# fullscreen (started with --kiosk) with no chrome to hide and no mouse/touch
# to click a "click to start" prompt with, so they skip that entirely. Add it
# even when -Url was passed directly, unless it's already there.
if ( $Url -notmatch '[?&]kiosk=1(&|$)' ) {
	$Url += if ( $Url -match '\?' ) { '&kiosk=1' } else { '?kiosk=1' }
}

$modifiersArg = ($CloseModifiers -join ',')

# Build the command line the Run key will execute on every sign-in. Because this
# string is written once and reused verbatim on every reboot, the URL (and thus
# this device's identity) stays fixed even though install-kiosk.ps1 itself is
# never run again.
$runCommand = 'powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -Url "{1}" -Browser {2} -CloseModifiers {3} -CloseKey {4}' -f `
	$scriptPath, $Url, $Browser, $modifiersArg, $CloseKey

if ( $ReplaceShell ) {
	# Make the kiosk this account's Windows shell instead of explorer.exe:
	# on sign-in there is no desktop, taskbar, or Start menu — just the kiosk
	# browser. Per-user, no elevation needed. It doesn't also need a Run-key
	# entry since it's now what launches on sign-in in the first place.
	Write-Host "==> Setting the kiosk as this account's shell (no desktop, taskbar, or Start menu will ever appear)..." -ForegroundColor Cyan
	$shellWinlogonPath = 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon'
	New-Item -Path $shellWinlogonPath -Force | Out-Null
	New-ItemProperty -Path $shellWinlogonPath -Name 'Shell' -Value $runCommand -PropertyType String -Force | Out-Null
	Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'DigitalSignageKiosk' -ErrorAction SilentlyContinue
} else {
	New-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' `
		-Name 'DigitalSignageKiosk' -Value $runCommand -PropertyType String -Force | Out-Null
	Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon' -Name 'Shell' -ErrorAction SilentlyContinue
}

# --- Optional: make Windows itself sign in automatically on boot. ---
if ( $EnableAutoLogon ) {
	# No prompt: this installer runs fully unattended. Pass -AutoLogonPassword
	# for an account that actually has one; with no password on the account
	# (the common case for a dedicated kiosk account), leave it out and an
	# empty password is written, which is what AutoAdminLogon needs anyway.
	if ( $AutoLogonPassword ) {
		$plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto( [Runtime.InteropServices.Marshal]::SecureStringToBSTR( $AutoLogonPassword ) )
	} else {
		$plainPassword = ''
	}

	$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
	New-ItemProperty -Path $winlogonPath -Name 'AutoAdminLogon' -Value '1' -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultUserName' -Value $AutoLogonUsername -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultDomainName' -Value $env:COMPUTERNAME -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultPassword' -Value $plainPassword -PropertyType String -Force | Out-Null
	# Not a persistent count limit — 0 here just means "unlimited", i.e. every boot.
	Remove-ItemProperty -Path $winlogonPath -Name 'AutoLogonCount' -ErrorAction SilentlyContinue

	$plainPassword = $null # Best-effort scrub of the in-memory copy.

	# --- Harden this PC as a true unattended kiosk: no keyboard/mouse is -----
	# --- ever going to be there to click through anything, so nothing on ----
	# --- this machine should be able to end up waiting for input. -----------
	Write-Host "==> Configuring power, lock-screen and update settings for unattended kiosk operation..." -ForegroundColor Cyan

	# Never sleep, never blank the display, no hibernate file — there's no
	# input device to wake it back up with.
	$powercfgRuns = @(
		, @('/change', 'monitor-timeout-ac', '0')
		, @('/change', 'monitor-timeout-dc', '0')
		, @('/change', 'standby-timeout-ac', '0')
		, @('/change', 'standby-timeout-dc', '0')
		, @('/change', 'hibernate-timeout-ac', '0')
		, @('/change', 'hibernate-timeout-dc', '0')
		, @('/hibernate', 'off')
	)
	foreach ( $pcArgs in $powercfgRuns ) {
		Start-Process -FilePath 'powercfg.exe' -ArgumentList $pcArgs -Wait -WindowStyle Hidden
	}

	# Disable the screen saver outright — with AutoAdminLogon there's normally
	# no lock screen to begin with, but a screen saver set to "on resume,
	# display logon screen" would otherwise strand the kiosk behind one nobody
	# can dismiss.
	Set-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'ScreenSaveActive' -Value '0' -Force
	Remove-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'SCRNSAVE.EXE' -ErrorAction SilentlyContinue

	# Suppress Windows Error Reporting's "<program> has stopped working"
	# dialog — there's nobody to click "Close program" on it, and left open
	# it blocks whatever's behind it.
	New-Item -Path 'HKLM:\SOFTWARE\Microsoft\Windows\Windows Error Reporting' -Force | Out-Null
	Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\Windows Error Reporting' -Name 'Disabled' -Value 1 -Type DWord -Force

	# Don't let a pending Windows Update pop a "we're going to restart, save
	# your work" dialog while someone (nobody) is expected to respond to it.
	# Updates still install and the PC still reboots on its own schedule —
	# straight back into the kiosk, thanks to AutoAdminLogon — this only
	# removes the interactive nag beforehand.
	$auPath = 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU'
	New-Item -Path $auPath -Force | Out-Null
	Set-ItemProperty -Path $auPath -Name 'NoAutoRebootWithLoggedOnUsers' -Value 1 -Type DWord -Force

	# Turn off Windows Spotlight / "suggested content" / tips overlays, which
	# occasionally take over the full screen after sign-in or a feature
	# update and wait for a click to dismiss.
	$cdmPath = 'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\ContentDeliveryManager'
	New-Item -Path $cdmPath -Force | Out-Null
	foreach ( $name in 'SubscribedContent-338387Enabled', 'SubscribedContent-338388Enabled',
		'SubscribedContent-338389Enabled', 'SubscribedContent-353694Enabled',
		'SubscribedContent-353696Enabled', 'RotatingLockScreenEnabled', 'RotatingLockScreenOverlayEnabled' ) {
		Set-ItemProperty -Path $cdmPath -Name $name -Value 0 -Type DWord -Force -ErrorAction SilentlyContinue
	}
}

Write-Host ""
Write-Host "✅ Installed. The kiosk will start automatically next time this Windows account signs in." -ForegroundColor Green
Write-Host "   Player URL:     $Url"
Write-Host "   Close hotkey:   $modifiersArg+$CloseKey"
Write-Host "   Installed to:   $scriptPath"
if ( $EnableAutoLogon ) {
	Write-Host "   Auto sign-in:   ENABLED for '$AutoLogonUsername' — this PC now boots straight to the kiosk." -ForegroundColor Green
	Write-Host "   Kiosk hardening: sleep/hibernate/screen saver disabled, crash and update" -ForegroundColor Green
	Write-Host "                    dialogs suppressed — nothing here waits on input." -ForegroundColor Green
}
if ( $ReplaceShell ) {
	Write-Host "   Shell:          REPLACED — no desktop, taskbar, or Start menu, just the kiosk." -ForegroundColor Green
	Write-Host "                    Recovery: Ctrl+Shift+Esc > Task Manager > File > Run new task" -ForegroundColor Green
	Write-Host "                    > explorer.exe gets a normal desktop back if you ever need one." -ForegroundColor Green
}
Write-Host ""
if ( $Site ) {
	Write-Host "On first launch this PC will show a pairing code + QR code full-screen —"
	Write-Host "scan it or open wp-admin > Digital Signage > Pair a Screen to link it."
	Write-Host "The same code/token stays valid across every reboot."
	Write-Host ""
}
Write-Host "To start it right now without rebooting:"
Write-Host "  Start-Process powershell -ArgumentList '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -Url `"$Url`" -Browser $Browser -CloseModifiers $modifiersArg -CloseKey $CloseKey'"
Write-Host ""
if ( -not $EnableAutoLogon ) {
	Write-Host "Auto sign-in is OFF for this install. To make this a dedicated kiosk PC" -ForegroundColor Yellow
	Write-Host "that boots straight to the signage with nobody needing to sign in:" -ForegroundColor Yellow
	Write-Host "  .\install-kiosk.ps1 -Site `"$Site`""
	Write-Host ""
}
Write-Host "To re-pair this PC as a different screen: .\install-kiosk.ps1 -Site `"$Site`" -Regenerate"
Write-Host "To remove: .\uninstall-kiosk.ps1"
