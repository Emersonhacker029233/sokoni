# Dalipay checks against STAGING (docs/delivery/STAGING-dalipay.md, step 9 and Update 2).
#
# Test keys (DALIPAY_ENV=test):
#   powershell -ExecutionPolicy Bypass -File tools\staging-dalipay-test.ps1 -Msisdn +2557XXXXXXXX
#
# LIVE keys, REAL money (DALIPAY_ENV=live, STAGING_PAYMENTS_ALLOW_LIVE=true,
# -Msisdn equal to STAGING_PAYMENTS_LIVE_MSISDN):
#   powershell -ExecutionPolicy Bypass -File tools\staging-dalipay-test.ps1 -Live -Msisdn +2557XXXXXXXX -Amount 1000
#
# Options:
#   -Live                                          one real payment and the duplicate-webhook check only
#                                                  (no decline or timeout check); at most TSh 2,000;
#                                                  you must type YES PAY before anything is sent
#   -Channel mpesa|airtel|mixx|halopesa|azampesa   network shown for the payment (display only:
#                                                  Dalipay takes the network from the number)
#   -Amount 1000                                   fee in TSh (100 to 10000; with -Live at most 2000)
#   -DeclineMsisdn +2557XXXXXXXX                   test mode: a number Dalipay declines, if Dalipay gave you one
#   -SkipTimeout                                   test mode: skip the 2-minute timeout check
#   -Api https://staging-api.sokoni.co.tz/api      only staging or a local server is accepted
#
# It asks for the staging test token (STAGING_PAYMENTS_TEST_TOKEN), or reads
# it from $env:SOKONI_STAGING_TOKEN, and never prints it. Dalipay's keys stay
# on the server and never pass through this script. Every fee is a test order
# marked "STAGING PAYMENT TEST".

param(
    [Parameter(Mandatory = $true)][ValidatePattern('^\+255[67]\d{8}$')][string]$Msisdn,
    [ValidateSet('mpesa', 'airtel', 'mixx', 'halopesa', 'azampesa')][string]$Channel = 'mpesa',
    [ValidateRange(100, 10000)][int]$Amount = 1000,
    [string]$DeclineMsisdn = '',
    [switch]$SkipTimeout,
    [switch]$Live,
    [string]$Api = 'https://staging-api.sokoni.co.tz/api'
)

$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Api = $Api.TrimEnd('/')
if ($Api -notmatch '^https://staging-api\.sokoni\.co\.tz/api$' -and $Api -notmatch '^http://(127\.0\.0\.1|localhost)(:\d+)?/api$') {
    throw "Refusing $Api : this script only runs against staging-api.sokoni.co.tz or a local server, never production."
}

if ($Live) {
    if ($Amount -gt 2000) { throw "Refusing TSh $Amount : a live test is at most TSh 2,000." }
    Write-Host ''
    Write-Host '  LIVE PAYMENT - REAL MONEY' -ForegroundColor Red
    Write-Host ("  Amount: TSh {0:N0}" -f $Amount) -ForegroundColor Red
    Write-Host "  Number: $Msisdn (must be STAGING_PAYMENTS_LIVE_MSISDN on the server)" -ForegroundColor Red
    Write-Host "  Server: $Api" -ForegroundColor Red
    Write-Host '  Approve the prompt on that phone with its mobile money PIN.' -ForegroundColor Red
    Write-Host ''
    $typed = Read-Host 'Type YES PAY to send it, anything else to stop'
    if ($typed -cne 'YES PAY') {
        Write-Host 'Stopped. Nothing was sent.' -ForegroundColor Yellow
        exit 1
    }
}

if ($env:SOKONI_STAGING_TOKEN) {
    $token = $env:SOKONI_STAGING_TOKEN
} else {
    $secure = Read-Host -AsSecureString 'Staging test token (STAGING_PAYMENTS_TEST_TOKEN)'
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
}

$results = New-Object System.Collections.Generic.List[object]
function Add-Result([string]$Check, [string]$Outcome, [string]$Detail) {
    $results.Add([pscustomobject]@{ Check = $Check; Result = $Outcome; Detail = $Detail })
    $colour = switch ($Outcome) { 'PASS' { 'Green' } 'WARN' { 'Yellow' } default { 'Red' } }
    Write-Host ("  {0}: {1}" -f $Outcome, $Detail) -ForegroundColor $colour
}

