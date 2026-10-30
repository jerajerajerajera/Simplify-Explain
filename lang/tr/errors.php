<?php

return [
    'unreachable' => 'Panelden :host adresine ulaşılamadı. Panel sunucusunun internete bağlanabildiğini kontrol et.',
    'invalid_key' => ':company API anahtarı geçersiz. Bir yönetici Explain ayarlarından yenisini girebilir.',
    'no_credit' => 'Bu anahtarın :company hesabında kredi kalmadı. Bir yönetici :company üzerinden kredi ekleyebilir.',
    'model_not_allowed' => 'Bu :company anahtarı seçilen modeli kullanamaz. Bir yönetici Explain ayarlarından başka bir model seçebilir.',
    'model_missing' => 'Seçilen :company modeli bu anahtar için kullanılamıyor. Bir yönetici Explain ayarlarından başka bir model seçebilir.',
    'busy' => ':company şu anda bu anahtar için meşgul. Bir dakika sonra tekrar dene.',
    'unavailable' => ':company geçici olarak kullanılamıyor. Bir dakika sonra tekrar dene.',
    'failed' => ':company bu soruyu yanıtlayamadı (hata :status). Daha sonra tekrar dene.',
    'server_unreachable' => 'Panel :host adresindeki :company sunucusuna ulaşamıyor. Explain ayarlarındaki adresi ve :company\'nin çalıştığını kontrol edin.',
    'server_model_missing' => 'Bu model :company sunucunuzda yok. "ollama pull" ile indirin veya Explain ayarlarında başka bir model girin.',
];
