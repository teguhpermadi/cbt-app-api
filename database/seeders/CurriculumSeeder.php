<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Curriculum;
use Illuminate\Database\Seeder;

final class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $curricula = $this->getKurikulumMerdeka();

        foreach ($curricula as $curriculumData) {
            Curriculum::updateOrCreate(
                ['code' => $curriculumData['code']],
                $curriculumData
            );
        }
    }

    private function getKurikulumMerdeka(): array
    {
        return [
            [
                'name' => 'Kurikulum Merdeka - Fase A SD Kelas 1-2',
                'code' => 'KM-SD-FA-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SD Fase A (Kelas 1-2)',
                'phase' => 'Fase A',
                'level' => 'SD',
                'grade_range' => ['min' => 1, 'max' => 2],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat mendengarkan dan memahami percakapan sederhana dalam Bahasa Indonesia', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta didik dapat berbicara dengan kosakata yang sederhana', 'order' => 2],
                            ['code' => 'BI-CO-3', 'description' => 'Peserta didik dapat membaca huruf dan kata sederhana', 'order' => 3],
                            ['code' => 'BI-CO-4', 'description' => 'Peserta didik dapat menulis huruf dan kata sederhana', 'order' => 4],
                        ],
                        'learning_objectives' => [
                            ['code' => 'BI-TP-1', 'description' => 'Siswa dapat mendengarkan cerita dari guru', 'order' => 1],
                            ['code' => 'BI-TP-2', 'description' => 'Siswa dapat mengucapkan kata dengan benar', 'order' => 2],
                        ],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami bilangan cacah sampai 20', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta didik dapat melakukan penjumlahan dan pengurangan bilangan cacah sampai 20', 'order' => 2],
                        ],
                        'learning_objectives' => [
                            ['code' => 'MTK-TP-1', 'description' => 'Siswa dapat menghitung benda 1-20', 'order' => 1],
                            ['code' => 'MTK-TP-2', 'description' => 'Siswa dapat menjumlahkan dua bilangan', 'order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase B SD Kelas 3-4',
                'code' => 'KM-SD-FB-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SD Fase B (Kelas 3-4)',
                'phase' => 'Fase B',
                'level' => 'SD',
                'grade_range' => ['min' => 3, 'max' => 4],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat memahami teks pendek sederhana', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta didik dapat menulis kalimat sederhana', 'order' => 2],
                        ],
                        'learning_objectives' => [
                            ['code' => 'BI-TP-1', 'description' => 'Siswa dapat membaca teks 3-5 kalimat', 'order' => 1],
                        ],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami bilangan cacah sampai 10.000', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta didik dapat melakukan operasi hitung bilangan cacah', 'order' => 2],
                        ],
                        'learning_objectives' => [
                            ['code' => 'MTK-TP-1', 'description' => 'Siswa dapat membaca bilangan sampai 10.000', 'order' => 1],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase C SD Kelas 5-6',
                'code' => 'KM-SD-FC-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SD Fase C (Kelas 5-6)',
                'phase' => 'Fase C',
                'level' => 'SD',
                'grade_range' => ['min' => 5, 'max' => 6],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat memahami teks nonsastra', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta didik dapat menulis karangan sederhana', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami bilangan bulat dan pecahan', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta didik dapat memahami bangun datar dan bangun ruang', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'IPA',
                        'code' => 'IPA',
                        'description' => 'Ilmu Pengetahuan Alam',
                        'learning_outcomes' => [
                            ['code' => 'IPA-CO-1', 'description' => 'Peserta didik dapat memahamimakhluk hidup dan proses kehidupan', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase D SMP Kelas 7-9',
                'code' => 'KM-SMP-FD-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SMP Fase D (Kelas 7-9)',
                'phase' => 'Fase D',
                'level' => 'SMP',
                'grade_range' => ['min' => 7, 'max' => 9],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat memahami berbagai teks nonsastra', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta didik dapat menulis teks nonsastra', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami bilangan bulat, bilangan rasional dan irasional', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta didik dapat memahami aljabar', 'order' => 2],
                            ['code' => 'MTK-CO-3', 'description' => 'Peserta didik dapat memahami geometri dan pengukuran', 'order' => 3],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'IPA',
                        'code' => 'IPA',
                        'description' => 'Ilmu Pengetahuan Alam',
                        'learning_outcomes' => [
                            ['code' => 'IPA-CO-1', 'description' => 'Peserta didik dapat memahami gejala alam dan материi', 'order' => 1],
                            ['code' => 'IPA-CO-2', 'description' => 'Peserta didik dapat memahami tubuh manusia dan kesehatan', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase E SMA Kelas 10-11',
                'code' => 'KM-SMA-FE-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SMA Fase E (Kelas 10-11)',
                'phase' => 'Fase E',
                'level' => 'SMA',
                'grade_range' => ['min' => 10, 'max' => 11],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat menganalisis berbagai teks', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami bilangan dan aljabar', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta didik dapat memahami kalkulus dasar', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Fisika',
                        'code' => 'FIS',
                        'description' => 'Mata pelajaran Fisika',
                        'learning_outcomes' => [
                            ['code' => 'FIS-CO-1', 'description' => 'Peserta didik dapat memahami mekanika', 'order' => 1],
                            ['code' => 'FIS-CO-2', 'description' => 'Peserta didik dapat memahami energi dan perubahan', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase F SMA Kelas 12',
                'code' => 'KM-SMA-FF-2024',
                'description' => 'Kurikulum Merdeka untuk jenjang SMA Fase F (Kelas 12)',
                'phase' => 'Fase F',
                'level' => 'SMA',
                'grade_range' => ['min' => 12, 'max' => 12],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia',
                        'learning_outcomes' => [
                            ['code' => 'BI-CO-1', 'description' => 'Peserta didik dapat memahami dan menghasilkan teks akademik', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta didik dapat memahami statistika dan peluang', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
        ];
    }
}
