#!/usr/bin/env php
<?php

require_once \dirname(__DIR__).'/vendor/autoload.php';
$baseDir = \dirname(__DIR__);

use fkooman\SAML\IdP\Config;

try {
    echo 'User ID: ';
    $userId = \trim(\fgets(STDIN));
    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    echo \sprintf('Setting password for user "%s"', $userId).PHP_EOL;
    // ask for password
    \exec('stty -echo');
    echo 'Password: ';
    $userPass = \trim(\fgets(STDIN));
    echo PHP_EOL.'Password (repeat): ';
    $userPassRepeat = \trim(\fgets(STDIN));
    \exec('stty echo');
    echo PHP_EOL;
    if ($userPass !== $userPassRepeat) {
        throw new RuntimeException('specified passwords do not match');
    }

    if (empty($userPass)) {
        throw new RuntimeException('Password cannot be empty');
    }

    $configFile = $baseDir.'/config/config.php';
    $config = Config::fromFile($configFile);

    if ('simpleAuth' !== $config->get('authMethod')) {
        throw new RuntimeException(\sprintf('backend "%s" not supported for adding users', $config->get('authMethod')));
    }

    $configData = $config->toArray();
    $passwordHash = \password_hash($userPass, PASSWORD_DEFAULT);
    $configData['simpleAuth'][$userId] = [
        'authPassHash' => $passwordHash,
        'attributeList' => [
            'uid' => [$userId],
        ],
    ];

    $configFileStr = \sprintf("<?php\n\nreturn %s;", \var_export($configData, true));
    if (false === \file_put_contents($configFile, $configFileStr)) {
        throw new RuntimeException(\sprintf('unable to write "%s"', $configFile));
    }
} catch (Exception $e) {
    echo \sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
