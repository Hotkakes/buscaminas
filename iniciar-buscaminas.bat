@echo off
title Buscaminas - servidor PHP
cd /d "%~dp0"

rem 1) PHP portable del proyecto, 2) PHP del sistema, 3) descargar PHP 8.5 portable
set "PHP=%~dp0php\php.exe"
if exist "%PHP%" goto :check
set "PHP=php"
where php 1>NUL 2>&1
if not errorlevel 1 goto :check
echo No se encontro PHP. Descargando PHP 8.5 portable desde windows.php.net...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\instalar-php.ps1"
if errorlevel 1 ( pause & exit /b 1 )
set "PHP=%~dp0php\php.exe"

:check
"%PHP%" -r "exit(PHP_VERSION_ID >= 80500 ? 0 : 1);"
if errorlevel 1 (
    echo Se necesita PHP 8.5 o superior. Version encontrada:
    "%PHP%" -v
    "%PHP%" -v > "%~dp0estado-php.txt" 2>&1
    pause
    exit /b 1
)
"%PHP%" -v > "%~dp0estado-php.txt" 2>&1

cd /d "%~dp0source"
echo.
echo Buscaminas en http://localhost:8000  (cierra esta ventana para detenerlo)
start "" http://localhost:8000
"%PHP%" -S localhost:8000 -t public public/index.php
