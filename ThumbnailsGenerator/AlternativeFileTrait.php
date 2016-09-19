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
    /**
     * @var \SplFileInfo
     */
    private $originalFile;

    /**
     * @var StoredFile
     */
    private $originalStoredFile;

    /**
     * A thumbnail config that was used to product this file.
     *
     * @var array
     */
    private $thumbnailConfig = array();

    /**
     * @return \SplFileInfo
     */
    public function getOriginalFile()
    {
        return $this->originalFile;
    }

    /**
     * @param \SplFileInfo $originalFile
     */
    public function setOriginalFile($originalFile)
    {
        $this->originalFile = $originalFile;
    }

    /**
     * @return StoredFile
     */
    public function getOriginalStoredFile()
    {
        return $this->originalStoredFile;
    }

    /**
     * @param StoredFile $originalStoredFile
     */
    public function setOriginalStoredFile($originalStoredFile)
    {
        $this->originalStoredFile = $originalStoredFile;
    }

    /**
     * @return array
     */
    public function getThumbnailConfig()
    {
        return $this->thumbnailConfig;
    }

    /**
     * @param array $thumbnailConfig
     */
    public function setThumbnailConfig(array $thumbnailConfig)
    {
        $this->thumbnailConfig = $thumbnailConfig;
    }
}
