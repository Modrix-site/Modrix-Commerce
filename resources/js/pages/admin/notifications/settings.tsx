import { Head, Link, useForm } from '@inertiajs/react';
import NotificationController from '@/actions/App/Http/Controllers/Admin/NotificationController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

type Kind = {
    kind: string;
    label: string;
    help?: string | null;
    bell: boolean;
    email: boolean;
};

type Channel = 'bell' | 'email';

const CHANNEL_LABELS: Record<Channel, string> = {
    bell: 'in the bell',
    email: 'by email',
};

export default function NotificationSettings({ kinds }: { kinds: Kind[] }) {
    const form = useForm<{
        preferences: Record<string, { bell: boolean; email: boolean }>;
    }>({
        preferences: Object.fromEntries(
            kinds.map((k) => [k.kind, { bell: k.bell, email: k.email }]),
        ),
    });

    const set = (kind: string, channel: Channel, on: boolean) =>
        form.setData('preferences', {
            ...form.data.preferences,
            [kind]: { ...form.data.preferences[kind], [channel]: on },
        });

    return (
        <>
            <Head title="Notification settings" />
            <AdminPage>
                <PageHeader
                    title="Notification settings"
                    description="Choose what you are told about, in the bell and by email. Only what your role may see is listed."
                    actions={
                        <Button variant="ghost" asChild>
                            <Link href={NotificationController.index.url()}>
                                All notifications
                            </Link>
                        </Button>
                    }
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Your notifications</CardTitle>
                        <CardDescription>
                            These settings are yours; other staff members choose
                            their own.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.put(
                                    NotificationController.updateSettings.url(),
                                    { preserveScroll: true },
                                );
                            }}
                            className="grid gap-4"
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 font-medium">
                                            Notification
                                        </th>
                                        <th className="w-28 py-2 text-center font-medium">
                                            In the bell
                                        </th>
                                        <th className="w-28 py-2 text-center font-medium">
                                            By email
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {kinds.map((k) => (
                                        <tr key={k.kind} className="border-b">
                                            <td className="py-2.5">
                                                {k.label}
                                                {k.help && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {k.help}
                                                    </p>
                                                )}
                                            </td>
                                            {(['bell', 'email'] as const).map(
                                                (channel) => (
                                                    <td
                                                        key={channel}
                                                        className="text-center"
                                                    >
                                                        <Checkbox
                                                            aria-label={`${k.label}, ${CHANNEL_LABELS[channel]}`}
                                                            checked={
                                                                form.data
                                                                    .preferences[
                                                                    k.kind
                                                                ]?.[channel]
                                                            }
                                                            onCheckedChange={(
                                                                c,
                                                            ) =>
                                                                set(
                                                                    k.kind,
                                                                    channel,
                                                                    c === true,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                ),
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save settings
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
