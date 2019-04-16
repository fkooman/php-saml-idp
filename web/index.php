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

use fkooman\SAML\IdP\Config;
use fkooman\SAML\IdP\ErrorHandler;
use fkooman\SAML\IdP\Http\HtmlResponse;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Service;
use fkooman\SAML\IdP\Template;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;

ErrorHandler::register();

$tpl = new Template(
    [
        sprintf('%s/views', $baseDir),
        sprintf('%s/config/views', $baseDir),
    ]
);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $metadataConfig = Config::fromFile($baseDir.'/config/metadata.php');

    $secureCookie = true;
    if ($config->has('secureCookie')) {
        $secureCookie = $config->get('secureCookie');
    }

//    error_log(var_export($secureCookie, true));

    $session = new Session(
        [],
        new Cookie(
            [
                'SameSite' => 'Lax',
                'Secure' => $secureCookie,
            ]
        )
    );
    $service = new Service($baseDir, $config, $metadataConfig, $session, $tpl);
    $response = $service->run(new Request($_SERVER, $_GET, $_POST));
    $response->send();
} catch (Exception $e) {
    $response = new HtmlResponse(
        $tpl->render('error', ['errorCode' => 500, 'errorMessage' => $e->getMessage()]),
        [],
        500
    );
    $response->send();
}
