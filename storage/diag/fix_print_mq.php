<?php
// Convert bare @media (max-width:...) to @media screen and (max-width:...)
// so mobile rules never apply to PRINT viewport (~718px A4).
$dirs = ['resources/css', 'resources/views'];
$changed = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!in_array($f->getExtension(), ['css', 'php'])) continue;
        if (str_contains($f->getPathname(), 'node_modules')) continue;
        $p = $f->getPathname();
        $c = file_get_contents($p);
        // only bare (no media-type) queries: "@media (max-width" or "@media all (max-width"
        $n = preg_replace('/@media\s+\(max-width/', '@media screen and (max-width', $c);
        if ($n !== $c) {
            file_put_contents($p, $n);
            $cnt = preg_match_all('/@media screen and \(max-width/', $n) - preg_match_all('/@media screen and \(max-width/', $c);
            $changed[] = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $p) . " (+{$cnt})";
        }
    }
}
echo "CHANGED FILES: " . count($changed) . "\n";
foreach ($changed as $c) echo "  {$c}\n";
// verify: no bare left
$left = 0;
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!in_array($f->getExtension(), ['css', 'php'])) continue;
        $c = file_get_contents($f->getPathname());
        preg_match_all('/@media(?!\s+screen)(?!\s+print)(?!\s+all\s+and)(?!\s+not)\s*\((max|min)-width/', $c, $m);
        $left += count($m[0]);
    }
}
echo "BARE REMAINING: {$left}\n";
