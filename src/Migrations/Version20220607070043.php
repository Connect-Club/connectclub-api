<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220607070043 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE land (id UUID NOT NULL, owner_id INT DEFAULT NULL, room_id INT DEFAULT NULL, thumb_id INT DEFAULT NULL, image_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL, x INT NOT NULL, y INT NOT NULL, sector INT NOT NULL, available BOOLEAN NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A800D5D87E3C61F9 ON land (owner_id)');
        $this->addSql('CREATE INDEX IDX_A800D5D854177093 ON land (room_id)');
        $this->addSql('CREATE INDEX IDX_A800D5D8C7034EA5 ON land (thumb_id)');
        $this->addSql('CREATE INDEX IDX_A800D5D83DA5256D ON land (image_id)');
        $this->addSql('CREATE INDEX IDX_A800D5D8B03A8386 ON land (created_by_id)');
        $this->addSql('COMMENT ON COLUMN land.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE land ADD CONSTRAINT FK_A800D5D87E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE land ADD CONSTRAINT FK_A800D5D854177093 FOREIGN KEY (room_id) REFERENCES video_room (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE land ADD CONSTRAINT FK_A800D5D8C7034EA5 FOREIGN KEY (thumb_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE land ADD CONSTRAINT FK_A800D5D83DA5256D FOREIGN KEY (image_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE land ADD CONSTRAINT FK_A800D5D8B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE land');
    }
}
