import { useId, useState } from 'react';
import { Plus, Minus } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { CategoryNode } from '@/components/storefront/site-header';
export function BeautyCategoryTree({
    nodes,
    active,
}: {
    nodes: CategoryNode[];
    active?: string;
}) {
    return (
        <ul className="lv-filter-tree">
            {nodes.map((n) => (
                <Branch key={n.id + ':' + active} node={n} active={active} />
            ))}
        </ul>
    );
}
function contains(n: CategoryNode, active?: string): boolean {
    return n.slug === active || n.children.some((c) => contains(c, active));
}
function Branch({ node, active }: { node: CategoryNode; active?: string }) {
    const [open, setOpen] = useState(contains(node, active));
    const id = useId();
    return (
        <li>
            <div>
                <Link
                    href={node.url}
                    aria-current={node.slug === active ? 'page' : undefined}
                >
                    {node.name}
                </Link>
                {node.children.length > 0 && (
                    <button
                        type="button"
                        aria-label={
                            (open ? 'Collapse ' : 'Expand ') + node.name
                        }
                        aria-expanded={open}
                        aria-controls={id}
                        onClick={() => setOpen(!open)}
                    >
                        {open ? <Minus size={13} /> : <Plus size={13} />}
                    </button>
                )}
            </div>
            {node.children.length > 0 && (
                <ul id={id} hidden={!open}>
                    {node.children.map((c) => (
                        <Branch key={c.id} node={c} active={active} />
                    ))}
                </ul>
            )}
        </li>
    );
}
