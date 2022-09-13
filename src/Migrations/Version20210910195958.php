<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210910195958 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE notification ADD start_process_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD processed_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD send_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD opened_at BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE notification DROP start_process_at');
        $this->addSql('ALTER TABLE notification DROP processed_at');
        $this->addSql('ALTER TABLE notification DROP send_at');
        $this->addSql('ALTER TABLE notification DROP opened_at');
    }
}
