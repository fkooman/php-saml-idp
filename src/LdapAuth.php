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

class LdapAuth
{
    /** @var Config */
    private $config;

    /** @var LdapClient */
    private $ldapClient;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->ldapClient = new LdapClient($config->get('ldapUri'));
    }

    /**
     * @param string $authUser
     * @param string $authPass
     *
     * @return UserInfo
     */
    public function authenticate($authUser, $authPass)
    {
        $userDn = \str_replace('{{UID}}', LdapClient::escapeDn($authUser), $this->config->get('userDnTemplate'));
        $this->ldapClient->bind($userDn, $authPass);

        return new UserInfo($authUser, $this->getAttributes($userDn));
    }

    /**
     * @param string $userDn
     *
     * @return array<string, array<string>>
     */
    private function getAttributes($userDn)
    {
        $ldapEntries = $this->ldapClient->search(
            $userDn,
            '(objectClass=*)',
            []
        );

        if (1 !== $ldapEntries['count']) {
            // user does not exist XXX
            throw new \Exception('user does not exist or multiple responses?!');
        }

        $userAttributes = [];
        $userInfo = $ldapEntries[0];

        $attributeList = [];
        for ($i = 0; $i < $userInfo['count']; ++$i) {
            $attributeList[] = $userInfo[$i];
        }

        foreach ($attributeList as $attributeName) {
            $attributeValues = [];
            for ($i = 0; $i < $userInfo[$attributeName]['count']; ++$i) {
                $attributeValues[] = $userInfo[$attributeName][$i];
            }
            $userAttributes[$attributeName] = $attributeValues;
        }

        return $userAttributes;
    }
}
