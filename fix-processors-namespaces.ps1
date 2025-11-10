# Fix namespaces for processors moved back to main app

$files = Get-ChildItem -Path "app\Services\Processors\Emails" -Filter "*.php"

foreach ($file in $files) {
    $content = Get-Content -Path $file.FullName -Raw
    
    # Replace plugin namespace with app namespace
    $newContent = $content -replace 'namespace CharlesStOlive\\MsGraphFilament\\Services\\Processors', 'namespace App\Services\Processors\Emails'
    $newContent = $newContent -replace 'use CharlesStOlive\\MsGraphFilament\\', 'use CharlesStOlive\MsGraphFilament\'
    
    if ($content -ne $newContent) {
        Set-Content -Path $file.FullName -Value $newContent -NoNewline
        Write-Host "Fixed namespace: $($file.FullName)"
    }
}

Write-Host "Namespace correction for processors completed!"