param(
    [string] $PluginSlug = "Insta_feed_WP_plugin"
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$pluginSource = Join-Path $projectRoot "wordpress-plugin\$PluginSlug"
$distDir = Join-Path $projectRoot "dist"
$zipPath = Join-Path $distDir "$PluginSlug.zip"

if (!(Test-Path $pluginSource)) {
    throw "Plugin source was not found: $pluginSource"
}

if (!(Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

if (Test-Path $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    Get-ChildItem -LiteralPath $pluginSource -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($pluginSource.Length).TrimStart('\', '/')
        $entryName = ($PluginSlug + "/" + $relativePath.Replace('\', '/'))
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $_.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $zip.Dispose()
}

Write-Host "Created plugin package: $zipPath"
