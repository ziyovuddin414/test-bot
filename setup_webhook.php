<?php
require_once __DIR__ . '/config.php';

$webhook_url = $argv[1] ?? '';
if (!$webhook_url) {
    echo "Usage: php setup_webhook.php https://your-domain.railway.app/\n";
    exit;
}

$ch = curl_init(API_URL . 'setWebhook');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['url' => $webhook_url]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);
echo $res['ok'] ? "✅ Webhook o'rnatildi: $webhook_url\n" : "❌ Xato: " . ($res['description'] ?? '') . "\n";
