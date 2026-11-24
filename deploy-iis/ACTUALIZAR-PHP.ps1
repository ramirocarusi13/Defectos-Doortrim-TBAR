#Requires -RunAsAdministrator
<#
  DOORTRIM DEFECTOS: pasa el sitio de IIS de PHP 8.5.6 (C:\php85) a PHP 8.5.13
  (C:\php8513) sin cortar las tablets. PowerShell COMO ADMINISTRADOR en el .150:

    powershell -ExecutionPolicy Bypass -File "\\tsclient\E\DATOS\Defectos-Doortrim-TBAR-paquete\ACTUALIZAR-PHP.ps1"

  Instala la 8.5.13 AL LADO de la actual, la prueba con la app (modulos y bases)
  y recien ahi cambia el sitio de IIS. El cambio tarda segundos. Si la API no
  responde con la nueva, vuelve sola a la anterior. C:\php85 queda intacto.

  Lo mas simple (por AnyDesk o lo que sea): el script y php8513.zip vienen en el
  repo, en deploy-iis. En PowerShell como administrador, una sola linea:
    git -C C:\DoortrimDefectos pull --ff-only; powershell -ExecutionPolicy Bypass -File C:\DoortrimDefectos\deploy-iis\ACTUALIZAR-PHP.ps1

  Sin git: copiar este script y php8513.zip a una misma
  carpeta del servidor (ej. C:\Defectos-Doortrim-TBAR-paquete) y correrlo desde ahi:
    powershell -ExecutionPolicy Bypass -File "C:\Defectos-Doortrim-TBAR-paquete\ACTUALIZAR-PHP.ps1"

  Para volver a la version anterior a mano:
    powershell -ExecutionPolicy Bypass -File "...\ACTUALIZAR-PHP.ps1" -Volver
#>
param(
    # Carpeta donde esta php8513.zip: por defecto, la misma del script.
    [string]$Paquete = $PSScriptRoot,
    [string]$Repo = "C:\DoortrimDefectos",
    [string]$Nuevo = "C:\php8513",
    [string]$Viejo = "C:\php85",
    [string]$Version = "8.5.13",
    [string]$Sitio = "DoortrimDefectos",
    [int]$Puerto = 9001,
    [switch]$Volver
)
$ErrorActionPreference = "Stop"
$local = "C:\Defectos-Doortrim-TBAR-paquete"
$backend = Join-Path $Repo "backend"
$appcmd = Join-Path $env:windir "System32\inetsrv\appcmd.exe"
$zip = "php8513.zip"

function Paso([string]$t) { Write-Host "`n== $t" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "   OK  $t" -ForegroundColor Green }
function Ojo([string]$t) { Write-Host "   OJO $t" -ForegroundColor Yellow }
function Falla([string]$t) { Write-Host "`n[ERROR] $t" -ForegroundColor Red; Read-Host "Enter para salir"; exit 1 }

# PHP que usa hoy el sitio (scriptProcessor del handler PHP_DOORTRIM)
function PhpDelSitio {
    $h = (& $appcmd list config $Sitio /section:handlers) -join "`n"
    if ($h -match "name=`"PHP_DOORTRIM`"[^>]*scriptProcessor=`"([^`"]+)`"") { return $Matches[1] }
    if ($h -match "scriptProcessor=`"([^`"]+)`"[^>]*name=`"PHP_DOORTRIM`"") { return $Matches[1] }
    return $null
}

# Apunta el sitio al php-cgi.exe indicado (lo registra en FastCGI si hace falta)
function ApuntarSitio([string]$cgi) {
    $fast = (& $appcmd list config /section:system.webServer/fastCgi) -join "`n"
    if ($fast -notmatch [regex]::Escape($cgi)) {
        & $appcmd set config /section:system.webServer/fastCgi "/+[fullPath='$cgi',maxInstances='8',instanceMaxRequests='10000',activityTimeout='600',requestTimeout='600']" | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "no se pudo registrar $cgi en FastCGI" }
    }
    & $appcmd set config $Sitio /section:handlers "/[name='PHP_DOORTRIM'].scriptProcessor:$cgi" /commit:apphost | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "no se pudo cambiar el handler PHP_DOORTRIM a $cgi" }
}

