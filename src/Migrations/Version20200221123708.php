<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200221123708 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE community_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE community (id INT NOT NULL, owner_id INT DEFAULT NULL, photo_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1B6040337E3C61F9 ON community (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1B6040337E9E4C8C ON community (photo_id)');
        $this->addSql('CREATE TABLE community_user (community_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(community_id, user_id))');
        $this->addSql('CREATE INDEX IDX_4CC23C83FDA7B0BF ON community_user (community_id)');
        $this->addSql('CREATE INDEX IDX_4CC23C83A76ED395 ON community_user (user_id)');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT FK_1B6040337E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT FK_1B6040337E9E4C8C FOREIGN KEY (photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community_user ADD CONSTRAINT FK_4CC23C83FDA7B0BF FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community_user ADD CONSTRAINT FK_4CC23C83A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE community_user DROP CONSTRAINT FK_4CC23C83FDA7B0BF');
        $this->addSql('DROP SEQUENCE community_id_seq CASCADE');
        $this->addSql('DROP TABLE community');
        $this->addSql('DROP TABLE community_user');
    }
}
