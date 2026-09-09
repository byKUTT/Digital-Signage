<#
.SYNOPSIS
    Removes the Digital Signage Windows kiosk auto-start and installed files.

.PARAMETER DisableAutoLogon
    Also turn off Windows auto sign-in (AutoAdminLogon) and revert the
    unattended-kiosk power/screen-saver/error-reporting/update settings, if
    install-kiosk.ps1 -EnableAutoLogon set them up. Requires an elevated
    PowerShell.
#>

param(
	[switch]$DisableAutoLogon
)

$ErrorActionPreference = 'SilentlyContinue'

Write-Host "==> Stopping any running kiosk session…"
Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
	Where-Object { $_.CommandLine -like '*kiosk-player.ps1*' } |
	ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "==> Removing auto-start registry entry…"
Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'DigitalSignageKiosk'
Unregister-ScheduledTask -TaskName 'DigitalSignageController' -Confirm:$false -ErrorAction SilentlyContinue

Write-Host "==> Removing installed files…"
$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
Remove-Item -Path $installDir -Recurse -Force

if ( $DisableAutoLogon ) {
	$isElevated = ( [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent() ).IsInRole( [Security.Principal.WindowsBuiltInRole]::Administrator )
	if ( -not $isElevated ) {
		Write-Host "⚠ -DisableAutoLogon needs an elevated PowerShell — re-run as Administrator to clear it." -ForegroundColor Yellow
	} else {
		Write-Host "==> Disabling Windows auto sign-in…"
		$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
		Set-ItemProperty -Path $winlogonPath -Name 'AutoAdminLogon' -Value '0'
		Remove-ItemProperty -Path $winlogonPath -Name 'DefaultPassword'

		Write-Host "==> Reverting unattended-kiosk power/lock/update settings…"
		powercfg /change monitor-timeout-ac 10
		powercfg /change monitor-timeout-dc 5
		powercfg /change standby-timeout-ac 30
		powercfg /change standby-timeout-dc 15
		Set-ItemProperty -Path 'HKCU:\Control Panel\Desktop' -Name 'ScreenSaveActive' -Value '1'
		Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\Windows Error Reporting' -Name 'Disabled'
		Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU' -Name 'NoAutoRebootWithLoggedOnUsers'
	}
}

Write-Host ""
Write-Host "✅ Kiosk removed." -ForegroundColor Green
if ( -not $DisableAutoLogon ) {
	Write-Host "   If -EnableAutoLogon was used to set up Windows auto sign-in, re-run this"
	Write-Host "   script from an elevated PowerShell with -DisableAutoLogon to turn that off too."
}
