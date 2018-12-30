<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="<?=$this->e($responseId); ?>" Version="2.0" IssueInstant="<?=$this->e($issueInstant); ?>" Destination="<?=$this->e($destinationAcs); ?>" InResponseTo="<?=$this->e($inResponseTo); ?>">
    <saml:Issuer><?=$this->e($assertionIssuer); ?></saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success" />
    </samlp:Status>
    <saml:Assertion xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="<?=$this->e($assertionId); ?>" Version="2.0" IssueInstant="<?=$this->e($issueInstant); ?>">
        <saml:Issuer><?=$this->e($assertionIssuer); ?></saml:Issuer>
        <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <ds:SignedInfo>
                <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
                <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
                <ds:Reference URI="#<?=$this->e($assertionId); ?>">
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
                    <ds:X509Certificate><?=$this->e($x509Certificate); ?></ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </ds:Signature>
        <saml:Subject>
            <saml:NameID SPNameQualifier="<?=$this->e($assertionAudience); ?>" Format="urn:oasis:names:tc:SAML:2.0:nameid-format:transient"><?=$this->e($transientNameId); ?></saml:NameID>
            <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                <saml:SubjectConfirmationData NotOnOrAfter="<?=$this->e($noOnOrAfter); ?>" Recipient="<?=$this->e($destinationAcs); ?>" InResponseTo="<?=$this->e($inResponseTo); ?>" />
            </saml:SubjectConfirmation>
        </saml:Subject>
        <saml:Conditions NotBefore="<?=$this->e($notBefore); ?>" NotOnOrAfter="<?=$this->e($noOnOrAfter); ?>">
            <saml:AudienceRestriction>
                <saml:Audience><?=$this->e($assertionAudience); ?></saml:Audience>
            </saml:AudienceRestriction>
        </saml:Conditions>
        <saml:AuthnStatement AuthnInstant="<?=$this->e($issueInstant); ?>" SessionNotOnOrAfter="<?=$this->e($sessionNotOnOrAfter); ?>" SessionIndex="<?=$this->e($sessionIndex); ?>">
            <saml:AuthnContext>
                <saml:AuthnContextClassRef>http://test.surfconext.nl/assurance/loa1</saml:AuthnContextClassRef>
            </saml:AuthnContext>
        </saml:AuthnStatement>
<?php if (0 !== \count($attributeList)): ?>
        <saml:AttributeStatement>
<?php foreach ($attributeList as $attributeName => $attributeValueList): ?>
            <saml:Attribute Name="<?=$this->e($attributeName); ?>" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
<?php foreach ($attributeValueList as $attributeValue): ?>
<?php if ('urn:oid:1.3.6.1.4.1.5923.1.1.1.10' === $attributeName): ?>
                <saml:AttributeValue>
                    <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" NameQualifier="<?=$this->e($assertionIssuer); ?>" SPNameQualifier="<?=$this->e($assertionAudience); ?>"><?=$this->batch($attributeValue, 'strip_tags'); ?></saml:NameID>
                </saml:AttributeValue>
<?php else: ?>
                <saml:AttributeValue xsi:type="xs:string"><?=$this->batch($attributeValue, 'strip_tags'); ?></saml:AttributeValue>
<?php endif; ?>
<?php endforeach; ?>
            </saml:Attribute>
<?php endforeach; ?>
        </saml:AttributeStatement>
<?php endif; ?>
    </saml:Assertion>
</samlp:Response>
