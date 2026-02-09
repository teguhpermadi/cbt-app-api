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
        $classroomUsers = ClassroomUser::factory(10)->make();

        foreach ($classroomUsers as $classroomUser) {
            try {
                $classroomUser->save();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}
