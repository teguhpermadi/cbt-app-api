<?php

namespace App\Exports\Sheets;

use App\Models\Exam;
use App\Models\ExamResult;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExamResultDetailsSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected Exam $exam;
    protected array $isCorrectMap = [];
    protected int $questionCount = 0;

    public function __construct(Exam $exam)
    {
        $this->exam = $exam;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $questions = $this->exam->examQuestions()->orderBy('question_number')->get();
        $this->questionCount = $questions->count();
        
        $results = ExamResult::where('exam_id', $this->exam->id)
            ->with(['user', 'officialSession.examResultDetails'])
            ->get();

        $data = collect();
        $rowIndex = 2; // Data starts at row 2

        foreach ($results as $index => $result) {
            $row = [
                $index + 1,
                $result->user?->name,
            ];

            // Map details by exam_question_id for quick lookup
            $details = $result->officialSession?->examResultDetails->keyBy('exam_question_id') ?? collect();

            foreach ($questions as $qIndex => $question) {
                $detail = $details->get($question->id);
                $answer = $detail ? $detail->student_answer : '-';
                
                if (is_array($answer)) {
                    $answer = json_encode($answer);
                }
                
                $row[] = $answer;

                // Store correctness for this cell (Column C is index 3)
                if ($detail) {
                    $colIndex = $qIndex + 3;
                    $this->isCorrectMap[$rowIndex][$colIndex] = $detail->is_correct;
                }
            }

            $row[] = $result->total_score;
            $data->push($row);
            $rowIndex++;
        }

        return $data;
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
        $questions = $this->exam->examQuestions()->orderBy('question_number')->get();
        
        $headings = ['No', 'Student Name'];
        
        foreach ($questions as $question) {
            $headings[] = 'Soal No ' . $question->question_number;
        }
        
        $headings[] = 'Total Score';
        
        return $headings;
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Apply background colors to answer cells
                foreach ($this->isCorrectMap as $rowIndex => $cols) {
                    foreach ($cols as $colIndex => $isCorrect) {
                        if ($isCorrect === null) continue; // Not corrected yet (Essay)

                        $cellAddress = $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->getCoordinate();
                        
                        if ($isCorrect === true) {
                            $sheet->getStyle($cellAddress)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('C6EFCE'); // Light Green
                        } elseif ($isCorrect === false) {
                            $sheet->getStyle($cellAddress)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFC7CE'); // Light Red/Pink
                        }
                    }
                }

                // Header styling
                $highestColumn = $sheet->getHighestColumn();
                $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
                
                // Auto size columns
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                for ($i = 1; $i <= $highestColumnIndex; $i++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                }
            },
        ];
    }
}
