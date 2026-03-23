<?php
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '8798260612:AAHD6NZc-U10XMiBPQ6Ft-TwELc6FKnmdJU');
define('ADMIN_IDS', explode(',', getenv('ADMIN_IDS') ?: '6487403841'));
define('CHANNEL_ID', getenv('CHANNEL_ID') ?: '@mat2026_test');
define('DB_FILE', __DIR__ . '/database.db');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
