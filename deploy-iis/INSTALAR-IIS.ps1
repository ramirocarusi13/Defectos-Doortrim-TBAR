#Requires -RunAsAdministrator
<#
  Instala el backend de DOORTRIM DEFECTOS en IIS (servidor .150), puerto 9001.
  Correr en PowerShell COMO ADMINISTRADOR, desde la carpeta del repo:

      powershell -ExecutionPolicy Bypass -File deploy-iis\INSTALAR-IIS.ps1

  Antes (ver PASO-A-PASO-IIS.md): PHP 8.3+ NTS x64 con los drivers sqlsrv, modulo
  URL Rewrite de IIS, backend\vendor (vendor.zip del paquete) y backend\.env
  (copia de deploy-iis\env.servidor con las claves).
  Se puede correr de nuevo sin problema: actualiza lo que ya existe.
#>
param(
    [string]$Repo = (Split-Path -Parent $PSScriptRoot),
    [int]$Puerto = 9001,
    [string]$PhpCgi = "C:\PHP\php-cgi.exe",
    [string]$Sitio = "DoortrimDefectos"
)
$ErrorActionPreference = "Stop"
$backend = Join-Path $Repo "backend"
$publico = Join-Path $backend "public"
$appcmd = Join-Path $env:windir "System32\inetsrv\appcmd.exe"

function Paso([string]$t) { Write-Host "`n== $t" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "   OK  $t" -ForegroundColor Green }
function Falla([string]$t) { Write-Host "`n[ERROR] $t" -ForegroundColor Red; exit 1 }

Paso "1/6 Chequeos"
if (-not (Test-Path $appcmd)) { Falla "IIS no esta instalado (no encuentro appcmd.exe)." }
if (-not (Test-Path $PhpCgi)) { Falla "No encuentro $PhpCgi. Instala PHP 8.3+ NTS x64 en C:\PHP o pasa -PhpCgi <ruta>." }
if (-not (Test-Path (Join-Path $env:windir "System32\inetsrv\rewrite.dll"))) { Falla "Falta el modulo URL Rewrite de IIS. Instalalo y volve a correr esto." }
if (-not (Test-Path (Join-Path $backend "vendor\autoload.php"))) { Falla "Falta backend\vendor: descomprimi vendor.zip del paquete en backend\ (o corre 'composer install --no-dev')." }
if (-not (Test-Path (Join-Path $backend ".env"))) { Falla "Falta backend\.env: copia deploy-iis\env.servidor a backend\.env y completa las claves." }
$php = Join-Path (Split-Path $PhpCgi) "php.exe"
$mods = & $php -m
foreach ($m in "sqlsrv", "pdo_sqlsrv", "openssl", "mbstring", "fileinfo", "gd", "zip", "curl") {
    if (-not ($mods -contains $m)) { Falla "A PHP le falta la extension '$m'. Habilitala en php.ini (ver PASO-A-PASO-IIS.md)." }
}
Ok "IIS, PHP ($(& $php -r 'echo PHP_VERSION;')), URL Rewrite, vendor y .env"
try {
    $cgi = Get-WindowsFeature Web-CGI -ErrorAction Stop
    if (-not $cgi.Installed) { Install-WindowsFeature Web-CGI | Out-Null; Ok "Habilitado CGI de IIS" }
} catch {
    Write-Host "   (no pude verificar 'CGI' de IIS; si PHP no responde, habilitalo en Roles > IIS > Desarrollo de aplicaciones > CGI)"
}

Paso "2/6 PHP como FastCGI"
$fast = (& $appcmd list config /section:system.webServer/fastCgi) -join "`n"
if ($fast -notmatch [regex]::Escape($PhpCgi)) {
    & $appcmd set config /section:system.webServer/fastCgi "/+[fullPath='$PhpCgi',maxInstances='8',instanceMaxRequests='10000',activityTimeout='600',requestTimeout='600']" | Out-Null
}
Ok $PhpCgi

Paso "3/6 Pool y sitio '$Sitio' en el puerto $Puerto"
if (-not (& $appcmd list apppool /name:$Sitio)) {
    & $appcmd add apppool /name:$Sitio /managedRuntimeVersion:"" | Out-Null
}
if (& $appcmd list site /name:$Sitio) {
    & $appcmd set vdir "$Sitio/" /physicalPath:$publico | Out-Null
} else {
    & $appcmd add site /name:$Sitio /physicalPath:$publico /bindings:"http/*:${Puerto}:" | Out-Null
}
& $appcmd set app "$Sitio/" /applicationPool:$Sitio | Out-Null
$handlers = (& $appcmd list config $Sitio /section:handlers) -join "`n"
if ($handlers -notmatch "PHP_DOORTRIM") {
    & $appcmd set config $Sitio /section:handlers "/+[name='PHP_DOORTRIM',path='*.php',verb='GET,HEAD,POST,PUT,DELETE,PATCH,OPTIONS',modules='FastCgiModule',scriptProcessor='$PhpCgi',resourceType='Either']" /commit:apphost | Out-Null
}
Ok "http://*:$Puerto  ->  $publico"

Paso "4/6 Carpetas y permisos"
foreach ($d in "storage\logs", "storage\app", "storage\framework\cache\data", "storage\framework\sessions", "storage\framework\views", "bootstrap\cache", "public\uploads\modelos") {
    New-Item -ItemType Directory -Force (Join-Path $backend $d) | Out-Null
}
$cuenta = "IIS AppPool\$Sitio"
foreach ($d in "storage", "bootstrap\cache", "public\uploads") {
    & icacls (Join-Path $backend $d) /grant "${cuenta}:(OI)(CI)M" /T /Q | Out-Null
}
Ok "storage, bootstrap\cache y public\uploads con escritura para $cuenta"

Paso "5/6 Firewall"
$regla = "DOORTRIM DEFECTOS $Puerto"
if (-not (Get-NetFirewallRule -DisplayName $regla -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $regla -Direction Inbound -Protocol TCP -LocalPort $Puerto -Action Allow | Out-Null
}
Ok "entrada TCP $Puerto permitida (las tablets llegan por la red)"

Paso "6/6 Prueba"
& $appcmd start site /site.name:$Sitio | Out-Null
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -UseBasicParsing "http://localhost:$Puerto/api/factory-config/version" -TimeoutSec 60
    Ok "API responde ($($r.StatusCode)): $($r.Content)"
} catch {
    Falla "La API no responde: $($_.Exception.Message). Mira backend\storage\logs y el Visor de eventos."
}
try {
    $r = Invoke-WebRequest -UseBasicParsing "http://localhost:$Puerto/api/eol-door/control-final" -TimeoutSec 60
    Ok "Lee TB-PLC.tblDoorMain: $($r.Content)"
} catch {
    Write-Host "   [OJO] No pude leer tblDoorMain en el .153: revisa DB_*_TBL en backend\.env" -ForegroundColor Yellow
}

Write-Host "`nLISTO. Las tablets buscan solas http://172.17.132.150:$Puerto/api" -ForegroundColor Green
