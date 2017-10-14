<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Talentohumano\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Talentohumano\Model\Entity\tipdocontrol; // (C)
use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Principal\Model\LogFunc;

class tipdocontrolController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/talentohumano/tipdocontrol/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Tipos de documentos de control"; // Titulo listado
    private $tfor = "Actualización tipo de documentos de control"; // Titulo formulario
    private $ttab = "id,Tipo de documento, Descripción, Items,Editar,Eliminar"; // Titulo de las columnas de la tabla
        
    // Listado de registros ********************************************************************************************
    public function listAction()
    {        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.* ,
                                     ( select count(b.id) from t_tip_docontrol_i b where b.idTdoc = a.id ) as num
                                                from t_tip_docontrol a"),            
            "ttablas"   =>  $this->ttab,
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
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter); 
            $t = new LogFunc($this->dbAdapter);
            $dt = $t->getDatLog();
      // Empleados
      $arreglo2 = '';
      $datos = $d->getEmp("");  
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'].' ('.$dat['email'].')';
          $arreglo2[$idc]= $nom;
      }      
      $form->get("idEmpM")->setValueOptions($arreglo2);                       

      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
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
                $u    = new tipdocontrol($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                
                   
                    $id = $u->actRegistro($data);

                    // Eliminar registros conceptos hijos de esta nomina
                    $d->modGeneral("Delete from t_tip_docontrol_e where idTdoc=".$data->id); 
                    $i=0;
                    foreach ($data->idEmpM as $dato)
                    {
                      $idConcM = $data->idEmpM[$i];  $i++; 
                      $d->modGeneral("insert into t_tip_docontrol_e (idTdoc, idEmp, idUsu)
                                  values(".$id.",".$idConcM.", '".$dt['idUsu']."') "); 
                    }

                    $connection->commit();                                
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                    

                  //}
                }// Fin try casth   
                 catch (\Exception $e) {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                     $connection->rollback();
                     echo $e;
                  }   
                  /* Other error handling */
                }// FIN TRANSACCION                                                                                    
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new tipdocontrol($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $a = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$a"); 
            $form->get("comen")->setAttribute("value",$datos['detalle']); 
            $form->get("numero")->setAttribute("value",$datos['diasAlerta']); 
            $datos = $d->getGeneral('select a.* 
                                       from t_tip_docontrol_e a 
                                         where a.idTdoc = '.$id);// Tipos de nomina afectadas por este automatico
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idEmp'];
            }                
            $form->get("idEmpM")->setValue($arreglo);                       
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
            $u=new tipdocontrol($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   //----------------------------------------------------------------------------------------------------------
   // FUNCIONES ADICIONALES GUARDADO DE LISTA DE CHQUEOS ASOCIADAS A ESTE TIPO DE CONTRATACION
     
   // Listado de items de la etapa **************************************************************************************
   public function listoAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value","$id"); 
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)    

      $dat = $u->getGeneral1("Select * from t_nivel_cargo where id = ".$id); 

      $valores=array
      (
           "titulo"    =>  'Lista de chequeo asociada a '.$dat['nombre'],
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "datos"     =>  $u->getLcheq($id), 
           "datos2"    =>  $u->getLcheqTcon($id),
           "form"      =>  $form,
           "lin"       =>  $this->lin
       );           
      return new ViewModel($valores);                           
        
   } // Fin listar registros orden  
   public function listoaAction() 
   { 
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);    
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        $data = $this->request->getPost();
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new tipdocontrolo($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                 
        $u->actRegistro($data);                            
      }
        $view = new ViewModel();        
        $this->layout('layout/blancoC'); // Layout del login
        return $view;            
      //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);

   } // Fin actualizar datos 
   // Borrar lista de chequeo para modificacion
   public function listodAction() 
   { 
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);    
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        $data = $this->request->getPost();
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new tipdocontrolo($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                 
        $u->delRegistro($data->id);                            
      }
      $view = new ViewModel();        
      $this->layout('layout/blancoC'); // Layout del login
      return $view;                  

   } // Fin actualizar datos    
   
   // Listado de items de la etapa **************************************************************************************
   public function listiAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);
      $form->get("numero")->setAttribute("value",0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = New AlbumTable($this->dbAdapter);      
            $t = new LogFunc($this->dbAdapter);
            $dt = $t->getDatLog();

      $datos = $d->getConnom();// Listado de conceptos
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipVal'].')';
          $arreglo[$idc]= $nom;
      }      
      $form->get("tipo")->setValueOptions($arreglo);  
      $arreglo = ''; 
      $datos = $d->getEmp('');// Listado de empleados 
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }      
      $form->get("idEmpM")->setValueOptions($arreglo);  

      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $d    = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();                
                $d->modGeneral("insert into t_tip_docontrol_i (idTdoc, nombre, idUsu) 
                                       values(".$data->id.",'".$data->nombre."',".$dt['idUsu'].")");
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);
            }
        }
      } 
      
      //$u=new Tipautoi($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
      $valores=array
      (
           "titulo"    =>  'Items de evaluacion para documentos de control ',
           "datos"     =>  $d->getGeneral("select a.id, a.nombre 
                                              from t_tip_docontrol_i a 

                                            where a.idTdoc = ".$id),// Listado de formularios            
           "ttablas"   =>  'Nombre, Eliminar',
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "form"      =>  $form,
           "lin"       =>  $this->lin
       );                
       return new ViewModel($valores);        
   } // Fin listar registros items   

   // Eliminar dato ********************************************************************************************
   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = New AlbumTable($this->dbAdapter);  
            // bucar id de parametro
            $datos = $d->getGeneral1("select idTdoc from t_tip_docontrol_i where id=".$id);// Listado de formularios                                
            $d->modGeneral("delete from t_tip_docontrol_i where id=".$id);// Listado de formularios                                
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idTdoc']);
          }          
   }// Fin eliminar datos

}
