<?php

return [
    'unreachable' => 'A panel nem éri el ezt: :host. Ellenőrizd, hogy a panel szervere csatlakozhat-e az internetre.',
    'invalid_key' => 'A :company API-kulcs érvénytelen. Egy adminisztrátor újat adhat meg az Explain beállításaiban.',
    'no_credit' => 'A kulcshoz tartozó :company-fióknak elfogyott az egyenlege. Egy adminisztrátor feltöltheti a :company oldalán.',
    'model_not_allowed' => 'Ez a :company-kulcs nem használhatja a választott modellt. Egy adminisztrátor másikat választhat az Explain beállításaiban.',
    'model_missing' => 'A választott :company-modell ehhez a kulcshoz nem érhető el. Egy adminisztrátor másikat választhat az Explain beállításaiban.',
    'busy' => 'A :company most túlterhelt ennél a kulcsnál. Próbáld újra egy perc múlva.',
    'unavailable' => 'A :company átmenetileg nem érhető el. Próbáld újra egy perc múlva.',
    'failed' => 'A :company nem tudott válaszolni (hiba: :status). Próbáld újra később.',
    'server_unreachable' => 'A panel nem éri el a(z) :company-szervert itt: :host. Ellenőrizd a címet az Explain beállításaiban, és hogy fut-e a(z) :company.',
    'server_model_missing' => 'Ez a modell nincs a(z) :company-szervereden. Töltsd le az "ollama pull" paranccsal, vagy adj meg másik modellt az Explain beállításaiban.',
];
