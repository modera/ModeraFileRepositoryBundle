<?php

namespace Modera\FileRepositoryBundle\Tests\Functional\EventListener;

use Doctrine\ORM\Tools\SchemaTool;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FoundationBundle\Testing\FunctionalTestCase;

/**
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
class ContainerInjectorListenerTest extends FunctionalTestCase
{
    /**
     * @var SchemaTool
     */
    private static $st;

    public static function doSetUpBeforeClass(): void
    {
        self::$st = new SchemaTool(self::$em);
        self::$st->createSchema([
            self::$em->getClassMetadata(Repository::class),
        ]);
    }

    public static function doTearDownAfterClass(): void
    {
        self::$st->dropSchema([
            self::$em->getClassMetadata(Repository::class),
        ]);
    }

    public function testHowWellContainerIsInjected()
    {
        $repository = new Repository('test repo', array(
            'filesystem' => '',
            'storage_key_generator' => '',
        ));

        self::$em->persist($repository);
        self::$em->flush();

        self::$em->clear();

        /* @var Repository $repository */
        $repository = self::$em->getRepository(Repository::class)->find($repository->getId());

        $reflClass = new \ReflectionClass($repository);
        $reflProp = $reflClass->getProperty('container');
        $reflProp->setAccessible(true);

        $this->assertInstanceOf(
            'Symfony\Component\DependencyInjection\ContainerInterface',
            $reflProp->getValue($repository)
        );
    }
}
