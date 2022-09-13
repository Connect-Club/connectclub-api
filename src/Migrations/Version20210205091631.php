<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210205091631 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE event_log_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE event_log_relation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE event_log (id INT NOT NULL, entity_code VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, event_code VARCHAR(255) NOT NULL, context JSON NOT NULL, time BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE event_log_relation (id INT NOT NULL, event_log_id INT DEFAULT NULL, entity_code VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, time BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E1C98665D8FE2AD4 ON event_log_relation (event_log_id)');
        $this->addSql('ALTER TABLE event_log_relation ADD CONSTRAINT FK_E1C98665D8FE2AD4 FOREIGN KEY (event_log_id) REFERENCES event_log (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_log_relation DROP CONSTRAINT FK_E1C98665D8FE2AD4');
        $this->addSql('DROP SEQUENCE event_log_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE event_log_relation_id_seq CASCADE');
        $this->addSql('DROP TABLE event_log');
        $this->addSql('DROP TABLE event_log_relation');
    }
}
