<?php

return [

    'public_key' => env('PAYSTACK_PUBLIC_KEY'),

    'secret_key' => env('PAYSTACK_SECRET_KEY'),

    'base_url' => rtrim(env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'), '/'),

];
