<?php

namespace Xn\Orchestrator\Catalog;

final class PackageDefinition
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $installSteps
     * @param  list<string>  $supportedLaravel
     * @param  list<string>  $dependsOn
     * @param  list<string>  $conflictsWith
     */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly array $tags,
        public readonly array $installSteps,
        public readonly array $supportedLaravel = [],
        public readonly string $supportedPhp = '',
        public readonly array $dependsOn = [],
        public readonly array $conflictsWith = [],
    ) {}
}
