<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220402080513 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            <<<SQL
            DELETE FROM activity WHERE join_request_id IN (
                SELECT cjr.id FROM club_join_request cjr
                JOIN users u on u.id = cjr.author_id
                WHERE u.state IN ('banned', 'deleted')
                AND cjr.status = 'moderation'
            );
            SQL
        );
        $this->addSql(
            <<<SQL
            DELETE FROM club_join_request WHERE id IN (
                SELECT cjr.id FROM club_join_request cjr
                JOIN users u on u.id = cjr.author_id
                WHERE u.state IN ('banned', 'deleted')
                AND cjr.status = 'moderation'
            );
            SQL
        );
        $this->addSql(
            <<<SQL
            DELETE FROM club_participant WHERE id IN (
                SELECT cp.id FROM club_participant cp
                JOIN users u on u.id = cp.user_id
                WHERE u.state IN ('banned', 'deleted')
            );
            SQL
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
    }
}
