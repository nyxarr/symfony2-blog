<?php
// src/blog/UserBundle/Controller/SecurityController.php;

namespace blog\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\SecurityContext;
use FOS\UserBundle\Controller\SecurityController as BaseController;

class SecurityController extends BaseController
{
  protected function renderLogin(array $data)
  {
    if ($this->container->get('request')->attributes->get('_route') == 'fos_user_security_login') {
      $view = 'login';
    } else {
      $view = 'login_content';
    }

    $template = sprintf('FOSUserBundle:Security:%s.html.%s', $view, $this->container->getParameter('fos_user.template.engine'));

    return $this->container->get('templating')->renderResponse($template, $data);
  }
}
