<?php

namespace Modera\FileRepositoryBundle\Exceptions;

use Modera\FileRepositoryBundle\Entity\Repository;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2015 Modera Foundation
 */
class FileValidationException extends \RuntimeException
{
    private \SplFileInfo $validatedFile;

    private ?Repository $repository;

    /**
     * @var string[]
     */
    private array $errors;

    /**
     * @param ConstraintViolationListInterface|ConstraintViolationInterface[]|string[] $errors
     */
    public static function create(\SplFileInfo $validatedFile, $errors, ?Repository $repository = null): self
    {
        $parsedErrors = [];
        foreach ($errors as $error) {
            if ($error instanceof ConstraintViolationInterface) {
                $parsedErrors[] = $error->getMessage();
            } else {
                $parsedErrors[] = (string) $error;
            }
        }

        $me = new self('File validation failed: '.\implode(', ', $parsedErrors));
        $me->validatedFile = $validatedFile;
        $me->errors = $parsedErrors;
        $me->repository = $repository;

        return $me;
    }

    public function getValidatedFile(): \SplFileInfo
    {
        return $this->validatedFile;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }
}
