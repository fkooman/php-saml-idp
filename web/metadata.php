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

require_once \dirname(__DIR__).'/vendor/autoload.php';
$baseDir = \dirname(__DIR__);

use fkooman\SAML\IdP\Certificate;
use fkooman\SAML\IdP\Config;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Http\Response;
use fkooman\SAML\IdP\Template;

$config = Config::fromFile($baseDir.'/config/config.php');
$request = new Request($_SERVER, $_GET, $_POST);
$entityId = $request->getRootUri().'metadata.php';
$ssoUri = $request->getRootUri().'sso.php';
$sloUri = $request->getRootUri().'slo.php';

$rsaCert = Certificate::fromFile($baseDir.'/config/server.crt');
$keyInfo = $rsaCert->toKeyInfo();

$dateTime = new DateTime();
$validUntil = \date_add(clone $dateTime, new DateInterval('PT24H'));

$tpl = new Template([$baseDir.'/views']);
$metaDataDocument = $tpl->render(
    'metadata',
    [
        'entityId' => $entityId,
        'keyInfo' => $keyInfo,
        'ssoUri' => $ssoUri,
        'sloUri' => $sloUri,
        'displayNameList' => $config->get('metaData')->get('displayNameList')->toArray(),
        'logoList' => $config->get('metaData')->get('logoList')->toArray(),
        'informationUrlList' => $config->get('metaData')->get('informationUrlList')->toArray(),
        'technicalContact' => $config->get('metaData')->get('technicalContact'),
        'identityScope' => $config->get('identityScope'),
        'validUntil' => $validUntil->format('Y-m-d\TH:i:s\Z'),
    ]
);

$response = new Response(200, ['Content-Type' => 'application/samlmetadata+xml'], $metaDataDocument);
$response->send();
