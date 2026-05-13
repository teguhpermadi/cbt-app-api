<?php

namespace App\Exports;

use App\Exports\Sheets\ExamResultDetailsSheet;
use App\Exports\Sheets\ExamResultsSheet;
use App\Models\Exam;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExamResultsExport implements WithMultipleSheets
{
    protected Exam $exam;

    public function __construct(Exam $exam)
    {
        $this->exam = $exam;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            'Results' => new ExamResultsSheet($this->exam),
            'Details' => new ExamResultDetailsSheet($this->exam),
        ];
    }
}
