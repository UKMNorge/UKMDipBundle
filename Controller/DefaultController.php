<?php

namespace UKMNorge\UKMDipBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('UKMDipBundle:Default:index.html.twig');
    }
}
