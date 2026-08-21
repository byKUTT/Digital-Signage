<#
.SYNOPSIS
    Digital Signage kiosk player for Windows.

.DESCRIPTION
    Launches the given player URL full-screen in a chrome-less kiosk browser
    window, with no visible console. Watches for a global keyboard shortcut
    (default Ctrl+Alt+Shift+Q) to close the kiosk and exit — the only normal
    way out, since Alt+F4/Alt+Tab/the taskbar are unavailable in kiosk mode.
    If the browser process ever exits on its own (crash, update), it is
    relaunched automatically unless the close hotkey triggered the exit.

.PARAMETER Url
    The player URL to display, e.g. https://yourdomain.com/signage/play/TOKEN/

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
	[Parameter(Mandatory = $true)]
	[string]$Url,

	[ValidateSet('edge', 'chrome')]
	[string]$Browser = 'edge',

	[string[]]$CloseModifiers = @('Ctrl', 'Alt', 'Shift'),

	[string]$CloseKey = 'Q'
)

$ErrorActionPreference = 'Stop'

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

$browserPath = Resolve-BrowserPath -Name $Browser

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
	# Kill only the process tree we launched (taskkill /T), not every Edge/Chrome
	# window on the machine — matters on a shared PC, not just a dedicated kiosk box.
	if ($script:browserProcess -and -not $script:browserProcess.HasExited) {
		Start-Process -FilePath 'taskkill.exe' -ArgumentList @('/PID', $script:browserProcess.Id, '/T', '/F') -WindowStyle Hidden -Wait -ErrorAction SilentlyContinue
	}
	[Ds.Win32Hotkey]::UnregisterHotKey($form.Handle, $HOTKEY_ID) | Out-Null
	[System.Windows.Forms.Application]::Exit()
}
Register-ObjectEvent -InputObject $form -EventName HotkeyPressed -Action $closeAction | Out-Null

# ---------------------------------------------------------------------------
# Launch + watchdog: relaunch the browser if it exits on its own, but not if
# the user closed it via the hotkey.
# ---------------------------------------------------------------------------
function Start-KioskBrowser {
	$flags = @(
		"--kiosk", $Url,
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

$script:browserProcess = Start-KioskBrowser

$watchdogTimer = New-Object System.Windows.Forms.Timer
$watchdogTimer.Interval = 2000
$watchdogTimer.Add_Tick({
	if ($script:closing) {
		return
	}
	if ($script:browserProcess.HasExited) {
		Start-Sleep -Seconds 2
		$script:browserProcess = Start-KioskBrowser
	}
})
$watchdogTimer.Start()

[System.Windows.Forms.Application]::Run()
