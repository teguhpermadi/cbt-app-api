<?php

namespace App\Exports\Sheets;

use App\Models\Exam;
use App\Models\ExamResult;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ExamResultsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    protected Exam $exam;

    public function __construct(Exam $exam)
    {
        $this->exam = $exam;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return ExamResult::query()
            ->where('exam_id', $this->exam->id)
            ->with(['user']);
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
     * @param ExamResult $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->user?->name,
            $row->user?->email,
            $row->total_score,
            $row->score_percent,
            $row->final_score,
            $row->is_passed ? 'Passed' : 'Failed',
            $row->result_type,
            $row->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
