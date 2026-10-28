# Initialise exclusivement la base academy du conteneur MongoDB de MEDACAP 8.4.
# Usage : .\scripts\init-medacap84-copy.ps1 -Archive "C:\\chemin\\archive-8.0.archive.gz"
# Prerequis : archive mongodump 8.0 autorisee, Docker Compose V2 demarre.
# AUCUN acces au MongoDB WAMP, a un serveur distant ou aux identifiants de prod.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$Archive
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$source = (Resolve-Path -LiteralPath $Archive -ErrorAction Stop).Path
if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
    throw "Archive introuvable : $source"
}
if ($source -notmatch '\.archive\.gz$') {
    throw "Fournir une archive mongodump compressee (*.archive.gz), deja validee pour MongoDB 8.0."
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$remoteArchive = '/tmp/medacap84-' + [guid]::NewGuid().ToString('N') + '.archive.gz'

$serviceId = ""
Push-Location -LiteralPath $repoRoot
try {
    # Refuser toute operation si la cible n'est pas le service Mongo local attendu.
    $composeFile = Join-Path $repoRoot 'docker-compose.yml'
    if (-not (Test-Path -LiteralPath $composeFile)) {
        throw "docker-compose.yml introuvable."
    }
    $ids = @(docker compose ps -q mongo)
    $composeExit = $LASTEXITCODE
    $serviceId = if ($ids.Count -gt 0) { [string]$ids[0] } else { '' }
    if ($composeExit -ne 0 -or [string]::IsNullOrWhiteSpace($serviceId)) {
        throw "Le conteneur Mongo MEDACAP 8.4 n'est pas demarre. Executer d'abord docker compose up -d --build."
    }

    # L'application doit etre isolee de la prod : seul le port 27018 de
    # l'hote loopback est publie vers le 27017 du conteneur.
    $published = (docker port $serviceId '27017/tcp' | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $published -notmatch '(?m)^127\.0\.0\.1:27018$') {
        throw "Port Mongo inattendu : $published. Restauration annulee."
    }

    $countOutput = @(docker compose exec -T mongo mongosh academy --quiet --eval 'db.getCollectionNames().length')
    if ($LASTEXITCODE -ne 0) {
        throw "Impossible de lire la base academy du conteneur."
    }
    $count = ($countOutput | Where-Object { $_ -match '^\s*\d+\s*$' } | Select-Object -Last 1)
    if ($null -eq $count -or [int]$count -ne 0) {
        throw "La base academy n'est pas vide ou ne peut pas etre verifiee. Aucune restauration effectuee."
    }

    Write-Host "Restauration sur le service Docker local mongo uniquement..."
    docker compose cp $source "mongo:$remoteArchive"
    if ($LASTEXITCODE -ne 0) { throw "Copie de l'archive vers le conteneur echouee." }

    # Ne jamais ajouter --drop ici. Une erreur laisse la copie a examiner.
    docker compose exec -T mongo mongorestore --gzip "--archive=$remoteArchive" '--nsInclude=academy.*'
    if ($LASTEXITCODE -ne 0) {
        throw "mongorestore a echoue. Examiner la base de test avant toute nouvelle tentative."
    }

    Write-Host "Nombre de documents users dans la copie academy :"
    docker compose exec -T mongo mongosh academy --quiet --eval 'db.users.countDocuments({})'
    if ($LASTEXITCODE -ne 0) { throw "Restauration terminee, mais verification users echouee." }
    Write-Host "Controle supplementaire requis : comparer les collections et rejouer PLAN.md."
}
finally {
    if (-not [string]::IsNullOrWhiteSpace($serviceId)) {
        docker compose exec -T mongo rm -f $remoteArchive 2>$null | Out-Null
    }
    Pop-Location
}
