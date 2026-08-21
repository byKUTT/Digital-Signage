<#
.SYNOPSIS
    Removes the Digital Signage Windows kiosk auto-start and installed files.
#>

$ErrorActionPreference = 'SilentlyContinue'

Write-Host "==> Stopping any running kiosk session…"
Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
	Where-Object { $_.CommandLine -like '*kiosk-player.ps1*' } |
	ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "==> Removing auto-start registry entry…"
Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'DigitalSignageKiosk'

Write-Host "==> Removing installed files…"
$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
Remove-Item -Path $installDir -Recurse -Force

Write-Host ""
Write-Host "✅ Kiosk removed. If you enabled Windows auto-logon (netplwiz) for a" -ForegroundColor Green
Write-Host "   dedicated kiosk account, re-run netplwiz to turn that back off too."
