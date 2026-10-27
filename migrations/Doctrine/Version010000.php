<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

use function is_a;

final class Version010000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // DBAL 3 has AbstractMySQLPlatform (MySQL and MariaDB), DBAL 2 has MySqlPlatform.
        $platform = $this->connection->getDatabasePlatform();

        $this->abortIf(
            !is_a($platform, 'Doctrine\DBAL\Platforms\AbstractMySQLPlatform')
            && !is_a($platform, 'Doctrine\DBAL\Platforms\MySqlPlatform'),
            'Migration can only be executed safely on MySQL.',
        );

        // ngrm_remote_media_content_link table

        $this->addSql(
            <<<'EOT'
            CREATE TABLE ngrm_remote_media_content_link (
              id int NOT NULL AUTO_INCREMENT,
              remote_resource_location_id int DEFAULT NULL,
              field_id int NOT NULL,
              version int NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY field_id_version_idx (field_id, version),
              UNIQUE KEY UNIQ_4E6FA05BB29166B0 (remote_resource_location_id),
              CONSTRAINT FK_4E6FA05BB29166B0 FOREIGN KEY (remote_resource_location_id)
                REFERENCES ngrm_remote_resource_location (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci
            EOT
        );
    }
}
