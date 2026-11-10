import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Compass,
    Globe,
    Layers,
    PieChart,
    Server,
    Shield,
    Users,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    timeframe: string;
    summary: {
        total: number;
        human: number;
        bots: number;
        bot_percentage: number;
    };
    statusCodes: Record<string, number>;
    classifications: Record<string, number>;
    geoBreakdown: Array<{ country_code: string; count: number; bot_count: number }>;
    timeline: Array<{ date: string; total: number; bots: number; human: number }>;
};

export default function TrafficAnalytics({
    timeframe = '7d',
    summary,
    statusCodes = {},
    classifications = {},
    geoBreakdown = [],
    timeline = [],
}: Props) {
    const handleTimeframeChange = (tf: string) => {
        router.get(`/admin/m/modrixcommerce/bot-protection/traffic?timeframe=${tf}`);
    };

    const maxTimeline = Math.max(1, ...timeline.map((t) => t.total || 0));

    return (
        <AdminPage>
            <Head title="Bot Protection - Traffic Analytics" />

            <PageHeader
                title="Traffic Analytics"
                description="Deep-dive traffic inspection, human vs bot ratio, status codes, and scraping velocity."
                actions={
                    <div className="flex items-center gap-1 bg-muted/60 p-1 rounded-lg border border-border/60">
                        {['24h', '7d', '30d'].map((tf) => (
                            <Button
                                key={tf}
                                size="sm"
                                variant={timeframe === tf ? 'default' : 'ghost'}
                                className="h-7 px-3 text-xs uppercase"
                                onClick={() => handleTimeframeChange(tf)}
                            >
                                {tf}
                            </Button>
                        ))}
                    </div>
                }
            />

            {/* Summary Overview Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Total Evaluated</span>
                        <div className="mt-2 text-3xl font-bold">{summary?.total?.toLocaleString() || 0}</div>
                        <p className="text-xs text-muted-foreground mt-1">Incoming HTTP/HTTPS requests</p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Human Shoppers</span>
                        <div className="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {summary?.human?.toLocaleString() || 0}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">Verified legitimate sessions</p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Automated Traffic</span>
                        <div className="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">
                            {summary?.bots?.toLocaleString() || 0}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">Scrapers, crawlers & bots</p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Bot Ratio</span>
                        <div className="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                            {summary?.bot_percentage || 0}%
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">Portion of automated traffic</p>
                    </CardContent>
                </Card>
            </div>

            {/* Timeline Area & Ratio Breakdown */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <Card className="lg:col-span-2 border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="font-semibold text-base flex items-center gap-2">
                                    <BarChart3 className="h-4 w-4 text-primary" />
                                    Daily Traffic Trend ({timeframe})
                                </h3>
                                <p className="text-xs text-muted-foreground">Historical volume of human vs bot requests.</p>
                            </div>
                            <div className="flex items-center gap-3 text-xs">
                                <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Human</span>
                                <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Bot</span>
                            </div>
                        </div>

                        <div className="h-48 flex items-end gap-2 pt-6 border-b border-border/40 pb-2">
                            {timeline.length === 0 ? (
                                <p className="w-full text-center text-xs text-muted-foreground self-center">No trend data for selected timeframe.</p>
                            ) : (
                                timeline.map((pt, i) => {
                                    const humanH = Math.round((pt.human / maxTimeline) * 100);
                                    const botH = Math.round((pt.bots / maxTimeline) * 100);
                                    return (
                                        <div key={i} className="flex-1 flex flex-col justify-end items-center h-full group relative">
                                            <div className="w-full flex flex-col justify-end rounded-t overflow-hidden">
                                                {botH > 0 && <div style={{ height: `${Math.max(4, botH)}%` }} className="w-full bg-amber-500/80 group-hover:bg-amber-500" />}
                                                <div style={{ height: `${Math.max(humanH > 0 ? 4 : 0, humanH)}%` }} className="w-full bg-emerald-500/70 group-hover:bg-emerald-500" />
                                            </div>
                                            <span className="text-[10px] text-muted-foreground mt-1">{pt.date}</span>
                                            <div className="absolute bottom-full mb-2 hidden group-hover:flex flex-col bg-popover text-popover-foreground text-[11px] p-2 rounded shadow-lg border border-border whitespace-nowrap z-20 pointer-events-none">
                                                <span className="font-bold">{pt.date}</span>
                                                <span className="text-emerald-500">Human: {pt.human}</span>
                                                <span className="text-amber-500">Bot: {pt.bots}</span>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Status Codes & Client Classification */}
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-6">
                        <h3 className="font-semibold text-base mb-1 flex items-center gap-2">
                            <Server className="h-4 w-4 text-primary" />
                            HTTP Status Breakdown
                        </h3>
                        <p className="text-xs text-muted-foreground mb-4">Response codes served to visitors.</p>

                        <div className="space-y-3">
                            {Object.entries(statusCodes).length === 0 ? (
                                <p className="text-xs text-muted-foreground py-4 text-center">No status code logs available.</p>
                            ) : (
                                Object.entries(statusCodes).map(([code, count]) => (
                                    <div key={code} className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-mono font-medium">HTTP {code}</span>
                                            <span className="text-muted-foreground font-semibold">{count}</span>
                                        </div>
                                        <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                            <div
                                                className={`h-full rounded-full ${
                                                    code.startsWith('2') ? 'bg-emerald-500' : code.startsWith('4') ? 'bg-rose-500' : 'bg-blue-500'
                                                }`}
                                                style={{ width: `${Math.min(100, Math.max(8, (count / Math.max(1, ...Object.values(statusCodes))) * 100))}%` }}
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Geographic Attack Distribution Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <h3 className="font-semibold text-base mb-1 flex items-center gap-2">
                        <Globe className="h-4 w-4 text-blue-500" />
                        Geographic Origin of Threats
                    </h3>
                    <p className="text-xs text-muted-foreground mb-4">
                        Country breakdown showing total requests vs suspected automated traffic.
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-muted-foreground text-left">
                                    <th className="pb-2 font-medium">Country Code</th>
                                    <th className="pb-2 font-medium text-right">Total Requests</th>
                                    <th className="pb-2 font-medium text-right">Bot Requests</th>
                                    <th className="pb-2 font-medium text-right">Threat Ratio</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {geoBreakdown.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="py-6 text-center text-muted-foreground">
                                            No geographic logs available for this period.
                                        </td>
                                    </tr>
                                ) : (
                                    geoBreakdown.map((geo, idx) => {
                                        const ratio = geo.count > 0 ? Math.round((geo.bot_count / geo.count) * 100) : 0;
                                        return (
                                            <tr key={idx} className="hover:bg-muted/30 transition">
                                                <td className="py-2.5 font-bold font-mono text-foreground flex items-center gap-2">
                                                    <span className="rounded bg-muted px-2 py-0.5">{geo.country_code}</span>
                                                </td>
                                                <td className="py-2.5 text-right font-medium">{geo.count.toLocaleString()}</td>
                                                <td className="py-2.5 text-right font-semibold text-amber-600 dark:text-amber-400">
                                                    {geo.bot_count.toLocaleString()}
                                                </td>
                                                <td className="py-2.5 text-right">
                                                    <Badge variant="outline" className={ratio >= 50 ? 'border-rose-500/30 text-rose-600' : ''}>
                                                        {ratio}%
                                                    </Badge>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </AdminPage>
    );
}
