<?php

return [
    // Dominios válidos para el correo institucional (confírmalos con TI).
    'email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('GRAD_EMAIL_DOMAINS', 'unicesmag.edu.co'))
    ))),

    // Cupo de invitados si la ceremonia no define uno.
    'default_max_guests' => 2,

    // Código de un solo uso para el login (tabla login_codes).
    'otp' => ['ttl_minutes' => 10, 'max_attempts' => 5],

    // Google Sheets: ID del archivo y nombres exactos de las pestañas.
    'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
    'graduates_sheet' => 'Graduados',
    'responses_sheet' => 'Respuestas',
];
