<# Digital Signage 3.0.0 multi-display controller for Windows 10/11. #>
param(
	[Parameter(Mandatory = $true)][string]$Site,
	[ValidateSet('edge', 'chrome')][string]$Browser = 'edge'
)

$ErrorActionPreference = 'Stop'
$Version = '3.0.0'
$Site = $Site.TrimEnd('/')
if ($Site -notmatch '^https://') { throw 'Controller site must use HTTPS.' }
$Root = Join-Path $env:ProgramData 'DigitalSignageKiosk'
$IdentityPath = Join-Path $Root 'controller.json'
$StatePath = Join-Path $Root 'controller-state.json'
$LogPath = Join-Path $Root 'controller.log'
$ProfileRoot = Join-Path $Root 'profiles'
New-Item -ItemType Directory -Path $Root, $ProfileRoot -Force | Out-Null
& icacls.exe $Root /inheritance:r /grant:r "$env:USERDOMAIN\$env:USERNAME`:(OI)(CI)F" 'SYSTEM:(OI)(CI)F' | Out-Null
Add-Type -AssemblyName System.Windows.Forms
Unregister-ScheduledTask -TaskName 'DigitalSignagePendingUpdate' -Confirm:$false -ErrorAction SilentlyContinue

function Write-AgentLog([string]$Message) {
	Add-Content -Path $LogPath -Value "$(Get-Date -Format o) $Message"
}

function Invoke-JsonRequest([string]$Url, $Body, [string]$Token = '') {
	$headers = @{}
	if ($Token) { $headers['X-DS-Controller-Token'] = $Token }
	if ($null -eq $Body) { return Invoke-RestMethod -Uri $Url -Headers $headers -Method Get -TimeoutSec 30 }
	Invoke-RestMethod -Uri $Url -Headers $headers -Method Post -ContentType 'application/json' -Body ($Body | ConvertTo-Json -Depth 8 -Compress) -TimeoutSec 30
}

function Save-JsonAtomic([string]$Path, $Value) {
	$temp = "$Path.tmp"
	$Value | ConvertTo-Json -Depth 8 | Set-Content -Path $temp -Encoding UTF8
	Move-Item -Path $temp -Destination $Path -Force
}

function Get-BrowserPath {
	$candidates = if ($Browser -eq 'edge') {
		@("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe")
	} else {
		@("$env:ProgramFiles\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe")
	}
	foreach ($candidate in $candidates) { if ($candidate -and (Test-Path $candidate)) { return $candidate } }
	throw "Could not find $Browser."
}

function Get-Outputs {
	@([System.Windows.Forms.Screen]::AllScreens | ForEach-Object {
		$bounds = $_.Bounds
		$key = ($_.DeviceName -replace '[^A-Za-z0-9_-]', '').ToLowerInvariant()
		@{ output_key = $key; connector = $_.DeviceName; label = $_.DeviceName; geometry = "$($bounds.X),$($bounds.Y),$($bounds.Width),$($bounds.Height)"; resolution = "$($bounds.Width)x$($bounds.Height)"; is_primary = $_.Primary }
	})
}

function Get-Telemetry {
	$os = Get-CimInstance Win32_OperatingSystem
	$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
	$disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
	$recent = if (Test-Path $LogPath) { @(Get-Content $LogPath -Tail 20) } else { @() }
	@{
		architecture = $env:PROCESSOR_ARCHITECTURE; cpu_model = $cpu.Name; cpu_cores = $cpu.NumberOfLogicalProcessors
		memory_total_mb = [math]::Round($os.TotalVisibleMemorySize / 1024); memory_free_mb = [math]::Round($os.FreePhysicalMemory / 1024)
		disk_total_mb = [math]::Round($disk.Size / 1MB); disk_free_mb = [math]::Round($disk.FreeSpace / 1MB)
		uptime_seconds = [int]((Get-Date) - $os.LastBootUpTime).TotalSeconds; browser_running = [bool](Get-Process msedge,chrome -ErrorAction SilentlyContinue)
		rtc_wake_supported = $false; suspend_supported = $true; os_update_supported = $true; recent_log = $recent
	}
}

function Ensure-Identity($Outputs) {
	if (Test-Path $IdentityPath) { return Get-Content $IdentityPath -Raw | ConvertFrom-Json }
	$response = Invoke-JsonRequest "$Site/wp-json/ds/v1/controller/request" @{ platform = 'windows'; hostname = $env:COMPUTERNAME; outputs = $Outputs }
	$identity = @{ site = $Site; public_id = $response.public_id; token = $response.token; pairing_code = $response.code }
	Save-JsonAtomic $IdentityPath $identity
	Write-AgentLog "Registered controller $($response.public_id), pairing code $($response.code)"
	$identity | ConvertTo-Json | ConvertFrom-Json
}

