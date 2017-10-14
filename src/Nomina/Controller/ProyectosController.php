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
use Nomina\Model\Entity\Proyectos; // (C)
use Principal\Model\LogFunc; // Parametros generales

class ProyectosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/proyectos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Proyectos"; // Titulo listado
    private $tfor = "Actualización proyectos"; // Titulo formulario
    private $ttab = "id,Proyecto, Cliente,Personal,Puestos de trabajo,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        $form = new Formulario("form");
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $con = '';
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {
            $data = $this->request->getPost();
            $con = "where a.id > 0 
                       
                and ( select count(b.id) 
                         from n_proyectos_e b 
                         inner join a_empleados bb on bb.id = b.idEmp 
                        where b.idProy=a.id 
                          and ( bb.CedEmp like '%".$data->nombre."%' and 
                          bb.nombre like '%".$data->nombre."%' or bb.apellido like '%".$data->nombre."%' )  ) > 0 "; 
        }
      }      
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.id, a.nombre , 
                                 (select count(b.id) from n_proyectos_e b where b.idProy=a.id) as numE,
                                 (select count(b.id) from n_proyectos_p b where b.idProy=a.id) as numP,
                        b.nombre as nomCli  
                                     from n_proyectos a 
                                       inner join n_clientes b on b.id = a.idCli ".$con),            
            "form"      =>  $form, 
            'url'     => $this->getRequest()->getBaseUrl(),
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
                $u    = new Proyectos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Proyectos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
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
            $u=new Proyectos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
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
                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();               
                if ($data->idPtra>0)
                {  
                   $d->modGeneral("insert into n_proyectos_ep (idProy, idIproy, idEmp, idPtra, prog, horasLiq, idUsu )
                     values(".$data->id.", ".$idIproy.",".$data->idEmp.", ".$data->idPtra.", ".$data->tipo.", ".$data->tipo1.", ".$dt['idUsu'].")"); 
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
      }// FIN GUARDADO DE PROEYCTOS     
      // Empleados
      $arreglo='';
      $datos = $d->getGeneral("select a.id, a.CedEmp, a.nombre, a.apellido 
                                      from a_empleados a 
                                    where a.estado=0 
                           and 
              not exists ( select null 
                               from n_proyectos_ep b 
                              where b.idEmp = a.id and b.estado = 0 ) 
                         order by a.nombre, a.apellido"); 
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
      if ($arreglo!='')         
          $form->get("idPtra")->setValueOptions($arreglo);                        
      else
          echo 'DEBE TENER AL MENOS UN PUESTO DE TRABAJO';  
      // 
      $form->get("tipo")->setValueOptions( array("0" => "No",
                                                 "1" => "Si" ) );

      $form->get("tipo1")->setValueOptions( array("0" => "Por dias",
                                                 "1" => "Por horas" ) );

      $dat = $d->getGeneral1("select b.nombre 
                                  from n_proyectos_p a 
                                 inner join n_proyectos b on b.id = a.idProy 
                               where a.idProy=".$id);
      $valores=array
      (
           "titulo"  => "Empleados proyecto ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.id, b.CedEmp, b.nombre, b.apellido, c.nombre as nomCar,
                                           a.fechaI, a.fechaF, a.horas, a.sueldo, b.id as idEmp,
                        case when a.prog = 1 then 'Programacion' else '' end as prog,
                        case when a.horasLiq = 1 then 'Liquidados por horas' else 'Por dias' end as horasLiq , a.relevante                                               
                                        from n_proyectos_e a
                                           inner join a_empleados b on b.id = a.idEmp
                                           inner join t_cargos c on c.id = b.idCar 
                                           where a.idProy=".$id),
           'datTtra'   => $d->getGeneral("select a.id , b.nombre as nomPtra,d.nombre as nomSed , a.idEmp   
                                        from n_proyectos_ep a
                                           inner join n_proyectos_p b on b.id = a.idPtra
                                           inner join n_proyectos_e c on c.id = a.idIproy 
                                           inner join t_sedes d on d.id = b.idSed 
                                           where a.estado=0 and a.idProy =".$id." order by b.nombre "), // Puesto de trabajo           
           "ttablas" =>  "Puesto, Cargo, Empleado, Sede, Sueldo, Horas/ tipo, Periodo, Ok, Eliminar",                   
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
      foreach ($datos as $dat)
      {
         $idc=$dat['id'];$nom = $dat['nombre'].' ('.$dat['departamento'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCiu")->setValueOptions($arreglo);  
      // Sede    
      $arreglo='';
      $datos = $d->getSedes(); 
      foreach ($datos as $dat)
      {
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
           'datos'   => $d->getGeneral("select a.* , d.nombre as nomZon, e.nombre as nomSede, ( select count(aa.id) from n_proyectos_ep aa where aa.idPtra = a.id ) as numPtra     
                                        from n_proyectos_p a
                                          left join n_proyectos_p_z b on b.idPtra = a.id 
                                          left join t_sedes_z c on c.id = b.idSzon 
                                          left join n_zonas d on d.id = c.idZon  
                                           left join t_sedes e on e.id = c.idSed   
                                    where a.idProy=".$id),
           "ttablas" =>  "Zona/sede/Puesto, Ciudad/Direccion , Ok, Eliminar",              
           "datCiu"  => $d->getCiudades(''),      
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

                $dat = $d->getGeneral1("Select * from n_proyectos_e where id=".$id);
                $d->modGeneral("delete from n_proyectos_ep where idIproy=".$id);
                $d->modGeneral("delete from n_proyectos_e where id=".$id);

                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$dat['idProy']);

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
                $d->modGeneral("delete from n_proyectos_p_z where idPtra=".$id);                
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
   // Pago de 
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
             $ver = (int) $data->ver;
             $d->modGeneral("update n_proyectos_p  
                      set nombre='".$data->nombre."', 
                          direccion='".$data->dir."', 
                          idCiu=".$data->ciudad."
                      where id = ".$data->id);  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }

   // PUESTOS DE TRABAJO
   public function listptAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $t = new LogFunc($this->dbAdapter);
      $dt = $t->getDatLog();

      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');

            $data = $this->request->getPost();

         }
      }   

      $con = " and ( c.nombre like '%".$data->nombre."%' 
                       or b.nombre like '%".$data->nombre."%' or 
                    a.nombre like '%".$data->nombre."%'  ) ";
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              "datos"   => $d->getPuestosCon($con),
              'url'       =>  $this->getRequest()->getBaseUrl(),              
              "lin"       =>  $this->lin, 
              "idReq"     =>  $data->idReq, 
              "ttablas"   =>  ",Grupo, Articulo, Entregar",
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;              

   }// FIN PUESTOS DE TRABAJO   
   // Guardado puesto de trabajo
   public function listptgAction()  
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) 
         {             
             $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
             $data = $this->request->getPost();   
             $d = new AlbumTable($this->dbAdapter);         
             $t = new LogFunc($this->dbAdapter);
             $dt = $t->getDatLog();               

             $connection = null;
             try 
             {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               
                // Buscar datos del proyecto actual 
                $dat = $d->getGeneral1("select * 
                                        from n_proyectos_ep a
                                          where id = ".$data->idPt); 
                $idProyN = $dat['idProy'];
                $idEmp = $dat['idEmp'];
                // Buscar datos del nuevo proyecto 
                $dat = $d->getGeneral1("select * 
                                        from n_proyectos_p a
                                          where id = ".$data->idPues); 
                $idPtraN = $dat['id'];
                $idProyN = $dat['idProy'];
                $idSedN = $dat['idSed'];
                $idCiuN = $dat['idCiu'];
                // Cambiar de proyecto 
                $dat = $d->getGeneral1("select * from n_proyectos_e 
                                           where idEmp = ".$idEmp);
                $idIproy = $dat['id'];
                $d->modGeneral("update n_proyectos_e set idProy = ".$idProyN." 
                                           where idEmp = ".$idEmp);               
                
                $d->modGeneral("insert into n_proyectos_ep 
              (idProy, idIproy, idEmp, idPtra, prog, horasLiq, idUsu, idProCa )
         values(".$idProyN.", ".$idIproy.",".$idEmp.", ".$idPtraN.", 1, 0, ".$dt['idUsu'].", ".$data->idPt.")");

                // Inactiva puesto anterior 

                $dat = $d->modGeneral("update n_proyectos_ep set estado=1,
                                             idUsuC = ".$dt['idUsu'].",
                                             fechaC = '".$dt['fecSis']."' 
                                          where id = ".$data->idPt); 

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

             $view = new ViewModel();        
             $this->layout('layout/blancoC'); // Layout del login
             return $view;                                   
         }
      }        
   } // Seleccionar puesto de trabajo            

   // Pago de 
   public function listpgeAction()
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
             $ver = (int) $data->ver;
             $d->modGeneral("update n_proyectos_e   
                      set relevante=".$data->relevo."
                      where id = ".$data->id);  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }   
}
