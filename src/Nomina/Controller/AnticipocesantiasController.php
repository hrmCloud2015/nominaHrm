<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Anticipocesantias;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos
use Principal\Model\Gnominag; // Procesos generacion de automaticos
use Principal\Model\NominaFunc;        // Libreria de funciones nomina

class AnticipocesantiasController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/anticipocesantias/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Anticipo de cesantias empleados"; // Titulo listado
    private $tfor = "Documento de cesantias"; // Titulo formulario
    private $ttab = "Fecha,Empleado,Cargo,Centro de costos, Valor, Pdf, Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "datos"     =>  $u->getGeneral("select a.*,b.nombre, b.CedEmp, b.apellido, c.nombre as nomCar, d.nombre as nomCcos  
                                from n_cesantias_anticipos a 
                      inner join a_empleados b on a.idEmp=b.id 
                                inner join t_cargos c on c.id=b.idCar
                                inner join n_cencostos d on d.id = b.idCcos 
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
        $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  

      $datos = $d->getMotivoAntCesantias('');// Listado de motivos para pedir las cesantias
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo")->setValueOptions($arreglo);                                     

      $datos = $d->getTerceros('');// Listado de terceros 
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom = $dat['codigo'].' - '.$dat['nombre'];
          $arreglo[$idc]= $nom;
      }           
      $form->get("tipo1")->setValueOptions($arreglo);                                     
      $form->get("tipo2")->setValueOptions($arreglo); 

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
            $form->setValidationGroup('id','idEmp'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Anticipocesantias($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $datG = $d->getConfiguraG("");

                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                { 
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                                
                    $u->actRegistro($data, $datG['idAntCes']);
                    if ($data->estado==2)  
                    {
                       $datEmp = $d->getGeneral1("select idGrup , idCcos from a_empleados where id=".$data->idEmp); 

                       $d->modGeneral("insert into n_nomina (fechaI,fechaF,idTnom,idGrupo, idCal,idIcal,estado,idUsu,numEmp)
                           values('".$data->fecDoc."','".$data->fecDoc."'    ,3    ,".$datEmp['idGrup']."       ,5   ,217   , 1 , 1, 1)");

                       $datNom = $d->getGeneral1("select @@identity AS id"); 
                       $d->modGeneral("insert into n_nomina_e (idNom,idEmp)
                           values(".$datNom['id'].",".$data->idEmp.")");                       

                       $datInom = $d->getGeneral1("select @@identity AS id"); 
                       // Cesantias 
                       $d->modGeneral("insert into n_nomina_e_d (idNom,idInom,idConc,idCcos,devengado, pagoCes)
                           values(".$datNom['id'].",".$datInom['id'].",213, ".$datEmp['idCcos']." ,".$data->numero.", 1)");                       

                       // Intereses
                       $d->modGeneral("insert into n_nomina_e_d (idNom,idInom,idConc,idCcos,devengado)
                           values(".$datNom['id'].",".$datInom['id'].",195, ".$datEmp['idCcos']." ,".$data->numero1.")");                       
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
            $u=new Anticipocesantias($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("tipo")->setAttribute("value",$datos['idMot']);             
            $form->get("tipo1")->setAttribute("value",$datos['idTerC']);             
            $form->get("tipo2")->setAttribute("value",$datos['idTerI']);             
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("numero")->setAttribute("value",$datos['valor']);             
            $form->get("numero1")->setAttribute("value",$datos['interes']);  
            $form->get("fecDoc")->setAttribute("value",$datos['fechaCorte']);              
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
            $u=new Anticipocesantias($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
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

   // Datos empleado de reemplazo
   public function listeAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) 
      {
            $data = $this->request->getPost();   
            $idEmp = $data->idEmp;            
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $datGen = $d->getConfiguraG(''); //------------- CONFIGURACIONES GENERALES (1)            
            $promSubTrans = $datGen['promSubTrans'];
            // Buscar calendario activo 
            //echo $data->fechaCorte;
            $datos = $d->getGeneral1("select a.id , a.idCal , a.fechaI, a.fechaF,
                                  month( a.fechaI ) as mesI, month( '".$data->fechaCorte."' ) as mesC,
                                  '".$data->fechaCorte."' as fechaC, # fecha de corte para anticipo de cesantias  
                                  datediff( '".$data->fechaCorte."', concat( year(now()),'-01-01')   ) +1 as dias, 
                                    '".$data->fechaCorte."' as fechaCorte,
  ( 
(select ( year('".$data->fechaCorte."') - year(ec.fechaI) )  * 360 
     from n_emp_contratos ec where ec.estado=0 and ec.tipo = 1 and ec.idEmp = ".$idEmp." ) + 

(select ( month('".$data->fechaCorte."') - month(ec.fechaI) )  * 30 
     from n_emp_contratos ec where ec.estado=0 and ec.tipo = 1 and ec.idEmp = ".$idEmp." )  + 

(select ( day('".$data->fechaCorte."') - day(ec.fechaI) )   
     from n_emp_contratos ec where ec.estado=0 and ec.tipo = 1 and ec.idEmp = ".$idEmp." ) ) as diasCesantiasRegimenAnt,

             # Si el contrato es menor al año antes del retiro
          case when ( select ec.fechaI from n_emp_contratos ec where ec.estado=0 and ec.tipo = 1 and ec.idEmp = ".$idEmp." ) <  ( concat( year('".$data->fechaCorte."') -1, '-', lpad( month('".$data->fechaCorte."'),2,'0'), '-', lpad( day('".$data->fechaCorte."'),2,'0') ) ) then # si la efcha inicio fin de contrato es menor al ano ant de retiro
             
             ( concat( year('".$data->fechaCorte."') -1, '-', lpad( ( month('".$data->fechaCorte."')+1)  ,2,'0'), '-', lpad( case when day('".$data->fechaCorte."') > 15 then 1 else 30 end ,2,'0')  ) )

          else # Si es mayor toma la fecha de inicio del contrato 

         ( select ( concat( year(ec.fechaI) , '-', lpad( month(ec.fechaI),2,'0'), '-', lpad( case when day(ec.fechaI) > 15 then 1 else 15 end ,2,'0') ) ) 
              from n_emp_contratos ec where ec.estado=0 and ec.tipo = 1 and ec.idEmp = ".$idEmp."   )   

       end as fechaInicioConsulta          

                                     from n_tip_calendario_d a 
                                  where a.idCal = 5 and a.estado=0 # Ojo este sw es cero por el calendario 
                                    order by a.fechaI limit 1") ;
            //print_r($datos);
            $diasCesantias = $datos['dias'];
            $diasCesantiasInt = $datos['dias'];
            if ( $datos['diasCesantiasRegimenAnt'] > 0 )
                 $diasCesantias = $datos['diasCesantiasRegimenAnt'];
            $idIcal = $datos['id'];
            $fechaI = $datos['fechaI'];
            $mesI   = $datos['mesI'];   
            $fechaF = $datos['fechaC'];                                   
            $mesF   = $datos['mesC'];                                   
            $fechaCorte = $datos['fechaCorte'];
            $fechaAnAnt = $datos['fechaInicioConsulta'];
            $diasAus = 0;
            //echo 'Consulta '.$fechaAnAnt;
            // Buscar dias reales de cesantias
            $g = new Gnominag($this->dbAdapter);
            $n = new NominaFunc($this->dbAdapter);
            $datDcal = $n->getDiasCalen( $mesI, $mesF, $fechaF ); // Funcion apra deolver dias para descontar entr rango de fecha pra dias habiles                
            if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
            {
                $diasCesantiasInt = $diasCesantiasInt - $datDcal['diasR'];   
                $diasCesantiasInt = $diasCesantiasInt + $datDcal['diasS'];                   
            }        
            //echo 'dias int '.$diasCesantiasInt;
            // Buscar ausentismos
            $datAus = $d->getAusentismosDias($idEmp, $fechaI, $fechaF );
            if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
            $diasCesantias = $diasCesantias - $datAus['dias'];  

                $datCon = $n->getDiasContrato( $idEmp, $fechaI, $fechaF );
                $diasPromedio = $datCon['diasLabor'];

            $datos2 = $g->getCesantiasS($idEmp, $fechaI, $fechaF, $diasCesantias, $fechaAnAnt,$promSubTrans, $diasPromedio);                  


            foreach ($datos2 as $dato)
            {  
               $valor = round( $dato["valorCesantias"], 2); // Buscar subdisio de transporte
               //echo $valor;
               //$valor = round(  ($base / 360) * $diasCesantias , 2 );
               //$valor = round( ($base * $diasCesantias ) / 360  , 2 );
            }
            $valores = array(
               "datos"  => $d->getGeneral1("select a.sueldo, b.nombre as nomCar  
                                              from a_empleados a
                                              inner join t_cargos b on b.id = a.idCar
                                                where a.id=".$data->idEmp),
               "datosCa"=> $d->getGeneral1("select sum( a.valor ) as cesantiasAnt, 
                                             sum( a.interes  ) as interesAnt
                                               from n_cesantias_anticipos a
                                                  where year(a.fecha)=year(now()) and a.id != ".$data->id." and idEmp = ".$data->idEmp),               
               "base"   => $valor,
               "dias"   => $diasCesantias,
               "diasInt" => $diasCesantiasInt,
               "diasAus"   => $datAus['dias'],
               "fechaCorte" => $fechaCorte,               
               "valor"  => $valor,    
               "idIcal" => $idIcal,       
               "form"   => $form, 
               "lin"       =>  $this->lin,
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }               

   // PROMEDIOS 
   public function listpAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $data = $this->request->getPost();

         }
      }   
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              "datos"   => $d->getGeneral("Select 
   b.nombre, d.fechaI, d.fechaF, a.devengado 
      from n_nomina_e_d a 
                inner join n_conceptos b on a.idConc=b.id 
                        inner join n_nomina d on d.id=a.idNom 
                        inner join n_nomina_e e on e.id = a.idInom and a.idInom = e.id 
                        inner join a_empleados f on f.id=e.idEmp 
                        inner join n_conceptos_pr c on c.idConc=b.id 
      where c.idProc = 5 and d.fechaI >= '2017-01-01' 
           and d.fechaF <= concat( year('".$data->fecsal."'),'-', lpad(month('".$data->fecsal."'),2,'0') ,'-' , (case when day('".$data->fecsal."')>15 then 30 else 30 end) ) 
        # Se debe tener en cuenta el calendario para la consulta 
           
       and e.idEmp = ".$data->idEmp),             
              "ttablas"   =>  "Concepto, Periodo, Valor",
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;              

   }// FIN PROMEDIOS       
}
