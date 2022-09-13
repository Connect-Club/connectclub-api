<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211007074959 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE subscription_payment_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE subscription_payment (id INT NOT NULL, paid_subscription_id INT NOT NULL, stripe_invoice_id VARCHAR(255) NOT NULL, amount INT NOT NULL, paid_at BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_subscription_payment_paid_at ON subscription_payment USING BRIN (paid_at)');
        $this->addSql('CREATE INDEX idx_subscription_payment_paid_subscription ON subscription_payment (paid_subscription_id)');
        $this->addSql('ALTER TABLE subscription_payment ADD CONSTRAINT FK_1E3D64969A1887DC FOREIGN KEY (paid_subscription_id) REFERENCES paid_subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE subscription_payment_id_seq CASCADE');
        $this->addSql('DROP TABLE subscription_payment');
    }
}
