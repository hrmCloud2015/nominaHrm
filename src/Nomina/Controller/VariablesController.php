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
use Nomina\Model\Entity\Variables;     // (C)


class VariablesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/variables/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Variables"; // Titulo listado
    private $tfor = "Actualización de variables"; // Titulo formulario
    private $ttab = "Variables ,M,E"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new Variables($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getRegistro(),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin
        );                
        return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form  = new Formulario("form");
            
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      $form->get("tipo")->setValueOptions(array('1'=>'VALOR','2'=>'QUERY')); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $valores=array
      (
          "titulo"  => $this->tfor,
          "form"    => $form,
          'url'     => $this->getRequest()->getBaseUrl(),
          'id'      => $id,
          'datos'   => $d->getProcesos(''),  // Listado de procesos
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
            $data = $this->request->getPost();
            //print_r($data);
            $form->setValidationGroup('nombre');
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) 
              {
                 $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                 $u    = new Conceptos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                 $data = $this->request->getPost();
//                 print_r($data);
                 $u->actRegistro($data);             
               }                
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
        }
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Variables($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $a = $datos['nombre'];
            $b = $datos['tipo'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$a"); 
            $form->get("tipo")->setAttribute("value","$b"); 
            $form->get("valorvar")->setAttribute("value",$datos['valor']); 
            
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
            $u=new Variables($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
        
}
