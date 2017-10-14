<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Talentohumano\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;
use Talentohumano\Model\Entity\Riesgos;
use Talentohumano\Model\Entity\RiesgosF;
use Talentohumano\Model\Entity\RiesgosP;
use Talentohumano\Model\Entity\RiesgosA;
use Talentohumano\Model\Entity\RiesgosE;
// (C)
use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos

class RiesgosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/talentohumano/riesgos/list";// Variable lin de acceso  0 (C)
    private $tlis = "Riesgos"; // Titulo listado
    private $tfor = "Actualización de riesgos"; // Titulo formulario
    private $ttab = "Id, Nombre,Editar,Eliminar";
    private $ttab2 = "Id, Actividad,Fecha,Empleados,Editar,Eliminar";
     // Titulo de las columnas de la tabla
        
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $u = new Riesgos($this->dbAdapter);
        $valores = array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getRegistro(),            
            "ttablas"   =>  $this->ttab,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
            "lin"       =>  $this->lin
        );                  
        return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      /*Adaptador local*/
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter'); 
      $d = new AlbumTable($this->dbAdapter);                           
      $datos = 0;
      $valores = array
      (
           "titulo"   => $this->tfor,
           "form"     => $form,
           'url'      => $this->getRequest()->getBaseUrl(),
           'id'       => $id,
           'datCtrol' => $d->getDatosControlesRiesgos($id),
           'datos'    => $datos,  
           "lin"      => $this->lin,
           "ttablas"   =>  $this->ttab2
      );
      $f = new  RiesgosF($this->dbAdapter);
      $p = new  RiesgosP($this->dbAdapter);  
    
      // Listado  de Factores de riesgos.
      $datos = $d->getFactoresRiesgos('');
      /*Carga de registros*/
      $arreglo='';
      foreach($datos as $dat)
      {
         $idp = $dat['id'];$nom=$dat['nombre'];
         $arreglo[$idp] = $nom;
      }           
      $form->get("idFries")->setValueOptions($arreglo);
      /*Carga de procesos*/
      $datos = $d->getProcesosRiesgos('');
      $arreglo='';
      foreach($datos as $dat)
      {
         $idp=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idp]= $nom;
      }           
      $form->get("idPries")->setValueOptions($arreglo);
      //carga de emppleados.
      $arreglo='';  
      $datos =  $d->getEmp('');
      foreach($datos as $dat)
      {

        $idc = $dat['id']; $nom = $dat['nombre'];
        $arreglo[$idc] = $nom;

      } 
      $form->get("idRpon")->setValueOptions($arreglo); 
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost())
        {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) 
            {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Riesgos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                  // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                     $connection = $this->dbAdapter->getDriver()->getConnection();
                     $connection->beginTransaction();  

                     $id = $u->actRegistro($data);
                     /*Eliminacion del registro asociado al id del factor de riesgo.*/
                     $f->delRegistroRiesFacId($id);
                     /*Eliminacion del registro asociado al id del proceso del riesgo.*/
                     $p->delRegistroRiesProId($id);
                     $i = 0;
                     if ($data->idFries!='')
                     {
                       foreach($data->idFries as $dato)
                       {
                          $idFries = $data->idFries[$i];  $i++; 
                          $f->actRegistroRiesFac($idFries,$id);                
                       }
                     }
                     //insercion para la relaciones de  procesos con riesgos.
                     $i = 0;
                     if ($data->idPries!='')
                     {
                       foreach($data->idPries as $dato)
                       {
                          $idPries = $data->idPries[$i];  $i++; 
                          $p->actRegistroRiesPro($id,$idPries);                
                       }
                     }
                     $connection->commit();
                     $this->flashMessenger()->addMessage('');               
                     return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                                
                }// Fin try casth   
                catch (\Exception $e) 
                {
                   if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                   {
                      $connection->rollback();
                      echo $e;
                   }
                }

            }
        }
        return new ViewModel($valores);
      }
      else
      {              
      if ($id > 0) // Cuando ya hay un registro asociado
      {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u = new Riesgos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $dat = $u->getRegistroId($id);
            // Valores guardados
            $form->get("nombre")->setAttribute("value",$dat['nombre']); 
            $form->get("comenN")->setAttribute("value",$dat['fuenteGeneradora']);
            $form->get("comenN2")->setAttribute("value",$dat['exposicion']); 
            $form->get("idNries")->setAttribute("value",$dat['idNries']); 
            $form->get("idProb")->setAttribute("value",$dat['idProb']);
            $form->get("idCons")->setAttribute("value",$dat['idCons']);
            $form->get("idActv")->setAttribute("value",$dat['idActv']); 
            $form->get("comenN3")->setAttribute("value",$dat['comentario']); 
            $form->get("idTiries")->setAttribute("value",$dat['idTiries']);
            $form->get("numero")->setAttribute("value",$dat['horas']);  
            $form->get("comenN4")->setAttribute("value",$dat['objetivos']);
            $form->get("comenN5")->setAttribute("value",$dat['meta']);  
            /*Carga de facatores de riesgos especifica.*/
            $datos=$d->getFactoresRiesgosId($id);
            $arreglo='';            
            foreach($datos as $dat)
            {
               $arreglo[]=$dat['id'];
            }                
            $form->get("idFries")->setValue($arreglo); 
            /*Carga de procesos especificos.*/
            $datos =$d->getProcesosRiesgosId($id);
            $arreglo ='';            
            foreach($datos as $dat)
            {
               $arreglo[] = $dat['id'];
            }                
            $form->get("idPries")->setValue($arreglo); 
       
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
          $connection = null;
           try 
           {
             $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
             $connection = $this->dbAdapter->getDriver()->getConnection();
             $connection->beginTransaction();  
             //Obtos creados para la borrada masiva de tablas
             $u = new Riesgos($this->dbAdapter);
             $p = new RiesgosP($this->dbAdapter);
             $f = new RiesgosF($this->dbAdapter);    //   ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (
             $d = new AlbumTable($this->dbAdapter);
             //borado sincronico.
             $f->delRegistroRiesFacId($id);
             $u->delRegistro($id);
             $p->delRegistroRiesProId($id);
             
             $connection->commit();
             $this->flashMessenger()->addMessage('');               
             return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                                
            }// Fin try casth   
            catch (\Exception $e) 
            {
              if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
               {
                 $connection->rollback();
                 echo $e;
               }
            }
       }          
   }

   public function listgaAction() 
   {
     $id = (int) $this->params()->fromRoute('id', 0);
     $connection = null;
     try 
     { 
       if($this->getRequest()->isPost()) // Actulizar datos
       {
          $request = $this->getRequest();
          if($request->isPost())
          {
              $data = $this->request->getPost();
              $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
              $connection = $this->dbAdapter->getDriver()->getConnection();
              $connection->beginTransaction();  
              $d = new AlbumTable($this->dbAdapter);
              $a = new RiesgosA($this->dbAdapter);
              $u = new RiesgosE($this->dbAdapter);
              //borado sincronico.
              $idIri=$a->actRegistro($data);
              $i=0;
              if ($data->idRpon!='')
              {
                foreach($data->idRpon as $dat)
                {
                  $idRpon  = $data->idRpon[$i];   $i++;
                  $u->actRegistroEmpleidIries($data->id,$idRpon,$idIri);                
                }
              }
              //insercion para la relaciones.
              $connection->commit();
              $this->flashMessenger()->addMessage('');               
              return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
             }
           }                                
         }// Fin try casth   
        catch (\Exception $e) 
        {
          if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
          {
            $connection->rollback();
            echo $e;
          }
       }
    }

    public function listdaAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $u = new RiesgosA($this->dbAdapter);
      $e = new RiesgosE($this->dbAdapter);
      //borado de foranias.
      $e->delRegistroActividadId($id);
      //borado de registro.
      $u->delRegistro($id);
      return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
        
    } 

} 