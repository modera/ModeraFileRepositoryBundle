<?php

namespace Modera\FileRepositoryBundle\Tests\Unit\Intercepting;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Intercepting\DefaultInterceptorsProvider;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2015 Modera Foundation
 */
class DefaultInterceptorsProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetInterceptors()
    {
        $dummyFilePropertiesValidationInterceptor = new \stdClass();
        $dummyAuthoringInterceptor = new \stdClass();
        $dummyFooInterceptor = new \stdClass();
        $mimeInterceptor = new \stdClass();

        $container = \Phake::mock('Symfony\Component\DependencyInjection\ContainerInterface');
        \Phake::when($container)
            ->get('modera_file_repository.validation.file_properties_validation_interceptor')
            ->thenReturn($dummyFilePropertiesValidationInterceptor)
        ;
        \Phake::when($container)
            ->get('modera_file_repository.authoring.authoring_interceptor')
            ->thenReturn($dummyAuthoringInterceptor)
        ;
        \Phake::when($container)
            ->get('modera_file_repository.intercepting.mime_saver_interceptor')
            ->thenReturn($mimeInterceptor)
        ;
        \Phake::when($container)
            ->get('foo_interceptor')
            ->thenReturn($dummyFooInterceptor)
        ;

        $repository = \Phake::mock(Repository::clazz());

        $provider = new DefaultInterceptorsProvider($container);

        $result = $provider->getInterceptors($repository);

        $this->assertEquals(3, count($result));
        $this->assertSame($dummyFilePropertiesValidationInterceptor, $result[0]);
        $this->assertSame($mimeInterceptor, $result[1]);
        $this->assertSame($dummyAuthoringInterceptor, $result[2]);

        // and now with a "interceptors" config:

        \Phake::when($repository)
            ->getConfig()
            ->thenReturn(array('interceptors' => ['foo_interceptor']))
        ;

        $result = $provider->getInterceptors($repository);

        $this->assertEquals(4, count($result));
        $this->assertSame($dummyFilePropertiesValidationInterceptor, $result[0]);
        $this->assertSame($mimeInterceptor, $result[1]);
        $this->assertSame($dummyAuthoringInterceptor, $result[2]);
        $this->assertSame($dummyFooInterceptor, $result[3]);
    }
}
