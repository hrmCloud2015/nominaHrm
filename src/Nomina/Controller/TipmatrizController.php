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
use Nomina\Model\Entity\Tipmatriz;     // (C)
use Nomina\Model\Entity\TipmatrizC;     // Conceptos de nomina asociados a la matriz
use Principal\Form\FormCon;            // Componentes de los conceptos


class TipmatrizController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/tipmatriz/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Matrices"; // Titulo listado
    private $tfor = "Actualización de matriz"; // Titulo formulario
    private $ttab = "Matriz ,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new Tipmatriz($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
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
      $formn = new FormCon("form");
      
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Tipos de nomina
      $form->get("tipo")->setValueOptions(array("0"=>"Tipo matriz","1"=>"Selección devengados",
                                                     "2"=>"Selección deducidos","3"=>"Selección general"));                   
      $datos = $d->getTnom('');// Tipos de nomina
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id']; $nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idTnom")->setValueOptions($arreglo);                   
      // Conceptos
      $datos = $d->getConnom('');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id']; $nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }
      $form->get("idConcM")->setValueOptions($arreglo);                  
      // Calendario
      $datos = $d->getGrupo2('');// Grupo
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id']; $nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("idGrupo")->setValueOptions($arreglo);                         

      $form->get("estado")->setValueOptions(array("0"=>"Activa", "1"=>"Inactiva"));                         

      // 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $valores=array
      (
          "titulo"  => $this->tfor,
          "form"    => $form,
          "formn"   => $formn,
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
            $data = $this->request->getPost();
            //print_r($data);
            $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            

            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Tipmatriz($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
//                 print_r($data);
                if ($data->id==0)
                   $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
                else 
                {
                   $u->actRegistro($data);             
                   $id = $data->id;
                }
                // Guardar conceptos de nominas que seran parte de la matriz
                $f = new TipmatrizC($this->dbAdapter);
                // Eliminar registros 
                $d->modGeneral("Delete from n_tip_matriz_tnv where idTmatz=".$id);                 
                $i=0;
                foreach ($data->idConcM as $dato){
                  $idConc = $data->idConcM[$i];$i++;           
                  $f->actRegistro($idConc,$id);                
                }                
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Tipmatriz($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $a = $datos['nombre'];
            $b = $datos['idTnom'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$a"); 
            $form->get("idTnom")->setAttribute("value","$b"); 
            $form->get("idGrupo")->setAttribute("value",$datos['idGrup']); 
            $form->get("tipo")->setAttribute("value",$datos['tipo']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            // Conceptos asociados a la matriz
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getConaMatz(' and idTmatz='.$id);// Conceptos aplicados a esta matriz
            $arreglo='';            
            foreach ($datos as $dat){
              $arreglo[]=$dat['idConc'];
            }                
            $form->get("idConcM")->setValue($arreglo);           
            
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
            $u=new Tipmatriz($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter);
            // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                            
                $d->modGeneral("delete from n_tip_matriz_tnv where idTmatz=".$id);
                $u->delRegistro($id);
                $connection->commit();                   
                $this->flashMessenger()->addMessage('');                         
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                
            }// Fin try casth   
            catch (\Exception $e) 
            {
              if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                   $connection->rollback();
                  echo $e;
                } 
              /* Other error handling */
            }// FIN TRANSACCION                                                     

          }
          
   }
   //----------------------------------------------------------------------------------------------------------
        
}
