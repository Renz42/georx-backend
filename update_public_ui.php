<?php
$files = [
    __DIR__ . '/resources/views/home.blade.php',
    __DIR__ . '/resources/views/public/pharmacy_catalog.blade.php',
    __DIR__ . '/resources/views/public/medicine_details.blade.php',
];

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
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Inject tailwind config
        if (strpos($content, 'tailwind.config =') === false) {
            // Replace the Inter font link with the Outfit font link and Tailwind config
            $content = preg_replace('/<style>\s*@import url\(\'https:\/\/fonts.googleapis.com\/css2\?family=Inter.*?\'\);\s*body \{ font-family: \'Inter\', sans-serif; \}\s*<\/style>/', $tailwindConfig, $content);
            $content = preg_replace('/<style>\s*@import url\(\'https:\/\/fonts.googleapis.com\/css2\?family=Inter.*\'\);\s*body \{ font-family: \'Inter\', sans-serif; \}\s*/', $tailwindConfig . '<style>', $content);
            $content = preg_replace('/<link href="https:\/\/fonts.googleapis.com\/css2\?family=Inter.*?rel="stylesheet">/', '', $content);
        }

        // Regex replace classes
        foreach (['bg', 'text', 'border', 'ring', 'shadow', 'fill', 'from', 'via', 'to', 'hover:bg', 'hover:text', 'hover:border', 'group-focus-within:text'] as $prefix) {
            foreach ($replacements as $hex => $geo) {
                // We want to replace e.g. bg-[#1F2E2C] with bg-geo-900
                // Sometimes it has opacity like bg-[#1F2E2C]/50, which should become bg-geo-900/50
                $content = preg_replace("/{$prefix}-\\[{$hex}\\](\\/[0-9]+)?/i", "{$prefix}-{$geo}$1", $content);
            }
        }
        
        // Also replace standalone hex codes in inline styles or scripts
        foreach ($replacements as $hex => $geo) {
            // But be careful not to break valid things, only replace if safe.
            // I'll skip global hex replace and stick to the class names because it's safer.
        }

        // Replace Inter with Outfit in remaining CSS
        $content = str_replace("font-family: 'Inter'", "font-family: 'Outfit'", $content);
        $content = str_replace("font-family:Inter", "font-family:Outfit", $content);

        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
