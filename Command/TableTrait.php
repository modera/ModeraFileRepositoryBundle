<?php


namespace Modera\FileRepositoryBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * @internal
 *
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 */
trait TableTrait
{
    /**
     * @param OutputInterface $output
     * @param array $headers
     * @param array $rows
     */
    private function renderTable(OutputInterface $output, array $headers, array $rows)
    {
        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }
}