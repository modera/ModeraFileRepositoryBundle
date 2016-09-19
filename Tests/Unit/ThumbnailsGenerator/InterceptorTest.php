<?php

namespace Modera\FileRepositoryBundle\Tests\Unit\ThumbnailsGenerator;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\Repository\FileRepository;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\AlternativeUploadedFile;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\Interceptor;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\ThumbnailsGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// because Phake was not working propertly when several \Phake::verify/capture were used in one TC
class MockFileRepository extends FileRepository
{
    public $invocations = [];

    public function put($repositoryName, \SplFileInfo $file, array $context = array())
    {
        $this->invocations[] = [$repositoryName, $file, $context];
    }
}

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class InterceptorTest extends \PHPUnit_Framework_TestCase
{
    private function createMocks()
    {
        $fr = new MockFileRepository(\Phake::mock(ContainerInterface::class));

        $tg = \Phake::mock(ThumbnailsGenerator::class);

        $repository = \Phake::mock(Repository::class);
        \Phake::when($repository)
            ->getConfig()
            ->thenReturn(array())
        ;

        $storedFile = \Phake::mock(StoredFile::class);
        \Phake::when($storedFile)
            ->getRepository()
            ->thenReturn($repository)
        ;

        $file = \Phake::mock(UploadedFile::class);

        return array(
            'file_repository' => $fr,
            'thumbnails_generator' => $tg,
            'repository' => $repository,
            'stored_file' => $storedFile,
            'file' => $file,
        );
    }

    public function testOnPut_noConfig()
    {
        $m = $this->createMocks();

        $itc = new Interceptor($m['file_repository'], $m['thumbnails_generator']);

        $this->assertEquals(
            Interceptor::RESULT_NO_CONFIG_AVAILABLE, $itc->onPut($m['stored_file'], $m['file'], $m['repository'])
        );
    }

    public function testOnPut_notImageGiven()
    {
        $m = $this->createMocks();

        \Phake::when($m['repository'])
            ->getConfig()
            ->thenReturn(array('thumbnail_sizes' => ['foo']))
        ;

        $pathname = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents('foo', $pathname);

        \Phake::when($m['file'])
            ->getPathname()
            ->thenReturn($pathname)
        ;

        $itc = new Interceptor($m['file_repository'], $m['thumbnails_generator']);

        $this->assertEquals(
            Interceptor::RESULT_NOT_IMAGE_GIVEN, $itc->onPut($m['stored_file'], $m['file'], $m['repository'])
        );
    }

    public function testOnPut()
    {
        $m = $this->createMocks();

        $thumbnailsConfig = [
            array(
                'width' => 150,
                'height' => 100,
            ),
            array(
                'width' => 60,
                'height' => 30,
            ),
        ];

        $imagePathname = __DIR__.'/../../Fixtures/backend.png';

        \Phake::when($m['repository'])
            ->getConfig()
            ->thenReturn(array('thumbnail_sizes' => $thumbnailsConfig))
        ;
        \Phake::when($m['repository'])
            ->getName()
            ->thenReturn('foo-repo')
        ;

        \Phake::when($m['file'])
            ->getPathname()
            ->thenReturn($imagePathname)
        ;
        \Phake::when($m['file'])
            ->getMimeType()
            ->thenReturn('image/png')
        ;

        \Phake::when($m['thumbnails_generator'])
            ->generate($m['file'], 150, 100)
            ->thenReturn(__FILE__) // it doesn't matter in scope of test what kind of file it is
        ;

        $itc = new Interceptor($m['file_repository'], $m['thumbnails_generator']);

        $this->assertEquals(
            Interceptor::RESULT_SCHEDULED, $itc->onPut($m['stored_file'], $m['file'], $m['repository'])
        );

        $this->assertTrue(isset($m['file_repository']->invocations[0]));

        /* @var AlternativeUploadedFile $firstScheduledFile */
        $firstScheduledFile = $m['file_repository']->invocations[0][1];

        $this->assertInstanceOf(AlternativeUploadedFile::class, $firstScheduledFile);
        $this->assertSame($m['file'], $firstScheduledFile->getOriginalFile());
        $this->assertSame($m['stored_file'], $firstScheduledFile->getOriginalStoredFile());
        $this->assertSame($thumbnailsConfig[0], $firstScheduledFile->getThumbnailConfig());

        // ---

        $firstThumbnailStoredFile = \Phake::mock(StoredFile::class);
        \Phake::when($firstThumbnailStoredFile)
            ->getRepository()
            ->thenReturn($m['repository'])
        ;

        \Phake::when($m['thumbnails_generator'])
            ->generate($m['file'], 60, 30)
            ->thenReturn(__FILE__) // it doesn't matter in scope of test what kind of file it is
        ;

        $this->assertEquals(
            Interceptor::RESULT_SCHEDULED, $itc->onPut($firstThumbnailStoredFile, $firstScheduledFile, $m['repository'])
        );

        \Phake::verify($m['thumbnails_generator'])
            ->updateStoredFileAlternativeMeta($firstThumbnailStoredFile, $firstScheduledFile->getThumbnailConfig())
        ;

        $this->assertTrue(isset($m['file_repository']->invocations[1]));
        /* @var AlternativeUploadedFile $secondScheduledFile */
        $secondScheduledFile = $firstScheduledFile = $m['file_repository']->invocations[1][1];

        $this->assertInstanceOf(AlternativeUploadedFile::class, $secondScheduledFile);
        $this->assertSame($m['file'], $secondScheduledFile->getOriginalFile());
        $this->assertSame($m['stored_file'], $secondScheduledFile->getOriginalStoredFile());
        $this->assertSame($thumbnailsConfig[1], $secondScheduledFile->getThumbnailConfig());

        // ---

        $secondThumbnailStoredFile = \Phake::mock(StoredFile::class);
        \Phake::when($secondThumbnailStoredFile)
            ->getRepository()
            ->thenReturn($m['repository'])
        ;

        $this->assertEquals(
            Interceptor::RESULT_NO_MORE_THUMBNAILS, $itc->onPut($secondThumbnailStoredFile, $secondScheduledFile, $m['repository'])
        );

        \Phake::verify($m['thumbnails_generator'])
            ->updateStoredFileAlternativeMeta($secondThumbnailStoredFile, $secondScheduledFile->getThumbnailConfig())
        ;
    }
}
