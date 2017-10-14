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
use Nomina\Model\Entity\Conceptos;     // (C)
use Nomina\Model\Entity\Conceptosp;    // Procesos en conceptos
use Nomina\Model\Entity\Conceptosn;    // Tipos de nominas afectadas por conceptos automaticos
use Nomina\Model\Entity\Conceptosh;    // Conceptos hijos
use Nomina\Model\Entity\Conceptose;    // Conceptos a tipos de empleados
use Principal\Form\FormCon;            // Componentes de los conceptos


class ConceptosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/conceptos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Conceptos de nomina"; // Titulo listado
    private $tfor = "Actualización de conceptos de nomina"; // Titulo formulario
    private $ttab = "id,Codigo, Conceptos, Tipo, Valor,Estado,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $d->getConM(''), 
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
            "ttablas"   =>  $this->ttab,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
            "lin"       =>  $this->lin
        );                
        return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form  = new Formulario("form");
      $formn = new FormCon("form");
      
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      $form->get("tipo")->setValueOptions(array('1'=>'DEVENGADO','2'=>'DEDUCIDO')); 
      $form->get("tipo2")->setValueOptions(array('1'=>'HORAS','2'=>'VALOR')); 
      $form->get("tipo3")->setValueOptions(array('0'=>'NO APLICA','1'=>'SALUD','2'=>'PENSION','3'=>'CESANTIAS','4'=>'ARP')); 

      $form->get("estado")->setValueOptions(array('0'=>'ACTIVO','2'=>'INACTIVO'));
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Formula asociada
      $arreglo='';
      $datos = $d->getFormulas(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idFor")->setValueOptions($arreglo);                                                 
      $form->get("idFor")->setAttribute("value", 1 ); 
      // Tipos de Nominas
      $datos = $d->getTnom('');// Listado de tipos de nomina
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idTnomm")->setValueOptions($arreglo);             
      // Conceptos de nomina
      $datos = $d->getConnom('');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idConcM")->setValueOptions($arreglo);                   
      // Tipos de empleados afectados
      $datos = $d->getTemp('');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;      }           
      $form->get("idTempM")->setValueOptions($arreglo);                         
     
      $datos = $d->getConcetos('');// Listado de conceptos para retro
      $arreglo='';
      $arreglo[0]='SIN CRUCE';
      foreach ($datos as $dat)
      {
          $idc=$dat['id'];
          $nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo5")->setValueOptions($arreglo);
            
      // Terceros
      $datos = $d->getTerceros("");// Listado de terceros
      $arreglo='';
      foreach ($datos as $dat)
      {
          $idc=$dat['id'];$nom=$dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;      
      }           
      $form->get("idTer")->setValueOptions($arreglo);                               

      $datos = $d->getCuentas('');// Listado de cuentas
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['codigo'];$nom = $dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("codCta")->setValueOptions($arreglo);
      $form->get("tipoC")->setValueOptions($arreglo);                                     
      
      $datos = $d->getConcetos('');// Listado de conceptos para retro
      $arreglo='';
      $arreglo[0]='SIN RETRO ACTIVO';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo4")->setValueOptions($arreglo);                                     

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $valores=array
      (
          "titulo"  => $this->tfor,
          "form"    => $form,
          "formn"   => $formn,
          'url'     => $this->getRequest()->getBaseUrl(),
          'id'      => $id,
          'datos'   => $d->getProcesos(''),  // Listado de procesos   
          "datPref" => $d->getGeneral("select a.id , a.nombre, b.codCta   
                                           from n_pref_con a 
                                             left join n_conceptos_pref b on b.idPref = a.id and b.idConc = ".$id),      
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
            if ($data['check1']==0) // si es 1 trae los tipos de nominas
               $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            else   
                $form->setValidationGroup('nombre','idTnomm');
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Conceptos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();

         // INICIO DE TRANSACCIONES
         $connection = null;
         try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
	 	            $connection->beginTransaction();                
                //print_r($data);
                if ($data->id==0)
                   $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $u->actRegistro($data);             
                   $id = $data->id;
                }
                // Guardar tipos de nominas afectado por automaticos
                $f = new Conceptosn($this->dbAdapter);
                // Eliminar registros de tipos de nomina afectados por automaticos  
                $d->modGeneral("Delete from n_conceptos_tn where idConc=".$id);                 
                $i=0;
                foreach ($data->idTnomm as $dato){
                  $idTnom = $data->idTnomm[$i];$i++;           
                  $f->actRegistro($idTnom,$id);                
                }
                // Eliminar registros de procesos tipo nomina
                $d->modGeneral("Delete from n_conceptos_pr where idConc=".$id); 
                $datos = $d->getProcesos('');  // Listado de procesos
                $f = new Conceptosp($this->dbAdapter); // Entidad tabla de procesos
                foreach ($datos as $dato){
                  $i= $dato['id'];  
                  if ($data['checki'.$i]==1) // Si esta marcado se envia la informacion a la bd
                  {
                     $f->actRegistro($data['idi'.$i],$id);                
                  }
                  $i++;                  
                  //
                }                
                // Guardar conceptos hijos
                $e = new Conceptosh($this->dbAdapter);
                // Eliminar registros conceptos hijos de esta nomina
                $d->modGeneral("Delete from n_conceptos_th where idConc=".$id); 
                $i=0;
                if ($data->idConcM!='')
                {
                   foreach ($data->idConcM as $dato){
                     $idConcM = $data->idConcM[$i];  $i++; 
                     $e->actRegistro($idConcM,$id);                
                   }
                }
                // Guardar conceptos hijos
                $e = new Conceptose($this->dbAdapter);
                // Eliminar registros conceptos hijos de esta nomina
                $d->modGeneral("Delete from n_conceptos_te where idConc=".$id); 
                $i=0;
                if ($data->idTempM!='')
                {
                   foreach ($data->idTempM as $dato){
                     $idConcM = $data->idTempM[$i];  $i++; 
                     $e->actRegistro($idConcM,$id);                
                   }
                }                
                $connection->commit();
                $this->flashMessenger()->addMessage('');               
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                                
          }// Fin try casth   
          catch (\Exception $e) {
	    if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
   	        $connection->rollback();
                echo $e;
	   }
	/* Other error handling */
          }
            
                
            }
        }
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Conceptos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $a = $datos['nombre'];
            $b = $datos['tipo'];
            $c = $datos['idFor'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$a"); 
            $form->get("tipo")->setAttribute("value","$b"); 
            $form->get("codigo")->setAttribute("value",$datos['codigo']); 
            $form->get("tipo2")->setAttribute("value",$datos['valor']); 
            $form->get("tipo3")->setAttribute("value",$datos['fondo']); 
            $form->get("idFor")->setAttribute("value","$c"); 
            $form->get("check1")->setAttribute("value",$datos['auto']); 
            $form->get("check2")->setAttribute("value",$datos['info']); 
            $form->get("alias")->setAttribute("value",$datos['alias']); 
            $form->get("periodo")->setAttribute("value",$datos['perAuto']); 
            $form->get("codCta")->setAttribute("value",$datos['codCta']); 
            $form->get("natCta")->setAttribute("value",$datos['natCta']); 
            $form->get("idTer")->setAttribute("value",$datos['idTer']);  
            $form->get("check3")->setAttribute("value",$datos['nitFon']);  
            $form->get("check4")->setAttribute("value",$datos['tercero']);  
            $form->get("tipo4")->setAttribute("value",$datos['idConcRetro']);  
            $form->get("check5")->setAttribute("value",$datos['retroConv']);
            $form->get("estado")->setAttribute("value",$datos['activo']); 
            $form->get("tipo5")->setAttribute("value",$datos['idConcHere']);               
            
            // Tipos de nominas aplicadas por concepto automatico
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getConaNapl(' and idConc='.$id);// Tipos de nomina afectadas por este automatico
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idTnom'];
            }                
            $form->get("idTnomm")->setValue($arreglo);           
            // Conceptos de nominas hijas del concepto
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getConNhij(' and idConc='.$id);// Tipos de nomina afectadas por este automatico
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idCon'];
            }                
            $form->get("idConcM")->setValue($arreglo);                       
            // Procesos involucrados en el concepto
            $datos = $d->getConPro(' and idConc='.$id);// Procesos del concepto
            $arreglo='';            
            foreach ($datos as $dat){
              $i=$dat['idProc'];  
              $formn->get("checki".$i)->setAttribute("value",1); 
            }                            
            // Conceptos de nominas hijas del concepto
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getConNtemp(' and idConc='.$id);// Tipos de nomina afectadas por este automatico
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idTemp'];
            }                
            $form->get("idTempM")->setValue($arreglo);                                   
         }            
         
      }
      return new ViewModel($valores);
   } // Fin actualizar datos 
   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u = new Conceptos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d = new AlbumTable($this->dbAdapter);
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
   	        $connection->beginTransaction();                
                //            
                $d->modGeneral("delete from n_conceptos_tn where idConc = ".$id);
                $d->modGeneral("delete from n_conceptos_th where idConc = ".$id);
                $d->modGeneral("delete from n_conceptos_te where idConc = ".$id);
                $d->modGeneral("delete from n_conceptos_pr where idConc = ".$id);
                
                $u->delRegistro($id);
                //
                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }// Fin try casth   
            catch (\Exception $e) {
    	        if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	            $connection->rollback();
                    echo $e;
 	        }	
 	        /* Other error handling */
            }// FIN TRANSACCION                                                    
          }
          
   }
   //----------------------------------------------------------------------------------------------------------

   // Asingar cuenta al prefijo amarrado a la cuenta *******************************************************************************
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
                                        from n_conceptos_pref 
                                           where idConc=".$data->id." and idPref=".$data->idPref);
             if ($dat['num']==0)
             {
               $d->modGeneral("insert into n_conceptos_pref 
                      (idConc, idPref, codCta) 
                         values(".$data->id.",".$data->idPref.", '".$data->valor."')");  
             }
            else
            { 
               $d->modGeneral("update n_conceptos_pref 
                       set codCta='".$data->valor."'
                                   where idConc=".$data->id." and idPref=".$data->idPref);
            }   
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }        
}
