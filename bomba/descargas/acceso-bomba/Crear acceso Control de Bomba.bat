@echo off
title Control de Bomba - Crear acceso de escritorio
setlocal

set "ICO_ORIGEN=%~dp0bomba.ico"
set "CARPETA_DESTINO=%LOCALAPPDATA%\ControlBomba"
set "ICO=%CARPETA_DESTINO%\bomba.ico"
set "URL=https://aguapotablemexquitic.com/bomba/login.php"
set "EDGE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist "%EDGE%" set "EDGE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if not exist "%ICO_ORIGEN%" (
    echo No se encontro el archivo bomba.ico junto a este .bat.
    echo Copia los dos archivos juntos en la misma carpeta y vuelve a intentar.
    pause
    exit /b 1
)

if not exist "%CARPETA_DESTINO%" mkdir "%CARPETA_DESTINO%"
copy /y "%ICO_ORIGEN%" "%ICO%" >nul

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut('%USERPROFILE%\Desktop\Control de Bomba.lnk');" ^
  "$s.TargetPath = '%EDGE%';" ^
  "$s.Arguments = '--app=%URL%';" ^
  "$s.IconLocation = '%ICO%';" ^
  "$s.Description = 'Control de Bomba - Sistema de Agua Potable';" ^
  "$s.WorkingDirectory = '%ProgramFiles(x86)%\Microsoft\Edge\Application';" ^
  "$s.Save()"

echo.
echo Listo. Ve a tu Escritorio: ya debe aparecer el icono "Control de Bomba".
echo A partir de ahora ya puedes borrar esta carpeta descargada si quieres, el icono quedo guardado aparte.
pause
