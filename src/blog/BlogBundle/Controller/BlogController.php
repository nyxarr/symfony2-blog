<?php

// src/blog/BlogBundle/Controller/BlogController.php

namespace blog\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Form;
use JMS\SecurityExtraBundle\Annotation\Secure;

use blog\BlogBundle\Entity\Article;
use blog\BlogBundle\Entity\Categorie;
use blog\BlogBundle\Entity\Commentaire;
use blog\BlogBundle\Entity\ArticleCompetence;

use blog\BlogBundle\Form\ArticleType;
use blog\BlogBundle\Form\ArticleEditType;
use blog\BlogBundle\Form\CommentaireType;

use blog\BlogBundle\Bigbrother\BigbrotherEvents;
use blog\BlogBundle\Bigbrother\MessagePostEvent;

class BlogController extends Controller
{
  public function indexAction($page)
  {
    // On récupère le nombre d'article par page depuis un paramètre du conteneur
    // cf app/config/parameters.yml
    $nbParPage = $this->container->getParameter('blog.nombre_par_page');

    // On récupère les articles de la page courante
    $articles = $this->getDoctrine()
                     ->getManager()
                     ->getRepository('SdzBlogBundle:Article')
                     ->getArticles($nbParPage, $page);

    // On passe le tout à la vue
    return $this->render('SdzBlogBundle:Blog:index.html.twig', array(
      'articles' => $articles,
      'page'     => $page,
      'nb_page'  => ceil(count($articles) / $nbParPage) ?: 1
    ));
  }

  public function voirAction(Article $article, Form $form = null)
  {
    // On récupère l'EntityManager
    $em = $this->getDoctrine()->getManager();

    // On récupère la liste des commentaires
    // On n'a pas joint les commentaires depuis l'article car il faut de toute façon
    // refaire une jointure pour avoir les utilisateurs des commentaires
    $commentaires = $em->getRepository('BlogBundle:Commentaire')
                       ->getByArticle($article->getId());

    // On crée le formulaire d'ajout de commentaire pour le passer à la vue
    if (null === $form) {
      $form = $this->getCommentaireForm($article);
    }

    // Puis modifiez la ligne du render comme ceci, pour prendre en compte les variables :
    return $this->render('BlogBundle:Blog:voir.html.twig', array(
      'article'      => $article,
      'form'         => $form->createView(),
      'commentaires' => $commentaires
    ));
  }

  /**
   * @Secure(roles="ROLE_AUTEUR")
   */
  public function ajouterAction()
  {
    $article = new Article;
    if ($this->getUser()) {
      // On définit le User par défaut dans le formulaire (utilisateur courant)
      $article->setUser($this->getUser());
    }

    // On crée le formulaire grâce à l'ArticleType
    $form = $this->createForm(new ArticleType(), $article);

    // On récupère la requête
    $request = $this->getRequest();

    // On vérifie qu'elle est de type POST
    if ($request->getMethod() == 'POST') {
      // On fait le lien Requête <-> Formulaire
      $form->bind($request);

      // On vérifie que les valeurs rentrées sont correctes
      if ($form->isValid()) {
        // --- Début de notre fonctionnalité BigBrother ---
        // On crée l'évènement
        $event = new MessagePostEvent($article->getContenu(), $this->getUser());

        // On déclenche l'évènement
        $this->get('event_dispatcher')
             ->dispatch(BigbrotherEvents::onMessagePost, $event);

        // On récupère ce qui a été modifié par le ou les listener(s), ici le message
        $article->setContenu($event->getMessage());
        // --- Fin de notre fonctionnalité BigBrother ---

        $article->getArticleCompetences()->clear();

        // On enregistre l'objet $article dans la base de données
        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        foreach ($form->get('articleCompetences')->getData() as $ac) {
          $ac->setArticle($article);
          $em->persist($ac);
        }
        $em->flush();

        // On définit un message flash
        $this->get('session')->getFlashBag()->add('info', 'Article bien ajouté');

        // On redirige vers la page de visualisation de l'article nouvellement créé
        return $this->redirect($this->generateUrl('sdzblog_voir', array('slug' => $article->getSlug())));
      }
    }

    return $this->render('SdzBlogBundle:Blog:ajouter.html.twig', array(
      'form' => $form->createView(),
    ));
  }

  /**
   * @Secure(roles="ROLE_AUTEUR")
   */
  public function modifierAction(Article $article)
  {
    $listeAc = array();
    foreach ($article->getArticleCompetences() as $ac) {
      $listeAc[] = $ac;
    }

    // On utilise le ArticleEditType
    $form = $this->createForm(new ArticleEditType(), $article);

    $request = $this->getRequest();

    if ($request->getMethod() == 'POST') {
      $form->bind($request);

      if ($form->isValid()) {
        $article->getArticleCompetences()->clear();

        // On enregistre l'article
        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        foreach ($form->get('articleCompetences')->getData() as $ac) {
          $ac->setArticle($article);
          $em->persist($ac);
        }

        foreach ($listeAc as $originalAc) {
          foreach ($form->get('articleCompetences')->getData() as $ac) {
            if ($originalAc == $ac) {
              continue 2;
            }
          }

          $em->remove($originalAc);
        }
        $em->flush();

        // On définit un message flash
        $this->get('session')->getFlashBag()->add('info', 'Article bien modifié');

        return $this->redirect($this->generateUrl('sdzblog_voir', array('slug' => $article->getSlug())));
      }
    }

    return $this->render('SdzBlogBundle:Blog:modifier.html.twig', array(
      'form'    => $form->createView(),
      'article' => $article
    ));
  }

