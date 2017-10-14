<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Nomina\Model\Entity\Sindicatos; // (C)

class SindicatosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/sindicatos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Sindicatos de nomina"; // Titulo listado
    private $tfor = "Actualización sindicato de nomina"; // Titulo formulario
    private $ttab = "id,Sindicato, Personal,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $d->getGeneral("select *  , ( select count(aa.id) from n_sindicatos_e aa where aa.idSin = a.id )  as  numItem                                
from n_sindicatos a 
"),            
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
      // Niveles de aspectos
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
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
                $u    = new Sindicatos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $u->actRegistro($data);
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Sindicatos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
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
            $u=new Sindicatos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
   // Datos del empleado
   public function listiAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // GUARDAR NOVEDADES //
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {      
            $data = $this->request->getPost();
            //print_r($data);
            // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                                                    

                $d->modGeneral("insert into n_sindicatos_e (idEmp, idSin, fechaIni)"
                    . " values(".$data->idEmp.",".$data->id.",'".$data->fecDoc."')");       
                 $connection->commit();                                                  
                }// Fin try casth   
                 catch (\Exception $e) 
                 {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                     $connection->rollback();
                     echo $e;
                  }   
                  /* Other error handling */
                }// FIN TRANSACCION                                                                
        }
      }

      // Empleados
      $arreglo='';
      $datos = $d->getGeneral(' select a.*, case when b.id is null then 0 else 1 end 
                                from a_empleados a
                                    left join n_tipemp_p b on b.idEmp = a.id 
                                    where activo=0 and ( case when b.id is null then 0 else 1 end ) = 0 '); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                         
                                           

      // Buscar de en novedades cuales tienen formulas 
      $datos = $d->getGeneral1("select * from n_sindicatos where id=".$id);      
      $valores=array
      (
           "titulo"  => "Listado de ".$datos['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),         
           'id'      => $id,          
           'datos'   => $d->getGeneral("select b.id, a.idTemp, a.fecing, 
                                          a.CedEmp, a.nombre, a.apellido, a.fecIng, b.fecha   
                                          from a_empleados a 
                                              inner join n_sindicatos_e b on b.idEmp = a.id 
                                              where b.idSin = ".$id),
           "ttablas" =>  "Cedula, Nombres y apellidos, Fecha de ingreso empresa, Fecha ingreso sindicato, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);              

   } // Fin actualizar datos de empleados         

   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select idSin from n_sindicatos_e where id = ".$id); 
            $d->modGeneral("delete from n_sindicatos_e where id = ".$id);             
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idSin']);
          }          
   }      
}
