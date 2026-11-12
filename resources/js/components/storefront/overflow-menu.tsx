import { ChevronDown } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { StoreMenuTree } from '@/components/storefront/store-menu';
import type { StoreMenu } from '@/types/storefront';
import '../../../css/store-overflow-menu.css';

type Props = {
    menu?: StoreMenu;
    /** Label of the button that opens the links that do not fit. */
    moreLabel?: string;
    /** Room kept for that button, in pixels. */
    moreWidth?: number;
    className?: string;
};

/**
 * A header menu that never wraps: the links that fit stay on one line and
 * the rest go into a dropdown ("See more"). Measured again whenever the bar
 * changes width or the fonts load. For any theme's header.
 */
export function OverflowMenuLinks({
    menu,
    moreLabel = 'More',
    moreWidth = 110,
    className = '',
}: Props) {
    const box = useRef<HTMLDivElement>(null);
    const more = useRef<HTMLDivElement>(null);
    const listId = useId();
    const [visible, setVisible] = useState<number | null>(null);
    const [open, setOpen] = useState(false);
    const links = menu?.links ?? [];

    const measure = useCallback(() => {
        const el = box.current;
        const items = el
            ? [
                  ...el.querySelectorAll<HTMLElement>(
                      ':scope > .store-menu-tree > .store-menu-item',
                  ),
              ]
            : [];

        if (!el || items.length === 0) {
            return;
        }

        // Show everything, measure, then hide what does not fit.
        items.forEach((item) => (item.style.display = ''));
        const tree = el.querySelector<HTMLElement>(':scope > .store-menu-tree');
        const gap = tree
            ? parseFloat(getComputedStyle(tree).columnGap) || 0
            : 0;
        const widths = items.map((item) => item.getBoundingClientRect().width);
        const total =
            widths.reduce((sum, w) => sum + w, 0) + gap * (widths.length - 1);
        let count = items.length;

        if (total > el.clientWidth) {
            const room = el.clientWidth - moreWidth - gap;
            let used = 0;
            count = 0;

            for (const width of widths) {
                used += (count > 0 ? gap : 0) + width;

                if (used > room) {
                    break;
                }

                count++;
            }
        }

        items.forEach(
            (item, i) => (item.style.display = i < count ? '' : 'none'),
        );
        setVisible(count < items.length ? count : null);
    }, [moreWidth]);

    useLayoutEffect(measure, [measure, links.length]);

    useEffect(() => {
        const el = box.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(() => measure());
        observer.observe(el);
        void document.fonts?.ready.then(measure);

        return () => observer.disconnect();
    }, [measure]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!more.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('click', close);

        return () => document.removeEventListener('click', close);
    }, [open]);

    if (menu?.enabled === false || links.length === 0) {
        return null;
    }

    return (
        <div className={`store-overflow-menu ${className}`} ref={box}>
            <StoreMenuTree menu={menu} orientation="horizontal" />
            {visible !== null && (
                <div
                    className="store-overflow-more"
                    ref={more}
                    onKeyDown={(event) =>
                        event.key === 'Escape' && setOpen(false)
                    }
                >
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls={listId}
                        onClick={() => setOpen(!open)}
                    >
                        {moreLabel} <ChevronDown size={14} aria-hidden />
                    </button>
                    <div
                        id={listId}
                        className="store-overflow-list"
                        hidden={!open}
                    >
                        <StoreMenuTree
                            links={links.slice(visible)}
                            orientation="vertical"
                            onNavigate={() => setOpen(false)}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
