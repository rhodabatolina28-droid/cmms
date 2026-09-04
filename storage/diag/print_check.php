<?php
$commits = ['66ef4d4', '9608d75', '7d907c7'];
foreach ($commits as $c) {
    $out = shell_exec("git --no-pager show {$c}:resources/views/purchase-requests/show.blade.php 2>NUL");
    if (!$out) { echo "=== {$c}: FILE NOT FOUND ===\n"; continue; }
    $lines = explode("\n", $out);
    echo "=== {$c}: total lines = " . count($lines) . " ===\n";
    $inPrint = false;
    foreach ($lines as $i => $line) {
        if (strpos($line, '@media print') !== false) {
            echo sprintf("%4d | %s\n", $i + 1, rtrim($line));
            $inPrint = true;
            continue;
        }
        if ($inPrint) {
            echo sprintf("%4d | %s\n", $i + 1, rtrim($line));
            if (strpos($line, '}') === 0) { $inPrint = false; echo "--- end of print block ---\n"; }
            if ($i > 400) break;
        }
    }
    echo "\n";
}
