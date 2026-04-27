param(
    [string] $PluginSlug = "Insta_feed_WP_plugin"
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$pluginSource = Join-Path $projectRoot "wordpress-plugin\$PluginSlug"
$distDir = Join-Path $projectRoot "dist"
$buildDir = Join-Path $distDir "build"
$packageRoot = Join-Path $buildDir $PluginSlug
$zipPath = Join-Path $distDir "$PluginSlug.zip"

if (!(Test-Path $pluginSource)) {
    throw "Plugin source was not found: $pluginSource"
}

if (Test-Path $buildDir) {
    Remove-Item -LiteralPath $buildDir -Recurse -Force
}

if (!(Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

New-Item -ItemType Directory -Path $packageRoot | Out-Null
Copy-Item -Path (Join-Path $pluginSource "*") -Destination $packageRoot -Recurse -Force

if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

Compress-Archive -Path $packageRoot -DestinationPath $zipPath -Force
Remove-Item -LiteralPath $buildDir -Recurse -Force

Write-Host "Created plugin package: $zipPath"
