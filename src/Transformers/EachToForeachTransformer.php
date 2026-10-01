<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class EachToForeachTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'each_to_foreach';
    }

    public function getDescription(): string
    {
        return 'Transforms obsolete while(list($k, $v) = each($arr)) constructs into foreach($arr as $k => $v)';
    }

    public function transform(string $source): string
    {
        // 1. while(list($key, $val) = each($arr)) -> foreach($arr as $key => $val)
        $source = preg_replace(
            '/\bwhile\s*\(\s*list\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*,\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)\s*=\s*each\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\[[^\]]+\])?)\s*\)\s*\)/i',
            'foreach ($3 as $1 => $2)',
            $source
        );

        // 2. while(list(, $val) = each($arr)) -> foreach($arr as $val)
        $source = preg_replace(
            '/\bwhile\s*\(\s*list\s*\(\s*,\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)\s*=\s*each\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\[[^\]]+\])?)\s*\)\s*\)/i',
            'foreach ($2 as $1)',
            $source
        );

        // 3. while(list($key) = each($arr)) -> foreach(array_keys($arr) as $key)
        $source = preg_replace(
            '/\bwhile\s*\(\s*list\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)\s*=\s*each\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\[[^\]]+\])?)\s*\)\s*\)/i',
            'foreach (array_keys($2) as $1)',
            $source
        );

        return $source;
    }
}
