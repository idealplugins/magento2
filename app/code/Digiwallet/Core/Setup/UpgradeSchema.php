<?php
namespace Digiwallet\Core\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     *
     * {@inheritdoc}
     *
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        
        $installer->startSetup();
        
        if (! $installer->getConnection()->tableColumnExists($installer->getTable('digiwallet_transaction'), 'more')) {
            $installer->getConnection()->addColumn($installer->getTable('digiwallet_transaction'), 'more', [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'LENGTH' => 1024,
                'comment' => 'BW more information updated'
            ]);
        }
        
        $installer->endSetup();
    }
}

