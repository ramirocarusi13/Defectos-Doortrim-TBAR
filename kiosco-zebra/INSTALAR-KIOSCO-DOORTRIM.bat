@echo off
REM ============================================================
REM  Deja la tablet en MODO KIOSCO con DOORTRIM DEFECTOS (la app
REM  de door trims). Requiere EHS instalado (INSTALAR-KIOSCO.bat),
REM  DOORTRIM instalada y la tablet por USB con depuracion autorizada.
REM
REM  Uso:  INSTALAR-KIOSCO-DOORTRIM.bat            (una tablet)
REM        INSTALAR-KIOSCO-DOORTRIM.bat <serie>    (si hay varias)
REM ============================================================
setlocal
set "ADB=E:\Android\Sdk\platform-tools\adb.exe"
if not exist "%ADB%" set "ADB=%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe"
set "SERIE="
if not "%~1"=="" set "SERIE=-s %~1"
cd /d "%~dp0"

echo [1/2] Copiando la config de kiosco DOORTRIM a /enterprise/usr ...
"%ADB%" %SERIE% push enterprisehomescreen-doortrim.xml /sdcard/enterprisehomescreen.xml >nul || (echo [ERROR] No pude conectar con la tablet & pause & exit /b 1)
"%ADB%" %SERIE% shell "cp /sdcard/enterprisehomescreen.xml /enterprise/usr/enterprisehomescreen.xml && chmod 644 /enterprise/usr/enterprisehomescreen.xml && rm /sdcard/enterprisehomescreen.xml"

echo [2/2] Reiniciando EHS...
"%ADB%" %SERIE% shell am force-stop com.zebra.mdna.enterprisehomescreen
"%ADB%" %SERIE% shell cmd package set-home-activity com.zebra.mdna.enterprisehomescreen/.HomeScreenActivity
"%ADB%" %SERIE% shell am start -n com.zebra.mdna.enterprisehomescreen/.HomeScreenActivity

echo.
echo LISTO: la tablet queda en kiosco con DOORTRIM DEFECTOS.
pause
