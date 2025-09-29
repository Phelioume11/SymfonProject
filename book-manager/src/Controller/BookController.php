<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookType;
use App\Repository\BookRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class BookController extends AbstractController
{
    #[Route('/', name: 'book_index')]
    public function index(Request $request, BookRepository $bookRepository): Response
    {
        $genre = $request->query->get('genre');
        $search = $request->query->get('search');

        if ($this->getUser()) {
            $books = $bookRepository->findByUser($this->getUser(), $genre, $search);
        } else {
            $books = $bookRepository->findAllBooks($genre, $search);
        }

        return $this->render('book/index.html.twig', [
            'books' => $books,
            'genres' => Book::GENRES,
            'current_genre' => $genre,
            'search' => $search,
        ]);
    }

    #[Route('/books/my-books', name: 'book_my_books')]
    #[IsGranted('ROLE_USER')]
    public function myBooks(Request $request, BookRepository $bookRepository): Response
    {
        $genre = $request->query->get('genre');
        $search = $request->query->get('search');

        $books = $bookRepository->findByUser($this->getUser(), $genre, $search);

        return $this->render('book/my_books.html.twig', [
            'books' => $books,
            'genres' => Book::GENRES,
            'current_genre' => $genre,
            'search' => $search,
        ]);
    }

    #[Route('/books/all', name: 'book_all')]
    #[IsGranted('ROLE_ADMIN')]
    public function all(Request $request, BookRepository $bookRepository): Response
    {
        $genre = $request->query->get('genre');
        $search = $request->query->get('search');

        $books = $bookRepository->findAllBooks($genre, $search);

        return $this->render('book/all.html.twig', [
            'books' => $books,
            'genres' => Book::GENRES,
            'current_genre' => $genre,
            'search' => $search,
        ]);
    }

    #[Route('/books/{slug}', name: 'book_show', methods: ['GET'])]
    public function show(string $slug, BookRepository $bookRepository): Response
    {
        $book = $bookRepository->findBySlug($slug);

        if (!$book) {
            throw $this->createNotFoundException('Livre non trouvé');
        }

        $canEdit = $this->isGranted('ROLE_ADMIN') || $book->getUser() === $this->getUser();

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'can_edit' => $canEdit,
        ]);
    }

    #[Route('/books/new/create', name: 'book_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        SluggerInterface $slugger
    ): Response {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImageFile')->getData();

            if ($coverImageFile) {
                $coverImageFilename = $fileUploader->upload(
                    $coverImageFile,
                    $slugger->slug($book->getTitle())->lower()
                );
                $book->setCoverImage($coverImageFilename);
            }

            $slug = $slugger->slug($book->getTitle())->lower();
            $book->setSlug($slug);
            $book->setUser($this->getUser());

            $entityManager->persist($book);
            $entityManager->flush();

            $this->addFlash('success', 'Le livre a été créé avec succès !');

            return $this->redirectToRoute('book_show', ['slug' => $book->getSlug()]);
        }

        return $this->render('book/new.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/books/{slug}/edit', name: 'book_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        string $slug,
        Request $request,
        BookRepository $bookRepository,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        SluggerInterface $slugger
    ): Response {
        $book = $bookRepository->findBySlug($slug);

        if (!$book) {
            throw $this->createNotFoundException('Livre non trouvé');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce livre');
        }

        $oldCoverImage = $book->getCoverImage();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImageFile')->getData();

            if ($coverImageFile) {
                if ($oldCoverImage) {
                    $fileUploader->remove($oldCoverImage);
                }

                $coverImageFilename = $fileUploader->upload(
                    $coverImageFile,
                    $slugger->slug($book->getTitle())->lower()
                );
                $book->setCoverImage($coverImageFilename);
            }

            $slug = $slugger->slug($book->getTitle())->lower();
            $book->setSlug($slug);

            $entityManager->flush();

            $this->addFlash('success', 'Le livre a été modifié avec succès !');

            return $this->redirectToRoute('book_show', ['slug' => $book->getSlug()]);
        }

        return $this->render('book/edit.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/books/{slug}/delete', name: 'book_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        string $slug,
        Request $request,
        BookRepository $bookRepository,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader
    ): Response {
        $book = $bookRepository->findBySlug($slug);

        if (!$book) {
            throw $this->createNotFoundException('Livre non trouvé');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce livre');
        }

        if ($this->isCsrfTokenValid('delete' . $book->getId(), $request->request->get('_token'))) {
            if ($book->getCoverImage()) {
                $fileUploader->remove($book->getCoverImage());
            }

            $entityManager->remove($book);
            $entityManager->flush();

            $this->addFlash('success', 'Le livre a été supprimé avec succès !');
        }

        return $this->redirectToRoute('book_index');
    }
}