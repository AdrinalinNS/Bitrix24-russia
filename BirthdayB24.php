<?php
// полный путь до скрипта функции определения пола
require '/home/gpt/helpers.php'; 

// Файл, BirthdayB24.php и helpers.php, должен иметь права на чтение и исполнение (r+x).
// Настройте cron для ежедневного запуска Cron (например, в 09:00):
// пример настройки по ссылке https://timeweb.com/ru/blog/authors/viktor-konoplev/articles/cron-nastroyka-i-zapusk-1/
// в 09:00
// 0 9 * * * /usr/bin/php /путь/к/BirthdayB24.php // не забудь заменить путь до скрипта и проверить его права!

// Настройки Bitrix24
$bitrixWebhook = 'https://ваш-портал.bitrix24.ru/rest/123456/abcdefghijk/';  // замените на ссылку на вебхук

// Настройки Yandex GPT
$yandexApiUrl = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion'; // запрос в яндекс
$iamToken = 'your-yandex-gpt-api-key'; // замените на ваш IAM-токен
$folderId = 'your-yandex-cloud-folder-id';  // замените на ID каталога

// ID общего чата в Bitrix24
$generalChatId = '80'; // укажите ID вашего общего чата, в моем случае $generalChatId = 'chat2'; а '80' это чат со мной был. Определить ID чата можно другим скриптом.

// Массив открыток, сгруппированных по полу
$cardsByGender = [
    'male' => [
    'https://ваш_сайт/1_картинка.jpg',
    'https://ваш_сайт/2_картинка.jpg',
    ],
    'female' => [
    'https://ваш_сайт/3_картинка.jpg',
	'https://ваш_сайт/4_картинка.jpg',
    ],
    'both' => [
    'https://ваш_сайт/5_картинка.jpg',
    'https://ваш_сайт/6_картинка.jpg',	
    ],
    'unknown' => [
    'https://ваш_сайт/7_картинка.jpg',
    'https://ваш_сайт/8_картинка.jpg',
    ]
];

$today = date('m-d'); // какая сегодня дата м-д
// вывод в cmd текущей даты для отладки
//echo $today;
// заполняем массив списком сотрудников у кого ДР и выбираем открытку
$birthdaysToday = [];

// Получаем список сотрудников
$usersResponse = file_get_contents($bitrixWebhook . 'user.get.json');
$users = json_decode($usersResponse, true);

if (empty($users['result'])) {
    error_log('Не удалось получить список пользователей');
    exit;
}

if (isset($users['error'])) {
    error_log('Bitrix24 API error: ' . $users['error']);
    exit;
}

// Собираем всех именинников (с именем и фамилией)
foreach ($users['result'] as $user) {
    $birthday = $user['PERSONAL_BIRTHDAY'] ?? '';
    if (empty($birthday)) continue;

    // Берём только YYYY-MM-DD
    $datePart = substr($birthday, 0, 10);
    // Преобразуем в m-d
    $birthdayDate = date('m-d', strtotime($datePart));
// вывод в cmd всех дат рождения без фио для отладки
// echo $birthdayDate;
// echo "\n";
    // Сравниваем дату рождения с сегодняшним днём
    if ($birthdayDate === $today) {
        // Формируем полное имя
        $fullName = trim($user['LAST_NAME'] . ' ' . ($user['NAME'] ?? ''));
        if (empty($fullName)) {
            $fullName = 'Уважаемый коллега'; // запасной вариант
        }

        // Определяем пол
        $gender = detectGender($user['NAME']); // ваша функция определения пола

        // Выбираем открытку
        switch ($gender) {
            case 'male':
                $availableCards = $cardsByGender['male'];
                break;
            case 'female':
                $availableCards = $cardsByGender['female'];
                break;
            case 'both':
                $availableCards = $cardsByGender['both'];
                break;
            default:
                $availableCards = $cardsByGender['unknown'];
                break;
        }

        $cardUrl = $availableCards[array_rand($availableCards)];

        // Заполняем массив именинников
        $birthdaysToday[] = [
            'ID' => $user['ID'],
            'FULL_NAME' => $fullName,
            'FIRST_NAME' => $user['NAME'],
            'LAST_NAME' => $user['LAST_NAME'],
            'GENDER' => $gender,
            'BIRTHDAY_DATE' => $birthdayDate,
            'CARD_URL' => $cardUrl // сохраняем URL открытки для каждого именинника
        ];
//		echo "Пол: $gender";
//		echo "\n";
//		echo 'Найден день рождения: ' . $fullName . ' → дата: ' . $birthdayDate . '<br>';
    }
}

// Если нет именинников — выходим
if (empty($birthdaysToday)) {
    error_log('Сегодня нет именинников');
    exit;
}

// Формируем индивидуальные поздравления через Yandex GPT для каждого именинника
$messages = [];

foreach ($birthdaysToday as $person) {
    $prompt = trim(
        "Напиши тёплое и официальное персональное поздравление с днём рождения для сотрудника указав полное Фамилию и Имя {$person['FULL_NAME']}. " .
        "Стиль: дружелюбный, но деловой. Длина: 3–5 предложений. " .
        "Упомяни по полному фамилии и имени. " .
        "Пожелай профессиональных достижений, личного счастья и исполнения мечт. " .
        "В конце используй фразу: С уважением и наилучшими пожеланиями, ваши коллеги."
    );

    $yandexRequest = [
        'modelUri' => "gpt://{$folderId}/yandexgpt-lite/latest",
        'completionOptions' => [
            'temperature' => 0.7,
            'maxTokens' => 1000
        ],
        'messages' => [
            ['role' => 'user', 'text' => $prompt]
        ]
    ];

    // Отправляем запрос в Yandex GPT
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $yandexApiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $iamToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($yandexRequest));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // для тестов; в проде включите проверку

    $yandexResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Обрабатываем ответ GPT
    if ($httpCode === 200) {
        $generation = json_decode($yandexResponse, true);
        if (!empty($generation['result']['alternatives'][0]['message']['text'])) {
            $greeting = preg_replace(
                '/[\(\[\{].*?[\)\]\}]/u',
                '',
                $generation['result']['alternatives'][0]['message']['text']
            );
            $greeting = trim($greeting . "\n\n{$person['CARD_URL']}\n\n");
        } else {
            $greeting = null;
        }
    } else {
        error_log("Ошибка API Yandex GPT: HTTP $httpCode, ответ: $yandexResponse");
        $greeting = null;
    }

    // Резервное сообщение (если GPT не ответил)
    if (empty($greeting)) {
        $greeting = "🎉 Поздравляем с днём рождения, {$person['FULL_NAME']}!\n\n";
        $greeting .= "От всей души желаем вам крепкого здоровья, счастья, профессиональных достижений и исполнения всех заветных желаний! 🚀\n\n";
        $greeting .= "С уважением, сотрудники группы компании Тринити 🚀\n\n";
        $greeting .= $person['CARD_URL'];
    }

    $messages[] = $greeting;
}

// Отправляем все сообщения в общий чат
foreach ($messages as $message) {
    $params = [
        'DIALOG_ID' => $generalChatId,
        'MESSAGE' => $message
    ];

    $sendResponse = file_get_contents(
        $bitrixWebhook . 'im.message.add.json',
        false,
        stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params)
            ]
        ])
    );
}

// Логируем отправку
$sentNames = implode(', ', array_column($birthdaysToday, 'FULL_NAME'));
error_log("Отправлено персональные поздравления для: $sentNames в чат $generalChatId");

?>