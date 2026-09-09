<#
.SYNOPSIS
    Regenerates install-standalone.ps1 from the real .ps1 source files.

.DESCRIPTION
    install-standalone.ps1 embeds install-kiosk.ps1, kiosk-player.ps1,
    ds-controller-agent.ps1 and uninstall-kiosk.ps1 as base64 blobs so it can
    install fully offline as a single file. Run this script after editing
    any of those four files to rebuild install-standalone.ps1 from the
    current source — do not hand-edit the base64 blobs.
#>

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$files = 'install-kiosk.ps1', 'kiosk-player.ps1', 'ds-controller-agent.ps1', 'uninstall-kiosk.ps1'

$payloadLines = foreach ( $f in $files ) {
	$bytes = [IO.File]::ReadAllBytes( (Join-Path $root $f) )
	$b64 = [Convert]::ToBase64String( $bytes )
	"`t'$f' = '$b64'"
}

$header = @'
<#
.SYNOPSIS
    Fully self-contained, offline installer for the Digital Signage Windows
    kiosk player.

.DESCRIPTION
    A single-file version of the windows-kiosk installer: install-kiosk.ps1,
    kiosk-player.ps1, ds-controller-agent.ps1 and uninstall-kiosk.ps1 are all
    embedded below (base64) and written out to a local folder at run time.
    Nothing is fetched from GitHub or anywhere else at install time — copy
    this one file to the kiosk PC (USB stick, network share, email
    attachment, etc.) and run it locally. The only network access this
    installer (and the kiosk it sets up) needs is to the signage site
    itself.

    Any parameters you pass are forwarded as-is to install-kiosk.ps1 (e.g.
    -Site, -Url, -MultiDisplay, -Browser, -EnableAutoLogon, -Regenerate,
    ...). With no parameters at all it installs against the default site
    baked into install-kiosk.ps1 (https://test.kutt.ee).

    To regenerate this file after editing install-kiosk.ps1 / kiosk-player.ps1
    / ds-controller-agent.ps1 / uninstall-kiosk.ps1, see build-standalone.ps1
    in this same folder.

.EXAMPLE
    .\install-standalone.ps1

.EXAMPLE
    .\install-standalone.ps1 -Site "https://yourdomain.com"

.EXAMPLE
    .\install-standalone.ps1 -Site "https://yourdomain.com" -MultiDisplay
#>

param(
	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]]$InstallArgs
)

$ErrorActionPreference = 'Stop'

# --- Embedded payloads (base64 of the real .ps1 files) -----------------
$payloads = [ordered]@{
'@

$footer = @'
}

$installDir = Join-Path $env:TEMP 'ds-kiosk-install'
New-Item -ItemType Directory -Force -Path $installDir | Out-Null

foreach ( $name in $payloads.Keys ) {
	$bytes = [Convert]::FromBase64String( $payloads[$name] )
	[IO.File]::WriteAllBytes( (Join-Path $installDir $name), $bytes )
}

& (Join-Path $installDir 'install-kiosk.ps1') @InstallArgs
'@

$outPath = Join-Path $root 'install-standalone.ps1'
# -Encoding UTF8 (with BOM, on Windows PowerShell) matters here: this file and
# the payloads it embeds contain non-ASCII characters (em dashes, etc.), and
# without a BOM Windows PowerShell falls back to the system codepage to read
# it back, mangling those bytes into a parse error.
( $header, ($payloadLines -join "`n"), $footer ) -join "`n" | Set-Content -Path $outPath -NoNewline -Encoding UTF8
Write-Host "Regenerated $outPath" -ForegroundColor Green
