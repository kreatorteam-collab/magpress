# ============================================================
# push.ps1 — deploy manual al temei Magpress pe toate site-urile
# ============================================================
# RECONSTRUIT după mecanismul documentat în functions.php:
#   - fiecare site expune POST {site}/wp-json/magpress/v1/deploy
#   - auth: header X-Magpress-Signature = HMAC-SHA256(body, deploy_secret)
#   - site-ul descarcă singur zipball-ul de pe GitHub (main) și
#     înlocuiește fișierele temei FĂRĂ maintenance mode
#
# NOTĂ: din v0.9.105 tema se actualizează SINGURĂ (self-update la 5 min +
# instant din wp-admin). Scriptul ăsta e necesar doar pentru bootstrap
# (site-uri care rulează < 0.9.105) sau ca fallback manual.
#
# Utilizare:
#   .\push.ps1                       # citește sites.json de lângă script
#   .\push.ps1 -SitesFile alta.json  # altă listă
#
# Format sites.json (deploy_secret-ul fiecărui site e în wp_options →
# magpress_deploy_secret; istoric, lista era stocată în magpress_sites
# pe app.targetseo.ro, unde site-urile și-o trimit prin ping):
#   [
#     { "url": "https://site1.ro", "secret": "deploy-secret-ul-site-ului" },
#     { "url": "https://site2.ro", "secret": "..." }
#   ]
# ============================================================

param(
    [string]$SitesFile = (Join-Path $PSScriptRoot 'sites.json')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $SitesFile)) {
    Write-Host "EROARE: nu găsesc lista de site-uri: $SitesFile" -ForegroundColor Red
    Write-Host 'Creează un sites.json de forma: [{"url":"https://site.ro","secret":"deploy_secret"}]'
    exit 1
}

$sites = Get-Content $SitesFile -Raw | ConvertFrom-Json
if (-not $sites -or $sites.Count -eq 0) {
    Write-Host "EROARE: $SitesFile e gol sau invalid." -ForegroundColor Red
    exit 1
}

# Body-ul e informativ (endpoint-ul validează doar semnătura peste el)
$body      = '{"ref":"main"}'
$bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($body)

$okCount = 0
$failed  = @()

Write-Host "Deploy Magpress (main) către $($sites.Count) site-uri..." -ForegroundColor Cyan

foreach ($s in $sites) {
    $url = $s.url.TrimEnd('/')
    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($s.secret)
    $sig = ($hmac.ComputeHash($bodyBytes) | ForEach-Object { $_.ToString('x2') }) -join ''

    try {
        $r = Invoke-RestMethod -Method Post `
            -Uri "$url/wp-json/magpress/v1/deploy" `
            -Body $body -ContentType 'application/json' `
            -Headers @{ 'X-Magpress-Signature' = $sig } `
            -TimeoutSec 180
        if ($r.ok) {
            Write-Host ("  OK   {0} -> v{1}" -f $url, $r.version) -ForegroundColor Green
            $okCount++
        } else {
            Write-Host ("  FAIL {0}: {1}" -f $url, $r.error) -ForegroundColor Red
            $failed += $url
        }
    } catch {
        Write-Host ("  FAIL {0}: {1}" -f $url, $_.Exception.Message) -ForegroundColor Red
        $failed += $url
    }
}

Write-Host ""
Write-Host ("Rezultat: {0} reușite, {1} eșuate din {2}." -f $okCount, $failed.Count, $sites.Count) -ForegroundColor Cyan
if ($failed.Count -gt 0) {
    Write-Host "Eșuate:" -ForegroundColor Yellow
    $failed | ForEach-Object { Write-Host "  - $_" }
    exit 1
}
