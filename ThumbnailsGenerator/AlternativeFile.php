<?php

namespace Modera\FileRepositoryBundle\ThumbnailsGenerator;

use Symfony\Component\HttpFoundation\File\File;

/**
 * @internal
 *
 * Marker class, used in Interceptor class
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class AlternativeFile extends File
{
    use AlternativeFileTrait;
}