# La API responde y con que version de PHP
function ProbarApi {
    $r = Invoke-WebRequest -UseBasicParsing "http://localhost:$Puerto/api/factory-config/version" -TimeoutSec 60
    $c = Invoke-WebRequest -UseBasicParsing "http://localhost:$Puerto/api/eol-door/control-final?puesto=1" -TimeoutSec 60
    return @{ php = "$($r.Headers['X-Powered-By'])"; api = $r.StatusCode; tbl = $c.Content }
}

if (-not (Test-Path $appcmd)) { Falla "IIS no esta instalado en este equipo (no encuentro appcmd.exe)." }
$actual = PhpDelSitio
if (-not $actual) { Falla "No encuentro el sitio '$Sitio' con el handler PHP_DOORTRIM. Corre primero INSTALAR-TODO.ps1." }

# ---------------------------------------------------------------- volver atras
if ($Volver) {
    Paso "Volver el sitio a $Viejo"
    $cgiViejo = Join-Path $Viejo "php-cgi.exe"
    if (-not (Test-Path $cgiViejo)) { Falla "No encuentro $cgiViejo." }
    ApuntarSitio $cgiViejo
    Start-Sleep -Seconds 3
    try { $p = ProbarApi; Ok "API responde con $($p.php)" } catch { Falla "La API no responde: $($_.Exception.Message)" }
    Write-Host "`nLISTO: el sitio volvio a $Viejo." -ForegroundColor Green
    Read-Host "Enter para cerrar"
    exit 0
}

Paso "1/5 PHP $Version del paquete"
New-Item -ItemType Directory -Force $local | Out-Null
$src = Join-Path $Paquete $zip
$dst = Join-Path $local $zip
$igual = (Test-Path $src) -and (Test-Path $dst) -and ((Get-Item $src).Length -eq (Get-Item $dst).Length)
if ($igual) { Ok "$zip (ya estaba)" }
elseif (Test-Path $src) {
    $copiado = $false
    foreach ($intento in 1..3) {
        try { Copy-Item $src $dst -Force -ErrorAction Stop; $copiado = $true; break }
        catch { Ojo "copiando $zip se corto (intento $intento): $($_.Exception.Message)"; Start-Sleep -Seconds 3 }
    }
    if (-not $copiado) { Falla "No se pudo copiar $zip desde $Paquete. Volve a correr el script." }
    Ok $zip
}
elseif (-not (Test-Path $dst)) { Falla "No encuentro $zip ni en $Paquete ni en $local. Copialo a la misma carpeta que este script." }

Paso "2/5 Instalar en $Nuevo (al lado de $Viejo, que no se toca)"
$php = Join-Path $Nuevo "php.exe"
$cgi = Join-Path $Nuevo "php-cgi.exe"
if (-not (Test-Path $php)) {
    Expand-Archive $dst -DestinationPath (Split-Path $Nuevo) -Force
}
try { $v = & $php -r "echo PHP_VERSION;" 2>&1 } catch { $v = $_.Exception.Message }
if ("$v" -ne $Version) { Falla "El PHP de $Nuevo no arranca o no es $Version ($v). Si dice que falta una DLL, instala 'Visual C++ Redistributable 2015-2022 x64' de Microsoft." }
$mods = & $php -m
foreach ($m in "sqlsrv", "pdo_sqlsrv", "openssl", "mbstring", "gd", "zip", "curl", "fileinfo", "sodium", "intl") {
    if (-not ($mods -contains $m)) { Falla "Al PHP de $Nuevo le falta '$m'. No se cambio nada." }
}
Ok "PHP $v con sqlsrv y todos los modulos"

