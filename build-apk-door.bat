@echo off
REM ============================================================================
REM  Compila el APK de eol-door apuntando a la IP de ESTA red (auto-detectada).
REM
REM  Uso:
REM    - Doble clic en este archivo  -> detecta la IP solo y compila.
REM    - Forzar una IP:   build-apk-door.bat 192.168.1.50
REM    - Forzar IP+puerto: build-apk-door.bat 192.168.1.50 9001
REM
REM  Toda la logica esta en build-apk.ps1 (al lado de este archivo), con -App door.
REM ============================================================================

setlocal
set "IP=%~1"
set "PORT=%~2"

set "ARGS=-App door"
if not "%IP%"=="" set "ARGS=%ARGS% -Ip %IP%"
if not "%PORT%"=="" set "ARGS=%ARGS% -Port %PORT%"

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build-apk.ps1" %ARGS%
