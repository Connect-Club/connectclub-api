<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210920095606 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE paid_subscription ADD waiting_for_payment_confirmation_up_to BIGINT DEFAULT NULL');
        $this->addSql('CREATE INDEX waiting_for_payment_confirmation_up_to ON paid_subscription (waiting_for_payment_confirmation_up_to) WHERE (waiting_for_payment_confirmation_up_to IS NOT NULL)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX waiting_for_payment_confirmation_up_to');
        $this->addSql('ALTER TABLE paid_subscription DROP waiting_for_payment_confirmation_up_to');
    }
}
