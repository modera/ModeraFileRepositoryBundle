<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Modera\FileRepositoryBundle\Entity\StoredFile;

/**
 * @internal
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
trait AlternativeFileTrait
{
    private \SplFileInfo $originalFile;

    private StoredFile $originalStoredFile;

    /**
     * A thumbnail config that was used to product this file.
     *
     * @var array<mixed>
     */
    private array $thumbnailConfig = [];

    public function getOriginalFile(): \SplFileInfo
    {
        return $this->originalFile;
    }

    public function setOriginalFile(\SplFileInfo $originalFile): void
    {
        $this->originalFile = $originalFile;
    }

    public function getOriginalStoredFile(): StoredFile
    {
        return $this->originalStoredFile;
    }

    public function setOriginalStoredFile(StoredFile $originalStoredFile): void
    {
        $this->originalStoredFile = $originalStoredFile;
    }

    /**
     * @return array<mixed>
     */
    public function getThumbnailConfig(): array
    {
        return $this->thumbnailConfig;
    }

    /**
     * @param array<mixed> $thumbnailConfig
     */
    public function setThumbnailConfig(array $thumbnailConfig): void
    {
        $this->thumbnailConfig = $thumbnailConfig;
    }
}
