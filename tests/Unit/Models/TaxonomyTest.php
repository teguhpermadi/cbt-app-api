<?php

declare(strict_types=1);

use App\Models\Taxonomy;

it('has correct connection', function () {
    $taxonomy = new Taxonomy();
    expect($taxonomy->getConnectionName())->toBe('mongodb');
});

it('has correct collection name', function () {
    $taxonomy = new Taxonomy();
    expect($taxonomy->getTable())->toBe('taxonomies');
});

it('has fillable attributes', function () {
    $taxonomy = new Taxonomy();
    expect($taxonomy->getFillable())->toContain('taxonomy_type', 'category', 'code', 'name', 'description', 'order', 'subcategories', 'verbs', 'is_active');
});

it('casts subcategories to array', function () {
    $taxonomy = new Taxonomy(['subcategories' => ['Remember', 'Understand']]);
    expect($taxonomy->subcategories)->toBeArray();
    expect($taxonomy->subcategories)->toBe(['Remember', 'Understand']);
});

it('casts verbs to array', function () {
    $taxonomy = new Taxonomy(['verbs' => ['identify', 'recall', 'list']]);
    expect($taxonomy->verbs)->toBeArray();
    expect($taxonomy->verbs)->toContain('identify');
});

it('casts order to integer', function () {
    $taxonomy = new Taxonomy(['order' => '5']);
    expect($taxonomy->order)->toBeInt();
    expect($taxonomy->order)->toBe(5);
});

it('casts is_active to boolean', function () {
    $taxonomy = new Taxonomy(['is_active' => 'true']);
    expect($taxonomy->is_active)->toBeBool();
    expect($taxonomy->is_active)->toBeTrue();
});

it('validates anderson krathwohl cognitive process structure', function () {
    $data = [
        'taxonomy_type' => 'anderson_krathwohl',
        'category' => 'Cognitive Process',
        'code' => 'CP1',
        'name' => 'Remember',
        'description' => 'Retrieving relevant knowledge from long-term memory',
        'order' => 1,
        'subcategories' => ['Recognizing', 'Recalling'],
        'verbs' => ['identify', 'recall', 'list', 'name', 'recognize'],
        'is_active' => true,
    ];

    $taxonomy = new Taxonomy($data);

    expect($taxonomy->taxonomy_type)->toBe('anderson_krathwohl');
    expect($taxonomy->category)->toBe('Cognitive Process');
    expect($taxonomy->code)->toBe('CP1');
    expect($taxonomy->name)->toBe('Remember');
    expect($taxonomy->order)->toBe(1);
    expect($taxonomy->is_active)->toBeTrue();
});

it('validates bloom taxonomy structure', function () {
    $data = [
        'taxonomy_type' => 'bloom',
        'category' => 'Cognitive',
        'code' => 'K',
        'name' => 'Knowledge',
        'description' => 'Recall of specifics and universals',
        'order' => 1,
        'subcategories' => ['Knowledge of specifics', 'Knowledge of ways and means'],
        'verbs' => ['define', 'list', 'memorize', 'recall'],
        'is_active' => true,
    ];

    $taxonomy = new Taxonomy($data);

    expect($taxonomy->taxonomy_type)->toBe('bloom');
    expect($taxonomy->code)->toBe('K');
});

it('validates solo taxonomy structure', function () {
    $data = [
        'taxonomy_type' => 'solo',
        'category' => 'Level',
        'code' => 'R',
        'name' => 'Relational',
        'description' => 'The different aspects become integrated into a coherent whole',
        'order' => 4,
        'subcategories' => [],
        'verbs' => ['analyze', 'apply', 'compare', 'explain'],
        'is_active' => true,
    ];

    $taxonomy = new Taxonomy($data);

    expect($taxonomy->taxonomy_type)->toBe('solo');
    expect($taxonomy->code)->toBe('R');
    expect($taxonomy->verbs)->toContain('analyze');
});

it('handles empty subcategories and verbs', function () {
    $taxonomy = new Taxonomy([
        'taxonomy_type' => 'solo',
        'category' => 'Level',
        'code' => 'P',
        'name' => 'Prestructural',
        'description' => 'The task is not attacked appropriately',
        'order' => 1,
        'subcategories' => [],
        'verbs' => [],
        'is_active' => true,
    ]);

    expect($taxonomy->subcategories)->toBeArray();
    expect($taxonomy->subcategories)->toBeEmpty();
    expect($taxonomy->verbs)->toBeArray();
    expect($taxonomy->verbs)->toBeEmpty();
});

describe('Taxonomy Scopes', function () {
    it('active scope returns builder', function () {
        $query = Taxonomy::active();
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('byType scope returns builder', function () {
        $query = Taxonomy::byType('bloom');
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('ordered scope returns builder', function () {
        $query = Taxonomy::ordered();
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('byCode scope returns builder', function () {
        $query = Taxonomy::byCode('CP1');
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('cognitiveProcess scope returns builder', function () {
        $query = Taxonomy::cognitiveProcess();
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('knowledge scope returns builder', function () {
        $query = Taxonomy::knowledge();
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });

    it('level scope returns builder', function () {
        $query = Taxonomy::level();
        expect($query)->toBeInstanceOf(MongoDB\Laravel\Eloquent\Builder::class);
    });
});
