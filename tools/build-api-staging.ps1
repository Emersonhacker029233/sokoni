# Builds the STAGING package of the Laravel API: staging-deploy\api-staging.zip
#
# Usage (PowerShell, from anywhere in the repo):
#   powershell -ExecutionPolicy Bypass -File tools\build-api-staging.ps1
#   powershell -ExecutionPolicy Bypass -File tools\build-api-staging.ps1 -Commit feature/delivery
#   powershell -ExecutionPolicy Bypass -File tools\build-api-staging.ps1 -FirstInstall
#
# What goes in: the COMMITTED api/ folder at -Commit (git archive, so local
# .env, logs, uploads and caches can never leak in), plus vendor/ from
# `composer install --no-dev`. What never goes in: anything under
# bootstrap/cache except its .gitignore (packages.php, services.php,
# config.php, routes-*.php, events.php, blade-icons.php and the Filament
# component cache bootstrap/cache/filament/), compiled views, file-cache
# data, sessions, logs, tests and front-end build tooling.
#
# Why: a cached file extracted onto the server freezes what Laravel and
# Filament see. A Filament component cache written before a resource
# existed hides that resource from the panel (2026-11-04: the Drivers
# screen). Laravel rebuilds packages.php and services.php by itself on the
# first request, so leaving them out costs nothing.
#
# Update packages (the default) also leave out public/.htaccess and
# public/.user.ini: on staging the server's .htaccess carries the admin
# directory-password block (STAGING-STEPS.md step 10) and extracting over it
# would remove that protection. -FirstInstall includes both for a new server.
#
# The script checks the finished zip and refuses to leave a package behind
# that breaks any of these rules.

