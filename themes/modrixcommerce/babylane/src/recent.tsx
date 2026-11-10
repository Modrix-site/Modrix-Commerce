import { ChevronDown } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { useDeviceList } from './components';
export function RecentMenu({ label }: { label: string }) {
    const items = useDeviceList('recent');
    return (
        <details className="bl-recent-menu">
            <summary>
                {label || 'Your recently viewed'}
                <ChevronDown size={13} />
            </summary>
            <div>
                {items.length ? (
                    items.map((p) => (
                        <Link
                            key={p.id}
                            href={'/p/' + p.slug}
                            onClick={(e) =>
                                e.currentTarget
                                    .closest('details')
                                    ?.removeAttribute('open')
                            }
                        >
                            {p.image && <img src={p.image} alt="" />}
                            <span>
                                {p.name}
                                <small>{p.price}</small>
                            </span>
                        </Link>
                    ))
                ) : (
                    <p>Products you view will appear here.</p>
                )}
            </div>
        </details>
    );
}
