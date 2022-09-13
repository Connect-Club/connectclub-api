<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201006081638 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE community_participant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE community_participant (id INT NOT NULL, user_id INT DEFAULT NULL, community_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C3AFFC28A76ED395 ON community_participant (user_id)');
        $this->addSql('CREATE INDEX IDX_C3AFFC28FDA7B0BF ON community_participant (community_id)');
        $this->addSql('ALTER TABLE community_participant ADD CONSTRAINT FK_C3AFFC28A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community_participant ADD CONSTRAINT FK_C3AFFC28FDA7B0BF FOREIGN KEY (community_id) REFERENCES community (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE community_user');
        $this->addSql('ALTER TABLE community ADD video_room_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT FK_1B604033B1FA993E FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B604033B1FA993E ON community (video_room_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE community_participant_id_seq CASCADE');
        $this->addSql('CREATE TABLE community_user (community_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(community_id, user_id))');
        $this->addSql('CREATE INDEX idx_4cc23c83a76ed395 ON community_user (user_id)');
        $this->addSql('CREATE INDEX idx_4cc23c83fda7b0bf ON community_user (community_id)');
        $this->addSql('ALTER TABLE community_user ADD CONSTRAINT fk_4cc23c83fda7b0bf FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community_user ADD CONSTRAINT fk_4cc23c83a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE community_participant');
        $this->addSql('ALTER TABLE community DROP CONSTRAINT FK_1B604033B1FA993E');
        $this->addSql('DROP INDEX UNIQ_1B604033B1FA993E');
        $this->addSql('ALTER TABLE community DROP video_room_id');
    }
}
