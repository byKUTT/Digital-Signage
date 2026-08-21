<#
.SYNOPSIS
    Installs the Digital Signage kiosk player to auto-start on Windows sign-in,
    optionally with a fully unattended Windows auto sign-in too.

.DESCRIPTION
    Copies kiosk-player.ps1 into %ProgramData%\DigitalSignageKiosk and adds a
    Run-key entry so it launches automatically, hidden, every time the current
    Windows user signs in.

    Pass -EnableAutoLogon to also make Windows itself sign in to this account
    automatically on boot (via the built-in AutoAdminLogon mechanism) — with
    that, the PC boots straight to the kiosk with no keyboard/mouse needed at
    all, the Windows equivalent of the Raspberry Pi installer's console
    autologin. This requires an elevated (Administrator) PowerShell and writes
    this account's password to the registry in a form Windows can read back
    in cleartext — that's an inherent limitation of AutoAdminLogon, not
    something this script can avoid, so only use it on a dedicated,
    low-privilege kiosk account with no sensitive access, physically secured
    hardware.

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
    Configure Windows to sign in to this account automatically on every boot,
    with no password prompt — the PC goes from power-on straight to the
    kiosk. Requires an elevated PowerShell. Prompts securely for the account
    password unless -AutoLogonPassword is given.

.PARAMETER AutoLogonUsername
    The account to auto sign in as. Defaults to the account running this
    script (recommended: run this script while logged into the dedicated
    kiosk account you want auto-signed-in).

.PARAMETER AutoLogonPassword
    SecureString password for -AutoLogonUsername. If -EnableAutoLogon is
    passed without this, you'll be prompted (input hidden).

.PARAMETER Browser
    "edge" (default) or "chrome".

.PARAMETER CloseModifiers
    Hotkey modifiers to close the kiosk. Default: Ctrl,Alt,Shift

.PARAMETER CloseKey
    Hotkey key to close the kiosk. Default: Q

.EXAMPLE
    .\install-kiosk.ps1 -Site "https://example.com"

.EXAMPLE
    # Fully unattended kiosk PC: signs in and starts playing with no one touching the keyboard.
    .\install-kiosk.ps1 -Site "https://example.com" -EnableAutoLogon

.EXAMPLE
    .\install-kiosk.ps1 -Url "https://example.com/signage/play/abc/" -CloseModifiers Ctrl,Alt -CloseKey X
#>

param(
	[string]$Site,

	[string]$Url,

	[switch]$Regenerate,

	[switch]$EnableAutoLogon,

	[string]$AutoLogonUsername = $env:USERNAME,

	[System.Security.SecureString]$AutoLogonPassword,

	[ValidateSet('edge', 'chrome')]
	[string]$Browser = 'edge',

	[string[]]$CloseModifiers = @('Ctrl', 'Alt', 'Shift'),

	[string]$CloseKey = 'Q'
)

$ErrorActionPreference = 'Stop'

if ( -not $Site -and -not $Url ) {
	throw "Pass either -Site 'https://yourdomain.com' (recommended — the device remembers its own identity) or -Url 'https://yourdomain.com/signage/play/TOKEN/' (a screen already paired in wp-admin)."
}
if ( $Site -and $Url ) {
	throw "Pass only one of -Site or -Url, not both."
}

if ( $EnableAutoLogon ) {
	$isElevated = ( [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent() ).IsInRole( [Security.Principal.WindowsBuiltInRole]::Administrator )
	if ( -not $isElevated ) {
		throw "-EnableAutoLogon needs an elevated PowerShell. Right-click PowerShell > 'Run as Administrator' (while logged into the kiosk account you want auto-signed-in) and re-run this command."
	}
}

$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
New-Item -ItemType Directory -Path $installDir -Force | Out-Null

Copy-Item -Path (Join-Path $PSScriptRoot 'kiosk-player.ps1') -Destination $installDir -Force
$scriptPath = Join-Path $installDir 'kiosk-player.ps1'

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

	$Url = "$Site/signage/play/$token/"
}

$modifiersArg = ($CloseModifiers -join ',')

# Build the command line the Run key will execute on every sign-in. Because this
# string is written once and reused verbatim on every reboot, the URL (and thus
# this device's identity) stays fixed even though install-kiosk.ps1 itself is
# never run again.
$runCommand = 'powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -Url "{1}" -Browser {2} -CloseModifiers {3} -CloseKey {4}' -f `
	$scriptPath, $Url, $Browser, $modifiersArg, $CloseKey

New-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' `
	-Name 'DigitalSignageKiosk' -Value $runCommand -PropertyType String -Force | Out-Null

# --- Optional: make Windows itself sign in automatically on boot. ---
if ( $EnableAutoLogon ) {
	if ( -not $AutoLogonPassword ) {
		$AutoLogonPassword = Read-Host -Prompt "Password for '$AutoLogonUsername' (used only to configure auto sign-in)" -AsSecureString
	}
	$plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto( [Runtime.InteropServices.Marshal]::SecureStringToBSTR( $AutoLogonPassword ) )

	$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
	New-ItemProperty -Path $winlogonPath -Name 'AutoAdminLogon' -Value '1' -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultUserName' -Value $AutoLogonUsername -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultDomainName' -Value $env:COMPUTERNAME -PropertyType String -Force | Out-Null
	New-ItemProperty -Path $winlogonPath -Name 'DefaultPassword' -Value $plainPassword -PropertyType String -Force | Out-Null
	# Not a persistent count limit — 0 here just means "unlimited", i.e. every boot.
	Remove-ItemProperty -Path $winlogonPath -Name 'AutoLogonCount' -ErrorAction SilentlyContinue

	$plainPassword = $null # Best-effort scrub of the in-memory copy.
}

Write-Host ""
Write-Host "✅ Installed. The kiosk will start automatically next time this Windows account signs in." -ForegroundColor Green
Write-Host "   Player URL:     $Url"
Write-Host "   Close hotkey:   $modifiersArg+$CloseKey"
Write-Host "   Installed to:   $scriptPath"
if ( $EnableAutoLogon ) {
	Write-Host "   Auto sign-in:   ENABLED for '$AutoLogonUsername' — this PC now boots straight to the kiosk." -ForegroundColor Green
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
	Write-Host "For a dedicated kiosk PC that boots straight to the signage with nobody" -ForegroundColor Yellow
	Write-Host "needing to sign in, re-run this from an elevated PowerShell with -EnableAutoLogon:" -ForegroundColor Yellow
	Write-Host "  .\install-kiosk.ps1 -Site `"$Site`" -EnableAutoLogon"
	Write-Host ""
}
Write-Host "To re-pair this PC as a different screen: .\install-kiosk.ps1 -Site `"$Site`" -Regenerate"
Write-Host "To remove: .\uninstall-kiosk.ps1"
