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
use Nomina\Model\Entity\Subgrupos; // (C)
use Nomina\Model\Entity\Subgruposi; // (C)

class SubgruposController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/subgrupos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Sub grupos de nomina"; // Titulo listado
    private $tfor = "Actualización de sub grupo de nomina"; // Titulo formulario
    private $ttab = "id, Sub grupo, Personal,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $d->getGeneral("select a.*, 
            ( select count(b.id) 
                    from n_subgrupos_e b 
                         inner join a_empleados c on c.id = b.idEmp 
                           where c.estado = 0 and c.activo = 0 and c.finContrato=0 and b.idSub = a.id ) as num 
                             from n_subgrupos a group by a.id"),            
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
                $u    = new Subgrupos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Subgrupos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
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
            $u=new Subgrupos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------

   public function listiAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = New AlbumTable($this->dbAdapter);      

      $arreglo='';
      $datos = $d->getEmpSubgrupos("");// Listado de empleados que no estan en sucursa
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
          $arreglo[$idc]= $nom;
      }      
      if ( $arreglo != '' )
           $form->get("idEmp")->setValueOptions($arreglo);        
      
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
                $u    = new Subgruposi($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();                
               // print_r($data);
                $d->modGeneral("update a_empleados 
                                    set idSgrup=".$data->id." where id = ".$data->idEmp);
                $u->actRegistro($data,$id);
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
      } 
      
      $u=new Subgruposi($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
      $valores=array
      (
           "titulo"    =>  'Empleados del subgrupo',
           "datos"     =>  $d->getGeneral("select a.id, b.CedEmp, b.nombre, 
                            b.apellido,  c.nombre as nomGrup      
                                 from n_subgrupos_e a 
                                      inner join a_empleados b on b.id = a.idEmp 
                                      inner join n_grupos c on c.id = b.idGrup 
                                 where a.idSub = ".$id),// Listado de formularios            
           "ttablas"   =>  'id, Cedula, Nombres, Apellidos, Grupo  , Eliminar',
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
            $u=new Subgruposi($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d = New AlbumTable($this->dbAdapter);  
            // bucar id de parametro
            $datos = $d->getGeneral1("select idSub from n_subgrupos_e where id=".$id);// Listado de formularios                                
            $d->modGeneral("update n_subgrupos_e a
                              inner join a_empleados b on b.id = a.idEmp 
                           set b.idSgrup = 0 where a.id = ".$id);
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idSub']);
          }          
   }// Fin eliminar datos        
}
