<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\Http;

$c = PlatformMetaConnection::platformDefault()->first();
$token = $c?->plainAccessToken() ?: config('platform.meta.system_user_token') ?: config('platform.whatsapp.access_token');
$version = config('services.meta.graph_version', 'v19.0');
$base = rtrim(config('services.meta.graph_url', 'https://graph.facebook.com'), '/') . '/' . $version;

echo "business_id=" . ($c->business_id ?? 'null') . PHP_EOL;
echo "waba_id=" . ($c->whatsapp_business_id ?? 'null') . PHP_EOL;
echo "token_len=" . strlen((string) $token) . PHP_EOL;

$biz = $c->business_id ?: null;

// Try me/businesses
$r = Http::timeout(40)->get("{$base}/me/businesses", [
    'access_token' => $token,
    'fields' => 'id,name',
    'limit' => 50,
]);
echo "me/businesses ok=" . ($r->ok() ? '1' : '0') . ' body=' . substr($r->body(), 0, 500) . PHP_EOL;

$businesses = $r->json('data', []);
if ($biz) {
    array_unshift($businesses, ['id' => $biz, 'name' => 'stored']);
}

$seen = [];
foreach ($businesses as $b) {
    $bid = (string) ($b['id'] ?? '');
    if ($bid === '' || isset($seen[$bid])) continue;
    $seen[$bid] = true;
    echo "--- BUSINESS {$bid} {$b['name']}\n";

    foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
        $wr = Http::timeout(40)->get("{$base}/{$bid}/{$edge}", [
            'access_token' => $token,
            'fields' => 'id,name,account_review_status,ownership_type',
            'limit' => 100,
        ]);
        echo "  {$edge} ok=" . ($wr->ok() ? '1' : '0') . ' count=' . count($wr->json('data', [])) . PHP_EOL;
        if (!$wr->ok()) {
            echo '  err=' . ($wr->json('error.message') ?? $wr->body()) . PHP_EOL;
            continue;
        }
        foreach ($wr->json('data', []) as $w) {
            echo "  WABA {$w['id']} | {$w['name']}\n";
            $pr = Http::timeout(40)->get("{$base}/{$w['id']}/phone_numbers", [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status,quality_rating',
                'limit' => 100,
            ]);
            foreach ($pr->json('data', []) as $p) {
                echo "    PHONE {$p['id']} | {$p['display_phone_number']} | {$p['verified_name']} | ver={$p['code_verification_status']} | st={$p['status']}\n";
            }
            if (!$pr->ok()) echo '    phone_err=' . ($pr->json('error.message') ?? '') . PHP_EOL;
        }
    }
}
