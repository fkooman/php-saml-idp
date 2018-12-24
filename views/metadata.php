<md:EntityDescriptor validUntil="<?=$this->e($validUntil); ?>" xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui" xmlns:shibmd="urn:mace:shibboleth:metadata:1.0" entityID="<?=$this->e($entityId); ?>">
    <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:Extensions>
            <mdui:UIInfo>
<?php foreach ($displayNameList as $langCode => $displayName): ?>
                <mdui:DisplayName xml:lang="<?=$this->e($langCode); ?>"><?=$this->e($displayName); ?></mdui:DisplayName>
<?php endforeach; ?>
<?php foreach ($logoList as $langCode => $logoData): ?>
                <mdui:Logo xml:lang="<?=$this->e($langCode); ?>" width="<?=$this->e($logoData['logoWidth']); ?>" height="<?=$this->e($logoData['logoHeight']); ?>"><?=$this->e($logoData['logoUri']); ?></mdui:Logo>
<?php endforeach; ?>
<?php foreach ($informationUrlList as $langCode => $informationUrl): ?>
                <mdui:InformationURL xml:lang="<?=$this->e($langCode); ?>"><?=$this->e($informationUrl); ?></mdui:InformationURL>
<?php endforeach; ?>
            </mdui:UIInfo>
            <shibmd:Scope regexp="false"><?=$this->e($identifierScope); ?></shibmd:Scope>
        </md:Extensions>
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate><?=$this->e($keyInfo); ?></ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:transient</md:NameIDFormat>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="<?=$this->e($ssoUri); ?>"/>
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="<?=$this->e($sloUri); ?>"/>
    </md:IDPSSODescriptor>
    <md:ContactPerson contactType="technical">
      <md:EmailAddress><?=$this->e($technicalContact); ?></md:EmailAddress>
    </md:ContactPerson>
</md:EntityDescriptor>
