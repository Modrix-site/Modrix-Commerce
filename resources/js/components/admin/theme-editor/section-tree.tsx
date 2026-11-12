import {
    ChevronDown,
    ChevronRight,
    Eye,
    EyeOff,
    GripVertical,
    LayoutTemplate,
    PanelTop,
    PlusCircle,
    Square,
} from 'lucide-react';
import { useState } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';
import type {
    EditorActions,
    SectionData,
    SectionSchema,
    Selection,
    TemplateData,
    TemplateKey,
} from './state';

/** A readable title for a section or block: its schema name plus its first text setting. */
export function itemTitle(
    name: string,
    settings: Record<string, unknown>,
): { name: string; detail: string | null } {
    const detail = ['heading', 'title', 'text', 'label', 'name']
        .map((k) => settings[k])
        .find((v) => typeof v === 'string' && v.trim() !== '');

    return { name, detail: typeof detail === 'string' ? detail : null };
}

export function SectionTree({
    groups,
    templates,
    schemas,
    selection,
    onSelect,
    onAddSection,
    actions,
}: {
    groups: { key: TemplateKey; title: string; kind: 'group' | 'template' }[];
    templates: Record<TemplateKey, TemplateData>;
    schemas: Record<string, SectionSchema>;
    selection: Selection;
    onSelect: (selection: Selection) => void;
    onAddSection: (key: TemplateKey) => void;
    actions: EditorActions;
}) {
    return (
        <div className="grid content-start">
            {groups.map((group) => {
                const template = templates[group.key] ?? {
                    sections: {},
                    order: [],
                };

                return (
                    <section
                        key={group.key}
                        className="border-b px-2 py-3 last:border-b-0"
                    >
                        <h2 className="px-2 pb-2 text-sm font-semibold">
                            {group.title}
                        </h2>
                        <SortableList
                            items={template.order}
                            onMove={(from, to) =>
                                actions.moveSection(group.key, from, to)
                            }
                            render={(id, handle) => {
                                const section = template.sections[id];

                                return section ? (
                                    <SectionRow
                                        key={id}
                                        templateKey={group.key}
                                        id={id}
                                        section={section}
                                        schema={schemas[section.type]}
                                        selection={selection}
                                        onSelect={onSelect}
                                        actions={actions}
                                        handle={handle}
                                        icon={
                                            group.kind === 'group'
                                                ? PanelTop
                                                : LayoutTemplate
                                        }
                                    />
                                ) : null;
                            }}
                        />
                        <AddLink onClick={() => onAddSection(group.key)}>
                            Add section
                        </AddLink>
                    </section>
                );
            })}
        </div>
    );
}

function SectionRow({
    templateKey,
    id,
    section,
    schema,
    selection,
    onSelect,
    actions,
    handle,
    icon: Icon,
}: {
    templateKey: TemplateKey;
    id: string;
    section: SectionData;
    schema: SectionSchema | undefined;
    selection: Selection;
    onSelect: (selection: Selection) => void;
    actions: EditorActions;
    handle: HandleProps;
    icon: typeof PanelTop;
}) {
    const [open, setOpen] = useState(true);
    const blockSchemas = schema?.blocks ?? [];
    const canAddBlocks =
        blockSchemas.length > 0 &&
        section.block_order.length < (schema?.max_blocks ?? 50);
    const selected =
        selection?.kind === 'section' &&
        selection.key === templateKey &&
        selection.section === id;
    const { name } = itemTitle(schema?.name ?? section.type, section.settings);

    return (
        <div>
            <Row
                selected={selected}
                disabled={section.disabled}
                depth={0}
                handle={handle}
                onClick={() =>
                    onSelect({ kind: 'section', key: templateKey, section: id })
                }
                onToggle={() => actions.toggleSection(templateKey, id)}
                leading={
                    blockSchemas.length > 0 ? (
                        <button
                            type="button"
                            aria-label={open ? 'Collapse' : 'Expand'}
                            onClick={(e) => {
                                e.stopPropagation();
                                setOpen((v) => !v);
                            }}
                            className="rounded p-0.5 hover:bg-muted"
                        >
                            {open ? (
                                <ChevronDown className="size-4" />
                            ) : (
                                <ChevronRight className="size-4" />
                            )}
                        </button>
                    ) : (
                        <span className="w-5" />
                    )
                }
                icon={<Icon className="size-4 text-muted-foreground" />}
                label={name}
            />
            {open && blockSchemas.length > 0 && (
                <div className="grid">
                    {canAddBlocks && (
                        <div className="pl-10">
                            <AddBlockMenu
                                blocks={blockSchemas}
                                onAdd={(type) => {
                                    const block = actions.addBlock(
                                        templateKey,
                                        id,
                                        type,
                                    );
                                    onSelect({
                                        kind: 'block',
                                        key: templateKey,
                                        section: id,
                                        block,
                                    });
                                }}
                            />
                        </div>
                    )}
                    <SortableList
                        items={section.block_order}
                        onMove={(from, to) =>
                            actions.moveBlock(templateKey, id, from, to)
                        }
                        render={(blockId, blockHandle) => {
                            const block = section.blocks[blockId];
                            const blockSchema = blockSchemas.find(
                                (b) => b.type === block?.type,
                            );

                            if (!block) {
                                return null;
                            }

                            const title = itemTitle(
                                blockSchema?.name ?? block.type,
                                block.settings,
                            );

                            return (
                                <Row
                                    key={blockId}
                                    depth={1}
                                    handle={blockHandle}
                                    disabled={block.disabled}
                                    selected={
                                        selection?.kind === 'block' &&
                                        selection.key === templateKey &&
                                        selection.section === id &&
                                        selection.block === blockId
                                    }
                                    onClick={() =>
                                        onSelect({
                                            kind: 'block',
                                            key: templateKey,
                                            section: id,
                                            block: blockId,
                                        })
                                    }
                                    onToggle={() =>
                                        actions.toggleBlock(
                                            templateKey,
                                            id,
                                            blockId,
                                        )
                                    }
                                    leading={<span className="w-5" />}
                                    icon={
                                        <Square className="size-4 text-muted-foreground" />
                                    }
                                    label={
                                        <>
                                            {title.name}
                                            {title.detail && (
                                                <span className="text-muted-foreground italic">
                                                    {' '}
                                                    – {title.detail}
                                                </span>
                                            )}
                                        </>
                                    }
                                />
                            );
                        }}
                    />
                </div>
            )}
        </div>
    );
}

