<?php
// Устанавливаем UTF-8 для корректного вывода кириллицы
header('Content-Type: text/plain; charset=utf-8');
mb_internal_encoding('UTF-8');
error_reporting(0);

// Настройки
$webhookUrl = 'https://ваш-портал.bitrix24.ru/rest/123456/abcdefghijk/'; // замените на ваш вебхук
$limit = 50; // сколько записей получить (максимум зависит от API)
// Формируем URL с параметром limit
$apiUrl = $webhookUrl . 'im.recent.list.json?LIMIT=' . $limit;

// Отправляем запрос
$response = file_get_contents($apiUrl);

if ($response === false) {
    die("Ошибка: не удалось выполнить запрос к Bitrix24\n");
}

// Декодируем ответ
$result = json_decode($response, true, 512, JSON_INVALID_UTF8_IGNORE);

// Проверка на ошибки API
if (isset($result['error'])) {
    echo "Ошибка API Bitrix24:\n";
    echo "Код: " . ($result['error'] ?? 'N/A') . "\n";
    echo "Описание: " . ($result['error_description'] ?? 'Нет описания') . "\n";
    exit;
}

// Проверка наличия данных
if (empty($result) || !isset($result['result'])) {
    echo "Ответ API не содержит ожидаемых данных ('result')\n";
    echo "Полный ответ:\n" . print_r($result, true);
    exit;
}

// Обрабатываем возможные форматы result
$items = [];

if (is_array($result['result'])) {
    // Случай 1: простой массив объектов
    if (!empty($result['result']) && array_key_exists(0, $result['result'])) {
        $items = $result['result'];
    }
    // Случай 2: ассоциативный массив (например, {'items': [...]})
    elseif (isset($result['result']['items']) && is_array($result['result']['items'])) {
        $items = $result['result']['items'];
    }
    // Случай 3: единственный объект (редко, но возможно)
    elseif (count($result['result']) > 0 && !array_key_exists(0, $result['result'])) {
        $items = [$result['result']];
    }
} else {
    echo "Ошибка: 'result' не является массивом\n";
    echo "Тип 'result': " . gettype($result['result']) . "\n";
    exit;
}

// Если элементов нет
if (empty($items)) {
    echo "Нет недавних диалогов для отображения.\n";
    exit;
}

// Вывод заголовка
echo "Последние диалоги (limit: $limit):\n";
echo "Всего найдено: " . count($items) . "\n\n";

// Шапка таблицы
echo sprintf("%-8s %-12s %-40s %s\n", "TYPE", "ID", "TITLE", "EXTRA");
echo str_repeat("-", 80) . "\n";

// Обработка каждого элемента
foreach ($items as $item) {
    if (!is_array($item)) {
        echo "Предупреждение: элемент не является массивом, пропускаем\n";
        continue;
    }

    // Извлекаем поля с запасными вариантами
    $type = $item['TYPE'] ?? $item['type'] ?? 'unknown';
    $id   = $item['ID'] ?? $item['id'] ?? $item['CHAT_ID'] ?? 'N/A';
    $title = $item['TITLE'] ?? $item['title'] ?? $item['NAME'] ?? $item['name'] ?? 'Без названия';

    
    // Дополнительные данные по типу
    $extra = '';

    switch ($type) {
        case 'U': // Личный чат
            $userId = ltrim($id, 'U');
            $extra = "Пользователь ID: $userId";
            break;

        case 'G': // Групповой чат
            $chatId = ltrim($id, 'G');
            $userCount = $item['USER_COUNT'] ?? $item['userCount'] ?? '?';
            $extra = "Участники: $userCount, Chat ID: $chatId";
            break;

        case 'C': // Канал
            $channelId = ltrim($id, 'C');
            $extra = "Канал ID: $channelId";
            if (isset($item['MESSAGE_COUNT'])) {
                $extra .= ", сообщений: {$item['MESSAGE_COUNT']}";
            }
            break;

        case 'S': // Системный чат
            $extra = "Системный чат";
            break;

        default:
            $extra = "Type: $type";
    }

    // Обрезаем длинное название (с учётом кириллицы)
    $truncatedTitle = mb_strimwidth($title, 0, 44, '...', 'UTF-8');

    // Формируем строку и переводим в ANSI
    $line = sprintf(
        "%-10s %-14s %-45s %s\n",
        $type,
        $id,
        $truncatedTitle,
        $extra
    );
    echo iconv('UTF-8', 'Windows-1251//TRANSLIT', $line);
}

echo iconv('UTF-8', 'Windows-1251//TRANSLIT', "\nГотово!\n");
?>