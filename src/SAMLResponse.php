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

namespace fkooman\SAML\IdP;

use DateInterval;
use DateTime;
use DOMDocument;
use ParagonIE\ConstantTime\Base64;

class SAMLResponse
{
    /** @var Key */
    private $rsaKey;

    /** @var Certificate */
    private $rsaCert;

    /** @var \DateTime */
    private $dateTime;

    /** @var array<string, array<string>> */
    private $attributeList = [];

    /** @var null|string $persistentId */
    private $persistentId = null;

    public function __construct(Key $rsaKey, Certificate $rsaCert)
    {
        $this->rsaKey = $rsaKey;
        $this->rsaCert = $rsaCert;
        $this->dateTime = new DateTime();
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
     * @param Config $spConfig
     * @param string $spEntityId
     * @param string $idpEntityId
     * @param string $id
     *
     * @return string
     */
    public function getAssertion(Config $spConfig, $spEntityId, $idpEntityId, $id)
    {
        $responseId = '_'.\bin2hex(\random_bytes(16));
        $assertionId = '_'.\bin2hex(\random_bytes(16));
        $destinationAcs = $spConfig->get('AssertionConsumerServiceURL');
        $transientNameId = '_'.\bin2hex(\random_bytes(16));
        $inResponseTo = $id;
        $issueInstant = $this->dateTime->format('Y-m-d\TH:i:s\Z');
        $assertionIssuer = $idpEntityId;
        $notBefore = $this->dateTime->sub(new DateInterval('PT3M'))->format('Y-m-d\TH:i:s\Z');
        $notOnOrAfter = $this->dateTime->add(new DateInterval('PT6M'))->format('Y-m-d\TH:i:s\Z');
        $assertionAudience = $spEntityId;
        $sessionIndex = '_'.\bin2hex(\random_bytes(16));
        $x509Certificate = $this->rsaCert->toKeyInfo();
        $responseTemplate = <<< EOF
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="{{RESPONSE_ID}}" Version="2.0" IssueInstant="{{ISSUE_INSTANT}}" Destination="{{DESTINATION_ACS}}" InResponseTo="{{IN_RESPONSE_TO}}">
    <saml:Issuer>{{ASSERTION_ISSUER}}</saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success" />
    </samlp:Status>
    <saml:Assertion xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="{{ASSERTION_ID}}" Version="2.0" IssueInstant="{{ISSUE_INSTANT}}">
        <saml:Issuer>{{ASSERTION_ISSUER}}</saml:Issuer>
        <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <ds:SignedInfo>
                <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
                <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
                 <ds:Reference URI="#{{ASSERTION_ID}}">
                    <ds:Transforms>
                        <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
                        <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
                    </ds:Transforms>
                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                    <ds:DigestValue />
                </ds:Reference>
            </ds:SignedInfo>
            <ds:SignatureValue />
            <ds:KeyInfo>
                <ds:X509Data>
                    <ds:X509Certificate>{{X509_CERTIFICATE}}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </ds:Signature>
        <saml:Subject>
            <saml:NameID SPNameQualifier="{{ASSERTION_AUDIENCE}}" Format="urn:oasis:names:tc:SAML:2.0:nameid-format:transient">{{TRANSIENT_NAME_ID}}</saml:NameID>
            <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                <saml:SubjectConfirmationData NotOnOrAfter="{{NOT_ON_OR_AFTER}}" Recipient="{{DESTINATION_ACS}}" InResponseTo="{{IN_RESPONSE_TO}}" />
            </saml:SubjectConfirmation>
        </saml:Subject>
        <saml:Conditions NotBefore="{{NOT_BEFORE}}" NotOnOrAfter="{{NOT_ON_OR_AFTER}}">
            <saml:AudienceRestriction>
                <saml:Audience>{{ASSERTION_AUDIENCE}}</saml:Audience>
            </saml:AudienceRestriction>
        </saml:Conditions>
        <saml:AuthnStatement AuthnInstant="{{ISSUE_INSTANT}}" SessionNotOnOrAfter="{{NOT_ON_OR_AFTER}}" SessionIndex="{{SESSION_INDEX}}">
            <saml:AuthnContext>
                <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef>
            </saml:AuthnContext>
        </saml:AuthnStatement>{{ATTRIBUTES}}
    </saml:Assertion>
</samlp:Response>
EOF;
        $responseDocument = \str_replace(
            [
                '{{RESPONSE_ID}}',
                '{{SESSION_INDEX}}',
                '{{TRANSIENT_NAME_ID}}',
                '{{DESTINATION_ACS}}',
                '{{IN_RESPONSE_TO}}',
                '{{ASSERTION_ID}}',
                '{{ISSUE_INSTANT}}',
                '{{ASSERTION_ISSUER}}',
                '{{NOT_BEFORE}}',
                '{{NOT_ON_OR_AFTER}}',
                '{{ASSERTION_AUDIENCE}}',
                '{{X509_CERTIFICATE}}',
                '{{ATTRIBUTES}}',
            ],
            [
                $responseId,
                $sessionIndex,
                $transientNameId,
                $destinationAcs,
                $inResponseTo,
                $assertionId,
                $issueInstant,
                $assertionIssuer,
                $notBefore,
                $notOnOrAfter,
                $assertionAudience,
                $x509Certificate,
                $this->prepareAttributes($assertionIssuer, $assertionAudience, $spConfig->get('attributeReleasePolicy'), $spConfig->get('attributeMapping')->toArray()),
            ],
            $responseTemplate
        );

        $responseDomDocument = new DOMDocument();
        $responseDomDocument->loadXML($responseDocument);
        $responseDomDocumentClone = clone $responseDomDocument;
        $assertionElement = $responseDomDocumentClone->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);
        $signatureElement = $responseDomDocumentClone->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
        $assertionElement->removeChild($signatureElement);

        $digestValue = Base64::encode(
            \hash(
                'sha256',
                $assertionElement->C14N(true, false),
                true
            )
        );

        $digestValueElement = $responseDomDocument->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'DigestValue')->item(0);
        $digestValueElement->appendChild($responseDomDocument->createTextNode($digestValue));
        $signedInfoElement = $responseDomDocument->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignedInfo')->item(0);
        \openssl_sign(
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
     * @param string $persistentId
     *
     * @return void
     */
    public function setPersistentId($persistentId)
    {
        $this->persistentId = $persistentId;
    }

    /**
     * @param string        $assertionIssuer
     * @param string        $assertionAudience
     * @param array<string> $attributeReleaseList
     * @param array<string> $attributeMapping
     *
     * @return string
     */
    private function prepareAttributes($assertionIssuer, $assertionAudience, array $attributeReleaseList, array $attributeMapping)
    {
        $filteredAttributeList = [];
        foreach ($this->attributeList as $k => $v) {
            if (\in_array($k, $attributeReleaseList, true)) {
                $filteredAttributeList[$k] = $v;
            }
        }

        foreach ($attributeMapping as $k => $v) {
            if (\array_key_exists($k, $filteredAttributeList)) {
                $filteredAttributeList[$k] = $filteredAttributeList[$v] = $filteredAttributeList[$k];
            }
        }

        if (0 === \count($filteredAttributeList)) {
            return '';
        }

        $output = '<saml:AttributeStatement>';

        if (null !== $this->persistentId) {
            // add EPTI
            $eduPersonTargetedIdTemplate = <<< EOF
<saml:Attribute Name="urn:oid:1.3.6.1.4.1.5923.1.1.1.10" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
    <saml:AttributeValue>
        <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" NameQualifier="{{ASSERTION_ISSUER}}" SPNameQualifier="{{ASSERTION_AUDIENCE}}">{{PERSISTENT_ID}}</saml:NameID>
    </saml:AttributeValue>
</saml:Attribute>
EOF;

            $output .= \str_replace(
                [
                    '{{ASSERTION_ISSUER}}',
                    '{{ASSERTION_AUDIENCE}}',
                    '{{PERSISTENT_ID}}',
                ],
                [
                    $assertionIssuer,
                    $assertionAudience,
                    $this->persistentId,
                ],
                $eduPersonTargetedIdTemplate
            );
        }

        foreach ($filteredAttributeList as $attributeName => $attributeValueList) {
            $attributeValueTemplate = <<< EOF
        <saml:AttributeValue xsi:type="xs:string">{{ATTRIBUTE_VALUE}}</saml:AttributeValue>
EOF;

            $attributeTemplate = <<< EOF
    <saml:Attribute Name="{{ATTRIBUTE_NAME}}" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
        {{ATTRIBUTE_VALUES}}
    </saml:Attribute>
EOF;

            $attributeValueListStr = '';
            foreach ($attributeValueList as $attributeValue) {
                $attributeValueListStr .= \str_replace('{{ATTRIBUTE_VALUE}}', $attributeValue, $attributeValueTemplate);
            }

            $output .= \str_replace(
                [
                    '{{ATTRIBUTE_NAME}}',
                    '{{ATTRIBUTE_VALUES}}',
                ],
                [
                    $attributeName,
                    $attributeValueListStr,
                ],
                $attributeTemplate
            );
        }

        $output .= '</saml:AttributeStatement>';

        return $output;
    }
}
