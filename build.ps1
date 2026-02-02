# Build script for edge-link-router plugin
param(
    [string]$OutputFile = "edge-link-router.zip"
)

$ErrorActionPreference = "Stop"

# Remove existing zip
if (Test-Path $OutputFile) {
    Remove-Item $OutputFile
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginDir = Join-Path $PSScriptRoot "plugin"
$zipPath = Join-Path $PSScriptRoot $OutputFile

# Create zip with proper forward slashes and plugin folder wrapper
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

try {
    Get-ChildItem -Path $pluginDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($pluginDir.Length + 1)
        # Use forward slashes and wrap in plugin folder name
        $entryName = "edge-link-router/" + $relativePath.Replace("\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
        Write-Host "  Added: $entryName"
    }
} finally {
    $zip.Dispose()
}

$fileInfo = Get-Item $zipPath
Write-Host ""
Write-Host "Created: $zipPath"
Write-Host "Size: $([math]::Round($fileInfo.Length / 1KB, 1)) KB"
