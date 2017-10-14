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
use Nomina\Model\Entity\ConceptosV;     // (C)
use Nomina\Model\Entity\ConceptosVC;     // Conceptos de nomina asociados a la matriz
use Nomina\Model\Entity\ConceptosVD;     // Conceptos de nomina asociados a la matriz
use Principal\Form\FormCon;            // Componentes de los conceptos


class ConceptosvController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/conceptosv/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Conceptos verticales"; // Titulo listado
    private $tfor = "Conceptos verticales de nomina"; // Titulo formulario
    private $ttab = "Matriz ,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listaAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new ConceptosV($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
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
   public function listAction() 
   { 
      $form  = new Formulario("form");
      $formn = new FormCon("form");
      
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       

      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);

      // Conceptos
      $datos = $d->getConnom2(' and tipo = 1 ');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id']; $nom=$dat['nombre'].' - ('.$dat['alias'].')';
          $arreglo[$idc]= $nom;
      }
      $form->get("idConcM")->setValueOptions($arreglo);                                          
      $datos = $d->getConnom2(' and tipo = 2 ');// Listado de conceptos
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id']; $nom=$dat['nombre'].' - ('.$dat['alias'].')';
          $arreglo[$idc]= $nom;
      }
      $form->get("idConcM2")->setValueOptions($arreglo);   
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
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');

                $d->modGeneral('delete from i_formatos_nomina_nv_c');// Conceptos aplicados a  
                $d->modGeneral('delete from i_formatos_nomina_nv_d');// Conceptos aplicados a  
                $data = $this->request->getPost();
                $id = $data->id;
                // Guardar conceptos de nominas que seran parte de la matriz
                $f = new ConceptosVC($this->dbAdapter);
                // Eliminar registros                   
                $i=0;
                foreach ($data->idConcM as $dato){
                  $idConc = $data->idConcM[$i];$i++;           
                  $f->actRegistro($idConc,$id);                
                }                
                // Guardar conceptos de nominas que seran parte de la matriz
                $f = new ConceptosVD($this->dbAdapter);
                // Eliminar registros                   
                $i=0;
                foreach ($data->idConcM2 as $dato)
                {
                  $idConc = $data->idConcM2[$i];$i++;           
                  $f->actRegistro($idConc,$id);                
                }                                
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);

        }
        
    }else{              
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');

            // Conceptos asociados a la matriz
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getGeneral('select * from i_formatos_nomina_nv_c');// Conceptos aplicados a esta matriz
            $arreglo='';
            foreach ($datos as $dat){
              $arreglo[]=$dat['idConc'];
            }                
            $form->get("idConcM")->setValue($arreglo);           
            
            // Conceptos asociados a la matriz
            $d = New AlbumTable($this->dbAdapter);            
            $datos = $d->getGeneral('select * from i_formatos_nomina_nv_d');// Conceptos aplicados a esta matriz
            $arreglo='';
            foreach ($datos as $dat){
              $arreglo[]=$dat['idConc'];
            }                
            $form->get("idConcM2")->setValue($arreglo);           

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
            $u=new ConceptosV($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
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
