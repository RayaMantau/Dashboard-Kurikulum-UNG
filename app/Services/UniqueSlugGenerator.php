<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UniqueSlugGenerator
{
    public function generate(Builder $query, string $name, string $fallback): string
    {
        $base = substr(Str::slug($name), 0, 240) ?: $fallback;
        $slug = $base;
        $suffix = 2;

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
