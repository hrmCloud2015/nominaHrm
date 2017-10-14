<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Talentohumano\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;
use Talentohumano\Model\Entity\ActividadA;
use Talentohumano\Model\Entity\ActividadE;
use Talentohumano\Model\Entity\RiesgosA;
use Talentohumano\Model\Entity\Empleados;
// (C)
use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos

class RiesactividadesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/talentohumano/riesactividades/list";// Variable lin de acceso  0 (C)
    private $tlis = "Actividades"; // Titulo listado
    private $tfor = "Chequeo de actividades"; // Titulo formulario
    private $ttab = "No,Actividad,Responsables,Fecha,Días,Estado,Acción"; // Titulo de las columnas de la tabla
    private $ttab2 = "Id,Actividad,Responsables,Fecha de realizacion";
    //Listado de actividades. ********************************************************************************************
    public function listAction()
    {
    
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        $u=new RiesgosA($this->dbAdapter);
        

        $dat=$d->getResponsablesActividadesGeneral();
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getRegistro(), 
            'dat'       =>  $dat,            
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
      $datos=0;
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'datos'   => $datos,  
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Comite($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $connection = null;
                try 
                {
                  $connection = $this->dbAdapter->getDriver()->getConnection();
                  $connection->beginTransaction();  
                  $data = $this->request->getPost();
                 
                  $u->actRegistro($data);
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
          $u=new Comite($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
          $dat = $u->getRegistroId($id);
            // Valores guardados
          $form->get("nombre")->setAttribute("value",$dat['nombre']); 
                     

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
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new Comite($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
        $u->delRegistro($id);
        return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
     }          
   }
  public function listiAction() 
   {  

      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
          
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);
     //---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
      

      $arreglo='';  
      $dato =   $d->getResponsablesActividades($id);
      foreach($dato as $dat)
      {

        $idc = $dat['id']; $nom = $dat['nombre']; $ape = $dat['apellido'];
        $arreglo[$idc] = $nom." ".$ape;  

      } 
      $form->get("idRpon")->setValueOptions($arreglo); 
      $dat=$d->getResponsablesComites($id);
      //Un llamdo a las actividades en realizadas.
      $dat2 = $d->getActividadesRealizadas($id);
      
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'dat'     => $dat,  
           'dat2'    => $dat2,
           "ttablas" =>  $this->ttab2,
           "lin"     => $this->lin
      );     

      if ($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost())
        {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            // Fin validacion de formulario ---------------------------
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $a = new ActividadA($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)
            $e = new ActividadE($this->dbAdapter);   
            $data = $this->request->getPost();
               // print_r($data);
                  // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
              $connection = $this->dbAdapter->getDriver()->getConnection();
              $connection->beginTransaction();  
              //insercion para la relaciones en t_actividad_accion.
              $idAct = $a->actRegistroActividad($data);
              $i=0;
              if ($data->idRpon!='')
              {
                foreach($data->idRpon as $dat)
                {
                  $idRpon  = $data->idRpon[$i];   $i++;
                  //insercion de relaciones .
                  $e->actResgistoActidaActEmp($idAct,$idRpon);                
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



   public function listcdAction() 
   {  

       $id = (int) $this->params()->fromRoute('id', 0);
       if ($id > 0)
       {
          $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
          $u=new ComiteE($this->dbAdapter); 
          $u->delRegistroMiemEmpId($id);
          return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
       }
     
   }
}



