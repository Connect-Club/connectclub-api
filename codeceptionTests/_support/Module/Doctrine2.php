<?php

namespace App\Tests\Module;

class Doctrine2 extends \Codeception\Module\Doctrine2
{
    // we don't need to run em->flush(), we need to test that our code has it's own flush() call
    protected function proceedSeeInRepository($entity, $params = []): array
    {
        $qb = $this->em->getRepository($entity)->createQueryBuilder('s');
        $this->buildAssociationQuery($qb, $entity, 's', $params);
        $this->debug($qb->getDQL());
        $res = $qb->getQuery()->getArrayResult();

        return ['True', (count($res) > 0), "$entity with " . json_encode($params)];
    }
}
