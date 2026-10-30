<?php

return [
    'unreachable' => 'Kunde inte nå :host från panelen. Kontrollera att panelservern får ansluta till internet.',
    'invalid_key' => 'API-nyckeln för :company är ogiltig. En administratör kan ange en ny i Explain-inställningarna.',
    'no_credit' => ':company-kontot bakom nyckeln har inget saldo kvar. En administratör kan fylla på hos :company.',
    'model_not_allowed' => 'Den här :company-nyckeln får inte använda den valda modellen. En administratör kan välja en annan i Explain-inställningarna.',
    'model_missing' => 'Den valda :company-modellen är inte tillgänglig för nyckeln. En administratör kan välja en annan i Explain-inställningarna.',
    'busy' => ':company är upptagen för den här nyckeln just nu. Försök igen om en minut.',
    'unavailable' => ':company är tillfälligt otillgänglig. Försök igen om en minut.',
    'failed' => ':company kunde inte svara på frågan (fel :status). Försök igen senare.',
    'server_unreachable' => 'Panelen når inte :company-servern på :host. Kontrollera adressen i Explains inställningar och att :company körs.',
    'server_model_missing' => 'Den här modellen finns inte på din :company-server. Ladda ner den med "ollama pull" eller ange en annan modell i Explains inställningar.',
];
