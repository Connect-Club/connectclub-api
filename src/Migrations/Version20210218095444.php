<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210218095444 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE waiting_list');
        $this->addSql('ALTER TABLE users ADD state VARCHAR(255) DEFAULT \'old\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE waiting_list (id UUID NOT NULL, phone_number VARCHAR(35) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_e4f3965b6b01bc5b ON waiting_list (phone_number)');
        $this->addSql('COMMENT ON COLUMN waiting_list.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN waiting_list.phone_number IS \'(DC2Type:phone_number)\'');
        $this->addSql('ALTER TABLE users DROP state');
    }
}
