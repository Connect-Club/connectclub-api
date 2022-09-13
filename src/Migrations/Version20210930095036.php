<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210930095036 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE club (id UUID NOT NULL, avatar_id INT DEFAULT NULL, owner_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B8EE387286383B10 ON club (avatar_id)');
        $this->addSql('CREATE INDEX IDX_B8EE38727E3C61F9 ON club (owner_id)');
        $this->addSql('COMMENT ON COLUMN club.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE club_join_request (id UUID NOT NULL, club_id UUID DEFAULT NULL, author_id INT DEFAULT NULL, handled_by_id INT DEFAULT NULL, status VARCHAR(255) NOT NULL, handled_at BIGINT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_93864C0F61190A32 ON club_join_request (club_id)');
        $this->addSql('CREATE INDEX IDX_93864C0FF675F31B ON club_join_request (author_id)');
        $this->addSql('CREATE INDEX IDX_93864C0FFE65AF40 ON club_join_request (handled_by_id)');
        $this->addSql('COMMENT ON COLUMN club_join_request.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN club_join_request.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE club_participant (id UUID NOT NULL, user_id INT DEFAULT NULL, club_id UUID DEFAULT NULL, joined_by_id INT DEFAULT NULL, role VARCHAR(255) NOT NULL, joined_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_96D339AA76ED395 ON club_participant (user_id)');
        $this->addSql('CREATE INDEX IDX_96D339A61190A32 ON club_participant (club_id)');
        $this->addSql('CREATE INDEX IDX_96D339AB520FE66 ON club_participant (joined_by_id)');
        $this->addSql('COMMENT ON COLUMN club_participant.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN club_participant.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE387286383B10 FOREIGN KEY (avatar_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE38727E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_join_request ADD CONSTRAINT FK_93864C0F61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_join_request ADD CONSTRAINT FK_93864C0FF675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_join_request ADD CONSTRAINT FK_93864C0FFE65AF40 FOREIGN KEY (handled_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_participant ADD CONSTRAINT FK_96D339AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_participant ADD CONSTRAINT FK_96D339A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_participant ADD CONSTRAINT FK_96D339AB520FE66 FOREIGN KEY (joined_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE club_join_request DROP CONSTRAINT FK_93864C0F61190A32');
        $this->addSql('ALTER TABLE club_participant DROP CONSTRAINT FK_96D339A61190A32');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP TABLE club_join_request');
        $this->addSql('DROP TABLE club_participant');
    }
}
