# DOORTRIM DEFECTOS en el servidor .150 (IIS)

El backend corre en IIS del **172.17.132.150**, sitio aparte en el **puerto 9001** (no toca nada de
asientos). Las tablets ya buscan solas `http://172.17.132.150:9001/api`: no hay que reinstalar el APK.

- Guarda en **EOL_TBAR** (SQL del .150, la misma base de asientos, tablas `eol_defectos` con
  `flujo='DOOR'`, `eol_doors`, `eol_piezas`, eventos).
- Lee el último QR de **[TB-PLC].[dbo].[tblDoorMain]** y las fotos/QR de **TB-L** en el .153,
  **solo lectura** (el backend rechaza cualquier escritura antes de mandarla).
- No usa JITMS ni el interlock.

## Qué hace falta

| Qué | Dónde |
|---|---|
| Repo | `git clone https://github.com/ramirocarusi13/Defectos-Doortrim-TBAR.git` (ej. en `C:\DoortrimDefectos`) |
| **Paquete** (no va en el repo) | `vendor.zip`, `fotos-737D-door.zip`, `DOORTRIM-DEFECTOS.apk` |
| PHP 8.3 o más nuevo, **NTS x64** | descomprimido en `C:\PHP` |
| Drivers SQL Server para PHP | `php_sqlsrv_8x_nts_x64.dll` y `php_pdo_sqlsrv_8x_nts_x64.dll` (Microsoft, versión 5.13) en `C:\PHP\ext` |
| ODBC Driver 17 o 18 for SQL Server | instalador de Microsoft |
| IIS con **CGI** y el módulo **URL Rewrite** | Roles de Windows / instalador de URL Rewrite |

## 1 — PHP

En `C:\PHP\php.ini` (copiar de `php.ini-production`):

```ini
extension_dir = "C:\PHP\ext"
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=zip
extension=sodium
extension=php_sqlsrv_83_nts_x64.dll
extension=php_pdo_sqlsrv_83_nts_x64.dll
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 20M
date.timezone = America/Argentina/Buenos_Aires
```

(cambiar `83` por la versión de PHP instalada). Probar: `C:\PHP\php.exe -m` tiene que listar `sqlsrv` y `pdo_sqlsrv`.

## 2 — Repo, librerías y .env

```bash
cd C:\DoortrimDefectos
```

1. Descomprimir **`vendor.zip`** dentro de `backend\` (queda `backend\vendor\autoload.php`).
2. Copiar `deploy-iis\env.servidor` a **`backend\.env`** y completar las claves (EOL_TBAR y TB-L).
3. Generar la clave de la app y las de login:

```bash
cd backend
```

```bash
C:\PHP\php.exe artisan key:generate --force
```

```bash
C:\PHP\php.exe artisan passport:keys
```

## 3 — Tablas de door en EOL_TBAR

Ver qué falta (solo agregan tablas/columnas de door, no borran nada):

```bash
C:\PHP\php.exe artisan migrate:status
```

```bash
C:\PHP\php.exe artisan migrate --force
```

## 4 — Sitio en IIS

PowerShell **como administrador**, desde la carpeta del repo:

```bash
powershell -ExecutionPolicy Bypass -File deploy-iis\INSTALAR-IIS.ps1
```

Chequea todo, registra PHP, crea el pool y el sitio `DoortrimDefectos` en el 9001, da permisos de
escritura a `storage`, `bootstrap\cache` y `public\uploads`, abre el 9001 en el firewall y prueba la API
y la lectura de `tblDoorMain`. Si PHP no está en `C:\PHP`: `-PhpCgi "D:\php\php-cgi.exe"`.

## 5 — Fotos de los modelos

Descomprimir `fotos-737D-door.zip` (ej. en `C:\fotos-door`) y cargarlas (copia las fotos a
`public\uploads\modelos`, las agranda y arma los modelos de foto por SD, posición y mano en /config):

```bash
C:\PHP\php.exe -d memory_limit=1G importar-fotos-doortrim.php "C:\fotos-door"
```

## 6 — Verificar

```bash
curl http://172.17.132.150:9001/api/factory-config/version
```

```bash
curl -X POST http://172.17.132.150:9001/api/eol-door/qr -H "Content-Type: application/json" -d "{\"qr\":\"14343|1|D3H|FR|LH\"}"
```

El segundo tiene que contestar `"tipo":"PANEL"` y `"codigo":"D3E FR LH"`. Después, una tablet en
TBAR-OFFICE **sin cable**: abre sola el último QR de `tblDoorMain`.

## Tablets

El APK (`DOORTRIM-DEFECTOS.apk`) prueba en este orden: el servidor `.150:9001`, el cable USB y la PC de
prueba. Para una tablet nueva: instalarlo y aplicar el kiosco con `kiosco-zebra\INSTALAR-KIOSCO-DOORTRIM.bat`.

## Si algo falla

| Síntoma | Causa probable |
|---|---|
| 500 en la API | `backend\.env` mal (claves) o falta `artisan key:generate`. Ver `backend\storage\logs`. |
| 404 en `/api/...` | Falta URL Rewrite o el sitio no apunta a `backend\public`. |
| "could not find driver" | Faltan `sqlsrv` / `pdo_sqlsrv` en `php.ini`, o el ODBC Driver. |
| La tablet dice "No se pudo conectar" | Firewall del 9001, o el sitio parado (`Administrador de IIS`). |
| No se abre la pieza sola | No llega al .153: revisar `DB_*_TBL` y que `dx` lea `TB-PLC`. |
