<#
.SYNOPSIS
    One-line remote installer for the Digital Signage Windows kiosk player.

.DESCRIPTION
    Downloads the windows-kiosk scripts straight from GitHub into a temp
    folder and runs install-kiosk.ps1 from there — no manual clone/copy step
    needed. Meant to be fetched and piped straight into PowerShell with a
    single command (see the README's "one-line install").

    Any parameters you pass to this script are forwarded as-is to
    install-kiosk.ps1 (e.g. -Site, -Url, -MultiDisplay, -Browser, ...). With
    no parameters at all it installs against the default site baked into
    install-kiosk.ps1.
#>

param(
	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]]$InstallArgs
)

$ErrorActionPreference = 'Stop'

$branch = 'claude/wonderful-feynman-bm3zws'
$base = "https://raw.githubusercontent.com/byKUTT/Digital-Signage/$branch/windows-kiosk"
$installDir = Join-Path $env:TEMP 'ds-kiosk-install'

New-Item -ItemType Directory -Force -Path $installDir | Out-Null

foreach ( $f in 'install-kiosk.ps1', 'kiosk-player.ps1', 'ds-controller-agent.ps1', 'uninstall-kiosk.ps1', 'DigitalSignageKioskLauncher.exe' ) {
	Invoke-WebRequest -UseBasicParsing -Uri "$base/$f" -OutFile (Join-Path $installDir $f)
}

& (Join-Path $installDir 'install-kiosk.ps1') @InstallArgs
