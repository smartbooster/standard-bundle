<?php

namespace Smart\StandardBundle;

use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractWebTestCase extends WebTestCase
{
    protected ?KernelBrowser $client = null;
    protected ?EntityManagerInterface $entityManager = null;
    protected AbstractDatabaseTool $databaseTool;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // https://github.com/liip/LiipTestFixturesBundle/blob/2.x/UPGRADE-2.0.md
        // @phpstan-ignore-next-line
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // https://github.com/liip/LiipTestFixturesBundle/pull/196/files
        unset($this->databaseTool);

        // avoid memory leaks
        if ($this->entityManager != null) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }

    protected function getParameter(string $name): mixed
    {
        return static::getContainer()->getParameter($name);
    }

    protected function getProjectTestDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/tests';
    }

    protected function getFixturesDir(): string
    {
        return $this->getProjectTestDir() . '/fixtures';
    }

    protected function getMinimalFixturesDir(): string
    {
        return $this->getProjectTestDir() . '/../fixtures/minimal';
    }

    protected function getCsvDir(): string
    {
        return $this->getProjectTestDir() . '/csv';
    }

    /**
     * Return object property who can be private
     * https://www.yellowduck.be/posts/test-private-and-protected-properties-using-phpunit
     */
    public static function getProperty(object $object, string $property): mixed
    {
        $reflectedClass = new \ReflectionClass($object);
        $reflection = $reflectedClass->getProperty($property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    /**
     * Return object method who can be private
     * https://stackoverflow.com/questions/249664/best-practices-to-test-protected-methods-with-phpunit
     * @param class-string $class
     */
    protected static function getMethod(string $name, string $class): \ReflectionMethod
    {
        $method = (new \ReflectionClass($class))->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * Test if $array contains exactly all values of $values no matter there order
     */
    public function assertArrayContainsValues(array $values, array $array): void
    {
        $this->assertCount(count($values), $array);

        foreach ($values as $value) {
            $this->assertContains($value, $array);
        }
    }
}
