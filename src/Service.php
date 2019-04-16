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

namespace fkooman\SAML\IdP;

use Exception;
use fkooman\SAML\IdP\Http\HtmlResponse;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SeCookie\SessionInterface;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;

class Service
{
    /** @var string */
    private $baseDir;

    /** @var Config */
    private $config;

    /** @var Config */
    private $metadataConfig;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var Template */
    private $tpl;

    /**
     * @param string $baseDir
     */
    public function __construct($baseDir, Config $config, Config $metadataConfig, SessionInterface $session, Template $tpl)
    {
        $this->baseDir = $baseDir;
        $this->config = $config;
        $this->metadataConfig = $metadataConfig;
        $this->session = $session;
        $this->tpl = $tpl;
    }

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    public function run(Request $request)
    {
        switch ($request->getMethod()) {
            case 'GET':
            case 'HEAD':
                if (false === $this->isAuthenticated($request)) {
                    return new HtmlResponse(
                        $this->tpl->render('auth')
                    );
                }

                return $this->processRequest($request);
            case 'POST':
//                switch ($request->getPathInfo()) {
//                    case '/auth':
                        $this->handleAuth($request);

                        return $this->processRequest($request);
//                    default:
//                        throw new Exception('404');
//                }

                break;
            default:
                throw new Exception('405');
        }
    }

    /**
     * @return bool
     */
    private function isAuthenticated(Request $request)
    {
        return $this->session->has('userInfo');
    }

