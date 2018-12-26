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

class Key
{
    /** @var string */
    private $pemKey;

    /**
     * @param string $pemKey
     */
    public function __construct($pemKey)
    {
        $this->pemKey = $pemKey;
    }

    /**
     * @param string $keyFile
     *
     * @return self
     */
    public static function fromFile($keyFile)
    {
        return new self(\file_get_contents($keyFile));
    }

    /**
     * @return resource
     */
    public function getPrivateKey()
    {
        return \openssl_pkey_get_private($this->pemKey);
    }

    /**
     * @return resource
     */
    public function getPublicKey()
    {
        $pemKey = \sprintf("-----BEGIN CERTIFICATE-----\n%s-----END CERTIFICATE-----", \chunk_split($this->pemKey));

        return \openssl_pkey_get_public($pemKey);
    }
}
