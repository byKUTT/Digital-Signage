<#
.SYNOPSIS
    Digital Signage kiosk player for Windows.

.DESCRIPTION
    Launches the given player URL full-screen in a chrome-less kiosk browser
    window on every connected monitor — not just the primary one — with no
    visible console. Watches for a global keyboard shortcut (default
    Ctrl+Alt+Shift+Q) to close the kiosk and exit — the only normal way out,
    since Alt+F4/Alt+Tab/the taskbar are unavailable in kiosk mode. If any of
    the browser windows exits on its own (crash, update), just that one is
    relaunched automatically, unless the close hotkey triggered the exit.

    Can be installed as the account's Windows shell in place of explorer.exe
    (install-kiosk.ps1 -ReplaceShell, on by default) — in that mode, this
    script exiting (via the close hotkey) ends the Windows session, and
    AutoAdminLogon immediately signs back in and restarts it.

    Also runnable with no parameters at all — install-kiosk.ps1 always
    writes them to kiosk-config.json (%ProgramData%\DigitalSignageKiosk\),
    and this script falls back to reading that file for anything not passed
    on the command line. That's what lets DigitalSignageKioskLauncher.exe
    (a tiny fixed-arguments wrapper) work as a Windows Assigned Access
    "KioskModeApp" — Assigned Access launches one exe with no arguments of
    its own, so the settings have to come from somewhere else.

.PARAMETER Url
    The player URL to display, e.g. https://yourdomain.com/signage/play/TOKEN/
    Falls back to kiosk-config.json if not given; an error if neither has it.

.PARAMETER Browser
    "edge" (default, ships with Windows) or "chrome".

.PARAMETER CloseModifiers
    Hotkey modifiers to close the kiosk: any combination of Ctrl, Alt, Shift, Win.
    Default: Ctrl,Alt,Shift

.PARAMETER CloseKey
    Hotkey key to close the kiosk (single key name, e.g. Q). Default: Q

.EXAMPLE
    powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File kiosk-player.ps1 -Url "https://example.com/signage/play/abc/"
#>

param(
	[string]$Url,

	[string]$Browser,

	[string[]]$CloseModifiers,

	[string]$CloseKey
)

$ErrorActionPreference = 'Stop'

# Fill in anything not passed on the command line from kiosk-config.json —
# written by install-kiosk.ps1 on every install/re-pair — so this script
# also works launched with zero arguments (Windows Assigned Access's
# "KioskModeApp" launches its one configured .exe with none of its own).
$configPath = Join-Path $env:ProgramData 'DigitalSignageKiosk\kiosk-config.json'
if ( -not $Url -or -not $Browser -or -not $CloseModifiers -or -not $CloseKey ) {
	$config = $null
	if ( Test-Path $configPath ) {
		try {
			$config = Get-Content -Path $configPath -Raw | ConvertFrom-Json
		} catch {
			Write-Warning "Could not read/parse $configPath — $($_.Exception.Message)"
		}
	}
	if ( -not $Url ) { $Url = $config.Url }
	if ( -not $Browser ) { $Browser = if ( $config.Browser ) { $config.Browser } else { 'edge' } }
	if ( -not $CloseModifiers ) { $CloseModifiers = if ( $config.CloseModifiers ) { @( $config.CloseModifiers ) } else { @( 'Ctrl', 'Alt', 'Shift' ) } }
	if ( -not $CloseKey ) { $CloseKey = if ( $config.CloseKey ) { $config.CloseKey } else { 'Q' } }
}
if ( -not $Url ) {
	throw "No -Url given and none found in $configPath. Run install-kiosk.ps1 first, or pass -Url directly."
}
if ( $Browser -notin 'edge', 'chrome' ) {
	throw "Browser must be 'edge' or 'chrome', got '$Browser' (from -Browser or kiosk-config.json)."
}

