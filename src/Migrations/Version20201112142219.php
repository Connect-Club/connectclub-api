<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201112142219 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE community_participant SET community_last_view = subquery.created_at FROM (
            SELECT vr.created_at, cp.id FROM community_participant cp
            JOIN community c on c.id = cp.community_id
            JOIN video_room vr on c.video_room_id = vr.id
            WHERE cp.community_last_view = 0
        ) AS subquery WHERE community_participant.id = subquery.id AND community_participant.community_last_view = 0');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
