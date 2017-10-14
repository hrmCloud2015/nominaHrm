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
use Nomina\Model\Entity\Tippresta; // (C)
use Nomina\Model\Entity\Tipprestan; // (C)

class TipprestaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/tippresta/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Tipos de prestamos"; // Titulo listado
    private $tfor = "Actualización tipo de prestamo"; // Titulo formulario
    private $ttab = "id,Tipo,Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new Tippresta($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
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
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);                       
      // Niveles de aspectos
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Concepto de salida del dinero
      $arreglo='';
      $datos = $d->getConnom2('and tipo=1'); 
      $arreglo[0]= 'No sale por nomina';
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConc")->setValueOptions($arreglo);                                           
      // Concepto de ingreso del dinero
      $arreglo='';
      $datos = $d->getConnom2('and tipo=2 and idTer>0 '); 
      $arreglo[0]= 'Seleccione el concepto';
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                            
      // Codigo de cuenta 
      $arreglo='';
      $datos = $d->getCuentas(''); 
      $arreglo[0]= 'Sin cuenta credito';
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['codigo'].' - '.$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo1")->setValueOptions($arreglo);

      //    
      // Tipos de nomina
      $arreglo='';
      $datos = $d->getTnom(''); 
      $arreglo = '';
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idTnom")->setValueOptions($arreglo);    
      
      $datos = $d->getTnom('');// Listado de tipos de nomina
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idTnomm")->setValueOptions($arreglo);             

      $arreglo = '';
      $arreglo[0]='QUINCENAL';
      $arreglo[1]='MENSUAL';
      $form->get("tipo3")->setValueOptions($arreglo);
      //
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
                $u    = new Tippresta($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                if ($data->id==0)
                   $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $u->actRegistro($data);             
                   $id = $data->id;
                }
                $f = new Tipprestan($this->dbAdapter);
                // Eliminar registros de tipos de nomina, solo sin tener nomina 
                $d->modGeneral("Delete from n_tip_prestamo_tn where idTpresta=".$id); 
                foreach ($data->idTnomm as $dato){
                  $idTnom = $dato[0];                      
                  $f->actRegistro($idTnom,$id);                
                }
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Tippresta($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
            $form->get("idConc")->setAttribute("value",$datos['idConS']); 
            $form->get("tipo")->setAttribute("value",$datos['idConE']); 
            $form->get("idTnom")->setAttribute("value",$datos['idTnom']); 
            $form->get("tipo1")->setAttribute("value",$datos['codigoCta']); 
            $form->get("tipo3")->setAttribute("value",$datos['tipo']); 
                // Tipos de nominas aplicadas
                $d = New AlbumTable($this->dbAdapter);            
                $datos = $d->getTippNapl(' and idTpresta='.$id);// Tipos de nomina afectadas por este automatico
                $arreglo='';            
                foreach ($datos as $dat){
                  $arreglo[]=$dat['idTnom'];
                }                
                $form->get("idTnomm")->setValue($arreglo);                             
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
            $u=new Tippresta($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
        
}
