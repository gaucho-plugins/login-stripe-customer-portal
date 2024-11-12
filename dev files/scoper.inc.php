<?php

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'LSCP', // Replace this with your custom namespace prefix
    'finders' => [
        // Specify the directory you want to prefix
        Finder::create()->files()->in(__DIR__ . '/lib/stripe-php'),
    ],
    'patchers' => [
        // You can add patchers here if necessary for compatibility.
    ],
    'exclude-namespaces' => [
        // If there are namespaces you want to exclude from prefixing, list them here.
    ],
    'expose-constants' => [
        // If there are global constants you want to keep accessible, list them here.
    ],
    'expose-functions' => [
        // If there are global functions you want to keep accessible, list them here.
    ],
    'expose-classes' => [
        // If there are classes you want to keep accessible, list them here.
    ],
];
