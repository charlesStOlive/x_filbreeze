<?php

namespace App\Models;

use CharlesStOlive\FilamentPermissionManager\Contracts\HasApiPermissions;
use Illuminate\Database\Eloquent\Model;

class DesTest extends Model implements HasApiPermissions
{
    protected $fillable = ['name', 'published'];

    protected $casts = [
        'published' => 'boolean',
    ];

    /**
     * Permissions custom au-delà du CRUD standard.
     * `permissions:sync` génèrera : destest.publish
     */
    public static function getApiSpecificPermissions(): array
    {
        return ['publish'];
    }

    /**
     * Préfixe utilisé pour toutes les permissions de ce modèle.
     */
    public static function getApiPermissionPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }
}
