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
use Nomina\Model\Entity\Bancos; // (C)

class BancosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/bancos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Bancos"; // Titulo listado
    private $tfor = "Actualización banco"; // Titulo formulario
    private $ttab = "id, Bancos, Alias, Codigo, Numero de cuenta, Planos,Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.id, a.nombre as alias,  
                   case when a.idBan=0 then a.nombre else c.nombre end as nombre,
              case when a.codigo=0 then c.codigo else a.codigo end codigo, a.numCuenta, 
                                  ( select count(d .id) from n_bancos_plano d where d.idBanPlano =a.id ) as conAut   
                                                 from n_bancos a
                                                    left join c_bancos c on c.id = a.idBan # Tabla maestra de bancos  "),            
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
      $d = new AlbumTable($this->dbAdapter); 
      
      $datos = $d->getCuentas('');// Listado de cuentas
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['codigo'];$nom = $dat['codigo'].'-'.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("codCta")->setValueOptions($arreglo);                                     

      $datos = $d->getBancosPlantilla('');// Listado de cuentas
      $arreglo='';
      foreach ($datos as $dat)
      {
          $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idBan")->setValueOptions($arreglo);                                     

      $form->get("tipo")->setValueOptions(array( "0"=> "Entidad financiera", "1"=> "Pago por tesoreria" ));

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
                $u    = new Bancos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Bancos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("idBan")->setAttribute("value",$datos['idBan']); 
            $form->get("numero1")->setAttribute("value",$datos['numCuenta']); 
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
            //$form->get("codigo")->setAttribute("value",$datos['nit']); 
            $form->get("tipo")->setAttribute("value",$datos['tipo']); 
            $form->get("codCta")->setAttribute("value",$datos['codCta']); 
            $form->get("check2")->setAttribute("value",$datos['emp']);    
            $form->get("numero")->setAttribute("value",$datos['codigo']);          
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
            $u=new Bancos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------


   // Listado de items de la etapa **************************************************************************************
   public function listiAction()
   {
      $form = new Formulario("form");
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');      
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
            if ($form->isValid()) 
            {
               $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
               $data = $this->request->getPost();
               $d = new AlbumTable($this->dbAdapter);
               $dat = $d->getGeneral1("select count(id) as num 
                          from n_bancos_plano 
                            where idBanPlano=".$data->id." and idBan=".$data->tipo);
               if ($dat['num']==0)               
                  $d->modGeneral("insert into n_bancos_plano (idBanPlano, idBan)
                         values(".$data->id.",".$data->tipo.")");                
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);
               //               
            } 
        }
      }       
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);            
      $d = New AlbumTable($this->dbAdapter);      
      
      $datos = $d->getBancosPlantilla('');// Listado de cuentas
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo")->setValueOptions($arreglo);  
      $datos = $d->getGeneral1("Select * from n_bancos where id=".$id);
      
      $valores=array
      (
           "titulo"    =>  'Planos asociados ',
           "empleado"  =>  $datos['nombre'],
           "datos"     =>  $d->getGeneral("select a.id, c.nombre , case when a.codBanco = 0 then 'CODIGO STANDAR DEL BANCO' else a.codBanco end as codBanco  
                                              from n_bancos_plano a 
                                                 inner join n_bancos b on b.id = a.idBanPlano
                                                 inner join c_bancos c on c.id = a.idBan 
                                              where a.idBanPlano = ".$id),// Listado de formularios            
           "ttablas"   =>  'id, Banco, Codigo , Eliminar',
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
            $d=new AlbumTable($this->dbAdapter);

            $datos = $d->getGeneral1("select idBanPlano  
                                         from n_bancos_plano where id=".$id);             
            $d->modGeneral("delete from n_bancos_plano where id=".$id);                     
            //$u=new Auto($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                    
            //$u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idBanPlano']);
          }          
   }// Fin eliminar datos            
}
