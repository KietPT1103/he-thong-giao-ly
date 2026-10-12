param(
    [string]$FtpHost = 'melody.maychu.cloud',
    [int]$FtpPort = 21,
    [switch]$SkipBuild,
    [switch]$NonInteractive,
    [switch]$CheckOnly
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if ($FtpHost -notmatch '^[a-zA-Z0-9][a-zA-Z0-9.-]*$' -or $FtpPort -lt 1 -or $FtpPort -gt 65535) {
    throw 'Invalid FTP hostname or port.'
}

# Establish a real TCP connection before building or asking for credentials.
$client = [System.Net.Sockets.TcpClient]::new()
try {
    if (-not $client.ConnectAsync($FtpHost, $FtpPort).Wait(10000)) {
        throw 'FTP TCP timeout. Hosting must permit connections from this runner/computer.'
    }
    Write-Host 'FTP TCP connection succeeded.'
} finally { $client.Dispose() }
if ($CheckOnly) { return }

if (-not $SkipBuild) {
    & npm.cmd ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    & npm.cmd run type-check
    if ($LASTEXITCODE -ne 0) { throw 'Type check failed.' }
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Build failed.' }
}
if (-not (Test-Path 'public/build/manifest.json')) { throw 'Build manifest is missing.' }

$ftpUser = $env:FTP_USERNAME
$ftpPassword = $env:FTP_PASSWORD
if (-not $ftpUser -or -not $ftpPassword) {
    if ($NonInteractive) { throw 'Missing FTP_USERNAME or FTP_PASSWORD secrets.' }
    $credentials = Get-Credential -UserName 'giaoxuca6aa3' -Message 'Enter FTP credentials; they will not be saved.'
    if (-not $credentials) { throw 'FTP login cancelled.' }
    $ftpUser = $credentials.UserName
    $ftpPassword = $credentials.GetNetworkCredential().Password
}
if ($ftpUser -match '[\r\n]' -or $ftpPassword -match '[\r\n]') { throw 'Invalid credential format.' }

$remoteBase = "ftp://${FtpHost}:${FtpPort}/domains/giaoxucairang.com/public_html/build/"
$buildRoot = (Resolve-Path 'public/build').Path
# Send credentials via stdin, rather than command arguments or a disk file.
$curlUser = ($ftpUser + ':' + $ftpPassword).Replace('\', '\\').Replace('"', '\"')
$curlConfig = 'user = "' + $curlUser + '"'
try {
    $files = @(Get-ChildItem -LiteralPath $buildRoot -File -Recurse |
        Where-Object { $_.FullName -ne (Join-Path $buildRoot 'manifest.json') })
    $files += Get-Item (Join-Path $buildRoot 'manifest.json')
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($buildRoot.Length + 1).Replace('\', '/')
        $encoded = (($relative -split '/') | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
        Write-Host "Uploading $relative"
        $curlConfig | & curl.exe --config - --ssl-reqd --ftp-pasv --ftp-create-dirs `
            --connect-timeout 15 --max-time 180 --silent --show-error `
            --upload-file $file.FullName ($remoteBase + $encoded)
        if ($LASTEXITCODE -ne 0) { throw "FTPS upload failed for $relative (curl exit $LASTEXITCODE)." }
    }
    Write-Host 'Frontend uploaded. Open the website and verify its behavior.'
} finally {
    $ftpPassword = $null
    $curlUser = $null
    $curlConfig = $null
    $credentials = $null
}
