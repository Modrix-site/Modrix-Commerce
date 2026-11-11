import { Head, router } from '@inertiajs/react';
import { BarChart3, Globe, Server } from 'lucide-react';
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
    geoBreakdown: Array<{
        country_code: string;
        count: number;
        bot_count: number;
    }>;
    timeline: Array<{
        date: string;
        total: number;
        bots: number;
        human: number;
    }>;
};

export default function TrafficAnalytics({
    timeframe = '7d',
    summary,
    statusCodes = {},
    classifications: _classifications = {},
    geoBreakdown = [],
    timeline = [],
}: Props) {
    const handleTimeframeChange = (tf: string) => {
        router.get(
            `/admin/m/modrixcommerce/bot-protection/traffic?timeframe=${tf}`,
        );
    };

    const maxTimeline = Math.max(1, ...timeline.map((t) => t.total || 0));

    return (
        <AdminPage>
            <Head title="Bot Protection - Traffic Analytics" />

            <PageHeader
                title="Traffic Analytics"
                description="Deep-dive traffic inspection, human vs bot ratio, status codes, and scraping velocity."
                actions={
                    <div className="flex items-center gap-1 rounded-lg border border-border/60 bg-muted/60 p-1">
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
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Total Evaluated
                        </span>
                        <div className="mt-2 text-3xl font-bold">
                            {summary?.total?.toLocaleString() || 0}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Incoming HTTP/HTTPS requests
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Human Shoppers
                        </span>
                        <div className="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {summary?.human?.toLocaleString() || 0}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Verified legitimate sessions
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Automated Traffic
                        </span>
                        <div className="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">
                            {summary?.bots?.toLocaleString() || 0}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Scrapers, crawlers & bots
                        </p>
                    </CardContent>
                </Card>

                <Card className="border border-border/60 shadow-sm">
                    <CardContent className="p-5">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Bot Ratio
                        </span>
                        <div className="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                            {summary?.bot_percentage || 0}%
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Portion of automated traffic
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Timeline Area & Ratio Breakdown */}
            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card className="border border-border/60 shadow-sm lg:col-span-2">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h3 className="flex items-center gap-2 text-base font-semibold">
                                    <BarChart3 className="h-4 w-4 text-primary" />
                                    Daily Traffic Trend ({timeframe})
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Historical volume of human vs bot requests.
                                </p>
                            </div>
                            <div className="flex items-center gap-3 text-xs">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{' '}
                                    Human
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-2.5 w-2.5 rounded-full bg-amber-500"></span>{' '}
                                    Bot
                                </span>
                            </div>
                        </div>

                        <div className="flex h-48 items-end gap-2 border-b border-border/40 pt-6 pb-2">
                            {timeline.length === 0 ? (
                                <p className="w-full self-center text-center text-xs text-muted-foreground">
                                    No trend data for selected timeframe.
                                </p>
                            ) : (
                                timeline.map((pt, i) => {
                                    const humanH = Math.round(
                                        (pt.human / maxTimeline) * 100,
                                    );
                                    const botH = Math.round(
                                        (pt.bots / maxTimeline) * 100,
                                    );
                                    return (
                                        <div
                                            key={i}
                                            className="group relative flex h-full flex-1 flex-col items-center justify-end"
                                        >
                                            <div className="flex w-full flex-col justify-end overflow-hidden rounded-t">
                                                {botH > 0 && (
                                                    <div
                                                        style={{
                                                            height: `${Math.max(4, botH)}%`,
                                                        }}
                                                        className="w-full bg-amber-500/80 group-hover:bg-amber-500"
                                                    />
                                                )}
                                                <div
                                                    style={{
                                                        height: `${Math.max(humanH > 0 ? 4 : 0, humanH)}%`,
                                                    }}
                                                    className="w-full bg-emerald-500/70 group-hover:bg-emerald-500"
                                                />
                                            </div>
                                            <span className="mt-1 text-[10px] text-muted-foreground">
                                                {pt.date}
                                            </span>
                                            <div className="pointer-events-none absolute bottom-full z-20 mb-2 hidden flex-col rounded border border-border bg-popover p-2 text-[11px] whitespace-nowrap text-popover-foreground shadow-lg group-hover:flex">
                                                <span className="font-bold">
                                                    {pt.date}
                                                </span>
                                                <span className="text-emerald-500">
                                                    Human: {pt.human}
                                                </span>
                                                <span className="text-amber-500">
                                                    Bot: {pt.bots}
                                                </span>
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
                        <h3 className="mb-1 flex items-center gap-2 text-base font-semibold">
                            <Server className="h-4 w-4 text-primary" />
                            HTTP Status Breakdown
                        </h3>
                        <p className="mb-4 text-xs text-muted-foreground">
                            Response codes served to visitors.
                        </p>

                        <div className="space-y-3">
                            {Object.entries(statusCodes).length === 0 ? (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No status code logs available.
                                </p>
                            ) : (
                                Object.entries(statusCodes).map(
                                    ([code, count]) => (
                                        <div key={code} className="space-y-1">
                                            <div className="flex justify-between text-xs">
                                                <span className="font-mono font-medium">
                                                    HTTP {code}
                                                </span>
                                                <span className="font-semibold text-muted-foreground">
                                                    {count}
                                                </span>
                                            </div>
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-full rounded-full ${
                                                        code.startsWith('2')
                                                            ? 'bg-emerald-500'
                                                            : code.startsWith(
                                                                    '4',
                                                                )
                                                              ? 'bg-rose-500'
                                                              : 'bg-blue-500'
                                                    }`}
                                                    style={{
                                                        width: `${Math.min(100, Math.max(8, (count / Math.max(1, ...Object.values(statusCodes))) * 100))}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ),
                                )
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Geographic Attack Distribution Table */}
            <Card className="border border-border/60 shadow-sm">
                <CardContent className="p-6">
                    <h3 className="mb-1 flex items-center gap-2 text-base font-semibold">
                        <Globe className="h-4 w-4 text-blue-500" />
                        Geographic Origin of Threats
                    </h3>
                    <p className="mb-4 text-xs text-muted-foreground">
                        Country breakdown showing total requests vs suspected
                        automated traffic.
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-border/60 text-left text-muted-foreground">
                                    <th className="pb-2 font-medium">
                                        Country Code
                                    </th>
                                    <th className="pb-2 text-right font-medium">
                                        Total Requests
                                    </th>
                                    <th className="pb-2 text-right font-medium">
                                        Bot Requests
                                    </th>
                                    <th className="pb-2 text-right font-medium">
                                        Threat Ratio
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/30">
                                {geoBreakdown.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            No geographic logs available for
                                            this period.
                                        </td>
                                    </tr>
                                ) : (
                                    geoBreakdown.map((geo, idx) => {
                                        const ratio =
                                            geo.count > 0
                                                ? Math.round(
                                                      (geo.bot_count /
                                                          geo.count) *
                                                          100,
                                                  )
                                                : 0;
                                        return (
                                            <tr
                                                key={idx}
                                                className="transition hover:bg-muted/30"
                                            >
                                                <td className="flex items-center gap-2 py-2.5 font-mono font-bold text-foreground">
                                                    <span className="rounded bg-muted px-2 py-0.5">
                                                        {geo.country_code}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 text-right font-medium">
                                                    {geo.count.toLocaleString()}
                                                </td>
                                                <td className="py-2.5 text-right font-semibold text-amber-600 dark:text-amber-400">
                                                    {geo.bot_count.toLocaleString()}
                                                </td>
                                                <td className="py-2.5 text-right">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            ratio >= 50
                                                                ? 'border-rose-500/30 text-rose-600'
                                                                : ''
                                                        }
                                                    >
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
