<?php
$files = [
    'resources/views/layouts/app.blade.php',
    'resources/views/scan/asset-info.blade.php',
    'resources/views/requests/maintenance/form.blade.php',
    'resources/views/requests/ict/form.blade.php',
    'resources/views/landing.blade.php',
    'resources/views/inventory/qr-batch.blade.php',
    'resources/views/errors/500.blade.php',
    'resources/views/errors/419.blade.php',
    'resources/views/errors/404.blade.php',
    'resources/views/errors/403.blade.php',
    'resources/views/csm/form.blade.php',
    'resources/views/auth/logout-success.blade.php',
    'resources/views/auth/login.blade.php',
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) { echo "SKIP: $file\n"; continue; }
    $original = file_get_contents($path);
    $content = $original;

    // Remove duplicate rel=stylesheet href= links
    $content = preg_replace(
        '/<link rel="stylesheet" href="(https:\/\/cdnjs\.cloudflare\.com\/ajax\/libs\/font-awesome\/[^"]+\/css\/all\.min\.css)">\s*\n[ \t]*<link rel="stylesheet" href="\1">/m',
        '<link rel="stylesheet" href="$1">',
        $content
    );

    // Remove duplicate href= rel=stylesheet links
    $content = preg_replace(
        '/<link href="(https:\/\/cdnjs\.cloudflare\.com\/ajax\/libs\/font-awesome\/[^"]+\/css\/all\.min\.css)" rel="stylesheet">\s*\n[ \t]*<link href="\1" rel="stylesheet">/m',
        '<link href="$1" rel="stylesheet">',
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "FIXED: $file\n";
    } else {
        echo "NO CHANGE: $file\n";
    }
}
echo "\nDone.\n";
