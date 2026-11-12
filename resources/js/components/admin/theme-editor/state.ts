import { useCallback, useMemo, useReducer } from 'react';

export type SettingDef = {
    type: string;
    id?: string;
    label?: string;
    content?: string;
    info?: string;
    default?: unknown;
    min?: number;
    max?: number;
    step?: number;
    unit?: string;
    placeholder?: string;
    options?: { value: string; label: string }[];
};

export type BlockSchema = {
    type: string;
    name: string;
    settings?: SettingDef[];
    limit?: number;
};

export type SectionSchema = {
    name: string;
    settings?: SettingDef[];
    blocks?: BlockSchema[];
    max_blocks?: number;
    limit?: number;
    presets?: {
        name: string;
        settings?: Record<string, unknown>;
        blocks?: { type: string; settings?: Record<string, unknown> }[];
    }[];
    enabled_on?: { templates?: string[]; groups?: string[] };
};

export type BlockData = {
    type: string;
    settings: Record<string, unknown>;
    disabled?: boolean;
};

export type SectionData = {
    type: string;
    settings: Record<string, unknown>;
    blocks: Record<string, BlockData>;
    block_order: string[];
    disabled?: boolean;
};

export type TemplateData = {
    sections: Record<string, SectionData>;
    order: string[];
};

/** "template:index", "group:header", … */
export type TemplateKey = string;

export type Selection =
    | { kind: 'section'; key: TemplateKey; section: string }
    | { kind: 'block'; key: TemplateKey; section: string; block: string }
    | null;

type Snapshot = {
    templates: Record<TemplateKey, TemplateData>;
    design: Record<string, unknown>;
};

type State = {
    past: Snapshot[];
    present: Snapshot;
    future: Snapshot[];
    /** What Save last stored; dirty = present differs from it. */
    saved: Snapshot;
};

type Action =
    | { type: 'change'; apply: (s: Snapshot) => Snapshot; merge?: string }
    | { type: 'undo' }
    | { type: 'redo' }
    | { type: 'saved'; snapshot: Snapshot };

const HISTORY = 100;
let lastMerge: { key: string; at: number } | null = null;

function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'change': {
            const next = action.apply(state.present);

            if (next === state.present) {
                return state;
            }

            // Typing into one field is one undo step, not one per keystroke.
            const now = Date.now();
            const merging =
                action.merge !== undefined &&
                lastMerge?.key === action.merge &&
                now - lastMerge.at < 1000;
            lastMerge = action.merge ? { key: action.merge, at: now } : null;

            return {
                ...state,
                past: merging
                    ? state.past
                    : [...state.past, state.present].slice(-HISTORY),
                present: next,
                future: [],
            };
        }
        case 'undo': {
            const previous = state.past.at(-1);
            lastMerge = null;

            return previous
                ? {
                      ...state,
                      past: state.past.slice(0, -1),
                      present: previous,
                      future: [state.present, ...state.future],
                  }
                : state;
        }
        case 'redo': {
            const [next, ...rest] = state.future;
            lastMerge = null;

            return next
                ? {
                      ...state,
                      past: [...state.past, state.present],
                      present: next,
                      future: rest,
                  }
                : state;
        }
        case 'saved':
            return {
                ...state,
                saved: action.snapshot,
                present: action.snapshot,
            };
    }
}

export function newId(type: string): string {
    const base = type.replace(/[^a-z0-9-]/g, '').slice(0, 30) || 'item';

    return `${base}_${Math.random().toString(36).slice(2, 8)}`;
}

export function defaultsFor(
    settings: SettingDef[] = [],
): Record<string, unknown> {
    const values: Record<string, unknown> = {};

    for (const setting of settings) {
        if (!setting.id || setting.type === 'header') {
            continue;
        }

        values[setting.id] =
            setting.default ??
            (setting.type === 'checkbox'
                ? false
                : setting.type === 'number' || setting.type === 'range'
                  ? (setting.min ?? 0)
                  : setting.type === 'select' || setting.type === 'radio'
                    ? (setting.options?.[0]?.value ?? '')
                    : '');
    }

    return values;
}

function updateTemplate(
    snapshot: Snapshot,
    key: TemplateKey,
    update: (t: TemplateData) => TemplateData,
): Snapshot {
    const current = snapshot.templates[key] ?? { sections: {}, order: [] };

    return {
        ...snapshot,
        templates: { ...snapshot.templates, [key]: update(current) },
    };
}

function updateSection(
    snapshot: Snapshot,
    key: TemplateKey,
    id: string,
    update: (s: SectionData) => SectionData,
): Snapshot {
    return updateTemplate(snapshot, key, (t) =>
        t.sections[id]
            ? {
                  ...t,
                  sections: { ...t.sections, [id]: update(t.sections[id]) },
              }
            : t,
    );
}

function move<T>(list: T[], from: number, to: number): T[] {
    const next = [...list];
    const [item] = next.splice(from, 1);
    next.splice(Math.max(0, Math.min(next.length, to)), 0, item);

    return next;
}

