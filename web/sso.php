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

use fkooman\SAML\IdP\Certificate;
use fkooman\SAML\IdP\Config;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Key;
use fkooman\SAML\IdP\SAMLResponse;
use ParagonIE\ConstantTime\Base64;

\libxml_disable_entity_loader(true);

$baseDir = \dirname(__DIR__);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');

    $request = new Request($_SERVER, $_GET, $_POST);

    // make sure user is logged in
    \session_start();

    if ('POST' === $request->getMethod()) {
        // attempt at logging in
        $authUser = $request->getPostParameter('authUser');
        $authPass = $request->getPostParameter('authPass');

        if (!$config->get('plainAuth')->has($authUser)) {
            throw new Exception('no such user');
        }

        if (!\password_verify($authPass, $config->get('plainAuth')->get($authUser)->get('authPassHash'))) {
            throw new Exception('invalid password');
        }
        $_SESSION['is_authenticated'] = true;
    }

    // assume GET
    if (!\array_key_exists('is_authenticated', $_SESSION) || !$_SESSION['is_authenticated']) {
        // auth
        echo '<html><head><title>Foo</title></head><body><form method="post"><label>User <input type="text" name="authUser"></label><label>Password <input type="password" name="authPass"></label><input type="submit" value="Sign In"></form></body></html>';
        exit(0);
    }

    // XXX input validation of everything
    $samlRequest = \gzinflate(Base64::decode($request->getQueryParameter('SAMLRequest'), true));
    $relayState = $request->getQueryParameter('RelayState');

    $dom = new DOMDocument();
    $dom->loadXML($samlRequest);
    foreach ($dom->childNodes as $child) {
        if (XML_DOCUMENT_TYPE_NODE === $child->nodeType) {
            throw new \InvalidArgumentException(
                'Invalid XML: Detected use of illegal DOCTYPE'
            );
        }
    }

    $authnRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'AuthnRequest')->item(0);
    $authnRequestId = $authnRequest->getAttribute('ID');
    $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
    $spConfig = $config->get('spList')->get($spEntityId);
    $authnRequestAcsUrl = $spConfig->get('AssertionConsumerServiceURL');

    $samlResponse = new SAMLResponse(
        Key::fromFile($baseDir.'/config/server.key'),
        Certificate::fromFile($baseDir.'/config/server.crt')
    );

    $responseXml = $samlResponse->getAssertion($authnRequestAcsUrl, $spEntityId, $request->getRootUri().'metadata.php', $authnRequestId);
    \error_log($responseXml);

    echo \sprintf('<html><head><title>Foo</title></head><body><form method="post" action="%s"><input type="hidden" name="SAMLResponse" value="%s"><input type="hidden" name="RelayState" value="%s"><input type="submit"></form></body></html>', $authnRequestAcsUrl, Base64::encode($responseXml), $relayState);
} catch (Exception $e) {
    die($e->getMessage());
}
