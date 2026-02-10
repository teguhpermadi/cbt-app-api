<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AcademicYearSeeder::class,
            ClassroomSeeder::class,
            SubjectSeeder::class,
            ClassroomUserSeeder::class,
            // QuestionSeeder::class,
            QuestionBankSeeder::class,
        ]);
    }
}
