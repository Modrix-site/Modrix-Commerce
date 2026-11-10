import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    AlertOctagon,
    Bell,
    Check,
    Cloud,
    Lock,
    Save,
    Settings as SettingsIcon,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sliders,
    Zap,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type SettingsData = {
    enabled: boolean;
    sensitivity_level: string;
    challenge_threshold: number;
    block_threshold: number;
    captcha_provider: string;
    captcha_site_key: string;
    captcha_secret_key: string;
    rate_limit_per_minute: number;
    api_rate_limit_per_minute: number;
    login_rate_limit_per_minute: number;
    prevent_rapid_checkout: boolean;
    min_checkout_seconds: number;
    prevent_inventory_hoarding: boolean;
    anti_scraping_enabled: boolean;
    block_cloud_hosting_asn: boolean;
    email_alerts_enabled: boolean;
    alert_emails: string;
    slack_webhook_url: string;
    custom_webhook_url: string;
};

type Props = {
    settings: SettingsData;
};

export default function BotSettings({ settings }: Props) {
    const [activeTab, setActiveTab] = useState<'general' | 'captcha' | 'ratelimit' | 'checkout' | 'alerts'>('general');

    const form = useForm({
        enabled: settings.enabled ?? true,
        sensitivity_level: settings.sensitivity_level ?? 'medium',
        challenge_threshold: settings.challenge_threshold ?? 45,
        block_threshold: settings.block_threshold ?? 80,
        captcha_provider: settings.captcha_provider ?? 'custom_interactive',
        captcha_site_key: settings.captcha_site_key ?? '',
        captcha_secret_key: settings.captcha_secret_key ?? '',
        rate_limit_per_minute: settings.rate_limit_per_minute ?? 120,
        api_rate_limit_per_minute: settings.api_rate_limit_per_minute ?? 60,
        login_rate_limit_per_minute: settings.login_rate_limit_per_minute ?? 5,
        prevent_rapid_checkout: settings.prevent_rapid_checkout ?? true,
        min_checkout_seconds: settings.min_checkout_seconds ?? 4,
        prevent_inventory_hoarding: settings.prevent_inventory_hoarding ?? true,
        anti_scraping_enabled: settings.anti_scraping_enabled ?? true,
        block_cloud_hosting_asn: settings.block_cloud_hosting_asn ?? false,
        email_alerts_enabled: settings.email_alerts_enabled ?? true,
        alert_emails: settings.alert_emails ?? '',
        slack_webhook_url: settings.slack_webhook_url ?? '',
        custom_webhook_url: settings.custom_webhook_url ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/m/modrixcommerce/bot-protection/settings');
    };

    return (
        <AdminPage>
            <Head title="Bot Protection - Settings" />

            <PageHeader
                title="Bot Protection Settings"
                description="Configure threat scoring sensitivity, CAPTCHA providers, rate limits, and incident notifications."
                actions={
                    <Button onClick={handleSubmit} disabled={form.processing} className="gap-2">
                        <Save className="h-4 w-4" /> Save Settings
                    </Button>
                }
            />

            {/* Navigation Tabs */}
            <div className="flex items-center gap-2 border-b border-border/60 pb-3 mb-6 overflow-x-auto">
                <Button
                    variant={activeTab === 'general' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('general')}
                >
                    <Sliders className="h-4 w-4" /> General & Scoring
                </Button>
                <Button
                    variant={activeTab === 'captcha' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('captcha')}
                >
                    <ShieldCheck className="h-4 w-4" /> CAPTCHA Configuration
                </Button>
                <Button
                    variant={activeTab === 'ratelimit' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('ratelimit')}
                >
                    <Zap className="h-4 w-4" /> Rate Limiting & Anti-Scraping
                </Button>
                <Button
                    variant={activeTab === 'checkout' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('checkout')}
                >
                    <Lock className="h-4 w-4" /> Checkout & Inventory Shield
                </Button>
                <Button
                    variant={activeTab === 'alerts' ? 'default' : 'ghost'}
                    size="sm"
                    className="gap-2 text-xs"
                    onClick={() => setActiveTab('alerts')}
                >
                    <Bell className="h-4 w-4" /> Alerts & Webhooks
                </Button>
            </div>

            <form onSubmit={handleSubmit}>
                {/* TAB 1: GENERAL & SCORING */}
                {activeTab === 'general' && (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-6 space-y-6 text-xs">
                            <div className="flex items-center justify-between pb-4 border-b border-border/60">
                                <div>
                                    <h4 className="font-semibold text-sm text-foreground">Real-Time Bot Detection Engine</h4>
                                    <p className="text-muted-foreground">Analyze all incoming storefront requests and score behavioral anomalies.</p>
                                </div>
                                <input
                                    type="checkbox"
                                    className="h-5 w-5 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer"
                                    checked={form.data.enabled}
                                    onChange={(e) => form.setData('enabled', e.target.checked)}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="font-semibold block mb-1">Threat Sensitivity Level</label>
                                    <select
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs"
                                        value={form.data.sensitivity_level}
                                        onChange={(e) => form.setData('sensitivity_level', e.target.value)}
                                    >
                                        <option value="low">Low (Standard store traffic, minimal challenges)</option>
                                        <option value="medium">Medium (Recommended balanced detection)</option>
                                        <option value="high">High (Strict defense, ideal during flash sales)</option>
                                        <option value="paranoid">Paranoid (Challenge all unrecognized user agents)</option>
                                    </select>
                                    <p className="text-muted-foreground mt-1">Adjusts multiplier applied to suspicious heuristic signals.</p>
                                </div>

                                <div>
                                    <label className="font-semibold block mb-1">Interactive Challenge Threshold (Score 10–99)</label>
                                    <Input
                                        type="number"
                                        min={10}
                                        max={99}
                                        value={form.data.challenge_threshold}
                                        onChange={(e) => form.setData('challenge_threshold', parseInt(e.target.value) || 45)}
                                    />
                                    <p className="text-muted-foreground mt-1">Visitors scoring at or above this threshold receive a CAPTCHA challenge.</p>
                                </div>

                                <div>
                                    <label className="font-semibold block mb-1">Immediate Block Threshold (Score 50–100)</label>
                                    <Input
                                        type="number"
                                        min={50}
                                        max={100}
                                        value={form.data.block_threshold}
                                        onChange={(e) => form.setData('block_threshold', parseInt(e.target.value) || 80)}
                                    />
                                    <p className="text-muted-foreground mt-1">Requests scoring at or above this threshold are blocked immediately with HTTP 403.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* TAB 2: CAPTCHA */}
                {activeTab === 'captcha' && (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-6 space-y-6 text-xs">
                            <div>
                                <label className="font-semibold block mb-1">CAPTCHA Provider</label>
                                <select
                                    className="w-full max-w-md h-9 rounded-md border border-input bg-background px-3 text-xs"
                                    value={form.data.captcha_provider}
                                    onChange={(e) => form.setData('captcha_provider', e.target.value)}
                                >
                                    <option value="custom_interactive">Interactive Security Verification (Built-in, zero setup)</option>
                                    <option value="turnstile">Cloudflare Turnstile (Recommended, invisible)</option>
                                    <option value="recaptcha_v2">Google reCAPTCHA v2 (Checkbox)</option>
                                    <option value="recaptcha_v3">Google reCAPTCHA v3 (Invisible)</option>
                                    <option value="hcaptcha">hCaptcha</option>
                                </select>
                                <p className="text-muted-foreground mt-1">
                                    The built-in verification works automatically out of the box with zero third-party API dependencies.
                                </p>
                            </div>

                            {form.data.captcha_provider !== 'custom_interactive' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-border/60">
                                    <div>
                                        <label className="font-semibold block mb-1">Site Key</label>
                                        <Input
                                            placeholder="Enter public site key"
                                            value={form.data.captcha_site_key}
                                            onChange={(e) => form.setData('captcha_site_key', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <label className="font-semibold block mb-1">Secret Key</label>
                                        <Input
                                            type="password"
                                            placeholder="Enter private secret key"
                                            value={form.data.captcha_secret_key}
                                            onChange={(e) => form.setData('captcha_secret_key', e.target.value)}
                                        />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* TAB 3: RATE LIMITING & ANTI-SCRAPING */}
                {activeTab === 'ratelimit' && (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-6 space-y-6 text-xs">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label className="font-semibold block mb-1">Storefront Rate Limit (req/min)</label>
                                    <Input
                                        type="number"
                                        min={10}
                                        max={600}
                                        value={form.data.rate_limit_per_minute}
                                        onChange={(e) => form.setData('rate_limit_per_minute', parseInt(e.target.value) || 120)}
                                    />
                                    <p className="text-muted-foreground mt-1">Maximum storefront page requests per IP per minute.</p>
                                </div>

                                <div>
                                    <label className="font-semibold block mb-1">API Endpoints Rate Limit (req/min)</label>
                                    <Input
                                        type="number"
                                        min={10}
                                        max={300}
                                        value={form.data.api_rate_limit_per_minute}
                                        onChange={(e) => form.setData('api_rate_limit_per_minute', parseInt(e.target.value) || 60)}
                                    />
                                    <p className="text-muted-foreground mt-1">Maximum JSON API calls per IP per minute.</p>
                                </div>

                                <div>
                                    <label className="font-semibold block mb-1">Failed Login Limit (attempts/15m)</label>
                                    <Input
                                        type="number"
                                        min={3}
                                        max={20}
                                        value={form.data.login_rate_limit_per_minute}
                                        onChange={(e) => form.setData('login_rate_limit_per_minute', parseInt(e.target.value) || 5)}
                                    />
                                    <p className="text-muted-foreground mt-1">Lockout triggered on repeat failed authentication.</p>
                                </div>
                            </div>

                            <div className="pt-4 border-t border-border/60 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="font-semibold text-foreground">Anti-Scraping & Price Monitoring Defense</h4>
                                        <p className="text-muted-foreground">Detect rapid catalog pagination and traps bots via hidden honeypots.</p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        className="h-5 w-5 rounded border-gray-300 text-primary cursor-pointer"
                                        checked={form.data.anti_scraping_enabled}
                                        onChange={(e) => form.setData('anti_scraping_enabled', e.target.checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div>
                                        <h4 className="font-semibold text-foreground">Block Commercial Datacenter / Cloud ASN Traffic</h4>
                                        <p className="text-muted-foreground">Restrict requests originating from AWS, DigitalOcean, Hetzner, and OVH.</p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        className="h-5 w-5 rounded border-gray-300 text-primary cursor-pointer"
                                        checked={form.data.block_cloud_hosting_asn}
                                        onChange={(e) => form.setData('block_cloud_hosting_asn', e.target.checked)}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* TAB 4: CHECKOUT & INVENTORY */}
                {activeTab === 'checkout' && (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-6 space-y-6 text-xs">
                            <div className="flex items-center justify-between pb-4 border-b border-border/60">
                                <div>
                                    <h4 className="font-semibold text-foreground">Prevent Rapid Automated Checkouts</h4>
                                    <p className="text-muted-foreground">Hold or challenge orders submitted faster than humanly possible.</p>
                                </div>
                                <input
                                    type="checkbox"
                                    className="h-5 w-5 rounded border-gray-300 text-primary cursor-pointer"
                                    checked={form.data.prevent_rapid_checkout}
                                    onChange={(e) => form.setData('prevent_rapid_checkout', e.target.checked)}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="font-semibold block mb-1">Minimum Human Checkout Duration (Seconds)</label>
                                    <Input
                                        type="number"
                                        min={2}
                                        max={30}
                                        value={form.data.min_checkout_seconds}
                                        onChange={(e) => form.setData('min_checkout_seconds', parseInt(e.target.value) || 4)}
                                    />
                                    <p className="text-muted-foreground mt-1">Checkouts completed in fewer seconds than this are flagged as automated bot scripts.</p>
                                </div>

                                <div className="flex items-center justify-between p-4 bg-muted/30 rounded-lg">
                                    <div>
                                        <h4 className="font-semibold text-foreground">Inventory Hoarding Shield</h4>
                                        <p className="text-muted-foreground">Stop automated scalper bots from reserving limited-stock items in cart.</p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        className="h-5 w-5 rounded border-gray-300 text-primary cursor-pointer"
                                        checked={form.data.prevent_inventory_hoarding}
                                        onChange={(e) => form.setData('prevent_inventory_hoarding', e.target.checked)}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* TAB 5: ALERTS & NOTIFICATIONS */}
                {activeTab === 'alerts' && (
                    <Card className="border border-border/60 shadow-sm">
                        <CardContent className="p-6 space-y-6 text-xs">
                            <div className="flex items-center justify-between pb-4 border-b border-border/60">
                                <div>
                                    <h4 className="font-semibold text-foreground">Email Attack Spike Notifications</h4>
                                    <p className="text-muted-foreground">Receive instant alerts when sudden surges of malicious bot traffic are intercepted.</p>
                                </div>
                                <input
                                    type="checkbox"
                                    className="h-5 w-5 rounded border-gray-300 text-primary cursor-pointer"
                                    checked={form.data.email_alerts_enabled}
                                    onChange={(e) => form.setData('email_alerts_enabled', e.target.checked)}
                                />
                            </div>

                            <div>
                                <label className="font-semibold block mb-1">Alert Email Recipients (comma-separated)</label>
                                <Input
                                    placeholder="security@store.com, admin@store.com"
                                    value={form.data.alert_emails}
                                    onChange={(e) => form.setData('alert_emails', e.target.value)}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-border/60">
                                <div>
                                    <label className="font-semibold block mb-1">Slack Webhook URL</label>
                                    <Input
                                        placeholder="https://hooks.slack.com/services/..."
                                        value={form.data.slack_webhook_url}
                                        onChange={(e) => form.setData('slack_webhook_url', e.target.value)}
                                    />
                                    <p className="text-muted-foreground mt-1">Post rich incident blocks to your Slack security channel.</p>
                                </div>

                                <div>
                                    <label className="font-semibold block mb-1">Custom Incident Webhook URL</label>
                                    <Input
                                        placeholder="https://siem.example.com/api/v1/webhook"
                                        value={form.data.custom_webhook_url}
                                        onChange={(e) => form.setData('custom_webhook_url', e.target.value)}
                                    />
                                    <p className="text-muted-foreground mt-1">Send JSON attack alerts directly to your SIEM / security monitor.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="mt-6 flex justify-end">
                    <Button type="submit" disabled={form.processing} className="gap-2">
                        <Save className="h-4 w-4" /> Save Configuration
                    </Button>
                </div>
            </form>
        </AdminPage>
    );
}
