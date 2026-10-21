<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Command;

use Netgen\RemoteMediaIbexa\Migration\V2\FieldMigration;
use Netgen\RemoteMediaIbexa\Migration\V2\Report;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextMigration;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

final class MigrateV2Command extends Command
{
    public function __construct(
        private readonly FieldMigration $fieldMigration,
        private readonly RichTextMigration $richTextMigration,
        private readonly TagAwareAdapterInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('netgen:remote_media:ibexa:migrate_v2')
            ->setDescription('Migrates Netgen Remote Media 2 data (field values and RichText custom tags) to Netgen Remote Media 3')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of fields processed at once', '500')
            ->setHelp(
                <<<'EOT'
                Migrates the data of Netgen Remote Media 2 to Netgen Remote Media 3, see MIGRATION.md of netgen/remote-media-ibexa.
                The tables of Netgen Remote Media 3 must exist.

                Archived versions are not migrated. Fields and custom tags which are already migrated are skipped, so the
                command can run again, e.g. after an interruption.
                EOT,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');

        if ($batchSize < 1) {
            $io->error('--batch-size must be a positive number');

            return Command::FAILURE;
        }

        $io->section('Field definitions');
        $io->writeln(sprintf('Default settings stored to %d field definitions', $this->fieldMigration->migrateFieldDefinitions()));

        $io->section('Fields');
        $io->progressStart($this->fieldMigration->countFields());
        $fieldReport = $this->fieldMigration->migrateFields($batchSize, $io->progressAdvance(...));
        $io->progressFinish();
        $this->writeReport($io, $fieldReport);

        $io->section('RichText');
        $io->progressStart($this->richTextMigration->countFields());
        $richTextReport = $this->richTextMigration->migrate($batchSize, $io->progressAdvance(...));
        $io->progressFinish();
        $this->writeReport($io, $richTextReport);

        // Content cached with Netgen Remote Media 2 data
        $this->cache->clear();
        $io->success('Done, the Ibexa cache pool is cleared. Reindex the content to index the migrated fields.');

        return Command::SUCCESS;
    }

    private function writeReport(SymfonyStyle $io, Report $report): void
    {
        $io->definitionList(
            ['Migrated' => $report->migrated],
            ['Already migrated' => $report->skipped],
            ['Empty' => $report->empty],
            ['Warnings' => count($report->warnings)],
        );

        if (count($report->warnings) > 0) {
            $io->listing($report->warnings);
        }
    }
}
