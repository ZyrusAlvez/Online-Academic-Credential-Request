<?php

return [

    /*
    | URL of the university's existing online payment portal. Students pay there and
    | upload the receipt to this system; no payment is processed here.
    */
    'payment_url' => env('UPHSL_PAYMENT_URL', 'https://uphsl.edu.ph/online_payment/guest'),

    /*
    | Disk for students' payment receipts: "local" (storage/app/private/<env>) or "r2"
    | (Cloudflare R2).
    */
    'receipts_disk' => env('RECEIPTS_DISK', 'local'),

    /*
    | Days of the week the offices work (ISO numbers, 1 = Monday … 7 = Sunday). Holidays and
    | closures come from the working calendar (non_working_days). Spec 10.
    */
    'work_week' => [1, 2, 3, 4, 5, 6],

    /*
    | The first registrar account, created by `db:seed` when no account with this email exists,
    | so that someone can log in and create every other account. The seeder never changes an
    | existing account, so editing these later does not change its password.
    */
    'initial_registrar' => [
        'email' => env('INITIAL_REGISTRAR_EMAIL'),
        'password' => env('INITIAL_REGISTRAR_PASSWORD'),
    ],

];
