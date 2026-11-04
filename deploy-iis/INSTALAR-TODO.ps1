#Requires -RunAsAdministrator
<#
  DOORTRIM DEFECTOS: instalacion COMPLETA en el servidor (.150), de una.
  PowerShell COMO ADMINISTRADOR, conectado por Escritorio remoto con el disco E
  de la PC compartido:

    powershell -ExecutionPolicy Bypass -File "\\tsclient\E\DATOS\Defectos-Doortrim-TBAR-paquete\INSTALAR-TODO.ps1"

  Hace: copia el paquete, instala PHP 8.5 en C:\php85 (no toca C:\php), baja el
  repo de GitHub a C:\DoortrimDefectos, pone vendor y .env, prueba EOL_TBAR y
  TB-PLC, genera claves, crea las tablas (pregunta antes), arma el sitio IIS en el
  9001, carga las fotos y verifica. Se puede volver a correr: salta lo que ya esta.
#>
param(
    [string]$Paquete = "\\tsclient\E\DATOS\Defectos-Doortrim-TBAR-paquete",
    [string]$Repo = "C:\DoortrimDefectos",
    [string]$PhpDir = "C:\php85",
    [string]$Git = "https://github.com/ramirocarusi13/Defectos-Doortrim-TBAR.git"
)
$ErrorActionPreference = "Stop"
$local = "C:\Defectos-Doortrim-TBAR-paquete"
$backend = Join-Path $Repo "backend"
$php = Join-Path $PhpDir "php.exe"

function Paso([string]$t) { Write-Host "`n== $t" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "   OK  $t" -ForegroundColor Green }
function Ojo([string]$t) { Write-Host "   OJO $t" -ForegroundColor Yellow }
function Falla([string]$t) { Write-Host "`n[ERROR] $t" -ForegroundColor Red; Read-Host "Enter para salir"; exit 1 }
function Php([string[]]$a) {
    Push-Location $backend
    try { & $php @a; if ($LASTEXITCODE -ne 0) { throw "php $($a -join ' ') termino con error $LASTEXITCODE" } }
    finally { Pop-Location }
}

Paso "1/9 Paquete"
New-Item -ItemType Directory -Force $local | Out-Null
foreach ($f in "php85.zip", "vendor.zip", "fotos-737D-door.zip", "backend.env", "DOORTRIM-DEFECTOS.apk") {
    $src = Join-Path $Paquete $f
    $dst = Join-Path $local $f
    if (Test-Path $src) { Copy-Item $src $dst -Force; Ok $f }
    elseif (Test-Path $dst) { Ok "$f (ya estaba)" }
    elseif ($f -ne "DOORTRIM-DEFECTOS.apk") { Falla "No encuentro $f ni en $Paquete ni en $local. Compartiste el disco E en el Escritorio remoto?" }
}

Paso "2/9 PHP 8.5 en $PhpDir (el PHP de C:\php queda como esta)"
if (-not (Test-Path $php)) {
    Expand-Archive (Join-Path $local "php85.zip") -DestinationPath (Split-Path $PhpDir) -Force
}
try { $v = & $php -r "echo PHP_VERSION;" 2>&1 } catch { $v = $_.Exception.Message }
if ($LASTEXITCODE -ne 0 -or "$v" -notmatch "^8\.") {
    Falla "PHP no arranca ($v). Instala 'Visual C++ Redistributable 2015-2022 x64' de Microsoft y volve a correr esto."
}
$mods = & $php -m
foreach ($m in "sqlsrv", "pdo_sqlsrv", "openssl", "mbstring", "gd", "zip", "curl", "fileinfo", "sodium") {
    if (-not ($mods -contains $m)) { Falla "Al PHP de $PhpDir le falta '$m'." }
}
Ok "PHP $v con sqlsrv"

