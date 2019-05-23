<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Imagine\Image\ImageInterface;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @internal
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class ThumbnailsGenerator
{
    /**
     * @throws NotImageGivenException
     *
     * @param File   $image
     * @param int    $width
     * @param int    $height
     * @param string $mode   Either "inset" or "outbound", see ImageInterface::THUMBNAIL_* constants for more details
     *
     * @return string A path to a temporary file where thumbnail is saved
     */
    public function generate(File $image, $width, $height, $mode = null)
    {
        $isImage = substr($image->getMimeType(), 0, strlen('image/')) == 'image/';
        if (!$isImage) {
            throw NotImageGivenException::create($image);
        }

        $pathname = tempnam(sys_get_temp_dir(), 'thumbnail_').'.'.$image->guessExtension();

        $size = new Box($width, $height);
        if (null === $mode) {
            $mode = ImageInterface::THUMBNAIL_INSET;
        }

        $imagine = new Imagine();
        $imagine->open($image->getPathname())->thumbnail($size, $mode)->save($pathname);

        return $pathname;
    }

    /**
     * @param StoredFile $alternative
     * @param array      $thumbnailConfig
     */
    public function updateStoredFileAlternativeMeta(StoredFile $alternative, array $thumbnailConfig)
    {
        $alternative->mergeMeta(array('thumbnail' => $thumbnailConfig));
    }
}
