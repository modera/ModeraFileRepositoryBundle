<?php

namespace Modera\FileRepositoryBundle\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Simplifies file validation using native Symfony constraints.
 *
 * @see http://symfony.com/doc/current/book/validation.html#file-constraints
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2015 Modera Foundation
 */
class FileWrapper
{
    protected \SplFileInfo $file;

    /**
     * @var Constraint[]
     */
    protected static array $constraints = [];

    /**
     * @param \SplFileInfo $file        A file that is being uploaded to a repository
     * @param Constraint[] $constraints Instances of \Symfony\Component\Validator\Constraint
     */
    public function __construct(\SplFileInfo $file, array $constraints = [])
    {
        $this->file = $file;
        self::$constraints = $constraints;
    }

    public function getFile(): \SplFileInfo
    {
        return $this->file;
    }

    /**
     * @return Constraint[]
     */
    public function getConstraints(): array
    {
        return self::$constraints;
    }

    /**
     * Adds an Image constraint.
     *
     * @see http://symfony.com/doc/current/reference/constraints/File.html
     *
     * @param array<mixed> $options
     */
    public function addImageConstraint(array $options = []): void
    {
        self::$constraints[] = new Assert\Image($options);
    }

    /**
     * Adds a File constraint.
     *
     * @see http://symfony.com/doc/current/reference/constraints/Image.html
     *
     * @param array<mixed> $options
     */
    public function addFileConstraint(array $options = []): void
    {
        self::$constraints[] = new Assert\File($options);
    }

    public function validate(ValidatorInterface $validator): ConstraintViolationListInterface
    {
        return $validator->validate($this);
    }

    /**
     * @private
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        foreach (self::$constraints as $constraint) {
            $metadata->addPropertyConstraint('file', $constraint);
        }
    }
}
