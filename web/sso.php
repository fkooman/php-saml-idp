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
use fkooman\SAML\IdP\Template;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use ParagonIE\ConstantTime\Base64;

\libxml_disable_entity_loader(true);

$baseDir = \dirname(__DIR__);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');

    $session = new Session(
        [],
        new Cookie(
            [
               'SameSite' => 'Lax',
            ]
        )
    );

    $request = new Request($_SERVER, $_GET, $_POST);

    $tpl = new Template([\sprintf('%s/views', $baseDir)]);

    $idpEntityId = $request->getRootUri().'metadata.php';

    // make sure user is logged in
//    \session_start();

    $userAttributeList = [];

    if ('POST' === $request->getMethod()) {
        // determine auth mech
        $authMethod = $config->get('authMethod');
        $authMethodClass = '\\fkooman\\SAML\\IdP\\'.\ucfirst($authMethod);
        $userAuthMethod = new $authMethodClass($config->get($authMethod));

        // set session crap
        // XXX failing auth throws exception?
        $session->set('userInfo', $userAuthMethod->authenticate($request->getPostParameter('authUser'), $request->getPostParameter('authPass')));
        $session->regenerate(true);
    }

    // assume GET
    if (!$session->has('userInfo')) {
        // auth
        echo $tpl->render('auth');
        exit(0);
    }

    // XXX input validation of everything
    $samlRequest = \gzinflate(Base64::decode($request->getQueryParameter('SAMLRequest'), true));
    $relayState = $request->hasQueryParameter('RelayState') ? $request->getQueryParameter('RelayState') : null;

    $dom = new DOMDocument();
    $dom->loadXML($samlRequest, LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_COMPACT);
    foreach ($dom->childNodes as $child) {
        if (XML_DOCUMENT_TYPE_NODE === $child->nodeType) {
            throw new \InvalidArgumentException(
                'Invalid XML: Detected use of illegal DOCTYPE'
            );
        }
    }

    $authnRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'AuthnRequest')->item(0);
    $authnRequestId = $authnRequest->getAttribute('ID');
    $forceAuthn = $authnRequest->getAttribute('ForceAuthn');
    if ('true' === $forceAuthn) {
        // force authentication of the user
        $session->delete('userInfo');
        // auth
        echo $tpl->render('auth');
        exit(0);
    }

    $userInfo = $session->get('userInfo');

    $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
    // XXX make sure we are the audience
    $spConfig = $config->get('spList')->get($spEntityId);
    $authnRequestAcsUrl = $spConfig->get('AssertionConsumerServiceURL');

    $samlResponse = new SAMLResponse(
        Key::fromFile($baseDir.'/config/server.key'),
        Certificate::fromFile($baseDir.'/config/server.crt')
    );

    // add common attributes
    if ($config->has('commonAttributeList')) {
        $commonAttributeList = $config->get('commonAttributeList')->toArray();
        foreach ($commonAttributeList as $k => $v) {
            $samlResponse->setAttribute($k, $v);
        }
    }

    // XXX take this from configuration file!
    $secretSalt = '8NwZS2Yudja6AzbsM5gQrH0fz24VLrCnptx20bAF8h4=';
    $persistentId = Base64::encode(
        \hash(
            'sha256',
            \sprintf('%s|%s|%s|%s', $secretSalt, $userInfo->getAuthUser(), $idpEntityId, $spEntityId),
            true
        )
    );
    $samlResponse->setPersistentId($persistentId);

    foreach ($userInfo->getAttributes() as $k => $v) {
        $samlResponse->setAttribute($k, $v);
    }

    $responseXml = $samlResponse->getAssertion($spConfig, $spEntityId, $idpEntityId, $authnRequestId);
//    \error_log($responseXml);

    echo $tpl->render('submit', ['relayState' => $relayState, 'acsUrl' => $authnRequestAcsUrl, 'samlResponse' => Base64::encode($responseXml)]);
    exit(0);
} catch (Exception $e) {
    die($e->getMessage());
}
