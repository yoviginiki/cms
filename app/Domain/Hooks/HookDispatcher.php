<?php

namespace App\Domain\Hooks;

class HookDispatcher
{
    /** @var array<string, array<int, array{callable: callable, priority: int}>> */
    private array $actions = [];

    /** @var array<string, array<int, array{callable: callable, priority: int}>> */
    private array $filters = [];

    /**
     * Register an action hook (side effects, no return value).
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][] = ['callable' => $callback, 'priority' => $priority];
    }

    /**
     * Register a filter hook (transforms a value through a chain).
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][] = ['callable' => $callback, 'priority' => $priority];
    }

    /**
     * Execute all action callbacks for a hook.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        $callbacks = $this->actions[$hook] ?? [];
        usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($callbacks as $cb) {
            call_user_func($cb['callable'], ...$args);
        }
    }

    /**
     * Run a value through all filter callbacks for a hook.
     */
    public function applyFilter(string $hook, mixed $value, mixed ...$args): mixed
    {
        $callbacks = $this->filters[$hook] ?? [];
        usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($callbacks as $cb) {
            $value = call_user_func($cb['callable'], $value, ...$args);
        }

        return $value;
    }

    /**
     * Collect all action outputs as concatenated string (for HTML injection points).
     */
    public function collectAction(string $hook, mixed ...$args): string
    {
        $output = '';
        $callbacks = $this->actions[$hook] ?? [];
        usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($callbacks as $cb) {
            ob_start();
            call_user_func($cb['callable'], ...$args);
            $output .= ob_get_clean();
        }

        return $output;
    }

    /**
     * Check if a hook has any registered callbacks.
     */
    public function hasHook(string $hook): bool
    {
        return !empty($this->actions[$hook]) || !empty($this->filters[$hook]);
    }

    /**
     * Remove all callbacks for a hook.
     */
    public function removeAll(string $hook): void
    {
        unset($this->actions[$hook], $this->filters[$hook]);
    }
}
