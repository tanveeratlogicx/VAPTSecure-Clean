param(
    [Parameter(Mandatory=$true)]
    [string]$RiskId
)

$SchemaFile = "..\resources\interface_schema_v2.0.json"

if (-not (Test-Path $SchemaFile)) {
    Write-Error "Data file missing at $SchemaFile"
    exit 1
}

Write-Host "Querying $RiskId in Interface Schema..." -ForegroundColor Cyan

try {
    $content = Get-Content $SchemaFile -Raw
    $json = ConvertFrom-Json $content -ErrorAction Stop
    
    $riskData = $json.risk_interfaces.$RiskId
    
    if ($null -eq $riskData) {
        Write-Warning "Risk ID $RiskId not found in schema."
    } else {
        Write-Host "Title: $($riskData.title)" -ForegroundColor Green
        Write-Host "Category: $($riskData.category)"
        Write-Host "Severity: $($riskData.severity.level)"
        Write-Host "Platforms: $($riskData.available_platforms -join ', ')"
        
        Write-Host "`nRaw Data Summary:" -ForegroundColor DarkGray
        $riskData | ConvertTo-Json -Depth 3 | Write-Host
    }

} catch {
    Write-Error "Error parsing schema file: $_"
    exit 1
}
