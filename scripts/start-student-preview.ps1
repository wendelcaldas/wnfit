$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
$env:APP_ENV = 'local'
$env:APP_URL = 'http://127.0.0.1:8012'
$env:DB_CONNECTION = 'sqlite'
$env:DB_URL = ''
$env:DB_DATABASE = Join-Path (Get-Location) 'storage/app/portal-validation.sqlite'
$env:SESSION_DRIVER = 'file'
$env:SESSION_SECURE_COOKIE = 'false'
$env:SESSION_COOKIE = 'wnfit_student_preview'
if (Test-Path 'bootstrap/cache/config.php') { throw 'Remova o cache de configuração antes de iniciar a demonstração local.' }
if (-not (Test-Path $env:DB_DATABASE)) { New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null }
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Falha na migração da demonstração.' }
php artisan db:seed --class=StudentPortalDemoSeeder --force
if ($LASTEXITCODE -ne 0) { throw 'Falha ao preparar os dados de demonstração.' }
php artisan serve --host=127.0.0.1 --port=8012
