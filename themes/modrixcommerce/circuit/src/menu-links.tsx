import { ChevronDown } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { StoreMenuTree } from '@/components/storefront/store-menu';
import type { StoreMenu } from '@/types/storefront';

/** Room kept for the "More" button when some links do not fit. */
const MORE_WIDTH = 96;

/**
 * The header menu on one line: the links that fit are shown, the rest go
 * under "More". Measured again whenever the bar changes width.
 */
export function CircuitMenuLinks({ menu }: { menu?: StoreMenu }) {
    const box = useRef<HTMLDivElement>(null);
    const more = useRef<HTMLDivElement>(null);
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
        const tree = el.querySelector<HTMLElement>('.store-menu-tree');
        const gap = tree
            ? parseFloat(getComputedStyle(tree).columnGap) || 0
            : 0;
        const widths = items.map((item) => item.getBoundingClientRect().width);
        const total =
            widths.reduce((sum, w) => sum + w, 0) + gap * (widths.length - 1);
        let count = items.length;

        if (total > el.clientWidth) {
            const room = el.clientWidth - MORE_WIDTH;
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
    }, []);

    useLayoutEffect(measure, [measure, links.length]);

    useEffect(() => {
        const el = box.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(() => measure());
        observer.observe(el);
        // Web fonts change the widths once they load.
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
        <div className="ci-menu-links" ref={box}>
            <StoreMenuTree menu={menu} />
            {visible !== null && (
                <div
                    className="ci-menu-more"
                    ref={more}
                    onKeyDown={(event) =>
                        event.key === 'Escape' && setOpen(false)
                    }
                >
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls="ci-menu-more-list"
                        onClick={() => setOpen(!open)}
                    >
                        More <ChevronDown size={14} aria-hidden />
                    </button>
                    <div
                        id="ci-menu-more-list"
                        className="ci-dropdown ci-menu-more-list"
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
