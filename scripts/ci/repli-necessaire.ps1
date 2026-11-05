# CI : décide si le runner .15 doit refaire un travail dont les jobs GitHub (ubuntu-latest) ont échoué.
#   exit 0 = repli : les jobs « $Prefix… » sont en échec sans avoir démarré (aucune étape, aucun runner),
#            cas d'une facturation GitHub bloquée (« not started because … payments / spending limit »).
#   exit 1 = pas de repli : au moins un job a tourné et échoué, c'est une vraie erreur à corriger.
# Lit les jobs de l'exécution en cours (permission actions: read) ou, pour tester, un fichier JSON.
#   .\repli-necessaire.ps1 -Prefix "CI GitHub"
#   .\repli-necessaire.ps1 -Prefix "CI GitHub" -JobsFile jobs.json
param(
    [Parameter(Mandatory = $true)][string]$Prefix,
    [string]$JobsFile = ""
)
$ErrorActionPreference = "Stop"

if ($JobsFile) {
    $jobs = @((Get-Content -Raw -Encoding UTF8 $JobsFile | ConvertFrom-Json).jobs)
} else {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $h = @{ Authorization = "Bearer $env:GH_TOKEN"; Accept = "application/vnd.github+json" }
    $base = "$env:GITHUB_API_URL/repos/$env:GITHUB_REPOSITORY/actions/runs/$env:GITHUB_RUN_ID/attempts/$env:GITHUB_RUN_ATTEMPT/jobs?per_page=100"
    $jobs = @()
    for ($page = 1; $page -le 10; $page++) {
        $r = Invoke-RestMethod -UseBasicParsing -Headers $h -Uri "$base&page=$page"
        $jobs += @($r.jobs)
        if ($jobs.Count -ge $r.total_count -or @($r.jobs).Count -eq 0) { break }
    }
}

$mine = @($jobs | Where-Object { $_.name -like "$Prefix*" })
$failed = @($mine | Where-Object { $_.conclusion -eq "failure" })
foreach ($j in $mine) {
    Write-Host ("{0} : {1}/{2}, {3} étape(s), runner '{4}'" -f $j.name, $j.status, $j.conclusion, @($j.steps).Count, $j.runner_name)
}
if ($failed.Count -eq 0) {
    Write-Host "::error::Aucun job « $Prefix » en échec : rien à refaire sur la .15"
    exit 1
}
$ran = @($failed | Where-Object { @($_.steps).Count -gt 0 -or $_.runner_name })
if ($ran.Count -gt 0) {
    Write-Host "::error::Les jobs GitHub ont tourné et échoué ($(($ran | ForEach-Object { $_.name }) -join ', ')) : erreur à corriger, pas de repli"
    exit 1
}
Write-Host "Repli sur la .15 : $($failed.Count) job(s) GitHub non démarré(s) (runners GitHub indisponibles)"
exit 0
