<?php

// Mocking the regex logic from QuestionImportService
$arabicInner  = '[\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]';
$arabicBridge = '[ \t\n\r\xA0\x{00A0}.,;:!\?\-\+\'\"()\[\]{}\/_\\\\@#%&\*~`^=|]';
$arabicPattern = '/(' . $arabicInner . '(' . $arabicBridge . '*' . $arabicInner . ')*)/u';

$tests = [
    'Normal space' => 'هَذِهِ غُرْفَةُ اِسْتِقْبَالِ',
    'Non-breaking space (\xA0)' => "هَذِهِ\xA0غُرْفَةُ\xA0اِسْتِقْبَالِ",
    'With punctuation' => 'هَذِهِ غُرْفَةُ اِسْتِقْبَالِ...',
    'Mixed with Latin' => 'Soal: هَذِهِ غُرْفَةُ اِسْتِقْبَالِ - Selesai',
];

echo "Testing Arabic Regex Fix\n";
echo "========================\n\n";

foreach ($tests as $desc => $input) {
    echo "Test Case: $desc\n";
    echo "Input:  $input\n";

    $result = preg_replace($arabicPattern, '[ara]$1[/ara]', $input);

    echo "Result: $result\n";

    // Check if there is only one [ara] tag for the whole phrase
    $tagCount = substr_count($result, '[ara]');
    if ($tagCount === 1) {
        echo "Status: PASSED (Single tag)\n";
    } else {
        echo "Status: FAILED (Tag count: $tagCount)\n";
    }
    echo "------------------------\n";
}
