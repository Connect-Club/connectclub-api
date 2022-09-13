<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211021084607 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE invite ADD club_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN invite.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE invite ADD CONSTRAINT FK_C7E210D761190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C7E210D761190A32 ON invite (club_id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE invite DROP CONSTRAINT FK_C7E210D761190A32');
        $this->addSql('DROP INDEX IDX_C7E210D761190A32');
        $this->addSql('ALTER TABLE invite DROP club_id');
    }
}
