<?php

return [

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
        'version' => '2023-06-01',
        'max_tokens' => 2000,
        // Ceny v USD za milión tokenov — nastav podľa aktuálneho cenníka, inak sa cena nezobrazí.
        'price_input' => env('ANTHROPIC_PRICE_INPUT'),
        'price_output' => env('ANTHROPIC_PRICE_OUTPUT'),
    ],

    'max_attempts' => 2,

    'categories' => [
        'beh/obuv' => 'Beh › Obuv',
        'beh/oblecenie' => 'Beh › Oblečenie',
        'beh/doplnky' => 'Beh › Doplnky',
        'turistika/obuv' => 'Turistika › Obuv',
        'turistika/batohy' => 'Turistika › Batohy',
        'turistika/oblecenie' => 'Turistika › Oblečenie',
        'zimne-sporty/lyze' => 'Zimné športy › Lyže',
        'zimne-sporty/prilby' => 'Zimné športy › Prilby',
        'zimne-sporty/oblecenie' => 'Zimné športy › Oblečenie',
        'cyklistika/prilby' => 'Cyklistika › Prilby',
        'cyklistika/oblecenie' => 'Cyklistika › Oblečenie',
        'fitness/naradie' => 'Fitness › Náradie',
        'fitness/oblecenie' => 'Fitness › Oblečenie',
        'loptove-sporty/lopty' => 'Loptové športy › Lopty',
        'ine' => 'Iné (na ručné zaradenie)',
    ],

    'genders' => ['panske', 'damske', 'detske', 'unisex'],

    'woocommerce' => [
        'url' => env('WOO_URL'),
        'user' => env('WOO_USER'),
        'app_password' => env('WOO_APP_PASSWORD'),
    ],

];
