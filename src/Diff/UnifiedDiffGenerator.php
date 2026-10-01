<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Diff;

class UnifiedDiffGenerator
{
    /**
     * Generates a standard unified diff string between old and new code.
     */
    public function generate(string $original, string $modified, string $filePath = 'input.php'): string
    {
        $origLines = explode("\n", str_replace("\r\n", "\n", $original));
        $modLines = explode("\n", str_replace("\r\n", "\n", $modified));

        $diff = [];
        $diff[] = "--- a/{$filePath}";
        $diff[] = "+++ b/{$filePath}";

        $matrix = [];
        $oCount = count($origLines);
        $mCount = count($modLines);

        // Simple Myers-style or line-by-line diff
        $i = 0;
        $j = 0;

        $hunkOrig = [];
        $hunkMod = [];
        $hunkOutput = [];
        $startOrig = 1;
        $startMod = 1;

        $hasChanges = false;

        while ($i < $oCount || $j < $mCount) {
            $lineO = $i < $oCount ? $origLines[$i] : null;
            $lineM = $j < $mCount ? $modLines[$j] : null;

            if ($lineO === $lineM) {
                $hunkOutput[] = " " . $lineO;
                $i++;
                $j++;
            } else {
                $hasChanges = true;
                // Lookahead to find match
                $lookaheadLimit = 15;
                $foundO = -1;
                $foundM = -1;

                for ($offset = 1; $offset <= $lookaheadLimit; $offset++) {
                    if ($j + $offset < $mCount && $lineO === $modLines[$j + $offset]) {
                        $foundM = $offset;
                        break;
                    }
                    if ($i + $offset < $oCount && $origLines[$i + $offset] === $lineM) {
                        $foundO = $offset;
                        break;
                    }
                }

                if ($foundM !== -1) {
                    for ($k = 0; $k < $foundM; $k++) {
                        $hunkOutput[] = "+" . $modLines[$j + $k];
                    }
                    $j += $foundM;
                } elseif ($foundO !== -1) {
                    for ($k = 0; $k < $foundO; $k++) {
                        $hunkOutput[] = "-" . $origLines[$i + $k];
                    }
                    $i += $foundO;
                } else {
                    if ($lineO !== null) {
                        $hunkOutput[] = "-" . $lineO;
                        $i++;
                    }
                    if ($lineM !== null) {
                        $hunkOutput[] = "+" . $lineM;
                        $j++;
                    }
                }
            }
        }

        if (!$hasChanges) {
            return '';
        }

        $header = "@@ -1,{$oCount} +1,{$mCount} @@";
        return implode("\n", array_merge($diff, [$header], $hunkOutput)) . "\n";
    }

    /**
     * Renders a colored CLI diff.
     */
    public function renderColored(string $diff): string
    {
        $lines = explode("\n", $diff);
        $colored = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '---') || str_starts_with($line, '+++')) {
                $colored[] = "\033[1;37m" . $line . "\033[0m"; // Bold white
            } elseif (str_starts_with($line, '@@')) {
                $colored[] = "\033[36m" . $line . "\033[0m"; // Cyan
            } elseif (str_starts_with($line, '+')) {
                $colored[] = "\033[32m" . $line . "\033[0m"; // Green
            } elseif (str_starts_with($line, '-')) {
                $colored[] = "\033[31m" . $line . "\033[0m"; // Red
            } else {
                $colored[] = $line;
            }
        }

        return implode("\n", $colored);
    }
}
