import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function PackageUpload({ type }: { type: 'theme' | 'extension' }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ package: File | null }>({ package: null });
    const title = type === 'theme' ? 'Upload theme' : 'Upload extension';
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (!value) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <Upload />
                    {title}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    Choose a developer ZIP package, up to 50 MB. Include{' '}
                    {type === 'theme' ? 'theme.json' : 'module.json'} at the ZIP
                    root. Existing packages are kept.
                </DialogDescription>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            type === 'theme'
                                ? '/admin/themes/packages'
                                : '/admin/extensions/packages',
                            {
                                forceFormData: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
                            },
                        );
                    }}
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`package-${type}`}>ZIP package</Label>
                        <Input
                            id={`package-${type}`}
                            type="file"
                            accept=".zip,application/zip"
                            onChange={(event) =>
                                form.setData(
                                    'package',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.package} />
                    </div>
                    {type === 'extension' && (
                        <p className="text-sm text-muted-foreground">
                            After upload, review the extension, then install and
                            enable it. Extensions with React pages require an
                            application asset build.
                        </p>
                    )}
                    {form.progress && (
                        <progress
                            className="w-full"
                            value={form.progress.percentage}
                            max={100}
                            aria-label="Upload progress"
                        />
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!form.data.package || form.processing}
                        >
                            {form.processing ? 'Uploading…' : title}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
