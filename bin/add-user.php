#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2018 François Kooman <fkooman@tuxed.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\SAML\IdP\Config;

try {
    echo 'User ID: ';
    $userId = trim(fgets(STDIN));
    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    echo sprintf('Setting password for user "%s"', $userId).PHP_EOL;
    // ask for password
    exec('stty -echo');
    echo 'Password: ';
    $userPass = trim(fgets(STDIN));
    echo PHP_EOL.'Password (repeat): ';
    $userPassRepeat = trim(fgets(STDIN));
    exec('stty echo');
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
        throw new RuntimeException(sprintf('backend "%s" not supported for adding users', $config->get('authMethod')));
    }

    $configData = $config->toArray();
    $passwordHash = password_hash($userPass, PASSWORD_DEFAULT);
    $configData['simpleAuth'][$userId] = [
        'authPassHash' => $passwordHash,
        'attributeList' => [
            'uid' => [$userId],
        ],
    ];

    $configFileStr = sprintf("<?php\n\nreturn %s;", var_export($configData, true));
    if (false === file_put_contents($configFile, $configFileStr)) {
        throw new RuntimeException(sprintf('unable to write "%s"', $configFile));
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
