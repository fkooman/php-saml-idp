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
use DOMDocument;
use ParagonIE\ConstantTime\Base64;

class SAMLResponse
{
    /** @var Template */
    private $tpl;

    /** @var Key */
    private $rsaKey;

    /** @var Certificate */
    private $rsaCert;

    /** @var \DateTime */
    private $dateTime;

    /** @var array<string, array<string>> */
    private $attributeList = [];

    public function __construct(Template $tpl, Key $rsaKey, Certificate $rsaCert)
    {
        $this->tpl = $tpl;
        $this->rsaKey = $rsaKey;
        $this->rsaCert = $rsaCert;
        $this->dateTime = new DateTime();
        $this->dateTime->setTimeZone(new DateTimeZone('UTC'));
    }

    /**
     * @param string        $attributeName
     * @param array<string> $attributeValueList
     *
     * @return void
     */
    public function setAttribute($attributeName, array $attributeValueList)
    {
        $this->attributeList[$attributeName] = $attributeValueList;
    }

    /**
     * @return array<string,array<string>>
     */
    public function getAttributeList(Config $spConfig)
    {
        return $this->prepareAttributes(
            $spConfig->has('attributeRelease') ? $spConfig->get('attributeRelease')->toArray() : [],
            $spConfig->has('attributeMapping') ? $spConfig->get('attributeMapping')->toArray() : []
        );
    }

    /**
     * @param Config $spConfig
     * @param string $spEntityId
     * @param string $idpEntityId
     * @param string $id
     * @param string $transientNameId
     *
     * @return string
     */
    public function getAssertion(Config $spConfig, $spEntityId, $idpEntityId, $id, $transientNameId)
    {
        $responseId = '_'.bin2hex(random_bytes(32));
        $assertionId = '_'.bin2hex(random_bytes(32));
        $destinationAcs = $spConfig->get('acsUrl');
        $inResponseTo = $id;
        $issueInstant = $this->dateTime->format('Y-m-d\TH:i:s\Z');
        $assertionIssuer = $idpEntityId;
        $notBefore = $this->dateTime->sub(new DateInterval('PT3M'))->format('Y-m-d\TH:i:s\Z');
        $notOnOrAfter = $this->dateTime->add(new DateInterval('PT6M'))->format('Y-m-d\TH:i:s\Z');
        $sessionNotOnOrAfter = $this->dateTime->add(new DateInterval('PT8H'))->format('Y-m-d\TH:i:s\Z');
        $assertionAudience = $spEntityId;
        $sessionIndex = '_'.bin2hex(random_bytes(16));
        $x509Certificate = $this->rsaCert->toKeyInfo();

        $responseDocument = $this->tpl->render(
            'response',
            [
                'responseId' => $responseId,
                'sessionIndex' => $sessionIndex,
                'transientNameId' => $transientNameId,
                'destinationAcs' => $destinationAcs,
                'inResponseTo' => $inResponseTo,
                'assertionId' => $assertionId,
                'issueInstant' => $issueInstant,
                'assertionIssuer' => $assertionIssuer,
                'notBefore' => $notBefore,
                'noOnOrAfter' => $notOnOrAfter,
                'sessionNotOnOrAfter' => $sessionNotOnOrAfter,
                'assertionAudience' => $assertionAudience,
                'x509Certificate' => $x509Certificate,
                'attributeList' => $this->prepareAttributes(
                    $spConfig->has('attributeRelease') ? $spConfig->get('attributeRelease')->toArray() : [],
                    $spConfig->has('attributeMapping') ? $spConfig->get('attributeMapping')->toArray() : []
                ),
            ]
        );

        $responseDomDocument = new DOMDocument();
        $responseDomDocument->loadXML($responseDocument);
        $responseDomDocumentClone = clone $responseDomDocument;
        $responseElement = $responseDomDocumentClone->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:protocol', 'Response')->item(0);
        $signatureElement = $responseDomDocumentClone->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
        $responseElement->removeChild($signatureElement);

        $digestValue = Base64::encode(
            hash(
                'sha256',
                $responseElement->C14N(true, false),
                true
            )
        );

        $digestValueElement = $responseDomDocument->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'DigestValue')->item(0);
        $digestValueElement->appendChild($responseDomDocument->createTextNode($digestValue));
        $signedInfoElement = $responseDomDocument->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignedInfo')->item(0);
        openssl_sign(
            $signedInfoElement->C14N(true, false),
            $signedInfoSignature,
            $this->rsaKey->getPrivateKey(),
            OPENSSL_ALGO_SHA256
        );
        $signatureValueElement = $responseDomDocument->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignatureValue')->item(0);
        $signatureValueElement->appendChild($responseDomDocument->createTextNode(Base64::encode($signedInfoSignature)));

        return $responseDomDocument->saveXML();
    }

    /**
     * @return array<string,string>
     */
    public static function getAttributeMapping()
    {
        return [
            'urn:oid:0.9.2342.19200300.100.1.1' => 'uid',
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.7' => 'eduPersonEntitlement',
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.10' => 'eduPersonTargetedID',
            'urn:oid:0.9.2342.19200300.100.1.3' => 'mail',
            'urn:oid:2.16.840.1.113730.3.1.241' => 'displayName',
            'urn:oid:2.5.4.42' => 'givenName',
            'urn:oid:2.5.4.4' => 'sn',
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6' => 'eduPersonPrincipalName',
        ];
    }

    /**
     * @param array<string> $attributeReleaseList
     * @param array<string> $attributeMapping
     *
     * @return array<string,array<string>>
     */
    private function prepareAttributes(array $attributeReleaseList, array $attributeMapping)
    {
        // apply mapping
        foreach ($attributeMapping as $k => $v) {
            if (\array_key_exists($k, $this->attributeList)) {
                $this->attributeList[$v] = $this->attributeList[$k];
            }
        }

        // only release the attributes we want to expose
        $filteredAttributeList = [];
        foreach ($this->attributeList as $k => $v) {
            if (\in_array($k, $attributeReleaseList, true)) {
                $filteredAttributeList[$k] = $v;
            }
        }

        return $filteredAttributeList;
    }
}
