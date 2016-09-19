<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 *
 * Marker class, used in Interceptor class
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class AlternativeUploadedFile extends UploadedFile
{
    use AlternativeFileTrait;

    /**
     * We can afford this behaviour because file's validity is determined by original file and its alternative
     * should omit any validation (originally it checks if file is uploaded and in case of thumbnails this will
     * fail by definition, because we create them manually).
     */
    public function isValid()
    {
        $original = $this->getOriginalFile();

        return $original instanceof File ? $original->isValid() : true;
    }
}
