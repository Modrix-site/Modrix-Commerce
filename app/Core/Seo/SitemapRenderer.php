<?php

namespace App\Core\Seo;

/**
 * Renders the sitemap URL set as XML. Output is escaped by the XMLWriter, so a
 * product slug or image URL can never break the document.
 */
final class SitemapRenderer
{
    /**
     * @param  iterable<SitemapEntry>  $entries
     */
    public function render(iterable $entries): string
    {
        $writer = new \XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach ($entries as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry->loc);

            if ($entry->lastmod !== null) {
                $writer->writeElement('lastmod', $entry->lastmod->toAtomString());
            }

            if ($entry->changefreq !== null) {
                $writer->writeElement('changefreq', $entry->changefreq);
            }

            if ($entry->priority !== null) {
                $writer->writeElement('priority', number_format($entry->priority, 1, '.', ''));
            }

            foreach ($entry->images as $image) {
                $writer->startElement('image:image');
                $writer->writeElement('image:loc', $image);
                $writer->endElement();
            }

            foreach ($entry->alternates as $hreflang => $url) {
                $writer->startElement('xhtml:link');
                $writer->writeAttribute('rel', 'alternate');
                $writer->writeAttribute('hreflang', (string) $hreflang);
                $writer->writeAttribute('href', $url);
                $writer->endElement();
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }
}
