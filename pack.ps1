# Package the envoice Magento module into envoice-magento-<version>.zip. The zip has the module
# files at its root, so the same file works two ways:
#   - unzip into app/code/Envoice/Einvoice, or
#   - drop it into a Composer "artifact" repository folder and composer require envoice/module-einvoice.
# A Composer artifact needs a version inside composer.json; the repo copy leaves it out because
# Packagist reads versions from git tags. The version comes from Config::MODULE_VERSION.
#
# Forward-slash entry names via System.IO.Compression (Compress-Archive writes backslashes).
#   pwsh magento-module/pack.ps1

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPhp = Get-Content -Raw (Join-Path $root 'Model/Config.php')
if ($configPhp -notmatch "MODULE_VERSION = '([0-9.]+)'") { throw 'MODULE_VERSION not found in Model/Config.php' }
$version = $Matches[1]

$out = Join-Path $root "envoice-magento-$version.zip"
if (Test-Path $out) { Remove-Item $out -Force }

$skip = @('pack.ps1', '.gitignore', 'CLAUDE.md')
$files = Get-ChildItem -Recurse -File $root | Where-Object {
  $_.Extension -ne '.zip' -and $skip -notcontains $_.Name
}

$composer = Get-Content -Raw (Join-Path $root 'composer.json') | ConvertFrom-Json
$composer | Add-Member -NotePropertyName version -NotePropertyValue $version -Force
$composerJson = $composer | ConvertTo-Json -Depth 10

$zip = [System.IO.Compression.ZipFile]::Open($out, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Length + 1) -replace '\\', '/'
    if ($rel -eq 'composer.json') {
      $entry = $zip.CreateEntry($rel)
      $writer = New-Object System.IO.StreamWriter($entry.Open(), (New-Object System.Text.UTF8Encoding($false)))
      $writer.Write($composerJson)
      $writer.Dispose()
    } else {
      [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $rel) | Out-Null
    }
  }
} finally {
  $zip.Dispose()
}
Write-Host "Wrote $out ($($files.Count) files, version $version)"
