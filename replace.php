<?php

$paths = [
    __DIR__ . '/app/Http/Controllers',
    __DIR__ . '/resources/views',
];

function processDir($dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php'])) {
            $content = file_get_contents($file->getPathname());
            
            $newContent = str_replace(
                [
                    "route('portal.",
                    "view('portal.",
                    "->route('portal.",
                    "/portal/dashboard"
                ],
                [
                    "route('pharmacy.",
                    "view('pharmacy.",
                    "->route('pharmacy.",
                    "/pharmacy/dashboard"
                ],
                $content
            );
            
            if ($newContent !== $content) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Updated: " . $file->getPathname() . "\n";
            }
        }
    }
}

foreach ($paths as $path) {
    if (is_dir($path)) {
        processDir($path);
    }
}

echo "Done.\n";
