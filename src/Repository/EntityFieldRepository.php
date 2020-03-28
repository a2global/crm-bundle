<?php

namespace A2Global\CRMBundle\Repository;

use A2Global\CRMBundle\Entity\EntityField;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method EntityField|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntityField|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntityField[]    findAll()
 * @method EntityField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntityFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityField::class);
    }
}