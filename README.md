# DOORTRIM DEFECTOS (TBAR)

App de defectos de la línea de **door trims** (panel, arm rest, garnish): Calidad marca el defecto
sobre la foto de la pieza, Producción la retrabaja o la cambia por otra del buffer, y Calidad valida.

| Carpeta | Qué es |
|---|---|
| `backend/` | API Laravel 13 / PHP 8.3 (la misma base de código que EOL Defectos de asientos). Door usa `EolDoorController`, `DoorService` y `DoorQrService`. |
| `eol-door-app/` | App Android (Expo / React Native) de las tablets y el TC21. Ver su `README.md`. |
| `kiosco-zebra/` | Config de Enterprise Home Screen (kiosco) para las tablets Zebra. |

## Cómo lee las piezas
- **QR** con el formato de la app de cámaras: `secuencia|x|SD|posición|mano` (ej. `14343|1|D3H|FR|LH`),
  y también `sec;mano;pos;SD`, armrest por posición y garnish = SD. Ver `backend/app/Services/DoorQrService.php`.
- **Control final**: la tablet de Calidad lee `[TB-PLC].[dbo].[tblDoorControlFinal]` (.153) cada 2 s y abre
  sola la pieza cuando cambia el QR.
- **Solo lectura** sobre las bases de producción (TB-L / TB-PLC en el .153): el backend rechaza cualquier
  escritura antes de mandarla. Door no usa JITMS ni el interlock.
- Todo lo de door se guarda en `EOL_TBAR`.

## Correr en una PC (prueba)
`INICIAR-DOORTRIM-LOCAL.bat`: levanta el backend en el puerto 9001 y muestra la IP de la PC. Las tablets la
encuentran solas en su red (o por USB). Configurar `backend/.env` a partir de `backend/.env.example`
(EOL_TBAR, y TB-L con `DB_HOST_TBL`, `DB_DATABASE_TBL`, `DB_USERNAME_TBL`, `DB_PASSWORD_TBL`).

## Servidor
- **IIS en el .150 (el que va):** `deploy-iis/PASO-A-PASO-IIS.md` + `deploy-iis/INSTALAR-IIS.ps1`
  (sitio aparte en el puerto 9001; `.env` de ejemplo en `deploy-iis/env.servidor`).
- Docker (alternativa): `PASO-A-PASO-DOOR-SERVIDOR.md`.

## Fotos de los modelos
`php backend/importar-fotos-doortrim.php "<carpeta 737D - Fotos DOOR AR GN>"`: carga las fotos por SD,
posición y mano (Front A primero; LH espejado si falta), agrandadas para que no se vean borrosas.
Las fotos (`backend/public/uploads/modelos`) no van en el repo: se copian aparte al servidor.

## Pruebas
```
php backend/doortrim-qr-local-test.php
php backend/doortrim-local-test.php
php backend/door-local-test.php
```
