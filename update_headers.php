<?php

$dirs = [
    'd:\Dev_empenios\empenios-api\resources\views\reportes',
    'd:\Dev_empenios\empenios-api\resources\views\reportes\contabilidad',
    'd:\Dev_empenios\empenios-api\resources\views\reports',
];

$logoPath = "data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}";
$headerTemplate = <<<HTML
    <div class="header" style="border:none; padding-bottom: 10px; border-bottom: 1px solid #ccc; margin-bottom: 15px;">
        <table width="100%">
            <tr>
                <td width="25%" style="text-align: left; vertical-align: middle;">
                    <img src="$logoPath" alt="Logo" style="height: 80px;">
                </td>
                <td width="50%" style="text-align: center; vertical-align: middle;">
                    {CONTENT}
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </div>
HTML;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = scandir($dir);
    foreach ($files as $file) {
        if (substr($file, -10) === '.blade.php') {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $content = file_get_contents($path);
            
            // Si ya tiene el logo, saltar
            if (strpos($content, 'avanza_logo.png') !== false) {
                continue;
            }

            // Buscar el div de header
            $pattern = '/<div class="header"[^>]*>(.*?)<\/div>/s';
            if (preg_match($pattern, $content, $matches)) {
                $innerHtml = trim($matches[1]);
                $newHeader = str_replace('{CONTENT}', $innerHtml, $headerTemplate);
                $newContent = str_replace($matches[0], $newHeader, $content);
                file_put_contents($path, $newContent);
                echo "Updated: $file\n";
            } else {
                // If there's a <body> tag but no <div class="header">, maybe it has <h2 class="document-title">
                echo "No header div found in $file\n";
            }
        }
    }
}