Paso "3/9 Codigo desde GitHub en $Repo"
if (Test-Path (Join-Path $Repo ".git")) {
    git -C $Repo pull --ff-only
    if ($LASTEXITCODE -ne 0) { Falla "git pull fallo (hay cambios locales en $Repo?)." }
    Ok "actualizado"
} else {
    git clone $Git $Repo
    if ($LASTEXITCODE -ne 0) { Falla "git clone fallo." }
    Ok "clonado"
}

Paso "4/9 Librerias (vendor) y .env"
if (-not (Test-Path (Join-Path $backend "vendor\autoload.php"))) {
    Expand-Archive (Join-Path $local "vendor.zip") -DestinationPath $backend -Force
}
Ok "vendor"
$envFile = Join-Path $backend ".env"
if (-not (Test-Path $envFile)) {
    $envPaquete = Join-Path $local "backend.env"
    if (-not (Test-Path $envPaquete)) { Falla "No hay backend.env en el paquete." }
    Copy-Item $envPaquete $envFile
    Ok ".env copiado del paquete"
} else {
    Ok ".env ya existia (no lo piso)"
}
# El .env con claves no queda tirado en el paquete local
Remove-Item (Join-Path $local "backend.env") -Force -ErrorAction SilentlyContinue
foreach ($d in "storage\logs", "storage\app", "storage\framework\cache\data", "storage\framework\sessions", "storage\framework\views", "bootstrap\cache", "public\uploads\modelos") {
    New-Item -ItemType Directory -Force (Join-Path $backend $d) | Out-Null
}

Paso "5/9 Conexion a las bases"
$prueba = Join-Path $env:TEMP "doortrim-probar-db.php"
@'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$mal = 0;
try { DB::connection('sqlsrv')->selectOne('SELECT 1 AS ok'); echo "   OK  EOL_TBAR (" . config('database.connections.sqlsrv.host') . ")\n"; }
catch (Throwable $e) { $mal++; echo "   ERROR EOL_TBAR: " . substr($e->getMessage(), 0, 220) . "\n"; }
try { $f = DB::connection('tbl')->selectOne("SELECT TOP 1 id, QR FROM " . config('door.control_final_tabla') . " WITH (NOLOCK) ORDER BY id DESC"); echo "   OK  TB-PLC tblDoorMain (ultimo QR: " . ($f->QR ?? 'vacia') . ")\n"; }
catch (Throwable $e) { $mal++; echo "   ERROR TB-L/TB-PLC: " . substr($e->getMessage(), 0, 220) . "\n"; }
exit($mal ? 1 : 0);
'@ | Set-Content -Path (Join-Path $backend "probar-db.tmp.php") -Encoding ASCII
Push-Location $backend
$salida = & $php "probar-db.tmp.php"
$dbOk = $LASTEXITCODE -eq 0
$salida | ForEach-Object { Write-Host $_ }
# Login de EOL_TBAR inexistente: se puede crear aca con el usuario de Windows
# (admin del SQL) corriendo crear-login-eol_app.sql del paquete.
$sqlLogin = Join-Path $Paquete "crear-login-eol_app.sql"
if (-not $dbOk -and ($salida -join "`n") -match "Login failed for user 'eol_app'" -and (Test-Path $sqlLogin)) {
    $sqlcmd = Get-Command sqlcmd -ErrorAction SilentlyContinue
    if (-not $sqlcmd) {
        Ojo "No hay sqlcmd: abri $sqlLogin en SSMS (conectado al .150) y ejecutalo con F5."
    } else {
        $r = Read-Host "   El usuario eol_app no existe en el SQL del .150. Crearlo ahora con tu usuario de Windows? (S/N)"
        if ($r -match "^[sS]") {
            & sqlcmd -S "172.17.132.150" -E -C -b -i $sqlLogin
            if ($LASTEXITCODE -ne 0) { & sqlcmd -S "172.17.132.150" -E -b -i $sqlLogin }
            $salida = & $php "probar-db.tmp.php"
            $dbOk = $LASTEXITCODE -eq 0
            $salida | ForEach-Object { Write-Host $_ }
        }
    }
}
Pop-Location
Remove-Item (Join-Path $backend "probar-db.tmp.php") -Force
if (-not $dbOk) { Falla "No conecta a alguna base. Revisa usuario/clave en $envFile (notepad) y volve a correr esto." }

