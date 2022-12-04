<?php

namespace Log4World\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;

class Log4WorldController extends Controller
{
    /**
     * @param Twig $twig
     * @return string
     */
    public function index(Twig $twig):string
    {
        return $twig->render('Log4World::Index');
    }
}