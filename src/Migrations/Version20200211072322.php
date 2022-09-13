<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200211072322 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE photo ADD original_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE photo ADD processed_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE photo ADD bucket VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE photo DROP original_src');
        $this->addSql('ALTER TABLE photo DROP src');

        $this->addSql('ALTER TABLE photo DROP upload_at');
        $this->addSql('ALTER TABLE photo ADD upload_at BIGINT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE photo ADD original_src VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE photo ADD src VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE photo DROP original_name');
        $this->addSql('ALTER TABLE photo DROP processed_name');
        $this->addSql('ALTER TABLE photo DROP bucket');
        $this->addSql('ALTER TABLE photo ALTER upload_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE photo ALTER upload_at DROP DEFAULT');
    }
}
