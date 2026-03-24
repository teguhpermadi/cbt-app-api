<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\QuestionTypeEnum;
use App\Models\Curriculum;
use App\Models\Taxonomy;

final class QuestionGeneratorContextService
{
    public function getCurriculumContext(?Curriculum $curriculum = null, ?string $subjectCode = null): array
    {
        if (! $curriculum) {
            $curriculum = Curriculum::active()->first();
        }

        if (! $curriculum) {
            return [
                'error' => 'No active curriculum found',
                'curriculum' => null,
            ];
        }

        $context = [
            'curriculum' => [
                'id' => (string) $curriculum->id,
                'name' => $curriculum->name,
                'code' => $curriculum->code,
                'type' => $curriculum->curriculum_type,
                'phase' => $curriculum->phase,
                'level' => $curriculum->level,
                'description' => $curriculum->description,
                'academic_year' => $curriculum->academic_year,
            ],
        ];

        if ($subjectCode) {
            $subject = $curriculum->getSubjectByCode($subjectCode);
            if ($subject) {
                $context['subject'] = $subject;
                $context['learning_outcomes'] = $curriculum->getLearningOutcomesBySubject($subjectCode);
                $context['learning_objectives'] = $curriculum->getLearningObjectivesBySubject($subjectCode);
            }
        }

        return $context;
    }

    public function getTaxonomyContext(?string $taxonomyType = null, ?string $categoryCode = null): array
    {
        $query = Taxonomy::active();

        if ($taxonomyType) {
            $query->byType($taxonomyType);
        }

        $taxonomies = $query->ordered()->get();

        if ($categoryCode) {
            $taxonomy = $taxonomies->firstWhere('code', $categoryCode);
            if ($taxonomy) {
                return [
                    'taxonomy' => [
                        'type' => $taxonomy->taxonomy_type,
                        'category' => $taxonomy->category,
                        'code' => $taxonomy->code,
                        'name' => $taxonomy->name,
                        'description' => $taxonomy->description,
                        'subcategories' => $taxonomy->subcategories,
                        'verbs' => $taxonomy->verbs,
                    ],
                ];
            }
        }

        $grouped = $taxonomies->groupBy('taxonomy_type');

        return [
            'taxonomies' => [
                'anderson_krathwohl' => $this->formatTaxonomyGroup($grouped->get('anderson_krathwohl', collect())),
                'bloom' => $this->formatTaxonomyGroup($grouped->get('bloom', collect())),
                'solo' => $this->formatTaxonomyGroup($grouped->get('solo', collect())),
            ],
        ];
    }

    public function getQuestionTypesContext(): array
    {
        return [
            'question_types' => collect(QuestionTypeEnum::cases())->map(fn ($type) => [
                'value' => $type->value,
                'name' => $type->label(),
                'description' => $type->description(),
            ])->toArray(),
        ];
    }

    public function getFullContextForGeneration(
        ?Curriculum $curriculum = null,
        ?string $subjectCode = null,
        ?string $taxonomyType = null,
        ?string $taxonomyCode = null
    ): array {
        return [
            'curriculum' => $this->getCurriculumContext($curriculum, $subjectCode),
            'taxonomy' => $this->getTaxonomyContext($taxonomyType, $taxonomyCode),
            'question_types' => $this->getQuestionTypesContext(),
        ];
    }

    public function formatForPrompt(array $context): string
    {
        $output = [];

        if (isset($context['curriculum']['curriculum'])) {
            $c = $context['curriculum']['curriculum'];
            $output[] = 'KURIKULUM:';
            $output[] = "- Nama: {$c['name']}";
            $output[] = "- Kode: {$c['code']}";
            $output[] = "- Tipe: {$c['type']}";
            $output[] = "- Fase: {$c['phase']}";
            $output[] = "- Level: {$c['level']}";
            if ($c['description']) {
                $output[] = "- Deskripsi: {$c['description']}";
            }
        }

        if (isset($context['curriculum']['subject'])) {
            $s = $context['curriculum']['subject'];
            $output[] = "\nMATA PELAJARAN:";
            $output[] = "- Kode: {$s['code']}";
            $output[] = '- Nama: '.(is_array($s) ? ($s['name'] ?? 'N/A') : $s);
        }

        if (isset($context['curriculum']['learning_outcomes']) && ! empty($context['curriculum']['learning_outcomes'])) {
            $output[] = "\nLEARNING OUTCOMES:";
            foreach ($context['curriculum']['learning_outcomes'] as $outcome) {
                $desc = is_array($outcome) ? ($outcome['description'] ?? json_encode($outcome)) : $outcome;
                $output[] = "- {$desc}";
            }
        }

        if (isset($context['curriculum']['learning_objectives']) && ! empty($context['curriculum']['learning_objectives'])) {
            $output[] = "\nLEARNING OBJECTIVES:";
            foreach ($context['curriculum']['learning_objectives'] as $objective) {
                $desc = is_array($objective) ? ($objective['description'] ?? json_encode($objective)) : $objective;
                $output[] = "- {$desc}";
            }
        }

        if (isset($context['taxonomy']['taxonomy'])) {
            $t = $context['taxonomy']['taxonomy'];
            $output[] = "\nTAKSONOMI:";
            $output[] = "- Tipe: {$t['type']}";
            $output[] = "- Level: {$t['name']} ({$t['code']})";
            $output[] = "- Deskripsi: {$t['description']}";
            if (! empty($t['verbs']) && is_array($t['verbs'])) {
                $output[] = '- Kata Kerja Operasional: '.implode(', ', $t['verbs']);
            }
            if (! empty($t['subcategories']) && is_array($t['subcategories'])) {
                $output[] = '- Subkategori: '.implode(', ', $t['subcategories']);
            }
        } elseif (isset($context['taxonomy']['taxonomies'])) {
            $output[] = "\nTAKSONOMI YANG TERSEDIA:";
            foreach ($context['taxonomy']['taxonomies'] as $typeName => $levels) {
                if (is_array($levels)) {
                    $output[] = "- {$typeName}:";
                    foreach ($levels as $level) {
                        $code = is_array($level) ? ($level['code'] ?? '') : $level;
                        $name = is_array($level) ? ($level['name'] ?? '') : $level;
                        $output[] = "  - {$code}: {$name}";
                    }
                }
            }
        }

        if (isset($context['question_types']['question_types'])) {
            $output[] = "\nTIPE SOAL YANG TERSEDIA:";
            foreach ($context['question_types']['question_types'] as $qt) {
                $output[] = "- {$qt['value']}: {$qt['description']}";
            }
        }

        return implode("\n", $output);
    }

    private function formatTaxonomyGroup($taxonomies): array
    {
        return $taxonomies->map(fn ($t) => [
            'code' => $t->code,
            'name' => $t->name,
            'category' => $t->category,
            'description' => $t->description,
            'subcategories' => $t->subcategories,
            'verbs' => $t->verbs,
        ])->toArray();
    }
}
