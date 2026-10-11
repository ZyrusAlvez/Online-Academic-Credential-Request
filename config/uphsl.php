<?php

return [

    /*
    | URL of the university's existing online payment portal. Students pay there and
    | upload the receipt to this system; no payment is processed here.
    */
    'payment_url' => env('UPHSL_PAYMENT_URL', 'https://uphsl.edu.ph/online_payment/guest'),

    /*
    | Initial registrar account created by `php artisan db:seed`.
    */
    'seed' => [
        'registrar_email' => env('SEED_REGISTRAR_EMAIL', 'registrar@uphsl.edu.ph'),
        'registrar_password' => env('SEED_REGISTRAR_PASSWORD'),
    ],

];
