<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Taxonomy;
use Illuminate\Database\Seeder;

final class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        Taxonomy::truncate();

        $taxonomies = $this->getTaxonomyData();

        foreach ($taxonomies as $taxonomy) {
            Taxonomy::create($taxonomy);
        }

        $this->command->info('Taxonomies seeded successfully!');
        $this->command->info('Total taxonomies: '.count($taxonomies));
    }

    private function getTaxonomyData(): array
    {
        return [
            ...$this->getAndersonKrathwohlCognitiveProcess(),
            ...$this->getAndersonKrathwohlKnowledge(),
            ...$this->getBloomTaxonomy(),
            ...$this->getSoloTaxonomy(),
        ];
    }

    private function getAndersonKrathwohlCognitiveProcess(): array
    {
        return [
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP1',
                'name' => 'Remember',
                'description' => 'Retrieving relevant knowledge from long-term memory',
                'order' => 1,
                'subcategories' => ['Recognizing', 'Recalling'],
                'verbs' => ['identify', 'recall', 'list', 'name', 'recognize', 'quote', 'recite', 'reproduce', 'define', 'duplicate', 'memorize', 'repeat', 'state'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP2',
                'name' => 'Understand',
                'description' => 'Determining the meaning of instructional messages',
                'order' => 2,
                'subcategories' => ['Interpreting', 'Exemplifying', 'Classifying', 'Summarizing', 'Inferring', 'Comparing', 'Explaining'],
                'verbs' => ['explain', 'describe', 'interpret', 'summarize', 'compare', 'contrast', 'classify', 'categorize', 'paraphrase', 'translate', 'discuss', 'predict', 'relate', 'distinguish', 'exemplify'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP3',
                'name' => 'Apply',
                'description' => 'Carrying out or using a procedure in a given situation',
                'order' => 3,
                'subcategories' => ['Executing', 'Implementing'],
                'verbs' => ['execute', 'implement', 'solve', 'demonstrate', 'compute', 'operate', 'use', 'apply', 'perform', 'interpret', 'construct', 'modify', 'prepare', 'produce', 'show'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP4',
                'name' => 'Analyze',
                'description' => 'Breaking material into its constituent parts and detecting how the parts relate to one another',
                'order' => 4,
                'subcategories' => ['Differentiating', 'Organizing', 'Attributing'],
                'verbs' => ['analyze', 'differentiate', 'compare', 'contrast', 'organize', 'deconstruct', 'distinguish', 'examine', 'experiment', 'question', 'test', 'discriminate', 'select', ' структурувати'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP5',
                'name' => 'Evaluate',
                'description' => 'Making judgments based on criteria and standards',
                'order' => 5,
                'subcategories' => ['Checking', 'Critiquing'],
                'verbs' => ['evaluate', 'justify', 'critique', 'judge', 'assess', 'defend', 'recommend', 'appraise', 'argue', 'determine', 'select', 'choose', 'rate', 'weigh'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Cognitive Process',
                'code' => 'CP6',
                'name' => 'Create',
                'description' => 'Putting elements together to form a novel, coherent whole',
                'order' => 6,
                'subcategories' => ['Generating', 'Planning', 'Producing'],
                'verbs' => ['create', 'design', 'construct', 'develop', 'formulate', 'produce', 'compose', 'author', 'invent', 'hypothesize', 'plan', 'prepare', 'propose', 'develop', 'revise'],
                'is_active' => true,
            ],
        ];
    }

    private function getAndersonKrathwohlKnowledge(): array
    {
        return [
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Knowledge',
                'code' => 'KD1',
                'name' => 'Factual Knowledge',
                'description' => 'The basic elements that students must know to be acquainted with a discipline',
                'order' => 7,
                'subcategories' => ['Knowledge of terminology', 'Knowledge of specific facts'],
                'verbs' => ['define', 'list', 'recall', 'identify', 'recognize', 'name', 'quote', 'state', 'describe', 'label', 'select', 'match'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Knowledge',
                'code' => 'KD2',
                'name' => 'Conceptual Knowledge',
                'description' => 'The interrelationships among the basic elements within a larger structure',
                'order' => 8,
                'subcategories' => ['Knowledge of classifications and categories', 'Knowledge of principles and generalizations', 'Knowledge of theories, models, and structures'],
                'verbs' => ['classify', 'explain', 'compare', 'relate', 'distinguish', 'differentiate', 'categorize', 'summarize', 'describe', 'interpret', 'model'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Knowledge',
                'code' => 'KD3',
                'name' => 'Procedural Knowledge',
                'description' => 'How to do something; methods of inquiry, and criteria for using skills',
                'order' => 9,
                'subcategories' => ['Knowledge of subject-specific skills and algorithms', 'Knowledge of subject-specific techniques and methods', 'Knowledge of criteria for determining when to use appropriate procedures'],
                'verbs' => ['apply', 'execute', 'demonstrate', 'solve', 'use', 'implement', 'perform', 'calculate', 'compute', 'operate', 'illustrate', 'show'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'anderson_krathwohl',
                'category' => 'Knowledge',
                'code' => 'KD4',
                'name' => 'Metacognitive Knowledge',
                'description' => 'Knowledge of cognition in general as well as awareness and knowledge of one\'s own cognition',
                'order' => 10,
                'subcategories' => ['Strategic knowledge', 'Knowledge about cognitive tasks', 'Self-knowledge'],
                'verbs' => ['plan', 'monitor', 'evaluate', 'reflect', 'self-regulate', 'think', 'critique', 'assess', 'justify', 'review', 'predict', 'set goals'],
                'is_active' => true,
            ],
        ];
    }

    private function getBloomTaxonomy(): array
    {
        return [
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'K',
                'name' => 'Knowledge',
                'description' => 'Recall of specifics and universals, involving recall of a wide body of basic knowledge',
                'order' => 1,
                'subcategories' => ['Knowledge of specifics', 'Knowledge of ways and means', 'Knowledge of universals and abstractions'],
                'verbs' => ['define', 'list', 'memorize', 'recall', 'recognize', 'identify', 'name', 'state', 'repeat', 'quote', 'record', 'describe'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'C',
                'name' => 'Comprehension',
                'description' => 'Understanding of basic meaning, grasping the meaning of information',
                'order' => 2,
                'subcategories' => ['Translation', 'Interpretation', 'Extrapolation'],
                'verbs' => ['classify', 'describe', 'discuss', 'explain', 'identify', 'locate', 'recognize', 'report', 'translate', 'paraphrase', 'summarize', 'interpret', 'express'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'A',
                'name' => 'Application',
                'description' => 'Use of information in new situations, applying learned material to practical problems',
                'order' => 3,
                'subcategories' => ['Use of abstractions in concrete situations'],
                'verbs' => ['apply', 'demonstrate', 'interpret', 'solve', 'use', 'operate', 'execute', 'implement', 'compute', 'construct', 'illustrate', 'show', 'demonstrate', 'practice'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'AN',
                'name' => 'Analysis',
                'description' => 'Breaking down information into constituent parts, identifying relationships',
                'order' => 4,
                'subcategories' => ['Analysis of elements', 'Analysis of relationships', 'Analysis of organization'],
                'verbs' => ['analyze', 'compare', 'contrast', 'differentiate', 'examine', 'experiment', 'question', 'test', 'distinguish', 'diagram', 'categorize', 'debate', 'dissect'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'E',
                'name' => 'Evaluation',
                'description' => 'Making judgments based on given criteria, defending opinions',
                'order' => 5,
                'subcategories' => ['Judgments according to internal evidence', 'Judgments according to external criteria'],
                'verbs' => ['argue', 'assess', 'criticize', 'evaluate', 'justify', 'recommend', 'judge', 'select', 'support', 'value', 'weigh', 'defend', 'appraise'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'bloom',
                'category' => 'Cognitive',
                'code' => 'S',
                'name' => 'Synthesis',
                'description' => 'Putting elements together in new patterns, producing original products',
                'order' => 6,
                'subcategories' => ['Production of a unique communication', 'Production of a plan', 'Derivation of a set of abstract relations'],
                'verbs' => ['create', 'design', 'develop', 'formulate', 'compose', 'construct', 'author', 'write', 'originate', 'produce', 'propose', 'arrange', 'assemble', 'organize'],
                'is_active' => true,
            ],
        ];
    }

    private function getSoloTaxonomy(): array
    {
        return [
            [
                'taxonomy_type' => 'solo',
                'category' => 'Level',
                'code' => 'P',
                'name' => 'Prestructural',
                'description' => 'The task is not attacked appropriately; the student misses the point',
                'order' => 1,
                'subcategories' => [],
                'verbs' => [],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'solo',
                'category' => 'Level',
                'code' => 'U',
                'name' => 'Unistructural',
                'description' => 'The student response only focuses on one relevant aspect',
                'order' => 2,
                'subcategories' => [],
                'verbs' => ['identify', 'name', 'follow', 'locate', 'find', 'point to', 'show', 'copy', 'do simple procedure'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'solo',
                'category' => 'Level',
                'code' => 'M',
                'name' => 'Multistructural',
                'description' => 'The response focuses on several relevant but independent aspects',
                'order' => 3,
                'subcategories' => [],
                'verbs' => ['combine', 'describe', 'enumerate', 'list', 'perform', 'recall', 'relate', 'tell', 'explain', 'summarize', 'classify', 'organize'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'solo',
                'category' => 'Level',
                'code' => 'R',
                'name' => 'Relational',
                'description' => 'The different aspects become integrated into a coherent whole',
                'order' => 4,
                'subcategories' => [],
                'verbs' => ['analyze', 'apply', 'compare', 'explain', 'relate', 'justify', 'contrast', 'differentiate', 'discuss', 'interpret', 'review', 'explain causes', 'theorize'],
                'is_active' => true,
            ],
            [
                'taxonomy_type' => 'solo',
                'category' => 'Level',
                'code' => 'EA',
                'name' => 'Extended Abstract',
                'description' => 'The integrated whole may be conceptualized at a higher level of abstraction',
                'order' => 5,
                'subcategories' => [],
                'verbs' => ['create', 'formulate', 'generate', 'hypothesize', 'theorize', 'reflect', 'predict', 'design', 'invent', 'produce', 'construct', 'originate', 'imagine', 'evaluate'],
                'is_active' => true,
            ],
        ];
    }
}
