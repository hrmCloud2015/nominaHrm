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
use Nomina\Model\Entity\TurnosG; // (C)

class TurnosgruposController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/turnosgrupos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Turnos"; // Titulo listado
    private $tfor = "Actualización turnos"; // Titulo formulario
    private $ttab = "id,Nombre, Horarios,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $d->getGeneral("select *,
                                             ( select count(b.id) 
                                                 from n_turnos_g_h b 
                                                    where b.idTur = a.id ) as numIte    
                                              from n_turnos_g a "),
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
      $o=new \Nomina\Model\Entity\Turnos($this->dbAdapter);
      $arreglo='';
      $turnos = $o->getRegistro(); 
      foreach ($turnos as $dat){
         $idc=$dat['id'];$nom=$dat['codigo'];
         $arreglo[$idc]= $nom;
      }     
      $form->get("tipo")->setValueOptions($arreglo);                         
      $form->get("tipo1")->setValueOptions($arreglo);       
      $form->get("tipo2")->setValueOptions($arreglo);       
      $form->get("idCar")->setValueOptions($arreglo);                                    
      
      $d = new AlbumTable($this->dbAdapter);
                    
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'datos'   => $d->getGeneral1("select * from n_turnos_g where id =".$id),  
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
//            print_r($_POST);
            
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new TurnosG($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
              // INICIO DE TRANSACCIONES
              $connection = null;
              try 
              {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                              

                $id = $u->actRegistro($data);
                $d    = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);// El 1 es para mostrar mensaje de guardado
              }// Fin try casth   
              catch (\Exception $e) 
              {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                  /* Other error handling */
              }// FIN TRANSACCION                                                          //              return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
       //exit(); 
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new TurnosG($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
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
            $u=new TurnosG($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------


   // Personal del proyecto
   /*public function listiAction() 
   { 
      $form = new Formulario("form");
      
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $u=new TurnosG($this->dbAdapter);
      // Agregar empleado 
      // GUARDAR PROYECTOS //      
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
 
                $idIproy = $d->modGeneralId("insert into n_proyectos_e (idProy, idEmp, fechaI, fechaF, Horas, sueldo)
                   values(".$data->id.", ".$data->idEmp.", '".$data->fechaIni."', '".$data->fechaFin."', ".$data->numero.", ".$data->numero2.")"); 
                // Puesto de trabajo proyecto 
                if ($data->idPtra>0)
                {  
                   $d->modGeneral("insert into n_proyectos_ep (idProy, idIproy, idEmp, idPtra)
                     values(".$data->id.", ".$idIproy.",".$data->idEmp.", ".$data->idPtra.")"); 
                }
                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              // Other error handling 
            }// FIN TRANSACCION                
        }
      }// FIN GUARDADO DE PROEYCTOS     
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idEmp")->setValueOptions($arreglo);                                       
      // Puestos de trabajo proyec
      $arreglo='';
      $datos = $d->getProyectosPuestos($id); 
      foreach ($datos as $dat)
      {
         $idc=$dat['id'];$nom = '( '.$dat['nomSed'].' ) - '.$dat['nombre'];
         $arreglo[$idc]= $nom;
      }                    
      $form->get("idPtra")->setValueOptions($arreglo);                         
      // 
      $dat = $d->getGeneral1("select b.nombre 
                                  from n_proyectos_p a 
                                 inner join n_proyectos b on b.id = a.idProy 
                               where a.idProy=".$id);
      $valores=array
      (
           "titulo"  => "Turno ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $u->getNombresRegistroId($id),
           'datTtra'   => $d->getGeneral("select b.nombre as nomPtra,d.nombre as nomSed , a.idEmp  
                                        from n_proyectos_ep a
                                           inner join n_proyectos_p b on b.id = a.idPtra
                                           inner join n_proyectos_e c on c.id = a.idIproy 
                                           inner join t_sedes d on d.id = b.idSed 
                                           where a.idProy =".$id), // Puesto de trabajo           
           "ttablas" =>  "Empleado, Cargo, Puesto, Sede, Sueldo, Horas, Periodo, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin personal         
*/

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
      // GUARDAR PUESTOS DE TRABAJOS PROYECTOS //      
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


                $idPtra = $d->modGeneralId("insert into n_proyectos_p 
                  (idProy, idCiu, idSed ,nombre, direccion)
               values(".$data->id.",".$data->idCiu.",".$data->idSed.",'".$data->nombre."','".$data->dir."')");                 // Zona del puesto
                if ($data->idZon>0)
                {  
                   $d->modGeneral("insert into n_proyectos_p_z (idProy, idPtra, idSzon ) 
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
                                  from n_proyectos_p a 
                                 inner join n_proyectos b on b.id = a.idProy 
                               where a.idProy=".$id);
      $valores=array
      (
           "titulo"  => "Puestos de trabajo ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.* , d.nombre as nomZon 
                                        from n_proyectos_p a
                                          left join n_proyectos_p_z b on b.idPtra = a.id 
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

                $dat = $d->getGeneral1("Select * from n_turnos_g_h where id=".$id);
                $d->modGeneral("delete from n_turnos_g_h where id=".$id);

                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$dat['idTur']);

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

                $dat = $d->getGeneral1("Select * from n_proyectos_p where id=".$id);
                $d->modGeneral("delete from n_proyectos_p_z where idProy=".$id);                
                $d->modGeneral("delete from n_proyectos_p where id=".$id);
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

   // Listado de items de la etapa **************************************************************************************
   public function listiAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);   
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            //$form->setInputFilter($album->getInputFilter());            
            //$form->setData($request->getPost());           
            //$form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            //if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $d    = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $d->modGeneral("insert into n_turnos_g_h (idTur, idHor)
                                    values(".$data->id.", ".$data->tipo.")");
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$id);
            //}
        }
      } 
$this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');      
      $o=new \Nomina\Model\Entity\Turnos($this->dbAdapter);
      $form->get("id")->setAttribute("value","$id"); 
      $form->get("ubicacion")->setValueOptions(array('1'=>'Encabezado'));       
      $arreglo='';
      $turnos = $o->getRegistro(); 
      foreach ($turnos as $dat){
         $idc=$dat['id'];$nom=$dat['codigo'];
         $arreglo[$idc]= $nom;
      }     
      $form->get("tipo")->setValueOptions($arreglo);                               

      $d = new AlbumTable($this->dbAdapter);      
      $datos = $d->getGeneral1("Select * from n_turnos_g where id=".$id);
      $valores=array
      (
           "titulo"    =>  'Horarios del turno '.$datos['nombre'],
           "datos"     =>  $d->getTurnoHorarios($id),            
           "ttablas"   =>  'dia, Tipo, Ok,Eliminar',
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "form"      =>  $form,          
           "lin"       =>  $this->lin,
           "id"        =>  $id,
       );                
       return new ViewModel($valores);        
   } // Fin listar registros items
// Fin eliminar datos
   public function listpgAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          $data = $this->request->getPost();
          if ($request->isPost()) 
          {
             $orden = (int) $data->orden;
             $d->modGeneral("update n_turnos_g_h 
                      set orden=".$orden."  
                           where id = ".$data->id);  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }

}
