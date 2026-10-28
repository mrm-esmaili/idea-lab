<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(
        string $model,
        string $source,
        string $fallback
    ): string {
        $base = Str::limit(Str::slug($source), 80, '');

        if ($base === '') {
            $base = $fallback;
        }

        $slug = $base;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base . '-' . Str::lower(Str::random(5));
        }

        return $slug;
    }
}
