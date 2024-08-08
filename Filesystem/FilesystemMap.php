<?php

namespace Modera\FileRepositoryBundle\Filesystem;

use Gaufrette\FilesystemInterface;
use Gaufrette\FilesystemMapInterface as GaufretteFilesystemMapInterface;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class FilesystemMap implements FilesystemMapInterface
{
    private GaufretteFilesystemMapInterface $filesystemMap;

    public function __construct(GaufretteFilesystemMapInterface $filesystemMap)
    {
        $this->filesystemMap = $filesystemMap;
    }

    public function has(string $name): bool
    {
        return $this->filesystemMap->has($name);
    }

    public function get(string $name): FilesystemInterface
    {
        return $this->filesystemMap->get($name);
    }
}
