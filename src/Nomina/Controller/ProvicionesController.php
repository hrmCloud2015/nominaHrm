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
use Nomina\Model\Entity\Proviciones; // (C)

class ProvicionesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/proviciones/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Provisiones"; // Titulo listado
    private $tfor = "Actualización Provisiones"; // Titulo formulario
    private $ttab = "id, Provisiones,Cuenta debito, Cuenta credito, Porcentaje,Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select *, case nombre when 1 then 'Cesantias' 
                                        when 2 then 'Intereses' 
					when 3 then 'Primas' 
					when 4 then 'Vacaciones' 
					when 5 then 'Salud' 
					when 6 then 'Pensiones' 
					when 7 then 'Caja de compensación' 
					when 8 then 'Sena' 
					when 9 then 'Icbf'
					when 10 then 'Riesgos profesionales'                                          
					end as nomProv      from n_proviciones order by id"),            
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
          $idc = $dat['codigo'];$nom = $dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("codCta")->setValueOptions($arreglo);                                           
      $form->get("tipo2")->setValueOptions($arreglo);

      $form->get("tipoC")->setValueOptions($arreglo);

      $datos = $d->getProcesos('');// Listado de procesos
      $arreglo='';
      foreach ($datos as $dat){
          $idc = $dat['id'];$nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo")->setValueOptions($arreglo);
      
      //$form->get("tipo")->setValueOptions(array("1" => "Cesantias",
        //                                         "2" => "Intereses",
          //                                       "3" => "Primas",
            //                                     "4" => "Vacaciones",
              //                                   "5" => "Salud",
                //                                 "6" => "Pensión",
                  //                               "7" => "Caja de compensación",          
                    //                             "8" => "Sena",                    
                      //                           "9" => "Icbf",                    
                        //                         "10" => "Riesgos //profesionales",                    
          //));                                           
      
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
          "datPref" => $d->getGeneral("select a.id , a.nombre, b.codCta   
                                           from n_pref_con a 
                                             left join n_proviciones_pref b on b.idPref = a.id and b.idProv = ".$id),                 
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
                $u    = new Proviciones($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Proviciones($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("tipo")->setAttribute("value",$datos['idProc']); 
            $form->get("tipo2")->setAttribute("value",$datos['codCtaC']); 
            $form->get("codCta")->setAttribute("value",$datos['codCtaD']); 
            $form->get("numero")->setAttribute("value",$datos['porc']);             
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
            $u=new Proviciones($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
   public function listpgAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          $data = $this->request->getPost();
          if ($request->isPost()) 
          {

             $dat = $d->getGeneral1("Select count(id) as num  
                                        from n_proviciones_pref 
                                           where idProv=".$data->id." and idPref=".$data->idPref);
             if ($dat['num']==0)
             {
               $d->modGeneral("insert into n_proviciones_pref 
                      (idProv, idPref, codCta) 
                         values(".$data->id.",".$data->idPref.", '".$data->valor."')");  
             }
            else
            { 
               $d->modGeneral("update n_proviciones_pref  
                       set codCta='".$data->valor."'
                                   where idProv=".$data->id." and idPref=".$data->idPref);
            }   
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }                
}
