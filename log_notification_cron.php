<?php

    // Find out where we are:
    define('DOCROOT', __DIR__ . '/../..');

    // Include autoloader:
    require_once DOCROOT . '/vendor/autoload.php';

    // Include the boot script:
    require_once DOCROOT . '/symphony/lib/boot/bundle.php';
    
    define_safe('__SYM_DATE_FORMAT__', Symphony::Configuration()->get('date_format', 'region'));
    define_safe('__SYM_TIME_FORMAT__', Symphony::Configuration()->get('time_format', 'region'));
    define_safe('__SYM_DATETIME_FORMAT__', __SYM_DATE_FORMAT__ . Symphony::Configuration()->get('datetime_separator', 'region') . __SYM_TIME_FORMAT__);
    DateTimeObj::setSettings(Symphony::Configuration()->get('region'));

    ExtensionManager::getInstance('log_notifications')->sendLogEmail();

    echo 'log notification completed';