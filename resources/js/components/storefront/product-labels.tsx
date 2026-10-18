import { cn } from '@/lib/utils';
import type { ProductLabel } from '@/types';

const TONE_CLASSES: Record<ProductLabel['tone'], string> = {
    new: 'bg-emerald-600 text-white',
    sale: 'bg-rose-600 text-white',
    featured: 'bg-amber-500 text-white',
    stock: 'bg-slate-700 text-white',
    info: 'bg-slate-900 text-white',
    warning: 'bg-orange-500 text-white',
    danger: 'bg-slate-800 text-white',
};

const SOFT_TONE_CLASSES: Record<ProductLabel['tone'], string> = {
    new: 'bg-emerald-100 text-emerald-800',
    sale: 'bg-rose-100 text-rose-800',
    featured: 'bg-amber-100 text-amber-900',
    stock: 'bg-slate-200 text-slate-800',
    info: 'bg-slate-200 text-slate-800',
    warning: 'bg-orange-100 text-orange-900',
    danger: 'bg-slate-200 text-slate-700',
};

const POSITION_CLASSES: Record<ProductLabel['position'], string> = {
    'top-left': 'top-2 left-2 items-start',
    'top-right': 'top-2 right-2 items-end',
    'bottom-left': 'bottom-2 left-2 items-start',
    'bottom-right': 'bottom-2 right-2 items-end',
};

type Style = 'solid' | 'soft' | 'outline';

type Props = {
    labels?: ProductLabel[];
    /** Applies when a label does not carry its own style. */
    style?: Style;
    className?: string;
};

/**
 * The badges a module contributes to a product card (Product Label). Positioned
 * over the product image; the first label decides where the stack sits. Renders
 * nothing when there are no labels, so themes can include it unconditionally.
 */
export function ProductLabels({ labels, style = 'solid', className }: Props) {
    if (!labels || labels.length === 0) {
        return null;
    }

    const position = labels[0].position;

    return (
        <div
            className={cn(
                'pointer-events-none absolute z-10 flex flex-col gap-1',
                POSITION_CLASSES[position],
                className,
            )}
        >
            {labels.map((label) => {
                const resolved = label.style ?? style;

                return (
                    <span
                        key={label.key}
                        className={cn(
                            'rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase shadow-sm',
                            resolved === 'solid' && TONE_CLASSES[label.tone],
                            resolved === 'soft' &&
                                SOFT_TONE_CLASSES[label.tone],
                            resolved === 'outline' &&
                                'border border-current bg-background text-foreground',
                        )}
                    >
                        {label.text}
                    </span>
                );
            })}
        </div>
    );
}
