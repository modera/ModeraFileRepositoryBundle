<?php

namespace Modera\FileRepositoryBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\FileRepositoryBundle\Repository\FileRepository;
use Modera\FileRepositoryBundle\Util\StoredFileUtils;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ListFilesCommand extends Command
{
    use TableTrait;

    private FileRepository $fr;

    public function __construct(FileRepository $fr)
    {
        $this->fr = $fr;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('modera:file-repository:list-files')
            ->setDescription('Allows to see files in a repository')
            ->addArgument('repository-name', InputArgument::REQUIRED)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repositoryName = $input->getArgument('repository-name');
        $repository = $this->fr->getRepository($repositoryName);

        if (!$repository) {
            throw new \RuntimeException(sprintf('Unable to find a repository with given name "%s"!', $repositoryName));
        }

        $rows = array();
        foreach ($repository->getFiles() as $storedFile) {
            $rows[] = array(
                $storedFile->getId(),
                $storedFile->getFilename(),
                $storedFile->getMimeType(),
                StoredFileUtils::formatFileSize($storedFile->getSize()),
                $storedFile->getCreatedAt()->format('d.m.Y H:i'),
                $storedFile->getOwner(),
            );
        }

        $this->renderTable(
            $output,
            ['#', 'Filename', 'Mime type', 'Size', 'Created', 'Owner'],
            $rows
        );

        return 0;
    }
}
