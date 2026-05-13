<?php

namespace App\Services;

use App\Exports\ExamResultsExport;
use App\Models\Exam;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExamResultExportService
{
    /**
     * Export exam results to Excel.
     *
     * @param Exam $exam
     * @return BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function download(Exam $exam)
    {
        $fileName = 'exam_results_' . str_replace(' ', '_', strtolower($exam->title)) . '_' . now()->format('Ymd_His') . '.xlsx';
        
        return Excel::download(new ExamResultsExport($exam), $fileName);
    }
}
