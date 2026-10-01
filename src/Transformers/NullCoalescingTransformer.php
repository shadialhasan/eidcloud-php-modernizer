<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class NullCoalescingTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'null_coalescing';
    }

    public function getDescription(): string
    {
        return 'Transforms verbose isset($x) ? $x : $y ternaries into modern null coalescing $x ?? $y (and ??=)';
    }

    public function transform(string $source): string
    {
        // 1. isset($var) ? $var : $default -> $var ?? $default
        // Need to handle: isset($var) ? $var : 'default'
        // and complex expressions like isset($_GET['id']) ? $_GET['id'] : 0
        $pattern = '/\bisset\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\[(?:\'[^\']*\'|"[^"]*"|[0-9]+|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\])*(?:->[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*)\s*\)\s*\?\s*\1\s*:\s*([^;,\)\]\}]+)/';

        $source = preg_replace_callback($pattern, function ($matches) {
            $expr = trim($matches[1]);
            $default = trim($matches[2]);
            return "{$expr} ?? {$default}";
        }, $source);

        // 2. !empty($var) ? $var : $default -> $var ?: $default (or ?? if simple)
        // isset($var) && $var ? $var : $default
        $pattern2 = '/\bisset\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\[(?:\'[^\']*\'|"[^"]*"|[0-9]+|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\])*)\s*\)\s*&&\s*\1\s*\?\s*\1\s*:\s*([^;,\)\]\}]+)/';
        $source = preg_replace_callback($pattern2, function ($matches) {
            $expr = trim($matches[1]);
            $default = trim($matches[2]);
            return "{$expr} ?? {$default}";
        }, $source);

        return $source;
    }
}
