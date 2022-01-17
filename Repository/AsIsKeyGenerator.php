<?php

namespace Modera\FileRepositoryBundle\Repository;

/**
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
class AsIsKeyGenerator implements StorageKeyGeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generateStorageKey(\SplFileInfo $file, array $context = array())
    {
        return $file->getFilename();
    }
}