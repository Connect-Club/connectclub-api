<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220318132238 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE club_token (id UUID NOT NULL, club_id UUID DEFAULT NULL, token_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FF598B8961190A32 ON club_token (club_id)');
        $this->addSql('CREATE INDEX IDX_FF598B8941DEE7B9 ON club_token (token_id)');
        $this->addSql('COMMENT ON COLUMN club_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN club_token.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN club_token.token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE club_token ADD CONSTRAINT FK_FF598B8961190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_token ADD CONSTRAINT FK_FF598B8941DEE7B9 FOREIGN KEY (token_id) REFERENCES token (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE club_token');
    }
}
