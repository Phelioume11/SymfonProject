<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookType;
use App\Repository\BookRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BookController extends AbstractController
{
    #[Route('/books', name: 'book_index', methods: ['GET'])]
    public function index(BookRepository $repo, Request $request): Response
    {
        $q = $request->query->get('q');
        $genre = $request->query->get('genre');

        $books = $repo->findForIndex($q, $genre);

        return $this->render('book/index.html.twig', [
            'books' => $books,
            'q' => $q,
            'genre' => $genre,
            'genres' => Book::getGenres(),
        ]);
    }

    #[Route('/', name: 'default_home', methods: ['GET'])]
    public function home(BookRepository $repo): Response
    {
        $books = $repo->findForIndex(null, null);
        return $this->render('default/home.html.twig', ['books' => $books]);
    }

    #[Route('/my-books', name: 'book_my_books', methods: ['GET'])]
    public function myBooks(BookRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $books = $repo->findBy(['user' => $user], ['createdAt' => 'DESC']);
        return $this->render('book/my_books.html.twig', ['books' => $books]);
    }

    #[Route('/books/new', name: 'book_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, FileUploader $uploader): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = (string) $slugger->slug($book->getTitle())->lower();
            $book->setSlug($slug);

            /** @var UploadedFile $file */
            $file = $form->get('coverImage')->getData();
            if ($file) {
                $filename = $uploader->uploadFile($file, $book->getTitle());
                $book->setCoverImage($filename);
            }

            $book->setUser($this->getUser());
            $em->persist($book);
            $em->flush();

            $this->addFlash('success', 'Livre créé avec succès.');
            return $this->redirectToRoute('book_show', ['slug' => $book->getSlug()]);
        }

        return $this->render('book/form.html.twig', ['form' => $form->createView(), 'book' => $book]);
    }

    #[Route('/books/{slug}', name: 'book_show', methods: ['GET'])]
    public function show(Book $book): Response
    {
        return $this->render('book/show.html.twig', ['book' => $book]);
    }

    #[Route('/books/{id}/edit', name: 'book_edit', methods: ['GET', 'POST'])]
    public function edit(Book $book, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, FileUploader $uploader): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && $book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce livre.');
        }

        $oldCover = $book->getCoverImage();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $book->setUpdatedAt(new \DateTimeImmutable());
            $book->setSlug((string) $slugger->slug($book->getTitle())->lower());

            /** @var UploadedFile $file */
            $file = $form->get('coverImage')->getData();
            if ($file) {
                $filename = $uploader->uploadFile($file, $book->getTitle());
                $book->setCoverImage($filename);
                $uploader->deleteFile($oldCover);
            }

            $em->flush();
            $this->addFlash('success', 'Livre mis à jour.');
            return $this->redirectToRoute('book_show', ['slug' => $book->getSlug()]);
        }

        return $this->render('book/form.html.twig', ['form' => $form->createView(), 'book' => $book]);
    }

    #[Route('/books/{id}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(Book $book, Request $request, EntityManagerInterface $em, FileUploader $uploader): Response
    {
        if (!$this->isCsrfTokenValid('delete-book-' . $book->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('book_index');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce livre.');
        }

        $uploader->deleteFile($book->getCoverImage());
        $em->remove($book);
        $em->flush();

        $this->addFlash('success', 'Livre supprimé.');
        return $this->redirectToRoute('book_index');
    }
}
