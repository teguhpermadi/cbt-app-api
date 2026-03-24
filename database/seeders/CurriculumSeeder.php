<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Curriculum;
use Illuminate\Database\Seeder;

final class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $curricula = array_merge(
            $this->getKurikulumMerdeka(),
            $this->getKurikulum2013()
        );

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
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta dapat melakukan penjumlahan dan pengurangan bilangan cacah sampai 20', 'order' => 2],
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
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'BI-CO-1', 'description' => 'Peserta dapat memahami teks pendek sederhana', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta dapat menulis kalimat sederhana', 'order' => 2],
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
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta dapat memahami bilangan cacah sampai 10.000', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta dapat melakukan operasi hitung bilangan cacah', 'order' => 2],
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
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'BI-CO-1', 'description' => 'Peserta dapat memahami teks nonsastra', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta dapat menulis karangan sederhana', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta dapat memahami bilangan bulat dan pecahan', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta dapat memahami bangun datar dan bangun ruang', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'IPA',
                        'code' => 'IPA',
                        'description' => 'Ilmu Pengetahuan Alam',
                        'learning_outcomes' => [
                            ['code' => 'IPA-CO-1', 'description' => 'Peserta dapat memahami makhluk hidup dan proses kehidupan', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase D SMP Kelas 7-9',
                'code' => 'KM-SMP-FD-2024',
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'BI-CO-1', 'description' => 'Peserta dapat memahami berbagai teks nonsastra', 'order' => 1],
                            ['code' => 'BI-CO-2', 'description' => 'Peserta dapat menulis teks nonsastra', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta dapat memahami bilangan bulat, bilangan rasional dan irasional', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta dapat memahami aljabar', 'order' => 2],
                            ['code' => 'MTK-CO-3', 'description' => 'Peserta dapat memahami geometri dan pengukuran', 'order' => 3],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'IPA',
                        'code' => 'IPA',
                        'description' => 'Ilmu Pengetahuan Alam',
                        'learning_outcomes' => [
                            ['code' => 'IPA-CO-1', 'description' => 'Peserta dapat memahami gejala alam dan materi', 'order' => 1],
                            ['code' => 'IPA-CO-2', 'description' => 'Peserta dapat memahami tubuh manusia dan kesehatan', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase E SMA Kelas 10-11',
                'code' => 'KM-SMA-FE-2024',
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'BI-CO-1', 'description' => 'Peserta dapat menganalisis berbagai teks', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta dapat memahami bilangan dan aljabar', 'order' => 1],
                            ['code' => 'MTK-CO-2', 'description' => 'Peserta dapat memahami kalkulus dasar', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Fisika',
                        'code' => 'FIS',
                        'description' => 'Mata pelajaran Fisika',
                        'learning_outcomes' => [
                            ['code' => 'FIS-CO-1', 'description' => 'Peserta dapat memahami mekanika', 'order' => 1],
                            ['code' => 'FIS-CO-2', 'description' => 'Peserta dapat memahami energi dan perubahan', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum Merdeka - Fase F SMA Kelas 12',
                'code' => 'KM-SMA-FF-2024',
                'curriculum_type' => 'Merdeka',
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
                            ['code' => 'BI-CO-1', 'description' => 'Peserta dapat memahami dan menghasilkan teks akademik', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika',
                        'learning_outcomes' => [
                            ['code' => 'MTK-CO-1', 'description' => 'Peserta dapat memahami statistika dan peluang', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
        ];
    }

    private function getKurikulum2013(): array
    {
        return [
            [
                'name' => 'Kurikulum 2013 - SD Kelas 1',
                'code' => 'K13-SD-1-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 1',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 1, 'max' => 1],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-1-1', 'description' => 'Peserta dapat mengenal huruf-huruf abjad', 'order' => 1],
                            ['code' => 'K13-BI-1-2', 'description' => 'Peserta dapat membaca suku kata', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-1-1', 'description' => 'Peserta dapat mengenal bilangan 1-10', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SD Kelas 2',
                'code' => 'K13-SD-2-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 2',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 2, 'max' => 2],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-2-1', 'description' => 'Peserta dapat membaca kata-kata', 'order' => 1],
                            ['code' => 'K13-BI-2-2', 'description' => 'Peserta dapat menulis kalimat sederhana', 'order' => 2],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-2-1', 'description' => 'Peserta dapat menghitung bilangan sampai 100', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SD Kelas 3',
                'code' => 'K13-SD-3-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 3',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 3, 'max' => 3],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-3-1', 'description' => 'Peserta dapat memahami teks pendek', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-3-1', 'description' => 'Peserta dapat memahami perkalian dan pembagian', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SD Kelas 4',
                'code' => 'K13-SD-4-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 4',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 4, 'max' => 4],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-4-1', 'description' => 'Peserta dapat memahami teks sejarah', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-4-1', 'description' => 'Peserta dapat memahami pecahan', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SD Kelas 5',
                'code' => 'K13-SD-5-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 5',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 5, 'max' => 5],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-5-1', 'description' => 'Peserta dapat memahami teks pidato', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-5-1', 'description' => 'Peserta dapat memahamivolume', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SD Kelas 6',
                'code' => 'K13-SD-6-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SD Kelas 6',
                'phase' => null,
                'level' => 'SD',
                'grade_range' => ['min' => 6, 'max' => 6],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-6-1', 'description' => 'Peserta dapat memahami teks eksplanasi', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-6-1', 'description' => 'Peserta dapat memahami bilangan bulat negatif', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMP Kelas 7',
                'code' => 'K13-SMP-7-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMP Kelas 7',
                'phase' => null,
                'level' => 'SMP',
                'grade_range' => ['min' => 7, 'max' => 7],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-7-1', 'description' => 'Peserta dapat memahami teks cerita reimbo', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-7-1', 'description' => 'Peserta dapat memahami bilangan bulat', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMP Kelas 8',
                'code' => 'K13-SMP-8-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMP Kelas 8',
                'phase' => null,
                'level' => 'SMP',
                'grade_range' => ['min' => 8, 'max' => 8],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-8-1', 'description' => 'Peserta dapat memahami teks prosedur', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-8-1', 'description' => 'Peserta dapat memahami aljabar', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMP Kelas 9',
                'code' => 'K13-SMP-9-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMP Kelas 9',
                'phase' => null,
                'level' => 'SMP',
                'grade_range' => ['min' => 9, 'max' => 9],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-9-1', 'description' => 'Peserta dapat memahami teks artikel', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-9-1', 'description' => 'Peserta dapat memahami statistika', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMA Kelas 10',
                'code' => 'K13-SMA-10-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMA Kelas 10',
                'phase' => null,
                'level' => 'SMA',
                'grade_range' => ['min' => 10, 'max' => 10],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-10-1', 'description' => 'Peserta dapat menganalisis teks filosofi', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-10-1', 'description' => 'Peserta dapat memahami eksponen dan logaritma', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMA Kelas 11',
                'code' => 'K13-SMA-11-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMA Kelas 11',
                'phase' => null,
                'level' => 'SMA',
                'grade_range' => ['min' => 11, 'max' => 11],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-11-1', 'description' => 'Peserta dapat memahami teks sastra', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-11-1', 'description' => 'Peserta dapat memahami limit fungsi', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
            [
                'name' => 'Kurikulum 2013 - SMA Kelas 12',
                'code' => 'K13-SMA-12-2024',
                'curriculum_type' => 'K2013',
                'description' => 'Kurikulum 2013 untuk SMA Kelas 12',
                'phase' => null,
                'level' => 'SMA',
                'grade_range' => ['min' => 12, 'max' => 12],
                'academic_year' => '2024/2025',
                'is_active' => true,
                'subjects' => [
                    [
                        'name' => 'Bahasa Indonesia',
                        'code' => 'BI',
                        'description' => 'Mata pelajaran Bahasa Indonesia Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-BI-12-1', 'description' => 'Peserta dapat membuat karya ilmiah', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                    [
                        'name' => 'Matematika',
                        'code' => 'MTK',
                        'description' => 'Mata pelajaran Matematika Kurikulum 2013',
                        'learning_outcomes' => [
                            ['code' => 'K13-MTK-12-1', 'description' => 'Peserta dapat memahami integral', 'order' => 1],
                        ],
                        'learning_objectives' => [],
                    ],
                ],
            ],
        ];
    }
}
