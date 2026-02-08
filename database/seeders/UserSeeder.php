<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::factory(2)->admin()->create();
        $teacher = User::factory(5)->teacher()->create();
        $student = User::factory(10)->student()->create();
    }
}
