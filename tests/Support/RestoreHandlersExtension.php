<?php

namespace Tests\Support;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Runner\ErrorHandler as PHPUnitErrorHandler;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Some third-party code exercised by the suite (HTMLPurifier's DOMLex/Encoder
 * mute handlers) can leave an extra error/exception handler on the stack. Under
 * PHPUnit 12's strict handler check that leak then cascades: every LATER test is
 * flagged "risky — removed error handlers" because PHPUnit's per-test restore
 * pops back to the leaked handler instead of its own.
 *
 * This extension pops any handlers left above PHPUnit's own after each test, so
 * a single leak can no longer cascade across the suite.
 */
final class RestoreHandlersExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements FinishedSubscriber {
            public function notify(Finished $event): void
            {
                // Pop leaked error handlers back down to PHPUnit's own (or none).
                for ($i = 0; $i < 25; $i++) {
                    $current = set_error_handler(static fn (): bool => false);
                    restore_error_handler(); // undo the probe
                    if ($current === null || $current instanceof PHPUnitErrorHandler) {
                        break;
                    }
                    restore_error_handler(); // pop the leaked handler
                }

                // Same for exception handlers.
                for ($i = 0; $i < 25; $i++) {
                    $current = set_exception_handler(null);
                    restore_exception_handler(); // undo the probe
                    if ($current === null || $current instanceof PHPUnitErrorHandler) {
                        break;
                    }
                    restore_exception_handler(); // pop the leaked handler
                }
            }
        });
    }
}
