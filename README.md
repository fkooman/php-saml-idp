**DO NOT USE**

Very simple SAML 2.0 IdP.

# Features

- PHP >= 5.4 (including latest 7 release)
- **Only** IdP functionality
- Metadata URL
- HTTP POST binding for SAML Response
- HTTP Redirect binding for AuthnRequest
- Only supports RSA+SHA256 signed assertions
- **No** encryption support
- Does NOT verify AuthnRequest signatures
- As simple as possible (only compat / helper libraries, **no** xmlseclibs)
- Simple Authentication (static username/password)
- LDAP Authentication
- Attribute Mapping (from LDAP to e.g. OID)
- Attribute Mapping / Filtering (per SP)
- Subject Identifier (pairwise-id) support
- Consent Page
- Only "transient" NameID support
- Logout (SLO)

# Screenshots

![Authentication](img/auth.png)

![Consent](img/consent.png)

# Compatibility

We tested with:

- [simpleSAMLphp](https://simplesamlphp.org/)
- [mod_auth_mellon](https://github.com/UNINETT/mod_auth_mellon/)
- [OpenConext](https://openconext.org/)

# Installation

Git clone the repository. Use [Composer](https://getcomposer.org/) to install
the dependencies:

    $ git clone https://git.tuxed.net/fkooman/php-saml-idp
    $ cd php-saml-idp
    $ composer install

# Configuration

## SP 

    $ cp config/config.php.example config/config.php
    $ cp config/metadata.php.example config/metadata.php

Modify `config/config.php` to configure the IdP, modify `config/metadata.php` 
to add your SP(s).

Generate a salt:
    
    $ php bin/generate-salt.php

And use that for `secretSalt` in the `config/config.php` file.

## Generate X.509 Certificates

    $ openssl req \
        -nodes \
        -subj "/CN=SAML IdP" \
        -x509 \
        -sha256 \
        -newkey rsa:3072 \
        -keyout "config/server.key" \
        -out "config/server.crt" \
        -days 3600

# Run

    $ php -S localhost:8080 -t web/

# TODO

- better error handling
- better input validation
- WebSSO saml2int (encryption of assertion part is vague, what is "key transport"?!)
- multi language support
- major refactor and cleaning before a 1.0 release
- support SPs with DisplayName in metadata
- allow setting displayName in `metadata.php`
- write SP import script
- add "add-user" script to repo
- verify signature LogoutRequest coming from SP
- support storing X.509 certificates from SPs for verifying signatures
