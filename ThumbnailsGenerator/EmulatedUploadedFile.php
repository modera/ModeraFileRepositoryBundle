<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 *
 * Used by IntegrateThumbnailsGeneratorCommand
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class EmulatedUploadedFile extends UploadedFile
{
    /**
     * We don't really need to validate is a file has been properly uploaded because this class acts as a wrapper
     * for a trusted file coming from local FS.
     */
    public function isValid()
    {
        return true;
    }
}
