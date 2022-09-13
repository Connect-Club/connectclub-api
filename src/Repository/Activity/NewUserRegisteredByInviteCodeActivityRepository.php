<?php

namespace App\Repository\Activity;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\Activity\NewUserRegisteredByInviteCodeActivity;
use App\Entity\User;
use App\Repository\BulkInsertTrait;
use App\Repository\HandleNativeQueryLastValuePaginationTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NewUserRegisteredByInviteCodeActivity|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewUserRegisteredByInviteCodeActivity|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewUserRegisteredByInviteCodeActivity[]    findAll()
 * @method NewUserRegisteredByInviteCodeActivity[]    findBy(array $criteria)
 */
class NewUserRegisteredByInviteCodeActivityRepository extends ServiceEntityRepository
{
    use BulkInsertTrait;
    use IsolatedEntityManagerTrait;
    use HandleNativeQueryLastValuePaginationTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewUserRegisteredByInviteCodeActivity::class);
    }

    public function removeWithUser(User $inviter, User $invited)
    {
        $activities = $this->createQueryBuilder('n')
                         ->join('n.nestedUsers', 'nn')
                         ->where('nn.id = :invited')
                         ->andWhere('n.user = :inviter')
                         ->getQuery()
                         ->setParameter('invited', $invited->id)
                         ->setParameter('inviter', $inviter)
                         ->getResult();

        foreach ($activities as $activity) {
            $this->getEntityManager()->remove($activity);
            $this->getEntityManager()->flush();
        }
    }
}
