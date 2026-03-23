<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\Option;
use App\Models\ExamQuestion;
use App\Models\ReadingMaterial;
use App\Models\ExamReadingMaterial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ConvertMojibake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:mojibake {--dry-run} {--verbose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert mojibake text to proper utf-8 and wrap Arabic/Javanese runs with tags';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        self::perform($dry, $this);
        return 0;
    }

    /**
     * Perform the conversion. Can be called statically from routes/console.php for simplicity.
     *
     * @param bool $dry
     * @param null|\Illuminate\Console\Command $output
     * @return void
     */
    public static function perform(bool $dry = false, $output = null): void
    {
        $verbose = false;
        if ($output) {
            // If run via artisan wrapper, $output is the Command instance and has option()
            if (method_exists($output, 'option')) {
                $verbose = (bool) $output->option('verbose');
            }
            $output->info('Starting mojibake conversion' . ($dry ? ' (dry-run)' : '') . ($verbose ? ' --verbose' : ''));
        }

        $fixMojibake = function (string $text): string {
            if ($text === '') return $text;
            $decoded = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                if (substr_count($decoded, '?') === substr_count($text, '?')) {
                    if ($decoded !== $text) return $decoded;
                }
            }
            return $text;
        };

        $wrapLanguageTags = function (string $text): string {
            if ($text === '') return $text;

            // Note: Arabic [ara] tagging was removed as requested by user.
            // Keeping Javanese [jav] tagging for now.

            $javanesePattern = '/([\x{A980}-\x{A9DF}]+)/u';
            if (strpos($text, '[jav]') === false) {
                if (preg_match_all($javanesePattern, $text, $m2) && count($m2[0]) > 0) {
                    $text = preg_replace($javanesePattern, '[jav]$1[/jav]', $text);
                }
            }

            return $text;
        };

        $qChanged = 0;
        $oChanged = 0;
        $eqChanged = 0;

        Question::chunk(100, function ($questions) use (&$qChanged, $dry, $fixMojibake, $wrapLanguageTags, $output, $verbose) {
            foreach ($questions as $q) {
                $contentOrig = $q->content ?? '';
                $contentFixed = $fixMojibake($contentOrig);
                $contentWrapped = $wrapLanguageTags($contentFixed);

                $hintOrig = $q->hint ?? '';
                $hintFixed = $fixMojibake($hintOrig);

                $changed = false;
                if ($contentWrapped !== $contentOrig) {
                    if (!$dry) $q->content = $contentWrapped;
                    $changed = true;
                }
                if ($hintFixed !== $hintOrig) {
                    if (!$dry) $q->hint = $hintFixed;
                    $changed = true;
                }

                if ($changed) {
                    Log::info('convert:mojibake - question updated', ['id' => $q->id]);
                    $qChanged++;
                    if ($verbose && $output) {
                        $output->line("Question {$q->id}: content updated" . ($contentWrapped !== $contentOrig ? " (content changed)" : "") . ($hintFixed !== $hintOrig ? " (hint changed)" : ""));
                    }
                    if (!$dry) {
                        $q->save();
                    }
                }
            }
        });

        Option::chunk(200, function ($options) use (&$oChanged, $dry, $output, $verbose) {
            foreach ($options as $opt) {
                $changed = false;
                try {
                    $changed = $opt->applyMojibakeConversion($dry, $verbose, $output);
                } catch (\Throwable $e) {
                    Log::warning('convert:mojibake - option conversion failed', ['id' => $opt->id, 'error' => $e->getMessage()]);
                }

                if ($changed) {
                    $oChanged++;
                }
            }
        });

        // Also scan ExamQuestion model for stored snapshots
        ExamQuestion::chunk(200, function ($items) use (&$eqChanged, $dry, $output, $verbose) {
            foreach ($items as $item) {
                try {
                    $changed = $item->applyMojibakeConversion($dry, $verbose, $output);
                    if ($changed) {
                        Log::info('convert:mojibake - examquestion updated', ['id' => $item->id]);
                        $eqChanged++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('convert:mojibake - examquestion conversion failed', ['id' => $item->id, 'error' => $e->getMessage()]);
                }
            }
        });

        $rmChanged = 0;
        $ermChanged = 0;

        // ReadingMaterial
        if (Schema::hasTable('reading_materials')) {
            ReadingMaterial::chunk(100, function ($items) use (&$rmChanged, $dry, $fixMojibake, $wrapLanguageTags, $output, $verbose) {
                foreach ($items as $item) {
                    $changed = false;
                    $fields = ['title', 'content'];
                    foreach ($fields as $field) {
                        $orig = $item->$field ?? '';
                        $fixed = $fixMojibake($orig);
                        $wrapped = ($field === 'content') ? $wrapLanguageTags($fixed) : $fixed;
                        if ($wrapped !== $orig) {
                            if (!$dry) $item->$field = $wrapped;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $rmChanged++;
                        if (!$dry) $item->save();
                        if ($verbose && $output) $output->line("ReadingMaterial {$item->id} updated");
                    }
                }
            });
        }

        // ExamReadingMaterial
        if (Schema::hasTable('exam_reading_materials')) {
            ExamReadingMaterial::chunk(100, function ($items) use (&$ermChanged, $dry, $fixMojibake, $wrapLanguageTags, $output, $verbose) {
                foreach ($items as $item) {
                    $changed = false;
                    $fields = ['title', 'content'];
                    foreach ($fields as $field) {
                        $orig = $item->$field ?? '';
                        $fixed = $fixMojibake($orig);
                        $wrapped = ($field === 'content') ? $wrapLanguageTags($fixed) : $fixed;
                        if ($wrapped !== $orig) {
                            if (!$dry) $item->$field = $wrapped;
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $ermChanged++;
                        if (!$dry) $item->save();
                        if ($verbose && $output) $output->line("ExamReadingMaterial {$item->id} updated");
                    }
                }
            });
        }

        if ($output) {
            $output->info("Done. Q: {$qChanged}, O: {$oChanged}, EQ: {$eqChanged}, RM: {$rmChanged}, ERM: {$ermChanged}.");
        }
    }
}