# ---------------------------------------------------------------------------
# Hide our own console window (harmless if already hidden via -WindowStyle).
# ---------------------------------------------------------------------------
Add-Type -Name Win32Console -Namespace Ds -MemberDefinition @'
[DllImport("kernel32.dll")] public static extern IntPtr GetConsoleWindow();
[DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
'@
$consoleHandle = [Ds.Win32Console]::GetConsoleWindow()
if ($consoleHandle -ne [IntPtr]::Zero) {
	[Ds.Win32Console]::ShowWindow($consoleHandle, 0) | Out-Null # SW_HIDE
}

# ---------------------------------------------------------------------------
# Resolve the browser executable.
# ---------------------------------------------------------------------------
function Resolve-BrowserPath {
	param([string]$Name)

	$candidates = @()
	if ($Name -eq 'edge') {
		$candidates = @(
			"$env:ProgramFiles(x86)\Microsoft\Edge\Application\msedge.exe",
			"$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
			"$env:LOCALAPPDATA\Microsoft\Edge\Application\msedge.exe"
		)
	} else {
		$candidates = @(
			"$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
			"$env:ProgramFiles(x86)\Google\Chrome\Application\chrome.exe",
			"$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
		)
	}

	foreach ($path in $candidates) {
		if ($path -and (Test-Path $path)) {
			return $path
		}
	}
	throw "Could not find $Name. Install it, or pass -Browser edge/chrome for the one you have."
}

# Retry instead of throwing: this script can be installed as the account's
# actual Windows shell (install-kiosk.ps1 -ReplaceShell), so letting an
# exception escape and end the process would log the session straight back
# off — with AutoAdminLogon that's a fast sign-in/sign-off loop, not a
# graceful failure. A transient failure here (e.g. the browser package still
# finishing its install on first boot) should just be waited out instead.
$browserPath = $null
while ( -not $browserPath ) {
	try {
		$browserPath = Resolve-BrowserPath -Name $Browser
	} catch {
		Start-Sleep -Seconds 5
	}
}

# ---------------------------------------------------------------------------
# Global hotkey registration (works even while the kiosk browser has focus).
# ---------------------------------------------------------------------------
Add-Type -Name Win32Hotkey -Namespace Ds -MemberDefinition @'
[DllImport("user32.dll")] public static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);
[DllImport("user32.dll")] public static extern bool UnregisterHotKey(IntPtr hWnd, int id);
'@

$MOD_ALT = 0x0001
$MOD_CONTROL = 0x0002
$MOD_SHIFT = 0x0004
$MOD_WIN = 0x0008
$HOTKEY_ID = 0xB001

$modifierFlags = 0
foreach ($m in $CloseModifiers) {
	switch ($m.ToLowerInvariant()) {
		'ctrl'  { $modifierFlags = $modifierFlags -bor $MOD_CONTROL }
		'alt'   { $modifierFlags = $modifierFlags -bor $MOD_ALT }
		'shift' { $modifierFlags = $modifierFlags -bor $MOD_SHIFT }
		'win'   { $modifierFlags = $modifierFlags -bor $MOD_WIN }
	}
}
$vkCode = [int][System.Windows.Forms.Keys]::Parse([System.Windows.Forms.Keys], $CloseKey.ToUpperInvariant())

Add-Type -AssemblyName System.Windows.Forms

# A hidden message-only form is the simplest reliable way to receive
# WM_HOTKEY in PowerShell without a full custom message loop.
Add-Type -Namespace Ds -Name HotkeyForm -MemberDefinition @"
using System;
using System.Windows.Forms;
using System.Runtime.InteropServices;

public class HiddenForm : Form {
	public event EventHandler HotkeyPressed;
	protected override void SetVisibleCore(bool value) { base.SetVisibleCore(false); }
	protected override void WndProc(ref Message m) {
		const int WM_HOTKEY = 0x0312;
		if (m.Msg == WM_HOTKEY && HotkeyPressed != null) {
			HotkeyPressed(this, EventArgs.Empty);
		}
		base.WndProc(ref m);
	}
}
"@ -ReferencedAssemblies 'System.Windows.Forms', 'System.Drawing' -ErrorAction SilentlyContinue

$form = New-Object Ds.HotkeyForm.HiddenForm
$form.CreateControl() # forces handle creation so RegisterHotKey has a target

