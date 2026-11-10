import { useState } from 'react';
import type { CSSProperties } from 'react';

export function PetCompare({
    before,
    after,
    heading,
}: {
    before: string;
    after: string;
    heading: string;
}) {
    const [position, setPosition] = useState(50);
    return (
        <section className="pawzy-compare-section">
            <header className="pawzy-section-heading">
                <p>The style edit</p>
                <h2>{heading}</h2>
            </header>
            <div
                className="pawzy-compare"
                style={
                    { '--compare-position': `${position}%` } as CSSProperties
                }
            >
                <img
                    src={after}
                    alt="Pet fashion, second look"
                    loading="lazy"
                />
                <div className="pawzy-compare-before">
                    <img
                        src={before}
                        alt="Pet fashion, first look"
                        loading="lazy"
                    />
                </div>
                <span className="pawzy-compare-divider" />
                <span className="pawzy-compare-label">Look one</span>
                <span className="pawzy-compare-label pawzy-compare-end">
                    Look two
                </span>
            </div>
            <label className="pawzy-compare-control">
                Compare the looks
                <input
                    type="range"
                    min="0"
                    max="100"
                    value={position}
                    onChange={(e) => setPosition(Number(e.target.value))}
                    aria-label="Compare pet fashion images"
                />
            </label>
        </section>
    );
}
