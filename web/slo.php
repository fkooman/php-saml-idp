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

use fkooman\SAML\IdP\Config;
use fkooman\SAML\IdP\ErrorHandler;
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Http\Response;
use fkooman\SAML\IdP\Template;
use fkooman\SeCookie\Cookie;
use fkooman\SeCookie\Session;
use ParagonIE\ConstantTime\Base64;

ErrorHandler::register();

$baseDir = \dirname(__DIR__);

$tpl = new Template([\sprintf('%s/views', $baseDir)]);

try {
    /*
    <?xml version="1.0"?>
    <samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_491736BBB8E483BE21C48F6967802ED3" Version="2.0" IssueInstant="2018-12-22T09:43:00Z" Destination="https://idp.tuxed.net/slo.php">
      <saml:Issuer>https://labrat.eduvpn.nl/saml</saml:Issuer>
      <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:transient" SPNameQualifier="https://labrat.eduvpn.nl/saml">1WyrC4rQnDlP-E_iQ9kWTlyF46_2uSM3IccPnQE8CxE</saml:NameID>
      <samlp:SessionIndex>_fb98f78d5b25be2791ab19975e9d3b2e</samlp:SessionIndex>
    </samlp:LogoutRequest>
    */

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

//    echo '<pre>';
//    \var_dump($_SESSION);
//    echo '</pre>';

    $request = new Request($_SERVER, $_GET, $_POST);

    // XXX input validation of everything
    $samlRequest = \gzinflate(Base64::decode($request->getQueryParameter('SAMLRequest'), true));
    // XXX we need to do anything with RelayState here?!
    //$relayState = $request->hasQueryParameter('RelayState') ? $request->getQueryParameter('RelayState') : null;

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
        throw new Exception('LogoutRequest schema validation failed');
    }
    \libxml_disable_entity_loader(true);

    // XXX validate it actually is an LogoutRequest!
    $logoutRequest = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'LogoutRequest')->item(0);
    $logoutRequestId = $logoutRequest->getAttribute('ID');

    // XXX do we need to validate the signature?! mod_auth_mellon adds a signature it seems... check saml2int

    // 1. verify "Destination"
    $ourSlo = $request->getRootUri().'slo.php';
    $ourEntityId = $request->getRootUri().'metadata.php';

    $destSlo = $logoutRequest->getAttribute('Destination');
    if (false === \hash_equals($ourSlo, $destSlo)) {
        throw new Exception('specified destination is not our destination');
    }

    // 2. verify if we know the issuer <saml:Issuer>, i.e. is an existing entityID in the metadata
    $spEntityId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer')->item(0)->nodeValue;
    if (false === $metadataConfig->has($spEntityId)) {
        throw new Exception('SP not registered here');
    }

    // 3. see if we the transient ID provided is also set in the user's session for
    //    this SP
    if (false === $session->has($spEntityId)) {
        throw new Exception('no session for this SP');
    }
    $logoutRequestTransientNameId = $dom->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'NameID')->item(0)->nodeValue;
    // XXX make sure the attributes are correct, i.e. SPNameQualifier, Format
    $transientNameId = $session->get($spEntityId)['transientNameId'];
    if (false === \hash_equals($transientNameId, $logoutRequestTransientNameId)) {
        throw new Exception('provided transient NameID does not match expected value');
    }

    // 4. XXX do something with sessionindex?!

    // 5. kill the user's session (?)
    $session->destroy();

    // XXX all seems to be fine at this point, log the user out, and send a
    // LogoutResponse

    $sloUrl = $metadataConfig->get($spEntityId)->get('sloUrl');

    $dateTime = new DateTime();
    $responseXml = $tpl->render(
        'logoutResponse',
        [
            'id' => '_'.\bin2hex(\random_bytes(32)),
            'issueInstant' => $dateTime->format('Y-m-d\TH:i:s\Z'),
            'destination' => $sloUrl,
            'inResponseTo' => $logoutRequestId,
            'issuer' => $ourEntityId,
        ]
    );

    $httpQuery = \http_build_query(
        [
            'SAMLRequest' => Base64::encode(\gzdeflate($responseXml)),
            'RelayState' => $request->getQueryParameter('RelayState'),
        ]
    );
    // XXX make sure it does not already have a "?" in the SLO URL!
    $sloUrl = $sloUrl.'?'.$httpQuery;

    $response = new Response(302);
    $response->setHeader('Location', $sloUrl);
    $response->send();
} catch (Exception $e) {
    echo $tpl->render('error', ['errorCode' => 500, 'errorMessage' => $e->getMessage()]);
}
