<?php

namespace Modera\FileRepositoryBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Modera\FileRepositoryBundle\Repository\FileRepository;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DeleteRepositoryCommand extends Command
{
    private EntityManagerInterface $em;

    private FileRepository $fr;

    public function __construct(EntityManagerInterface $em, FileRepository $fr)
    {
        $this->em = $em;
        $this->fr = $fr;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('modera:file-repository:delete-repository')
            ->setDescription('Deletes a repository with all its files')
            ->addArgument('repository', InputArgument::REQUIRED)
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

        if (count($repository->getFiles()) > 0) {
            /* @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelperSet()->get('question');

            $question = sprintf(
                'Repository "%s" contains %s files, are you sure that you want to delete this repository with all these files ? [Y/n]: ',
                $repository->getName(), count($repository->getFiles())
            );
            $question = new Question($question);

            $answer = $questionHelper->ask($input, $output, $question);
            if ($answer) {
                $output->writeln(sprintf('Deleting repository "%s"', $repository->getName()));

                $this->em->remove($repository);
                $this->em->flush();

                $output->writeln('Done!');
            } else {
                $output->writeln('Aborting ...');
            }
        } else {
            $output->writeln(sprintf('Deleting repository "%s"', $repository->getName()));

            $this->em->remove($repository);
            $this->em->flush();

            $output->writeln('Done!');
        }

        return 0;
    }
}
