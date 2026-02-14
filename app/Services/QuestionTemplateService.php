<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

class QuestionTemplateService
{
    /**
     * Generate template Word document for question upload
     *
     * @return string Path to generated template file
     */
    public function generateTemplate(): string
    {
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('CBT App');
        $properties->setTitle('Template Upload Soal');
        $properties->setDescription('Template untuk upload soal ke bank soal');

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
        ]);

        // Add title
        $section->addText(
            'Template Upload Soal - Bank Soal CBT',
            ['size' => 16, 'bold' => true],
            ['alignment' => 'center']
        );

        $section->addTextBreak(1);

        // Add instructions
        $section->addText('Petunjuk Penggunaan:', ['size' => 12, 'bold' => true]);
        $section->addText('1. Isi tabel di bawah dengan soal-soal Anda', ['size' => 10]);
        $section->addText('2. Kolom yang diperlukan: Tipe Soal, Pertanyaan, Opsi, Kunci, Poin, Tags', ['size' => 10]);
        $section->addText('3. Untuk opsi, pisahkan dengan ENTER (line break)', ['size' => 10]);
        $section->addText('4. Tipe soal yang didukung: MULTIPLE_CHOICE, MULTIPLE_SELECTION, TRUE_FALSE, MATCHING, ORDERING, ESSAY, NUMERICAL_INPUT', ['size' => 10]);
        $section->addText('5. Kolom Tags: Pisahkan tag dengan koma (contoh: Matematika, Trigonometri, Mudah)', ['size' => 10]);
        $section->addText('6. Simpan file dan upload melalui menu Bank Soal', ['size' => 10]);

        $section->addTextBreak(1);

        // Create table style
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'alignment' => JcTable::CENTER,
            'width' => 100 * 50, // 100% width
        ];

        $phpWord->addTableStyle('QuestionTable', $tableStyle);

        // Create table
        $table = $section->addTable('QuestionTable');

        // Header row
        $table->addRow(500);
        $table->addCell(2000, ['bgColor' => 'CCCCCC'])->addText('Tipe Soal', ['bold' => true], ['alignment' => 'center']);
        $table->addCell(3500, ['bgColor' => 'CCCCCC'])->addText('Pertanyaan', ['bold' => true], ['alignment' => 'center']);
        $table->addCell(2500, ['bgColor' => 'CCCCCC'])->addText('Opsi', ['bold' => true], ['alignment' => 'center']);
        $table->addCell(1500, ['bgColor' => 'CCCCCC'])->addText('Kunci', ['bold' => true], ['alignment' => 'center']);
        $table->addCell(800, ['bgColor' => 'CCCCCC'])->addText('Poin', ['bold' => true], ['alignment' => 'center']);
        $table->addCell(2000, ['bgColor' => 'CCCCCC'])->addText('Tags', ['bold' => true], ['alignment' => 'center']);

        // Example 1: Multiple Choice
        $table->addRow();
        $table->addCell(2000)->addText('MULTIPLE_CHOICE');
        $cell = $table->addCell(3500);
        $cell->addText('Perhatikan gambar segitiga. Tentukan panjang sisi miring jika sisi tegak adalah ');
        $cell->addText('$a=3$ dan alas $b=4$ menggunakan rumus $c = \sqrt{a^2 + b^2}$');

        $optionsCell = $table->addCell(2500);
        $optionsCell->addText('A. 5 cm');
        $optionsCell->addText('B. 7 cm');
        $optionsCell->addText('C. 9 cm');
        $optionsCell->addText('D. 12 cm');

        $table->addCell(1500)->addText('A');
        $table->addCell(800)->addText('10');
        $table->addCell(2000)->addText('Matematika, Geometri, Mudah');

        // Example 2: Multiple Selection
        $table->addRow();
        $table->addCell(2000)->addText('MULTIPLE_SELECTION');
        $table->addCell(3500)->addText('Manakah di bawah ini yang merupakan bilangan prima?');

        $optionsCell = $table->addCell(2500);
        $optionsCell->addText('A. 2');
        $optionsCell->addText('B. 4');
        $optionsCell->addText('C. 9');
        $optionsCell->addText('D. 11');
        $optionsCell->addText('E. 15');

        $table->addCell(1500)->addText('A, D');
        $table->addCell(800)->addText('10');
        $table->addCell(2000)->addText('Matematika, Bilangan');

        // Example 3: True/False
        $table->addRow();
        $table->addCell(2000)->addText('TRUE_FALSE');
        $cell = $table->addCell(3500);
        $cell->addText('Besaran turunan dari ');
        $cell->addText('$\frac{kg \cdot m}{s^2}$ adalah Newton.');
        $optionsCell = $table->addCell(2500);
        $optionsCell->addText('TRUE');
        $optionsCell->addText('FALSE');
        $table->addCell(1500)->addText('TRUE');
        $table->addCell(800)->addText('5');
        $table->addCell(2000)->addText('Fisika, Besaran, Satuan');

        // Example 4: Matching
        $table->addRow();
        $table->addCell(2000)->addText('MATCHING');
        $table->addCell(3500)->addText('Pasangkan rumus bangun datar berikut dengan luasnya.');

        $optionsCell = $table->addCell(2500);
        $optionsCell->addText('Persegi ($s \times s$) :: $L = s^2$');
        $optionsCell->addText('Lingkaran ($\pi \times r \times r$) :: $L = \pi r^2$');
        $optionsCell->addText('Segitiga :: $L = \frac{1}{2} a \cdot t$');

        $table->addCell(1500)->addText('-');
        $table->addCell(800)->addText('15');
        $table->addCell(2000)->addText('Matematika, Rumus');

        // Example 5: Ordering
        $table->addRow();
        $table->addCell(2000)->addText('ORDERING');
        $table->addCell(3500)->addText('Urutkan langkah-langkah metode ilmiah berikut ini:');

        $optionsCell = $table->addCell(2500);
        $optionsCell->addText('1. Merumuskan Masalah');
        $optionsCell->addText('2. Menyusun Hipotesis');
        $optionsCell->addText('3. Melakukan Eksperimen');
        $optionsCell->addText('4. Menarik Kesimpulan');

        $table->addCell(1500)->addText('-');
        $table->addCell(800)->addText('10');
        $table->addCell(2000)->addText('Sains, Metode Ilmiah');

        // Example 6: Essay
        $table->addRow();
        $table->addCell(2000)->addText('ESSAY');
        $cell = $table->addCell(3500);
        $cell->addText('Jelaskan mengapa ');
        $cell->addText('$\lim_{x \to 0} \frac{\sin x}{x} = 1$');
        $table->addCell(2500)->addText('-');
        $table->addCell(1500)->addText('Gunakan aturan L\'Hopital atau ekspansi deret Taylor.');
        $table->addCell(800)->addText('20');
        $table->addCell(2000)->addText('Matematika, Kalkulus, Pembuktian');

        // Example 7: Numerical Input
        $table->addRow();
        $table->addCell(2000)->addText('NUMERICAL_INPUT');
        $cell = $table->addCell(3500);
        $cell->addText('Hitung nilai dari ');
        $cell->addText('$\frac{3}{4} + \frac{2}{5}$');
        $table->addCell(2500)->addText('-');
        $table->addCell(1500)->addText('1.15');
        $table->addCell(800)->addText('15');
        $table->addCell(2000)->addText('Matematika, Pecahan, Aritmatika');

        // Add empty rows for user to fill
        for ($i = 0; $i < 5; $i++) {
            $table->addRow(800);
            $table->addCell(2000);
            $table->addCell(3500);
            $table->addCell(2500);
            $table->addCell(1500);
            $table->addCell(800);
            $table->addCell(2000);
        }

        // Save file
        $fileName = 'template_upload_soal.docx';
        $filePath = storage_path('templates/' . $fileName);

        // Ensure directory exists
        if (!file_exists(storage_path('templates'))) {
            mkdir(storage_path('templates'), 0755, true);
        }

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filePath);

        return $filePath;
    }
}
