<?php
require_once __DIR__ . '/config.php';

function api(string $method, array $params = []): ?array {
    $url = API_URL . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function send(int $chat_id, string $text, ?array $keyboard = null, string $parse_mode = 'HTML'): void {
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    api('sendMessage', $params);
}

function edit(int $chat_id, int $msg_id, string $text, ?array $keyboard = null): void {
    $params = ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    api('editMessageText', $params);
}

function answer_cb(string $cb_id, string $text = ''): void {
    api('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => $text]);
}

function send_doc(int $chat_id, string $path, string $caption = ''): void {
    $url = API_URL . 'sendDocument';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chat_id,
            'document' => new CURLFile($path),
            'caption'  => $caption,
            'parse_mode' => 'HTML',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function inline_kb(array $buttons): array {
    return ['inline_keyboard' => $buttons];
}

function is_admin(int $uid): bool {
    return in_array((string)$uid, array_map('strval', ADMIN_IDS));
}

function notify_admins(string $text): void {
    foreach (ADMIN_IDS as $aid) {
        send((int)$aid, $text);
    }
}

function notify_channel(string $text): void {
    $ch = CHANNEL_ID;
    $params = ['chat_id' => $ch, 'text' => $text, 'parse_mode' => 'HTML'];
    api('sendMessage', $params);
}
