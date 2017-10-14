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
use Nomina\Model\Entity\Plancuentas; // (C)

class PlancuentasController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/plancuentas/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Plan de cuentas"; // Titulo listado
    private $tfor = "Actualización plan de cuentas"; // Titulo formulario
    private $ttab = "Codigo de cuenta,Nombre,Auxiliar, Gasto, C. Costo, Tercero,Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        $form = new Formulario("form");  
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        // Si es por busqueda
        $con = ' and id=-55'; // Pa que no busque nada
        $bus = 0;
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) {
              $data = $this->request->getPost();        
              $con = " and ( ( codigo like '%".$data->cedula."%') 
                   or ( nombre like '%".$data->cedula."%')  ) ";
              $bus = 1;
            }
        }        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select *, case when aux then 'Si' else 'No' end as paux 
                  , case when gast then 'Si' else 'No' end as pgas
                  , case when ter then 'Si' else 'No' end as pter
                  , case when ccos then 'Si' else 'No' end as pccos 
                  from n_plan_cuentas where id>0 ".$con." order by codigo"),            
            "ttablas"   =>  $this->ttab,
            "form"      => $form,
            'url'     => $this->getRequest()->getBaseUrl(),
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
                $u    = new Plancuentas($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Plancuentas($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
            $form->get("codigo")->setAttribute("value",$datos['codigo']); 
            $form->get("check1")->setAttribute("value",$datos['ter']);             
            $form->get("check2")->setAttribute("value",$datos['ccos']);             
            $form->get("check3")->setAttribute("value",$datos['gast']);
            $form->get("check4")->setAttribute("value",$datos['asociada']);
            $form->get("check5")->setAttribute("value",$datos['aux']);                         
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
            $u=new Plancuentas($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
        
}
