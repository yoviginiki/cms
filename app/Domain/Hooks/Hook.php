<?php

namespace App\Domain\Hooks;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void add(string $hook, callable $callback, int $priority = 10)
 * @method static void addAction(string $hook, callable $callback, int $priority = 10)
 * @method static void addFilter(string $hook, callable $callback, int $priority = 10)
 * @method static void doAction(string $hook, mixed ...$args)
 * @method static mixed applyFilter(string $hook, mixed $value, mixed ...$args)
 * @method static string collectAction(string $hook, mixed ...$args)
 * @method static void filter(string $hook, callable $callback, int $priority = 10)
 */
class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookDispatcher::class;
    }

    /**
     * Convenience: add() registers as action.
     */
    public static function add(string $hook, callable $callback, int $priority = 10): void
    {
        static::getFacadeRoot()->addAction($hook, $callback, $priority);
    }

    /**
     * Convenience: filter() registers as filter.
     */
    public static function filter(string $hook, callable $callback, int $priority = 10): void
    {
        static::getFacadeRoot()->addFilter($hook, $callback, $priority);
    }
}