param(
    [string]$Commit = 'HEAD',
    [string]$Out = '',
    [switch]$FirstInstall
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repo = (git rev-parse --show-toplevel).Trim()
if (-not $Out) { $Out = Join-Path $repo 'staging-deploy\api-staging.zip' }
$sha = (git -C $repo rev-parse --verify "$Commit^{commit}").Trim()
$short = $sha.Substring(0, 7)
$branch = (git -C $repo rev-parse --abbrev-ref HEAD).Trim()

$dirty = git -C $repo status --porcelain -- api
if ($dirty) {
    Write-Warning "api/ has uncommitted changes. They are NOT in the package; it is built from commit $short only."
}

$stage = Join-Path ([System.IO.Path]::GetTempPath()) ("sokoni-api-staging-" + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $stage | Out-Null
$tarPath = "$stage.tar"

try {
    Write-Host "1/5 Exporting api/ at $short" -ForegroundColor Cyan
    git -C $repo archive --format=tar -o $tarPath "${sha}:api"
    if ($LASTEXITCODE -ne 0) { throw 'git archive failed' }
    tar -xf $tarPath -C $stage
    if ($LASTEXITCODE -ne 0) { throw 'tar extract failed' }

    Write-Host '2/5 composer install --no-dev (scripts off, so nothing is written to bootstrap/cache)' -ForegroundColor Cyan
    $composerArgs = @('install', '--working-dir', $stage, '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts')
    $phar = Join-Path $env:USERPROFILE 'bin\composer.phar'
    if (Get-Command composer -ErrorAction SilentlyContinue) {
        & composer @composerArgs
    } elseif (Test-Path $phar) {
        & php $phar @composerArgs
    } else {
        throw 'Composer not found: put composer on PATH or composer.phar in %USERPROFILE%\bin.'
    }
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

    Write-Host '3/5 Removing dev-only files and every cache file' -ForegroundColor Cyan
    $drop = @('tests', 'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js', 'public/build.zip', '.env.example', '.npmrc', 'node_modules')
    if (-not $FirstInstall) { $drop += @('public/.htaccess', 'public/.user.ini') }
    foreach ($rel in $drop) {
        $p = Join-Path $stage $rel
        if (Test-Path $p) { Remove-Item -Recurse -Force $p }
    }
    # Keep each folder and its .gitignore (Laravel needs the folders to exist), drop everything else in it.
    foreach ($rel in @('bootstrap/cache', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/testing', 'storage/logs')) {
        $dir = Join-Path $stage $rel
        if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
        Get-ChildItem -LiteralPath $dir -Force | Where-Object { $_.Name -ne '.gitignore' -and -not ($rel -eq 'storage/framework/cache' -and $_.Name -eq 'data') } |
            Remove-Item -Recurse -Force
    }
    $data = Join-Path $stage 'storage/framework/cache/data'
    if (Test-Path $data) { Get-ChildItem -LiteralPath $data -Force | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Recurse -Force }

    # Lets you check on the server which build is really live (File Manager > View).
    $stamp = @(
        "Sokoni API staging package",
        "commit: $sha",
        "branch: $branch",
        "type:   $(if ($FirstInstall) { 'first install (includes public/.htaccess and .user.ini)' } else { 'update (keeps the server''s public/.htaccess and .user.ini)' })",
        "built:  $((Get-Date).ToString('yyyy-MM-dd HH:mm:ss zzz'))"
    ) -join "`n"
    [System.IO.File]::WriteAllText((Join-Path $stage 'BUILD.txt'), $stamp + "`n")

    Write-Host '4/5 Zipping (forward-slash paths for Linux)' -ForegroundColor Cyan
    $outDir = Split-Path -Parent $Out
    if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
    if (Test-Path $Out) { Remove-Item -Force $Out }
    $root = (Resolve-Path $stage).Path.TrimEnd('\') + '\'
    $zip = [System.IO.Compression.ZipFile]::Open($Out, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -LiteralPath $stage -Recurse -Force -File | ForEach-Object {
            $name = $_.FullName.Substring($root.Length).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $name, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally {
        $zip.Dispose()
    }

    Write-Host '5/5 Checking the package' -ForegroundColor Cyan
    $problems = New-Object System.Collections.Generic.List[string]
    $read = [System.IO.Compression.ZipFile]::OpenRead($Out)
    try {
        $names = @($read.Entries | ForEach-Object { $_.FullName })
        foreach ($n in $names) {
            if ($n.Contains('\')) { $problems.Add("backslash in path: $n") }
            if ($n -match '^bootstrap/cache/' -and $n -ne 'bootstrap/cache/.gitignore') { $problems.Add("cache file: $n") }
            if ($n -match '^storage/(framework|logs)/' -and $n -notmatch '/\.gitignore$') { $problems.Add("runtime file: $n") }
            if ($n -match '(^|/)\.env$') { $problems.Add("environment file: $n") }
            if ($n -match '^tests/') { $problems.Add("test file: $n") }
            if (-not $FirstInstall -and ($n -eq 'public/.htaccess' -or $n -eq 'public/.user.ini')) { $problems.Add("would overwrite the server's $n") }
        }
        $required = @('artisan', 'BUILD.txt', 'public/index.php', 'vendor/autoload.php', 'bootstrap/cache/.gitignore', 'app/Filament/Resources/Drivers/DriverResource.php')
        if ($FirstInstall) { $required += @('public/.htaccess', 'public/.user.ini') }
        foreach ($must in $required) {
            if ($names -notcontains $must) { $problems.Add("missing: $must") }
        }
        $classmap = $read.GetEntry('vendor/composer/autoload_classmap.php')
        if ($null -eq $classmap) {
            $problems.Add('missing: vendor/composer/autoload_classmap.php')
        } else {
            $reader = New-Object System.IO.StreamReader($classmap.Open())
            $text = $reader.ReadToEnd()
            $reader.Dispose()
            if (-not $text.Contains("App\\Filament\\Resources\\Drivers\\DriverResource")) { $problems.Add('autoloader does not know DriverResource') }
        }
        $count = $names.Count
    } finally {
        $read.Dispose()
    }

    if ($problems.Count -gt 0) {
        Remove-Item -Force $Out
        $problems | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
        throw "Package rejected ($($problems.Count) problems). Nothing was written."
    }

    $hash = (Get-FileHash -Algorithm SHA256 $Out).Hash
    $mb = [math]::Round((Get-Item $Out).Length / 1MB, 1)
    Write-Host ''
    Write-Host "OK  $Out" -ForegroundColor Green
    Write-Host "    commit $short ($branch), $count files, $mb MB"
    Write-Host "    no bootstrap/cache files, no compiled views, no .env"
    if (-not $FirstInstall) { Write-Host "    update package: the server's public/.htaccess and .user.ini are left as they are" }
    Write-Host "    SHA-256 $hash"
} finally {
    if (Test-Path $tarPath) { Remove-Item -Force $tarPath }
    if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
}
