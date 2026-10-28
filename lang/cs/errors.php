<?php

return [
    'unreachable' => ':host není z panelu dostupný. Zkontroluj, že se server panelu smí připojit k internetu.',
    'invalid_key' => 'API klíč :company není platný. Administrátor může zadat nový v nastavení Explain.',
    'no_credit' => 'Účet :company u tohoto klíče už nemá kredit. Administrátor ho může dobít u :company.',
    'model_not_allowed' => 'Tento klíč :company nesmí používat zvolený model. Administrátor může v nastavení Explain vybrat jiný.',
    'model_missing' => 'Zvolený model :company není pro tento klíč dostupný. Administrátor může v nastavení Explain vybrat jiný.',
    'busy' => ':company je teď pro tento klíč vytížený. Zkus to za minutu znovu.',
    'unavailable' => ':company je dočasně nedostupný. Zkus to za minutu znovu.',
    'failed' => ':company na tento dotaz nedokázal odpovědět (chyba :status). Zkus to později.',
];
