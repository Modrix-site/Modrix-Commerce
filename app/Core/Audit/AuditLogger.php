<?php

namespace App\Core\Audit;

use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes redacted, append-only audit entries.
 *
 * Never pass raw request input as metadata without thinking: keys that look
 * like secrets are redacted, but values under innocuous keys are stored as-is.
 */
final class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEY_PATTERN = '/(password|passwd|secret|token|api[_-]?key|private[_-]?key|card|cvv|cvc|recovery|two_factor|otp)/i';

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ?Request $request = null,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        $actor ??= $this->currentUser();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor ? Str::limit($actor->email, 188) : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request ? Str::limit((string) $this->request->userAgent(), 500, '') : null,
            'metadata' => $metadata === [] ? null : self::redact($metadata),
        ]);
    }

    /**
     * Recursively replace values whose keys look sensitive.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private function currentUser(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }
}
