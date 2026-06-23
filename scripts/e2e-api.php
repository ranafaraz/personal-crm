<?php
/**
 * AI Agent API e2e smoke test — runs on the VPS as part of deploy.yml.
 *
 * Mints a temp test token for ApiClient #11 (pocrm_live_WJkJS prefix),
 * fires GET+POST+DELETE against every new AI-agent domain via HTTPS,
 * cleans up, then exits non-zero if any check failed.
 */

chdir('/var/www/crm.dexdevs.com');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── Find the GPT client ────────────────────────────────────────────────────
// token_prefix is on api_client_tokens, not api_clients — look up by ID directly
$client = \App\Models\ApiClient::find(11);
if (!$client) {
    echo "SKIP: ApiClient #11 not found on prod (run crm:api-client:create first)\n";
    exit(0);
}

// ── Mint a short-lived test token ─────────────────────────────────────────
['raw' => $raw, 'hash' => $hash, 'prefix' => $prefix] = \App\Models\ApiClientToken::generateRaw('live');
$tok = \App\Models\ApiClientToken::create([
    'api_client_id' => $client->id,
    'name'          => 'e2e-smoke-' . date('YmdHis'),
    'token_hash'    => $hash,
    'token_prefix'  => $prefix,
    'is_active'     => true,
]);

// ── HTTP helper ────────────────────────────────────────────────────────────
function hit(string $method, string $path, string $raw, ?array $body = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://crm.dexdevs.com' . $path,
        CURLOPT_HTTPHEADER     => [
            "X-Api-Key: $raw",
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp ? (json_decode($resp, true) ?? []) : []];
}

$pass = 0;
$fail = 0;

function chk(string $label, int $got, int $expect): void
{
    global $pass, $fail;
    $ok = ($got === $expect);
    $ok ? $pass++ : $fail++;
    printf("  [%s] %-50s HTTP %d\n", $ok ? 'PASS' : 'FAIL', $label, $got);
}

// ── Tests ──────────────────────────────────────────────────────────────────
$s = date('YmdHis');   // unique suffix per run to avoid collisions

// gpt/v1 — existing endpoints extended in Step 1
[$c] = hit('GET', '/api/gpt/v1/opportunities', $raw); chk('GET gpt/v1/opportunities', $c, 200);
[$c] = hit('GET', '/api/gpt/v1/contacts',      $raw); chk('GET gpt/v1/contacts',      $c, 200);
[$c] = hit('GET', '/api/gpt/v1/email-drafts',  $raw); chk('GET gpt/v1/email-drafts',  $c, 200);
[$c] = hit('GET', '/api/gpt/v1/follow-ups',    $raw); chk('GET gpt/v1/follow-ups',    $c, 200);

// content/v1
[$c] = hit('GET', '/api/content/v1/items', $raw); chk('GET content/v1/items', $c, 200);
[$c, $r] = hit('POST', '/api/content/v1/items', $raw, [
    'title' => "E2E $s", 'content_type' => 'article', 'channel' => 'blog',
]);
chk('POST content/v1/items', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/content/v1/items/$id", $raw); chk('DELETE content/v1/items', $c, 200);
}

// research/v1
[$c] = hit('GET', '/api/research/v1/papers', $raw); chk('GET research/v1/papers', $c, 200);
[$c, $r] = hit('POST', '/api/research/v1/papers', $raw, [
    'title' => "E2E $s", 'url' => 'https://example.com/paper',
]);
chk('POST research/v1/papers', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/research/v1/papers/$id", $raw); chk('DELETE research/v1/papers', $c, 200);
}

// proposals/v1
[$c] = hit('GET', '/api/proposals/v1/proposals', $raw); chk('GET proposals/v1/proposals', $c, 200);
[$c, $r] = hit('POST', '/api/proposals/v1/proposals', $raw, [
    'title' => "E2E $s", 'amount' => '1500.00', 'currency' => 'USD',
]);
chk('POST proposals/v1/proposals', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/proposals/v1/proposals/$id", $raw); chk('DELETE proposals/v1/proposals', $c, 200);
}

// youtube/v1
[$c] = hit('GET', '/api/youtube/v1/videos', $raw); chk('GET youtube/v1/videos', $c, 200);
[$c, $r] = hit('POST', '/api/youtube/v1/videos', $raw, [
    'title' => "E2E $s", 'url' => 'https://youtube.com/test',
]);
chk('POST youtube/v1/videos', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/youtube/v1/videos/$id", $raw); chk('DELETE youtube/v1/videos', $c, 200);
}

