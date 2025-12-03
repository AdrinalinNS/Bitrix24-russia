<?php
function detectGender($name) {
    $name = mb_strtolower(trim($name), 'UTF-8');

    // Список исключений
    $exceptions = [
        'саша' => 'both',
        'валя' => 'both',
        'женя' => 'both',
        'лёша' => 'male',
        'люба' => 'female',
        'юра'  => 'male',
        'аня'  => 'female',
        'оля'  => 'female',
        'илья' => 'male',
        'никита'=> 'male'
    ];

    if (isset($exceptions[$name])) {
        return $exceptions[$name];
    }

    // Базовые списки имён
    $femaleNames = ['анна', 'мария', /* ... полный список */];
    $maleNames   = ['александр', 'сергей', /* ... полный список */];

    if (in_array($name, $femaleNames)) return 'female';
    if (in_array($name, $maleNames))  return 'male';

    // Правила по окончаниям
    $femaleEndings = ['а', 'я', 'ия', 'ья', 'ея'];
    $maleEndings   = ['й', 'ь', 'в', 'н', 'р', 'л', 'м', 'с', 'т', 'к', 'х'];

    foreach ($femaleEndings as $ending) {
        if (mb_substr($name, -mb_strlen($ending, 'UTF-8')) === $ending) {
            return 'female';
        }
    }

    foreach ($maleEndings as $ending) {
        if (mb_substr($name, -mb_strlen($ending, 'UTF-8')) === $ending) {
            return 'male';
        }
    }

    return 'unknown';
}
?>