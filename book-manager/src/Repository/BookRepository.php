<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function findByUser(User $user, ?string $genre = null, ?string $search = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC');

        if ($genre) {
            $qb->andWhere('b.genre = :genre')
                ->setParameter('genre', $genre);
        }

        if ($search) {
            $qb->andWhere('b.title LIKE :search OR b.author LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllBooks(?string $genre = null, ?string $search = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC');

        if ($genre) {
            $qb->andWhere('b.genre = :genre')
                ->setParameter('genre', $genre);
        }

        if ($search) {
            $qb->andWhere('b.title LIKE :search OR b.author LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findBySlug(string $slug): ?Book
    {
        return $this->createQueryBuilder('b')
            ->where('b.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}