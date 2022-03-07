<?php
$hostUrl = 'http://localhost:80';

$app->configureMode('development', function () use ($app) {
    // db einrichten
    ORM::configure('mysql:host=database;dbname=csp_appplaceholder;charset=utf8');
    ORM::configure('username', 'root');
    ORM::configure('password', 'kiloherz_karte_karton');
});

$app->configureMode('production', function () use ($app) {
    ORM::configure('mysql:host=database;dbname=csp_appplaceholder;charset=utf8');
    ORM::configure('username', 'root');
    ORM::configure('password', 'kiloherz_karte_karton');
});
