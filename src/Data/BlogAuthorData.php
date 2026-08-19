<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * Public-safe description of a blog author archive subject.
 *
 * The users table owned by Core carries no public slug column, so the public
 * identity of an author is derived from their display name. `userId` is kept
 * for server-side query scoping only and must never reach rendered output.
 */
final class BlogAuthorData extends Data
{
    public function __construct(
        public int $userId,
        public string $slug,
        public string $name,
    ) {}

    /**
     * Derive the public archive slug for an author display name.
     */
    public static function slugForName(string $name): string
    {
        return Str::slug(trim($name));
    }
}
