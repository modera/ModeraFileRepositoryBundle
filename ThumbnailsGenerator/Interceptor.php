<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\Intercepting\BaseOperationInterceptor;
use Modera\FileRepositoryBundle\Repository\FileRepository;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * This interceptors allows to generate thumbnails for files that are uploaded to a repository. This interceptor
 * attempts to read thumbnail's generation config from repository's config using "thumbnail_sizes" key, every element
 * of which must be an array containing two keys - "width" and "height". This is a sample repository config required
 * for this interceptor to work:.
 *
 *     array(
 *         'thumbnail_sizes' => array(
 *             array('width' => 250, 'height' => 100),
 *             array('width' => 50, 'height' => 50),
 *          ),
 *     ),
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class Interceptor extends BaseOperationInterceptor
{
    const ID = 'modera_file_repository.interceptors.thumbnails_generator.interceptor';

    // internal; this flags are used to facilitate testing
    const RESULT_NO_CONFIG_AVAILABLE = 'no_config_available';
    const RESULT_NOT_IMAGE_GIVEN = 'not_image_given';
    const RESULT_NO_MORE_THUMBNAILS = 'no_more_thumbnails';
    const RESULT_SCHEDULED = 'scheduled';

    /**
     * @var FileRepository
     */
    private $fileRepository;

    /**
     * @var ThumbnailsGenerator
     */
    private $thumbnailsGenerator;

    /**
     * Indexed by original's pathname.
     *
     * @var array
     */
    private $thumbnailsProgress = array();

    /**
     * @param FileRepository      $fileRepository
     * @param ThumbnailsGenerator $thumbnailsGenerator
     */
    public function __construct(FileRepository $fileRepository, ThumbnailsGenerator $thumbnailsGenerator)
    {
        $this->fileRepository = $fileRepository;
        $this->thumbnailsGenerator = $thumbnailsGenerator;
    }

    /**
     * @param \SplFileInfo $file
     *
     * @return bool
     */
    private function isAlternative(\SplFileInfo $file)
    {
        return in_array(AlternativeFileTrait::class, class_uses(get_class($file)));
    }

    /**
     * This method is going to be invoked recursively to generate thumbnails, classes with AlternativeFileTrait are
     * going to serve as markers that thumbnail needs to be generated.
     *
     * {@inheritdoc}
     */
    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository)
    {
        $repoConfig = $storedFile->getRepository()->getConfig();
        if (!isset($repoConfig['thumbnail_sizes']) || count($repoConfig['thumbnail_sizes']) == 0) {
            return self::RESULT_NO_CONFIG_AVAILABLE;
        }

        $isAlternative = $this->isAlternative($file);

        // given $storedFile and $file could be alternative already so we need to resolve
        // an original file that we are going to use to generate a thumbnail from
        if ($isAlternative) {
            /* @var AlternativeFileTrait $file */
            $originalStoredFile = $file->getOriginalStoredFile();
            $originalFile = $file->getOriginalFile();

            $originalStoredFile->addAlternative($storedFile);
        } else {
            $originalStoredFile = $storedFile;
            $originalFile = $file;
        }

        if (!$originalFile instanceof File) {
            // "File" class provides the API we need (like guessing MIME type)
            $originalFile = new File($originalFile->getPathname());
        }

        $isImage = substr($originalFile->getMimeType(), 0, strlen('image/')) == 'image/';
        if (!$isImage) {
            return self::RESULT_NOT_IMAGE_GIVEN;
        }

        $lookupKey = $originalFile->getPathname();
        if (!isset($this->thumbnailsProgress[$lookupKey])) {
            $this->thumbnailsProgress[$lookupKey] = $repoConfig['thumbnail_sizes']; // thumbnails that have yet to be generated
        }

        if ($isAlternative) {
            // Taking a config of previously generated thumbnail and updating entity to store it.
            // We couldn't update it right away when we generate a thumbnail because we haven't yet
            // had access to entity
            $this->thumbnailsGenerator->updateStoredFileAlternativeMeta($storedFile, $file->getThumbnailConfig());
        }

        // getting next thumbnail's config that needs to be generated
        $thumbnailConfig = array_shift($this->thumbnailsProgress[$lookupKey]);
        if (!$thumbnailConfig) {
            // making it possible to have thumbnails generated if the same original file is passed to file
            // repository
            unset($this->thumbnailsProgress[$lookupKey]);

            return self::RESULT_NO_MORE_THUMBNAILS;
        }

        $thumbnailPathname = $this->generateThumbnail($originalFile, $storedFile, $thumbnailConfig);

        /* @var AlternativeFileTrait $newFile */
        $newFile = null;
        if ($originalFile instanceof UploadedFile) {
            $newFile = new AlternativeUploadedFile(
                $thumbnailPathname, // we need to save a thumbnail to the same file repository
                $originalFile->getClientOriginalName(),
                $originalFile->getClientMimeType(),
                filesize($thumbnailPathname)
            );
        } else {
            $newFile = new AlternativeFile($thumbnailPathname);
        }
        $newFile->setOriginalFile($originalFile);
        $newFile->setOriginalStoredFile($originalStoredFile);
        $newFile->setThumbnailConfig($thumbnailConfig);

        // recursively creating thumbnails
        $this->fileRepository->put($repository->getName(), $newFile);
        // ... we cannot modify returned storedFile here already because UoW is already flushed

        return self::RESULT_SCHEDULED;
    }

    /**
     * You can override this method if you need to a more elaborate way how thumbnails are generated.
     *
     * @param File       $originalFile
     * @param StoredFile $storedFile
     * @param array      $config
     *
     * @return string A pathname where thumbnail is saved
     */
    protected function generateThumbnail(File $originalFile, StoredFile $storedFile, array $config)
    {
        return $this->thumbnailsGenerator->generate($originalFile, $config['width'], $config['height']);
    }
}
