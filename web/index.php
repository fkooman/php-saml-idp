<?php

/*
 * Copyright (c) 2019 François Kooman <fkooman@tuxed.net>
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

use fkooman\Otp\Storage;
use fkooman\Otp\Totp;
use fkooman\SAML\IdP\Certificate;
use fkooman\SAML\IdP\Config;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Http\Response;
use fkooman\SAML\IdP\Key;
use fkooman\SAML\IdP\Service;
use fkooman\SAML\IdP\SeSession;
use fkooman\SAML\IdP\Template;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\Session;

try {
    $tpl = new Template([$baseDir.'/src/tpl', $baseDir.'/config/tpl']);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $metadataConfig = Config::fromFile($baseDir.'/config/metadata.php');

    $samlKey = Key::fromFile($baseDir.'/config/server.key');
    $samlCert = Certificate::fromFile($baseDir.'/config/server.crt');

    $secureCookie = true;
    if ($config->has('secureCookie')) {
        $secureCookie = $config->get('secureCookie');
    }

    $cookieOptions = CookieOptions::init()->withSameSiteLax();
    $session = new Session(
        null,
        $secureCookie ? $cookieOptions : $cookieOptions->withoutSecure()
    );

    $storage = new Storage(new PDO(sprintf('sqlite:%s/data/db.sqlite', $baseDir)));
    $totp = new Totp($storage);

    $service = new Service($config, $totp, $metadataConfig, new SeSession($session), $tpl, $samlKey, $samlCert);
    $service->run(new Request($_SERVER, $_GET, $_POST))->send();
} catch (Exception $e) {
    $response = new Response(
        'ERROR: '.$e->getMessage(),
        [],
        500
    );
    $response->send();
}
