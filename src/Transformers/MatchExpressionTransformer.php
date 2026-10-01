<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class MatchExpressionTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'match_expression';
    }

    public function getDescription(): string
    {
        return 'Upgrades direct-return or variable-assignment switch statements to modern PHP 8.0+ match expressions';
    }

    public function transform(string $source): string
    {
        // Pattern to identify simple switch statements assigning to a variable or returning
        // Example:
        // switch ($x) {
        //     case 'a':
        //         $y = 1;
        //         break;
        //     case 'b':
        //         $y = 2;
        //         break;
        //     default:
        //         $y = 3;
        //         break;
        // }
        // transforms to:
        // $y = match ($x) {
        //     'a' => 1,
        //     'b' => 2,
        //     default => 3,
        // };

        $pattern = '/switch\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)\s*\{((?:[^{}]*?))\}/s';

        $source = preg_replace_callback($pattern, function ($matches) {
            $switchVar = trim($matches[1]);
            $body = trim($matches[2]);

            // Let's parse cases
            // Check if every branch is an assignment to the same target variable or a return
            // Split by case / default
            $casePattern = '/(?:case\s+([^:]+):|default\s*:)\s*(?:(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=\s*([^;]+);(?:\s*break;)?|return\s+([^;]+);(?:\s*break;)?)/i';

            if (!preg_match_all($casePattern, $body, $caseMatches, PREG_SET_ORDER)) {
                return $matches[0]; // cannot safely convert
            }

            // Check if entire body only consists of these cases
            $reconstructed = '';
            $targetVar = null;
            $isReturn = null;
            $arms = [];

            foreach ($caseMatches as $arm) {
                $cond = trim($arm[1] ?? '');
                $isDefault = empty($cond);

                if (!empty($arm[4])) {
                    // return
                    if ($targetVar !== null) {
                        return $matches[0]; // mixed return & var assignment
                    }
                    $isReturn = true;
                    $val = trim($arm[4]);
                } elseif (!empty($arm[2])) {
                    // var assignment
                    if ($isReturn === true) {
                        return $matches[0];
                    }
                    if ($targetVar === null) {
                        $targetVar = $arm[2];
                    } elseif ($targetVar !== $arm[2]) {
                        return $matches[0]; // different variables
                    }
                    $val = trim($arm[3]);
                } else {
                    return $matches[0];
                }

                $key = $isDefault ? 'default' : $cond;
                $arms[] = "    {$key} => {$val},";
            }

            if (empty($arms)) {
                return $matches[0];
            }

            $armsStr = implode("\n", $arms);
            if ($isReturn) {
                return "return match ({$switchVar}) {\n{$armsStr}\n};";
            } elseif ($targetVar !== null) {
                return "{$targetVar} = match ({$switchVar}) {\n{$armsStr}\n};";
            }

            return $matches[0];
        }, $source);

        return $source;
    }
}