function Sync-Players($Assignments, $Outputs, [hashtable]$Players, [string]$BrowserPath) {
	$geometry = @{}
	foreach ($output in $Outputs) { $geometry[$output.output_key] = @($output.geometry.Split(',') | ForEach-Object { [int]$_ }) }
	$desired = @{}
	foreach ($assignment in $Assignments) {
		$url = if ($assignment.player_url) { $assignment.player_url } else { $assignment.pairing_url }
		if ($url -and $geometry.ContainsKey($assignment.output_key)) { $desired[$assignment.output_key] = $url }
	}
	foreach ($key in @($Players.Keys)) {
		if (-not $desired.ContainsKey($key) -or $Players[$key].HasExited) {
			Stop-Process -Id $Players[$key].Id -Force -ErrorAction SilentlyContinue
			$Players.Remove($key)
		}
	}
	foreach ($key in $desired.Keys) {
		if ($Players.ContainsKey($key)) { continue }
		$x, $y, $width, $height = $geometry[$key]
		$profile = Join-Path $ProfileRoot $key
		New-Item -ItemType Directory -Path $profile -Force | Out-Null
		$args = @('--kiosk', $desired[$key], "--user-data-dir=$profile", "--window-position=$x,$y", "--window-size=$width,$height", '--no-first-run', '--noerrdialogs', '--disable-features=Translate,TranslateUI', '--autoplay-policy=no-user-gesture-required')
		$Players[$key] = Start-Process -FilePath $BrowserPath -ArgumentList $args -PassThru
	}
}

function Ack-Command($Identity, $Command, [string]$Status, [string]$Result) {
	Invoke-JsonRequest "$Site/wp-json/ds/v1/controller/$($Identity.public_id)/command/$($Command.id)/ack" @{ status = $Status; result = $Result } $Identity.token | Out-Null
}

function Install-VerifiedUpdate($Payload) {
	$url = [string]$Payload.url
	$expected = [string]$Payload.sha256
	if ($url -notmatch '^https://github\.com/byKUTT/Digital-Signage/releases/download/' -or $expected -notmatch '^[a-f0-9]{64}$') { throw 'Update URL or digest is not trusted.' }
	$staging = Join-Path $Root 'pending-update'
	Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
	New-Item -ItemType Directory -Path $staging | Out-Null
	$archive = Join-Path $staging 'controller.zip'
	Invoke-WebRequest -Uri $url -OutFile $archive -UseBasicParsing
	$actual = (Get-FileHash -Path $archive -Algorithm SHA256).Hash.ToLowerInvariant()
	if ($actual -ne $expected) { throw 'Update digest mismatch.' }
	Add-Type -AssemblyName System.IO.Compression.FileSystem
	$zip = [System.IO.Compression.ZipFile]::OpenRead($archive)
	try {
		foreach ($entry in $zip.Entries) {
			$normalized = $entry.FullName.Replace('\', '/')
			if ($normalized.StartsWith('/') -or $normalized -match '(^|/)\.\.(/|$)') { throw 'Unsafe path in update archive.' }
		}
	} finally { $zip.Dispose() }
	Expand-Archive -Path $archive -DestinationPath (Join-Path $staging 'package') -Force
	$installer = @(Get-ChildItem (Join-Path $staging 'package') -Filter 'install-kiosk.ps1' -Recurse)
	if ($installer.Count -ne 1) { throw 'Update package has no unique installer.' }
	$arguments = '-NoProfile -ExecutionPolicy Bypass -File "{0}" -Site "{1}" -Browser {2} -MultiDisplay' -f $installer[0].FullName, $Site, $Browser
	$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments
	$trigger = New-ScheduledTaskTrigger -Once -At ((Get-Date).AddMinutes(1))
	$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Highest
	Register-ScheduledTask -TaskName 'DigitalSignagePendingUpdate' -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null
}

function Invoke-ControllerCommand($Identity, $Command, [hashtable]$Players) {
	Ack-Command $Identity $Command 'running' 'Started'
	try {
		switch ($Command.type) {
			'restart_players' { foreach ($process in $Players.Values) { Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue }; $Players.Clear() }
			'refresh_displays' { }
			'reboot' { Start-Process 'shutdown.exe' -ArgumentList '/r /t 5 /f' }
			'system_update' { Start-Process 'UsoClient.exe' -ArgumentList 'StartInstall' -Wait }
			'software_update' { Install-VerifiedUpdate $Command.payload }
			'power_test' { throw 'RTC wake testing is available only on Linux controllers.' }
			default { throw "Unsupported command $($Command.type)" }
		}
		Ack-Command $Identity $Command 'succeeded' "$($Command.type) completed"
	} catch {
		Ack-Command $Identity $Command 'failed' $_.Exception.Message
		Write-AgentLog "Command failed: $($_.Exception.Message)"
	}
}

$BrowserPath = Get-BrowserPath
$Players = @{}
$Outputs = Get-Outputs
$Identity = Ensure-Identity $Outputs
while ($true) {
	try {
		$Outputs = Get-Outputs
		$os = Get-CimInstance Win32_OperatingSystem
		$payload = @{ hostname = $env:COMPUTERNAME; os_version = $os.Caption; agent_version = $Version; software_version = $Version; outputs = $Outputs; telemetry = Get-Telemetry }
		$state = Invoke-JsonRequest "$Site/wp-json/ds/v1/controller/$($Identity.public_id)/heartbeat" $payload $Identity.token
		Save-JsonAtomic $StatePath $state
		Sync-Players $state.assignments $Outputs $Players $BrowserPath
		if ($state.command) { Invoke-ControllerCommand $Identity $state.command $Players }
	} catch { Write-AgentLog "Heartbeat failed: $($_.Exception.Message)" }
	Start-Sleep -Seconds 10
}
