<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Embargos;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 

class EmbargosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/embargos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Embargos a empleados"; // Titulo listado
    private $tfor = "Documento de embargo"; // Titulo formulario
    private $ttab = "Fecha,Fec apro.,Empleado,Cargo,Centro de costos,Tipo,Estado, Pdf  ,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getGeneral("select a.*, b.CedEmp, b.nombre as nomEmp, b.apellido as nomApe, c.nombre as nomCar, d.nombre as nomCcos,
                                            e.nombre as nomTemb 
                                            from n_embargos a  
                                            inner join a_empleados b on b.id=a.idEmp
                                            inner join t_cargos c on c.id=b.idCar 
                                            inner join n_cencostos d on d.id=b.idCcos 
                                            inner join n_tip_emb e on e.id=a.idTemb 
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
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  
      // 
      $arreglo='';
      $datos = $d->getTemb(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);        
      //
      $datos = $d->getTerceros('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom = $dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTer")->setValueOptions($arreglo);        
      $datos = $d->getBancos('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom = $dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idBanco")->setValueOptions($arreglo);              

      // Conceptos de nomina
      $datos = $d->getConnom2(' and tipo = 1 ');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idConcM")->setValueOptions($arreglo);                         

      // Conceptos de nomina
      $datos = $d->getConnom2('and tipo= 2 ');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idConcM2")->setValueOptions($arreglo);                               

      $val=array
          (
            "0"  => 'Revisión',
            "1"  => 'Aprobado',
            "2"  => 'Terminado'
          );       
      $form->get("estado")->setValueOptions($val);      

      // Devengado
      $datos = $d->getGeneral('select * from n_embargos_dev where idEmb='.$id);// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['idConc'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idConcM")->setValue($arreglo);                         

      // Deducido
      $datos = $d->getGeneral('select * from n_embargos_ded where idEmb='.$id);// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['idConc'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idConcM2")->setValue($arreglo);                         

      
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
                $u    = new Embargos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();

                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();                

                   $data = $this->request->getPost();
                   $id = $u->actRegistro($data);
                   // Devengados  
                   $d->modGeneral("Delete from n_embargos_dev where idEmb=".$id);                 
                   $i=0;
                   if ($data->idConcM != '')
                   { 
                     foreach ($data->idConcM as $dato)
                     {
                        $idConc = $data->idConcM[$i];$i++;           
                        $d->modGeneral("insert into n_embargos_dev (idEmb, idConc, idUsu)
                                       values(".$id.",".$idConc.",".$dt['idUsu'].") ");                
                      }  
                   }                
                   // Deducidos  
                   $d->modGeneral("Delete from n_embargos_ded where idEmb=".$id);                 
                   $i=0;
                   if ($data->idConcM2 != '')
                   {                    
                     foreach ($data->idConcM2 as $dato)
                     {
                        $idConc = $data->idConcM2[$i];$i++;           
                        $d->modGeneral("insert into n_embargos_ded (idEmb, idConc, idUsu)
                                       values(".$id.",".$idConc.",".$dt['idUsu'].") ");                
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
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Embargos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("tipo")->setAttribute("value",$datos['idTemb']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("formula")->setAttribute("value",$datos['formula']); 
            $form->get("numero")->setAttribute("value",$datos['valor']); 
            $form->get("idTer")->setAttribute("value",$datos['idTer']); 
            $form->get("formaPago")->setAttribute("value",$datos['idForP']); 
            $form->get("numCuenta")->setAttribute("value",$datos['numCuenta']); 
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
            $u=new Embargos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   
}
