<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer;

use EidCloud\PhpModernizer\Transformers\TransformerInterface;
use EidCloud\PhpModernizer\Transformers\MysqlToPdoTransformer;
use EidCloud\PhpModernizer\Transformers\ConstructorTransformer;
use EidCloud\PhpModernizer\Transformers\PropertyPromotionTransformer;
use EidCloud\PhpModernizer\Transformers\EregToPregTransformer;
use EidCloud\PhpModernizer\Transformers\EachToForeachTransformer;
use EidCloud\PhpModernizer\Transformers\NullCoalescingTransformer;
use EidCloud\PhpModernizer\Transformers\MatchExpressionTransformer;
use EidCloud\PhpModernizer\Diff\UnifiedDiffGenerator;

class Modernizer
{
    public const VERSION = '1.0.0';

    /**
     * @var TransformerInterface[]
     */
    private array $transformers = [];
    private UnifiedDiffGenerator $diffGenerator;

    public function __construct(array $customTransformers = [])
    {
        $this->diffGenerator = new UnifiedDiffGenerator();

        if (!empty($customTransformers)) {
            $this->transformers = $customTransformers;
        } else {
            $this->registerDefaultTransformers();
        }
    }

    private function registerDefaultTransformers(): void
    {
        $this->transformers = [
            new MysqlToPdoTransformer(),
            new ConstructorTransformer(),
            new PropertyPromotionTransformer(),
            new EregToPregTransformer(),
            new EachToForeachTransformer(),
            new NullCoalescingTransformer(),
            new MatchExpressionTransformer(),
        ];
    }

    /**
     * @return TransformerInterface[]
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }

    /**
     * Modernize a string of PHP code.
     */
    public function modernizeString(string $source): string
    {
        $current = $source;
        foreach ($this->transformers as $transformer) {
            $current = $transformer->transform($current);
        }
        return $current;
    }

    /**
     * Modernize a file and return transformation result details.
     *
     * @return array{
     *     filePath: string,
     *     hasChanged: bool,
     *     diff: string,
     *     isValidSyntax: bool,
     *     syntaxError: ?string,
     *     original: string,
     *     modernized: string
     * }
     */
    public function modernizeFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $original = file_get_contents($filePath);
        if ($original === false) {
            throw new \RuntimeException("Unable to read file: {$filePath}");
        }

        $modernized = $this->modernizeString($original);
        $hasChanged = ($original !== $modernized);

        $diff = '';
        if ($hasChanged) {
            $diff = $this->diffGenerator->generate($original, $modernized, $filePath);
        }

        $syntaxResult = $this->validateSyntax($modernized);

        return [
            'filePath' => $filePath,
            'hasChanged' => $hasChanged,
            'diff' => $diff,
            'isValidSyntax' => $syntaxResult['valid'],
            'syntaxError' => $syntaxResult['error'],
            'original' => $original,
            'modernized' => $modernized,
        ];
    }

    /**
     * Validates syntax using php -l.
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateSyntax(string $phpCode): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'php_mod_');
        if ($tempFile === false) {
            return ['valid' => true, 'error' => null];
        }

        try {
            file_put_contents($tempFile, $phpCode);
            $cmd = sprintf('php -l %s 2>&1', escapeshellarg($tempFile));
            $output = [];
            $returnVar = 0;
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0) {
                return ['valid' => true, 'error' => null];
            }

            $rawOutput = implode("\n", $output);
            return [
                'valid' => false,
                'error' => str_replace($tempFile, 'code', $rawOutput),
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Scans a directory recursively for PHP files.
     *
     * @return string[]
     */
    public function scanDirectory(string $directory): array
    {
        $files = [];
        if (!is_dir($directory)) {
            if (is_file($directory) && str_ends_with(strtolower($directory), '.php')) {
                return [$directory];
            }
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'php') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);
        return $files;
    }
}
