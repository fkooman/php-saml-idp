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

use fkooman\SqliteMigrate\Migration;
use PDO;

class Storage
{
    const CURRENT_SCHEMA_VERSION = '2019041601';

    /** @var \PDO */
    private $db;

    /** @var \fkooman\SqliteMigrate\Migration */
    private $migration;

    /**
     * @param \PDO   $db
     * @param string $schemaDir
     */
    public function __construct(PDO $db, $schemaDir)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        $this->db = $db;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
    }

    /**
     * @param string $userId
     * @param string $spEntityId
     * @param string $attributeHash
     *
     * @return bool
     */
    public function hasConsent($userId, $spEntityId, $attributeHash)
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)
             FROM consent
             WHERE
                user_id = :user_id
             AND
                sp_entity_id = :sp_entity_id
             AND
                attribute_hash = :attribute_hash'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':sp_entity_id', $spEntityId, PDO::PARAM_STR);
        $stmt->bindValue(':attribute_hash', $attributeHash, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === (int) $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     * @param string $spEntityId
     * @param string $attributeHash
     *
     * @return void
     */
    public function storeConsent($userId, $spEntityId, $attributeHash)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO
                consent (user_id, sp_entity_id, attribute_hash)
            VALUES
                (:user_id, :sp_entity_id, :attribute_hash)'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':sp_entity_id', $spEntityId, PDO::PARAM_STR);
        $stmt->bindValue(':attribute_hash', $attributeHash, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @return void
     */
    public function init()
    {
        $this->migration->init();
    }

    /**
     * @return void
     */
    public function update()
    {
        $this->migration->run();
    }
}
