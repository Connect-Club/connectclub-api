<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220207224551 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE video_room DROP CONSTRAINT fk_75080c479c9a2529');
        $this->addSql('ALTER TABLE user_contact DROP CONSTRAINT fk_146ff8329c9a2529');
        $this->addSql('ALTER TABLE chat_access DROP CONSTRAINT fk_61b51c751a9a7125');
        $this->addSql('ALTER TABLE chat_participant DROP CONSTRAINT fk_e8ed9c891a9a7125');
        $this->addSql('ALTER TABLE opened_video_room DROP CONSTRAINT fk_d05d745724cff17f');
        $this->addSql('ALTER TABLE square_config DROP CONSTRAINT fk_f07e58a124cff17f');
        $this->addSql('ALTER TABLE opened_video_room_variant DROP CONSTRAINT fk_fb2a3447513b6c6e');
        $this->addSql('ALTER TABLE networking_meeting_match DROP CONSTRAINT fk_8885aa19d83cfd5c');
        $this->addSql('ALTER TABLE networking_meeting_user DROP CONSTRAINT fk_31fc841ad83cfd5c');
        $this->addSql('DROP SEQUENCE chat_participant_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE chat_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_contact_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE square_config_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE square_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE opened_video_room_variant_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE opened_video_room_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE support_account_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE chat_access_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE networking_meeting_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE networking_meeting_match_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE networking_meeting_user_id_seq CASCADE');
        $this->addSql('DROP TABLE user_contact');
        $this->addSql('DROP TABLE chat');
        $this->addSql('DROP TABLE square');
        $this->addSql('DROP TABLE opened_video_room');
        $this->addSql('DROP TABLE opened_video_room_variant');
        $this->addSql('DROP TABLE square_config');
        $this->addSql('DROP TABLE support_account');
        $this->addSql('DROP TABLE chat_access');
        $this->addSql('DROP TABLE networking_meeting_match');
        $this->addSql('DROP TABLE networking_meeting_user');
        $this->addSql('DROP TABLE networking_meeting');
        $this->addSql('DROP TABLE chat_participant');
        $this->addSql('ALTER TABLE users DROP jabber_password');
        $this->addSql('ALTER TABLE users DROP networking_tutorial_showed');
        $this->addSql('DROP INDEX uniq_75080c479c9a2529');
        $this->addSql('ALTER TABLE video_room DROP group_chat_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE chat_participant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE chat_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_contact_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE square_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE square_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE opened_video_room_variant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE opened_video_room_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE support_account_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE chat_access_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE networking_meeting_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE networking_meeting_match_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE networking_meeting_user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_contact (id INT NOT NULL, user_id INT DEFAULT NULL, contact_id INT DEFAULT NULL, video_room_id INT DEFAULT NULL, group_chat_id INT DEFAULT NULL, created_at INT NOT NULL, sid VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_146ff832e7a1254a ON user_contact (contact_id)');
        $this->addSql('CREATE INDEX idx_146ff832b1fa993e ON user_contact (video_room_id)');
        $this->addSql('CREATE INDEX idx_146ff8329c9a2529 ON user_contact (group_chat_id)');
        $this->addSql('CREATE INDEX idx_146ff832a76ed395 ON user_contact (user_id)');
        $this->addSql('CREATE TABLE chat (id INT NOT NULL, owner_id INT DEFAULT NULL, user_id INT DEFAULT NULL, photo_id INT DEFAULT NULL, video_room_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, room_name VARCHAR(255) NOT NULL, created_at INT NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, title TEXT DEFAULT NULL, synced_with_jabber BOOLEAN DEFAULT NULL, pinned_message_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_659df2aab1fa993e ON chat (video_room_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_659df2aa3d279462 ON chat (room_name)');
        $this->addSql('CREATE INDEX idx_659df2aa7e3c61f9 ON chat (owner_id)');
        $this->addSql('CREATE INDEX idx_659df2aa7e9e4c8c ON chat (photo_id)');
        $this->addSql('CREATE INDEX idx_659df2aaa76ed395 ON chat (user_id)');
        $this->addSql('CREATE TABLE square (id INT NOT NULL, code VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE opened_video_room (id INT NOT NULL, square_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, location_x INT DEFAULT 0 NOT NULL, location_y INT DEFAULT 0 NOT NULL, schedule TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_d05d745724cff17f ON opened_video_room (square_id)');
        $this->addSql('CREATE TABLE opened_video_room_variant (id INT NOT NULL, opened_video_room_id INT DEFAULT NULL, video_room_id INT DEFAULT NULL, available_for_continent JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_fb2a3447513b6c6e ON opened_video_room_variant (opened_video_room_id)');
        $this->addSql('CREATE INDEX idx_fb2a3447b1fa993e ON opened_video_room_variant (video_room_id)');
        $this->addSql('CREATE TABLE square_config (id INT NOT NULL, background_photo_id INT DEFAULT NULL, square_id INT DEFAULT NULL, background_room_width_multiplier INT NOT NULL, background_room_height_multiplier INT NOT NULL, initial_room_scale INT NOT NULL, min_room_zoom INT NOT NULL, max_room_zoom INT NOT NULL, video_bubble_size INT NOT NULL, publisher_radar_size INT DEFAULT 3000 NOT NULL, interval_to_send_data_track_in_milliseconds INT NOT NULL, image_memory_multiplier DOUBLE PRECISION DEFAULT \'0.75\' NOT NULL, video_quality_width INT NOT NULL, video_quality_height INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_f07e58a1a5e1414b ON square_config (background_photo_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_f07e58a124cff17f ON square_config (square_id)');
        $this->addSql('CREATE TABLE support_account (id INT NOT NULL, user_id INT DEFAULT NULL, active BOOLEAN NOT NULL, last_usage BIGINT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c0035e58a76ed395 ON support_account (user_id)');
        $this->addSql('CREATE TABLE chat_access (id INT NOT NULL, chat_id INT DEFAULT NULL, user_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_61b51c751a9a7125 ON chat_access (chat_id)');
        $this->addSql('CREATE INDEX idx_61b51c75a76ed395 ON chat_access (user_id)');
        $this->addSql('CREATE TABLE networking_meeting_match (id INT NOT NULL, networking_meeting_id INT DEFAULT NULL, first_user_id INT DEFAULT NULL, second_user_id INT DEFAULT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8885aa19d83cfd5c ON networking_meeting_match (networking_meeting_id)');
        $this->addSql('CREATE INDEX idx_8885aa19b02c53f8 ON networking_meeting_match (second_user_id)');
        $this->addSql('CREATE INDEX idx_8885aa19b4e2bf69 ON networking_meeting_match (first_user_id)');
        $this->addSql('CREATE TABLE networking_meeting_user (id INT NOT NULL, networking_meeting_id INT DEFAULT NULL, user_id INT DEFAULT NULL, subscribe BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_31fc841aa76ed395 ON networking_meeting_user (user_id)');
        $this->addSql('CREATE INDEX idx_31fc841ad83cfd5c ON networking_meeting_user (networking_meeting_id)');
        $this->addSql('CREATE TABLE networking_meeting (id INT NOT NULL, video_room_id INT DEFAULT NULL, networking_time_range_start BIGINT NOT NULL, networking_time_range_end BIGINT NOT NULL, created_at BIGINT NOT NULL, is_closed BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_e9571116b1fa993e ON networking_meeting (video_room_id)');
        $this->addSql('CREATE TABLE chat_participant (id INT NOT NULL, chat_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_at INT NOT NULL, mute BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_e8ed9c891a9a7125 ON chat_participant (chat_id)');
        $this->addSql('CREATE INDEX idx_e8ed9c89a76ed395 ON chat_participant (user_id)');
        $this->addSql('ALTER TABLE user_contact ADD CONSTRAINT fk_146ff832a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_contact ADD CONSTRAINT fk_146ff832e7a1254a FOREIGN KEY (contact_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_contact ADD CONSTRAINT fk_146ff832b1fa993e FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_contact ADD CONSTRAINT fk_146ff8329c9a2529 FOREIGN KEY (group_chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT fk_659df2aa7e3c61f9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT fk_659df2aaa76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT fk_659df2aa7e9e4c8c FOREIGN KEY (photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT fk_659df2aab1fa993e FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE opened_video_room ADD CONSTRAINT fk_d05d745724cff17f FOREIGN KEY (square_id) REFERENCES square (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE opened_video_room_variant ADD CONSTRAINT fk_fb2a3447513b6c6e FOREIGN KEY (opened_video_room_id) REFERENCES opened_video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE opened_video_room_variant ADD CONSTRAINT fk_fb2a3447b1fa993e FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE square_config ADD CONSTRAINT fk_f07e58a1a5e1414b FOREIGN KEY (background_photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE square_config ADD CONSTRAINT fk_f07e58a124cff17f FOREIGN KEY (square_id) REFERENCES square (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE support_account ADD CONSTRAINT fk_c0035e58a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_access ADD CONSTRAINT fk_61b51c751a9a7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_access ADD CONSTRAINT fk_61b51c75a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT fk_8885aa19d83cfd5c FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT fk_8885aa19b4e2bf69 FOREIGN KEY (first_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_match ADD CONSTRAINT fk_8885aa19b02c53f8 FOREIGN KEY (second_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT fk_31fc841ad83cfd5c FOREIGN KEY (networking_meeting_id) REFERENCES networking_meeting (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting_user ADD CONSTRAINT fk_31fc841aa76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE networking_meeting ADD CONSTRAINT fk_e9571116b1fa993e FOREIGN KEY (video_room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_participant ADD CONSTRAINT fk_e8ed9c891a9a7125 FOREIGN KEY (chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chat_participant ADD CONSTRAINT fk_e8ed9c89a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE video_room ADD group_chat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_room ADD CONSTRAINT fk_75080c479c9a2529 FOREIGN KEY (group_chat_id) REFERENCES chat (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_75080c479c9a2529 ON video_room (group_chat_id)');
        $this->addSql('ALTER TABLE users ADD jabber_password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD networking_tutorial_showed BOOLEAN DEFAULT \'false\' NOT NULL');
    }
}
