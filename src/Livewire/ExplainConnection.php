<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use Filament\Schemas\Schema;

/**
 * Explain's row on Simplify's Connections page (account menu, Simplify's ADDONS.md "connections"), for root admins:
 * the AI service or Ollama server with its key or address, Test and Remove key. It is the service part of Explain's
 * settings form, so it saves, tests and checks its user the same way; who may use Explain stays in the settings.
 *
 * @property Schema $form
 */
final class ExplainConnection extends ExplainSettingsForm
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            self::serviceSection(__('simplify-explain::settings.connection_heading')),
        ]);
    }
}
