<?php

function parsePdf($file) 
{
    $content = file_get_contents($file);

    $lines = explode("\n", $content);

    $parsedLines = [];
    $currentTitle = '';
    foreach ($lines as $line) {
        $line = trim($line);
        $line = cleanLine($line);
        if (empty($line)) continue;
        
        if (preg_match('/\/T\s*<([0-9A-Fa-f]+)>/i', $line, $matches)) {
            $hex_raw = $matches[1];
            $text = decodePdfHex($hex_raw);
            $currentTitle = $text;
            $parsedLines[] = $currentTitle;
        }

        if (preg_match('/\/T\s*\((.*?)\)/', $line, $matchesText)) {
            $currentTitle = trim($matchesText[1]);
            $parsedLines[] = $currentTitle;
        }

        if (preg_match('/\/E\s*\((.*?)\)/', $line, $matchesText)) {
            $text = trim($matchesText[1]);
            if ($text === $currentTitle) continue;
            if ($currentTitle) $parsedLines[] = $text;
        }

        if (preg_match('/\/E\s*<([0-9A-Fa-f]+)>/i', $line, $matches) && $currentTitle) {
            $hex_raw = $matches[1];
            $text = decodePdfHex($hex_raw);
            if ($text === $currentTitle) continue;
            if ($currentTitle) $parsedLines[] = $text;
        }
    }

    return implode("\n", $parsedLines);
}

function decodePdfHex($hex) {
    $hex_clean = preg_replace('/\s+/', '', $hex);
    $binary = pack('H*', $hex_clean);
    return mb_convert_encoding($binary, 'UTF-8', 'Windows-1252');
}

function cleanLine($line) {
    $line = trim($line);
    if ($line === '') return '';

    $chars = strlen($line);
    $alnum = preg_match_all('/[a-zA-Z0-9]/', $line);

    if ($chars > 0 && ($alnum / $chars) < 0.3) return '';

    return $line;
}