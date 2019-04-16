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

namespace fkooman\SAML\IdP\Http;

use fkooman\SAML\IdP\Http\Exception\HttpException;

class Request
{
    /** @var array */
    private $serverData;

    /** @var array */
    private $getData;

    /** @var array */
    private $postData;

    /**
     * @param array $serverData
     * @param array $getData
     * @param array $postData
     */
    public function __construct(array $serverData, array $getData, array $postData)
    {
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    /**
     * @return string
     */
    public function getServerName()
    {
        return $this->serverData['SERVER_NAME'];
    }

    /**
     * @return array
     */
    public function getQueryParameters()
    {
        return $this->getData;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasQueryParameter($key)
    {
        return \array_key_exists($key, $this->getData) && !empty($this->getData[$key]);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getQueryParameter($key)
    {
        if (!$this->hasQueryParameter($key)) {
            throw new HttpException(sprintf('query parameter "%s" not provided', $key), 400);
        }

        return $this->getData[$key];
    }

    /**
     * @return array
     */
    public function getPostParameters()
    {
        return $this->postData;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasPostParameter($key)
    {
        return \array_key_exists($key, $this->postData) && !empty($this->postData[$key]);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getPostParameter($key)
    {
        if (!$this->hasPostParameter($key)) {
            throw new HttpException(sprintf('post parameter "%s" not provided', $key), 400);
        }

        return $this->postData[$key];
    }

    /**
     * @param string $key
     *
     * @return null|string
     */
    public function getHeader($key)
    {
        return \array_key_exists($key, $this->serverData) ? $this->serverData[$key] : null;
    }

    /**
     * @return string
     */
    public function getRoot()
    {
        $rootDir = \dirname($this->serverData['SCRIPT_NAME']);
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    /**
     * @return string
     */
    public function getAuthority()
    {
        // scheme
        if (!\array_key_exists('REQUEST_SCHEME', $this->serverData)) {
            $requestScheme = 'http';
        } else {
            $requestScheme = $this->serverData['REQUEST_SCHEME'];
        }

        // server_name
        $serverName = $this->serverData['SERVER_NAME'];

        // port
        $serverPort = (int) $this->serverData['SERVER_PORT'];

        $usePort = false;
        if ('https' === $requestScheme && 443 !== $serverPort) {
            $usePort = true;
        }
        if ('http' === $requestScheme && 80 !== $serverPort) {
            $usePort = true;
        }

        if ($usePort) {
            return sprintf('%s://%s:%d', $requestScheme, $serverName, $serverPort);
        }

        return sprintf('%s://%s', $requestScheme, $serverName);
    }

    /**
     * @return string
     */
    public function getRootUri()
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    /**
     * @return string
     */
    public function getUri()
    {
        $requestUri = $this->serverData['REQUEST_URI'];

        return sprintf('%s%s', $this->getAuthority(), $requestUri);
    }

    /**
     * @return string
     */
    public function getPathInfo()
    {
        // remove the query string
        $requestUri = $this->serverData['REQUEST_URI'];
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        // if requestUri === scriptName
        if ($this->serverData['REQUEST_URI'] === $this->serverData['SCRIPT_NAME']) {
            return '/';
        }

        // remove script_name (if it is part of request_uri
        if (0 === strpos($requestUri, $this->serverData['SCRIPT_NAME'])) {
            return substr($requestUri, \strlen($this->serverData['SCRIPT_NAME']));
        }

        // remove the root
        if ('/' !== $this->getRoot()) {
            return substr($requestUri, \strlen($this->getRoot()) - 1);
        }

        return $requestUri;
    }
}
