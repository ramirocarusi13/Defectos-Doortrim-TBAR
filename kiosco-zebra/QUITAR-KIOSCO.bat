@echo off
REM ============================================================
REM  SALE del modo kiosco: empuja la config con kiosco apagado y
REM  restaura el launcher normal de Android.
REM  Requiere la tablet por USB con depuracion autorizada.
REM ============================================================
setlocal
set ADB=%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe
cd /d "%~dp0"

echo [1/3] Empujando config con kiosco APAGADO...
"%ADB%" push enterprisehomescreen-admin.xml /sdcard/enterprisehomescreen.xml >nul
"%ADB%" shell "cp /sdcard/enterprisehomescreen.xml /enterprise/usr/enterprisehomescreen.xml && chmod 644 /enterprise/usr/enterprisehomescreen.xml && rm /sdcard/enterprisehomescreen.xml"

echo [2/3] Reiniciando EHS para que tome la config...
"%ADB%" shell am force-stop com.zebra.mdna.enterprisehomescreen
"%ADB%" shell am start -n com.zebra.mdna.enterprisehomescreen/.HomeScreenActivity

echo [3/3] Restaurando el launcher de Android...
"%ADB%" shell cmd package set-home-activity com.android.launcher3/com.android.launcher3.uioverrides.QuickstepLauncher

echo.
echo LISTO: kiosco desactivado. Para volver a activarlo: INSTALAR-KIOSCO.bat
pause
