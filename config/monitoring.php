<?php

return [

    /*
    | Bearer token Prometheus presents to GET /api/metrics. Scraped over public
    | HTTPS so it needs no private networking and measures what a visitor
    | reaches.
    */
    'metrics_token' => env('MONITORING_METRICS_TOKEN'),
];
