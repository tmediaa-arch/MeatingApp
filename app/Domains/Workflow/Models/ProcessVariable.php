<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * متغیر یک instance.
 *
 * مقدار در یکی از ستون‌های typed ذخیره می‌شود برای جستجوی سریع.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $scope_token_id
 * @property string $name
 * @property string $type
 */
class ProcessVariable extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ProcessVariableFactory::new();
    }

    protected $fillable = [
        'instance_id',
        'scope_token_id',
        'name',
        'type',
        'string_value',
        'integer_value',
        'float_value',
        'boolean_value',
        'json_value',
        'datetime_value',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'integer_value' => 'integer',
        'float_value' => 'float',
        'boolean_value' => 'boolean',
        'json_value' => 'array',
        'datetime_value' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'instance_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ProcessToken::class, 'scope_token_id');
    }

    public function getValue(): mixed
    {
        return match ($this->type) {
            'string' => $this->string_value,
            'integer' => $this->integer_value,
            'float' => $this->float_value,
            'boolean' => $this->boolean_value,
            'json' => $this->json_value,
            'date', 'datetime' => $this->datetime_value,
            'reference' => [$this->reference_type, $this->reference_id],
            default => null,
        };
    }

    /**
     * مقدار را با تشخیص خودکار نوع تنظیم می‌کند.
     */
    public function setValue(mixed $value): void
    {
        // ریست همه ستون‌های مقدار
        $this->string_value = null;
        $this->integer_value = null;
        $this->float_value = null;
        $this->boolean_value = null;
        $this->json_value = null;
        $this->datetime_value = null;
        $this->reference_type = null;
        $this->reference_id = null;

        if (is_bool($value)) {
            $this->type = 'boolean';
            $this->boolean_value = $value;
        } elseif (is_int($value)) {
            $this->type = 'integer';
            $this->integer_value = $value;
        } elseif (is_float($value)) {
            $this->type = 'float';
            $this->float_value = $value;
        } elseif ($value instanceof \DateTimeInterface) {
            $this->type = 'datetime';
            $this->datetime_value = $value;
        } elseif (is_array($value) || is_object($value)) {
            $this->type = 'json';
            $this->json_value = is_object($value) ? json_decode(json_encode($value), true) : $value;
        } elseif (is_string($value)) {
            $this->type = 'string';
            $this->string_value = $value;
        } else {
            $this->type = 'json';
            $this->json_value = ['value' => $value];
        }
    }

    public static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            $value instanceof \DateTimeInterface => 'datetime',
            is_array($value), is_object($value) => 'json',
            default => 'string',
        };
    }
}
