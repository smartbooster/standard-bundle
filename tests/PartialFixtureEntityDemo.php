<?php

namespace Smart\StandardBundle\Tests;

/**
 * Not a real test (no "*Test.php" suffix, doesn't extend TestCase, never instantiated) : this class only
 * exists so phpstan.neon's `method.nonObject` / `path: tests/` ignoreErrors example — a template for
 * projects whose Alice/Doctrine fixtures build partially-hydrated entities — actually matches an error in
 * this bundle's own analysis, instead of failing `reportUnmatchedIgnoredErrors` with "ignore.unmatched".
 */
final class PartialFixtureEntityDemo
{
    public function __construct(private readonly ?self $relatedEntity = null)
    {
    }

    public function getRelatedEntity(): ?self
    {
        return $this->relatedEntity;
    }

    public function getLabel(): string
    {
        return 'demo';
    }

    /**
     * Mirrors a fixture assertion written against a relation Alice/Doctrine hasn't hydrated yet:
     * getRelatedEntity() is nullable, so calling getLabel() on it triggers `method.nonObject`.
     */
    public function demonstrateIgnoredMethodNonObject(): string
    {
        return $this->getRelatedEntity()->getLabel();
    }
}
