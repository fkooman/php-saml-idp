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

use fkooman\SeCookie\Session;

class SeSession
{
    /** @var \fkooman\SeCookie\Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @return void
     */
    public function start()
    {
        $this->session->start();
    }

    /**
     * @return void
     */
    public function regenerate()
    {
        $this->session->regenerate();
    }

    /**
     * @return void
     */
    public function destroy()
    {
        $this->session->destroy();
    }

    /**
     * @param string $sessionKey
     * @param mixed  $sessionValue
     *
     * @return void
     */
    public function set($sessionKey, $sessionValue)
    {
        $this->session->set($sessionKey, serialize($sessionValue));
    }

    /**
     * @param string $sessionKey
     *
     * @return null|mixed
     */
    public function get($sessionKey)
    {
        if (null === $sessionValue = $this->session->get($sessionKey)) {
            return null;
        }

        return unserialize($sessionValue);
    }
}
