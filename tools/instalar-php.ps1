# Descarga PHP 8.5 portable (oficial, windows.php.net) en la carpeta "php" del proyecto.
$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$root   = Split-Path -Parent $PSScriptRoot
$dest   = Join-Path $root 'php'
$estado = Join-Path $root 'estado-php.txt'
try {
    Write-Host 'Consultando la ultima version de PHP 8.5 en windows.php.net...'
    $releases = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
    $rel = $releases.'8.5'
    $pkg = $rel.'nts-vs17-x64'.zip
    $url = "https://windows.php.net/downloads/releases/$($pkg.path)"
    $zip = Join-Path $env:TEMP $pkg.path
    Write-Host "Descargando $($pkg.path) ..."
    Invoke-WebRequest $url -OutFile $zip -UseBasicParsing
    $hash = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLower()
    if ($pkg.sha256 -and $hash -ne $pkg.sha256.ToLower()) { throw "La suma SHA-256 no coincide." }
    if (Test-Path $dest) { Remove-Item $dest -Recurse -Force }
    Expand-Archive $zip -DestinationPath $dest
    Remove-Item $zip
    $ini = Get-Content (Join-Path $dest 'php.ini-development')
    $ini = $ini -replace '^;\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"' `
                -replace '^;extension=mbstring', 'extension=mbstring' `
                -replace '^;extension=openssl', 'extension=openssl'
    Set-Content (Join-Path $dest 'php.ini') $ini
    "OK $($rel.version)" | Set-Content $estado
    Write-Host "PHP $($rel.version) listo en $dest"
} catch {
    "ERROR $($_.Exception.Message)" | Set-Content $estado
    Write-Host "No se pudo descargar PHP: $($_.Exception.Message)"
    exit 1
}
