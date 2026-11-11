<?php

namespace EcoModular\BotProtection\Services;

use EcoModular\BotProtection\Models\BotSecurityRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RulesEngineService
{
    public function getActiveRules(): array
    {
        return Cache::remember('bot_active_security_rules', 60, function () {
            return BotSecurityRule::query()
                ->where('is_active', true)
                ->orderBy('priority', 'asc')
                ->get()
                ->toArray();
        });
    }

    public function evaluateRules(Request $request): ?array
    {
        $rules = $this->getActiveRules();
        $path = '/'.ltrim($request->path(), '/');
        $userAgent = (string) $request->userAgent();
        $ip = $request->ip() ?? '127.0.0.1';

        foreach ($rules as $rule) {
            $conditions = $rule['conditions'] ?? [];
            if ($this->matchesConditions($request, $conditions, $path, $userAgent, $ip)) {
                // Increment hits
                BotSecurityRule::query()->where('id', $rule['id'])->increment('hits_count');

                return [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'action' => $rule['action'], // block, challenge, throttle, monitor
                    'reason' => "Custom Security Rule triggered: {$rule['name']}",
                ];
            }
        }

        return null;
    }

    private function matchesConditions(Request $request, array $conditions, string $path, string $userAgent, string $ip): bool
    {
        // 1. Path pattern match
        if (! empty($conditions['path_pattern'])) {
            $pattern = $conditions['path_pattern'];
            $isRegex = str_starts_with($pattern, '/') && str_ends_with($pattern, '/');
            if ($isRegex) {
                if (! preg_match($pattern, $path)) {
                    return false;
                }
            } else {
                if (! fnmatch($pattern, $path)) {
                    return false;
                }
            }
        }

        // 2. HTTP method match
        if (! empty($conditions['methods']) && is_array($conditions['methods'])) {
            if (! in_array(strtoupper($request->method()), array_map('strtoupper', $conditions['methods']), true)) {
                return false;
            }
        }

        // 3. User-Agent regex match
        if (! empty($conditions['user_agent_regex'])) {
            if (! @preg_match($conditions['user_agent_regex'], $userAgent)) {
                return false;
            }
        }

        // 4. Missing required header
        if (! empty($conditions['missing_header'])) {
            if ($request->hasHeader($conditions['missing_header'])) {
                return false;
            }
        }

        return true;
    }
}
