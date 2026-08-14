<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Python Math Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi dengan Python Math Engine microservice.
    | Engine digunakan untuk generate soal matematika secara deterministik.
    |
    */

    'url' => env('MATH_ENGINE_URL', 'http://localhost:8001'),

    'timeout' => (int) env('MATH_ENGINE_TIMEOUT', 30),

    'retry' => [
        'attempts' => (int) env('MATH_ENGINE_RETRY_ATTEMPTS', 2),
        'delay' => (int) env('MATH_ENGINE_RETRY_DELAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Level → Difficulty Mapping
    |--------------------------------------------------------------------------
    |
    | Mapping dari level Python Math Engine ke difficulty CBT App.
    |
    */

    'level_difficulty_map' => [
        1 => 'mudah',
        2 => 'mudah',
        3 => 'sedang',
        4 => 'sedang',
        5 => 'sedang',
        6 => 'sulit',
        7 => 'sulit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi domain soal matematika yang didukung.
    |
    */

    'domains' => [
        'arithmetic' => [
            'name' => 'Aritmatika',
            'requires_number_type' => true,
            'operations' => [
                'addition', 'subtraction', 'multiplication', 'division',
                'power', 'root', 'modulo', 'gcd', 'lcm', 'mixed',
                'comparison', 'ordering', 'factorization',
            ],
        ],
        'geometry' => [
            'name' => 'Geometri',
            'requires_number_type' => false,
            'shapes' => [
                'square', 'rectangle', 'triangle', 'circle',
                'cube', 'cuboid', 'cylinder', 'cone', 'sphere',
            ],
        ],
        'algebra' => [
            'name' => 'Aljabar',
            'requires_number_type' => false,
        ],
        'measurement' => [
            'name' => 'Pengukuran',
            'requires_number_type' => false,
        ],
        'statistics' => [
            'name' => 'Statistika',
            'requires_number_type' => false,
        ],
        'angles' => [
            'name' => 'Sudut',
            'requires_number_type' => false,
            'types' => [
                'complementary', 'supplementary', 'vertical',
                'interior', 'exterior',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'score' => 2,
        'timer' => 60,
        'distractor_count' => 3,
        'count' => 1,
    ],

];