  /**
   * @Secure(roles="ROLE_ADMIN")
   */
  public function supprimerAction(Article $article)
  {
    $form = $this->createFormBuilder()->getForm();

    $request = $this->getRequest();
    if ($request->getMethod() == 'POST') {
      $form->bind($request);

      if ($form->isValid()) { // Ici, isValid ne vérifie donc que le CSRF
        // On supprime l'article
        $em = $this->getDoctrine()->getManager();
        $em->remove($article);
        $em->flush();

        // On définit un message flash
        $this->get('session')->getFlashBag()->add('info', 'Article bien supprimé');

        // Puis on redirige vers l'accueil
        return $this->redirect($this->generateUrl('sdzblog_accueil'));
      }
    }

    // Si la requête est en GET, on affiche une page de confirmation avant de supprimer
    return $this->render('SdzBlogBundle:Blog:supprimer.html.twig', array(
      'article' => $article,
      'form'    => $form->createView()
    ));
  }

  public function ajouterCommentaireAction(Article $article)
  {
    $commentaire = new Commentaire;
    $commentaire->setArticle($article);
    $commentaire->setIp($this->getRequest()->server->get('REMOTE_ADDR'));

    $form = $this->getCommentaireForm($article, $commentaire);

    $request = $this->getRequest();

    // Avec la route que l'on a, nous sommes forcément en POST ici, pas besoin de le retester
    $form->bind($request);
    if ($form->isValid()) {
      $em = $this->getDoctrine()->getManager();
      $em->persist($commentaire);
      $em->flush();

      $this->get('session')->getFlashBag()->add('info', 'Commentaire bien enregistré !');

      // On redirige vers la page de l'article, avec une ancre vers le nouveau commentaire
      return $this->redirect($this->generateUrl('sdzblog_voir', array('slug' => $article->getSlug())).'#comment'.$commentaire->getId());
    }

    $this->get('session')->getFlashBag()->add('error', 'Votre formulaire contient des erreurs');

    // On réaffiche le formulaire sans redirection (sinon on perd les informations du formulaire)
    return $this->forward('SdzBlogBundle:Blog:voir', array(
      'article' => $article,
      'form'    => $form
    ));
  }

  /**
   * @Secure(roles="ROLE_ADMIN")
   */
  public function supprimerCommentaireAction(Commentaire $commentaire)
  {
    $form = $this->createFormBuilder()->getForm();

    $request = $this->getRequest();
    if ($request->getMethod() == 'POST') {
      $form->bind($request);

      if ($form->isValid()) { // Ici, isValid ne vérifie donc que le CSRF
        // On supprime l'article
        $em = $this->getDoctrine()->getManager();
        $em->remove($commentaire);
        $em->flush();

        // On définit un message flash
        $this->get('session')->getFlashBag()->add('info', 'Commentaire bien supprimé');

        // Puis on redirige vers l'accueil
        return $this->redirect($this->generateUrl('sdzblog_voir', array('slug' => $commentaire->getArticle()->getSlug())));
      }
    }

    // Si la requête est en GET, on affiche une page de confirmation avant de supprimer
    return $this->render('SdzBlogBundle:Blog:supprimerCommentaire.html.twig', array(
      'commentaire' => $commentaire,
      'form'        => $form->createView()
    ));
  }

  public function menuAction($nombre)
  {
    $repository = $this->getDoctrine()->getManager()->getRepository('SdzBlogBundle:Article');

    $liste = $repository->findBy(
      array(),                 // Pas de critère
      array('date' => 'desc'), // On tri par date décroissante
      $nombre,                 // On sélectionne $nombre articles
      0                        // A partir du premier
    );

    return $this->render('SdzBlogBundle:Blog:menu.html.twig', array(
      'liste_articles' => $liste // C'est ici tout l'intérêt : le contrôleur passe les variables nécessaires au template !
    ));
  }

  public function traductionAction($name)
  {
    return $this->render('SdzBlogBundle:Blog:traduction.html.twig', array(
      'name' => $name
    ));
  }

  public function feedAction()
  {
    $articles = $this->getDoctrine()
                     ->getManager()
                     ->getRepository('SdzBlogBundle:Article')
                     ->getArticles(10, 1);

    $lastArticle = current($articles->getIterator());

    return $this->render('SdzBlogBundle:Blog:feed.xml.twig', array(
      'articles'  => $articles,
      'buildDate' => $lastArticle->getDate()
    ));
  }

  // Méthodes protégées :

  /**
   * Retourne le formulaire d'ajout d'un commentaire
   * @param Article $article
   * @return Form
   */
  protected function getCommentaireForm(Article $article, Commentaire $commentaire = null)
  {
    if (null === $commentaire) {
      $commentaire = new Commentaire;
    }

    // Si l'utilisateur courant est identifié, on l'ajoute au commentaire
    if (null !== $this->getUser()) {
        $commentaire->setUser($this->getUser());
    }

    return $this->createForm(new CommentaireType(), $commentaire);
  }
}
