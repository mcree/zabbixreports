<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ZabbixReports\MainBundle\Twig;

/**
 * Description of ErrorEventListener
 *
 * @author mcree
 */
class ErrorEventListener
{

    public function onKernelException(\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $e)
    {
        print "aaaa $e";
    }

}
