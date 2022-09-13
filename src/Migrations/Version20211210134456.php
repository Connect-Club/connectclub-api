<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211210134456 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT FK_1CD4F82B82F1BAF4');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT FK_1CD4F82B82F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT FK_75080C4782F1BAF4');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT FK_75080C4782F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT fk_75080c4782f1baf4');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT fk_75080c4782f1baf4 FOREIGN KEY (language_id) REFERENCES interest (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_schedule DROP CONSTRAINT fk_1cd4f82b82f1baf4');
        $this->addSql('ALTER TABLE event_schedule ADD CONSTRAINT fk_1cd4f82b82f1baf4 FOREIGN KEY (language_id) REFERENCES interest (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
