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
use fkooman\SAML\IdP\ErrorHandler;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Key;
use fkooman\SAML\IdP\SAMLResponse;
use fkooman\SAML\IdP\Template;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;

ErrorHandler::register();

$tpl = new Template([\sprintf('%s/views', $baseDir)]);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $metadataConfig = Config::fromFile($baseDir.'/config/metadata.php');

    $secureCookie = true;
    if ($config->has('secureCookie')) {
        $secureCookie = $config->get('secureCookie');
    }

    $session = new Session(
        [],
        new Cookie(
            [
               'SameSite' => 'Lax',
               'Secure' => $secureCookie,
            ]
        )
    );

    $request = new Request($_SERVER, $_GET, $_POST);
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

    \libxml_disable_entity_loader(true);
    $dom = new DOMDocument();
    $dom->loadXML($samlRequest, LIBXML_NONET | LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_COMPACT);
    foreach ($dom->childNodes as $child) {
        if (XML_DOCUMENT_TYPE_NODE === $child->nodeType) {
            throw new \InvalidArgumentException(
                'Invalid XML: Detected use of illegal DOCTYPE'
            );
        }
    }

    \libxml_disable_entity_loader(false);
    if (false === $dom->schemaValidate(\sprintf('%s/schema/saml-schema-protocol-2.0.xsd', $baseDir))) {
        throw new Exception('AuthnRequest schema validation failed');
    }
    \libxml_disable_entity_loader(true);

    // XXX validate it actually is an AuthnRequest!
    $authnRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'AuthnRequest')->item(0);
    $authnRequestId = $authnRequest->getAttribute('ID');
    $forceAuthn = $authnRequest->getAttribute('ForceAuthn');
    if ('true' === $forceAuthn || '1' === $forceAuthn) {
        // force authentication of the user
        $session->delete('userInfo');
        // auth
        echo $tpl->render('auth');
        exit(0);
    }

    $userInfo = $session->get('userInfo');

    $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
    // XXX make sure we are the audience
    $spConfig = $metadataConfig->get($spEntityId);
    $authnRequestAcsUrl = $spConfig->get('acsUrl');

    $samlResponse = new SAMLResponse(
        $tpl,
        Key::fromFile($baseDir.'/config/server.key'),
        Certificate::fromFile($baseDir.'/config/server.crt')
    );

    // add common attributes
    if ($spConfig->has('staticAttributeList')) {
        $staticAttributeList = $spConfig->get('staticAttributeList')->toArray();
        foreach ($staticAttributeList as $k => $v) {
            $samlResponse->setAttribute($k, $v);
        }
    }

    $secretSalt = $config->get('secretSalt');
    if ('__REPLACE_ME__' === $secretSalt) {
        throw new Exception('"secretSalt" not configured');
    }

    $identifierSourceAttribute = $config->get('identifierSourceAttribute');

    $persistentId = Base64UrlSafe::encodeUnpadded(
        \hash(
            'sha256',
            \sprintf('%s|%s|%s|%s', $secretSalt, $identifierSourceAttribute, $idpEntityId, $spEntityId),
            true
        )
    );
    $samlResponse->setAttribute('urn:oid:1.3.6.1.4.1.5923.1.1.1.10', [$persistentId]);
    $samlResponse->setAttribute(
        'urn:oasis:names:tc:SAML:attribute:pairwise-id',
        [
            \sprintf('%s@%s', $persistentId, $config->get('identifierScope')),
        ]
    );
    foreach ($userInfo->getAttributes() as $k => $v) {
        $samlResponse->setAttribute($k, $v);
    }

    $transientNameId = Base64UrlSafe::encodeUnpadded(\random_bytes(32));
    $session->set($spEntityId, ['transientNameId' => $transientNameId]);

    $responseXml = $samlResponse->getAssertion($spConfig, $spEntityId, $idpEntityId, $authnRequestId, $transientNameId);
//    \error_log($responseXml);

    echo $tpl->render(
        'submit', [
            'spEntityId' => $spEntityId,
            'relayState' => $relayState,
            'acsUrl' => $authnRequestAcsUrl,
            'samlResponse' => Base64::encode($responseXml),
            // XXX somehow improve this so it does not have to come from the object
            'attributeList' => $samlResponse->getAttributeList($spConfig),
        ]
    );
} catch (Exception $e) {
    echo $tpl->render('error', ['errorCode' => 500, 'errorMessage' => $e->getMessage()]);
}
