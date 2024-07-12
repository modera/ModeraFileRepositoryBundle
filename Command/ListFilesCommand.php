<?php

namespace Modera\FileRepositoryBundle\Command;

use Modera\FileRepositoryBundle\Repository\FileRepository;
use Modera\FileRepositoryBundle\Util\StoredFileUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected function configure(): void
    {
        $this
            ->setName('modera:file-repository:list-files')
            ->setDescription('Allows to see files in a repository')
            ->addArgument('repository-name', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $repositoryName */
        $repositoryName = $input->getArgument('repository-name');
        $repository = $this->fr->getRepository($repositoryName);

        if (!$repository) {
            throw new \RuntimeException(\sprintf('Unable to find a repository with given name "%s"!', $repositoryName));
        }

        $rows = [];
        foreach ($repository->getFiles() as $storedFile) {
            $rows[] = [
                $storedFile->getId(),
                $storedFile->getFilename(),
                $storedFile->getMimeType(),
                StoredFileUtils::formatFileSize($storedFile->getSize()),
                $storedFile->getCreatedAt()->format('d.m.Y H:i'),
                $storedFile->getOwner(),
            ];
        }

        $this->renderTable(
            $output,
            ['#', 'Filename', 'Mime type', 'Size', 'Created', 'Owner'],
            $rows
        );

        return 0;
    }
}
