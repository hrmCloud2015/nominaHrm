<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Nomina;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface; 
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Authentication\AuthenticationService;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $translator          = $e->getApplication()->getServiceManager()->get('translator'); 
        $eventManager        = $e->getApplication()->getEventManager(); 
        $moduleRouteListener = new ModuleRouteListener(); 
        $moduleRouteListener->attach($eventManager); 
        $application         = $e->getApplication(); 
        // Cambio de idioma en las validaciones
        $translator->addTranslationFile( 
            'phpArray', 
            'vendor/zendframework/zendframework/resources/languages/Zend_Validate.php', 
            'default', 
            'de_DE' 
        ); 
       $translator->addTranslationFile( 
            'phpArray', 
            './vendor/zendframework/zendframework/resources/languages/es/Zend_Validate.php' 
       );
       // Validar session
       $eventManager->attach(MvcEvent::EVENT_DISPATCH, 
            function ($e) 
            {
                  $auth = new AuthenticationService();            
                  $route = $e->getRouteMatch()->getMatchedRouteName();
                  //$url = 'nis/public/auth/index/login'; 
                  if ( (!$auth->hasIdentity()) && ($route != 'auth/default') ) 
                     {
                       $response = $e->getResponse();
                       $response->getHeaders()->addHeaderLine('Location', $url);
                       $response->setStatusCode(302);
                       $response->sendHeaders();
                       return $response;
                    }else{
                       if ( ($auth->hasIdentity()>0) && ($route != 'auth/default') ) 
                       {
                          // VERIFICAR PERMISOS DE USUARIO AL CONTROLADOR EN CUESTION
                          $adapter=$e->getApplication()->getServiceManager()->get('Zend\Db\Adapter\Adapter');
                          $url = $_SERVER['REQUEST_URI'];
                          $lin = str_replace("/nis/public/", "", $url);
                          $d=new \Principal\Model\AlbumTable($adapter);
                          //echo 'lin '.$lin;                         
                          $acc = $d->getPermisosAcceso($lin);
                          //echo 'permi'.$acc;
                          if ($acc==0)// Si es 0 redireccina el acceso 
                          {
                            //  $auth->clearIdentity();
                            //  $sessionManager = new \Zend\Session\SessionManager();
                            //  $sessionManager->forgetMe();   

                            // $response = $e->getResponse();
                            // $response->getHeaders()->addHeaderLine('Location', $url);
                            // $response->setStatusCode(302);
                            // $response->sendHeaders();
                            // return $response;
                          }
                        }// Verifricar si tien autenticacion 
                    }
            });
       // ENVIO DE ALERTAS
       $adapter=$e->getApplication()->getServiceManager()->get('Zend\Db\Adapter\Adapter');
       $d=new \Principal\Model\Alertas($adapter); 
       $d->getDocusControlados();
       
    }

    // Configuracion de servicios 
    public function getServiceConfig()
    {
        return array(
            'initializers' => array(
                function ($instance, $sm) {
                    if ($instance instanceof \Zend\Db\Adapter\AdapterAwareInterface) {
                        $instance->setDbAdapter($sm->get('Zend\Db\Adapter\Adapter'));
                    }
                }
            ),
            'invokables' => array(
                 'c_mu' => 'Principal\Model\MenuTable' // LLamado al menu principal
            ),
            'factories' => array(
                'Navigation' => 'Principal\Navigation\MyNavigationFactory' // Construcción del menu 
            )
          );
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}