<?php
/** STANDAR MAESTROS NISSI  */
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Nomina \Model\Entity\Supervisores;

use Principal\Form\Formulario;     // Componentes generales de todos los formularios
use Principal\Model\ValFormulario; // Validaciones de entradas de datos
use Principal\Model\AlbumTable;    // Libreria de datos


class SupervisoresController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/supervisores/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Supervisores"; // Titulo listado
    private $tfor = "Actualización de supervisores"; // Titulo formulario
    private $ttab = "Cedula,Nombre,Apellido,Cargo,Centro de costo, Permisos,Modificar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u = new Supervisores($this->dbAdapter);
        $d = new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $d->getGeneral("Select a.id, b.CedEmp, b.nombre, b.apellido, c.nombre as nomCar,
                                               d.nombre as nomCcos  
                                               from n_supervisores a 
                                               inner join a_empleados b on b.id = a.idEmp 
                                               inner join t_cargos c on c.id = b.idCar
                                               inner join n_cencostos d on d.id = b.idCcos "),            
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
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $arreglo2 = '';
      $datos = $d->getEmp("");  
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
          $arreglo2[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo2);      
      //
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
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Supervisores($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                if ($data->id==0)
                   $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $u->actRegistro($data);             
                   $id = $data->id;
                }
                $this->flashMessenger()->addMessage('');
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Supervisores($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Dotaciones guardados
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
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
            $u=new Supervisores($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   // PERMISO DE SUPERVISORES -------------------------------------------------------------

    public function listnAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        $form = new Formulario("form");
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        $dat = $d->getGeneral1("select concat( b.nombre ,' ', b.apellido ) as nombre   
                               from n_supervisores a 
                                  inner join a_empleados b on b.id = a.idEmp      
                                   where a.id=".$id);
        $valores=array
        (
            "titulo"    => 'Puestos de trabajo asignados a '.$dat['nombre'],
            "daPer"     => $d->getPermisos($this->lin), // Permisos de esta opcion
            "datArb"    => $d->getArbolProy(""), 
            "ttablas"   => $this->ttab,
            "form"      => $form,
            "lin"       => $this->lin,
            'url'       => $this->getRequest()->getBaseUrl(),
            'id'        => $id,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
        );                       

        return new ViewModel($valores);
        
    } // Fin listar registros 

   // Opciones ver modulos
   public function listoAction()
   {
      $form = new Formulario("form");
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = New AlbumTable($this->dbAdapter);                    
      $id = $this->params()->fromRoute('id', 0);
 //     echo $id.'<br />';
      // Proyecto
      $pos = strpos($id, ".");            
      $idProy = substr($id, 0 , $pos ); // Proyecto
echo 'id proyecto:'.$idProy.'<br />';
      $id = substr($id, $pos+1, 100 );
//echo $id.'<br />';      
      $pos = strpos($id, ".");            
      $idZon = substr($id, 0 , $pos ); // Zona 
echo 'id zona:'.$idZon.'<br />';
      $id = substr($id, $pos+1, 100 );
echo 'id Coord :'.$id.'<br />';      

      $form->get("id2")->setAttribute("value",$id); // id del supervisor                     
      $form->get("id")->setAttribute("value",$idZon); // id de la zona 
      $form->get("id3")->setAttribute("value",$idProy); // id del proyecto 

      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {       
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           
           $u = New AlbumTable($this->dbAdapter);              
           $data = $this->request->getPost();

            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                                

                //$datos = $d->getMenuRoles($data->id, $data->id2);// Listado de roles y usuarios
                $datos = $d->getPuestosPorProyectos($data->id3, $data->id);// Listado de menus y roles

                foreach ($datos as $dato)
                {
                   $idLc = $dato['id'];
                   $campo = '$data->accion'.$idLc; // Archivos 

                   $u->modGeneral("delete from n_supervisores_p
                                      where idSup=".$data->id2." and idPues=".$idLc );           

                   eval("\$campo = $campo;"); 
                   if ($campo!='')
                   {
                       foreach ($campo as $valor)
                       {
                         $u->modGeneral("insert into n_supervisores_p  
                             (idSup, idZon, idPues ,valor)
                        values(".$data->id2.",".$data->id.",".$idLc.",".$valor.")" );           
                         $id = $data->id2;
                         $idProy = $data->id3;
                         $idZon = $data->id;
                       }
                   }                  
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
           // Verificar si existe opcion ya guardada           
        }
      }
      $datos = $d->getGrupo2();// Listado de grupos
      $arreglo[0]='Todos';            
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }  
      //
      $valores=array
      (
         "titulo"    =>  'Opciones',
         "datos"     =>  $d->getPuestosPorProyectos($idProy, $idZon),// Listado de menus y roles
         "datAcc"    =>  $d->getSuperPuestosItem( $idZon , $id ),// Acciones                
         "ttablas"   =>  'Opcion, Nuevo, Modificar, Eliminar, Aprobar, Vista ',
         "datGrupo"  =>  $arreglo,
         'url'       =>  $this->getRequest()->getBaseUrl(),
         "form"      =>  $form,
         "id"        =>  $id,
         "idSup"     =>  $id,
         "lin"       =>  $this->lin
      );             
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoJ'); // Layout del login
      return $view;              
   
   } // Fin listar registros items

   // FIN PERMISO DE SUPERVISORES -------------------------------------------------------------        
}
