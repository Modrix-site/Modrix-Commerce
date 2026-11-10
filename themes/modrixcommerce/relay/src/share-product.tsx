import { useState } from 'react';
import { Copy, Check, Share2 } from 'lucide-react';
export default function ProductShare({ name }: { name: string }) {
    const [copied, setCopied] = useState(false);
    const [message, setMessage] = useState('');
    return (
        <div className="re-product-share">
            <span>Share:</span>
            <button
                type="button"
                aria-label="Copy product link"
                onClick={async () => {
                    try {
                        await navigator.clipboard.writeText(
                            window.location.href,
                        );
                        setCopied(true);
                        setTimeout(() => setCopied(false), 2000);
                    } catch {
                        setMessage(
                            'Copy the address from your browser to share this product.',
                        );
                    }
                }}
            >
                {copied ? <Check size={16} /> : <Copy size={16} />}
            </button>
            <button
                type="button"
                aria-label="Share product"
                onClick={async () => {
                    try {
                        if (navigator.share)
                            await navigator.share({
                                title: name,
                                url: window.location.href,
                            });
                        else {
                            await navigator.clipboard.writeText(
                                window.location.href,
                            );
                            setCopied(true);
                            setTimeout(() => setCopied(false), 2000);
                        }
                    } catch (e) {
                        if (
                            !(
                                e instanceof DOMException &&
                                e.name === 'AbortError'
                            )
                        )
                            setMessage(
                                'Copy the address from your browser to share this product.',
                            );
                    }
                }}
            >
                <Share2 size={16} />
            </button>
            <span role="status">{copied ? 'Link copied' : message}</span>
        </div>
    );
}