Paso "6/9 Claves de la app"
$envTxt = Get-Content $envFile -Raw
if ($envTxt -notmatch "(?m)^APP_KEY=base64:") { Php @("artisan", "key:generate", "--force"); Ok "APP_KEY generada" } else { Ok "APP_KEY ya estaba" }
if (-not (Test-Path (Join-Path $backend "storage\oauth-private.key"))) { Php @("artisan", "passport:keys"); Ok "claves de login generadas" } else { Ok "claves de login ya estaban" }
Php @("artisan", "config:clear") | Out-Null

Paso "7/9 Tablas de door en EOL_TBAR"
Push-Location $backend
$estado = & $php artisan migrate:status 2>&1
Pop-Location
# EOL_TBAR del .150 se armo desde un dacpac: tiene las tablas pero no el registro de
# migraciones viejas del monolito (users, racks, telescope...). Esas NO se corren:
# solo las pendientes de las tablas eol_* (defectos / door), una por una.
$pendientes = @($estado | Select-String -Pattern "^\s*(\S+)\s.*Pending" | ForEach-Object { $_.Matches[0].Groups[1].Value })
$deDoor = @($pendientes | Where-Object { $_ -match "eol_" })
$otras = @($pendientes | Where-Object { $_ -notmatch "eol_" })
if ($otras.Count) { Write-Host "   (se ignoran $($otras.Count) migraciones viejas del monolito que no son de door)" }
if (-not $deDoor.Count) {
    Ok "las tablas de door ya estan al dia"
} else {
    Write-Host "   Pendientes de door (solo agregan columnas/tablas, no borran nada):"
    $deDoor | ForEach-Object { Write-Host "     $_" }
    $r = Read-Host "   Aplicarlas en EOL_TBAR ahora? (S/N)"
    if ($r -match "^[sS]") {
        foreach ($mig in $deDoor) { Php @("artisan", "migrate", "--force", "--path=database/migrations/$mig.php") }
        Ok "tablas de door al dia"
    } else {
        Ojo "no se aplicaron: algunas pantallas pueden fallar hasta aplicarlas"
    }
}

Paso "8/9 Sitio IIS en el puerto 9001"
& (Join-Path $Repo "deploy-iis\INSTALAR-IIS.ps1") -Repo $Repo -PhpCgi (Join-Path $PhpDir "php-cgi.exe")

Paso "9/9 Fotos de los modelos"
$fotos = "C:\fotos-door"
if (-not (Test-Path (Join-Path $fotos "Door"))) {
    Expand-Archive (Join-Path $local "fotos-737D-door.zip") -DestinationPath $fotos -Force
}
Push-Location $backend
& $php -d memory_limit=1G importar-fotos-doortrim.php $fotos | Select-Object -Last 2
Pop-Location
if (Test-Path (Join-Path $local "DOORTRIM-DEFECTOS.apk")) {
    Copy-Item (Join-Path $local "DOORTRIM-DEFECTOS.apk") (Join-Path $backend "public\doortrim.apk") -Force
    Ok "APK para tablets nuevas: http://172.17.132.150:9001/doortrim.apk"
}

Write-Host "`n=================================================================" -ForegroundColor Green
Write-Host " LISTO. DOORTRIM DEFECTOS en http://172.17.132.150:9001" -ForegroundColor Green
Write-Host " Las tablets (en TBAR-OFFICE) se conectan solas al .150." -ForegroundColor Green
Write-Host " Para actualizar despues: correr este mismo script (hace git pull)." -ForegroundColor Green
Write-Host "=================================================================" -ForegroundColor Green
Read-Host "Enter para cerrar"
