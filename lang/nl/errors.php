<?php

return [
    'unreachable' => 'Kon :host niet bereiken vanaf het paneel. Controleer of de paneelserver verbinding met internet mag maken.',
    'invalid_key' => 'De API-sleutel van :company is niet geldig. Een beheerder kan een nieuwe invullen in de Explain-instellingen.',
    'no_credit' => 'Het :company-account achter deze sleutel heeft geen tegoed meer. Een beheerder kan tegoed bijkopen bij :company.',
    'model_not_allowed' => 'Deze :company-sleutel mag het gekozen model niet gebruiken. Een beheerder kan een ander model kiezen in de Explain-instellingen.',
    'model_missing' => 'Het gekozen :company-model is niet beschikbaar voor deze sleutel. Een beheerder kan een ander model kiezen in de Explain-instellingen.',
    'busy' => ':company is nu druk voor deze sleutel. Probeer het over een minuut opnieuw.',
    'unavailable' => ':company is tijdelijk niet beschikbaar. Probeer het over een minuut opnieuw.',
    'failed' => ':company kon deze vraag niet beantwoorden (fout :status). Probeer het later opnieuw.',
    'server_unreachable' => 'Het paneel kan de :company-server op :host niet bereiken. Controleer het adres in de Explain-instellingen en of :company draait.',
    'server_model_missing' => 'Dit model staat niet op je :company-server. Download het met "ollama pull" of vul een ander model in bij de Explain-instellingen.',
];
