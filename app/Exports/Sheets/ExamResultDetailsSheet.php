<?php

namespace App\Exports\Sheets;

use App\Models\Exam;
use App\Models\ExamResultDetail;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExamResultDetailsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, WithEvents
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
        // Get all detail records for the sessions that are considered "results" for this exam
        return ExamResultDetail::query()
            ->whereHas('examSession', function ($query) {
                $query->where('exam_id', $this->exam->id);
            })
            ->with([
                'examSession.user', 
                'examQuestion.originalQuestion'
            ])
            ->orderBy('exam_session_id')
            ->orderBy('question_number');
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Details';
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Student Name',
            'Question Number',
            'Question Content (Snapshot)',
            'Original Question Content',
            'Student Answer',
            'Key Answer',
            'Is Correct',
            'Score Earned',
            'Time Spent (seconds)',
            'Answered At',
        ];
    }

    /**
     * @param ExamResultDetail $row
     * @return array
     */
    public function map($row): array
    {
        $studentAnswer = is_array($row->student_answer) ? json_encode($row->student_answer) : $row->student_answer;
        $keyAnswer = is_array($row->examQuestion?->key_answer) ? json_encode($row->examQuestion->key_answer) : $row->examQuestion?->key_answer;

        return [
            $row->examSession?->user?->name,
            $row->question_number,
            strip_tags($row->examQuestion?->content ?? ''),
            strip_tags($row->examQuestion?->originalQuestion?->content ?? ''),
            $studentAnswer,
            $keyAnswer,
            $row->is_correct ? 'Yes' : 'No',
            $row->score_earned,
            $row->time_spent,
            $row->answered_at ? $row->answered_at->format('Y-m-d H:i:s') : '-',
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

                for ($row = 2; $row <= $highestRow; $row++) {
                    $isCorrect = $sheet->getCell('G' . $row)->getValue(); // Column G is 'Is Correct'

                    if ($isCorrect === 'Yes') {
                        // Light Green background
                        $sheet->getStyle('A' . $row . ':J' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('C6EFCE');
                    } else {
                        // Light Red/Pink background
                        $sheet->getStyle('A' . $row . ':J' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFC7CE');
                    }
                }

                // Header styling
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
            },
        ];
    }
}
