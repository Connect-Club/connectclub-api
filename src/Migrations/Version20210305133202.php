<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210305133202 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE activity (id UUID NOT NULL, user_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AC74095AA76ED395 ON activity (user_id)');
        $this->addSql('COMMENT ON COLUMN activity.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE activity_user (activity_id UUID NOT NULL, user_id INT NOT NULL, PRIMARY KEY(activity_id, user_id))');
        $this->addSql('CREATE INDEX IDX_8E570DDB81C06096 ON activity_user (activity_id)');
        $this->addSql('CREATE INDEX IDX_8E570DDBA76ED395 ON activity_user (user_id)');
        $this->addSql('COMMENT ON COLUMN activity_user.activity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDB81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDBA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE activity_user DROP CONSTRAINT FK_8E570DDB81C06096');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_user');
    }
}
