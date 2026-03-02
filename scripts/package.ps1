Param(
    [string]$Version = "1.0.0"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root "dist"
$staging = Join-Path $dist "modules"
$moduleSource = Join-Path $root "modules"
$zipName = "crmconnector-whmcs-v$Version.zip"
$zipPath = Join-Path $dist $zipName

if (Test-Path $dist) {
    Remove-Item -Path $dist -Recurse -Force
}

New-Item -ItemType Directory -Path $staging -Force | Out-Null
Copy-Item -Path $moduleSource\* -Destination $staging -Recurse -Force

Compress-Archive -Path (Join-Path $dist "modules") -DestinationPath $zipPath -Force

Write-Host "Package created: $zipPath"
