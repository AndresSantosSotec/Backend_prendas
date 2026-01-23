# Script para probar el API
Write-Host "=== PROBANDO LOGIN ===" -ForegroundColor Cyan

# 1. Probar login
$loginUrl = "http://127.0.0.1:8000/api/v1/auth/login"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}
$body = @{
    username = "admin"
    password = "admin"
} | ConvertTo-Json

Write-Host "`nEnviando POST a $loginUrl" -ForegroundColor Yellow
Write-Host "Body: $body" -ForegroundColor Gray

try {
    $response = Invoke-WebRequest -Uri $loginUrl -Method POST -Headers $headers -Body $body -UseBasicParsing
    Write-Host "`nStatus Code: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Green
    $response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 10
    
    # Guardar el token si el login fue exitoso
    $responseObj = $response.Content | ConvertFrom-Json
    if ($responseObj.success -and $responseObj.data.token) {
        $token = $responseObj.data.token
        Write-Host "`nToken obtenido: $($token.Substring(0, 20))..." -ForegroundColor Green
        
        # 2. Probar endpoint protegido
        Write-Host "`n=== PROBANDO ENDPOINT PROTEGIDO ===" -ForegroundColor Cyan
        $sucursalesUrl = "http://127.0.0.1:8000/api/v1/sucursales/activas"
        $authHeaders = @{
            "Authorization" = "Bearer $token"
            "Accept" = "application/json"
        }
        
        Write-Host "`nEnviando GET a $sucursalesUrl" -ForegroundColor Yellow
        
        try {
            $sucursalesResponse = Invoke-WebRequest -Uri $sucursalesUrl -Method GET -Headers $authHeaders -UseBasicParsing
            Write-Host "`nStatus Code: $($sucursalesResponse.StatusCode)" -ForegroundColor Green
            Write-Host "Response:" -ForegroundColor Green
            $sucursalesResponse.Content | ConvertFrom-Json | ConvertTo-Json -Depth 10
        } catch {
            Write-Host "`nError en sucursales:" -ForegroundColor Red
            Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
            Write-Host $_.Exception.Message -ForegroundColor Red
        }
    }
} catch {
    Write-Host "`nError en login:" -ForegroundColor Red
    Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    if ($_.ErrorDetails.Message) {
        Write-Host "Detalles:" -ForegroundColor Red
        $_.ErrorDetails.Message
    }
}

Write-Host "`n=== FIN DE PRUEBAS ===" -ForegroundColor Cyan
