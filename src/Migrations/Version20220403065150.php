<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220403065150 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE community_interest');
        $this->addSql('ALTER TABLE community DROP CONSTRAINT fk_1b6040337e9e4c8c');
        $this->addSql('DROP INDEX uniq_1b6040337e9e4c8c');
        $this->addSql('ALTER TABLE community DROP photo_id');
        $this->addSql('ALTER TABLE community DROP available_for_regions');
        $this->addSql('ALTER TABLE community DROP is_public');
        $this->addSql('ALTER TABLE community DROP group_key');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE community_interest (community_id INT NOT NULL, interest_id INT NOT NULL, PRIMARY KEY(community_id, interest_id))');
        $this->addSql('CREATE INDEX idx_9e78df40fda7b0bf ON community_interest (community_id)');
        $this->addSql('CREATE INDEX idx_9e78df405a95ff89 ON community_interest (interest_id)');
        $this->addSql('ALTER TABLE community_interest ADD CONSTRAINT fk_9e78df40fda7b0bf FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community_interest ADD CONSTRAINT fk_9e78df405a95ff89 FOREIGN KEY (interest_id) REFERENCES interest (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE community ADD photo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE community ADD available_for_regions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE community ADD is_public BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE community ADD group_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT fk_1b6040337e9e4c8c FOREIGN KEY (photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_1b6040337e9e4c8c ON community (photo_id)');
    }
}
