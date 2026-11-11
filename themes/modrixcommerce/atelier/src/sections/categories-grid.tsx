import type { CSSProperties } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { blockAttributes } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function CategoriesGrid({ section }: ThemeSectionProps) {
    const s = section.settings;
    const { storefront } = usePage().props;
    const mode = str(s.mode, 'store');
    const cols = Number(s.columns) || 4;
    const height = Number(s.image_height) || 280;

    const useStore =
        mode === 'store' &&
        Array.isArray(storefront?.categories) &&
        storefront.categories.length > 0;

    return (
        <section className="at-section">
            <div className="at-frame">
                {str(s.heading) && (
                    <div className="at-section-head">
                        <h2>{str(s.heading, 'Discover our categories')}</h2>
                    </div>
                )}

                <div
                    className="at-categories-grid"
                    style={{ '--at-cat-cols': cols } as CSSProperties}
                >
                    {useStore
                        ? storefront.categories.slice(0, 8).map((cat) => (
                              <Link
                                  key={cat.id}
                                  href={'/c/' + cat.slug}
                                  className="at-category-tile"
                              >
                                  <div
                                      className="at-category-photo"
                                      style={{ height: `${height}px` }}
                                  >
                                      {cat.image ? (
                                          <img
                                              src={cat.image.url}
                                              alt={cat.name}
                                              loading="lazy"
                                          />
                                      ) : (
                                          <div className="flex h-full w-full items-center justify-center text-xs text-neutral-400">
                                              {cat.name}
                                          </div>
                                      )}
                                  </div>
                                  <div className="at-category-label">
                                      <span>{cat.name}</span>
                                      <ArrowRight size={14} />
                                  </div>
                              </Link>
                          ))
                        : section.blocks.map((b) => (
                              <Link
                                  key={b.id}
                                  href={str(b.settings.link, '/search')}
                                  className="at-category-tile"
                                  {...blockAttributes(b)}
                              >
                                  <div
                                      className="at-category-photo"
                                      style={{ height: `${height}px` }}
                                  >
                                      {str(b.settings.image) && (
                                          <img
                                              src={assetUrl(b.settings.image)}
                                              alt={str(
                                                  b.settings.alt,
                                                  str(b.settings.label),
                                              )}
                                              loading="lazy"
                                          />
                                      )}
                                  </div>
                                  <div className="at-category-label">
                                      <span>{str(b.settings.label)}</span>
                                      <ArrowRight size={14} />
                                  </div>
                              </Link>
                          ))}
                </div>
            </div>
        </section>
    );
}
