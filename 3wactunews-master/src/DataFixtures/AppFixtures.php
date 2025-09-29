<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private SluggerInterface $slugger;

    public function __construct(UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger)
    {
        $this->passwordHasher = $passwordHasher;
        $this->slugger = $slugger;
    }

    public function load(ObjectManager $manager)
    {
        $admin = new User();
        $admin->setEmail('admin@example.test');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpass'));
        $manager->persist($admin);

        $user = new User();
        $user->setEmail('user@example.test');
        $user->setUsername('user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'userpass'));
        $manager->persist($user);

        for ($i = 1; $i <= 6; $i++) {
            $book = new Book();
            $book->setTitle('Livre exemple ' . $i);
            $book->setAuthor('Auteur ' . $i);
            $book->setDescription('Description pour le livre ' . $i);
            $genres = array_values(Book::getGenres());
            $book->setGenre($genres[array_rand($genres)]);
            $book->setSlug((string) $this->slugger->slug($book->getTitle())->lower());
            $book->setUser($user);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
