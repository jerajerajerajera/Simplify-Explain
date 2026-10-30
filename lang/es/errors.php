<?php

return [
    'unreachable' => 'No se pudo conectar con :host desde el panel. Comprueba que el servidor del panel pueda acceder a internet.',
    'invalid_key' => 'La clave API de :company no es válida. Un administrador puede introducir otra en los ajustes de Explain.',
    'no_credit' => 'La cuenta de :company de esta clave no tiene saldo. Un administrador puede añadir saldo en :company.',
    'model_not_allowed' => 'Esta clave de :company no puede usar el modelo elegido. Un administrador puede elegir otro en los ajustes de Explain.',
    'model_missing' => 'El modelo de :company elegido no está disponible para esta clave. Un administrador puede elegir otro en los ajustes de Explain.',
    'busy' => ':company está ocupado para esta clave ahora mismo. Inténtalo de nuevo en un minuto.',
    'unavailable' => ':company no está disponible temporalmente. Inténtalo de nuevo en un minuto.',
    'failed' => ':company no pudo responder a esta pregunta (error :status). Inténtalo más tarde.',
    'server_unreachable' => 'El panel no puede conectar con el servidor de :company en :host. Revisa la dirección en los ajustes de Explain y que :company esté en marcha.',
    'server_model_missing' => 'Este modelo no está en tu servidor de :company. Descárgalo con "ollama pull" o escribe otro modelo en los ajustes de Explain.',
];
