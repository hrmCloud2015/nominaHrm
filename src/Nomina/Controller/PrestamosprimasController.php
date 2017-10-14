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
use Nomina\Model\Entity\Auto; // (C)
use Nomina\Model\Entity\Auton; // (C)


class PrestamosprimasController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/prestamosprimas/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Descuentos colectivos en primas"; // Titulo listado
    private $tfor = "Descuentos colectivos en primas"; // Titulo formulario
    private $ttab = "Cedula, Empleado, Prestamos"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $form = new Formulario("form");        
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter); 

      $arreglo='';
      $datos = $d->getTpres("");
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("tipo")->setValueOptions($arreglo);                    

      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $d->getGeneral("select distinct a.id,a.CedEmp, a.nombre as nomEmp,
                                          a.apellido, ( select count(g.id) from n_presta_primas g where g.idEmp = a.id and g.idIpres=f.id) as num  
                                 from a_empleados a 
                                    inner join n_prestamos e on e.idEmp = a.id 
                                    inner join n_prestamos_tn f on f.idPres = e.id 
                                     where e.estado = 1  
                          group by a.id order by a.nombre,a.apellido"),            
        "ttablas"   =>  $this->ttab,
        'url'       =>  $this->getRequest()->getBaseUrl(),
        "form"      =>  $form,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 

   //----------------------------------------------------------------------------------------------------------
   // FUNCIONES ADICIONALES GUARDADO DE ITEMS   
     
   // Listado de items de la etapa **************************************************************************************
   public function listiAction()
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');      
      $d = New AlbumTable($this->dbAdapter);          
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);

      $datos2 = $d->getGeneral1("select a.id , a.fechaI, a.fechaF 
                                      from n_tip_calendario_d a 
                                        where a.estado = 0 and idTnom = 2 order by a.id limit 1");# Consulta solo para nomina de vacaciones      
      $form->get("id2")->setAttribute("value" , $datos2['id'] );           

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
            if ($form->isValid()) {
               $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
               $data = $this->request->getPost();

               $d->modGeneral("delete from n_presta_primas where idEmp=".$data->id);
      $datos = $d->getGeneral("select b.id, a.fecDoc, e.nombre as nomTpres, 
                                sum(b.valor) as valor, sum(b.saldoIni) + sum(b.pagado) as pagado  
                                   from n_prestamos a 
                                         inner join n_tip_prestamo e on e.id = a.idTpres 
                                         inner join n_prestamos_tn b on b.idPres = a.id
                                         where a.estado =1 and (  (b.valor) != ( (b.saldoIni) + (b.pagado) ) ) and a.idEmp=".$data->id." 
                                         group by b.id ");

               $datos = $d->getEmpPrestamosCero("a.idEmp = ".$data->id);
               foreach($datos as $datDes)
               {                                
                   $var = '$data->valor'.$datDes['id'];// Ojo con las matusculas
                   eval("\$valor=$var;");                                 
                   if ($valor>0) // Guardar traslado
                   {                                 
                      $d->modGeneral("insert into n_presta_primas (idEmp, idIcal, idIpres, valor) 
                              values(".$data->id.",".$data->id2.",".$datDes['id'].",".$valor.")");
                   }
               }
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);
               //               
            } 
        }
      }       
      
      $dat = $d->getGeneral1("Select CedEmp, nombre, apellido from a_empleados where id=".$id);

      $valores=array
      (
           "titulo"    =>  'Prestamos asociados',
           "datos"     =>  $d->getEmpPrestamosCero("a.idEmp = ".$id),
           "empleado"  =>  $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'],
           "ttablas"   =>  'No, Fecha,Tipo de prestamo, Valor, Abonado, saldo , valor a abonar',
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "form"      =>  $form,
           "id"        =>  $id,
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

            $datos = $d->getGeneral1("select idEmp from n_emp_conc where id=".$id);             
            $d->modGeneral("delete from n_emp_conc_tn where idEmCon=".$id);                     
            $d->modGeneral("delete from n_emp_conc where id=".$id);                     
            //$u=new Auto($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                    
            //$u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idEmp']);
          }          
   }// Fin eliminar datos    

   // Datos de conceptos rapidos en automaticos 
   public function listpAction() 
   {
      $form = new Formulario("form");  
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         

      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {
            // Zona de validacion del fomrulario  --------------------
            $data = $this->request->getPost();
        }
      }    
      $datos2 = $d->getGeneral1("select a.id , a.fechaI, a.fechaF 
                                      from n_tip_calendario_d a 
                                        where a.estado = 0 and idTnom = 2 order by a.id limit 1");# Consulta solo para nomina de vacaciones                               
      //$idGrupo = $datos2['idGrup']; 

      $valores = array
      (
         "titulo"  => "Conceptos en automaticos de empleados ",
         'url'     => $this->getRequest()->getBaseUrl(),            
         "form"    => $form, 
         "datTpre" => $d->getGeneral1("select id as idTpres, nombre as nomTpres  
                                        from n_tip_prestamo a 
                                         where a.id = ".$data->tipo), 
         "datos"   => $d->getGeneral("select a.id, CedEmp, a.nombre, c.nombre as nomCar , a.apellido, b.docRef, case when b.valor is null then 0 else b.valor end as valor 
                                      from a_empleados a 
                                        inner join t_cargos c on c.id = a.idCar 
                                        left join n_presta_primas_nu b on b.idEmp = a.id and b.estado=0 and b.idTpres = ".$data->tipo." 
                                    where a.estado = 0 and a.activo = 0 "),          
         "ttablas" =>  "Cedula, Empleado, Cargo, Documento, Valor, Actualizar",          
         "idIcal"  => $datos2['id'],
         "lin"     => $this->lin         
      );                    
      $view = new ViewModel($valores);        
      return $view;
   }                   

   // Editaer concepto automatico  *******************************************************************************
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
             $dat = $d->getGeneral1("select count(id) as num
                               from n_presta_primas_nu 
                                where idIcal=".$data->idIcal." and  idEmp=".$data->id." and 
                                 idTpres=".$data->idTpres); 
             echo $data->idIcal.'-'.$data->id.'-'.$data->idTpres;
             if ($dat['num']>0)
             {
                $d->modGeneral("update n_presta_primas_nu 
                            set valor = ".$data->valor.", docRef = ".$data->docu."   
                  where idIcal=".$data->idIcal." and idEmp=".$data->id." and idTpres=".$data->idTpres);  
             }else{ 
                $d->modGeneral("insert into n_presta_primas_nu ( idIcal, idEmp, idTpres, docRef, valor, idUsu ) 
              values(".$data->idIcal.",".$data->id.", ".$data->idTpres.", ".$data->docu.", ".$data->valor.", 1)");  
             }  
          }
      }
      $view = new ViewModel();        
      $this->layout("layout/blancoC");
      return $view;            
    }
}

