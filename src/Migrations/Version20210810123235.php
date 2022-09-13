<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210810123235 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE users ADD banned_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD deleted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD ban_comment VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD delete_comment VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9386B8E7 FOREIGN KEY (banned_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1483A5E9386B8E7 ON users (banned_by_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9C76F1F52 ON users (deleted_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E9386B8E7');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E9C76F1F52');
        $this->addSql('DROP INDEX IDX_1483A5E9386B8E7');
        $this->addSql('DROP INDEX IDX_1483A5E9C76F1F52');
        $this->addSql('ALTER TABLE users DROP banned_by_id');
        $this->addSql('ALTER TABLE users DROP deleted_by_id');
        $this->addSql('ALTER TABLE users DROP ban_comment');
        $this->addSql('ALTER TABLE users DROP delete_comment');
    }
}
