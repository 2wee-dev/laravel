<?php

namespace TwoWee\Laravel;

/**
 * Convenience base class for full-screen editable grid resources
 * (journals, batch entry screens, etc.)
 *
 * Subclasses must implement lineColumns() instead of form()/table().
 */
abstract class GridResource extends Resource
{
    public static function layout(): string
    {
        return 'Grid';
    }

    public static function form(): array
    {
        return [];
    }

    public static function table(): array
    {
        return static::lineColumns();
    }
}
