param(
    [Parameter(Mandatory=$true)]
    [string]$SchemaFile
)

if (-not (Test-Path $SchemaFile)) {
    Write-Error "Error: File $SchemaFile not found!"
    exit 1
}

Write-Host "Validating JSON syntax for $SchemaFile..." -ForegroundColor Cyan

try {
    $content = Get-Content $SchemaFile -Raw
    $json = ConvertFrom-Json $content -ErrorAction Stop
    Write-Host "[x] Valid JSON syntax." -ForegroundColor Green
    
    $hasControls = $null -ne $json.controls
    $hasEnforcement = $null -ne $json.enforcement
    
    if ($hasControls -and $hasEnforcement) {
        Write-Host "[x] completely verified structure with 'controls' and 'enforcement'." -ForegroundColor Green
    } else {
        Write-Error "Missing 'controls' or 'enforcement' top-level keys."
        exit 1
    }
} catch {
    Write-Error "Invalid JSON syntax: $_"
    exit 1
}
