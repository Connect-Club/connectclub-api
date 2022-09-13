<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211108135305 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE activity ADD join_request_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN activity.join_request_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A2A1C965C FOREIGN KEY (join_request_id) REFERENCES club_join_request (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AC74095A2A1C965C ON activity (join_request_id)');

        $this->addSql('
            UPDATE activity
            SET
                join_request_id = (
                    SELECT id
                    FROM club_join_request request
                    WHERE
                        request.club_id = activity.club_id
                        AND request.author_id = (
                            SELECT user_id
                            FROM activity_user
                            WHERE activity_id = activity.id
                        )
                ),
                club_id = null
            WHERE type = \'new-join-request\'
        ');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('
            UPDATE activity
            SET
                club_id = (
                    SELECT club_id
                    FROM club_join_request request
                    WHERE request.id = activity.join_request_id
                ),
                join_request_id = null
            WHERE type = \'new-join-request\'
        ');

        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A2A1C965C');
        $this->addSql('DROP INDEX IDX_AC74095A2A1C965C');
        $this->addSql('ALTER TABLE activity DROP join_request_id');
    }
}