export function useEditorState(
    templates: Record<TemplateKey, TemplateData>,
    design: Record<string, unknown>,
    schemas: Record<string, SectionSchema>,
) {
    const initial = useMemo<Snapshot>(() => ({ templates, design }), []); // eslint-disable-line react-hooks/exhaustive-deps
    const [state, dispatch] = useReducer(reducer, {
        past: [],
        present: initial,
        future: [],
        saved: initial,
    });
    const change = useCallback(
        (apply: (s: Snapshot) => Snapshot, merge?: string) =>
            dispatch({ type: 'change', apply, merge }),
        [],
    );

    const actions = useMemo(
        () => ({
            setSectionSetting(
                key: TemplateKey,
                section: string,
                id: string,
                value: unknown,
            ) {
                change(
                    (s) =>
                        updateSection(s, key, section, (data) => ({
                            ...data,
                            settings: { ...data.settings, [id]: value },
                        })),
                    `${key}/${section}/${id}`,
                );
            },
            setBlockSetting(
                key: TemplateKey,
                section: string,
                block: string,
                id: string,
                value: unknown,
            ) {
                change(
                    (s) =>
                        updateSection(s, key, section, (data) =>
                            data.blocks[block]
                                ? {
                                      ...data,
                                      blocks: {
                                          ...data.blocks,
                                          [block]: {
                                              ...data.blocks[block],
                                              settings: {
                                                  ...data.blocks[block]
                                                      .settings,
                                                  [id]: value,
                                              },
                                          },
                                      },
                                  }
                                : data,
                        ),
                    `${key}/${section}/${block}/${id}`,
                );
            },
            setDesign(id: string, value: unknown) {
                change(
                    (s) => ({ ...s, design: { ...s.design, [id]: value } }),
                    `design/${id}`,
                );
            },
            /** Adds a section from a preset after `index` (end when omitted); returns its id. */
            addSection(
                key: TemplateKey,
                type: string,
                presetIndex = 0,
                index?: number,
            ): string {
                const schema = schemas[type];
                const preset = schema?.presets?.[presetIndex];
                const id = newId(type);
                const blocks: Record<string, BlockData> = {};
                const blockOrder: string[] = [];

                for (const block of preset?.blocks ?? []) {
                    const blockSchema = schema?.blocks?.find(
                        (b) => b.type === block.type,
                    );
                    const blockId = newId(block.type);
                    blocks[blockId] = {
                        type: block.type,
                        settings: {
                            ...defaultsFor(blockSchema?.settings),
                            ...block.settings,
                        },
                    };
                    blockOrder.push(blockId);
                }

                change((s) =>
                    updateTemplate(s, key, (t) => ({
                        sections: {
                            ...t.sections,
                            [id]: {
                                type,
                                settings: {
                                    ...defaultsFor(schema?.settings),
                                    ...preset?.settings,
                                },
                                blocks,
                                block_order: blockOrder,
                            },
                        },
                        order:
                            index === undefined
                                ? [...t.order, id]
                                : [
                                      ...t.order.slice(0, index + 1),
                                      id,
                                      ...t.order.slice(index + 1),
                                  ],
                    })),
                );

                return id;
            },
            removeSection(key: TemplateKey, section: string) {
                change((s) =>
                    updateTemplate(s, key, (t) => {
                        const sections = { ...t.sections };
                        delete sections[section];

                        return {
                            sections,
                            order: t.order.filter((id) => id !== section),
                        };
                    }),
                );
            },
            moveSection(key: TemplateKey, from: number, to: number) {
                change((s) =>
                    updateTemplate(s, key, (t) => ({
                        ...t,
                        order: move(t.order, from, to),
                    })),
                );
            },
            toggleSection(key: TemplateKey, section: string) {
                change((s) =>
                    updateSection(s, key, section, (data) => ({
                        ...data,
                        disabled: !data.disabled,
                    })),
                );
            },
            addBlock(key: TemplateKey, section: string, type: string): string {
                const sectionType =
                    state.present.templates[key]?.sections[section]?.type ?? '';
                const blockSchema = schemas[sectionType]?.blocks?.find(
                    (b) => b.type === type,
                );
                const id = newId(type);
                change((s) =>
                    updateSection(s, key, section, (data) => ({
                        ...data,
                        blocks: {
                            ...data.blocks,
                            [id]: {
                                type,
                                settings: defaultsFor(blockSchema?.settings),
                            },
                        },
                        block_order: [...data.block_order, id],
                    })),
                );

                return id;
            },
            removeBlock(key: TemplateKey, section: string, block: string) {
                change((s) =>
                    updateSection(s, key, section, (data) => {
                        const blocks = { ...data.blocks };
                        delete blocks[block];

                        return {
                            ...data,
                            blocks,
                            block_order: data.block_order.filter(
                                (id) => id !== block,
                            ),
                        };
                    }),
                );
            },
            moveBlock(
                key: TemplateKey,
                section: string,
                from: number,
                to: number,
            ) {
                change((s) =>
                    updateSection(s, key, section, (data) => ({
                        ...data,
                        block_order: move(data.block_order, from, to),
                    })),
                );
            },
            toggleBlock(key: TemplateKey, section: string, block: string) {
                change((s) =>
                    updateSection(s, key, section, (data) =>
                        data.blocks[block]
                            ? {
                                  ...data,
                                  blocks: {
                                      ...data.blocks,
                                      [block]: {
                                          ...data.blocks[block],
                                          disabled:
                                              !data.blocks[block].disabled,
                                      },
                                  },
                              }
                            : data,
                    ),
                );
            },
            undo: () => dispatch({ type: 'undo' }),
            redo: () => dispatch({ type: 'redo' }),
            markSaved: (snapshot: Snapshot) =>
                dispatch({ type: 'saved', snapshot }),
        }),
        [change, schemas, state.present.templates],
    );

    const dirtyTemplates = Object.keys(state.present.templates).filter(
        (key) => state.present.templates[key] !== state.saved.templates[key],
    );
    const dirtyDesign = state.present.design !== state.saved.design;

    return {
        templates: state.present.templates,
        design: state.present.design,
        canUndo: state.past.length > 0,
        canRedo: state.future.length > 0,
        dirtyTemplates,
        dirtyDesign,
        dirty: dirtyTemplates.length > 0 || dirtyDesign,
        actions,
    };
}

export type EditorActions = ReturnType<typeof useEditorState>['actions'];
