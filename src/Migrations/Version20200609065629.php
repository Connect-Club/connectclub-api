<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: PleASe modify to your needs!
 */
final clASs Version20200609065629 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, pleASe modify it to your needs
        $this->addSql('INSERT INTO video_room_object 
            (id, type, name, password, width, height, position_x, position_y, background_id)  
            SELECT 
                nextval(\'video_room_object_id_seq\') AS id, 
                \'main_spawn\' AS type, 
                \'main_spawn\' AS name, 
                NULL AS password, 
                1000 AS width, 
                2000 AS height, 
                100 AS position_x, 
                100 AS position_y, 
                id AS background_id FROM photo WHERE type = \'videoRoomBackground\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, pleASe modify it to your needs

    }
}
