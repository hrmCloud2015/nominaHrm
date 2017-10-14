<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Aumentosueldo;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

use Principal\Model\Pgenerales; // Parametros generales


class AumentosueldoController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/aumentosueldo/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Aumento de sueldo de empleados"; // Titulo listado
    private $tfor = "Documento de aumento de sueldo"; // Titulo formulario
    private $ttab = "Fecha,Empleado,Cargo, Sueldo anterior, Sueldo nuevo, Fecha de inicio , Pdf,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*,b.nombre, b.CedEmp, b.apellido,
                      c.nombre as nomCar, b.sueldo , a.sueldoNue  
                                from n_aumento_sueldo a 
                                left join a_empleados b on a.idEmp = b.id # el que reemplaza
                                left join t_cargos c on c.id=b.idCar
                                left join n_cencostos d on d.id = b.idCcos
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
        $arregloI[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arregloI);  

      $form->get("idEmp2")->setValueOptions($arreglo); 

      $form->get("idSalario")->setAttribute("value", 0 );                    
   
      // Escala salarial
      $arreglo='';
      $datos = $d->getSalarios(''); 
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['salario'];
        $arreglo[$idc]= $nom;
      }              
      $form->get("idSalario")->setValueOptions($arreglo);        
      // 
      $val=array
          (
            "0"  => 'Revisión',
            "1"  => 'Aprobado'
          );       
      $form->get("estado")->setValueOptions($val);      

      // Parametros generales
      $pn = new Pgenerales( $this->dbAdapter );
      $dp = $pn->getGeneral1(1);
      $escala = $dp['escala'];// Escala salarial 0 no, 1 si 

      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'escala'  => $escala,
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
                $u    = new Aumentosueldo($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                
                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                
                    $sueldo = 0;
                    if ($escala==1) // Buscar escala salarial
                    {
                        $datS = $d->getGeneral1("select salario from n_salarios where id = ".$data->idSalario);  
                        $sueldo = $datS['salario'];
                        $idSal = $data->idSalario;
                    }
                    else
                    {
                        $sueldo = $data->numero;
                        $idSal = 1;
                    }

                    $id = $u->actRegistro($data, $sueldo);
                    // Actualizar sueldos
                    if ($data->estado==1)
                    {
                       $d->modGeneral("update n_aumento_sueldo a   
                           inner join a_empleados b on b.id = a.idEmp
                           left join n_salarios c on c.id = a.idSal 
                             set a.sueldoAnt = b.sueldo, a.sueldoNue = c.salario, b.idSal = ".$idSal.",
                               b.sueldo = ".$sueldo."  
                           where a.id = ".$id);
                    }  
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
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Aumentosueldo($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("fechaIni")->setAttribute("value",$datos['fechai']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("idSalario")->setAttribute("value",$datos['idSal']);             
            $form->get("numero")->setAttribute("value",$datos['sueldoNue']);             
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
            $u=new Aumentosueldo($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   // Datos empleado de reemplazo
   public function listeAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         

            $data = $this->request->getPost();       
            $valores = array(
               "datos"  => $d->getGeneral1("select a.sueldo, b.nombre as nomCar  
                                              from a_empleados a
                                              inner join t_cargos b on b.id = a.idCar
                                                where a.id=".$data->idEmp),
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }            
   
}
