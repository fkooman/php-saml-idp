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

use fkooman\SAML\IdP\Exception\LdapClientException;

class LdapClient
{
    /** @var resource */
    private $ldapResource;

    /**
     * @param string $ldapUri
     */
    public function __construct($ldapUri)
    {
        $this->ldapResource = @\ldap_connect($ldapUri);
        if (false === $this->ldapResource) {
            // only with very old OpenLDAP will it ever return false...
            throw new LdapClientException(\sprintf('unacceptable LDAP URI "%s"', $ldapUri));
        }
        if (false === \ldap_set_option($this->ldapResource, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new LdapClientException('unable to set LDAP option');
        }
    }

    /**
     * Bind to an LDAP server.
     *
     * @param string|null $bindUser you MUST use LdapClient::escapeDn on any user input used to contruct the DN!
     * @param string|null $bindPass
     *
     * @return void
     */
    public function bind($bindUser = null, $bindPass = null)
    {
        if (false === @\ldap_bind($this->ldapResource, $bindUser, $bindPass)) {
            throw new LdapClientException(
                \sprintf(
                    'LDAP error: (%d) %s',
                    \ldap_errno($this->ldapResource),
                    \ldap_error($this->ldapResource)
                )
            );
        }
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public static function escapeDn($str)
    {
        // ldap_escape in PHP >= 5.6 (or symfony/polyfill-php56)
        return \ldap_escape($str, '', LDAP_ESCAPE_DN);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public static function escapeFilter($str)
    {
        // ldap_escape in PHP >= 5.6 (or symfony/polyfill-php56)
        return \ldap_escape($str, '', LDAP_ESCAPE_FILTER);
    }

    /**
     * @param string        $baseDn
     * @param string        $searchFilter
     * @param array<string> $attributeList
     *
     * @return array
     */
    public function search($baseDn, $searchFilter, array $attributeList = [])
    {
        $searchResource = @\ldap_search(
            $this->ldapResource,    // link_identifier
            $baseDn,                // base_dn
            $searchFilter,          // filter
            $attributeList,         // attributes (dn is always returned...)
            0,                      // attrsonly
            0,                      // sizelimit
            10                      // timelimit
        );
        if (false === $searchResource) {
            throw new LdapClientException(
                \sprintf(
                    'LDAP error: (%d) %s',
                    \ldap_errno($this->ldapResource),
                    \ldap_error($this->ldapResource)
                )
            );
        }

        $ldapEntries = @\ldap_get_entries($this->ldapResource, $searchResource);
        if (false === $ldapEntries) {
            throw new LdapClientException(
                \sprintf(
                    'LDAP error: (%d) %s',
                    \ldap_errno($this->ldapResource),
                    \ldap_error($this->ldapResource)
                )
            );
        }

        return $ldapEntries;
    }
}