    /**
     * @return void
     */
    private function handleAuth(Request $request)
    {
        // determine auth mech
        $authMethod = $this->config->get('authMethod');
        $authMethodClass = '\\fkooman\\SAML\\IdP\\'.ucfirst($authMethod);
        $userAuthMethod = new $authMethodClass($this->config->get($authMethod));

        // set session crap
        // XXX failing auth throws exception?
        $this->session->set('userInfo', $userAuthMethod->authenticate($request->getPostParameter('authUser'), $request->getPostParameter('authPass')));
        $this->session->regenerate(true);
    }

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    private function processRequest(Request $request)
    {
        $userAttributeList = [];
        $idpEntityId = $request->getRootUri().'metadata.php';

        // XXX input validation of everything
        $samlRequest = gzinflate(Base64::decode($request->getQueryParameter('SAMLRequest'), true));
        $relayState = $request->hasQueryParameter('RelayState') ? $request->getQueryParameter('RelayState') : null;

        $requestDocument = XmlDocument::fromProtocolMessage($samlRequest);

        $authnRequestElement = XmlDocument::requireDomElement($requestDocument->domXPath->query('/samlp:AuthnRequest')->item(0));

//        var_dump($authnRequestElement);

        // XXX validate it actually is an AuthnRequest!
//        $authnRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'AuthnRequest')->item(0);
//        $authnRequestId = $authnRequest->getAttribute('ID');

        $authnRequestId = $authnRequestElement->getAttribute('ID');
        // XXX make sure it is a string

        $userInfo = $this->session->get('userInfo');

//        $foo = $requestDocument->domXPath->query('/samlp:AuthnRequest/saml:Issuer');
//        var_dump($foo->item(0));

        $issuerElement = XmlDocument::requireDomElement($requestDocument->domXPath->query('/samlp:AuthnRequest/saml:Issuer')->item(0));

//        var_dump($issuerElement);

        $spEntityId = $issuerElement->textContent;

//        $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
        // XXX make sure we are the audience
        // XXX make sure the SP is registered
        $spConfig = $this->metadataConfig->get($spEntityId);

        // do we have a signing key for this SP?
        // maybe it is good enough to enforce signature checking iff we have a
        // public key for the SP...
        if ($spConfig->has('signingKey')) {
            $signingKey = $spConfig->get('signingKey');
            $sigAlg = $request->getQueryParameter('SigAlg');
            $signature = Base64::decode($request->getQueryParameter('Signature'));

            // XXX we have to get the raw parameters, just like in php-saml-sp
            $httpQuery = http_build_query(
                [
                    'SAMLRequest' => $request->getQueryParameter('SAMLRequest'),
                    'RelayState' => $request->getQueryParameter('RelayState'),
                    'SigAlg' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                ]
            );
            $rsaKey = new Key($signingKey);
            if (1 !== openssl_verify($httpQuery, $signature, $rsaKey->getPublicKey(), OPENSSL_ALGO_SHA256)) {
                throw new Exception('signature invalid');
            }
        }

        $authnRequestAcsUrl = $spConfig->get('acsUrl');

        $samlResponse = new SAMLResponse(
            $this->tpl,
            Key::fromFile($this->baseDir.'/config/server.key'),
            Certificate::fromFile($this->baseDir.'/config/server.crt')
        );

        // add common attributes
        if ($spConfig->has('staticAttributeList')) {
            $staticAttributeList = $spConfig->get('staticAttributeList')->toArray();
            foreach ($staticAttributeList as $k => $v) {
                $samlResponse->setAttribute($k, $v);
            }
        }

        $secretSalt = $this->config->get('secretSalt');
        if ('__REPLACE_ME__' === $secretSalt) {
            throw new Exception('"secretSalt" not configured');
        }

        $identifierSourceAttribute = $this->config->get('identifierSourceAttribute');
        $persistentId = Base64UrlSafe::encodeUnpadded(
            hash(
                'sha256',
                sprintf('%s|%s|%s|%s', $secretSalt, $identifierSourceAttribute, $idpEntityId, $spEntityId),
                true
            )
        );

        $samlResponse->setAttribute('urn:oid:1.3.6.1.4.1.5923.1.1.1.10', [$persistentId]);
        $samlResponse->setAttribute(
            'urn:oasis:names:tc:SAML:attribute:pairwise-id',
            [
                sprintf('%s@%s', $persistentId, $this->config->get('identifierScope')),
            ]
        );
        foreach ($userInfo->getAttributes() as $k => $v) {
            $samlResponse->setAttribute($k, $v);
        }

        $identifierSourceAttributeValue = $userInfo->getAttribute($this->config->get('identifierSourceAttribute'))[0];
        $persistentId = Base64UrlSafe::encodeUnpadded(
            hash(
                'sha256',
                sprintf('%s|%s|%s|%s', $secretSalt, $identifierSourceAttributeValue, $idpEntityId, $spEntityId),
                true
            )
        );

        $eduPersonTargetedId = sprintf('%s!%s!%s', $idpEntityId, $spEntityId, $persistentId);
        error_log($identifierSourceAttributeValue.':'.$eduPersonTargetedId);

        $samlResponse->setAttribute('urn:oid:1.3.6.1.4.1.5923.1.1.1.10', [$persistentId]);
        $samlResponse->setAttribute(
            'urn:oasis:names:tc:SAML:attribute:pairwise-id',
            [
                sprintf('%s@%s', $persistentId, $this->config->get('identifierScope')),
            ]
        );

        $transientNameId = Base64UrlSafe::encodeUnpadded(random_bytes(32));
        $this->session->set($spEntityId, ['transientNameId' => $transientNameId]);

        $displayName = $spEntityId;
        if ($spConfig->has('displayName')) {
            if ($spConfig->get('displayName')->has('en')) {
                $displayName = $spConfig->get('displayName')->get('en');
            }
        }

        $responseXml = $samlResponse->getAssertion($spConfig, $spEntityId, $idpEntityId, $authnRequestId, $transientNameId);

        return new HtmlResponse(
            $this->tpl->render(
                'consent',
                [
                    'spEntityId' => $spEntityId,
                    'displayName' => $displayName,
                    'relayState' => $relayState,
                    'acsUrl' => $authnRequestAcsUrl,
                    'samlResponse' => Base64::encode($responseXml),
                    // XXX somehow improve this so it does not have to come from the object
                    'attributeList' => $samlResponse->getAttributeList($spConfig),
                    'attributeMapping' => SAMLResponse::getAttributeMapping(),
                ]
            )
        );
    }
}