function Invoke-Api([string]$Method, [string]$Path, $Body = $null, [hashtable]$Headers = @{}, [switch]$NoToken, [string]$RawBody = '') {
    $h = @{ Accept = 'application/json' }
    if (-not $NoToken) { $h['X-Staging-Token'] = $token }
    foreach ($k in $Headers.Keys) { $h[$k] = $Headers[$k] }
    $params = @{ Method = $Method; Uri = "$Api$Path"; Headers = $h; ContentType = 'application/json'; TimeoutSec = 90; UseBasicParsing = $true }
    if ($RawBody) { $params['Body'] = $RawBody } elseif ($null -ne $Body) { $params['Body'] = ($Body | ConvertTo-Json -Compress) }
    try {
        $r = Invoke-WebRequest @params
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Body = ($r.Content | ConvertFrom-Json) }
    } catch {
        $code = 0
        if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
        $text = $null
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            $text = $_.ErrorDetails.Message
        } elseif ($_.Exception.Response) {
            # Windows PowerShell 5.1 sometimes leaves ErrorDetails empty: read the body itself.
            try { $reader = New-Object IO.StreamReader($_.Exception.Response.GetResponseStream()); $text = $reader.ReadToEnd(); $reader.Dispose() } catch { $text = $null }
        }
        $parsed = $null
        if ($text) { try { $parsed = $text | ConvertFrom-Json } catch { $parsed = $null } }
        return [pscustomobject]@{ Status = $code; Body = $parsed }
    }
}

function Show-Payment($p) {
    Write-Host ("  payment #{0}: {1}{2}  fee paid: {3}  live: {4}  Dalipay uuid: {5}  network (Dalipay): {6}  push answered in: {7}  external_id: {8}" -f $p.payment_id, $p.status,
        $(if ($p.failure_reason) { " ($($p.failure_reason))" } else { '' }), $p.fee_paid, $p.live,
        $(if ($p.dalipay_uuid) { $p.dalipay_uuid } else { '-' }), $(if ($p.dalipay_provider) { $p.dalipay_provider } else { '-' }),
        $(if ($p.dalipay_create_seconds) { "$($p.dalipay_create_seconds) s" } else { '-' }), $p.external_id)
}

function Wait-Payment([int]$Id, [int]$Seconds) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        Start-Sleep -Seconds 5
        $r = Invoke-Api 'GET' "/staging/payments/$Id"
        $p = $r.Body.data
        Write-Host ("  {0:HH:mm:ss}  {1} {2}" -f (Get-Date), $p.status, $p.failure_reason)
    } until (($p.status -ne 'pending') -or ((Get-Date) -gt $deadline))
    return $p
}

function Start-TestPayment([hashtable]$Body) {
    $r = Invoke-Api 'POST' '/staging/payments' $Body
    if ($r.Status -eq 404) { throw 'The staging test answered 404: wrong token, STAGING_PAYMENTS_TEST_TOKEN not set (24+ characters), APP_ENV is not staging, or the new code/routes are not live (clear bootstrap/cache).' }
    if ($r.Status -eq 409) { throw "Refused ($($r.Body.code)): $($r.Body.message)" }
    if ($r.Status -ne 201) { throw "Unexpected answer $($r.Status): $($r.Body | ConvertTo-Json -Compress)" }
    return $r.Body.data
}

if ($Live) {
    Write-Host "Dalipay LIVE check on $Api" -ForegroundColor Red
} else {
    Write-Host "Dalipay test-mode checks on $Api" -ForegroundColor Cyan

    # 0. Pre-flight (test mode only)
    Write-Host "`n0. Pre-flight" -ForegroundColor Cyan
    $f = Invoke-Api 'GET' '/features' -NoToken
    if ($f.Status -eq 200) { Add-Result 'new code live' 'PASS' 'GET /api/features answers' } else { Add-Result 'new code live' 'FAIL' "GET /api/features answered $($f.Status)" }
    $d = Invoke-Api 'GET' '/delivery/config' -NoToken
    if ($d.Status -eq 404) { Add-Result 'Bolt off' 'PASS' 'delivery routes answer 404 (BOLT_ENABLED=false)' } else { Add-Result 'Bolt off' 'WARN' "GET /api/delivery/config answered $($d.Status)" }
    $w = Invoke-Api 'POST' '/payments/webhook/dalipay' -NoToken -RawBody '{"event":"collection.success","data":{"uuid":"preflight"}}' -Headers @{ 'X-Signature' = 'bad' }
    if ($w.Status -eq 401 -and $w.Body.message -eq 'Invalid signature.') { Add-Result 'webhook route' 'PASS' 'a bad signature is refused (401), DALIPAY_WEBHOOK_SECRET is set' }
    elseif ($w.Status -eq 401) { Add-Result 'webhook route' 'WARN' "401 '$($w.Body.message)': set DALIPAY_WEBHOOK_SECRET, or Dalipay's webhooks are all refused" }
    else { Add-Result 'webhook route' 'FAIL' "POST /api/payments/webhook/dalipay answered $($w.Status) (expected 401)" }
    $g = Invoke-Api 'POST' '/staging/payments' @{} -NoToken
    if ($g.Status -eq 404) { Add-Result 'test guard' 'PASS' 'without the token the staging test does not exist (404)' } else { Add-Result 'test guard' 'FAIL' "answered $($g.Status) without the token" }
}

