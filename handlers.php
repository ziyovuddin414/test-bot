<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/telegram.php';

// ═══════════════════════════════════════════════════════════════════════════════
//  ADMIN HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

function admin_menu(int $uid): void {
    send($uid, "🛠 <b>Admin panel</b>", inline_kb([
        [['text' => '➕ Test qo\'shish',    'callback_data' => 'a_add']],
        [['text' => '📋 Faol testlar',      'callback_data' => 'a_list']],
        [['text' => '📊 Natijalar',         'callback_data' => 'a_results']],
        [['text' => '🏆 Top-10',            'callback_data' => 'a_top']],
        [['text' => '📥 Excel eksport',     'callback_data' => 'a_excel']],
        [['text' => '📢 Xabar yuborish',    'callback_data' => 'a_broadcast']],
        [['text' => '📈 Statistika',        'callback_data' => 'a_stats']],
        [['text' => '📚 Barcha testlar',    'callback_data' => 'a_all_tests']],
    ]));
}

function handle_admin_cb(int $uid, string $data, string $cb_id): void {
    answer_cb($cb_id);

    // ── TEST QO'SHISH ─────────────────────────────────────────────────────────
    if ($data === 'a_add') {
        set_state($uid, 'a_add_title');
        send($uid, "📝 Test nomini kiriting:\n<i>Masalan: Matematika 7-sinf</i>");
        return;
    }

    // ── FAOL TESTLAR ──────────────────────────────────────────────────────────
    if ($data === 'a_list') {
        $tests = get_active_tests();
        if (!$tests) { send($uid, "📭 Faol test yo'q."); return; }
        $btns = [];
        foreach ($tests as $t) {
            $btns[] = [['text' => "📋 {$t['title']} ({$t['total']} ta)", 'callback_data' => "a_test_{$t['id']}"]];
        }
        send($uid, "📋 <b>Faol testlar:</b>", inline_kb($btns));
        return;
    }

    // ── BARCHA TESTLAR ────────────────────────────────────────────────────────
    if ($data === 'a_all_tests') {
        $tests = get_all_tests();
        if (!$tests) { send($uid, "📭 Test yo'q."); return; }
        $text = "📚 <b>Barcha testlar:</b>\n\n";
        foreach ($tests as $t) {
            $status = $t['is_active'] ? '🟢' : '🔴';
            $text .= "$status <b>{$t['title']}</b> — {$t['total']} ta | {$t['created_at']}\n";
        }
        send($uid, $text);
        return;
    }

    // ── TEST DETAIL ───────────────────────────────────────────────────────────
    if (strpos($data, 'a_test_') === 0) {
        $tid = (int) substr($data, 7);
        $t = get_test($tid);
        if (!$t) { send($uid, "Test topilmadi."); return; }
        $results = get_results_by_test($tid);
        $deadline = $t['deadline'] ? "\n⏰ Deadline: <b>{$t['deadline']}</b>" : "";
        send($uid,
            "📋 <b>{$t['title']}</b>\n" .
            "🔢 Savollar: <b>{$t['total']}</b>\n" .
            "📅 Yaratilgan: <b>{$t['created_at']}</b>$deadline\n" .
            "👥 Topshirganlar: <b>" . count($results) . "</b>\n" .
            "✅ Javoblar: <code>{$t['answers']}</code>",
            inline_kb([
                [
                    ['text' => '🗑 O\'chirish',  'callback_data' => "a_del_{$tid}"],
                    ['text' => '🔴 Yopish',      'callback_data' => "a_deact_{$tid}"],
                ],
                [['text' => '◀️ Orqaga', 'callback_data' => 'a_list']],
            ])
        );
        return;
    }

    // ── O'CHIRISH ─────────────────────────────────────────────────────────────
    if (strpos($data, 'a_del_') === 0) {
        $tid = (int) substr($data, 6);
        delete_test($tid);
        send($uid, "🗑 Test va barcha natijalari o'chirildi.");
        return;
    }

    // ── YOPISH ────────────────────────────────────────────────────────────────
    if (strpos($data, 'a_deact_') === 0) {
        $tid = (int) substr($data, 8);
        deactivate_test($tid);
        send($uid, "🔴 Test yopildi.");
        return;
    }

    // ── NATIJALAR ─────────────────────────────────────────────────────────────
    if ($data === 'a_results') {
        $tests = get_active_tests();
        if (!$tests) { send($uid, "📭 Faol test yo'q."); return; }
        $btns = array_map(fn($t) => [['text' => "📊 {$t['title']}", 'callback_data' => "a_res_{$t['id']}"]], $tests);
        send($uid, "Qaysi testning natijalarini ko'rmoqchisiz?", inline_kb($btns));
        return;
    }

    if (strpos($data, 'a_res_') === 0) {
        $tid = (int) substr($data, 6);
        $t = get_test($tid);
        $results = get_results_by_test($tid);
        if (!$results) { send($uid, "📭 Hali hech kim topshirmagan."); return; }
        $text = "📊 <b>{$t['title']} — Natijalar</b>\n📅 {$t['created_at']}\n\n";
        foreach ($results as $i => $r) {
            $text .= ($i+1) . ". <b>{$r['full_name']}</b>\n";
            $text .= "   ✅ {$r['correct']} | ❌ {$r['wrong']} | 🎯 {$r['score']}%\n";
            $text .= "   🕐 {$r['submitted_at']}\n\n";
        }
        send($uid, $text);
        return;
    }

    // ── TOP 10 ────────────────────────────────────────────────────────────────
    if ($data === 'a_top') {
        $tests = get_active_tests();
        if (!$tests) { send($uid, "📭 Faol test yo'q."); return; }
        $btns = array_map(fn($t) => [['text' => "🏆 {$t['title']}", 'callback_data' => "a_top_{$t['id']}"]], $tests);
        send($uid, "Qaysi testning Top-10 ni ko'rmoqchisiz?", inline_kb($btns));
        return;
    }

    if (strpos($data, 'a_top_') === 0) {
        $tid = (int) substr($data, 6);
        $t = get_test($tid);
        $top = get_top10($tid);
        if (!$top) { send($uid, "📭 Natija yo'q."); return; }
        $medals = ['🥇','🥈','🥉','🏅','🏅','🏅','🏅','🏅','🏅','🏅'];
        $text = "🏆 <b>{$t['title']} — Top 10</b>\n\n";
        foreach ($top as $i => $r) {
            $text .= "{$medals[$i]} <b>{$r['full_name']}</b> — {$r['score']}% ({$r['correct']} to'g'ri)\n";
        }
        send($uid, $text);
        return;
    }

    // ── EXCEL ─────────────────────────────────────────────────────────────────
    if ($data === 'a_excel') {
        $tests = get_active_tests();
        if (!$tests) { send($uid, "📭 Faol test yo'q."); return; }
        $btns = array_map(fn($t) => [['text' => "📥 {$t['title']}", 'callback_data' => "a_xl_{$t['id']}"]], $tests);
        send($uid, "Qaysi testni eksport qilmoqchisiz?", inline_kb($btns));
        return;
    }

    if (strpos($data, 'a_xl_') === 0) {
        $tid = (int) substr($data, 5);
        export_excel($uid, $tid);
        return;
    }

    // ── BROADCAST ─────────────────────────────────────────────────────────────
    if ($data === 'a_broadcast') {
        set_state($uid, 'a_broadcast');
        send($uid, "✍️ Barcha foydalanuvchilarga yuboriladigan xabarni yozing:");
        return;
    }

    // ── STATISTIKA ────────────────────────────────────────────────────────────
    if ($data === 'a_stats') {
        $s = get_stats();
        send($uid,
            "📈 <b>Statistika</b>\n\n" .
            "👥 Foydalanuvchilar: <b>{$s['users']}</b>\n" .
            "📋 Testlar: <b>{$s['tests']}</b>\n" .
            "📝 Topshirishlar: <b>{$s['results']}</b>\n" .
            "🎯 O'rtacha ball: <b>{$s['avg']}%</b>"
        );
        return;
    }
}

