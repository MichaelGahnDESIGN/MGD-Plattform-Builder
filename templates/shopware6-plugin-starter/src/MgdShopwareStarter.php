<?php
declare(strict_types=1);

namespace MgdShopwareStarter;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

/** Startpunkt ohne Datenzugriff; Migrationen und Dienste werden bewusst separat ergänzt. */
final class MgdShopwareStarter extends Plugin
{
    public function install(InstallContext $context): void
    {
        parent::install($context);
    }
}
