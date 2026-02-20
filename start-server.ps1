# Démarrer le serveur PHP pour le formulaire Manufa.
# Exécuter depuis le dossier du projet dans PowerShell.

$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $dir

# Essayer php dans le PATH, ou chemins courants
$php = $null
if (Get-Command php -ErrorAction SilentlyContinue) {
    $php = "php"
} elseif (Test-Path "C:\php\php.exe") {
    $php = "C:\php\php.exe"
} elseif (Test-Path "C:\xampp\php\php.exe") {
    $php = "C:\xampp\php\php.exe"
} elseif (Test-Path "C:\laragon\bin\php\php-*\php.exe") {
    $php = (Get-Item "C:\laragon\bin\php\php-*\php.exe").FullName
}

if (-not $php) {
    Write-Host "PHP non trouvé. Installez PHP ou ajoutez-le au PATH." -ForegroundColor Red
    Write-Host "Sinon, ouvrez index.html directement dans le navigateur (version statique)." -ForegroundColor Yellow
    exit 1
}

Write-Host "Démarrage du serveur sur http://127.0.0.1:8080" -ForegroundColor Green
Write-Host "Ouvrez http://127.0.0.1:8080/index.php dans le navigateur. Ctrl+C pour arrêter." -ForegroundColor Gray
& $php -S 127.0.0.1:8080
