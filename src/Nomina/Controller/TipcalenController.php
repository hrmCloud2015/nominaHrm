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
use Nomina\Model\Entity\Tipcalen;      // (C)
use Nomina\Model\Entity\TipcalenP;     // Periodos ajustados en el calendario
use Nomina\Model\Entity\Tipcalend;     // (C) Generacion inicio calendario
use Principal\Model\NominaFunc;        // Libreria de funciones nomina


class TipcalenController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/tipcalen/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Tipo de calendario"; // Titulo listado
    private $tfor = "Actualización de tipo de calendario"; // Titulo formulario
    private $ttab = "Calendario, Tipo, Inicio, Intervalo,  Periodos, Distribución ,Editar ,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new Tipcalen($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
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
      // tipos
      $form->get("tipo")->setValueOptions( array("1"=>"Manual","2"=>"Intervalo de días","3"=>"Periodos en el año" ) );  
      
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
                $u    = new Tipcalen($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
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
            $u=new Tipcalen($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];$a = $datos['valor'];$b = $datos['fecha'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
            $form->get("numero1")->setAttribute("value","$a"); 
            $form->get("fecDoc")->setAttribute("value","$b"); 
            $form->get("tipo")->setAttribute("value",$datos['tipo']); 
            $form->get("check1")->setAttribute("value",$datos['fecIemp']);                         
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
   // Distribucion del calendario ********************************************************************************************
   public function listcAction() 
   {
      $form = new Formulario("form"); 
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      //
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $f=new NominaFunc($this->dbAdapter);
      // Validar existencia de distribucion del calendario
      $datos = $d->getGeneral1("select count(id) as num from n_tip_calendario_p where idCal=".$id);                        
      if ($datos['num']==0)
      {
        // Inicio de calendario
        $periodoI = array();
        $periodoF = array();
        $datos = $d->getGeneral1("select * from n_tip_calendario where id=".$id);                  
        $dias   = $datos['valor']; // Valor o intervalo
        $fecha = '2001-01-1'; // Fecha de inicio del calendario
        $ano  = substr($fecha,0,4);                   
        $sw = 0;
        $i  = 0;
        while($sw==0)
        {
           $dat   = $f->getPeriodo($fecha,$dias);  // dia final del periodo 
           if ($ano!=substr( $dat['fechaF'] ,0,4))
           {
              $sw=1;             
           }else
           {
             $periodoI[$i] = $fecha;         
             $periodoF[$i] = $dat['fechaF'];               
           }
           $i++;         
          $fecha    = $dat['fechaF'];           
        }   
        // Organizar periodos
        $perF = count($periodoF)-1;
        for ($i=0;$i<=$perF;$i++){ 
            $pI = substr($periodoI[$i],5,2);
            $pF = substr($periodoF[$i],5,2);
            $dI = substr($periodoI[$i],8,11);
            $dF = substr($periodoF[$i],8,11);  
            $u    = new TipcalenP($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = array("idcal" => $id,
                          "mi"    => $pI,
                          "mf"    => $pF,
                          "di"    => $dI,
                          "df"    => $dF,
                          "o"     => $i,
                          "id"    => 0);  
            $u->actRegistro($data);                            
        }
      }
      
      $valores=array
      (
        "titulo"    =>  'Distribución de los periodos en el año',
        "datos"     =>  $d->getGeneral1("select * from n_tip_calendario where id=".$id),            
        "datosC"    =>  $d->getGeneral("select * from n_tip_calendario_p where idCal=".$id." order by orden "),            
        "form"      =>  $form,
        "ttablas"   =>  "Periodo, día , final del periodo, día",
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);         
   }   
   // Actualizar datos del calendario
   public function listcgAction()            
   {         
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {       
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $u    = new TipcalenP($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
           $data = $this->request->getPost();
           $u->actRegistro($data);       
           $view = new ViewModel();        
           $this->layout('layout/blanco'); // Layout del login
           return $view;                           
        }
      }
   } // Fin actualizar cajas   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Tipcalen($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }
          
   }
   //----------------------------------------------------------------------------------------------------------
   public function listiAction()
   {
        $form = new Formulario("form");
        $id = (int) $this->params()->fromRoute('id', 0);
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        // Consultar periodos del calendario     
        $con = "select a.id, a.fechaI,a.fechaF,b.nombre as nomTnom, 
                  c.nombre as nomGrupo,a.fechaInfoI, a.fechaInfoF , a.estado 
          from n_tip_calendario_d a 
                inner join n_tip_nom b on b.id=a.idTnom
                inner join n_grupos c on c.id=a.idGrupo 
          where idCal=".$id;        
        $valores=array
        (
            "titulo"    =>  "Periodo calendario ",
            "datos"     =>  $u->getGeneral($con),            
            "ttablas"   =>  "id, Tipo de nomina,Grupo de nomina,fecha inicial,fecha final, fecha inicial consulta, fecha final consulta, Estado, ok ",
            "form"      =>  $form,
            "lin"       =>  $this->lin
        );                
        return new ViewModel($valores);
        
   } // Fin listar registros 

   public function listpgAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          $data = $this->request->getPost();
          if ($request->isPost()) 
          {
//             $orden = (int) $data->orden;
             $d->modGeneral("update n_tip_calendario_d   
                      set fechaI='".$data->fechaI."',   
                          fechaF='".$data->fechaF."',
                          fechaInfoI='".$data->fechaInfoI."',
                          fechaInfoF='".$data->fechaInfoF."', 
                          estado=".$data->estado."
                      where id = ".$data->id);  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }   

}
