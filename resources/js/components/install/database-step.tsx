import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InstallController from '@/actions/App/Http/Controllers/Install/InstallController';
import { FormField } from '@/components/admin/form-field';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { ConnectionTestResult } from '@/types';

type Props = {
    tokenLocation: string;
    connectionTest: ConnectionTestResult | null;
};

/**
 * Manual installs: the database the hosting control panel created
 * (cPanel → MySQL Databases, Plesk → Databases, ...).
 */
export function DatabaseStep({ tokenLocation, connectionTest }: Props) {
    const form = useForm({
        host: 'localhost',
        port: '3306',
        database: '',
        username: '',
        db_password: '',
        setup_token: '',
    });

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.submit(InstallController.database(), {
            onError: () => form.reset('db_password'),
        });
    };

    const test = () => {
        form.submit(InstallController.testDatabase(), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <form onSubmit={save} className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Database</CardTitle>
                    <CardDescription>
                        Create a MySQL (5.7+) or MariaDB (10.3+) database and a
                        user with all privileges on it in your control panel,
                        then enter the details here. The installer saves them in{' '}
                        <code className="rounded bg-muted px-1">.env</code> and
                        creates the tables.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <FormField id="host" label="Host" error={form.errors.host}>
                        <Input
                            id="host"
                            value={form.data.host}
                            onChange={(e) =>
                                form.setData('host', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField id="port" label="Port" error={form.errors.port}>
                        <Input
                            id="port"
                            inputMode="numeric"
                            value={form.data.port}
                            onChange={(e) =>
                                form.setData('port', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="database"
                        label="Database name"
                        error={form.errors.database}
                    >
                        <Input
                            id="database"
                            value={form.data.database}
                            onChange={(e) =>
                                form.setData('database', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="username"
                        label="Database user"
                        error={form.errors.username}
                    >
                        <Input
                            id="username"
                            autoComplete="off"
                            value={form.data.username}
                            onChange={(e) =>
                                form.setData('username', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="db_password"
                        label="Database password"
                        error={form.errors.db_password}
                    >
                        <PasswordInput
                            id="db_password"
                            autoComplete="off"
                            value={form.data.db_password}
                            onChange={(e) =>
                                form.setData('db_password', e.target.value)
                            }
                        />
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Setup token</CardTitle>
                    <CardDescription>
                        To prove you control this server, open{' '}
                        <code className="rounded bg-muted px-1">
                            {tokenLocation}
                        </code>{' '}
                        in your control panel's file manager and paste its
                        contents.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FormField
                        id="setup_token"
                        label="Setup token"
                        error={form.errors.setup_token}
                    >
                        <Input
                            id="setup_token"
                            autoComplete="off"
                            value={form.data.setup_token}
                            onChange={(e) =>
                                form.setData('setup_token', e.target.value)
                            }
                            required
                        />
                    </FormField>
                </CardContent>
            </Card>

            {connectionTest && (
                <Alert variant={connectionTest.ok ? 'default' : 'destructive'}>
                    <AlertTitle>
                        {connectionTest.ok
                            ? 'Connection works'
                            : 'Connection failed'}
                    </AlertTitle>
                    <AlertDescription>
                        {connectionTest.message}
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex flex-wrap gap-3">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={test}
                >
                    Test connection
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing
                        ? 'Creating tables…'
                        : 'Save and create tables'}
                </Button>
            </div>
        </form>
    );
}
