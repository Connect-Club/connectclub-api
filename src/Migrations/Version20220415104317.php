<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220415104317 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<SQL
            DELETE FROM club_participant WHERE id IN (
                SELECT cp1.id
                FROM club_participant cp1
                JOIN club_participant cp2 ON cp1.id != cp2.id AND cp1.club_id = cp2.club_id AND cp1.user_id = cp2.user_id
            )
        SQL
        );
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
