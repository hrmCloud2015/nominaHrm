<?php
/** STANDAR MAESTROS NISSI  */
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Nomina\Model\Entity\Conchorarios;

use Principal\Form\Formulario;     // Componentes generales de todos los formularios
use Principal\Model\ValFormulario; // Validaciones de entradas de datos
use Principal\Model\AlbumTable;    // Libreria de datos

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 


class ConchorariosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/conchorarios/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Conceptos fijos para manejo de turnos"; // Titulo listado
    private $tfor = "Actualización de conceptos fijos"; // Titulo formulario
    private $ttab = "Codigo, idCon,Nombre, Dia, Tipo, Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u = new Conchorarios($this->dbAdapter);
        $d = new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $d->getGeneral("select a.*, b.codigo, b.nombre as nomCon, case when  a.tipo = 0 then 'Ordinario' else 'Festivo' end as nomTip,
                       case when  a.modalidad = 0 then 'Diurna' else 'Nocturna' end as nomMod  
                                                 from n_conceptos_hor a 
                                                   inner join n_conceptos b on b.id = a.idConc"),            
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
      $form->get("tipo")->setValueOptions(array("0"=>"Ordinario", "1"=>"Festiva" ));

      $form->get("tipo1")->setValueOptions(array("0"=>"Diurna", "1"=>"Nocturna" ));
     
      // Conceptos 
      $arreglo='';
      $datos = $d->getConcetos(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idConc")->setValueOptions($arreglo);                                       

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
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Conchorarios($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Conchorarios($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Dotaciones guardados
            $form->get("idConc")->setAttribute("value",$datos['idConc']); 
            $form->get("tipo")->setAttribute("value",$datos['tipo']); 
            $form->get("tipo1")->setAttribute("value",$datos['modalidad']); 
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
            $u=new Conchorarios($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
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
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                               

                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();

                $d->modGeneralId("insert into n_Conchorarios_c (idHor,idCon,Horas, idUsu)
                   values(".$data->id.", ".$data->idConc.",  ".$data->numero.", ".$dt['idUsu']." )"); 
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
      $datos = $d->getConcetos(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
         $arreglo[$idc]= $nom;
      }
      $form->get("idConc")->setValueOptions($arreglo);                                       
      // 
      $dat = $d->getGeneral1("select a.nombre 
                                  from n_Conchorarios a 
                               where a.id = ".$id);
      $valores=array
      (
           "titulo"  => "Conceptos fijos del horario ".$dat['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,          
           'datos'   => $d->getGeneral("select a.id, b.nombre, a.horas  
                                          from n_Conchorarios_c a
                                            inner join n_conceptos b on b.id = a.idCon 
                                          where a.idHor =".$id),
           "ttablas" =>  "id, Conceptos, Horas, Eliminar",                   
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

                $dat = $d->getGeneral1("Select * from n_Conchorarios_c where id=".$id);
                $d->modGeneral("delete from n_Conchorarios_c where id=".$id);

                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$dat['idHor']);

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
