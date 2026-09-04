# build.ps1 — Build release zips for com_userreminder (single merged component zip) and the package.
param(
    [string]$Version = "4.2.0",
    [string]$OutDir = "C:\wamp64\www\UserReminder\releases"
)

$ErrorActionPreference = "Stop"

$root      = Resolve-Path "C:\wamp64\www\UserReminder"
$buildDir  = Join-Path $OutDir "build"
$pkgInner  = Join-Path $buildDir "packages\pkg_userreminder"
$compDir   = Join-Path $pkgInner "com_userreminder"
$adminDir  = Join-Path $compDir "admin"
$siteDir   = Join-Path $compDir "site"

if (Test-Path $buildDir) { Remove-Item -Recurse -Force $buildDir }
New-Item -ItemType Directory -Path $adminDir -Force | Out-Null
New-Item -ItemType Directory -Path $siteDir  -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $buildDir "language") -Force | Out-Null

Write-Host "Copying admin component (-> admin/)..."
Copy-Item -Recurse -Path "$root\administrator\components\com_userreminder\*" -Destination $adminDir
if (Test-Path "$adminDir\legacy") { Remove-Item -Recurse -Force "$adminDir\legacy" }
# The root manifest is shipped once at the zip root — remove the admin copy
# and any stray site-manifest copy (a package quirk can drop it here).
Remove-Item -Force -ErrorAction SilentlyContinue "$adminDir\userreminder.xml", "$adminDir\com_userreminder.xml"

Write-Host "Copying site component (-> site/)..."
Copy-Item -Recurse -Path "$root\components\com_userreminder\*" -Destination $siteDir
if (Test-Path "$siteDir\legacy") { Remove-Item -Recurse -Force "$siteDir\legacy" }

Write-Host "Copying component media..."
Copy-Item -Recurse -Path "$root\administrator\components\com_userreminder\media" -Destination (Join-Path $compDir "media")

Write-Host "Copying root manifest..."
Copy-Item "$root\administrator\components\com_userreminder\userreminder.xml" -Destination $compDir
# <scriptfile> resolves from the manifest root — ship the installer script there too.
Copy-Item "$root\administrator\components\com_userreminder\script.php" -Destination (Join-Path $compDir "script.php")

Write-Host "Copying task plugin..."
$pluginDir = Join-Path $pkgInner "plg_task_userreminder"
New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null
Copy-Item -Recurse -Path "$root\plugins\task\userreminder\*" -Destination $pluginDir
if (Test-Path "$pluginDir\legacy") { Remove-Item -Recurse -Force "$pluginDir\legacy" }

Write-Host "Copying package files..."
Copy-Item "$root\pkg_script.php" -Destination $buildDir
Copy-Item "$root\pkg_userreminder.xml" -Destination $buildDir

Write-Host "Replacing ##VERSION## in all manifest XMLs with $Version..."
$xmls = Get-ChildItem -Path $buildDir -Recurse -Filter "*.xml"
foreach ($x in $xmls) {
    (Get-Content $x.FullName -Raw) -replace "##VERSION##", $Version | Set-Content -NoNewline $x.FullName
}

Write-Host "Zipping inner zips..."
$compZip   = Join-Path $pkgInner "com_userreminder.zip"
$pluginZip = Join-Path $pkgInner "plg_task_userreminder.zip"

function Zip-Dir($src, $dst) {
    if (Test-Path $dst) { Remove-Item $dst }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($src, $dst)
}

Zip-Dir $compDir   $compZip
Zip-Dir $pluginDir $pluginZip

# Clean source dirs from inside the package — only zips should remain inside pkg_inner
Remove-Item -Recurse -Force $compDir
Remove-Item -Recurse -Force $pluginDir

$pkgZip = Join-Path $OutDir "pkg_userreminder_v${Version}.zip"
if (Test-Path $pkgZip) { Remove-Item $pkgZip }
Zip-Dir $buildDir $pkgZip

Write-Host "Done. Package: $pkgZip"
Write-Host "Inner zips inside the package:"
Get-ChildItem $pkgInner | Select-Object Name, Length
