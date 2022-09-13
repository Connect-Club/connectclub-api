<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220331054836 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE club_invite (id UUID NOT NULL, club_id UUID DEFAULT NULL, user_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E22DAC5D61190A32 ON club_invite (club_id)');
        $this->addSql('CREATE INDEX IDX_E22DAC5DA76ED395 ON club_invite (user_id)');
        $this->addSql('CREATE INDEX IDX_E22DAC5DB03A8386 ON club_invite (created_by_id)');
        $this->addSql('COMMENT ON COLUMN club_invite.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN club_invite.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE club_invite ADD CONSTRAINT FK_E22DAC5D61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_invite ADD CONSTRAINT FK_E22DAC5DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_invite ADD CONSTRAINT FK_E22DAC5DB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE club_invite');
    }
}
