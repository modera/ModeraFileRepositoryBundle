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
    public const ID = 'modera_file_repository.interceptors.thumbnails_generator.interceptor';

    // internal; these flags are used to facilitate testing
    public const RESULT_NO_CONFIG_AVAILABLE = 'no_config_available';
    public const RESULT_NOT_IMAGE_GIVEN = 'not_image_given';
    public const RESULT_NO_MORE_THUMBNAILS = 'no_more_thumbnails';
    public const RESULT_SCHEDULED = 'scheduled';

    private FileRepository $fileRepository;

    private ThumbnailsGenerator $thumbnailsGenerator;

    /**
     * Indexed by original's pathname.
     *
     * @var array<string, array<mixed>>
     */
    private array $thumbnailsProgress = [];

    public function __construct(FileRepository $fileRepository, ThumbnailsGenerator $thumbnailsGenerator)
    {
        $this->fileRepository = $fileRepository;
        $this->thumbnailsGenerator = $thumbnailsGenerator;
    }

    private function isAlternative(\SplFileInfo $file): bool
    {
        return \in_array(AlternativeFileTrait::class, \class_uses(\get_class($file)));
    }

    /**
     * This method is going to be invoked recursively to generate thumbnails, classes with AlternativeFileTrait are
     * going to serve as markers that thumbnail needs to be generated.
     */
    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
        $this->doPut($storedFile, $file, $repository);
    }

    public function doPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository): string
    {
        /** @var array{'thumbnail_sizes'?: array<array{'width': int, 'height': int}>} $repoConfig */
        $repoConfig = $storedFile->getRepository()->getConfig();

        if (!isset($repoConfig['thumbnail_sizes']) || 0 === \count($repoConfig['thumbnail_sizes'])) {
            return self::RESULT_NO_CONFIG_AVAILABLE;
        }

        $isAlternative = $this->isAlternative($file);

        // given $storedFile and $file could be alternative already, so we need to resolve
        // an original file that we are going to use to generate a thumbnail from
        if ($isAlternative) {
            /** @var AlternativeFile $alternativeFile */
            $alternativeFile = $file;
            $originalStoredFile = $alternativeFile->getOriginalStoredFile();
            $originalFile = $alternativeFile->getOriginalFile();

            $originalStoredFile->addAlternative($storedFile);
        } else {
            $originalStoredFile = $storedFile;
            $originalFile = $file;
        }

        if (!$originalFile instanceof File) {
            // "File" class provides the API we need (like guessing MIME type)
            $originalFile = new File($originalFile->getPathname());
        }

        $isImage = 'image/' === \substr($originalFile->getMimeType() ?? '', 0, \strlen('image/'));
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
            /** @var AlternativeFile $alternativeFile */
            $alternativeFile = $file;
            $this->thumbnailsGenerator->updateStoredFileAlternativeMeta($storedFile, $alternativeFile->getThumbnailConfig());
        }

        // getting next thumbnail's config that needs to be generated
        /** @var ?array{'width': int, 'height': int} $thumbnailConfig */
        $thumbnailConfig = \array_shift($this->thumbnailsProgress[$lookupKey]);
        if (!$thumbnailConfig) {
            // making it possible to have thumbnails generated if the same original file is passed to file repository
            unset($this->thumbnailsProgress[$lookupKey]);

            return self::RESULT_NO_MORE_THUMBNAILS;
        }

        $thumbnailPathname = $this->generateThumbnail($originalFile, $storedFile, $thumbnailConfig);

        if ($originalFile instanceof UploadedFile) {
            $newFile = new AlternativeUploadedFile(
                $thumbnailPathname, // we need to save a thumbnail to the same file repository
                $originalFile->getClientOriginalName() ?: '',
                $originalFile->getClientMimeType()
            );
            $size = \filesize($thumbnailPathname);
            if (false === $size) {
                $size = null;
            }
            $newFile->setClientSize($size);
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
     * @param array{'width': int, 'height': int} $config
     *
     * @return string A pathname where thumbnail is saved
     */
    protected function generateThumbnail(File $originalFile, StoredFile $storedFile, array $config): string
    {
        return $this->thumbnailsGenerator->generate($originalFile, $config['width'], $config['height']);
    }
}
