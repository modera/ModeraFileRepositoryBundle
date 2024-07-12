<?php

namespace Modera\FileRepositoryBundle\Repository;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class UniqidKeyGenerator implements StorageKeyGeneratorInterface
{
    private bool $preserveExtension;

    /**
     * @param bool $preserveExtension If this parameter is set to TRUE then when a filename is generated original's file
     *                                extension will be added to the new filename
     */
    public function __construct(bool $preserveExtension = false)
    {
        $this->preserveExtension = $preserveExtension;
    }

    public function generateStorageKey(\SplFileInfo $file, array $context = []): string
    {
        return \uniqid().($this->preserveExtension ? '.'.$file->getExtension() : '');
    }
}
