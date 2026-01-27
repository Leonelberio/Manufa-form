@echo off
chcp 65001 >nul
set "ROOT=%~dp0"
cd /d "%ROOT%"

set "PHP_EXE="

where php >nul 2>&1 && set "PHP_EXE=php" && goto :run
if exist "C:\laragon\bin\php\php.exe" set "PHP_EXE=C:\laragon\bin\php\php.exe" && goto :run
for /d %%D in ("C:\laragon\bin\php\php-*") do if exist "%%D\php.exe" (set "PHP_EXE=%%D\php.exe" && goto :run)
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe" && goto :run
if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe" && goto :run
if exist "%ProgramFiles%\PHP\php.exe" set "PHP_EXE=%ProgramFiles%\PHP\php.exe" && goto :run

:run
if "%PHP_EXE%"=="" (
    echo.
    echo PHP est introuvable.
    echo Installez PHP (XAMPP, Laragon, ou https://windows.php.net/download/)
    echo puis ajoutez le dossier de php.exe au PATH, ou placez ce projet
    echo dans le dossier d'un serveur (ex: C:\xampp\htdocs\form-manufa).
    echo.
    pause
    exit /b 1
)

echo Demarrage du serveur PHP sur http://localhost:8000
echo Dossier : %ROOT%
echo Ouvrez http://localhost:8000 dans le navigateur. Arret : Ctrl+C
echo.
"%PHP_EXE%" -S localhost:8000
pause
