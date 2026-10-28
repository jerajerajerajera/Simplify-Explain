<?php

declare(strict_types=1);

namespace Simplify\Explain;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;

final class SimplifyExplainPlugin implements Plugin
{
    public const ID = 'simplify-explain';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([ExplainSettings::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
