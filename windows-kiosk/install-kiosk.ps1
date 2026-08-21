<#
.SYNOPSIS
    Installs the Digital Signage kiosk player to auto-start on Windows sign-in.

.DESCRIPTION
    Copies kiosk-player.ps1 into %ProgramData%\DigitalSignageKiosk and adds a
    Run-key entry so it launches automatically, hidden, every time the current
    Windows user signs in. For a true "boots straight to the signage" kiosk PC,
    also enable Windows auto-logon for a dedicated kiosk account (instructions
    printed at the end, and in README.md) — Windows itself has no equivalent
    of Linux console autologin without a user account signing in first.

.PARAMETER Url
    The player URL to display, e.g. https://yourdomain.com/signage/play/TOKEN/

.PARAMETER Browser
    "edge" (default) or "chrome".

.PARAMETER CloseModifiers
    Hotkey modifiers to close the kiosk. Default: Ctrl,Alt,Shift

.PARAMETER CloseKey
    Hotkey key to close the kiosk. Default: Q

.EXAMPLE
    .\install-kiosk.ps1 -Url "https://example.com/signage/play/abc/"

.EXAMPLE
    .\install-kiosk.ps1 -Url "https://example.com/signage/play/abc/" -CloseModifiers Ctrl,Alt -CloseKey X
#>

param(
	[Parameter(Mandatory = $true)]
	[string]$Url,

	[ValidateSet('edge', 'chrome')]
	[string]$Browser = 'edge',

	[string[]]$CloseModifiers = @('Ctrl', 'Alt', 'Shift'),

	[string]$CloseKey = 'Q'
)

$ErrorActionPreference = 'Stop'

$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
New-Item -ItemType Directory -Path $installDir -Force | Out-Null

Copy-Item -Path (Join-Path $PSScriptRoot 'kiosk-player.ps1') -Destination $installDir -Force

$scriptPath = Join-Path $installDir 'kiosk-player.ps1'
$modifiersArg = ($CloseModifiers -join ',')

# Build the command line the Run key will execute on every sign-in.
$runCommand = 'powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -Url "{1}" -Browser {2} -CloseModifiers {3} -CloseKey {4}' -f `
	$scriptPath, $Url, $Browser, $modifiersArg, $CloseKey

New-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' `
	-Name 'DigitalSignageKiosk' -Value $runCommand -PropertyType String -Force | Out-Null

Write-Host ""
Write-Host "✅ Installed. The kiosk will start automatically next time this Windows account signs in." -ForegroundColor Green
Write-Host "   Player URL:     $Url"
Write-Host "   Close hotkey:   $modifiersArg+$CloseKey"
Write-Host "   Installed to:   $scriptPath"
Write-Host ""
Write-Host "To start it right now without rebooting:"
Write-Host "  Start-Process powershell -ArgumentList '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptPath`" -Url `"$Url`" -Browser $Browser -CloseModifiers $modifiersArg -CloseKey $CloseKey'"
Write-Host ""
Write-Host "For a dedicated kiosk PC that boots straight to the signage with nobody" -ForegroundColor Yellow
Write-Host "needing to sign in, also enable Windows auto-logon for this account:" -ForegroundColor Yellow
Write-Host "  1. Run: netplwiz"
Write-Host "  2. Uncheck 'Users must enter a user name and password to use this computer'"
Write-Host "  3. Enter this account's credentials when prompted"
Write-Host ""
Write-Host "To remove: .\uninstall-kiosk.ps1"
