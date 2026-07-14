param(
    [string[]]$SpecificFiles = @()
)

# Script de déploiement automatique avec gestion de version SemVer
# Usage: ./deploy.ps1

$Source = "C:\wamp64\www\MEDACAP"
$Destination = "Y:\"
$VersionFile = "$Source\VERSION"
$ExcludeConfigFile = "$Source\deploy.exclude"

function Read-DeployExcludes {
    param([string]$ConfigPath)

    $dirs = [System.Collections.Generic.List[string]]::new()
    $files = [System.Collections.Generic.List[string]]::new()
    $section = "dirs"

    if (Test-Path $ConfigPath) {
        Get-Content $ConfigPath | ForEach-Object {
            $line = $_.Trim()
            if ($line -eq "" -or $line.StartsWith("#")) { return }
            if ($line -match '^\[(dirs|files)\]$') {
                $section = $Matches[1]
                return
            }
            if ($section -eq "files") { $files.Add($line) } else { $dirs.Add($line) }
        }
    }

    if ($dirs.Count -eq 0 -and $files.Count -eq 0) {
        $dirs.AddRange(@(".git", ".vscode", ".gemini", "node_modules"))
        $files.AddRange(@(
            "deploy.ps1", "check_*.php", "debug_*.php", "find_*.php",
            "analyze_*.php", "inspect_*.php", "get_distinct_*.php",
            "list_users_*.php", "search_*.php"
        ))
    }

    return @{
        Dirs  = $dirs.ToArray()
        Files = $files.ToArray()
    }
}

function Test-DeployExcluded {
    param(
        [string]$RelativePath,
        [string[]]$ExcludeDirs,
        [string[]]$ExcludeFiles
    )

    $normalized = $RelativePath.Replace('/', '\').TrimStart('\')
    foreach ($dir in $ExcludeDirs) {
        $d = $dir.Replace('/', '\').TrimStart('\').TrimEnd('\')
        if ($normalized -eq $d -or $normalized.StartsWith("$d\")) { return $true }
    }

    $leaf = Split-Path $normalized -Leaf
    foreach ($pattern in $ExcludeFiles) {
        if ($normalized -ieq $pattern.Replace('/', '\').TrimStart('\')) { return $true }
        if ($leaf -like $pattern) { return $true }
    }
    return $false
}

$deployExcludes = Read-DeployExcludes -ConfigPath $ExcludeConfigFile
$ExcludeDirs = $deployExcludes.Dirs
$ExcludeFiles = $deployExcludes.Files

Write-Host "Exclusions deploy : $($ExcludeDirs.Count) dossier(s), $($ExcludeFiles.Count) fichier(s)/motif(s)" -ForegroundColor Gray
if (Test-Path $ExcludeConfigFile) {
    Write-Host "  -> deploy.exclude" -ForegroundColor DarkGray
}

# 1. Lire la version actuelle
if (Test-Path $VersionFile) {
    $CurrentVersion = Get-Content $VersionFile
}
else {
    $CurrentVersion = "1.0.0"
}

Write-Host "Version actuelle : $CurrentVersion" -ForegroundColor Cyan
Write-Host "Quel type de changement effectuez-vous ?"
Write-Host "1. PATCH (Petites modifs, corrections) -> x.y.[z+1]"
Write-Host "2. MINOR (Nouveau module, changements plusieurs dossiers) -> x.[y+1].0"
Write-Host "3. MAJOR (Changement architecture, DB, Langage) -> [x+1].0.0"
Write-Host "4. AUCUN (Garder la version actuelle)"

$Choice = Read-Host "Entrez votre choix (1-4)"

$v = $CurrentVersion.Split('.')
[int]$major = $v[0]
[int]$minor = $v[1]
[int]$patch = $v[2]

switch ($Choice) {
    "1" { $patch++; Write-Host "Incrémentation PATCH..." }
    "2" { $minor++; $patch = 0; Write-Host "Incrémentation MINOR..." }
    "3" { $major++; $minor = 0; $patch = 0; Write-Host "Incrémentation MAJOR..." }
    "4" { Write-Host "Version inchangée." }
    default { Write-Host "Choix invalide, on garde la version actuelle."; }
}

$NewVersion = "$major.$minor.$patch"
$NewVersion | Out-File -FilePath $VersionFile -Encoding ascii
Write-Host "Nouvelle version : $NewVersion" -ForegroundColor Green

# 2. Synchronisation
if ($SpecificFiles.Count -gt 0) {
    Write-Host "Deploiement cible de $($SpecificFiles.Count) fichiers..." -ForegroundColor Cyan
    $CleanFiles = @()
    foreach ($file in $SpecificFiles) {
        # Normalize file path relative to Source
        $relPath = $file.Replace($Source, "").TrimStart('\').TrimStart('/')
        $srcFile = Join-Path $Source $relPath
        $destFile = Join-Path $Destination $relPath
        
        if (Test-Path $srcFile) {
            if (Test-DeployExcluded -RelativePath $relPath -ExcludeDirs $ExcludeDirs -ExcludeFiles $ExcludeFiles) {
                Write-Host "  [SKIP] Exclu par deploy.exclude : $relPath" -ForegroundColor DarkYellow
                continue
            }
            $destDir = Split-Path $destFile -Parent
            if (!(Test-Path $destDir)) {
                New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            }
            Copy-Item -Path $srcFile -Destination $destFile -Force
            $CleanFiles += $relPath.Replace('\', '/')
            Write-Host "  [OK] Copie de $relPath" -ForegroundColor Green
        } else {
            Write-Host "  [WARN] Fichier non trouve : $relPath" -ForegroundColor Yellow
        }
    }
    # Copie du fichier VERSION
    Copy-Item -Path $VersionFile -Destination "$Destination\VERSION" -Force
    $FilesParam = [string]::Join("|", $CleanFiles)
    $LASTEXITCODE = 0
} else {
    # Récupérer la liste des fichiers modifiés/nouveaux (simulation /L) AVANT la synchro réelle
    Write-Host "Recherche des fichiers modifies..." -ForegroundColor Gray
    $FileList = robocopy $Source $Destination /E /L /Z /XO /MT:32 /NP /NDL /NC /NS /NJH /NJS /XD $ExcludeDirs /XF $ExcludeFiles
    $CleanFiles = @()
    if ($FileList) {
        $CleanFiles = $FileList | Where-Object { $_.Trim() -ne "" } | ForEach-Object { 
            $f = $_.Trim()
            if ($f.StartsWith($Source)) {
                $f = $f.Substring($Source.Length).TrimStart('\')
            }
            $f
        }
    }
    $FilesParam = [string]::Join("|", $CleanFiles)
    
    Write-Host "Demarrage de la synchronisation vers $Destination..." -ForegroundColor Cyan
    robocopy $Source $Destination /E /Z /XO /MT:32 /NP /XD $ExcludeDirs /XF $ExcludeFiles
}

# 3. Notification
if ($LASTEXITCODE -lt 8) {
    Write-Host "Synchronisation reussie !" -ForegroundColor Green
    Write-Host "Envoi de la notification v$NewVersion via le serveur..." -ForegroundColor Yellow
    
    $Body = @{
        v     = $NewVersion
        files = $FilesParam
    }
    
    Invoke-WebRequest -Uri "http://10.68.0.7/medacap/deploy_notifier.php" -Method Post -Body $Body -UseBasicParsing | Out-Null
}
else {
    Write-Host ("Erreur lors de la synchronisation (Code: " + $LASTEXITCODE + ")") -ForegroundColor Red
}