import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

const escape = (text: string) =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

/** Plain text (older descriptions, AI drafts) becomes paragraphs. */
export function toEditorHtml(value: string | null | undefined): string {
    const text = (value ?? '').replace(/\r\n/g, '\n').trim();

    if (text === '' || /<\/?[a-z][a-z0-9]*(\s[^>]*)?\/?>/i.test(text)) {
        return text;
    }

    return text
        .split(/\n{2,}/)
        .map((p) => `<p>${escape(p.trim()).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

const emptyAsBlank = (editor: Editor) =>
    editor.isEmpty ? '' : editor.getHTML();

function normaliseUrl(url: string): string {
    const value = url.trim();

    if (value === '' || /^(https?:|mailto:|tel:|\/|#)/i.test(value)) {
        return value;
    }

    return value.includes('@') && !value.includes('/')
        ? `mailto:${value}`
        : `https://${value}`;
}

function Tool({
    label,
    pressed,
    onClick,
    disabled,
    children,
}: {
    label: string;
    pressed?: boolean;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Toggle
            size="sm"
            aria-label={label}
            title={label}
            pressed={pressed ?? false}
            onPressedChange={onClick}
            onMouseDown={(e) => e.preventDefault()}
            disabled={disabled}
        >
            {children}
        </Toggle>
    );
}

export function RichTextEditor({
    id,
    label = 'Description',
    value,
    onChange,
    invalid,
    placeholder,
}: {
    id: string;
    label?: string;
    value: string;
    onChange: (html: string) => void;
    invalid?: boolean;
    placeholder?: string;
}) {
    const emitted = useRef(value);
    const [linkOpen, setLinkOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState('');

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                    HTMLAttributes: {
                        rel: 'noopener noreferrer nofollow',
                        target: null,
                    },
                },
            }),
        ],
        content: toEditorHtml(value),
        editorProps: {
            attributes: {
                id,
                role: 'textbox',
                'aria-label': label,
                'aria-multiline': 'true',
                'aria-invalid': invalid ? 'true' : 'false',
                'data-placeholder': placeholder ?? '',
                class: 'rich-text min-h-48 px-3 py-2 text-sm outline-none',
            },
        },
        onUpdate: ({ editor }) => {
            const html = emptyAsBlank(editor);
            emitted.current = html;
            onChange(html);
        },
    });

    // Values set from outside the editor (AI drafts, form resets).
    useEffect(() => {
        if (!editor || value === emitted.current) {
            return;
        }
        emitted.current = value;
        editor.commands.setContent(toEditorHtml(value), { emitUpdate: false });
    }, [editor, value]);

    const state = useEditorState({
        editor,
        selector: ({ editor }) =>
            editor
                ? {
                      bold: editor.isActive('bold'),
                      italic: editor.isActive('italic'),
                      underline: editor.isActive('underline'),
                      strike: editor.isActive('strike'),
                      h2: editor.isActive('heading', { level: 2 }),
                      h3: editor.isActive('heading', { level: 3 }),
                      bullet: editor.isActive('bulletList'),
                      ordered: editor.isActive('orderedList'),
                      quote: editor.isActive('blockquote'),
                      link: editor.isActive('link'),
                      canUndo: editor.can().undo(),
                      canRedo: editor.can().redo(),
                      empty: editor.isEmpty,
                  }
                : null,
    });

    const openLink = () => {
        if (!editor) return;
        setLinkUrl(editor.getAttributes('link').href ?? '');
        setLinkOpen(true);
    };

    const applyLink = () => {
        if (!editor) return;
        const href = normaliseUrl(linkUrl);
        const chain = editor.chain().focus().extendMarkRange('link');
        if (href === '') {
            chain.unsetLink().run();
        } else {
            chain.setLink({ href }).run();
        }
        setLinkOpen(false);
    };

    const chain = () => editor!.chain().focus();

    return (
        <div
            className={cn(
                'rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30',
                invalid && 'border-destructive',
            )}
        >
            <div
                role="toolbar"
                aria-label="Formatting"
                aria-controls={id}
                className="flex flex-wrap items-center gap-0.5 border-b p-1"
            >
                <Tool
                    label="Bold"
                    pressed={state?.bold}
                    onClick={() => chain().toggleBold().run()}
                >
                    <Bold />
                </Tool>
                <Tool
                    label="Italic"
                    pressed={state?.italic}
                    onClick={() => chain().toggleItalic().run()}
                >
                    <Italic />
                </Tool>
                <Tool
                    label="Underline"
                    pressed={state?.underline}
                    onClick={() => chain().toggleUnderline().run()}
                >
                    <Underline />
                </Tool>
                <Tool
                    label="Strikethrough"
                    pressed={state?.strike}
                    onClick={() => chain().toggleStrike().run()}
                >
                    <Strikethrough />
                </Tool>
                <Separator orientation="vertical" className="mx-1 h-5!" />
                <Tool
                    label="Heading"
                    pressed={state?.h2}
                    onClick={() => chain().toggleHeading({ level: 2 }).run()}
                >
                    <Heading2 />
                </Tool>
                <Tool
                    label="Subheading"
                    pressed={state?.h3}
                    onClick={() => chain().toggleHeading({ level: 3 }).run()}
                >
                    <Heading3 />
                </Tool>
                <Tool
                    label="Bulleted list"
                    pressed={state?.bullet}
                    onClick={() => chain().toggleBulletList().run()}
                >
                    <List />
                </Tool>
                <Tool
                    label="Numbered list"
                    pressed={state?.ordered}
                    onClick={() => chain().toggleOrderedList().run()}
                >
                    <ListOrdered />
                </Tool>
                <Tool
                    label="Quote"
                    pressed={state?.quote}
                    onClick={() => chain().toggleBlockquote().run()}
                >
                    <Quote />
                </Tool>
                <Tool
                    label="Divider"
                    onClick={() => chain().setHorizontalRule().run()}
                >
                    <Minus />
                </Tool>
                <Separator orientation="vertical" className="mx-1 h-5!" />
                <Tool label="Link" pressed={state?.link} onClick={openLink}>
                    <Link2 />
                </Tool>
                <Tool
                    label="Clear formatting"
                    onClick={() => chain().unsetAllMarks().clearNodes().run()}
                >
                    <RemoveFormatting />
                </Tool>
                <div className="ml-auto flex gap-0.5">
                    <Tool
                        label="Undo"
                        disabled={!state?.canUndo}
                        onClick={() => chain().undo().run()}
                    >
                        <Undo2 />
                    </Tool>
                    <Tool
                        label="Redo"
                        disabled={!state?.canRedo}
                        onClick={() => chain().redo().run()}
                    >
                        <Redo2 />
                    </Tool>
                </div>
            </div>
            {linkOpen && (
                <div className="flex flex-wrap items-center gap-2 border-b p-2">
                    <Input
                        autoFocus
                        aria-label="Link address"
                        placeholder="https://example.com or /p/product"
                        value={linkUrl}
                        onChange={(e) => setLinkUrl(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                applyLink();
                            }
                            if (e.key === 'Escape') {
                                e.preventDefault();
                                setLinkOpen(false);
                            }
                        }}
                        className="h-8 min-w-0 flex-1"
                    />
                    <Button type="button" size="sm" onClick={applyLink}>
                        Apply
                    </Button>
                    {state?.link && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                chain()
                                    .extendMarkRange('link')
                                    .unsetLink()
                                    .run();
                                setLinkOpen(false);
                            }}
                        >
                            Remove link
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setLinkOpen(false)}
                    >
                        Cancel
                    </Button>
                </div>
            )}
            <div
                className={cn(
                    'relative',
                    state?.empty &&
                        placeholder &&
                        'before:pointer-events-none before:absolute before:top-2 before:left-3 before:text-sm before:text-muted-foreground before:content-[attr(data-placeholder)]',
                )}
                data-placeholder={placeholder}
            >
                <EditorContent editor={editor} />
            </div>
        </div>
    );
}
