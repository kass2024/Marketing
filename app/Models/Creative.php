<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creative extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'creatives';


    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_DRAFT  = 'DRAFT';
    const STATUS_ARCHIVED = 'ARCHIVED';


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'meta_creative_id',

        // creative content
        'title',
        'body',

        // media
        'image_url',
        'video_url',

        // CTA
        'call_to_action',

        // destination
        'destination_url',

        // meta payload
        'json_payload',

        // status
        'status'
    ];


    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'json_payload' => 'array',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Creative can be used by many Ads
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }


    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }


    /*
    |--------------------------------------------------------------------------
    | Media Helpers
    |--------------------------------------------------------------------------
    */

    public function hasImage(): bool
    {
        return !empty($this->image_url);
    }

    public function hasVideo(): bool
    {
        return !empty($this->video_url);
    }

    public function getMediaTypeAttribute(): string
    {
        if ($this->video_url) {
            return 'video';
        }

        if ($this->image_url) {
            return 'image';
        }

        return 'unknown';
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->video_url ?: $this->image_url;
    }


    /*
    |--------------------------------------------------------------------------
    | Preview Helpers
    |--------------------------------------------------------------------------
    */

    public function getHeadlineAttribute(): string
    {
        return $this->title ?? '';
    }

    public function getDescriptionAttribute(): string
    {
        return $this->body ?? '';
    }

    public function getPreviewData(): array
    {
        return [
            'headline' => $this->title,
            'description' => $this->body,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'cta' => $this->call_to_action,
            'destination_url' => $this->destination_url
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Meta API Helpers
    |--------------------------------------------------------------------------
    */

    public function getMetaPayload(): array
    {
        return $this->json_payload ?? [];
    }

    public function setMetaPayload(array $payload): void
    {
        $this->json_payload = $payload;
        $this->save();
    }


    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}