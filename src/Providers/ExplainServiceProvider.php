<?php

declare(strict_types=1);

namespace Simplify\Explain\Providers;

use Illuminate\Support\ServiceProvider;
use Simplify\Explain\ExplainAddon;

final class ExplainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify reads this tag to list its add-ons. Without Simplify nothing reads it and ExplainAddon is never loaded.
        $this->app->tag([ExplainAddon::class], 'simplify.addons');
    }
}
