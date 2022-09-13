<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200810112607 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX uniq_1483a5e9321bb843');
        $this->addSql('DROP INDEX uniq_1483a5e9772f153b');
        $this->addSql('DROP INDEX uniq_1483a5e9c3222106');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9321bb843 ON users (google_profile_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9772f153b ON users (apple_profile_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9c3222106 ON users (facebook_profile_id)');
    }
}
