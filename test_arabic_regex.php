<?php

$arabicInner  = '[\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]';
$arabicBridge = '[ \t.,;:!?\-+\'"()\[\]{}\/_\\\\@#%&*~`^=|]';
$arabicPattern = '/(' . $arabicInner . '(' . $arabicBridge . '*' . $arabicInner . ')*)/u';

echo "=== Pattern ===" . PHP_EOL;
echo $arabicPattern . PHP_EOL . PHP_EOL;

$tests = [
    'هَذِهِ غُرْفَةُ اِسْتِقْبَالِ',
    'هَذِهِ غُرْفَةُ اِسْتِقْبَالِ...',
    '<p>هَذِهِ غُرْفَةُ اِسْتِقْبَالِ</p>',
    'text latin هَذِهِ غُرْفَةُ اِسْتِقْبَالِ latin again',
];

foreach ($tests as $text) {
    echo "=== Input: $text ===" . PHP_EOL;

    $count = preg_match_all($arabicPattern, $text, $m);
    echo "Matches: $count" . PHP_EOL;
    foreach ($m[0] as $i => $match) {
        echo "  [$i]: [$match]" . PHP_EOL;
    }

    $result = preg_replace($arabicPattern, '[ara]$1[/ara]', $text);
    echo "Result: $result" . PHP_EOL . PHP_EOL;
}
