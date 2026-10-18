<?php

namespace App\Core\Extensions;

/**
 * Content-Security-Policy origins declared by the modules loaded in this
 * request (manifest `content_security`, ADR-047). Only enabled, successfully
 * loaded modules contribute, so disabling a module removes its origins.
 */
final class ModuleContentSecurity
{
    /** @var array<string, array<string, list<string>>> module name => directive => origins */
    private array $modules = [];

    public function add(Manifest $manifest): void
    {
        $policy = [];

        foreach ($manifest->contentSecurity as $directive => $origins) {
            if (in_array($directive, Manifest::CONTENT_SECURITY_DIRECTIVES, true)) {
                // Re-checked here: stored manifests are trusted, but the policy is security-critical.
                $policy[$directive] = array_values(array_filter($origins, ManifestValidator::isHttpsOrigin(...)));
            }
        }

        $this->modules[$manifest->name] = $policy;
    }

    public function remove(string $module): void
    {
        unset($this->modules[$module]);
    }

    /**
     * @return list<string>
     */
    public function sources(string $directive): array
    {
        $sources = [];

        foreach ($this->modules as $policy) {
            array_push($sources, ...($policy[$directive] ?? []));
        }

        return array_values(array_unique($sources));
    }
}
