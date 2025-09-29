<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function findForIndex(?string $q, ?string $genre)
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC');

        if ($q) {
            $qb->andWhere('b.title LIKE :q')->setParameter('q', '%'.$q.'%');
        }

        if ($genre) {
            $qb->andWhere('b.genre = :genre')->setParameter('genre', $genre);
        }

        return $qb->getQuery()->getResult();
    }
}
