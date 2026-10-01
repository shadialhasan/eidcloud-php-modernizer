<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

interface TransformerInterface
{
    /**
     * Unique identifier/name of the transformer.
     */
    public function getName(): string;

    /**
     * Human-readable description of what this transformer upgrades.
     */
    public function getDescription(): string;

    /**
     * Transforms the given PHP source code string.
     *
     * @param string $source
     * @return string Transformed PHP code
     */
    public function transform(string $source): string;
}
