<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211019113436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE activity ADD club_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN activity.club_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC74095A61190A32 ON activity (club_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A61190A32');
        $this->addSql('DROP INDEX IDX_AC74095A61190A32');
        $this->addSql('ALTER TABLE activity DROP club_id');
    }
}
