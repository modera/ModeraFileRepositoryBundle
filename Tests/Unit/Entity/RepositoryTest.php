<?php

namespace Modera\FileRepositoryBundle\Tests\Unit\Entity;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\Exceptions\InvalidRepositoryConfig;
use Modera\FileRepositoryBundle\Intercepting\OperationInterceptorInterface;

// there's some glitches in Phake which didn't allow to properly
// validate several method's invocations in one single tc
class DummyInterceptor implements OperationInterceptorInterface
{
    /**
     * @var array
     */
    public array $beforePutInvocations = [];

    /**
     * @var array
     */
    public array $onPutInvocations = [];

    /**
     * @var array
     */
    public array $afterPutInvocations = [];

    public function beforePut(\SplFileInfo $file, Repository $repository, array $context = []): void
    {
        $this->beforePutInvocations[] = [$file, $repository];
    }

    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
        $this->onPutInvocations[] = [$storedFile, $file, $repository];
    }

    public function afterPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
        $this->afterPutInvocations[] = [$storedFile, $file, $repository];
    }
}

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class RepositoryTest extends \PHPUnit\Framework\TestCase
{
    public function test__construct()
    {
        $thrownException = null;

        try {
            new Repository('foo', array());
        } catch (InvalidRepositoryConfig $e) {
            $thrownException = $e;
        }

        $this->assertNotNull($thrownException);
        $this->assertEquals('filesystem', $thrownException->getMissingConfigurationKey());
        $this->assertEquals(array(), $thrownException->getConfig());
    }

    public function testInterceptors()
    {
        $itc = new DummyInterceptor();

        $interceptorsProvider = \Phake::mock('Modera\FileRepositoryBundle\Intercepting\InterceptorsProviderInterface');
        \Phake::when($interceptorsProvider)
            ->getInterceptors($this->isInstanceOf(Repository::class))
            ->thenReturn([$itc])
        ;

        $container = \Phake::mock('Symfony\Component\DependencyInjection\ContainerInterface');
        \Phake::when($container)
            ->get('modera_file_repository.intercepting.interceptors_provider')
            ->thenReturn($interceptorsProvider)
        ;

        $repository = new Repository('foo', array('filesystem' => 'foo'));
        $repository->init($container);

        $splFile = new \SplFileInfo(__FILE__);
        $storedFile = \Phake::mock(StoredFile::class);

        // ---

        $this->assertEquals(0, count($itc->beforePutInvocations));

        $repository->beforePut($splFile);

        $this->assertEquals(1, count($itc->beforePutInvocations));
        $this->assertSame($splFile, $itc->beforePutInvocations[0][0]);
        $this->assertSame($repository, $itc->beforePutInvocations[0][1]);

        // ---

        $receivedInterceptor = null;
        $repository->beforePut(
            $splFile,
            function ($interceptor) use (&$receivedInterceptor) {
                $receivedInterceptor = $interceptor;

                return false;
            }
        );

        $this->assertSame($itc, $receivedInterceptor);
        $this->assertEquals(1, count($itc->beforePutInvocations));

        // ---

        $this->assertEquals(0, count($itc->onPutInvocations));

        $repository->onPut($storedFile, $splFile);

        $this->assertEquals(1, count($itc->onPutInvocations));
        $this->assertSame($storedFile, $itc->onPutInvocations[0][0]);
        $this->assertSame($splFile, $itc->onPutInvocations[0][1]);
        $this->assertSame($repository, $itc->onPutInvocations[0][2]);

        // ---

        $receivedInterceptor = null;
        $repository->onPut(
            $storedFile,
            $splFile,
            function ($interceptor) use (&$receivedInterceptor) {
                $receivedInterceptor = $interceptor;

                return false;
            }
        );

        $this->assertSame($itc, $receivedInterceptor);
        $this->assertEquals(1, count($itc->onPutInvocations));

        // ---

        $this->assertEquals(0, count($itc->afterPutInvocations));

        $repository->afterPut($storedFile, $splFile);

        $this->assertEquals(1, count($itc->afterPutInvocations));
        $this->assertSame($storedFile, $itc->afterPutInvocations[0][0]);
        $this->assertSame($splFile, $itc->afterPutInvocations[0][1]);
        $this->assertSame($repository, $itc->afterPutInvocations[0][2]);

        $receivedInterceptor = null;
        $repository->afterPut(
            $storedFile,
            $splFile,
            function ($interceptor) use (&$receivedInterceptor) {
                $receivedInterceptor = $interceptor;

                return false;
            }
        );

        $this->assertSame($itc, $receivedInterceptor);
        $this->assertEquals(1, count($itc->afterPutInvocations));
    }
}
