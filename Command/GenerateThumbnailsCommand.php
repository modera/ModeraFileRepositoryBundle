<?php

namespace Modera\FileRepositoryBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\File;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\NotImageGivenException;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\EmulatedUploadedFile;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\ThumbnailsGenerator;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\Interceptor;
use Modera\FileRepositoryBundle\Repository\FileRepository;
use Modera\FileRepositoryBundle\Entity\StoredFile;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class GenerateThumbnailsCommand extends Command
{
    private EntityManagerInterface $em;

    private FileRepository $fr;

    private ThumbnailsGenerator $generator;

    public function __construct(EntityManagerInterface $em, FileRepository $fr, ThumbnailsGenerator $generator)
    {
        $this->em = $em;
        $this->fr = $fr;
        $this->generator = $generator;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('modera:file-repository:generate-thumbnails')
            ->setDescription('Allows to generate thumbnails for already existing files.')
            ->addArgument('repository', InputArgument::REQUIRED, 'Technical name of a repository')
            ->addOption(
                'thumbnail',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Dimensions are to be delimited by x, for example - 300x200'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If given then no thumbnails will be generated but instead a report will be provided of what thumbnails are to be generated'
            )
            ->addOption(
                'update-config',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify "false" if you do not want to update repository\'s config so that uploaded files would have thumbnails generated automatically',
                true
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->fr->getRepository($input->getArgument('repository'));
        if (!$repository) {
            throw new \RuntimeException(sprintf(
                'Unable to find a repository with name "%s"', $input->getArgument('repository')
            ));
        }

        $expectedThumbnailsConfig = $input->getOption('thumbnail');

        // indexed by original file's ID
        $report = array();

        // fetching original files
        $query = sprintf('SELECT e.id FROM %s e WHERE e.alternativeOf IS NULL AND e.repository = ?0', StoredFile::class);
        $query = $this->em->createQuery($query);
        $query->setParameter(0, $repository);

        foreach ($query->getArrayResult() as $fileData) {
            $originalId = $fileData['id'];

            $existingThumbnails = [];
            $missingThumbnails = [];

            // fetching original file's alternatives
            $alternativesQuery = $this->em->createQuery(sprintf('SELECT e.id, e.meta FROM %s e WHERE e.alternativeOf = ?0', StoredFile::class));
            $alternativesQuery->setParameter(0, $fileData['id']);

            foreach ($alternativesQuery->getArrayResult() as $alternativeData) {
                if (isset($alternativeData['meta']['thumbnail'])) {
                    $thumbnailConfig = $alternativeData['meta']['thumbnail'];

                    if (isset($thumbnailConfig['width']) && isset($thumbnailConfig['height'])) {
                        $existingThumbnails[] = $thumbnailConfig['width'].'x'.$thumbnailConfig['height'];
                    }
                }
            }

            foreach ($expectedThumbnailsConfig as $expectedThumbnailDimensions) {
                if (!in_array($expectedThumbnailDimensions, $existingThumbnails)) {
                    $missingThumbnails[] = $expectedThumbnailDimensions;
                }
            }

            $report[$originalId] = array(
                'existing' => $existingThumbnails,
                'missing' => $missingThumbnails,
            );
        }

        if (count($report) == 0) {
            $output->writeln('No thumbnails to generate');

            return 0;
        }

        if ($input->getOption('dry-run')) {
            $headers = ['ID', 'Filename', 'Missing thumbnails', 'Existing thumbnails'];
            $rows = [];

            foreach ($report as $id => $entry) {
                /* @var StoredFile $storedFile */
                $storedFile = $this->em->getRepository(StoredFile::class)->find($id);

                $missingOnes = count($entry['missing']) > 0 ? implode(', ', $entry['missing']) : '-';
                $existingOnes = count($entry['existing']) > 0 ? implode(', ', $entry['existing']) : '-';

                $rows[] = [$id, $storedFile->getFilename(), $missingOnes, $existingOnes];
            }

            $table = new Table($output);
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->render();

            return 0;
        }

        foreach ($report as $id => $entry) {
            /* @var StoredFile $originalStoredFile */
            $originalStoredFile = $this->em->getRepository(StoredFile::class)->find($id);

            $output->writeln(sprintf(' # Processing (%d) %s', $originalStoredFile->getId(), $originalStoredFile->getFilename()));

            foreach ($entry['missing'] as $dimensions) {
                list($width, $height) = explode('x', $dimensions);

                $originalPathname = tempnam(sys_get_temp_dir(), 'file_');
                file_put_contents($originalPathname, $originalStoredFile->getContents());

                $image = new File($originalPathname);

                try {
                    $thumbnailPathname = $this->generator->generate($image, $width, $height);
                } catch (NotImageGivenException $e) {
                    $output->writeln('  * Skipping, file is not an image.');

                    continue;
                }

                // we need to use a subclass of UploadedFile class because it FileRepository
                // relies on its interface to properly determine mime, original mime type etc
                $thumbnailFile = new EmulatedUploadedFile(
                    $thumbnailPathname,
                    $originalStoredFile->getFilename(),
                    $originalStoredFile->getMimeType(),
                    filesize($thumbnailPathname)
                );

                $thumbnailStoredFile = $this->fr->put(
                    $repository->getName(),
                    $thumbnailFile,
                    array(
                        'put_interceptor_filter' => function ($itc) {
                            // we are disabling thumbnails-generator-filter because if
                            // a repository has already this interceptor configured then putting thumbnails
                            // into repository will result in attempts to generate thumbnails for thumbnails ...
                            return !$itc instanceof Interceptor;
                        },
                    )
                );

                $this->generator->updateStoredFileAlternativeMeta(
                    $thumbnailStoredFile,
                    array('width' => $width, 'height' => $height)
                );

                $originalStoredFile->addAlternative($thumbnailStoredFile);

                // we don't need to keep a temporary file because file-repository by now should have
                // already stored a thumbnail file in its FS
                unlink($thumbnailPathname);

                $this->em->flush();

                $output->writeln(sprintf('  * %dx%d', $width, $height));
            }
        }

        if ($input->getOption('update-config') == true) {
            $isInterceptorAdded = false;
            $isThumbnailConfigUpdated = false;

            $repositoryConfig = $repository->getConfig();
            if (!isset($repositoryConfig['interceptors'])) {
                $repositoryConfig['interceptors'] = [];
            }
            if (!in_array(Interceptor::ID, $repositoryConfig['interceptors'])) {
                $repositoryConfig['interceptors'][] = Interceptor::ID;

                $isInterceptorAdded = true;
            }

            if (!isset($repositoryConfig['thumbnail_sizes'])) {
                $repositoryConfig['thumbnail_sizes'] = [];
            }

            $existingThumbnailsConfigEntries = [];
            foreach ($repositoryConfig['thumbnail_sizes'] as $thumbnailConfig) {
                if (isset($thumbnailConfig['width']) && isset($thumbnailConfig['height'])) {
                    $existingThumbnailsConfigEntries[] = $thumbnailConfig['width'].'x'.$thumbnailConfig['height'];
                }
            }

            foreach ($expectedThumbnailsConfig as $dimensions) {
                list($width, $height) = explode('x', $dimensions);

                if (!in_array($dimensions, $existingThumbnailsConfigEntries)) {
                    $repositoryConfig['thumbnail_sizes'][] = array(
                        'width' => $width,
                        'height' => $height,
                    );

                    $isThumbnailConfigUpdated = true;
                }
            }

            $repository->setConfig($repositoryConfig);

            $this->em->flush();

            $output->writeln(
                $isInterceptorAdded ? 'Interceptor integrated into repository' : 'Interceptor is already has been registered before, skipping ...'
            );
            $output->writeln(
                $isThumbnailConfigUpdated ? 'Thumbnails config updated for repository' : 'Repository already contains necessary thumbnails config, skipping ...'
            );
        }

        return 0;
    }
}
