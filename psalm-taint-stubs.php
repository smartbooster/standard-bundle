<?php

/**
 * Psalm taint stubs — Doctrine SQL SINKS.
 *
 * Symfony SOURCES (Request, InputBag, ParameterBag, HeaderBag) are provided by
 * psalm/plugin-symfony (enabled in psalm.xml), with Symfony-version-aware stubs.
 * This file only declares what the plugin does NOT cover: the SQL sinks of Doctrine
 * ORM/DBAL. Without them, no SQL injection going through createQuery()/QueryBuilder/DBAL
 * would be detected. See docs/psalm.md.
 *
 * Superglobals ($_GET, $_POST, ...) stay native Psalm sources. The signatures below
 * reproduce the vendor ones exactly (Psalm merges these annotations with the real
 * classes — resync if Doctrine changes a signature).
 */

namespace Doctrine\ORM;

interface EntityManagerInterface
{
    /**
     * @psalm-taint-sink sql $dql
     */
    public function createQuery(string $dql = ''): Query;
}

class QueryBuilder
{
    /**
     * @psalm-taint-sink sql $predicates
     */
    public function where(mixed ...$predicates): static
    {
    }

    /**
     * @psalm-taint-sink sql $where
     */
    public function andWhere(mixed ...$where): static
    {
    }

    /**
     * @psalm-taint-sink sql $where
     */
    public function orWhere(mixed ...$where): static
    {
    }

    /**
     * @psalm-taint-sink sql $having
     */
    public function having(mixed ...$having): static
    {
    }

    /**
     * @psalm-taint-sink sql $having
     */
    public function andHaving(mixed ...$having): static
    {
    }

    /**
     * @psalm-taint-sink sql $having
     */
    public function orHaving(mixed ...$having): static
    {
    }

    /**
     * @psalm-taint-sink sql $groupBy
     */
    public function groupBy(string ...$groupBy): static
    {
    }

    /**
     * @psalm-taint-sink sql $groupBy
     */
    public function addGroupBy(string ...$groupBy): static
    {
    }
}

namespace Doctrine\DBAL;

use Doctrine\DBAL\Cache\QueryCacheProfile;

class Connection
{
    /**
     * @psalm-taint-sink sql $sql
     */
    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
    }

    /**
     * @psalm-taint-sink sql $sql
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
    }
}
