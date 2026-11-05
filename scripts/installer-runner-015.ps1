# MEDACAP 8.4 — installe le runner GitHub Actions auto-hébergé sur la .15 (une fois).
# À lancer dans un PowerShell administrateur, sur la .15, avec le compte qui fait tourner Docker Desktop
# et qui a cloné E:\medacap84 (clé $HOME\.ssh\medacap_deploy). Le runner tourne en service Windows
# sous ce compte : il se connecte à GitHub en sortie (443), aucun port n'est ouvert sur le serveur.
#   .\installer-runner-015.ps1 -Token <jeton>
# Jeton : GitHub > dépôt > Settings > Actions > Runners > New self-hosted runner (valable 1 heure).
param(
    [Parameter(Mandatory = $true)][string]$Token,
    [string]$Account = "$env:USERDOMAIN\$env:USERNAME",
    [string]$RunnerDir = "C:\actions-runner",
    [string]$MedacapDir = "E:\medacap84",
    [string]$Repo = "EagleFox31/MEDACAP2026",
    [string]$Name = "medacap-015"
)
$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { throw "Lancer ce script dans un PowerShell administrateur" }

# Prérequis du déploiement, vérifiés avec le compte courant (celui du service).
if (-not (Test-Path (Join-Path $MedacapDir "deploy-server.ps1"))) { throw "Clone absent : $MedacapDir\deploy-server.ps1" }
if (-not (Test-Path (Join-Path $HOME ".ssh\medacap_deploy"))) { throw "Clé de déploiement absente : $HOME\.ssh\medacap_deploy" }
docker version --format "{{.Server.Version}}" | Out-Null
if ($LASTEXITCODE -ne 0) { throw "docker ne répond pas avec ce compte : démarrer Docker Desktop" }

# Dernière version du runner, avec contrôle de l'empreinte publiée dans la release.
$release = Invoke-RestMethod -UseBasicParsing "https://api.github.com/repos/actions/runner/releases/latest"
$asset = $release.assets | Where-Object { $_.name -match '^actions-runner-win-x64-[\d.]+\.zip$' } | Select-Object -First 1
if (-not $asset) { throw "Archive win-x64 introuvable dans la release $($release.tag_name)" }
New-Item -ItemType Directory -Force $RunnerDir | Out-Null
$zip = Join-Path $RunnerDir $asset.name
Write-Host "Téléchargement de $($asset.name)"
Invoke-WebRequest -UseBasicParsing $asset.browser_download_url -OutFile $zip
if ($release.body -match '<!-- BEGIN SHA win-x64 -->([0-9a-f]{64})<!-- END SHA win-x64 -->') {
    $hash = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLower()
    if ($hash -ne $Matches[1]) { throw "Empreinte SHA256 incorrecte pour $($asset.name)" }
    Write-Host "Empreinte SHA256 vérifiée"
}
Expand-Archive -Path $zip -DestinationPath $RunnerDir -Force
Remove-Item $zip

# Le service tourne sous le compte qui pilote Docker Desktop (groupe docker-users, clé SSH, clone).
$secure = Read-Host -AsSecureString "Mot de passe de $Account (pour le service du runner)"
$plain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))

Push-Location $RunnerDir
try {
    & .\config.cmd --unattended --replace `
        --url "https://github.com/$Repo" --token $Token `
        --name $Name --labels $Name --work "_work" `
        --runasservice --windowslogonaccount $Account --windowslogonpassword $plain
    if ($LASTEXITCODE -ne 0) { throw "config.cmd en échec (code $LASTEXITCODE)" }
} finally {
    $plain = $null
    Pop-Location
}

$svc = Get-Service "actions.runner.*" | Where-Object { $_.Name -like "*$Name*" } | Select-Object -First 1
if (-not $svc -or $svc.Status -ne "Running") { throw "Service du runner absent ou arrêté" }
Write-Host "Runner $Name installé, service $($svc.Name) : $($svc.Status)"
Write-Host "Vérifier sur GitHub > Settings > Actions > Runners que $Name est « Idle »."
