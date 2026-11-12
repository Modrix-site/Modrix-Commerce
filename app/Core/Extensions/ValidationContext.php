<?php

namespace App\Core\Extensions;

/**
 * What a manifest must not conflict with: namespaces, permissions and
 * migration file names already claimed by core or other packages.
 */
final class ValidationContext
{
    /**
     * @param  array<string, string>  $namespaces  namespace => owning module
     * @param  array<string, string>  $permissionOwners  permission => "core" or owning module
     * @param  array<string, string>  $migrationOwners  migration file name => "core" or owning module
     */
    public function __construct(
        public readonly array $namespaces = [],
        public readonly array $permissionOwners = [],
        public readonly array $migrationOwners = [],
    ) {}
}
