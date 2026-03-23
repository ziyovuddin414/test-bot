<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/handlers.php';

init_db();

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

// ── CALLBACK QUERY ────────────────────────────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb    = $update['callback_query'];
    $uid   = (int) $cb['from']['id'];
    $data  = $cb['data'];
    $cb_id = $cb['id'];
    $from  = $cb['from'];
    $full_name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));

    if (is_admin($uid)) {
        handle_admin_cb($uid, $data, $cb_id);
    } else {
        handle_student_cb($uid, $full_name, $data, $cb_id);
    }
    exit;
}

// ── MESSAGE ───────────────────────────────────────────────────────────────────
if (!isset($update['message'])) exit;

$msg  = $update['message'];
$uid  = (int) $msg['from']['id'];
$from = $msg['from'];
$text = $msg['text'] ?? '';
$full_name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
$username  = $from['username'] ?? '';

$st = get_state($uid);

// /start
if ($text === '/start') {
    clear_state($uid);
    if (is_admin($uid)) {
        send($uid, "👋 Salom, Admin!\n/admin — boshqaruv paneliga kirish");
    } else {
        send($uid,
            "👋 <b>Test botiga xush kelibsiz!</b>\n\n" .
            "Iltimos, <b>Ism va Familiyangizni</b> kiriting:"
        );
        set_state($uid, 's_name');
    }
    exit;
}

// /admin
if ($text === '/admin') {
    if (!is_admin($uid)) { send($uid, "❌ Ruxsat yo'q."); exit; }
    clear_state($uid);
    admin_menu($uid);
    exit;
}

// /mystats
if ($text === '/mystats') {
    $results = get_user_results($uid);
    if (!$results) { send($uid, "📭 Siz hali biror test topshirmagansiz."); exit; }
    $text2 = "📜 <b>Sizning natijalaringiz:</b>\n\n";
    foreach ($results as $r) {
        $text2 .= "📋 <b>{$r['title']}</b> — {$r['score']}%\n";
        $text2 .= "✅ {$r['correct']} | ❌ {$r['wrong']} | 🕐 {$r['submitted_at']}\n\n";
    }
    send($uid, $text2);
    exit;
}

// /tests (o'quvchi uchun)
if ($text === '/tests') {
    if (is_admin($uid)) { admin_menu($uid); exit; }
    student_menu($uid);
    exit;
}

// Admin state handler
if (is_admin($uid) && $st['state'] && strpos($st['state'], 'a_') === 0) {
    handle_admin_message($uid, $text, $st);
    exit;
}

// Student state handler
if (!is_admin($uid)) {
    if ($st['state']) {
        handle_student_message($uid, $full_name, $text, $st);
    } else {
        student_menu($uid);
    }
    exit;
}
