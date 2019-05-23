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
     * @var int
     */
    private $size;

    /**
     * Returns the file size.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @deprecated since Symfony 4.1, use getSize() instead.
     *
     * @return int|null The file sizes
     */
    public function getClientSize()
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1. Use getSize() instead.', __METHOD__), E_USER_DEPRECATED);

        return $this->size ?: $this->getSize();
    }

    /**
     * @internal
     * @param int|null $size
     */
    public function setClientSize($size = null)
    {
        $this->size = $size;
    }

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
