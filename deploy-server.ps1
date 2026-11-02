# MEDACAP 8.4 — déploiement sur un serveur Windows (Docker Desktop, sans distribution Linux) :
# lance deploy.sh dans un conteneur qui pilote le Docker de la machine. Rien à installer sur le serveur.
#   .\deploy-server.ps1            déploie le dernier commit de DEPLOY_BRANCH
#   .\deploy-server.ps1 <commit>   déploie un commit précis
# Voir docs/deploiement-serveur.md.
param([string]$Commit = "")
# Pas de $ErrorActionPreference = "Stop" : PowerShell 5.1 prendrait la progression de docker (stderr) pour une erreur.

$root = $PSScriptRoot
# compose passe au démon les chemins de ses bind mounts : le dépôt est monté au chemin où la VM
# Docker Desktop voit ce disque (E:\medacap84 -> /run/desktop/mnt/host/e/medacap84).
$vmRoot = "/run/desktop/mnt/host/" + $root.Substring(0, 1).ToLower() + ($root.Substring(2) -replace '\\', '/')
$key = Join-Path $HOME ".ssh\medacap_deploy"
if (-not (Test-Path $key)) { throw "Clé de déploiement absente : $key (voir docs/deploiement-serveur.md)" }

docker build --quiet --tag medacap84-deployer "$root\docker\deployer" | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Construction de l'image medacap84-deployer en échec" }

$runArgs = @("run", "--rm", "--init",
    "-v", "/var/run/docker.sock:/var/run/docker.sock",
    "-v", "${root}:${vmRoot}",
    "-v", "${key}:/deploy-key:ro",
    "-w", $vmRoot,
    "medacap84-deployer")
if ($Commit) { $runArgs += $Commit }
& docker @runArgs
exit $LASTEXITCODE
