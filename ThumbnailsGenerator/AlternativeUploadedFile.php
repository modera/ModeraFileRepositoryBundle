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

    private ?int $size = null;

    /**
     * Returns the file size.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @deprecated since Symfony 4.1, use getSize() instead.
     */
    public function getClientSize(): ?int
    {
        @\trigger_error(\sprintf('The "%s()" method is deprecated since Symfony 4.1. Use getSize() instead.', __METHOD__), E_USER_DEPRECATED);

        $size = $this->size ?: $this->getSize();
        if (false === $size) {
            $size = null;
        }

        return $size;
    }

    /**
     * @internal
     */
    public function setClientSize(?int $size = null): void
    {
        $this->size = $size;
    }

    /**
     * We can afford this behaviour because file's validity is determined by original file and its alternative
     * should omit any validation (originally it checks if file is uploaded and in case of thumbnails this will
     * fail by definition, because we create them manually).
     */
    public function isValid(): bool
    {
        $original = $this->getOriginalFile();

        if ($original instanceof File) {
            if (\method_exists($original, 'isValid')) {
                return $original->isValid();
            }
        }

        return true;
    }
}
