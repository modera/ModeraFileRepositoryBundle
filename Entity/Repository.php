<?php

namespace Modera\FileRepositoryBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gaufrette\Filesystem;
use Modera\FileRepositoryBundle\Exceptions\InvalidRepositoryConfig;
use Modera\FileRepositoryBundle\Filesystem\FilesystemMapInterface;
use Modera\FileRepositoryBundle\Intercepting\DefaultInterceptorsProvider;
use Modera\FileRepositoryBundle\Intercepting\OperationInterceptorInterface;
use Modera\FileRepositoryBundle\Repository\StorageKeyGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Every repository is associated with one underlying Gaufrette filesystem.
 *
 * @ORM\Entity
 *
 * @ORM\Table(
 *     name="modera_filerepository_repository",
 *     indexes={
 *
 *         @ORM\Index(name="name_idx", columns={"name"})
 *     }
 * )
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class Repository
{
    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;

    /**
     * Stores configuration for this repository. Some standard configuration properties:.
     *
     *  * filesystem  -- DI service ID which points to \Gaufrette\Filesystem which will be used to store files for
     *                   this repository.
     *  * storage_key_generator -- DI service ID of class which implements {@class StorageKeyGeneratorInterface}.
     *
     * @ORM\Column(type="json")
     *
     * @var array<mixed>
     */
    private array $config = [];

    /**
     * @ORM\Column(type="string")
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $label = null;

    /**
     * @var Collection<int, StoredFile>
     *
     * @ORM\OneToMany(targetEntity="StoredFile", mappedBy="repository", cascade={"PERSIST", "REMOVE"})
     */
    private ?Collection $files = null;

    /**
     * @ORM\Column(name="internal", type="boolean")
     */
    private bool $internal = false;

    private ?ContainerInterface $container = null;

    /**
     * @param array<mixed> $config
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->setConfig($config);
        $this->getFiles();
    }

    /**
     * @private
     */
    public function init(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    private function getContainer(): ContainerInterface
    {
        if (!$this->container) {
            throw new \RuntimeException('container not injected, call init method');
        }

        return $this->container;
    }

    /**
     * @return OperationInterceptorInterface[]
     */
    private function getInterceptors(): array
    {
        /** @var DefaultInterceptorsProvider $provider */
        $provider = $this->getContainer()->get('modera_file_repository.intercepting.interceptors_provider');

        return $provider->getInterceptors($this);
    }

    /**
     * @internal
     *
     * @param array<mixed> $context
     */
    public function beforePut(\SplFileInfo $file, ?callable $filter = null, array $context = []): void
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->beforePut($file, $this, $context);
            }
        }
    }

    /**
     * @internal
     *
     * @param array<mixed> $context
     */
    public function onPut(StoredFile $storedFile, \SplFileInfo $file, ?callable $filter = null, array $context = []): void
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->onPut($storedFile, $file, $this, $context);
            }
        }
    }

    /**
     * @internal
     *
     * @param array<mixed> $context
     */
    public function afterPut(StoredFile $storedFile, \SplFileInfo $file, ?callable $filter = null, array $context = []): void
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->afterPut($storedFile, $file, $this, $context);
            }
        }
    }

    /**
     * @deprecated Use native ::class property
     */
    public static function clazz(): string
    {
        @\trigger_error(\sprintf(
            'The "%s()" method is deprecated. Use native ::class property.',
            __METHOD__
        ), \E_USER_DEPRECATED);

        return \get_called_class();
    }

    public function getFilesystem(): Filesystem
    {
        /** @var FilesystemMapInterface $map */
        $map = $this->getContainer()->get('modera_file_repository.filesystem_map');

        /** @var string $filesystem */
        $filesystem = $this->config['filesystem'] ?? '';

        /** @var Filesystem $fs */
        $fs = $map->get($filesystem);

        return $fs;
    }

    /**
     * @param array<mixed> $context
     */
    public function generateStorageKey(\SplFileInfo $file, array $context): string
    {
        /** @var string $storageKeyGenerator */
        $storageKeyGenerator = $this->config['storage_key_generator'] ?? '';

        /** @var StorageKeyGeneratorInterface $generator */
        $generator = $this->getContainer()->get($storageKeyGenerator);

        return $generator->generateStorageKey($file, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function createFile(\SplFileInfo $file, array $context = []): StoredFile
    {
        $result = new StoredFile($this, $file, $context);
        $result->init($this->getContainer());
        $this->getFiles()->add($result);

        return $result;
    }

    /**
     * @param array<mixed> $config
     */
    public function setConfig(array $config): void
    {
        if (!isset($config['filesystem'])) {
            throw InvalidRepositoryConfig::create('filesystem', $config);
        }
        if (!isset($config['storage_key_generator'])) {
            $config['storage_key_generator'] = 'modera_file_repository.repository.uniqid_key_generator';
        }

        $this->config = $config;
    }

    // boilerplate:

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @private
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * @param Collection<int, StoredFile> $files
     */
    public function setFiles(Collection $files): void
    {
        $this->files = $files;
    }

    /**
     * @return Collection<int, StoredFile>
     */
    public function getFiles(): Collection
    {
        if (null === $this->files) {
            $this->files = new ArrayCollection();
        }

        return $this->files;
    }

    public function setInternal(bool $internal): void
    {
        $this->internal = $internal;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }
}