// freelance/v1
[$c] = hit('GET', '/api/freelance/v1/projects', $raw); chk('GET freelance/v1/projects', $c, 200);
[$c, $r] = hit('POST', '/api/freelance/v1/projects', $raw, [
    'title' => "E2E $s", 'rate_type' => 'fixed', 'budget' => '5000.00',
]);
chk('POST freelance/v1/projects', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/freelance/v1/projects/$id", $raw); chk('DELETE freelance/v1/projects', $c, 200);
}

// bulk (non-existent id → partial success, 200 with succeeded=0/failed=1)
[$c] = hit('POST', '/api/gpt/v1/bulk', $raw, [
    'entity' => 'contacts', 'operation' => 'update',
    'ids'    => [999999999], 'data' => ['status' => 'active'],
]);
chk('POST gpt/v1/bulk (partial not_found)', $c, 200);

// pipelines/v1
[$c] = hit('GET', '/api/pipelines/v1/pipelines',     $raw); chk('GET pipelines/v1/pipelines',     $c, 200);
[$c] = hit('GET', '/api/pipelines/v1/runs',           $raw); chk('GET pipelines/v1/runs',           $c, 200);
[$c] = hit('GET', '/api/pipelines/v1/scheduled-jobs', $raw); chk('GET pipelines/v1/scheduled-jobs', $c, 200);
[$c, $r] = hit('POST', '/api/pipelines/v1/pipelines', $raw, [
    'name' => "E2E $s", 'trigger_type' => 'manual',
]);
chk('POST pipelines/v1/pipelines', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('POST', "/api/pipelines/v1/pipelines/$id/execute", $raw, []);
    chk('POST pipelines/{id}/execute', $c, 201);
    [$c] = hit('DELETE', "/api/pipelines/v1/pipelines/$id", $raw);
    chk('DELETE pipelines/v1/pipelines', $c, 200);
}

// webhooks/v1
[$c] = hit('GET', '/api/webhooks/v1/webhooks',   $raw); chk('GET webhooks/v1/webhooks',   $c, 200);
[$c] = hit('GET', '/api/webhooks/v1/deliveries', $raw); chk('GET webhooks/v1/deliveries', $c, 200);
[$c, $r] = hit('POST', '/api/webhooks/v1/webhooks', $raw, [
    'name' => "E2E $s", 'url' => 'https://example.com/hook',
    'events' => ['contact.created'],
]);
chk('POST webhooks/v1/webhooks', $c, 201);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('POST', "/api/webhooks/v1/webhooks/$id/test", $raw, ['event' => 'test.ping']);
    chk('POST webhooks/{id}/test', $c, 201);
    [$c] = hit('DELETE', "/api/webhooks/v1/webhooks/$id", $raw);
    chk('DELETE webhooks/v1/webhooks', $c, 200);
}

// analytics/v1 (read-only)
[$c] = hit('GET', '/api/analytics/v1/summary',       $raw); chk('GET analytics/v1/summary',       $c, 200);
[$c] = hit('GET', '/api/analytics/v1/opportunities', $raw); chk('GET analytics/v1/opportunities', $c, 200);
[$c] = hit('GET', '/api/analytics/v1/revenue',       $raw); chk('GET analytics/v1/revenue',       $c, 200);
[$c] = hit('GET', '/api/analytics/v1/content',       $raw); chk('GET analytics/v1/content',       $c, 200);

// tags/v1 — firstOrCreate returns 200 (existing) or 201 (new); both are OK
[$c] = hit('GET', '/api/tags/v1/tags', $raw); chk('GET tags/v1/tags', $c, 200);
[$c, $r] = hit('POST', '/api/tags/v1/tags', $raw, ['name' => "E2E-$s"]);
$tagOk = in_array($c, [200, 201]);
$tagOk ? $pass++ : $fail++;
printf("  [%s] %-50s HTTP %d\n", $tagOk ? 'PASS' : 'FAIL', 'POST tags/v1/tags', $c);
if ($id = $r['data']['id'] ?? null) {
    [$c] = hit('DELETE', "/api/tags/v1/tags/$id", $raw); chk('DELETE tags/v1/tags', $c, 200);
}

// OpenAPI specs (public routes — no auth required, key header ignored)
[$c] = hit('GET', '/openapi/gpt-actions.json',   $raw); chk('GET openapi/gpt-actions.json',   $c, 200);
[$c] = hit('GET', '/openapi/agent-actions.json', $raw); chk('GET openapi/agent-actions.json', $c, 200);

// ── Cleanup ────────────────────────────────────────────────────────────────
$tok->delete();

printf("\n=== AI Agent API e2e: %d passed, %d failed ===\n", $pass, $fail);
if ($fail > 0) {
    echo "Some endpoints failed — check laravel.log for details.\n";
    exit(1);
}
