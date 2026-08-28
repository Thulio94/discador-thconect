<?php

return [
    // Empty by default. A deployment bootstrap request is impossible unless an
    // operator explicitly enables this short-lived token in the environment.
    'bootstrap_token' => env('INSTALL_BOOTSTRAP_TOKEN'),
];
