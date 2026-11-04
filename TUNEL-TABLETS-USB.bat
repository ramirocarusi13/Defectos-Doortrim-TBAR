@echo off
REM ============================================================================
REM  Mantiene el tunel USB de las tablets DOORTRIM al backend local (puerto 9001).
REM  Cada 5 segundos, a toda tablet conectada sin el tunel se lo vuelve a armar
REM  (se pierde al desenchufar o mover el cable). Lo abre INICIAR-DOORTRIM-LOCAL.bat.
REM ============================================================================
title DOORTRIM - tunel USB de las tablets
set "ADB=E:\Android\Sdk\platform-tools\adb.exe"
:loop
for /f "skip=1 tokens=1,2" %%A in ('"%ADB%" devices') do (
  if "%%B"=="device" (
    "%ADB%" -s %%A reverse --list | find "tcp:9001" >nul || (
      "%ADB%" -s %%A reverse tcp:9001 tcp:9001 >nul && echo %time:~0,8%  tunel armado en %%A
    )
  )
)
timeout /t 5 /nobreak >nul
goto loop
