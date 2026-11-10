import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Journal({ section }: ThemeSectionProps) {
    return (
        <section className="lv-journal">
            <div className="lv-frame">
                <h2>{str(section.settings.heading)}</h2>
                <div>
                    {section.blocks.map((b) => (
                        <article key={b.id} {...blockAttributes(b)}>
                            <Link href={str(b.settings.link) || '/search'}>
                                <img
                                    src={str(b.settings.image)}
                                    alt=""
                                    loading="lazy"
                                />
                                <h3>{str(b.settings.heading)}</h3>
                            </Link>
                            <p>{str(b.settings.body)}</p>
                            <Link
                                href={str(b.settings.link) || '/search'}
                                className="lv-text-link"
                            >
                                {str(b.settings.button)}
                            </Link>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
