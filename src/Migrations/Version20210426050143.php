<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210426050143 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('INSERT INTO activity_user (activity_id, user_id)
        SELECT a.id, c.owner_id FROM activity a
        JOIN video_room vr on vr.id = a.video_room_id
        JOIN community c on vr.id = c.video_room_id
        WHERE a.type = \'follow-become-speaker\' AND NOT EXISTS(
            SELECT * FROM activity_user au WHERE au.activity_id = a.id AND au.user_id = c.owner_id
        )');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
