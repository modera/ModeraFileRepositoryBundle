<?php

namespace Modera\FileRepositoryBundle\Tests\Functional\Entity;

use Doctrine\ORM\Tools\SchemaTool;
use Gaufrette\Exception\FileNotFound;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\Repository\FileRepository;
use Modera\FoundationBundle\Testing\FunctionalTestCase;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2017 Modera Foundation
 */
class StoredFileTest extends FunctionalTestCase
{
    private static $st;

    public static function doSetUpBeforeClass(): void
    {
        self::$st = new SchemaTool(self::$em);
        self::$st->createSchema([
            self::$em->getClassMetadata(Repository::class),
            self::$em->getClassMetadata(StoredFile::class),
        ]);
    }

    public static function doTearDownAfterClass(): void
    {
        self::$st->dropSchema([
            self::$em->getClassMetadata(Repository::class),
            self::$em->getClassMetadata(StoredFile::class),
        ]);
    }

    /**
     * @group MPFE-1027
     */
    public function testDeletingEntityWithoutPhysicalFile()
    {
        /* @var FileRepository $fr */
        $fr = self::getContainer()->get('modera_file_repository.repository.file_repository');

        $repoName = 'dummy_repository2';

        $this->assertNull($fr->getRepository($repoName));

        $repositoryConfig = array(
            'storage_key_generator' => 'modera_file_repository.repository.uniqid_key_generator',
            'filesystem' => 'dummy_tmp_fs',
        );

        $this->assertFalse($fr->repositoryExists($repoName));

        $repository = $fr->createRepository($repoName, $repositoryConfig, 'My dummy repository 2');

        // ---

        $storedFile = $this->createAndPersistStoredFile($fr, $repository);

        // physically deleting a file
        $storedFile->getRepository()->getFilesystem()->delete($storedFile->getStorageKey());

        self::$em->remove($storedFile);
        self::$em->flush(); // no exception has been thrown
    }

    /**
     * @group MPFE-1027
     */
    public function testDeletingEntityWithoutPhysicalFileDenied()
    {
        /* @var FileRepository $fr */
        $fr = self::getContainer()->get('modera_file_repository.repository.file_repository');

        $repoName = 'dummy_repository3';

        $this->assertNull($fr->getRepository($repoName));

        $repositoryConfig = array(
            'storage_key_generator' => 'modera_file_repository.repository.uniqid_key_generator',
            'filesystem' => 'dummy_tmp_fs',
        );

        $this->assertFalse($fr->repositoryExists($repoName));

        $repository = $fr->createRepository($repoName, $repositoryConfig, 'My dummy repository 3');

        // ---

        $storedFile = $this->createAndPersistStoredFile($fr, $repository);

        // physically deleting a file
        $storedFile->getRepository()->getFilesystem()->delete($storedFile->getStorageKey());
        $storedFile->setIgnoreMissingFileOnDelete(false);

        $fileNotFoundException = null;
        try {
            self::$em->remove($storedFile);
            self::$em->flush();
        } catch (FileNotFound $e) {
            $fileNotFoundException = $e;
        }
        $this->assertNotNull($fileNotFoundException);

        $storedFile->setIgnoreMissingFileOnDelete(true);

        self::$em->remove($storedFile);
        self::$em->flush(); // no exception thrown because
        self::$em->clear();
    }

    private function createAndPersistStoredFile(FileRepository $fr, Repository $repository)
    {
        $fileContents = 'bar contents';
        $filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'our-bar-dummy-file.txt';
        file_put_contents($filePath, $fileContents);

        $file = new File($filePath);

        $storedFile = $fr->put($repository->getName(), $file, array());

        self::$em->clear(); // this way we will make sure that data is actually persisted in database

        /* @var StoredFile $storedFile */
        $storedFile = self::$em->find(StoredFile::class, $storedFile->getId());
        $this->assertInstanceOf(StoredFile::class, $storedFile);

        return $storedFile;
    }
}
