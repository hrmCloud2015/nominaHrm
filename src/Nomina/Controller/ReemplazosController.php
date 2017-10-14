<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Reemplazos;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

class ReemplazosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/reemplazos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Reemplazos de empleados"; // Titulo listado
    private $tfor = "Documento de reeemplazos"; // Titulo formulario
    private $ttab = "Fecha,Empleado,Cargo, Reemplazado por,Cargo ,Desde, Hasta,Estado, Pdf  ,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*,b.nombre, b.CedEmp, b.apellido,
                      c.nombre as nomCar, b.sueldo,  # Empleado que reemplaza 
                      e.CedEmp as CedEmpR, e.nombre as nombreR, e.apellido as apellidoR,  # Empleado al que reemplaza
                      f.nombre as nomCarR, e.sueldo as sueldoR  
                                from n_reemplazos a 
                                left join a_empleados b on a.idEmp = b.id # el que reemplaza
                                left join t_cargos c on c.id=b.idCar
                                left join n_cencostos d on d.id = b.idCcos
                                left join a_empleados e on a.idEmpR = e.id # el que reemplaza
                                left join t_cargos f on f.id = e.idCar
                                left join n_cencostos g on g.id = e.idCcos 
                                order by a.fecDoc desc"),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado 
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
      // Sedes
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Empleados
      $d = New AlbumTable($this->dbAdapter);      
      $datos = $d->getEmp('');
      $arreglo='';
      $arregloI[0]='SIN REEMPLAZO';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
        $arregloI[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arregloI);  

      $form->get("idEmp2")->setValueOptions($arreglo);        
   
      // Escala salarial
      $arreglo='';
      $arreglo[0]='Diferencia en sueldo';
      $datos = $d->getSalarios(''); 
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['salario'];
        $arreglo[$idc]= $nom;
      }              
      $form->get("idSalario")->setValueOptions($arreglo);        
      // 
      $val=array
          (
            "0"  => 'Revisión',
            "1"  => 'Aprobado'
          );       
      $form->get("estado")->setValueOptions($val);      
      
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
            $form->setValidationGroup('id','idEmp','fechaIni','fechaFin'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Reemplazos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                
                $u->actRegistro($data);
                // Actualizar empleado                 
                
                $this->flashMessenger()->addMessage(''); 
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Reemplazos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("idEmp2")->setAttribute("value",$datos['idEmpR']); 
            $form->get("fechaIni")->setAttribute("value",$datos['fechai']); 
            $form->get("fechaFin")->setAttribute("value",$datos['fechaf']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("idSalario")->setAttribute("value",$datos['idSal']);             
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
            $u=new Reemplazos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

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
   
}
