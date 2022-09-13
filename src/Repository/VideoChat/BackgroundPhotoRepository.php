<?php

namespace App\Repository\VideoChat;

use Anboo\ApiBundle\Repository\IsolatedEntityManagerTrait;
use App\Entity\VideoChat\BackgroundPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry as ManagerRegistry;

/**
 * @method BackgroundPhoto|null find($id, $lockMode = null, $lockVersion = null)
 * @method BackgroundPhoto|null findOneBy(array $criteria, array $orderBy = null)
 * @method BackgroundPhoto[]    findAll()
 * @method BackgroundPhoto[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BackgroundPhotoRepository extends ServiceEntityRepository
{
    use IsolatedEntityManagerTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BackgroundPhoto::class);
    }

    /** @return BackgroundPhoto[]|ArrayCollection */
    public function findUnusedBackgrounds() : ArrayCollection
    {
        return new ArrayCollection(
            $this
                ->createQueryBuilder('b')
                ->where('SIZE(b.videoRooms) = 0')
                ->getQuery()
                ->getResult()
        );
    }
}
