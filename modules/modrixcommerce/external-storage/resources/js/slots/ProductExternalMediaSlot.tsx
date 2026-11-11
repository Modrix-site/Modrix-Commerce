import { Film, PlayCircle } from 'lucide-react';
import { useState } from 'react';

type VideoItem = {
    id: number;
    title: string;
    url: string;
    mime_type: string;
    provider_label: string;
};

type Props = {
    productName: string;
    videos: VideoItem[];
};

export default function ProductExternalMediaSlot({ productName, videos }: Props) {
    const [activeIndex, setActiveIndex] = useState(0);

    if (!videos || videos.length === 0) {
        return null;
    }

    const activeVideo = videos[activeIndex] ?? videos[0];

    return (
        <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div className="flex items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-2.5">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <Film className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="text-base font-semibold text-slate-900">
                            Product Videos ({videos.length})
                        </h3>
                        <p className="text-xs text-slate-500">
                            Watch {productName} in action
                        </p>
                    </div>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-slate-950 aspect-video">
                <video
                    key={activeVideo.url}
                    src={activeVideo.url}
                    controls
                    preload="metadata"
                    className="h-full w-full object-contain"
                >
                    <source src={activeVideo.url} type={activeVideo.mime_type} />
                </video>
            </div>

            <div className="mt-2.5 flex items-center justify-between text-xs text-slate-600">
                <span className="font-medium text-slate-800">{activeVideo.title}</span>
                <span className="text-[11px] text-slate-400">
                    Streamed via {activeVideo.provider_label}
                </span>
            </div>

            {videos.length > 1 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {videos.map((v, idx) => (
                        <button
                            key={v.id}
                            type="button"
                            onClick={() => setActiveIndex(idx)}
                            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                                idx === activeIndex
                                    ? 'border-sky-600 bg-sky-50 text-sky-800'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            }`}
                        >
                            <PlayCircle className="h-3.5 w-3.5" />
                            {v.title}
                        </button>
                    ))}
                </div>
            )}
        </section>
    );
}
