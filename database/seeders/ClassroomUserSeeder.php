<?php

namespace Database\Seeders;

use App\Models\ClassroomUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassroomUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ClassroomUser::factory(10)->create();
    }
}
