<?php

namespace EcoModular\Newsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An email marketing broadcast campaign.
 *
 * @property int $id
 * @property string $title
 * @property string $subject
 * @property string|null $preview_text
 * @property string $target_type
 * @property string|null $target_group
 * @property string $content_html
 * @property string $status
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 * @property Carbon|null $sent_at
 * @property string|null $last_error
 */
class NewsletterCampaign extends Model
{
    protected $table = 'newsletter_campaigns';

    protected $fillable = [
        'title',
        'subject',
        'preview_text',
        'target_type',
        'target_group',
        'content_html',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'sent_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }
}
