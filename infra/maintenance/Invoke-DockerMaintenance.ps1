[CmdletBinding()]
param(
    [ValidatePattern('^\d+(\.\d+)?(KB|MB|GB|TB)$')]
    [string]$MaxBuildCache = '2GB',
    [switch]$SkipDanglingImages
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker CLI is not available. Install Docker Desktop or Docker Engine before running maintenance.'
}

& docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker is not running or the current user cannot access its daemon.'
}

Write-Host "Docker disk usage before maintenance:"
& docker system df

Write-Host "`nLimiting BuildKit cache to $MaxBuildCache..."
& docker buildx prune --force --max-used-space $MaxBuildCache
if ($LASTEXITCODE -ne 0) {
    throw 'BuildKit cache maintenance failed.'
}

if (-not $SkipDanglingImages) {
    Write-Host "`nRemoving dangling images..."
    & docker image prune --force --filter 'dangling=true'
    if ($LASTEXITCODE -ne 0) {
        throw 'Dangling image cleanup failed.'
    }
}

Write-Host "`nDocker disk usage after maintenance:"
& docker system df