$registered = [Ds.Win32Hotkey]::RegisterHotKey($form.Handle, $HOTKEY_ID, [uint32]$modifierFlags, [uint32]$vkCode)
if (-not $registered) {
	Write-Warning "Could not register the close hotkey (it may already be in use). The kiosk will still run; close it via Task Manager if needed."
}

$script:closing = $false
$closeAction = {
	$script:closing = $true
	# Kill only the process trees we launched (taskkill /T), not every
	# Edge/Chrome window on the machine — matters on a shared PC, not just a
	# dedicated kiosk box.
	foreach ($process in $script:browserProcesses.Values) {
		if ($process -and -not $process.HasExited) {
			Start-Process -FilePath 'taskkill.exe' -ArgumentList @('/PID', $process.Id, '/T', '/F') -WindowStyle Hidden -Wait -ErrorAction SilentlyContinue
		}
	}
	[Ds.Win32Hotkey]::UnregisterHotKey($form.Handle, $HOTKEY_ID) | Out-Null
	[System.Windows.Forms.Application]::Exit()
}
Register-ObjectEvent -InputObject $form -EventName HotkeyPressed -Action $closeAction | Out-Null

# ---------------------------------------------------------------------------
# Launch + watchdog: one kiosk browser window per connected monitor, all
# showing the same Url, positioned to exactly cover that monitor. Relaunches
# any window that exits on its own, but not if the hotkey closed the kiosk.
# ---------------------------------------------------------------------------
function Start-KioskBrowserOnScreen {
	param( $Bounds, [string]$ProfileKey )

	# Chromium refuses to run two instances against the same profile at once,
	# so each screen gets its own — that's also what lets them run as fully
	# independent, individually-relaunchable windows.
	$profileDir = Join-Path $env:LOCALAPPDATA "DigitalSignageKiosk\profile-$ProfileKey"
	New-Item -ItemType Directory -Path $profileDir -Force | Out-Null

	$flags = @(
		"--kiosk", $Url,
		"--user-data-dir=$profileDir",
		"--window-position=$($Bounds.X),$($Bounds.Y)",
		"--window-size=$($Bounds.Width),$($Bounds.Height)",
		"--edge-kiosk-type=fullscreen",
		"--no-first-run",
		"--noerrdialogs",
		"--disable-infobars",
		"--disable-translate",
		"--disable-features=Translate,TranslateUI",
		"--disable-session-crashed-bubble",
		"--overscroll-history-navigation=0",
		"--autoplay-policy=no-user-gesture-required"
	)
	return Start-Process -FilePath $browserPath -ArgumentList $flags -PassThru
}

# Enumerated once at startup, not live-tracked — a monitor plugged in or
# unplugged mid-session takes effect on the next restart (close hotkey,
# sign-out/sign-in, or reboot), not instantly.
$screens = @( [System.Windows.Forms.Screen]::AllScreens | ForEach-Object { $_.Bounds } )
if ( $screens.Count -eq 0 ) {
	# Shouldn't happen (Windows always reports at least one), but don't run
	# with nothing to show on if it somehow does.
	$screens = @( [System.Drawing.Rectangle]::new(0, 0, 1920, 1080) )
}

$script:browserProcesses = @{} # screen index -> Process
for ( $i = 0; $i -lt $screens.Count; $i++ ) {
	$script:browserProcesses[$i] = Start-KioskBrowserOnScreen -Bounds $screens[$i] -ProfileKey $i
}

$watchdogTimer = New-Object System.Windows.Forms.Timer
$watchdogTimer.Interval = 2000
$watchdogTimer.Add_Tick({
	if ($script:closing) {
		return
	}
	foreach ($i in @($script:browserProcesses.Keys)) {
		if ($script:browserProcesses[$i].HasExited) {
			Start-Sleep -Seconds 2
			$script:browserProcesses[$i] = Start-KioskBrowserOnScreen -Bounds $screens[$i] -ProfileKey $i
		}
	}
})
$watchdogTimer.Start()

[System.Windows.Forms.Application]::Run()
