<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class ApiTester extends \Codeception\Actor
{
    use _generated\ApiTesterActions;

    public function loadFixtures($fixtures, $append = null)
    {
        /** @var EntityManagerInterface $em */
        $em = $this->grabService(EntityManagerInterface::class);

        $tables = [];
        foreach ($em->getConnection()->getSchemaManager()->listTables() as $table) {
            $tables[] = $table->getName();
        }

        $sql = [];
        foreach ($tables as $table) {
            $sql[] = 'ALTER TABLE '.$table.' DISABLE TRIGGER ALL';
        }
        $sql && $em->getConnection()->getWrappedConnection()->exec(implode(';', $sql));

        $result = $this->getScenario()->runStep(new \Codeception\Step\Action('loadFixtures', func_get_args()));

        $sql = [];
        foreach ($tables as $table) {
            $sql[] = 'ALTER TABLE '.$table.' ENABLE TRIGGER ALL';
        }
        $sql && $em->getConnection()->getWrappedConnection()->exec(implode(';', $sql));

        return $result;
    }
}
