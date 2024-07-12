<?php

namespace Modera\FileRepositoryBundle\Intercepting;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;

/**
 * Implementations of this interface will be able to perform additional actions
 * when a file is being uploaded to a repository.
 *
 * NB! Since 2.56.0 all methods declared below accept an additional parameter "$context", it can be accessed using
 * func_get_arg() function. We cannot change the signature of the methods here because it would break the BC. See
 * \Modera\FileRepositoryBundle\Authoring\AuthoringInterceptor class for a working example.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2015 Modera Foundation
 */
interface OperationInterceptorInterface
{
    /**
     * Throwing an exception in this method will prevent a file from being uploaded to a repository.
     *
     * @param array<mixed> $context
     */
    public function beforePut(\SplFileInfo $file, Repository $repository, array $context = []): void;

    /**
     * Method is invoked when a StoredFile is configured, but before it is persisted into storage.
     *
     * @param array<mixed> $context
     */
    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void;

    /**
     * Method is invoked when a file is uploaded and $storedFile has been successfully persisted.
     * Changes that you do $storedFile here won't be automatically persisted to database.
     *
     * @param array<mixed> $context
     */
    public function afterPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void;
}
