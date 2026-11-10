import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck, ExternalLink } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    state: {
        enabled: boolean;
        measurementId: string | null;
        measurementIdValid: boolean;
        live: boolean;
        trackPageViews: boolean;
        anonymizeIp: boolean;
        debugMode: boolean;
    };
    settingsUrl: string;
};

export default function GoogleAnalyticsAdmin({ state, settingsUrl }: Props) {
    const checks: [boolean, string][] = [
        [state.enabled, 'Google Analytics is enabled'],
        [
            state.measurementIdValid,
            state.measurementId
                ? `Measurement ID ${state.measurementId}`
                : 'Add your Measurement ID (G-XXXXXXXXXX) in the settings',
        ],
    ];

    return (
        <>
            <Head title="Google Analytics" />
            <AdminPage>
                <PageHeader
                    title="Google Analytics"
                    description="The Google tag is loaded on every storefront page and a page view is recorded on each visit and navigation."
                    actions={
                        <>
                            <Badge
                                variant={state.live ? 'default' : 'secondary'}
                            >
                                {state.live ? 'Live' : 'Not active'}
                            </Badge>
                            <Button variant="outline" asChild>
                                <a href={settingsUrl}>
                                    <ExternalLink /> Settings
                                </a>
                            </Button>
                        </>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {state.live
                                ? 'Google Analytics is tracking'
                                : 'Set up Google Analytics'}
                        </CardTitle>
                        <CardDescription>
                            Events appear in Google Analytics under Reports →
                            Realtime within a minute of a visit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {checks.map(([done, label]) => (
                                <li key={label} className="flex gap-2">
                                    {done ? (
                                        <CircleCheck className="size-4 shrink-0 text-primary" />
                                    ) : (
                                        <CircleAlert className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span>{label}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tracking options</CardTitle>
                        <CardDescription>
                            Changed in Extensions → Google Analytics.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <Row
                            label="Page views on every navigation"
                            on={state.trackPageViews}
                        />
                        <Row
                            label="Anonymise IP addresses"
                            on={state.anonymizeIp}
                        />
                        <Row label="Debug mode" on={state.debugMode} />
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

function Row({ label, on }: { label: string; on: boolean }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span>{label}</span>
            <Badge variant={on ? 'secondary' : 'outline'}>
                {on ? 'On' : 'Off'}
            </Badge>
        </div>
    );
}
