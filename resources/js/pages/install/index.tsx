import { Head } from "@inertiajs/react";
import { ChecksList } from "@/components/admin/checks-list";
import { DatabaseStep } from "@/components/install/database-step";
import { OwnerStep } from "@/components/install/owner-step";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type {
    ConnectionTestResult,
    CurrencyOption,
    InstallMode,
    InstallProof,
    InstallStep,
    RequirementCheck,
} from "@/types";

type Props = {
    mode: InstallMode;
    step: InstallStep;
    checks: RequirementCheck[];
    ready: boolean;
    databaseProblem: "not_configured" | "unreachable" | "not_migrated" | null;
    servedFromPublic: boolean;
    proof: InstallProof;
    connectionTest: ConnectionTestResult | null;
    storageLinked: boolean | null;
    currencies: CurrencyOption[];
    timezones: string[];
    defaultTimezone: string;
    tokenLocation: string;
};

const PROBLEMS: Record<NonNullable<Props["databaseProblem"]>, string> = {
    not_configured: "No database is configured. Set the DB_* variables.",
    unreachable: "The configured database cannot be reached.",
    not_migrated:
        "Migrations have not run. Add php artisan migrate --force to your deploy script.",
};

function StepIndicator({
    mode,
    step,
}: {
    mode: InstallMode;
    step: InstallStep;
}) {
    const steps: { key: InstallStep; label: string }[] =
        mode === "manual"
            ? [
                  { key: "requirements", label: "Requirements" },
                  { key: "database", label: "Database" },
                  { key: "owner", label: "Owner" },
              ]
            : [
                  { key: "requirements", label: "Requirements" },
                  { key: "owner", label: "Owner" },
              ];
    const current = steps.findIndex((s) => s.key === step);

    return (
        <ol className="flex flex-wrap items-center gap-2 text-sm">
            {steps.map((s, index) => (
                <li
                    key={s.key}
                    aria-current={index === current ? "step" : undefined}
                    className={
                        index === current
                            ? "font-medium text-foreground"
                            : index < current
                              ? "text-muted-foreground line-through"
                              : "text-muted-foreground"
                    }
                >
                    {index + 1}. {s.label}
                    {index < steps.length - 1 && (
                        <span
                            aria-hidden
                            className="ms-2 text-muted-foreground"
                        >
                            →
                        </span>
                    )}
                </li>
            ))}
        </ol>
    );
}

export default function Install(props: Props) {
    const { mode, step, checks, ready, databaseProblem, servedFromPublic } =
        props;

    return (
        <>
            <Head title="Install" />
            <main className="mx-auto grid w-full max-w-3xl gap-6 px-4 py-10">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Install EcoModular
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {mode === "auto"
                            ? "Your hosting platform provides the database. Create the owner account to finish."
                            : "Check the server, connect your database, then create the owner account."}{" "}
                        You can also install from the command line with{" "}
                        <code className="rounded bg-muted px-1">
                            php artisan ecomodular:install
                        </code>
                        .
                    </p>
                    <StepIndicator mode={mode} step={step} />
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>Server requirements</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ChecksList checks={checks} />
                    </CardContent>
                </Card>

                {mode === "manual" && !servedFromPublic && (
                    <Alert variant="destructive">
                        <AlertTitle>Wrong document root</AlertTitle>
                        <AlertDescription>
                            This site is served from the project folder. Point
                            your domain's document root at the public/ folder in
                            your control panel, then reload.
                        </AlertDescription>
                    </Alert>
                )}

                {mode === "auto" && databaseProblem && (
                    <Alert variant="destructive">
                        <AlertTitle>Database not ready</AlertTitle>
                        <AlertDescription>
                            {PROBLEMS[databaseProblem]}
                        </AlertDescription>
                    </Alert>
                )}

                {props.storageLinked === false && (
                    <Alert>
                        <AlertTitle>Media link not created</AlertTitle>
                        <AlertDescription>
                            This host does not allow symbolic links, so
                            public/storage could not be created. Create it in
                            your control panel or ask your host; uploaded images
                            will not display until it exists.
                        </AlertDescription>
                    </Alert>
                )}

                {step === "requirements" &&
                    mode === "manual" &&
                    servedFromPublic && (
                        <Alert variant="destructive">
                            <AlertTitle>Installation blocked</AlertTitle>
                            <AlertDescription>
                                Fix the failing requirements above and reload
                                this page.
                            </AlertDescription>
                        </Alert>
                    )}

                {step === "database" && (
                    <DatabaseStep
                        tokenLocation={props.tokenLocation}
                        connectionTest={props.connectionTest}
                    />
                )}

                {step === "owner" &&
                    (ready ? (
                        <OwnerStep
                            proof={props.proof}
                            tokenLocation={props.tokenLocation}
                            currencies={props.currencies}
                            timezones={props.timezones}
                            defaultTimezone={props.defaultTimezone}
                        />
                    ) : (
                        <Alert variant="destructive">
                            <AlertTitle>Installation blocked</AlertTitle>
                            <AlertDescription>
                                Fix the failing requirements above and reload
                                this page.
                            </AlertDescription>
                        </Alert>
                    ))}
            </main>
        </>
    );
}
