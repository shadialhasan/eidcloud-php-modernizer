<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class EregToPregTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'ereg_to_preg';
    }

    public function getDescription(): string
    {
        return 'Transforms obsolete ereg*, split() POSIX regex functions to modern PCRE preg_* functions';
    }

    public function transform(string $source): string
    {
        // 1. ereg_replace(pattern, replacement, string) -> preg_replace('/' . pattern . '/', replacement, string)
        $source = preg_replace_callback(
            '/\bereg_replace\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"])\s*,\s*(.+?)\s*,\s*(.+?)\s*\)/i',
            function ($matches) {
                $pat = trim($matches[1], "'\"");
                $escaped = addcslashes($pat, '/');
                $delimited = "'/{$escaped}/'";
                return "preg_replace({$delimited}, {$matches[2]}, {$matches[3]})";
            },
            $source
        );

        // 2. eregi_replace(pattern, replacement, string) -> preg_replace('/' . pattern . '/i', replacement, string)
        $source = preg_replace_callback(
            '/\beregi_replace\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"])\s*,\s*(.+?)\s*,\s*(.+?)\s*\)/i',
            function ($matches) {
                $pat = trim($matches[1], "'\"");
                $escaped = addcslashes($pat, '/');
                $delimited = "'/{$escaped}/i'";
                return "preg_replace({$delimited}, {$matches[2]}, {$matches[3]})";
            },
            $source
        );

        // 3. ereg(pattern, string, [regs]) -> preg_match('/' . pattern . '/', string, [regs])
        $source = preg_replace_callback(
            '/\bereg\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"])\s*,\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?\s*\)/i',
            function ($matches) {
                $pat = trim($matches[1], "'\"");
                $escaped = addcslashes($pat, '/');
                $delimited = "'/{$escaped}/'";
                $str = trim($matches[2]);
                if (!empty($matches[3])) {
                    $regs = trim($matches[3]);
                    return "preg_match({$delimited}, {$str}, {$regs})";
                }
                return "preg_match({$delimited}, {$str})";
            },
            $source
        );

        // 4. eregi(pattern, string, [regs]) -> preg_match('/' . pattern . '/i', string, [regs])
        $source = preg_replace_callback(
            '/\beregi\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"])\s*,\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?\s*\)/i',
            function ($matches) {
                $pat = trim($matches[1], "'\"");
                $escaped = addcslashes($pat, '/');
                $delimited = "'/{$escaped}/i'";
                $str = trim($matches[2]);
                if (!empty($matches[3])) {
                    $regs = trim($matches[3]);
                    return "preg_match({$delimited}, {$str}, {$regs})";
                }
                return "preg_match({$delimited}, {$str})";
            },
            $source
        );

        // 5. split(pattern, string, [limit]) -> explode(pattern, string) or preg_split('/' . pattern . '/', string)
        $source = preg_replace_callback(
            '/\bsplit\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"])\s*,\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?\s*\)/i',
            function ($matches) {
                $pat = trim($matches[1], "'\"");
                $str = trim($matches[2]);
                $limit = !empty($matches[3]) ? ', ' . trim($matches[3]) : '';
                // If simple characters without regex specials, explode is faster and simpler
                if (!preg_match('/[\[\]\^\$\.\|\?\*\+\(\)\\\\]/', $pat) && empty($limit)) {
                    return "explode('{$pat}', {$str})";
                }
                $escaped = addcslashes($pat, '/');
                return "preg_split('/{$escaped}/', {$str}{$limit})";
            },
            $source
        );

        return $source;
    }
}
