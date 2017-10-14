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
use Nomina\Model\Entity\Terceros; // (C)

class TercerosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/terceros/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Terceros"; // Titulo listado
    private $tfor = "Actualización tercero"; // Titulo formulario
    private $ttab = "Terceros, Nit, Dv ,Editar,Sedes,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*, count(b.id) as numSuc 
                                            from n_terceros a
                                            left join n_terceros_s b on b.idTer = a.id
                                            group by a.id"),            
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
                $u    = new Terceros($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $id = $u->actRegistro($data);
                $d  = new AlbumTable($this->dbAdapter);
                if ($data->id == 0) // Si es la primera vez guarda en la tabla de sucursal
                {
                	  $d->modGeneral("insert into n_terceros_s (idTer, nombre, central) values(".$id.", '".$data->nombre."', 1)");
                }
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Terceros($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
            $form->get("codigo")->setAttribute("value",$datos['codigo']); 
            $form->get("numero")->setAttribute("value",$datos['dv']);      
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
   
   
   // Sucursales para el tercero *********************************************************************************************
   public function listiAction()
   {
   	$form = new Formulario("form");
   	//  valores iniciales formulario   (C)
   	$id = (int) $this->params()->fromRoute('id', 0);
   	$form->get("id")->setAttribute("value",$id);
   	// 
   	$this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
   	$d = new AlbumTable($this->dbAdapter);
   	if($this->getRequest()->isPost())
   	{
   		$request = $this->getRequest();
   		if ($request->isPost()) {
   	  	   $data = $this->request->getPost();
   		   $d->modGeneral("insert into n_terceros_s (idTer, nombre) values(".$data->id.", '".$data->nombre."')");	
   	   	   return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);   			
   		}
   	}// Guardar sucrusales del tercero 
   	//	   	
   	$datos = $d->getGeneral1("Select * from n_terceros where id=".$id);
   	$valores=array
   	(
   			"titulo"  => "Sedes o sucursales de ".$datos['nombre'].' Nit: '.$datos['codigo'],
   			"form"    => $form,
   			'url'     => $this->getRequest()->getBaseUrl(),
   			'id'      => $id,
   			'datos'   => $d->getGeneral("Select * from n_terceros_s where idTer=".$id),
   			"ttablas" =>  'Nombre de la sede o sucursal, Eliminar',
   			"lin"     => $this->lin
   	);   
   	return new ViewModel($valores);

   } // Fin actualizar datos   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Terceros($this->dbAdapter);  // 
            $d=new AlbumTable($this->dbAdapter); 
            $d->modGeneral("delete from n_terceros_s where idTer = ".$id);
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   // Eliminar dato ********************************************************************************************
   public function listidAction()
   {
   	$id = (int) $this->params()->fromRoute('id', 0);
   	if ($id > 0)
   	{
   		$this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
   		$d=new AlbumTable($this->dbAdapter);
   
   		$datos = $d->getGeneral1("select idTer from n_terceros_s where id=".$id);
   		$d->modGeneral("delete from n_terceros_s where id=".$id);
   		//$u=new Auto($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)
   		//$u->delRegistro($id);
   		return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idTer']);
   	}
   }// Fin eliminar datos   
   //----------------------------------------------------------------------------------------------------------
        
}
