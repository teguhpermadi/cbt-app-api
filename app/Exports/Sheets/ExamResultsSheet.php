<?php

namespace App\Exports\Sheets;

use App\Models\Exam;
use App\Models\ExamResult;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExamResultsSheet implements FromCollection, WithTitle, WithEvents
{
    protected Exam $exam;
    protected array $passFailMap = [];

    public function __construct(Exam $exam)
    {
        $this->exam = $exam;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $results = ExamResult::where('exam_id', $this->exam->id)
            ->with(['user'])
            ->get();

        $rows = collect();

        // 1. Statistics (Rows 1-6)
        $avgScore = $results->avg('final_score');
        $maxScore = $results->max('final_score');
        $minScore = $results->min('final_score');
        
        $highestStudents = $results->where('final_score', $maxScore)->map(fn($r) => $r->user?->name)->filter()->implode(', ');
        $lowestStudents = $results->where('final_score', $minScore)->map(fn($r) => $r->user?->name)->filter()->implode(', ');

        $passingGrade = $this->exam->passing_score ?? 0;
        $passedCount = $results->where('final_score', '>=', $passingGrade)->count();
        $failedCount = $results->where('final_score', '<', $passingGrade)->count();

        $rows->push(['STATISTIK RINGKASAN']);
        $rows->push(['Nilai Tertinggi', $maxScore, '(' . $highestStudents . ')']);
        $rows->push(['Nilai Terendah', $minScore, '(' . $lowestStudents . ')']);
        $rows->push(['Nilai Rata-rata', round((float)$avgScore, 2)]);
        $rows->push(['Lulus (>= ' . $passingGrade . ')', $passedCount . ' Siswa']);
        $rows->push(['Tidak Lulus (< ' . $passingGrade . ')', $failedCount . ' Siswa']);

        // 2. Spacers (Rows 7-8)
        $rows->push([null]);
        $rows->push([null]);

        // 3. Table Headings (Row 9)
        $rows->push([
            'ID',
            'Student Name',
            'Student Email',
            'Total Score',
            'Score Percent',
            'Final Score',
            'Status (Passed)',
            'Result Type',
            'Created At',
        ]);

        // 4. Student Data (Row 10+)
        $currentRow = 10;
        foreach ($results as $row) {
            $isPassed = $row->final_score >= $passingGrade;
            $this->passFailMap[$currentRow] = $isPassed;

            $rows->push([
                $row->id,
                $row->user?->name,
                $row->user?->email,
                $row->total_score,
                $row->score_percent,
                $row->final_score,
                $isPassed ? 'Passed' : 'Failed',
                $row->result_type?->value,
                $row->created_at->format('Y-m-d H:i:s'),
            ]);
            $currentRow++;
        }

        return $rows;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Results';
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                
                // Statistics styling (top 6 rows)
                $sheet->getStyle('A1:C6')->getFont()->setBold(true);
                $sheet->getStyle('A1')->getFont()->setSize(12)->setUnderline(true);
                
                // Header row styling (Row 9)
                $sheet->getStyle('A9:' . $highestColumn . '9')->getFont()->setBold(true);
                
                // Conditional coloring for data rows (Row 10+)
                foreach ($this->passFailMap as $rowIndex => $isPassed) {
                    if ($isPassed) {
                        // Light Green
                        $sheet->getStyle('A' . $rowIndex . ':' . $highestColumn . $rowIndex)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('C6EFCE');
                    } else {
                        // Light Red/Pink
                        $sheet->getStyle('A' . $rowIndex . ':' . $highestColumn . $rowIndex)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFC7CE');
                    }
                }

                // Auto-size columns
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                for ($i = 1; $i <= $highestColumnIndex; $i++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                }
            },
        ];
    }
}
