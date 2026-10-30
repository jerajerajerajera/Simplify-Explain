<?php

return [
    'unreachable' => 'Kunne ikke nå :host fra panelet. Tjek, at panelserveren må forbinde til internettet.',
    'invalid_key' => 'API-nøglen til :company er ugyldig. En administrator kan indtaste en ny i Explain-indstillingerne.',
    'no_credit' => ':company-kontoen bag nøglen har ingen kredit tilbage. En administrator kan tilføje kredit hos :company.',
    'model_not_allowed' => 'Denne :company-nøgle må ikke bruge den valgte model. En administrator kan vælge en anden i Explain-indstillingerne.',
    'model_missing' => 'Den valgte :company-model er ikke tilgængelig for nøglen. En administrator kan vælge en anden i Explain-indstillingerne.',
    'busy' => ':company er optaget for denne nøgle lige nu. Prøv igen om et minut.',
    'unavailable' => ':company er midlertidigt utilgængelig. Prøv igen om et minut.',
    'failed' => ':company kunne ikke svare på spørgsmålet (fejl :status). Prøv igen senere.',
    'server_unreachable' => 'Panelet kan ikke nå :company-serveren på :host. Tjek adressen i Explain-indstillingerne, og at :company kører.',
    'server_model_missing' => 'Denne model findes ikke på din :company-server. Hent den med "ollama pull", eller skriv en anden model i Explain-indstillingerne.',
];
