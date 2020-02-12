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

use Exception;

class UserInfo
{
    /** @var string */
    private $authUser;

    /** @var array<string, array<string>> */
    private $attributeList;

    /** @var bool */
    private $twoFactorVerified = false;

    /**
     * @param string                       $authUser
     * @param array<string, array<string>> $attributeList
     */
    public function __construct($authUser, array $attributeList)
    {
        $this->authUser = $authUser;
        $this->attributeList = $attributeList;
    }

    /**
     * @return string
     */
    public function getAuthUser()
    {
        return $this->authUser;
    }

    /**
     * @return void
     */
    public function setTwoFactorVerified()
    {
        $this->twoFactorVerified = true;
    }

    /**
     * @return bool
     */
    public function getTwoFactorVerified()
    {
        return $this->twoFactorVerified;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getAttributes()
    {
        return $this->attributeList;
    }

    /**
     * @param string $attributeName
     *
     * @return array<string>
     */
    public function getAttribute($attributeName)
    {
        if (!\array_key_exists($attributeName, $this->attributeList)) {
            throw new Exception(sprintf('attribute "%s" not available', $attributeName));
        }

        return $this->attributeList[$attributeName];
    }
}
