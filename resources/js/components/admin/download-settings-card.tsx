import { Form, usePage } from '@inertiajs/react';
import DownloadSettingsController from '@/actions/App/Http/Controllers/Admin/DownloadSettingsController';
import { FormField } from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/**
 * Store settings: limits for new download links of digital products. Saves
 * on its own (outside the store settings form).
 */
export function DownloadSettingsCard() {
    const { downloads } = usePage<{
        downloads?: { limit: number; expiry_days: number };
    }>().props;

    if (!downloads) {
        return null;
    }

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle>Digital downloads</CardTitle>
                <CardDescription>
                    Applies to new download links. Use 0 for unlimited downloads
                    or links that never expire.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...DownloadSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                id="download_limit"
                                label="Downloads per file"
                                error={errors.download_limit}
                            >
                                <Input
                                    id="download_limit"
                                    name="download_limit"
                                    type="number"
                                    min={0}
                                    max={1000}
                                    defaultValue={downloads.limit}
                                />
                            </FormField>
                            <FormField
                                id="download_expiry_days"
                                label="Link expires after (days)"
                                error={errors.download_expiry_days}
                            >
                                <Input
                                    id="download_expiry_days"
                                    name="download_expiry_days"
                                    type="number"
                                    min={0}
                                    max={3650}
                                    defaultValue={downloads.expiry_days}
                                />
                            </FormField>
                            <Button type="submit" disabled={processing}>
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
