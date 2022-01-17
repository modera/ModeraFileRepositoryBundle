<?php

namespace Modera\FileRepositoryBundle\UrlGeneration;

use Modera\FileRepositoryBundle\Entity\StoredFile;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2015 Modera Foundation
 */
interface UrlGeneratorInterface
{
    /**
     * @param StoredFile $storedFile
     * @param $type
     *
     * @return string
     */
    public function generateUrl(StoredFile $storedFile, $type);
}
