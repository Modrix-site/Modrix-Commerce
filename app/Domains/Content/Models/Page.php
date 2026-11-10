<?php

namespace App\Domains\Content\Models;

use App\Core\Content\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string $status
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class Page extends Model
{
    use SoftDeletes;

    public const KIND_PAGE = 'page';

    public const KIND_FAQ = 'faq';

    protected $fillable = ['title', 'slug', 'kind', 'content', 'faqs', 'status', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return ['faqs' => 'array'];
    }

    /**
     * @return array{title: string, slug: string, kind: string, content: string|null, content_html: string|null, faqs: list<array{question: string, answer: string|null, answer_html: string|null}>, meta_title: string|null, meta_description: string|null}
     */
    public function forStorefront(): array
    {
        $faqs = [];
        foreach ((array) $this->faqs as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $faqs[] = [
                'question' => $question,
                'answer' => RichText::toText($item['answer'] ?? null),
                'answer_html' => RichText::toHtml($item['answer'] ?? null),
            ];
        }

        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'kind' => $this->kind === self::KIND_FAQ ? self::KIND_FAQ : self::KIND_PAGE,
            'content' => RichText::toText($this->content),
            'content_html' => RichText::toHtml($this->content),
            'faqs' => $faqs,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }
}
