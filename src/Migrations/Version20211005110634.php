<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211005110634 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE club_interest (club_id UUID NOT NULL, interest_id INT NOT NULL, PRIMARY KEY(club_id, interest_id))');
        $this->addSql('CREATE INDEX IDX_6F85B0CE61190A32 ON club_interest (club_id)');
        $this->addSql('CREATE INDEX IDX_6F85B0CE5A95FF89 ON club_interest (interest_id)');
        $this->addSql('COMMENT ON COLUMN club_interest.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE club_interest ADD CONSTRAINT FK_6F85B0CE61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE club_interest ADD CONSTRAINT FK_6F85B0CE5A95FF89 FOREIGN KEY (interest_id) REFERENCES interest (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE club_interest');
    }
}
