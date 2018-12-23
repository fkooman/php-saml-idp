<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="<?=$this->e($id); ?>" Version="2.0" IssueInstant="<?=$this->e($issueInstant); ?>" Destination="<?=$this->e($destination); ?>" InResponseTo="<?=$this->e($inResponseTo); ?>">
    <saml:Issuer><?=$this->e($issuer); ?></saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
    </samlp:Status>
</samlp:LogoutResponse>
