<?php

namespace Modera\FileRepositoryBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gaufrette\Exception\FileNotFound;
use Modera\FileRepositoryBundle\DependencyInjection\ModeraFileRepositoryExtension;
use Modera\FileRepositoryBundle\File\Base64File;
use Modera\FileRepositoryBundle\UrlGeneration\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;

/**
 * When this entity is removed from database associated with it physical file be automatically removed as well.
 *
 * Instances of this class are not meant to be created directly, please use
 * {@class \Modera\FileRepositoryBundle\Repository\FileRepository::put} instead.
 *
 * @ORM\Entity
 *
 * @ORM\Table("modera_filerepository_storedfile")
 *
 * @ORM\HasLifecycleCallbacks
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class StoredFile
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
     * @ORM\ManyToOne(targetEntity="Repository", inversedBy="files")
     */
    private ?Repository $repository = null;

    /**
     * This is a filename that is used to identify this file in "filesystem".
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $storageKey = null;

    /**
     * Full filename. For example - /dir1/dir2/file.txt.
     *
     * @ORM\Column(type="string")
     */
    private ?string $filename = null;

    /**
     * Some additional metadata you may want to associate with this file.
     *
     * @var array<string, mixed>
     *
     * @ORM\Column(type="json")
     */
    private array $meta = [];

    /**
     * Some value that your application logic can understand and identify a user. It could be user entity id, for example.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $author = null;

    /**
     * Some tag that later can be used to figure what/who this stored file belongs to. It can be whatever value that your
     * logic can parse, no restrictions implied.
     *
     * @var mixed Mixed value
     */
    private $owner;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?\DateTimeInterface $createdAt = null;

    /**
     * File extension. For example, for file "file.jpg" this field will contain "jpg".
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $extension = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $mimeType = null;

    private ?ContainerInterface $container = null;

    /**
     * This field can be used by interceptors if they need to create a linked alternative representation of uploaded file.
     * For example, an original full size picture may have different size thumbnails linked to it.
     *
     * @see addAlternative()
     *
     * @ORM\ManyToOne(targetEntity="StoredFile", inversedBy="alternatives", cascade={"PERSIST"})
     */
    private ?StoredFile $alternativeOf = null;

    /**
     * @see addAlternative()
     *
     * @var Collection<int, StoredFile>
     *
     * @ORM\OneToMany(targetEntity="StoredFile", mappedBy="alternativeOf", cascade={"PERSIST", "REMOVE"})
     */
    private ?Collection $alternatives = null;

    /**
     * Sometimes it might happen that a physical file has already been deleted when an entity
     * is deleted and in this case deleting an entity fill also fail, in order to allow
     * an entity without a physical file to be deleted this value is set to TRUE.
     *
     * @see setIgnoreMissingFileOnDelete
     */
    private bool $isMissingFileIgnoredOnDelete = true;

    /**
     * @ORM\Column(type="integer")
     */
    private int $position = 0;

    /**
     * @param array<mixed> $context
     */
    public function __construct(Repository $repository, \SplFileInfo $file, array $context = [])
    {
        $this->getAlternatives();
        $this->getCreatedAt();

        $this->repository = $repository;

        $this->storageKey = $repository->generateStorageKey($file, $context);
        if (!$this->storageKey) {
            throw new \RuntimeException('No storage key has been generated!');
        }

        $this->filename = $file->getFilename();
        $this->extension = $file->getExtension();

        if ($file instanceof File || $file instanceof Base64File) {
            $this->mimeType = $file->getMimeType();
        }
        if ($file instanceof UploadedFile) {
            $this->filename = $file->getClientOriginalName();
            $this->extension = $file->getClientOriginalExtension();
        }

        if (\is_string($context['author'] ?? null)) {
            $this->author = $context['author'];
        }
        if (isset($context['owner'])) {
            $this->owner = $context['owner'];
        }
    }

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
     * @return Collection<int, StoredFile>
     */
    public function getAlternatives(): Collection
    {
        if (null === $this->alternatives) {
            $this->alternatives = new ArrayCollection();
        }

        return $this->alternatives;
    }

    public function addAlternative(StoredFile $alternative): void
    {
        $this->getAlternatives()->add($alternative);
        $alternative->setAlternativeOf($this);
    }

    public function getAlternativeOf(): ?StoredFile
    {
        return $this->alternativeOf;
    }

    public function setAlternativeOf(?StoredFile $alternativeOf = null): void
    {
        $this->alternativeOf = $alternativeOf;
    }

    public function getUrl(int $type = RouterInterface::NETWORK_PATH): string
    {
        $urlGenerator = null;

        $container = $this->getContainer();

        /** @var string $defaultUrlGenerator */
        $defaultUrlGenerator = $container->getParameter(
            ModeraFileRepositoryExtension::CONFIG_KEY.'.default_url_generator'
        );

        /** @var array<string, string> $urlGenerators */
        $urlGenerators = $container->getParameter(
            ModeraFileRepositoryExtension::CONFIG_KEY.'.url_generators'
        );

        /** @var array{'filesystem': string} $config */
        $config = $this->getRepository()->getConfig();
        if (\is_string($urlGenerators[$config['filesystem']] ?? null)) {
            /** @var UrlGeneratorInterface $urlGenerator */
            $urlGenerator = $container->get($urlGenerators[$config['filesystem']]);
        }

        if (!$urlGenerator instanceof UrlGeneratorInterface) {
            /** @var UrlGeneratorInterface $urlGenerator */
            $urlGenerator = $container->get($defaultUrlGenerator);
        }

        return $urlGenerator->generateUrl($this, $type);
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

    /**
     * This method is not meant to be used directly.
     *
     * @internal
     *
     * @ORM\PreRemove
     */
    public function onRemove(): void
    {
        if (!$this->repository || !$this->storageKey) {
            return;
        }

        try {
            $this->repository->getFilesystem()->delete($this->storageKey);
        } catch (FileNotFound $e) {
            if (!$this->isMissingFileIgnoredOnDelete()) {
                throw $e;
            }
        }
    }

    public function getContents(): string
    {
        if (!$this->repository || !$this->storageKey) {
            return '';
        }

        return $this->repository->getFilesystem()->read($this->storageKey);
    }

    public function getSize(): int
    {
        if (!$this->repository || !$this->storageKey) {
            return 0;
        }

        return $this->repository->getFilesystem()->size($this->storageKey);
    }

    public function getChecksum(): string
    {
        if (!$this->repository || !$this->storageKey) {
            return '';
        }

        return $this->repository->getFilesystem()->checksum($this->storageKey);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function mergeMeta(array $meta): void
    {
        $this->setMeta(\array_merge($this->getMeta(), $meta));
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setCreatedAt(\DateTimeInterface $date): void
    {
        $this->createdAt = $date;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTime('now');
        }

        return $this->createdAt;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return mixed Mixed value
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param mixed $owner Mixed value
     */
    public function setOwner($owner): void
    {
        $this->owner = $owner;
    }

    public function getRepository(): Repository
    {
        if (!$this->repository) {
            throw new \RuntimeException('repository not defined');
        }

        return $this->repository;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey ?? '';
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function setIgnoreMissingFileOnDelete(bool $ignoreMissingFileOnDelete): void
    {
        $this->isMissingFileIgnoredOnDelete = $ignoreMissingFileOnDelete;
    }

    public function isMissingFileIgnoredOnDelete(): bool
    {
        return $this->isMissingFileIgnoredOnDelete;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
