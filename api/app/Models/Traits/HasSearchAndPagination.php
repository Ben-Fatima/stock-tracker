<?php

namespace App\Models\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait providing reusable search, sort, and pagination helpers for Eloquent models.
 *
 * Expected model constants:
 * SearchableField string
 * array<int, SearchableField> static::SEARCHABLE
 * array<int, string> static::ALLOWED_SORTS
 * int static::PER_PAGE_DEFAULT
 * int static::PER_PAGE_MAX
 */
trait HasSearchAndPagination
{
    /**
     * Build a query with search/sort and return a paginated result set.
     *
     * @param array<string, mixed> $params
     */
    public static function searchAndPaginate(array $params): LengthAwarePaginator
    {
        $search = static::normalizeString($params['q'] ?? null);
        $sort   = static::normalizeString($params['sort'] ?? null);
        $dir    = static::normalizeDir($params['dir'] ?? null);
        $perPage = static::parsePerPage($params['per_page'] ?? null);
        
        $query  = static::query();
        static::applySearch($query, $search);
        static::applySort($query, $sort, $dir);

        return $query->paginate($perPage);
    }

    /**
     * Normalize a value into a trimmed string or null.
     */
    protected static function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalize direction to 'asc' or 'desc'.
     */
    protected static function normalizeDir(mixed $dir): string
    {
        $dir = strtolower(static::normalizeString($dir));
        if ($dir === 'desc') return 'desc';
        return 'asc';
    }

    /**
     * Parse per-page value, clamped to configured limits.
     */
    protected static function parsePerPage(mixed $value): int
    {
        $perPage = static::PER_PAGE_DEFAULT;

        if (is_string($value)) {
            $value = trim($value);
            if (ctype_digit($value)) {
                $perPage = (int) $value;
            }
        } elseif (is_int($value)) {
            $perPage = $value;
        }

        if ($perPage <= 0) {
            return static::PER_PAGE_DEFAULT;
        }

        return min($perPage, static::PER_PAGE_MAX);
    }

    /**
     * Apply search filters to the query.
     */
    protected static function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $fields = static::SEARCHABLE ?? [];
        if (empty($fields)) {
            return;
        }

        $query->where(function ($q) use ($fields, $search) {
            $first = array_shift($fields);
            $q->where($first, 'like', "%{$search}%");

            foreach ($fields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Apply sorting to the query.
     */
    protected static function applySort(Builder $query, ?string $sort, string $dir): void
    {
        if ($sort === null) {
            $query->orderBy('id', 'asc');
            return;
        }

        $allowed = static::ALLOWED_SORTS ?? [];
        if (!in_array($sort, $allowed, true)) {
            $query->orderBy('id', 'asc');
            return;
        }

        $query->orderBy($sort, $dir);
    }
}
