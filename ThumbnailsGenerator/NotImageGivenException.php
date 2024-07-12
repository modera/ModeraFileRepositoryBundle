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
    private File $failedFile;

    public function getFailedFile(): File
    {
        return $this->failedFile;
    }

    public static function create(File $file): self
    {
        $msg = \sprintf(
            'File "%s" is not an image, expected mime type is image/*, given - "%s"',
            $file->getPathname(),
            $file->getMimeType()
        );

        $me = new self($msg);
        $me->failedFile = $file;

        return $me;
    }
}
