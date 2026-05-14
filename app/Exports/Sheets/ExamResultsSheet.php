<?php

namespace App\Exports\Sheets;

use App\Models\Exam;
use App\Models\ExamResult;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ExamResultsSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected Exam $exam;

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

        $rows = $results->map(function ($row) {
            return [
                $row->id,
                $row->user?->name,
                $row->user?->email,
                $row->total_score,
                $row->score_percent,
                $row->final_score,
                $row->is_passed ? 'Passed' : 'Failed',
                $row->result_type?->value,
                $row->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // Add spacers
        $rows->push([]);
        $rows->push([]);

        // Statistics
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
    public function headings(): array
    {
        return [
            'ID',
            'Student Name',
            'Student Email',
            'Total Score',
            'Score Percent',
            'Final Score',
            'Status (Passed)',
            'Result Type',
            'Created At',
        ];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Header styling
                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                
                // Statistics styling (last 6 rows)
                $statsStartRow = $highestRow - 5;
                $sheet->getStyle('A' . ($statsStartRow - 1) . ':C' . $highestRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . ($statsStartRow - 1))->getFont()->setSize(12)->setUnderline(true);

                // Auto-size columns
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
