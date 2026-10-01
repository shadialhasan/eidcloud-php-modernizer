<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class ConstructorTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'legacy_constructor';
    }

    public function getDescription(): string
    {
        return 'Upgrades PHP 4/5 style class constructors (function ClassName()) to modern __construct()';
    }

    public function transform(string $source): string
    {
        // Matches class definitions and replaces methods sharing the class name with __construct
        // Example: class User { function User($name) { ... } }
        $pattern = '/\bclass\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*(?:extends\s+[^{]+)?(?:implements\s+[^{]+)?\s*\{/i';

        if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $source;
        }

        // We find each class and its body
        $tokens = token_get_all($source);
        $output = '';
        $count = count($tokens);
        $currentClass = null;
        $classDepth = 0;
        $braceDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_CLASS) {
                    // Peek ahead to find class name
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j])) {
                            if ($tokens[$j][0] === T_STRING) {
                                $currentClass = $tokens[$j][1];
                                break;
                            }
                        }
                    }
                    $output .= $token[1];
                    continue;
                }

                if ($token[0] === T_FUNCTION && $currentClass !== null) {
                    // Peek ahead to check if the function name matches $currentClass
                    $funcNameIndex = -1;
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j])) {
                            if ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT || $tokens[$j][0] === T_DOC_COMMENT) {
                                continue;
                            }
                            if ($tokens[$j][0] === T_STRING) {
                                $funcNameIndex = $j;
                                break;
                            }
                        } elseif ($tokens[$j] === '&') {
                            continue;
                        } else {
                            break;
                        }
                    }

                    if ($funcNameIndex !== -1 && strcasecmp($tokens[$funcNameIndex][1], $currentClass) === 0) {
                        // We found legacy constructor! Check that it's inside the class body
                        $tokens[$funcNameIndex][1] = '__construct';
                    }

                    $output .= $token[1];
                    continue;
                }

                $output .= $token[1];
            } else {
                if ($token === '{') {
                    $braceDepth++;
                } elseif ($token === '}') {
                    $braceDepth--;
                    if ($braceDepth <= 0) {
                        $currentClass = null;
                        $braceDepth = 0;
                    }
                }
                $output .= $token;
            }
        }

        // Also update parent constructor calls: parent::ClassName(...) -> parent::__construct(...)
        $output = preg_replace('/parent::[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\(/i', 'parent::__construct(', $output);

        return $output;
    }
}
