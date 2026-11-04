@echo off
REM ============================================================================
REM  DOORTRIM DEFECTOS con el backend en ESTA PC. UN CLIC. No necesita a Claude
REM  ni internet.
REM
REM  1) Conecta esta PC a la red de las tablets (ej. TBAR-OFFICE).
REM  2) Doble clic aca y deja la ventana ABIERTA mientras se usa la app.
REM  3) La tablet encuentra sola esta PC en su red (puerto 9001) y la recuerda.
REM     Si no la encuentra: en el login mantener 3 s el titulo "DOORTRIM
REM     DEFECTOS", escribir la IP que muestra esta ventana y GUARDAR.
REM
REM  Tablets por USB tambien andan (127.0.0.1 por "adb reverse"): el tunel lo arma
REM  y rearma solo TUNEL-TABLETS-USB.bat, que se abre minimizado.
REM ============================================================================
title DOORTRIM DEFECTOS - servidor (puerto 9001)
setlocal
set "PHPINI=C:\Users\User\php\php.ini"
cd /d "%~dp0"

echo.
echo  ==========================================================
echo   DOORTRIM DEFECTOS - servidor en esta PC
echo  ==========================================================
echo   IP de esta PC (por si la tablet no la encuentra sola):
for /f "tokens=2 delims=:" %%I in ('ipconfig ^| findstr /C:"IPv4"') do echo      %%I
echo   Si hay varias, es la de la red Wi-Fi de las tablets.
echo  ==========================================================
echo.

REM Ya esta corriendo? (otra ventana de este mismo .bat)
netstat -ano | findstr /R /C:":9001 .*LISTENING" >nul && (
  echo  El servidor YA ESTA CORRIENDO en otra ventana. No hace falta abrirlo de nuevo.
  echo.
  pause
  exit /b 0
)

REM Tablets por USB (si hay): tunel 127.0.0.1:9001, rearmado solo cada 5 s.
start "DOORTRIM - tunel USB" /min "%~dp0TUNEL-TABLETS-USB.bat"

echo  Servidor andando. DEJA ESTA VENTANA ABIERTA: cerrarla apaga la app de las tablets.
echo  (abajo van apareciendo los pedidos de las tablets)
echo.
cd /d "%~dp0backend\public"
php -c "%PHPINI%" -S 0.0.0.0:9001 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
echo.
echo  [ERROR] El servidor se detuvo. Revisa el mensaje de arriba.
pause
