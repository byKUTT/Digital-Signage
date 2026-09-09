<#
.SYNOPSIS
    Removes the Digital Signage Windows kiosk auto-start and installed files.

.PARAMETER DisableAutoLogon
    Also turn off Windows auto sign-in (AutoAdminLogon) and revert the
    unattended-kiosk power/screen-saver/error-reporting/update settings, if
    install-kiosk.ps1 -EnableAutoLogon set them up. Requires an elevated
    PowerShell.

.PARAMETER RemoveKioskUser
    Also delete the dedicated local account install-kiosk.ps1 -CreateKioskUser
    created (-KioskUsername, default "Kiosk") and its profile. Requires an
    elevated PowerShell.

.PARAMETER KioskUsername
    Name of the dedicated local account to clean up after / remove. Default:
    "Kiosk" — must match whatever -KioskUsername install-kiosk.ps1 was given.
#>

param(
	[switch]$DisableAutoLogon,
	[switch]$RemoveKioskUser,
	[string]$KioskUsername = 'Kiosk'
)

$ErrorActionPreference = 'SilentlyContinue'

Write-Host "==> Stopping any running kiosk session…"
Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
	Where-Object { $_.CommandLine -like '*kiosk-player.ps1*' } |
	ForEach-Object { Stop-Process -Id $_.ProcessId -Force }

Write-Host "==> Removing auto-start registry entry…"
Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'DigitalSignageKiosk'
Unregister-ScheduledTask -TaskName 'DigitalSignageController' -Confirm:$false -ErrorAction SilentlyContinue

# install-kiosk.ps1 -CreateKioskUser (the default) writes the shell/Run-key/
# screen-saver settings into a dedicated account's profile — or, if that
# account had never signed in yet, Windows's Default Profile template —
# rather than the current user's own registry. Clean up wherever they
# actually ended up: the live registry if $KioskUsername is who's running
# this script, otherwise that account's (or the Default template's)
# NTUSER.DAT, loaded offline.
$offlineHiveName = 'DsKioskUninstallHive'
$kioskNtUserDat = $null
if ( $KioskUsername -and $KioskUsername -ne $env:USERNAME ) {
	$kioskNtUserDat = Join-Path $env:SystemDrive "Users\$KioskUsername\NTUSER.DAT"
	if ( -not (Test-Path $kioskNtUserDat) ) {
		$kioskNtUserDat = Join-Path $env:SystemDrive 'Users\Default\NTUSER.DAT'
	}
}
$usingOfflineHive = [bool]$kioskNtUserDat -and (Test-Path $kioskNtUserDat)
if ( $usingOfflineHive ) {
	& reg.exe load "HKU\$offlineHiveName" $kioskNtUserDat | Out-Null
}
$userRoot = if ( $usingOfflineHive ) { "Registry::HKEY_USERS\$offlineHiveName" } else { 'HKCU:' }

Write-Host "==> Restoring the normal desktop shell (undoing -ReplaceShell, if it was used)…"
Remove-ItemProperty -Path "$userRoot\Software\Microsoft\Windows NT\CurrentVersion\Winlogon" -Name 'Shell'
Remove-ItemProperty -Path "$userRoot\Software\Microsoft\Windows\CurrentVersion\Run" -Name 'DigitalSignageKiosk'
Set-ItemProperty -Path "$userRoot\Control Panel\Desktop" -Name 'ScreenSaveActive' -Value '1'

if ( $usingOfflineHive ) {
	[gc]::Collect()
	[gc]::WaitForPendingFinalizers()
	& reg.exe unload "HKU\$offlineHiveName" | Out-Null
}

Write-Host "==> Removing installed files…"
$installDir = Join-Path $env:ProgramData 'DigitalSignageKiosk'
Remove-Item -Path $installDir -Recurse -Force

if ( $DisableAutoLogon -or $RemoveKioskUser ) {
	$isElevated = ( [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent() ).IsInRole( [Security.Principal.WindowsBuiltInRole]::Administrator )
	if ( -not $isElevated ) {
		Write-Host "⚠ -DisableAutoLogon/-RemoveKioskUser need an elevated PowerShell — re-run as Administrator." -ForegroundColor Yellow
	} else {
		if ( $DisableAutoLogon ) {
			Write-Host "==> Disabling Windows auto sign-in…"
			$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
			Set-ItemProperty -Path $winlogonPath -Name 'AutoAdminLogon' -Value '0'
			Remove-ItemProperty -Path $winlogonPath -Name 'DefaultPassword'

			Write-Host "==> Reverting unattended-kiosk power/lock/update settings…"
			powercfg /change monitor-timeout-ac 10
			powercfg /change monitor-timeout-dc 5
			powercfg /change standby-timeout-ac 30
			powercfg /change standby-timeout-dc 15
			Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\Windows Error Reporting' -Name 'Disabled'
			Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU' -Name 'NoAutoRebootWithLoggedOnUsers'
		}

		if ( $RemoveKioskUser ) {
			Write-Host "==> Removing local '$KioskUsername' account and its profile…"
			$kioskProfile = Get-CimInstance Win32_UserProfile |
				Where-Object { $_.LocalPath -eq (Join-Path $env:SystemDrive "Users\$KioskUsername") }
			if ( $kioskProfile ) {
				Remove-CimInstance -InputObject $kioskProfile
			}
			& net.exe user $KioskUsername /delete | Out-Null
			# Belt and suspenders in case Win32_UserProfile didn't clean the folder.
			Remove-Item -Path (Join-Path $env:SystemDrive "Users\$KioskUsername") -Recurse -Force
		}
	}
}

Write-Host ""
Write-Host "✅ Kiosk removed." -ForegroundColor Green
if ( -not $DisableAutoLogon ) {
	Write-Host "   If -EnableAutoLogon was used to set up Windows auto sign-in, re-run this"
	Write-Host "   script from an elevated PowerShell with -DisableAutoLogon to turn that off too."
}
if ( -not $RemoveKioskUser ) {
	Write-Host "   The dedicated '$KioskUsername' account (if -CreateKioskUser created one) was left"
	Write-Host "   in place; re-run with -RemoveKioskUser (elevated) to delete it too."
}
