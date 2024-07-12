<?php

namespace Modera\FileRepositoryBundle\Repository;

/**
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
class AsIsKeyGenerator implements StorageKeyGeneratorInterface
{
    public function generateStorageKey(\SplFileInfo $file, array $context = []): string
    {
        return $file->getFilename();
    }
}
