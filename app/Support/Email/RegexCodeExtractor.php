<?php

namespace App\Support\Email;

final class RegexCodeExtractor
{
    /**
     * Extract the regex code and options from a given string.
     * Looks for patterns like "## code -x=1 -verbose ##"
     */
    public function extract(string $bodyText): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $bodyText)));
        $firstNonEmptyLine = $lines[0] ?? '';

        $regexCode = '';
        $regexOptions = [];

        if (preg_match('/^##\s*([\w-]+)(.*)##$/', $firstNonEmptyLine, $matches)) {
            $regexCode = $matches[1];

            // Extract options
            if (!empty($matches[2])) {
                preg_match_all('/-([\w]+)(?:=([\w]+))?/', $matches[2], $optionMatches, PREG_SET_ORDER);
                foreach ($optionMatches as $option) {
                    $key = $option[1];
                    $value = $option[2] ?? true; // If no value is provided, default to `true`
                    $regexOptions[$key] = $value;
                }
            }
        }

        return [$regexCode, $regexOptions];
    }
}
