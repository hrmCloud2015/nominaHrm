<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Liquidacion;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

class LiquidacionController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/liquidacion/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Liquidación de empleados"; // Titulo listado
    private $tfor = "Documento de liquidación"; // Titulo formulario
    private $ttab = "Fecha,Fec apro.,Empleado,Cargo,Centro de costos,Tipo,Fin de contato, Estado, Pdf  ,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*,b.nombre, b.CedEmp, b.apellido, c.nombre as nomCar, 
                                d.nombre as nomCcos, d.nombre as nomLiqu
                                from n_liquidacion a inner join a_empleados b on a.idEmp=b.id 
                                left join t_cargos c on c.id=b.idCar
                                inner join n_cencostos d on d.id=b.idCcos
                                inner join n_tip_liqu e on e.id=a.idTliqu 
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
        $idc=$dat['id'];$nom=$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  
      // 
      $arreglo='';
      $datos = $d->getLiquidacion('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idInc")->setValueOptions($arreglo);              
      // Motivos de retiro
      $arreglo='';
      $datos = $d->getMotRetiro();
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("tipo")->setValueOptions($arreglo);              
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
            $form->setValidationGroup('id','idEmp','fechaIni'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Liquidacion($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                
                $u->actRegistro($data);
                // Actualizar empleado                 
                $d = New AlbumTable($this->dbAdapter);  
                
                //if ($data->estado==1)
                //   $d->modGeneral("update a_empleados set idAus=".$data->id." where id=".$data->idEmp); 
                
                $this->flashMessenger()->addMessage(''); 
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Liquidacion($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("idInc")->setAttribute("value",$datos['idTliqu']); 
            $form->get("fechaIni")->setAttribute("value",$datos['fechaf']); 
            $form->get("tipo")->setAttribute("value",$datos['idMot']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
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
            $u=new Liquidacion($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   // VALIDACION DEL PERIODO PARA GUARDADO DE DATOS
   public function listgAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $u->getGeneral1("select idGrup from a_empleados where id=".$data->idEmp);            
            $idGrup = $datos['idGrup'];
            $datos = $u->getGeneral1("select a.idTnom, b.idTcal from n_tip_aus a 
                        inner join n_tip_nom b on b.id=a.idTnom  
                        where a.id=".$data->idInc);
            // Buscar datos del periodo
            $datos = $u->getCalenIniFin2($idGrup, $datos['idTcal'], $datos['idTnom']); 
            $arreglo = '';
            foreach ($datos as $dat){
                $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
                $arreglo[$idc]= $nom;
                break; 
            }  
            // Comprar el periodo que se intenta guardar
            $fecSis = $data->fechaIni;
            $sw = 0;
            // Fecha del sistema
            $fechaI = $dat['fechaI'];
            $valido = 0;
            if ($fecSis < $fechaI ) // Si es menor que la fecha del sistema no debe guardar el documento
                $valido = 1;
            
            $valores = array(
               "verPer" => $valido,
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }            
   
}
