<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Symfony\Component\HttpFoundation\File\File;

/**
 * @internal
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class NotImageGivenException extends \RuntimeException
{
    /**
     * @var File
     */
    private $failedFile;

    /**
     * @return File
     */
    public function getFailedFile()
    {
        return $this->failedFile;
    }

    /**
     * @param File $file
     *
     * @return NotImageGivenException
     */
    public static function create(File $file)
    {
        $msg = sprintf(
            'File "%s" is not an image, expected mime type is image/*, given - ', $file->getPathname(), $file->getMimeType()
        );

        $me = new static($msg);
        $me->failedFile = $file;

        return $me;
    }
}