Paso "3/5 Probar la app con PHP $Version (antes de cambiar nada)"
$prueba = Join-Path $backend "probar-php.tmp.php"
@'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$mal = 0;
try { DB::connection('sqlsrv')->selectOne('SELECT 1 AS ok'); echo "   OK  EOL_TBAR\n"; }
catch (Throwable $e) { $mal++; echo "   ERROR EOL_TBAR: " . substr($e->getMessage(), 0, 220) . "\n"; }
try { $f = DB::connection('tbl')->selectOne("SELECT TOP 1 id, QR FROM " . config('door.control_final_tabla') . " WITH (NOLOCK) ORDER BY id DESC"); echo "   OK  TB-PLC (ultimo QR: " . ($f->QR ?? 'vacia') . ")\n"; }
catch (Throwable $e) { $mal++; echo "   ERROR TB-PLC: " . substr($e->getMessage(), 0, 220) . "\n"; }
try { $q = App\Services\DoorQrService::interpretar('20274|5|D3H|RR|RH'); echo "   OK  lectura de QR (" . ($q['mostrar'] ?? '-') . ")\n"; }
catch (Throwable $e) { $mal++; echo "   ERROR lectura de QR: " . substr($e->getMessage(), 0, 220) . "\n"; }
exit($mal ? 1 : 0);
'@ | Set-Content -Path $prueba -Encoding ASCII
Push-Location $backend
$salida = & $php "probar-php.tmp.php"
$okApp = $LASTEXITCODE -eq 0
Pop-Location
Remove-Item $prueba -Force -ErrorAction SilentlyContinue
$salida | ForEach-Object { Write-Host $_ }
if (-not $okApp) { Falla "La app no anda con PHP $Version. No se cambio nada: el sitio sigue con $actual." }

Paso "4/5 Cambiar el sitio de IIS a PHP $Version"
Write-Host "   Hoy el sitio usa: $actual"
if ($actual -ieq $cgi) {
    Ok "ya estaba en $cgi"
} else {
    $r = Read-Host "   Cambiar ahora a $cgi? Las tablets pueden tardar unos segundos en responder (S/N)"
    if ("$r".Trim() -notmatch "^[sS]") { Write-Host "`nNo se cambio nada. El sitio sigue con $actual."; Read-Host "Enter para cerrar"; exit 0 }
    try { ApuntarSitio $cgi } catch { Falla "No se pudo cambiar el sitio: $($_.Exception.Message). Sigue con $actual." }
    Ok "sitio apuntado a $cgi"
}

Paso "5/5 Verificar"
Start-Sleep -Seconds 3
$bien = $false
try {
    $p = ProbarApi
    Write-Host "   API $($p.api) con $($p.php)"
    Write-Host "   Tablets (puesto 1): $($p.tbl)"
    $bien = $p.php -match [regex]::Escape($Version)
} catch {
    Ojo "la API no responde: $($_.Exception.Message)"
}
if (-not $bien) {
    Ojo "algo fallo con PHP ${Version}: vuelvo el sitio a $actual"
    try { ApuntarSitio $actual; Start-Sleep -Seconds 3; $p = ProbarApi; Ok "volvio a $($p.php)" } catch { Write-Host "   [ERROR] tampoco responde con $actual : $($_.Exception.Message)" -ForegroundColor Red }
    Falla "No se pudo pasar a PHP $Version. El sitio quedo como estaba. Mira C:\DoortrimDefectos\backend\storage\logs y el Visor de eventos."
}

Write-Host "`n=================================================================" -ForegroundColor Green
Write-Host " LISTO. DOORTRIM DEFECTOS corre con PHP $Version ($Nuevo)." -ForegroundColor Green
Write-Host " $Viejo queda como respaldo. Para volver: este script con -Volver." -ForegroundColor Green
Write-Host "=================================================================" -ForegroundColor Green
Read-Host "Enter para cerrar"
