@livewire('simplify-explain-panel', ['source' => $source, 'serverId' => $serverId ?? null, 'nodeId' => $nodeId ?? null], key('simplify-explain-'.$source.'-'.($serverId ?? $nodeId ?? 0)))
