<?php
$directory = new RecursiveDirectoryIterator(__DIR__ . '/resources/views');
$iterator = new RecursiveIteratorIterator($directory);
$files = [];

foreach ($iterator as $info) {
    if ($info->isFile() && $info->getExtension() === 'php' && strpos($info->getPathname(), 'auth') === false) {
        $files[] = $info->getPathname();
    }
}

$tailwindConfig = <<<EOD
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
EOD;

$replacements = [
    '#1F2E2C' => 'geo-900',
    '#2F7E6A' => 'geo-600',
    '#63C6A7' => 'geo-500',
    '#BFE8D6' => 'geo-400',
    '#E9F7F2' => 'geo-100',
    '#FAFAFD' => 'geo-50',
    'Inter' => 'Outfit'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Inject tailwind config into layouts or standalone files
    if (strpos($content, '<head>') !== false && strpos($content, 'tailwind.config =') === false) {
        $content = preg_replace('/<style>\s*@import url\(\'https:\/\/fonts.googleapis.com\/css2\?family=Inter.*?\'\);\s*body \{ font-family: \'Inter\', sans-serif; \}\s*<\/style>/', $tailwindConfig, $content);
        $content = preg_replace('/<style>\s*@import url\(\'https:\/\/fonts.googleapis.com\/css2\?family=Inter.*\'\);\s*body \{ font-family: \'Inter\', sans-serif; \}\s*/', $tailwindConfig . '<style>', $content);
        $content = preg_replace('/<link href="https:\/\/fonts.googleapis.com\/css2\?family=Inter.*?rel="stylesheet">/', '', $content);
        
        // If the regex failed to find the specific block but <head> exists
        if (strpos($content, 'tailwind.config =') === false) {
            $content = str_replace('</head>', $tailwindConfig . '</head>', $content);
        }
    }

    // Regex replace classes
    foreach (['bg', 'text', 'border', 'ring', 'shadow', 'fill', 'from', 'via', 'to', 'hover:bg', 'hover:text', 'hover:border', 'group-focus-within:text', 'border-t', 'border-b', 'border-l', 'border-r'] as $prefix) {
        foreach ($replacements as $hex => $geo) {
            $content = preg_replace("/{$prefix}-\\[{$hex}\\](\\/[0-9]+)?/i", "{$prefix}-{$geo}$1", $content);
        }
    }

    $content = str_replace("font-family: 'Inter'", "font-family: 'Outfit'", $content);
    $content = str_replace("font-family:Inter", "font-family:Outfit", $content);

    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
