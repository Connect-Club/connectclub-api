<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201006082607 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('INSERT INTO community (id, name, description, owner_id, video_room_id, created_at)
                       SELECT nextval(\'community_id_seq\') AS id,
                              v.name AS name,
                              v.description AS description,
                              v.owner_id AS owner_id,
                              v.id AS video_room_id,
                              v.created_at AS created_at
                       FROM video_room v 
                       INNER JOIN chat c ON v.id = c.video_room_id');

        $this->addSql('INSERT INTO community_participant (id, user_id, community_id, created_at)
                       SELECT nextval(\'community_participant_id_seq\') AS id,
                              cp.user_id AS user_id,
                              cm.id AS community_id,
                              cp.created_at AS created_at
                       FROM video_room v 
                       INNER JOIN community cm on v.id = cm.video_room_id
                       INNER JOIN chat c ON v.id = c.video_room_id
                       INNER JOIN chat_participant cp on c.id = cp.chat_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DELETE FROM community_participant');
        $this->addSql('DELETE FROM community');
    }
}
