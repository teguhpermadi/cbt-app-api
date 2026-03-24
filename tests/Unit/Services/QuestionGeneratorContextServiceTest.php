<?php

declare(strict_types=1);

use App\Services\AI\QuestionGeneratorContextService;

it('returns all question types', function () {
    $service = new QuestionGeneratorContextService();
    $result = $service->getQuestionTypesContext();

    expect($result)->toHaveKey('question_types');
    expect($result['question_types'])->toBeArray();
    expect($result['question_types'])->not()->toBeEmpty();
});

it('includes multiple_choice type', function () {
    $service = new QuestionGeneratorContextService();
    $result = $service->getQuestionTypesContext();
    $types = $result['question_types'];

    $mcTypes = array_filter($types, fn ($t) => $t['value'] === 'multiple_choice');
    expect($mcTypes)->not()->toBeEmpty();
});

it('includes correct label for each type', function () {
    $service = new QuestionGeneratorContextService();
    $result = $service->getQuestionTypesContext();
    $types = $result['question_types'];

    foreach ($types as $type) {
        expect($type)->toHaveKeys(['value', 'name', 'description']);
        expect($type['value'])->toBeString();
        expect($type['name'])->toBeString();
        expect($type['description'])->toBeString();
    }
});

it('formats curriculum context correctly', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'curriculum' => [
            'curriculum' => [
                'name' => 'Kurikulum Merdeka',
                'code' => 'MERDEKA',
                'type' => 'National',
                'phase' => 'Fase A',
                'level' => 'SD',
                'description' => 'Kurikulum Merdeka Belajar',
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('KURIKULUM:');
    expect($result)->toContain('Kurikulum Merdeka');
    expect($result)->toContain('MERDEKA');
    expect($result)->toContain('National');
    expect($result)->toContain('Fase A');
    expect($result)->toContain('SD');
});

it('formats subject context correctly', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'curriculum' => [
            'subject' => [
                'code' => 'MAT',
                'name' => 'Matematika',
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('MATA PELAJARAN:');
    expect($result)->toContain('MAT');
    expect($result)->toContain('Matematika');
});

it('formats learning outcomes correctly', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'curriculum' => [
            'learning_outcomes' => [
                'Memahami konsep pecahan',
                'Menghitung operasi pecahan',
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('LEARNING OUTCOMES:');
    expect($result)->toContain('Memahami konsep pecahan');
    expect($result)->toContain('Menghitung operasi pecahan');
});

it('formats taxonomy context correctly', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'taxonomy' => [
            'taxonomy' => [
                'type' => 'anderson_krathwohl',
                'name' => 'Understand',
                'code' => 'CP2',
                'description' => 'Determining the meaning',
                'verbs' => ['explain', 'describe', 'interpret'],
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('TAKSONOMI:');
    expect($result)->toContain('anderson_krathwohl');
    expect($result)->toContain('Understand');
    expect($result)->toContain('CP2');
    expect($result)->toContain('explain');
});

it('formats question types in prompt', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'question_types' => [
            'question_types' => [
                ['value' => 'multiple_choice', 'name' => 'Multiple Choice', 'description' => 'Pilihan ganda'],
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('TIPE SOAL YANG TERSEDIA:');
    expect($result)->toContain('multiple_choice');
    expect($result)->toContain('Pilihan ganda');
});

it('handles missing curriculum gracefully', function () {
    $service = new QuestionGeneratorContextService();
    $context = [];

    $result = $service->formatForPrompt($context);

    expect($result)->toBeString();
    expect($result)->toBeEmpty();
});

it('combines all context sections', function () {
    $service = new QuestionGeneratorContextService();
    $context = [
        'curriculum' => [
            'curriculum' => [
                'name' => 'Test Curriculum',
                'code' => 'TEST',
                'type' => 'National',
                'phase' => 'Fase A',
                'level' => 'SD',
                'description' => 'Test Description',
            ],
            'subject' => [
                'code' => 'MAT',
                'name' => 'Math',
            ],
            'learning_outcomes' => ['Outcome 1'],
        ],
        'taxonomy' => [
            'taxonomy' => [
                'type' => 'bloom',
                'name' => 'Knowledge',
                'code' => 'K',
                'description' => 'Test',
                'verbs' => ['define'],
            ],
        ],
        'question_types' => [
            'question_types' => [
                ['value' => 'multiple_choice', 'name' => 'MC', 'description' => 'MCQ'],
            ],
        ],
    ];

    $result = $service->formatForPrompt($context);

    expect($result)->toContain('KURIKULUM:');
    expect($result)->toContain('MATA PELAJARAN:');
    expect($result)->toContain('LEARNING OUTCOMES:');
    expect($result)->toContain('TAKSONOMI:');
    expect($result)->toContain('TIPE SOAL YANG TERSEDIA:');
});