# 1. The payment
$what = $(if ($Live) { 'REAL payment' } else { 'test fee' })
Write-Host ("`n1. A {0} of TSh {1:N0} from {2} ({3} shown)" -f $what, $Amount, $Msisdn, $Channel) -ForegroundColor Cyan
$paid = Start-TestPayment @{ msisdn = $Msisdn; channel = $Channel; amount = $Amount; mode = 'normal'; live = [bool]$Live }
Show-Payment $paid
if ($Live -and -not $paid.live) { throw 'The server did not treat this as a live payment; stopping.' }
if ($paid.status -eq 'pending') { $paid = Wait-Payment $paid.payment_id 180 }
Show-Payment $paid
if ($paid.status -eq 'succeeded' -and $paid.fee_paid) { Add-Result $what 'PASS' "paid, Dalipay reference $($paid.dalipay_reference)" }
else { Add-Result $what 'FAIL' "ended as $($paid.status) $($paid.failure_reason)" }
Start-Sleep -Seconds 10
$again = (Invoke-Api 'GET' "/staging/payments/$($paid.payment_id)").Body.data
if ($again.webhooks.Count -gt 0) { Add-Result 'Dalipay webhook' 'PASS' ("received: " + (($again.webhooks | ForEach-Object { "$($_.event_id) -> $($_.result)" }) -join '; ')) }
else { Add-Result 'Dalipay webhook' 'WARN' 'none received yet: check the webhook URL in Dalipay Settings (the status checks confirmed the payment anyway)' }

if (-not $Live) {
    # 2. A declined number (test mode only, and only with a number Dalipay declines)
    Write-Host "`n2. A declined payment" -ForegroundColor Cyan
    if (-not $DeclineMsisdn) {
        # Dalipay takes the network from the number, so a mismatched network neither declines nor tests anything.
        Add-Result 'declined' 'WARN' 'skipped: pass -DeclineMsisdn with a number Dalipay declines in test mode (ask Dalipay for one)'
    } else {
        $declined = Start-TestPayment @{ msisdn = $DeclineMsisdn; channel = $Channel; amount = $Amount; mode = 'normal' }
        if ($declined.status -eq 'pending') { $declined = Wait-Payment $declined.payment_id 180 }
        Show-Payment $declined
        if ($declined.status -eq 'failed') { Add-Result 'declined' 'PASS' "failed as '$($declined.failure_reason)'; the app shows the matching message" }
        elseif ($declined.status -eq 'succeeded') { Add-Result 'declined' 'WARN' 'Dalipay test mode approved that number' }
        else { Add-Result 'declined' 'FAIL' "still $($declined.status) after 3 minutes" }
    }

    # 3. No answer -> timeout (test mode only)
    if ($SkipTimeout) {
        Add-Result 'timeout' 'WARN' 'skipped (-SkipTimeout)'
    } else {
        Write-Host "`n3. No answer from the payment service: waits, then expires (about 2 minutes)" -ForegroundColor Cyan
        $silent = Start-TestPayment @{ msisdn = $Msisdn; channel = $Channel; amount = $Amount; mode = 'no_answer' }
        Show-Payment $silent
        if ($silent.status -eq 'pending' -and -not $silent.dalipay_uuid) { $silent = Wait-Payment $silent.payment_id 240 }
        Show-Payment $silent
        if ($silent.status -eq 'failed' -and $silent.failure_reason -eq 'timeout') { Add-Result 'timeout' 'PASS' 'stayed pending, then expired as timeout; the buyer can try again' }
        else { Add-Result 'timeout' 'FAIL' "ended as $($silent.status) $($silent.failure_reason)" }
    }
}

# 4. Duplicate webhook
Write-Host "`n4. The same signed webhook twice" -ForegroundColor Cyan
if ($paid.status -ne 'succeeded') {
    Add-Result 'duplicate webhook' 'FAIL' 'skipped: step 1 did not end in a payment'
} else {
    $rep = Invoke-Api 'POST' "/staging/payments/$($paid.payment_id)/replay-webhook" @{}
    if ($rep.Status -ne 200) { Add-Result 'duplicate webhook' 'FAIL' "answered $($rep.Status): $($rep.Body.message)" }
    else {
        $first = $rep.Body.data.first; $second = $rep.Body.data.second
        Write-Host ("  first: HTTP {0} duplicate={1}   second: HTTP {2} duplicate={3}   events stored for this payment: {4}" -f $first.http, [bool]$first.duplicate, $second.http, [bool]$second.duplicate, $rep.Body.data.payment.webhooks.Count)
        if ($second.http -eq 200 -and $second.duplicate -and $rep.Body.data.payment.status -eq 'succeeded') { Add-Result 'duplicate webhook' 'PASS' 'answered 200 and processed once; the payment is unchanged' }
        else { Add-Result 'duplicate webhook' 'FAIL' 'the second delivery was not treated as a duplicate' }
    }
}

Write-Host "`nSummary" -ForegroundColor Cyan
$results | Format-Table -AutoSize
$token = $null
if ($results | Where-Object { $_.Result -eq 'FAIL' }) { exit 1 }
