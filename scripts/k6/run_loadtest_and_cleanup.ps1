param(
  [Parameter(Mandatory = $true)][string]$PaperId,
  [string]$BaseUrl = "http://10.68.0.7/medacap",
  [string]$MongoUri = "mongodb://10.68.0.7:27017",
  [int]$Count = 100,
  [int]$TargetVus = 100,
  [string]$Warmup = "30s",
  [string]$Steady = "2m",
  [string]$Rampdown = "30s"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$repo = Split-Path -Parent $root
$tag = "lt_" + (Get-Date -Format "yyyyMMdd_HHmmss")
$outDir = Join-Path $PSScriptRoot "out"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$summaryFile = Join-Path $outDir "$tag-summary.json"
$logFile = Join-Path $outDir "$tag-worker.log"
$stateFile = Join-Path $outDir "$tag-state.json"
$snapshotFile = Join-Path $outDir "$tag-snapshot.json"

Write-Host "Tag: $tag"

$stateStart = @{
  tag = $tag
  status = "running"
  message = "Load test running (external runner)"
  started_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
  paperId = $PaperId
  baseUrl = $BaseUrl
  targetVus = $TargetVus
  warmup = $Warmup
  steady = $Steady
  rampdown = $Rampdown
  summaryFile = $summaryFile
  logFile = $logFile
  snapshotFile = $snapshotFile
}
$stateStart | ConvertTo-Json -Depth 5 | Set-Content -Path $stateFile -Encoding UTF8

Write-Host "1) Creating $Count fake candidates..."
$setup = php "$PSScriptRoot\setup_fake_candidates.php" "--paper-id=$PaperId" "--count=$Count" "--mongo-uri=$MongoUri" "--tag=$tag"
$setupObj = $setup | ConvertFrom-Json
$usersCsv = $setupObj.usersCsv
$k6Exit = 1

try {
  Write-Host "2) Running k6 load test..."
  k6 run "$PSScriptRoot\medacap-quiz-load.js" `
    -e "BASE_URL=$BaseUrl" `
    -e "PAPER_ID=$PaperId" `
    -e "TARGET_VUS=$TargetVus" `
    -e "WARMUP_DURATION=$Warmup" `
    -e "STEADY_DURATION=$Steady" `
    -e "RAMPDOWN_DURATION=$Rampdown" `
    -e "USERS_FILE=$usersCsv" `
    --summary-export "$summaryFile" 2>&1 | Tee-Object -FilePath "$logFile" -Append

  $k6Exit = $LASTEXITCODE
  Write-Host "3) Summary:"
  php "$PSScriptRoot\summarize_k6.php" "--summary=$summaryFile"

  Write-Host "3.1) Snapshot avant cleanup..."
  php "$PSScriptRoot\create_snapshot.php" `
    "--tag=$tag" `
    "--mongo-uri=$MongoUri" `
    "--summary=$summaryFile" `
    "--state=$stateFile" `
    "--log=$logFile" `
    "--out=$snapshotFile"
}
finally {
  Write-Host "4) Cleaning users and artifacts in DB..."
  php "$PSScriptRoot\cleanup_fake_candidates.php" "--tag=$tag" "--mongo-uri=$MongoUri"

  $isSuccess = (Test-Path $summaryFile) -and ($k6Exit -eq 0)
  $finalStatus = if ($isSuccess) { "completed" } else { "failed" }
  $finalMessage = if ($isSuccess) { "Load test completed (external runner)" } else { "Load test failed (external runner)" }

  $stateEnd = @{
    tag = $tag
    status = $finalStatus
    message = $finalMessage
    started_at = $stateStart.started_at
    finished_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    paperId = $PaperId
    baseUrl = $BaseUrl
    targetVus = $TargetVus
    warmup = $Warmup
    steady = $Steady
    rampdown = $Rampdown
    summaryFile = $summaryFile
    logFile = $logFile
    snapshotFile = $snapshotFile
    exit_code = $k6Exit
  }
  $stateEnd | ConvertTo-Json -Depth 5 | Set-Content -Path $stateFile -Encoding UTF8
}

Write-Host "Done. Summary file: $summaryFile"
