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
use Nomina\Model\Entity\Puestos; // (C)

class PuestosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/puestos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Puestos de trabajo y supervisores"; // Titulo listado
    private $tfor = "Actualización Puestos"; // Titulo formulario
    private $ttab = "id,Puesto, Ciudad, Proyecto, Sede, Supervisores, Editar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getPuestosCon(" and a.estado=0 "),            
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
      $d = new AlbumTable($this->dbAdapter);    

      $datos = $d->getClientes('');// Listado de clientes
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo")->setValueOptions($arreglo);                   

      $datos = $d->getModalidad('');// Listado de modalidades
      $arreglo='';
      $arreglo[0]='Sin modalidad';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo1")->setValueOptions($arreglo);                   

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
                $u    = new Puestos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Puestos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
            $form->get("tipo")->setAttribute("value", $datos['idCli'] ); 
            $form->get("tipo1")->setAttribute("value", $datos['idMod'] ); 
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
            $u=new Puestos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------


   // Personal del proyecto
   public function listiAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Agregar empleado 
      // GUARDAR Puestos //      
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();                         
        $data = $this->request->getPost();         
        if ($request->isPost()) 
        {      
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               
 
               $idIproy = $d->modGeneralId("insert into n_supervisores_p (idPues, idSup, valor)
                   values(".$data->id.", ".$data->idEmp.", 1 )"); 

                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                
        }
      }// FIN GUARDADO DE PROEYCTOS     
      // Empleados
      $arreglo='';
      $datos = $d->getSupervisoresNombresActivos(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nomComp'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idEmp")->setValueOptions($arreglo);                                       

      $valores=array
      (
           "titulo"  => "Puestos ",
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.id, c.CedEmp, c.nombre ,          c.apellido  
                                 from n_supervisores_p a 
                                    inner join n_supervisores b on b.id = a.idSup 
                                    inner join a_empleados c on c.id = b.idEmp 
                          where c.estado = 0 and a.idPues = ".$id),
           "ttablas" =>  "id, Supervisor, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin personal         


   // Puestos de trabajo del proyecto
   public function listpAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Ciudad de labores 
      $arreglo[0]='Seleccionar ciudad de labores';            
      $datos = $d->getCiudades(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['nombre'].' ('.$dat['departamento'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCiu")->setValueOptions($arreglo);  
      // Sede    
      $arreglo='';
      $datos = $d->getSedes(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idSed")->setValueOptions($arreglo);            
      // Zonas
      $arreglo='';
      $datos = $d->getZonas(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['nombre'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idZon")->setValueOptions($arreglo);                                             
      // GUARDAR PUESTOS DE TRABAJOS Puestos //      
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        $data = $this->request->getPost();         
        if ($request->isPost()) 
        {      
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                              


                $idPtra = $d->modGeneralId("insert into n_Puestos_p 
                  (idProy, idCiu, idSed ,nombre, direccion)
               values(".$data->id.",".$data->idCiu.",".$data->idSed.",'".$data->nombre."','".$data->dir."')");                 // Zona del puesto
                if ($data->idZon>0)
                {  
                   $d->modGeneral("insert into n_Puestos_p_z (idProy, idPtra, idSzon ) 
                           values(".$data->id.",".$idPtra.", ".$data->idZon.")"); 
                }                

                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                
        }
      }// FIN GUARDADO DE PUESTOS DE TRABAJO PROEYCTOS       
      $dat = $d->getGeneral1("select b.nombre 
                                  from n_Puestos_p a 
                                 inner join n_Puestos b on b.id = a.idProy 
                               where a.idProy=".$id);
      $valores=array
      (
           "titulo"  => "Puestos de trabajo ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.* , d.nombre as nomZon 
                                        from n_Puestos_p a
                                          left join n_Puestos_p_z b on b.idPtra = a.id 
                                          left join t_sedes_z c on c.id = b.idSzon 
                                          left join n_zonas d on d.id = c.idZon  
                                           where a.idProy=".$id),
           "ttablas" =>  "Nombre, Zona, Direccion, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin personal         

   // Datos empleado de reemplazo
   public function listeAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         

            $data = $this->request->getPost();       
            $valores = array(
               "datos"  => $d->getGeneral1("select a.sueldo, b.nombre as nomCar  
                                              from a_empleados a
                                              inner join t_cargos b on b.id = a.idCar
                                                where a.id=".$data->idEmp),
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }                  
   // Eliminar empleado dato ********************************************************************************************
   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               

                $dat = $d->getGeneral1("Select * from n_supervisores_p where id=".$id);
                $d->modGeneral("delete from n_supervisores_p where id = ".$id);

                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$dat['idPues']);

            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                                




          }
          
   }   

   // Zonas de las sedes ********************************************************************************************
   public function listzAction() 
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d    = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();       
            $valores=array
            (
                "datos"   => $d->getGeneral('select b.id, a.nombre  
                                                from n_zonas a 
                                                 inner join t_sedes_z b on b.idZon = a.id 
                                             where b.idSed = '.$data->id.' order by a.nombre'),
            );          
            $view = new ViewModel($valores);        
            $this->layout('layout/blancoC'); // Layout del login
            return $view;                          
        }
      }            
   }
   // Eliminar puesto ********************************************************************************************
   public function listzidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               

                $dat = $d->getGeneral1("Select * from n_Puestos_p where id=".$id);
                $d->modGeneral("delete from n_Puestos_p_z where idPtra=".$id);                
                $d->modGeneral("delete from n_Puestos_p where id=".$id);
                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'p/'.$dat['idProy']);

            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                                

          }
          
   }   
}
