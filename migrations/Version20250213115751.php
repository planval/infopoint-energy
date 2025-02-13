<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250213115751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add other_option_values column to pv_financial_support table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pv_financial_support ADD other_option_values JSON DEFAULT NULL');
        $this->addSql('UPDATE pv_financial_support SET other_option_values = \'{}\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pv_financial_support DROP COLUMN other_option_values');
    }
}
