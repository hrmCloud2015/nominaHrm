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
use Nomina\Model\Entity\Tipaus; // (C)
use Nomina\Model\Entity\TipausC; // (C)

class TipausController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/tipaus/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Tipos de ausentismos"; // Titulo listado
    private $tfor = "Actualización tipo de ausentismos"; // Titulo formulario
    private $ttab = "id,Tipo,Modificar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);
        $u=new Tipaus($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getRegistro(),            
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
      $form->get("tipo")->setValueOptions(array("1"=>"Remunerada","2"=>"No remunerada" ));                                           
      // Conceptos de nomina
      $arreglo='';
      $datos = $d->getConnom(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConcM")->setValueOptions($arreglo);                                                 
      $form->get("idConcM2")->setValueOptions($arreglo);                                                 
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
                $u    = new Tipaus($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                if ($data->id==0)
                   $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $u->actRegistro($data);             
                   $id = $data->id;
                }
                // Eliminar registros 
                $f    = new TipausC($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $d->modGeneral("Delete from n_tip_aus_c where idTaus=".$id);                 
                $i=0;
                foreach ($data->idConcM as $dato){
                  $idConc = $data->idConcM[$i];$i++;           
                  $f->actRegistro($idConc,$id,1);                
                }                           
                $i=0;

                $this->flashMessenger()->addMessage('');
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Tipaus($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
            $form->get("tipo")->setAttribute("value",$datos['tipo']);
            // Conceptos asociados a la matriz
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getConTaus(' and idTaus='.$id);// Conceptos empresa
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idConc'];
            }                
            $form->get("idConcM")->setValue($arreglo);  
            //
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
            $u=new Tipaus($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
        
}
