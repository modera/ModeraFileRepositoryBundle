<?php

namespace Modera\FileRepositoryBundle\Authoring;

use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Modera\FileRepositoryBundle\Intercepting\BaseOperationInterceptor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * If a user is authenticated when files are added to repository then we will try to set that
 * user as their author.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2017 Modera Foundation
 */
class AuthoringInterceptor extends BaseOperationInterceptor
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStore)
    {
        $this->tokenStorage = $tokenStore;
    }

    public function onPut(StoredFile $storedFile, \SplFileInfo $file, Repository $repository, array $context = []): void
    {
        $isAuthorManuallyOverriddenDuringFileCreation = isset($context['author']);
        if ($isAuthorManuallyOverriddenDuringFileCreation) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!\is_object($user)) {
            return;
        }

        $reflClass = new \ReflectionClass(\get_class($user));
        if ($reflClass->hasMethod('getId') && $reflClass->getMethod('getId')->isPublic()) {
            $reflMethod = $reflClass->getMethod('getId');

            $author = $reflMethod->invoke($user);
            if (\is_int($author)) {
                $author = (string) $author;
            } else {
                $author = null;
            }

            $storedFile->setAuthor($author);
        }
    }
}
