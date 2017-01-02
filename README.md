# ModeraFileRepositoryBundle

[![Build Status](https://travis-ci.org/modera/foundation.svg?branch=master)](https://travis-ci.org/modera/foundation)
[![StyleCI](https://styleci.io/repos/19245390/shield)](https://styleci.io/repos/19245390)

This bundle provides a high level API for putting your files to virtual file repositories which internally use Gaufrette
filesystem abstraction layer.

## Installation

Add this dependency to your composer.json:

    "modera/file-repository-bundle": "~1.0"

Update your AppKernel class and add these bundles there:

    new Knp\Bundle\GaufretteBundle\KnpGaufretteBundle(), // if you still don't have it
    new Modera\FileRepositoryBundle\ModeraFileRepositoryBundle(),

And finally check your `config.yml` and make sure that validation service is enabled:

    framework:
        validation: ~

## Documentation

This bundle proves useful when you need to have a consistent way of storing your files with an ability
to later reference these files in your domain model or query them (using Doctrine ORM). Configuration
process consists of two steps:

 * Configuring Gaufrette filesystem adapter
 * Creating a virtual repository

This is a sample filesystem configuration using Gaufrette which creates a filesystem which will use local
`/path/to/my/filesystem` path to store files:

    # app/config/config.yml
    knp_gaufrette:
        adapters:
            local_fs:
                local:
                    directory: /path/to/my/filesystem
        filesystems:
            local_fs:
                adapter:    local_fs

Once low-level filesystem is configured you can create a repository that will manage your files:

    /* @var \Modera\FileRepositoryBundle\Repository\FileRepository $fr */
    $fr = $container->get('modera_file_repository.repository.file_repository');

    $repositoryConfig = array(
        'filesystem' => 'local_fs'
    );

    $fr->createRepository('my_repository', $repositoryConfig, 'My dummy repository');

    $dummyFile = new \SplFileInfo('dummy-file.txt');

    /* @var \Modera\FileRepositoryBundle\Entity\StoredFile $storedFile */
    $storedFile = $fr->put('my_repository', $dummyFile);

When a physical file is put to a repository its descriptor record is created in database that later you can use
in your domain logic. For example, having a Doctrine entity which represents a physical may prove useful when you
have a user and you want to associate a profile picture with that user. Also it is worth mentioning that once StoredFile
entity is removed, its physical file stored in a configured filesystem will be automatically removed as well. This
descriptive record saved in database contains a bunch of useful information like mime-type, file extension etc, please
see StoredFile's entity fields for more details.

### Repository configuration

When you create a repository you can use these configuration properties to tweak behaviour of your repository:

 * filesystem -- Gaufrette's filesystem name that this repository should use to store files
 * storage_key_generator -- DI service ID of class which implements `Modera\FileRepositoryBundle\Repository\StorageKeyGeneratorInterface`
                            interface. This class is used to generate filenames that will be used by filesystem to store
                            files. If this configuration property is not provided when repository is created then
                            `Modera\FileRepositoryBundle\Repository\UniqidKeyGenerator` class will be used.
 * images_only  -- if set to TRUE then it only will be possible to upload images to a repository.
 * max_size -- if specified it won't be possible to upload files whose size exceeds given value. For megabytes use "m" prefix,
               for kilobytes - "k" and if no prefix is provided then bytes will be used, for example: 100k, 5m, 800.
 * file_constraint -- Configuration options of [File](http://symfony.com/doc/current/reference/constraints/File.html)
                     constraint.
 * image_constraint -- Configuration options of [Image](http://symfony.com/doc/current/reference/constraints/Image.html)
                       constraint.
 * interceptors -- allows to specify additional interceptors to use, values must be service container IDs

### Command line

Bundle ships commands that allow you to perform some standards operations on your repositories and files:

 * modera:file-repository:create
 * modera:file-repository:list
 * modera:file-repository:delete-repository
 * modera:file-repository:put-file
 * modera:file-repository:list-files
 * modera:file-repository:download-file
 * modera:file-repository:delete-file
 * modera:file-repository:generate-thumbnails
 
### Thumbnails generation

Bundle contains an interceptor that you can use to have thumbnails automatically generated for images when they
are stored in a repository, to enable this feature when creating a new repository you need to use
**modera_file_repository.interceptors.thumbnails_generator.interceptor** interceptor:

    /* @var \Modera\FileRepositoryBundle\Repository\FileRepository $fr */
    $fr = $container->get('modera_file_repository.repository.file_repository');

    $repositoryConfig = array(
        'filesystem' => 'local_fs',
        'interceptors' => [
            \Modera\FileRepositoryBundle\ThumbnailsGenerator\Interceptor::ID,
        ],
        'thumbnail_sizes' => array(
            array(
                'width' => 300,
                'height' => 150
            ),
            array(
                'width' => 32,
                'height' => 32
            )
        )
    );

    $fr->createRepository('vacation_pictures', $repositoryConfig, 'Pictures from vacation');
    
Configuration key "thumbnail_sizes" can be used to specify what thumbnail size you need to have. With this configuration
whenever a picture is uploaded to a repository alternative will be created, for more details see StoredFile::$alternatives,
StoredFile::$alternativeOf properties. If you already have a repository and you want to generate thumbnails for it
then use `modera:file-repository:generate-thumbnails` command.

## Licensing

This bundle is under the MIT license. See the complete license in the bundle:
Resources/meta/LICENSE
