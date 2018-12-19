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

# Compatibility

We tested with:

- [simpleSAMLphp](https://simplesamlphp.org/)
- [mod_auth_mellon](https://github.com/UNINETT/mod_auth_mellon/)
- [OpenConext](https://openconext.org/)

# Configuration

## SP 

    $ cp config/config.php.example config/config.php

Modify `config/config.php` to add your SP(s).

## Generate Certificates

    $ openssl req \
        -nodes \
        -subj "/CN=SAML IdP" \
        -x509 \
        -sha256 \
        -newkey rsa:3072 \
        -keyout "config/server.key" \
        -out "config/server.crt" \
        -days 2880

# Run

    $ php -S localhost:8080 -t web/

# TODO

- better persistent ID implementation, move salt to config
- cleanup attribute release / mapping (what a mess!)
- error handling
- make SAML response assertion as simple as possible
- figure out if there are any (security) issues with parsing XML in 
  AuthnRequest
- WebSSO saml2int
- consent screen (show attributes + values)
- multi language support
- implement logout
- implement validuntil in metadata xml

