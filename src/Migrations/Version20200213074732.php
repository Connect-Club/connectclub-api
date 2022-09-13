<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200213074732 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users DROP password');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9321BB843 ON users (google_profile_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9C3222106 ON users (facebook_profile_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_1483A5E9321BB843');
        $this->addSql('DROP INDEX UNIQ_1483A5E9C3222106');
        $this->addSql('ALTER TABLE users ADD password VARCHAR(255) DEFAULT \'\' NOT NULL');
    }
}