function handle_admin_message(int $uid, string $text, array $st): void {
    $state = $st['state'];
    $data  = $st['data'];

    if ($state === 'a_add_title') {
        set_state($uid, 'a_add_answers', ['title' => trim($text)]);
        send($uid, "✅ Test nomi: <b>" . trim($text) . "</b>\n\n✍️ Javoblarni kiriting (A,B,C,D harflar):\n<code>ABCDABCDABCD</code>");
        return;
    }

    if ($state === 'a_add_answers') {
        $answers = strtoupper(trim($text));
        if (!preg_match('/^[ABCD]+$/', $answers)) {
            send($uid, "❌ Faqat A, B, C, D harflaridan foydalaning! Qaytadan:");
            return;
        }
        set_state($uid, 'a_add_deadline', array_merge($data, ['answers' => $answers]));
        send($uid, "⏰ Deadline belgilaysizmi? (ixtiyoriy)\nMasalan: <code>2024-12-31 18:00</code>\nYoki o'tkazib yuborish uchun: <b>yo'q</b>");
        return;
    }

    if ($state === 'a_add_deadline') {
        $deadline = null;
        if (strtolower(trim($text)) !== "yo'q" && strtolower(trim($text)) !== 'yoq' && strtolower(trim($text)) !== 'no') {
            $deadline = trim($text);
        }
        $tid = add_test($data['title'], $data['answers'], $uid, $deadline);
        clear_state($uid);
        $dl = $deadline ? "\n⏰ Deadline: <b>$deadline</b>" : "";
        send($uid,
            "✅ <b>Test saqlandi!</b>\n\n" .
            "📌 Nom: <b>{$data['title']}</b>\n" .
            "🔢 Savollar: <b>" . strlen($data['answers']) . "</b>$dl\n" .
            "🆔 Test ID: <code>$tid</code>"
        );
        return;
    }

    if ($state === 'a_broadcast') {
        $users = get_all_users();
        $sent = $failed = 0;
        foreach ($users as $user_id) {
            $res = api('sendMessage', [
                'chat_id'    => (int)$user_id,
                'text'       => "📢 <b>Admin xabari:</b>\n\n$text",
                'parse_mode' => 'HTML',
            ]);
            $res && isset($res['ok']) && $res['ok'] ? $sent++ : $failed++;
        }
        clear_state($uid);
        send($uid, "✅ Yuborildi: <b>$sent</b> ta\n❌ Xato: <b>$failed</b> ta");
        return;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  STUDENT HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

function student_menu(int $uid): void {
    $tests = get_active_tests();
    if (!$tests) {
        send($uid, "📭 Hozircha faol test mavjud emas. Keyinroq urinib ko'ring.");
        return;
    }
    $btns = [];
    foreach ($tests as $t) {
        $deadline = $t['deadline'] ? " ⏰{$t['deadline']}" : "";
        $btns[] = [['text' => "📋 {$t['title']} ({$t['total']} ta)$deadline", 'callback_data' => "s_test_{$t['id']}"]];
    }
    send($uid, "📋 <b>Mavjud testlar:</b>\nBirini tanlang:", inline_kb($btns));
}

function handle_student_cb(int $uid, string $full_name, string $data, string $cb_id): void {
    answer_cb($cb_id);

    if (strpos($data, 's_test_') === 0) {
        $tid = (int) substr($data, 7);
        $t = get_test($tid);
        if (!$t || !$t['is_active']) { send($uid, "❌ Bu test mavjud emas yoki yopilgan."); return; }

        // Deadline tekshirish
        if ($t['deadline'] && date('Y-m-d H:i') > $t['deadline']) {
            send($uid, "⏰ Bu testning muddati tugagan (<b>{$t['deadline']}</b>).");
            return;
        }

        if (already_submitted($tid, $uid)) {
            send($uid, "⚠️ Siz bu testni allaqachon topshirgansiz!");
            return;
        }

        set_state($uid, 's_answer', [
            'test_id'      => $tid,
            'test_title'   => $t['title'],
            'test_answers' => $t['answers'],
            'test_total'   => $t['total'],
            'test_created' => $t['created_at'],
        ]);

        send($uid,
            "📋 <b>{$t['title']}</b>\n" .
            "🔢 Savollar: <b>{$t['total']}</b>\n" .
            "📅 Yaratilgan: <b>{$t['created_at']}</b>\n\n" .
            "✍️ Javoblaringizni yuboring:\n" .
            "Masalan: <code>ABCDABCD</code> ({$t['total']} ta harf)\n\n" .
            "⚠️ Faqat A, B, C, D harflari!"
        );
        return;
    }

    // Natijalar tarixi
    if ($data === 's_history') {
        $results = get_user_results($uid);
        if (!$results) { send($uid, "📭 Siz hali biror test topshirmagansiz."); return; }
        $text = "📜 <b>Sizning natijalaringiz:</b>\n\n";
        foreach ($results as $r) {
            $text .= "📋 <b>{$r['title']}</b>\n";
            $text .= "✅ {$r['correct']} | ❌ {$r['wrong']} | 🎯 {$r['score']}%\n";
            $text .= "🕐 {$r['submitted_at']}\n\n";
        }
        send($uid, $text);
        return;
    }
}

function handle_student_message(int $uid, string $full_name, string $text, array $st): void {
    $state = $st['state'];
    $data  = $st['data'];

    if ($state === 's_name') {
        $name = trim($text);
        if (str_word_count($name) < 2) {
            send($uid, "❗ To'liq ism va familiyangizni kiriting (ikki so'z):");
            return;
        }
        register_user($uid, $name);
        set_state($uid, null, ['full_name' => $name]);
        send($uid, "✅ Salom, <b>$name</b>! Ro'yxatdan o'tdingiz.");
        student_menu($uid);
        clear_state($uid);
        return;
    }

    if ($state === 's_answer') {
        $user_ans = strtoupper(trim($text));
        $total = $data['test_total'];
        $correct_ans = $data['test_answers'];

        if (!preg_match('/^[ABCD]+$/', $user_ans)) {
            send($uid, "❌ Faqat A, B, C, D harflarini kiriting!");
            return;
        }
        if (strlen($user_ans) !== $total) {
            send($uid, "❌ <b>$total</b> ta javob kerak, siz <b>" . strlen($user_ans) . "</b> ta yubordingiz. Qaytadan:");
            return;
        }
        if (already_submitted($data['test_id'], $uid)) {
            send($uid, "⚠️ Siz bu testni allaqachon topshirgansiz!");
            clear_state($uid);
            return;
        }

        $correct = 0;
        for ($i = 0; $i < $total; $i++) {
            if ($user_ans[$i] === $correct_ans[$i]) $correct++;
        }
        $wrong = $total - $correct;
        $score = round(($correct / $total) * 100, 1);

        save_result($data['test_id'], $uid, $full_name, $user_ans, $correct, $wrong, $score);

        $emoji = $score >= 90 ? '🏆' : ($score >= 70 ? '✅' : ($score >= 50 ? '📊' : '❌'));
        $now = date('Y-m-d H:i');

        $result_text =
            "$emoji <b>Natija</b>\n\n" .
            "👤 O'quvchi: <b>$full_name</b>\n" .
            "📋 Test: <b>{$data['test_title']}</b>\n" .
            "📅 Test yaratilgan: <b>{$data['test_created']}</b>\n" .
            "🕐 Topshirilgan: <b>$now</b>\n\n" .
            "✅ To'g'ri: <b>$correct</b> ta\n" .
            "❌ Xato: <b>$wrong</b> ta\n" .
            "🎯 Ball: <b>$score%</b>";

        // O'quvchiga
        send($uid, $result_text, inline_kb([[['text' => '📜 Mening natijalarim', 'callback_data' => 's_history']]]));

        // Adminga
        notify_admins("📬 <b>Yangi natija!</b>\n🆔 ID: <code>$uid</code>\n\n$result_text");

        // Kanalga
        notify_channel($result_text);

        clear_state($uid);
        return;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  EXCEL EXPORT
// ═══════════════════════════════════════════════════════════════════════════════

function export_excel(int $uid, int $tid): void {
    $t = get_test($tid);
    $results = get_results_by_test($tid);
    if (!$results) { send($uid, "📭 Natija yo'q."); return; }

    $filename = sys_get_temp_dir() . "/test_{$tid}_natijalar.csv";
    $fp = fopen($filename, 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($fp, ['#', 'Ism-Familiya', "To'g'ri", 'Xato', 'Ball (%)', 'Javoblar', 'Topshirilgan'], ';');
    foreach ($results as $i => $r) {
        fputcsv($fp, [$i+1, $r['full_name'], $r['correct'], $r['wrong'], $r['score'], $r['user_answers'], $r['submitted_at']], ';');
    }
    fclose($fp);

    send_doc($uid, $filename, "📥 <b>{$t['title']}</b> — natijalar");
    unlink($filename);
}
