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

use DateInterval;
use DateTime;
use DateTimeZone;
use fkooman\SAML\IdP\Http\Exception\HttpException;
use fkooman\SAML\IdP\Http\HtmlResponse;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Http\Response;
use fkooman\SeCookie\SessionInterface;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

class Service
{
    /** @var Key */
    private $samlKey;

    /** @var Certificate */
    private $samlCert;

    /** @var Config */
    private $config;

    /** @var Config */
    private $metadataConfig;

    /** @var \fkooman\SeCookie\SessionInterface */
    private $session;

    /** @var Template */
    private $tpl;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param string $baseDir
     */
    public function __construct(Config $config, Config $metadataConfig, SessionInterface $session, Template $tpl, Key $samlKey, Certificate $samlCert)
    {
        $this->config = $config;
        $this->metadataConfig = $metadataConfig;
        $this->session = $session;
        $this->tpl = $tpl;
        $this->samlKey = $samlKey;
        $this->samlCert = $samlCert;
        $this->dateTime = new DateTime();
        $this->dateTime->setTimeZone(new DateTimeZone('UTC'));
    }

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    public function run(Request $request)
    {
        try {
            switch ($request->getMethod()) {
                case 'GET':
                case 'HEAD':
                    switch ($request->getPathInfo()) {
                        case '/metadata':
                            return $this->getMetadata($request);
                        case '/sso':
                            if (false === $this->isAuthenticated($request)) {
                                return new HtmlResponse(
                                    $this->tpl->render('auth')
                                );
                            }

                            return $this->processSso($request);
                        case '/slo':
                            if (false === $this->isAuthenticated($request)) {
                                return new HtmlResponse(
                                    $this->tpl->render('auth')
                                );
                            }

                            return $this->processSlo($request);
                        default:
                            throw new HttpException('page not found', 404);
                    }

                    break;
                case 'POST':
                    switch ($request->getPathInfo()) {
                        case '/sso':
                            $this->handleAuth($request);

                            return $this->processSso($request);
                        default:
                            throw new HttpException('page not found', 404);
                    }

                    break;
                default:
                    $e = new HttpException('invalid method', 405);
                    $e->setHeaders(['Allow' => 'HEAD,GET,POST']);

                    throw $e;
            }
        } catch (HttpException $e) {
            return new HtmlResponse(
                $this->tpl->render('error', ['errorCode' => $e->getCode(), 'errorMessage' => $e->getMessage()]),
                $e->getHeaders(),
                $e->getCode()
            );
        }
    }

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    private function getMetadata(Request $request)
    {
        $entityId = $request->getRootUri().'metadata';
        $ssoUri = $request->getRootUri().'sso';
        $sloUri = $request->getRootUri().'slo';
        $keyInfo = $this->samlCert->toKeyInfo();

        $validUntil = date_add(clone $this->dateTime, new DateInterval('PT24H'));

        $metaDataDocument = $this->tpl->render(
            'metadata',
            [
                'entityId' => $entityId,
                'keyInfo' => $keyInfo,
                'ssoUri' => $ssoUri,
                'sloUri' => $sloUri,
                'displayNameList' => $this->config->get('metaData')->get('displayNameList')->toArray(),
                'logoList' => $this->config->get('metaData')->get('logoList')->toArray(),
                'informationUrlList' => $this->config->get('metaData')->get('informationUrlList')->toArray(),
                'technicalContact' => $this->config->get('metaData')->get('technicalContact'),
                'identifierScope' => $this->config->get('identifierScope'),
                'validUntil' => $validUntil->format('Y-m-d\TH:i:s\Z'),
            ]
        );

        return new Response(
            $metaDataDocument,
            ['Content-Type' => 'application/samlmetadata+xml']
        );
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
        $userAuthMethod = new SimpleAuth($this->config->get('SimpleAuth'));

        // set session crap
        // XXX failing auth throws exception?
        $this->session->set('userInfo', $userAuthMethod->authenticate($request->getPostParameter('authUser'), $request->getPostParameter('authPass')));
        $this->session->regenerate(true);
    }

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    private function processSso(Request $request)
    {
        $userAttributeList = [];
        $idpEntityId = $request->getRootUri().'metadata';

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
                throw new RuntimeException('signature invalid');
            }
        }

        $authnRequestAcsUrl = $spConfig->get('acsUrl');

        $samlResponse = new SAMLResponse(
            $this->tpl,
            $this->samlKey,
            $this->samlCert
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
            throw new RuntimeException('"secretSalt" not configured');
        }

        $identifierSourceAttribute = $this->config->get('identifierSourceAttribute');
        $persistentId = Base64UrlSafe::encodeUnpadded(
            hash(
                'sha256',
                sprintf('%s|%s|%s|%s', $secretSalt, $identifierSourceAttribute, $idpEntityId, $spEntityId),
                true
            )
        );

        $samlResponse->setAttribute('eduPersonTargetedID', [$persistentId]);
        $samlResponse->setAttribute(
            'pairwise-id',
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

        $samlResponse->setAttribute('eduPersonTargetedID', [$persistentId]);
        $samlResponse->setAttribute(
            'pairwise-id',
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

    /**
     * @return \fkooman\SAML\IdP\Http\Response
     */
    private function processSlo(Request $request)
    {
        // XXX we do NOT verify the signature here, we MUST according to saml2int spec

        // XXX input validation of everything
        $samlRequest = gzinflate(Base64::decode($request->getQueryParameter('SAMLRequest'), true));

        $requestDocument = XmlDocument::fromProtocolMessage($samlRequest);

        $logoutRequestElement = XmlDocument::requireDomElement($requestDocument->domXPath->query('/samlp:LogoutRequest')->item(0));

        // XXX validate it actually is an LogoutRequest!
//        $logoutRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'LogoutRequest')->item(0);
        $logoutRequestId = $logoutRequestElement->getAttribute('ID');

        // XXX do we need to validate the signature?! mod_auth_mellon adds a signature it seems... check saml2int

        $issuerElement = XmlDocument::requireDomElement($requestDocument->domXPath->query('/samlp:LogoutRequest/saml:Issuer')->item(0));
        $spEntityId = $issuerElement->textContent;

        // 2. verify if we know the issuer <saml:Issuer>, i.e. is an existing entityID in the metadata
//        $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
        if (false === $this->metadataConfig->has($spEntityId)) {
            throw new RuntimeException('SP not registered here');
        }

        $spConfig = $this->metadataConfig->get($spEntityId);

        // do we have a signing key for this SP?
        // maybe it is good enough to enforce signature checking iff we have a
        // public key for the SP...
        if ($spConfig->has('signingKey')) {
            $signingKey = $spConfig->get('signingKey');
            $sigAlg = $request->getQueryParameter('SigAlg');
            $signature = Base64::decode($request->getQueryParameter('Signature'));

            $httpQuery = http_build_query(
                [
                    'SAMLRequest' => $request->getQueryParameter('SAMLRequest'),
                    'RelayState' => $request->getQueryParameter('RelayState'),
                    'SigAlg' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                ]
            );
            $rsaKey = new Key($signingKey);
            if (1 !== openssl_verify($httpQuery, $signature, $rsaKey->getPublicKey(), OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('signature invalid');
            }
        }

        // 1. verify "Destination"
        $ourSlo = $request->getRootUri().'slo';
        $ourEntityId = $request->getRootUri().'metadata';

        $destSlo = $logoutRequestElement->getAttribute('Destination');

        if (false === hash_equals($ourSlo, $destSlo)) {
            throw new RuntimeException('specified destination is not our destination');
        }

        // 3. see if we the transient ID provided is also set in the user's session for
        //    this SP
        if (false === $this->session->has($spEntityId)) {
            throw new RuntimeException('no session for this SP');
        }
//        $logoutRequestTransientNameId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'NameID')->item(0)->nodeValue;

        $nameIdElement = XmlDocument::requireDomElement($requestDocument->domXPath->query('/samlp:LogoutRequest/saml:NameID')->item(0));
        $nameIdValue = $nameIdElement->textContent;

        // XXX make sure the attributes are correct, i.e. SPNameQualifier, Format
        $transientNameId = $this->session->get($spEntityId)['transientNameId'];
        if (false === hash_equals($transientNameId, $nameIdValue)) {
            throw new RuntimeException('provided transient NameID does not match expected value');
        }

        // 4. XXX do something with sessionindex?!

        // 5. kill the user's session (?)
        $this->session->destroy();

        // XXX all seems to be fine at this point, log the user out, and send a
        // LogoutResponse

        $sloUrl = $this->metadataConfig->get($spEntityId)->get('sloUrl');

        $responseXml = $this->tpl->render(
            'logoutResponse',
            [
                'id' => '_'.bin2hex(random_bytes(32)),
                'issueInstant' => $this->dateTime->format('Y-m-d\TH:i:s\Z'),
                'destination' => $sloUrl,
                'inResponseTo' => $logoutRequestId,
                'issuer' => $ourEntityId,
            ]
        );

        $httpQuery = http_build_query(
            [
                'SAMLResponse' => Base64::encode(gzdeflate($responseXml)),
                'RelayState' => $request->getQueryParameter('RelayState'),
                'SigAlg' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            ]
        );

        // calculate the signature over httpQuery
        // add it to the query string
        openssl_sign(
            $httpQuery,
            $signedInfoSignature,
            $this->samlKey->getPrivateKey(),
            OPENSSL_ALGO_SHA256
        );

        $httpQuery .= '&'.http_build_query(
            [
                'Signature' => Base64::encode($signedInfoSignature),
            ]
        );

        // XXX make sure it does not already have a "?" in the SLO URL!
        $sloUrl = $sloUrl.'?'.$httpQuery;

        return new Response(
            '',
            ['Location' => $sloUrl],
            302
        );
    }
}
