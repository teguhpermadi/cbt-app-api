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
        
        // Row 2: Question Content
        $contentRow = ['', 'Konten Soal'];
        foreach ($questions as $question) {
            $contentRow[] = strip_tags($question->content ?? '');
        }
        $contentRow[] = ''; // Total Score column placeholder
        $data->push($contentRow);

        $rowIndex = 3; // Student data starts at row 3 (Row 1=Header, Row 2=Content)
        $correctCounts = array_fill(0, $this->questionCount, 0);

        foreach ($results as $index => $result) {
            $row = [
                $index + 1,
                $result->user?->name,
            ];

            $details = $result->officialSession?->examResultDetails->keyBy('exam_question_id') ?? collect();

            foreach ($questions as $qIndex => $question) {
                $detail = $details->get($question->id);
                $answer = $detail ? $detail->student_answer : '-';
                
                if (is_array($answer)) {
                    $answer = json_encode($answer);
                }
                
                $row[] = $answer;

                if ($detail) {
                    $colIndex = $qIndex + 3;
                    $this->isCorrectMap[$rowIndex][$colIndex] = $detail->is_correct;
                    
                    if ($detail->is_correct) {
                        $correctCounts[$qIndex]++;
                    }
                }
            }

            $row[] = $result->total_score;
            $data->push($row);
            $rowIndex++;
        }

        // Final Row: Total Correct Summary
        $summaryRow = ['', 'Total Benar'];
        foreach ($correctCounts as $count) {
            $summaryRow[] = $count;
        }
        $summaryRow[] = '';
        $data->push($summaryRow);

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
                        if ($isCorrect === null) continue;

                        $cellAddress = $sheet->getCellByColumnAndRow($colIndex, $rowIndex)->getCoordinate();
                        
                        if ($isCorrect === true) {
                            $sheet->getStyle($cellAddress)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('C6EFCE');
                        } elseif ($isCorrect === false) {
                            $sheet->getStyle($cellAddress)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFC7CE');
                        }
                    }
                }

                $highestColumn = $sheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // Header styling
                $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
                
                // Question Content row styling (Row 2)
                $sheet->getStyle('A2:' . $highestColumn . '2')->getFont()->setItalic(true);
                $sheet->getStyle('A2:' . $highestColumn . '2')->getAlignment()->setWrapText(true);
                
                // Summary row styling (last row)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle('A' . $highestRow . ':' . $highestColumn . $highestRow)->getFont()->setBold(true);

                // Column Sizing
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(30);
                
                for ($i = 3; $i < $highestColumnIndex; $i++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($columnLetter)->setWidth(20);
                }
                
                // Last column (Total Score)
                $sheet->getColumnDimension($highestColumn)->setWidth(15);
            },
        ];
    }
}
