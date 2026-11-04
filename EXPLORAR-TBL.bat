@echo off
REM ============================================================================
REM  Lee la base TB-L de la app de camaras (.153) SOLO LEYENDO y guarda en
REM  tbl-tablas.txt (al lado de este archivo) las tablas, sus columnas, unas
REM  filas de ejemplo y donde aparece el QR de un panel.
REM
REM  Correrlo con esta PC conectada a la red TBAR-OFFICE (la que llega al .153).
REM  No necesita internet. Despues, con internet, pasarle tbl-tablas.txt a Claude
REM  para armar la consulta de la hora del panel (DOOR_TBL_SQL_PANEL).
REM ============================================================================
title DOORTRIM - leer tablas de TB-L
setlocal
cd /d "%~dp0backend"
echo.
echo  Escanea o escribi el QR de un PANEL que haya pasado por la camara
echo  (ej. 0001;RH;FR;P3A). Enter solo para saltear la busqueda.
set "QR="
set /p "QR=QR del panel: "
echo.
echo  Leyendo TB-L (puede tardar un minuto)...
if defined QR (
  php -c "C:\Users\User\php\php.ini" tbl-explorar.php "%QR%" > "%~dp0tbl-tablas.txt" 2>&1
) else (
  php -c "C:\Users\User\php\php.ini" tbl-explorar.php > "%~dp0tbl-tablas.txt" 2>&1
)
echo.
echo  Listo. Resultado en: %~dp0tbl-tablas.txt
type "%~dp0tbl-tablas.txt" | more
pause
