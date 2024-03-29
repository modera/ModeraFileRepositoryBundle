<?php

namespace Modera\FileRepositoryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gaufrette\Exception\FileNotFound;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;
use Modera\FileRepositoryBundle\DependencyInjection\ModeraFileRepositoryExtension;
use Modera\FileRepositoryBundle\File\Base64File;
use Modera\FileRepositoryBundle\UrlGeneration\UrlGeneratorInterface;

/**
 * When this entity is removed from database associated with it physical file be automatically removed as well.
 *
 * Instances of this class are not meant to be created directly, please use
 * {@class \Modera\FileRepositoryBundle\Repository\FileRepository::put} instead.
 *
 * @ORM\Entity
 * @ORM\Table("modera_filerepository_storedfile")
 * @ORM\HasLifecycleCallbacks
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class StoredFile
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Repository
     *
     * @ORM\ManyToOne(targetEntity="Repository", inversedBy="files")
     */
    private $repository;

    /**
     * This is a filename that is used to identify this file in "filesystem".
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $storageKey;

    /**
     * Full filename. For example - /dir1/dir2/file.txt.
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $filename;

    /**
     * Some additional metadata you may want to associate with this file.
     *
     * @ORM\Column(type="array")
     */
    private $meta = array();

    /**
     * Some value that your application logic can understand and identify a user. It could be user entity id, for example.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $author;

    /**
     * Some tag that later can be used to figure what/who this stored file belongs to. It can be whatever value that your
     * logic can parse, no restrictions implied.
     */
    private $owner;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * File extension. For example, for file "file.jpg" this field will contain "jpg".
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $extension;

    /**
     * @var null|string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $mimeType;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * This field can be used by interceptors if they need to create a linked alternative representation of uploaded file.
     * For example, an original full size picture may have different size thumbnails linked to it.
     *
     * @see addAlternative()
     *
     * @ORM\ManyToOne(targetEntity="StoredFile", inversedBy="alternatives", cascade={"PERSIST"})
     */
    private $alternativeOf;

    /**
     * @see addAlternative()
     *
     * @ORM\OneToMany(targetEntity="StoredFile", mappedBy="alternativeOf", cascade={"PERSIST", "REMOVE"})
     */
    private $alternatives;

    /**
     * Sometimes it might happen that a physical file has already been deleted when an entity
     * is deleted and in this case deleting an entity fill also fail, in order to allow
     * an entity without a physical file to be deleted this value is set to TRUE.
     *
     * @see setIgnoreMissingFileOnDelete
     *
     * @since 2.56.0
     *
     * @var bool
     */
    private $isMissingFileIgnoredOnDelete = true;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $position = 0;

    /**
     * @param Repository   $repository
     * @param \SplFileInfo $file
     * @param array        $context
     */
    public function __construct(Repository $repository, \SplFileInfo $file, array $context = array())
    {
        $this->repository = $repository;

        $this->storageKey = $repository->generateStorageKey($file, $context);
        if (!$this->storageKey) {
            throw new \RuntimeException('No storage key has been generated!');
        }

        $this->createdAt = new \DateTime('now');

        $this->filename = $file->getFilename();
        $this->extension = $file->getExtension();

        $this->alternatives = new ArrayCollection();

        if ($file instanceof File || $file instanceof Base64File) {
            $this->mimeType = $file->getMimeType();
        }
        if ($file instanceof UploadedFile) {
            $this->filename = $file->getClientOriginalName();
            $this->extension = $file->getClientOriginalExtension();
        }

        if (isset($context['author'])) {
            $this->author = $context['author'];
        }
        if (isset($context['owner'])) {
            $this->owner = $context['owner'];
        }
    }

    /**
     * @param StoredFile $alternative
     */
    public function addAlternative(StoredFile $alternative)
    {
        $this->alternatives->add($alternative);
        $alternative->setAlternativeOf($this);
    }

    /**
     * @return StoredFile
     */
    public function getAlternativeOf()
    {
        return $this->alternativeOf;
    }

    /**
     * @param mixed $alternativeOf
     */
    public function setAlternativeOf(StoredFile $alternativeOf = null)
    {
        $this->alternativeOf = $alternativeOf;
    }

    /**
     * @return StoredFile[]
     */
    public function getAlternatives()
    {
        return $this->alternatives;
    }

    /**
     * @param ContainerInterface $container
     */
    public function init(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getUrl($type = RouterInterface::NETWORK_PATH)
    {
        $urlGenerator = null;
        $defaultUrlGenerator = $this->container->getParameter(
            ModeraFileRepositoryExtension::CONFIG_KEY.'.default_url_generator'
        );

        $urlGenerators = $this->container->getParameter(
            ModeraFileRepositoryExtension::CONFIG_KEY.'.url_generators'
        );

        $config = $this->getRepository()->getConfig();
        if (isset($urlGenerators[$config['filesystem']])) {
            /* @var UrlGeneratorInterface $urlGenerator */
            $urlGenerator = $this->container->get($urlGenerators[$config['filesystem']]);
        }

        if (!$urlGenerator instanceof UrlGeneratorInterface) {
            /* @var UrlGeneratorInterface $urlGenerator */
            $urlGenerator = $this->container->get($defaultUrlGenerator);
        }

        return $urlGenerator->generateUrl($this, $type);
    }

    /**
     * @deprecated Use native ::class property
     *
     * @return string
     */
    public static function clazz()
    {
        @trigger_error(sprintf(
            'The "%s()" method is deprecated. Use native ::class property.',
            __METHOD__
        ), \E_USER_DEPRECATED);

        return get_called_class();
    }

    /**
     * This method is not meant to be used directly.
     *
     * @internal
     *
     * @ORM\PreRemove
     */
    public function onRemove()
    {
        try {
            $this->repository->getFilesystem()->delete($this->storageKey);
        } catch (FileNotFound $e) {
            if (!$this->isMissingFileIgnoredOnDelete()) {
                throw $e;
            }
        }
    }

    /**
     * @return string
     */
    public function getContents()
    {
        return $this->repository->getFilesystem()->read($this->storageKey);
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->repository->getFilesystem()->size($this->storageKey);
    }

    /**
     * @return string
     */
    public function getChecksum()
    {
        return $this->repository->getFilesystem()->checksum($this->storageKey);
    }

    /**
     * @param array $meta
     */
    public function mergeMeta(array $meta)
    {
        $this->setMeta(array_merge($this->getMeta(), $meta));
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param \DateTime $date
     */
    public function setCreatedAt(\DateTime $date)
    {
        $this->createdAt = $date;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param string $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * @param mixed $meta
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;
    }

    /**
     * @return null|string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @return mixed
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return \Modera\FileRepositoryBundle\Entity\Repository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return mixed
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * @since 2.56.0
     *
     * @param mixed $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @since 2.56.0
     *
     * @param mixed $owner
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @since 2.56.0
     *
     * @param bool $ignoreMissingFileOnDelete
     */
    public function setIgnoreMissingFileOnDelete($ignoreMissingFileOnDelete)
    {
        $this->isMissingFileIgnoredOnDelete = $ignoreMissingFileOnDelete;
    }

    /**
     * @since 2.56.0
     *
     * @return bool
     */
    public function isMissingFileIgnoredOnDelete()
    {
        return $this->isMissingFileIgnoredOnDelete;
    }

    /**
     * @param int $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }
}
