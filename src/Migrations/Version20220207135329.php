<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220207135329 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE user_block (id UUID NOT NULL, author_id INT DEFAULT NULL, blocked_user_id INT DEFAULT NULL, is_was_following BOOLEAN NOT NULL, is_was_follows BOOLEAN NOT NULL, created_at BIGINT NOT NULL, deleted_at BIGINT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_61D96C7AF675F31B ON user_block (author_id)');
        $this->addSql('CREATE INDEX IDX_61D96C7A1EBCBB63 ON user_block (blocked_user_id)');
        $this->addSql('COMMENT ON COLUMN user_block.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_block ADD CONSTRAINT FK_61D96C7AF675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_block ADD CONSTRAINT FK_61D96C7A1EBCBB63 FOREIGN KEY (blocked_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE user_block');
    }
}
