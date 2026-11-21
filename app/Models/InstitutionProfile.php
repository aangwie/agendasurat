<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class InstitutionProfile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'government_name',
        'institution_name',
        'address',
        'website',
        'email',
        'logo_path',
    ];

    /**
     * Get the single institution profile row, creating it when missing.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrCreate([]);
    }

    /**
     * Get the public URL of the uploaded government logo.
     *
     * Memakai path relatif agar tetap tampil pada host/port apa pun
     * (mis. localhost maupun 127.0.0.1), ditambah versi untuk cache busting.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (blank($this->logo_path)) {
                    return null;
                }

                $version = $this->updated_at?->timestamp;

                return '/storage/'.ltrim($this->logo_path, '/').($version ? '?v='.$version : '');
            },
        );
    }

    /**
     * Get the institution name or a fallback label.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => filled($this->institution_name)
                ? $this->institution_name
                : config('app.name', 'Agenda Surat'),
        );
    }

    /**
     * Get the government name or a fallback label.
     */
    protected function displayGovernment(): Attribute
    {
        return Attribute::make(
            get: fn (): string => filled($this->government_name)
                ? $this->government_name
                : 'Pemerintah Daerah',
        );
    }

    /**
     * Get the website URL with a scheme so it can be used in href attributes.
     */
    protected function websiteHref(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (blank($this->website)) {
                    return null;
                }

                return str_starts_with($this->website, 'http')
                    ? $this->website
                    : 'https://'.$this->website;
            },
        );
    }
}
