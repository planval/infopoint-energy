<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250213115752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add funding_provider field to financial support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pv_financial_support ADD funding_provider VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pv_financial_support DROP funding_provider');
    }
} 