function Row({
    selected,
    disabled,
    depth,
    handle,
    onClick,
    onToggle,
    leading,
    icon,
    label,
}: {
    selected: boolean;
    disabled?: boolean;
    depth: number;
    handle: HandleProps;
    onClick: () => void;
    onToggle: () => void;
    leading: React.ReactNode;
    icon: React.ReactNode;
    label: React.ReactNode;
}) {
    return (
        <div
            role="button"
            tabIndex={0}
            onClick={onClick}
            onKeyDown={(e) =>
                (e.key === 'Enter' || e.key === ' ') &&
                (e.preventDefault(), onClick())
            }
            className={cn(
                'group flex min-h-9 items-center gap-1.5 rounded-md pr-1 text-sm hover:bg-muted',
                selected && 'bg-muted font-medium',
                disabled && 'text-muted-foreground',
            )}
            style={{ paddingLeft: `${0.5 + depth * 1.75}rem` }}
        >
            {leading}
            {icon}
            <span
                className={cn(
                    'min-w-0 flex-1 truncate',
                    disabled && 'line-through decoration-muted-foreground/40',
                )}
            >
                {label}
            </span>
            <button
                type="button"
                aria-label={disabled ? 'Show' : 'Hide'}
                onClick={(e) => {
                    e.stopPropagation();
                    onToggle();
                }}
                className={cn(
                    'rounded p-1 hover:bg-background',
                    disabled
                        ? 'opacity-100'
                        : 'opacity-0 group-hover:opacity-100 focus:opacity-100',
                )}
            >
                {disabled ? (
                    <EyeOff className="size-4" />
                ) : (
                    <Eye className="size-4" />
                )}
            </button>
            <span
                {...handle}
                aria-label="Drag to reorder"
                className="cursor-grab rounded p-1 opacity-0 group-hover:opacity-100 hover:bg-background"
                onClick={(e) => e.stopPropagation()}
            >
                <GripVertical className="size-4" />
            </span>
        </div>
    );
}

type HandleProps = {
    draggable: true;
    onDragStart: (e: DragEvent) => void;
    onDragEnd: () => void;
};

/** Minimal drag-to-reorder within one list. */
function SortableList({
    items,
    onMove,
    render,
}: {
    items: string[];
    onMove: (from: number, to: number) => void;
    render: (id: string, handle: HandleProps) => React.ReactNode;
}) {
    const [dragging, setDragging] = useState<number | null>(null);
    const [over, setOver] = useState<number | null>(null);

    return (
        <div className="grid">
            {items.map((id, index) => (
                <div
                    key={id}
                    onDragOver={(e) => {
                        if (dragging === null) {
                            return;
                        }
                        e.preventDefault();
                        setOver(index);
                    }}
                    onDrop={(e) => {
                        e.preventDefault();
                        if (dragging !== null && dragging !== index) {
                            onMove(dragging, index);
                        }
                        setDragging(null);
                        setOver(null);
                    }}
                    className={cn(
                        over === index &&
                            dragging !== null &&
                            dragging !== index &&
                            'rounded-md ring-2 ring-primary/40',
                    )}
                >
                    {render(id, {
                        draggable: true,
                        onDragStart: (e) => {
                            e.stopPropagation();
                            e.dataTransfer.effectAllowed = 'move';
                            setDragging(index);
                        },
                        onDragEnd: () => {
                            setDragging(null);
                            setOver(null);
                        },
                    })}
                </div>
            ))}
        </div>
    );
}

function AddLink({
    onClick,
    children,
}: {
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="mt-1 flex min-h-9 w-full items-center gap-2 rounded-md px-2 text-sm text-[#2c6ecb] hover:bg-muted"
        >
            <PlusCircle className="size-4" />
            {children}
        </button>
    );
}

function AddBlockMenu({
    blocks,
    onAdd,
}: {
    blocks: { type: string; name: string }[];
    onAdd: (type: string) => void;
}) {
    const [open, setOpen] = useState(false);

    if (blocks.length === 1) {
        return (
            <AddLink onClick={() => onAdd(blocks[0].type)}>
                Add {blocks[0].name}
            </AddLink>
        );
    }

    return (
        <div className="relative">
            <AddLink onClick={() => setOpen((v) => !v)}>Add block</AddLink>
            {open && (
                <div className="absolute left-0 z-20 mt-1 grid w-56 rounded-md border bg-popover p-1 shadow-md">
                    {blocks.map((block) => (
                        <button
                            key={block.type}
                            type="button"
                            className="rounded px-2 py-1.5 text-left text-sm hover:bg-muted"
                            onClick={() => {
                                onAdd(block.type);
                                setOpen(false);
                            }}
                        >
                            {block.name}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
