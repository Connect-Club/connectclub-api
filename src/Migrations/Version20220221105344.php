<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220221105344 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE event_schedule_subscription ADD notification_hourly_send_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule_subscription ADD notification_daily_send_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule_subscription ADD notification_send_at BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE event_schedule_subscription DROP notification_already_send');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event_schedule_subscription ADD notification_already_send BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE event_schedule_subscription DROP notification_hourly_send_at');
        $this->addSql('ALTER TABLE event_schedule_subscription DROP notification_daily_send_at');
        $this->addSql('ALTER TABLE event_schedule_subscription DROP notification_send_at');
    }
}
