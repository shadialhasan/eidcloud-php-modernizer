<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class PropertyPromotionTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'property_promotion';
    }

    public function getDescription(): string
    {
        return 'Promotes untyped class properties assigned in __construct() to modern PHP 8.0+ constructor property promotion with inferred types';
    }

    public function transform(string $source): string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $classes = []; // list of ['name' => ..., 'start_brace' => ..., 'end_brace' => ...]

        $braceStack = [];
        $currentClass = null;

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && $t[0] === T_CLASS) {
                // find class name
                $cName = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $cName = $tokens[$j][1];
                        break;
                    }
                    if ($tokens[$j] === '{') {
                        break;
                    }
                }
                $currentClass = $cName;
                continue;
            }

            if ($t === '{') {
                if ($currentClass !== null && empty($braceStack)) {
                    $classes[] = [
                        'name' => $currentClass,
                        'body_start' => $i,
                        'body_end' => null,
                    ];
                    $braceStack[] = count($classes) - 1;
                    $currentClass = null;
                } elseif (!empty($braceStack)) {
                    $braceStack[] = -1;
                }
            } elseif ($t === '}') {
                if (!empty($braceStack)) {
                    $idx = array_pop($braceStack);
                    if ($idx >= 0 && isset($classes[$idx])) {
                        $classes[$idx]['body_end'] = $i;
                    }
                }
            }
        }

        if (empty($classes)) {
            return $source;
        }

        // Process from bottom to top so token offsets or string replacements don't shift
        // Alternatively, reconstruct tokens for the classes
        foreach (array_reverse($classes) as $c) {
            if ($c['body_end'] === null) {
                continue;
            }

            // Extract class body code
            $classBodyTokens = array_slice($tokens, $c['body_start'] + 1, $c['body_end'] - $c['body_start'] - 1);
            $bodyStr = '';
            foreach ($classBodyTokens as $bt) {
                $bodyStr .= is_array($bt) ? $bt[1] : $bt;
            }

            // Find __construct in body
            $constructPattern = '/(public\s+|protected\s+|private\s+)?function\s+__construct\s*\((.*?)\)\s*\{([^}]*)\}/is';
            if (!preg_match($constructPattern, $bodyStr, $cMatch)) {
                continue;
            }

            $cVisibility = !empty($cMatch[1]) ? trim($cMatch[1]) : 'public';
            $cParamsRaw = trim($cMatch[2]);
            $cBody = trim($cMatch[3]);

            if (empty($cParamsRaw)) {
                continue;
            }

            $paramList = array_map('trim', explode(',', $cParamsRaw));
            $params = [];
            $skip = false;
            foreach ($paramList as $p) {
                if (preg_match('/^(public|protected|private)\s+/', $p)) {
                    $skip = true;
                    break;
                }
                if (preg_match('/^(?:([a-zA-Z0-9_\\\?|&]+)\s+)?(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(?:\s*=\s*(.+))?$/', $p, $pMatch)) {
                    $type = !empty($pMatch[1]) ? $pMatch[1] : '';
                    $name = $pMatch[2];
                    $default = !empty($pMatch[3]) ? $pMatch[3] : null;
                    $params[$name] = ['type' => $type, 'default' => $default];
                }
            }

            if ($skip || empty($params)) {
                continue;
            }

            $promoted = [];
            $newBodyStatements = [];
            $bodyLines = array_filter(array_map('trim', explode(';', $cBody)));

            foreach ($bodyLines as $line) {
                if (preg_match('/^\$this->([a-zA-Z0-9_]+)\s*=\s*(\$[a-zA-Z0-9_]+)$/', $line, $assignMatch)) {
                    $propName = $assignMatch[1];
                    $varName = $assignMatch[2];
                    if (isset($params[$varName]) && $varName === '$' . $propName) {
                        $promoted[$propName] = $params[$varName];
                        continue;
                    }
                }
                if (!empty($line)) {
                    $newBodyStatements[] = $line . ';';
                }
            }

            if (empty($promoted)) {
                continue;
            }

            // Remove property declarations like: var $name; public $name;
            $cleanedBody = $bodyStr;
            foreach ($promoted as $propName => $propInfo) {
                $cleanedBody = preg_replace(
                    '/(?:var|public|protected|private)\s+\$' . $propName . '\s*(?:=[^;]+)?;\s*\r?\n?/i',
                    '',
                    $cleanedBody
                );
            }

            // Reconstruct constructor
            $promotedParams = [];
            foreach ($params as $varName => $info) {
                $rawName = substr($varName, 1);
                if (isset($promoted[$rawName])) {
                    $typeStr = !empty($info['type']) ? $info['type'] . ' ' : '';
                    $defStr = $info['default'] !== null ? " = {$info['default']}" : '';
                    $promotedParams[] = "        public {$typeStr}{$varName}{$defStr}";
                } else {
                    $typeStr = !empty($info['type']) ? $info['type'] . ' ' : '';
                    $defStr = $info['default'] !== null ? " = {$info['default']}" : '';
                    $promotedParams[] = "        {$typeStr}{$varName}{$defStr}";
                }
            }

            $newParamsStr = "\n" . implode(",\n", $promotedParams) . "\n    ";
            $innerBodyStr = empty($newBodyStatements) ? '' : "\n        " . implode("\n        ", $newBodyStatements) . "\n    ";
            $newConstruct = "{$cVisibility} function __construct({$newParamsStr}) {{$innerBodyStr}}";

            $cleanedBody = str_replace($cMatch[0], $newConstruct, $cleanedBody);

            // Replace in source
            $source = str_replace($bodyStr, $cleanedBody, $source);
        }

        return $source;
    }
}
