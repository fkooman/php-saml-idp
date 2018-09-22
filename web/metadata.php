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
use fkooman\SAML\IdP\Http\Request;
use fkooman\SAML\IdP\Http\Response;

$baseDir = \dirname(__DIR__);
$rsaCert = Certificate::fromFile($baseDir.'/config/server.crt');
$keyInfo = $rsaCert->toKeyInfo();

$metaDataTemplate = <<< EOF
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="{{ENTITY_ID}}">
    <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>{{X509_CERTIFICATE}}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:transient</md:NameIDFormat>
        <md:SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="{{SSO_ENDPOINT}}"/>
    </md:IDPSSODescriptor>
</md:EntityDescriptor>
EOF;

$request = new Request($_SERVER, $_GET, $_POST);
$entityId = $request->getRootUri().'metadata.php';
$ssoUri = $request->getRootUri().'sso.php';

$metaDataDocument = \str_replace(
    [
        '{{ENTITY_ID}}',
        '{{X509_CERTIFICATE}}',
        '{{SSO_ENDPOINT}}',
    ],
    [
        $entityId,
        $keyInfo,
        $ssoUri,
    ],
    $metaDataTemplate
);

$response = new Response(200, ['Content-Type' => 'application/samlmetadata+xml'], $metaDataDocument);
$response->send();
