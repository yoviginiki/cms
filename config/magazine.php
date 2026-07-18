<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DTP default image
    |--------------------------------------------------------------------------
    |
    | Shown in a DTP image frame that has no user-selected image (e.g. an
    | Issue-Studio-generated slot the author hasn't filled yet). Prevents empty
    | holes in the viewer and PDF. Accepts any image URL or data URI — point it
    | at an uploaded asset to brand the fallback. When empty, a built-in neutral
    | SVG placeholder is used (see DtpRenderService::defaultImageSrc()).
    |
    */
    'dtp_default_image' => env('MAGAZINE_DTP_DEFAULT_IMAGE'),
];
