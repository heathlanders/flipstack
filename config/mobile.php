<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile API authorization enforcement
    |--------------------------------------------------------------------------
    |
    | When false (default), the api.permission / api.role middlewares run in
    | SOFT mode: a would-be denial is audited (security.mobile.authz_denied) but
    | the request still proceeds. Flip to true once the audit logs show only
    | genuinely-unauthorized callers, to start returning hard 403s.
    |
    */

    'authz_enforce' => env('MOBILE_AUTHZ_ENFORCE', false),

];
