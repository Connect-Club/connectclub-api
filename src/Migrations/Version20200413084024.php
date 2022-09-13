<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200413084024 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('
        INSERT INTO video_room_history (id, video_room_id, user_id, password, joined_at)  
        SELECT nextval(\'video_room_history_id_seq\'), id, owner_id, password, created_at FROM video_room v 
        WHERE NOT EXISTS (
            SELECT id FROM video_room_history h WHERE h.user_id = v.owner_id AND h.video_room_id = v.id
        )
        ');
    }

    public function down(Schema $schema) : void
    {
    }
}
