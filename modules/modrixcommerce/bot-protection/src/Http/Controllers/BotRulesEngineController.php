<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotAuditLog;
use EcoModular\BotProtection\Models\BotSecurityRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BotRulesEngineController extends Controller
{
    public function index(): Response
    {
        $rules = BotSecurityRule::query()->orderBy('priority', 'asc')->get();

        return Inertia::render('module/modrixcommerce/bot-protection/admin/rules-engine', [
            'rules' => $rules,
            'ruleTemplates' => [
                [
                    'name' => 'Aggressive Scraper Blocker',
                    'rule_type' => 'user_agent',
                    'description' => 'Immediately blocks known automated scraper user agents like Scrapy, Selenium, Puppeteer.',
                    'action' => 'block',
                    'conditions' => [
                        'user_agent_regex' => '/(scrapy|selenium|puppeteer|playwright|phantomjs)/i',
                    ],
                ],
                [
                    'name' => 'Protect Login & Auth from Brute Force',
                    'rule_type' => 'path_pattern',
                    'description' => 'Enforce challenge on suspicious rapid login attempts.',
                    'action' => 'challenge',
                    'conditions' => [
                        'path_pattern' => '/login*',
                        'methods' => ['POST'],
                    ],
                ],
                [
                    'name' => 'Checkout Speed Trap',
                    'rule_type' => 'path_pattern',
                    'description' => 'Challenge visitors submitting checkout without standard browser language headers.',
                    'action' => 'challenge',
                    'conditions' => [
                        'path_pattern' => '/checkout*',
                        'missing_header' => 'accept-language',
                    ],
                ],
                [
                    'name' => 'API Abuse Throttler',
                    'rule_type' => 'path_pattern',
                    'description' => 'Dynamic rate limiting for all JSON API endpoints.',
                    'action' => 'throttle',
                    'conditions' => [
                        'path_pattern' => '/api/*',
                    ],
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'rule_type' => 'required|string|max:40',
            'action' => 'required|string|in:block,challenge,throttle,monitor',
            'priority' => 'nullable|integer|min:1|max:100',
            'conditions' => 'required|array',
        ]);

        $rule = BotSecurityRule::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rule_type' => $validated['rule_type'],
            'action' => $validated['action'],
            'priority' => $validated['priority'] ?? 10,
            'conditions' => $validated['conditions'],
            'is_active' => true,
        ]);

        Cache::forget('bot_active_security_rules');
        BotAuditLog::record("Created Security Rule: {$rule->name}", 'bot_security_rule', (string) $rule->id);

        return back()->with('success', "Security rule '{$rule->name}' created.");
    }

    public function toggle(BotSecurityRule $rule): RedirectResponse
    {
        $rule->is_active = ! $rule->is_active;
        $rule->save();

        Cache::forget('bot_active_security_rules');
        BotAuditLog::record("Toggled Security Rule {$rule->name} (".($rule->is_active ? 'Active' : 'Disabled').')', 'bot_security_rule', (string) $rule->id);

        return back()->with('success', "Rule '{$rule->name}' ".($rule->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(BotSecurityRule $rule): RedirectResponse
    {
        $name = $rule->name;
        $rule->delete();

        Cache::forget('bot_active_security_rules');
        BotAuditLog::record("Deleted Security Rule: {$name}", 'bot_security_rule', (string) $rule->id);

        return back()->with('success', "Security rule '{$name}' deleted.");
    }
}
