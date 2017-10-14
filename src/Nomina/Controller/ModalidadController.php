<?php
/** STANDAR MAESTROS NISSI  */
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Nomina\Model\Entity\Modalidad;

use Principal\Form\Formulario;     // Componentes generales de todos los formularios
use Principal\Model\ValFormulario; // Validaciones de entradas de datos
use Principal\Model\AlbumTable;    // Libreria de datos

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 


class ModalidadController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/modalidad/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Modalidades"; // Titulo listado
    private $tfor = "Actualización de modalidades"; // Titulo formulario
    private $ttab = "id ,Nombre, Conceptos fijos,Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u = new Modalidad($this->dbAdapter);
        $d = new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $d->getGeneral("select a.*, ( select count(b.id) from n_modalidad_c b where b.idMod = a.id) as num 
                            from n_modalidad a"),            
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
      // Calendario de nomina
      //$form->get("tipo")->setValueOptions(array("1"=>"Hombre","2"=>"Mujer","3"=>"Unisex" ));                                                 
      
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
                $u    = new Modalidad($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Modalidad($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Dotaciones guardados
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
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
            $u=new Modalidad($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //--------------------------------------------------------------------

   // Conceptos fijos 
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
            try 
            {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               

                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();

                $d->modGeneralId("insert into n_modalidad_c (idMod, idCon, formula ,Horas, idUsu)
                   values(".$data->id.", ".$data->idConc.", '".$data->formula."',  ".$data->numero.", ".$dt['idUsu']." )"); 
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
      }// FIN GUARDADO DE PROEYCTOS     
      // Empleados
      $arreglo='';
      $datos = $d->getConcetosFijos(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idConc")->setValueOptions($arreglo);                                       
      // 
      $dat = $d->getGeneral1("select a.nombre 
                                  from n_modalidad a 
                               where a.id = ".$id);
      $valores=array
      (
           "titulo"  => "Conceptos fijos de la modalidad ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.id, b.nombre, a.horas, a.horasFijas, a.formula , a.valida, a.si, a.no   
                                          from n_modalidad_c a
                                            inner join n_conceptos b on b.id = a.idCon 
                                          where a.idMod = ".$id),
           "ttablas" =>  "id, Conceptos, Formula, Condicion, Horas modalidad, horas conceptos, ok, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin conceptos fijos    
   // Eliminar conceptos 
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

                $dat = $d->getGeneral1("Select * from n_modalidad_c where id=".$id);
                $d->modGeneral("delete from n_modalidad_c where id=".$id);

                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$dat['idMod']);

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
             $horasFijas = (float) $data->horasFijas;
             $horas = (float) $data->horas;


             $d->modGeneral("update n_modalidad_c  
                      set horasFijas = ".$horasFijas." ,
                          horas = ".$horas." ,
                          formula = '".$data->formula."' ,
                          valida = '".$data->valida."' ,
                          si = '".$data->si."' ,
                          no = '".$data->no."' 
                      where id = ".$data->id);  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }   
}
