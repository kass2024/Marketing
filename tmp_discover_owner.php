<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\Http;

$c = PlatformMetaConnection::platformDefault()->first();
$token = $c?->plainAccessToken() ?: config('platform.meta.system_user_token');
$version = config('services.meta.graph_version', 'v19.0');
$base = rtrim(config('services.meta.graph_url', 'https://graph.facebook.com'), '/') . '/' . $version;
$waba = (string) ($c->whatsapp_business_id ?: config('platform.whatsapp.business_id'));

echo "stored_waba={$waba}\n";

$fields = [
    'id,name,account_review_status,ownership_type,currency',
    'owner_business_info{id,name}',
    'on_behalf_of_business_info{id,name}',
];
$r = Http::timeout(40)->get("{$base}/{$waba}", [
    'access_token' => $token,
    'fields' => implode(',', $fields),
]);
echo "waba_detail ok=" . ($r->ok()?'1':'0') . "\n" . json_encode($r->json(), JSON_PRETTY_PRINT) . "\n";

$owner = data_get($r->json(), 'owner_business_info.id')
    ?: data_get($r->json(), 'on_behalf_of_business_info.id');
echo "owner_biz={$owner}\n";

if ($owner) {
    foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
        $wr = Http::timeout(40)->get("{$base}/{$owner}/{$edge}", [
            'access_token' => $token,
            'fields' => 'id,name,account_review_status,ownership_type',
            'limit' => 100,
        ]);
        echo "{$edge} ok=" . ($wr->ok()?'1':'0') . " count=" . count($wr->json('data', [])) . "\n";
        if (!$wr->ok()) {
            echo "err=" . ($wr->json('error.message') ?? $wr->body()) . "\n";
            continue;
        }
        foreach ($wr->json('data', []) as $w) {
            echo "WABA {$w['id']} | {$w['name']}\n";
            $pr = Http::timeout(40)->get("{$base}/{$w['id']}/phone_numbers", [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
                'limit' => 50,
            ]);
            foreach ($pr->json('data', []) as $p) {
                echo "  {$p['display_phone_number']} | {$p['verified_name']} | {$p['code_verification_status']} | {$p['status']}\n";
            }
        }
    }

    // Also pages + ad accounts for full sync
    foreach (['owned_pages' => 'id,name', 'owned_ad_accounts' => 'id,name,account_status'] as $edge => $f) {
        $ar = Http::timeout(40)->get("{$base}/{$owner}/{$edge}", [
            'access_token' => $token,
            'fields' => $f,
            'limit' => 50,
        ]);
        echo "{$edge} ok=" . ($ar->ok()?'1':'0') . " count=" . count($ar->json('data', [])) . "\n";
        foreach ($ar->json('data', []) as $row) {
            echo "  {$row['id']} | " . ($row['name'] ?? '') . "\n";
        }
    }
}

// phones on stored waba alone
$pr = Http::timeout(40)->get("{$base}/{$waba}/phone_numbers", [
    'access_token' => $token,
    'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
]);
echo "direct_waba_phones count=" . count($pr->json('data', [])) . "\n";
foreach ($pr->json('data', []) as $p) {
    echo "  {$p['display_phone_number']} | {$p['verified_name']}\n";
}
