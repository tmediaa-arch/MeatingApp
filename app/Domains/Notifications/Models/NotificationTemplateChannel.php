<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Models;

use App\Domains\Notifications\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplateChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id', 'channel', 'subject', 'body', 'body_html', 'metadata',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Render کردن template با متغیرها.
     * placeholderها به فرمت {{ name }} هستند.
     */
    public function render(array $variables, ?string $field = 'body'): string
    {
        $content = $this->{$field} ?? '';

        foreach ($variables as $key => $value) {
            $content = str_replace(
                ['{{ ' . $key . ' }}', '{{' . $key . '}}'],
                (string) $value,
                $content,
            );
        }

        return $content;
    }
}
