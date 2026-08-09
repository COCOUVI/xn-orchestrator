<?php

namespace Xn\Orchestrator\Catalog;

interface CatalogRepositoryInterface
{
    /** @return list<PackageDefinition> */
    public function getAll(): array;

    public function findByName(string $name): ?PackageDefinition;

    /** @return list<PackageDefinition> */
    public function findByCategory(string $category): array;
}
