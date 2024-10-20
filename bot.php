<?php
$token = '5180483481:AAEK1DOTkHNmu5tnaLRs0k5CNAAYr2yiE7c';  // Укажите токен вашего бота
$apiUrl = "https://api.telegram.org/bot$token/";
$input = json_decode(file_get_contents('php://input'), true);

// Подключение к SQLite
$db = new SQLite3('investments.db');

// Создаём таблицы, если они не существуют
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_id INTEGER UNIQUE,
    balance FLOAT DEFAULT 0
)");

$db->exec("CREATE TABLE IF NOT EXISTS investments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    amount FLOAT,
    start_date TEXT,
    end_date TEXT,
    daily_percent FLOAT,
    last_accrual TEXT
)");

// Обработка сообщений от Telegram
if (isset($input['message'])) {
    $chatId = $input['message']['chat']['id'];
    $text = $input['message']['text'];

    switch ($text) {
        case '/start':
            sendMessage($chatId, "Добро пожаловать! Введите /menu для начала.");
            break;
        case '/menu':
            showMenu($chatId);
            break;
        default:
            sendMessage($chatId, "Неизвестная команда. Введите /menu для списка команд.");
    }
}

// Функция для отправки сообщений
function sendMessage($chatId, $text) {
    global $apiUrl;
    file_get_contents($apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($text));
}

// Функция для отображения меню
function showMenu($chatId) {
    $keyboard = [
        'keyboard' => [
            [['text' => 'Инвестировать'], ['text' => 'Баланс']],
            [['text' => 'Мои инвестиции']]
        ],
        'resize_keyboard' => true
    ];
    sendKeyboard($chatId, "Выберите действие:", $keyboard);
}

// Функция для отправки клавиатуры
function sendKeyboard($chatId, $text, $keyboard) {
    global $apiUrl;
    $encodedKeyboard = json_encode(['chat_id' => $chatId, 'text' => $text, 'reply_markup' => $keyboard]);
    file_get_contents($apiUrl . "sendMessage?" . http_build_query(json_decode($encodedKeyboard, true)));
}

// Функция для начисления процентов
function accrueInterest() {
    global $db;
    $now = new DateTime();

    $result = $db->query("SELECT * FROM investments WHERE end_date > datetime('now')");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $lastAccrual = new DateTime($row['last_accrual']);
        if ($now->diff($lastAccrual)->days >= 1) {
            $amount = $row['amount'];
            $percent = $row['daily_percent'] / 100;
            $accrued = $amount * $percent;

            // Обновляем баланс пользователя
            $userId = $row['user_id'];
            $db->exec("UPDATE users SET balance = balance + $accrued WHERE id = $userId");

            // Обновляем дату последнего начисления
            $db->exec("UPDATE investments SET last_accrual = datetime('now') WHERE id = {$row['id']}");
        }
    }
}

// Настройка фона: бесконечный цикл для начисления процентов каждые 24 часа
ignore_user_abort(true);
set_time_limit(0);

while (true) {
    accrueInterest();  // Начисляем проценты
    sleep(86400);  // Ждём 24 часа
}
?>
