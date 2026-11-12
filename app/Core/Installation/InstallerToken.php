<?php

namespace App\Core\Installation;

/**
 * One-time setup token for the web installer.
 *
 * The token is written to a file outside the web root and is never rendered
 * by the application, so only someone with shell/file access to the server
 * can complete a web installation. This prevents a first-visitor takeover of
 * a freshly deployed instance.
 */
final class InstallerToken
{
    public function path(): string
    {
        return storage_path('app/private/installer.token');
    }

    /**
     * Ensure a token exists and return it (for CLI display only).
     */
    public function ensure(): string
    {
        $existing = $this->read();

        if ($existing !== null && ! $this->expired()) {
            return $existing;
        }

        return $this->regenerate();
    }

    /**
     * Tokens expire (ECOMODULAR_INSTALLER_TOKEN_HOURS, default 24) so one
     * copied into deployment logs or tickets does not stay usable.
     */
    public function expired(): bool
    {
        $hours = (int) config('ecomodular.installer.token_hours', 24);
        $modified = is_file($this->path()) ? filemtime($this->path()) : false;

        return $hours > 0 && $modified !== false && $modified < time() - $hours * 3600;
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(24));
        $directory = dirname($this->path());

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($this->path(), $token.PHP_EOL, LOCK_EX);
        @chmod($this->path(), 0600);

        return $token;
    }

    public function verify(string $candidate): bool
    {
        $token = $this->read();

        return $token !== null && ! $this->expired() && hash_equals($token, trim($candidate));
    }

    public function delete(): void
    {
        if (is_file($this->path())) {
            @unlink($this->path());
        }
    }

    private function read(): ?string
    {
        if (! is_file($this->path())) {
            return null;
        }

        $token = trim((string) file_get_contents($this->path()));

        return $token === '' ? null : $token;
    }
}
