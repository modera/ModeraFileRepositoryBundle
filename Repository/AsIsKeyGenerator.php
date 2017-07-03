<?php

namespace Modera\FileRepositoryBundle\Repository;

/**
 * @author Sergei Lissovski <sergei.lissovski@nowinnovations.com>
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