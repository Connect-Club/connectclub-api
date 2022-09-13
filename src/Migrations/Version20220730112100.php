<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220730112100 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add test data';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql(<<<SQL
            INSERT INTO users (id, email, created_at, name, surname, languages)
            VALUES 
            (1, 'test@test.ru' ,0, '', '', '["EN"]');
        SQL);
        $this->addSql(<<<SQL
            SELECT SETVAL('users_id_seq', (SELECT MAX(id) + 1 FROM users));
        SQL);
        $bucket = $_ENV["GOOGLE_CLOUD_STORAGE_BUCKET"];
        $this->addSql(<<<SQL
            INSERT INTO photo (id ,upload_by_id, type, original_name, processed_name, bucket, upload_at, width, height, is_system_background)
            VALUES 
            (1, 1, 'videoRoomBackground', '45f80f11-832c-47dc-9e48-4b9776de5fae.jpg', '45f80f11-832c-47dc-9e48-4b9776de5fae.jpg', '$bucket', 1623850640, 2250, 4872, true),
            (2, 1, 'videoRoomBackground', 'f9ecedc9-cefe-4174-b1ae-60258c4f955c.jpg', 'f9ecedc9-cefe-4174-b1ae-60258c4f955c.jpg', '$bucket', 1623903245, 9000, 19488, true),
            (3, 1, 'videoRoomBackground', '314009d4-fed1-4375-b865-e0816ca2f1b5.jpg', '314009d4-fed1-4375-b865-e0816ca2f1b5.jpg', '$bucket', 1629190116, 4500, 9744, true),
            (4, 1, 'videoRoomBackground', '021da593-e6f0-46d6-b610-b0afb03304a4.jpg', '021da593-e6f0-46d6-b610-b0afb03304a4.jpg', '$bucket', 1629190286, 4500, 9744, true),
            (5, 1, 'videoRoomBackground', '36b9dc26-1bf0-4be2-a494-a44b42026dfd.jpg', '36b9dc26-1bf0-4be2-a494-a44b42026dfd.jpg', '$bucket', 1634548557, 9000, 19488, true),
            (6, 1, 'videoRoomBackground', '1c387701-9e37-431a-8002-94636fe72b4f.jpg', '1c387701-9e37-431a-8002-94636fe72b4f.jpg', '$bucket', 1646233114, 9000, 19488, true);
        SQL);
        $this->addSql(<<<SQL
            SELECT SETVAL('photo_id_seq', (SELECT MAX(id) + 1 FROM photo));
        SQL);
        $this->addSql(<<<SQL
            INSERT INTO event_draft (id, background_photo_id, description, background_room_width_multiplier, background_room_height_multiplier, index, with_speakers, initial_room_scale, publisher_radar_size, type, max_participants, max_room_zoom, expected_width, expected_height)
            VALUES 
            ('64e0e935-13a7-4bf3-9168-97535dd5adfe', 1, 'Small broadcasting room', 1, 1, 5, true, 0, 1, 's_broadcasting', 5000, 5, 2250, 4872),
            ('ff5b37f0-db88-4aff-99b3-60ca21730a85', 2, 'Large networking room', 4, 4, 2, true, 1, 3000, 'l_networking', 100, 10, 9000, 19488),
            ('b3cb33cd-807f-4820-a493-5232a2dbeb11', 3, 'Small networking room', 4, 4, 4, true, 1, 3000, 's_networking', 100, 10, 4500, 9744),
            ('270267de-9fa2-4141-b084-aa162ee54ef2', 4, 'Large broadcasting room', 1, 1, 1, true, 0, 1, 'l_broadcasting', 5000, 5, 4500, 9744),
            ('51ec1eff-8110-40d7-b55e-f540cdbd67b0', 5, 'Gallery', 4, 4, 4, true, 1, 3000, 'gallery', 100, 10, 9000, 19488),
            ('3955eee6-7974-4e15-8355-7bba8e88bf65', 6, '', 4, 4, 3, true, 1, 3000, 'multiroom', 100, 10, 9000, 19488)
        SQL);
        $this->addSql(<<<SQL
            INSERT INTO settings (id, data_track_url, data_track_api_url)
            VALUES 
            (1, '{$_ENV['DATATRACK_URL']}', '{$_ENV['DATATRACK_API_URL']}');
        SQL);
    }

    public function down(Schema $schema) : void
    {
    }
}


























