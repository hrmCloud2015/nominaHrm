<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;
use Zend\Db\Adapter\Driver\ConnectionInterface;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Principal\Model\Retefuente; // Retefuente

use Nomina\Model\Entity\Gnomina; // (C)
use Nomina\Model\Entity\Gnominac; // Procesos especiales apra generacion de nomina
use Nomina\Model\Entity\Cesantias; // Cesantias
use Nomina\Model\Entity\Primas; // Primas
use Nomina\Model\Entity\PrimasA; // Prima de antiguedad
use Nomina\Model\Entity\EmbargosN; // Embargos

use Principal\Model\Gnominag; // Procesos generacion de automaticos
use Principal\Model\ExcelFunc; // Funciones de excel 

use Principal\Model\Paranomina; // Parametros especiales de nomina
use Principal\Model\Alertas; // Alertas automaticas

use Principal\Model\LogFunc; // Funciones de logeo y usuarios 

class GnominaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/gnomina/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Nominas activas"; // Titulo listado
    private $tfor = "Generación de la nomina"; // Titulo formulario
    private $ttab = ",id, Nomina, Periodo, Empleados ,Estado, Pre-nomina, Pre-nomina resumida, Retefuente,Eliminar"; // Titulo de las columnas de la tabla
    
    // LISTAR NOMINAS  ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $a = new Alertas($this->dbAdapter);
      $form = new Formulario("form");

      $datPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      $con = '';

      if ( $datPer['idGrupNom']>0)
           $con = ' and a.idGrupo='.$datPer['idGrupNom'];
//echo $con;
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "form"      =>  $form,
        "datPer"    =>  $datPer, // Permisos de esta opcion
        "datos"     =>  $g->getListNominas("a.estado in (0,1) ".$con), // Listado de nominas 
        "datAud"    => $g->getAuditoriaNomina("a.estado in (0,1) ".$con),
        "datAudOtcon"    => $g->getAuditoriaNominaOtconc("a.estado in (0,1) ".$con),
        "datTemp"   => $d->getGeneral("select a.id, lower(d.nombre) as nombre , count( b.idEmp ) as num,
                           ( select count(e.id) from a_empleados e where e.pensionado = 1 and e.id = c.id   ) as pension  
                             from n_nomina a 
                                 inner join n_nomina_e b on b.idNom = a.id
                                 inner join a_empleados c on c.id = b.idEmp  
                                 inner join n_tipemp d on d.id = c.idTemp 
                             where a.estado=1 group by a.id, d.nombre"),
        "datEmpS" => $d->getGeneral("select b.CedEmp, lower(b.nombre) as nombre, lower(b.apellido) as apellido, 
                                         a.fechaF, b.idGrup 
                                            from n_nomina_l a 
                                              inner join a_empleados b on b.id = a.idEmp
                                      where a.idNom > 0 order by a.fechaF desc "),
        "datEmpE" => $d->getGeneral("select b.CedEmp, lower(b.nombre) as nombre, lower(b.apellido) as apellido, 
                                         a.fechaF, b.idGrup 
                                            from n_emp_contratos a 
                                              inner join a_empleados b on b.id = a.idEmp
                                      where a.fechaI > '2016-04-15' order by a.fechaF desc"),        
        "datTfon"   => $d->getGeneral("select a.id, case c.idConc
                                           when 11 then 'Pensión'
                                           when 15 then 'Salud'
                                           when 21 then 'Soli' end as tipo , count(c.id) as num
                                        from n_nomina a 
                                           inner join n_nomina_e b on b.idNom = a.id
                                           inner join n_nomina_e_d c on c.idInom = b.id
                                        where a.estado = 1 and c.idConc in (11,15,21) 
                                        group by a.id, c.idConc ;"),        
        "datRet"    => $d->getGeneral1("select count(id) as numRet 
                                           from n_nomina_retro_i where estado=0"),
        "datEmp" => $d->getGeneral("select a.id as idNom, c.id, c.idEmp, d.CedEmp, 
                                   d.nombre , d.apellido, c.fechaIngreso as fechaI , c.fechaF       
                               from n_nomina a 
                                  inner join n_nomina_e b on b.idNom = a.id 
                                  inner join n_nomina_l c on c.idEmp = b.idEmp and c.idNom = a.id   
                                  inner join a_empleados d on d.id = b.idEmp 
                                where a.idGrupo=99 and c.estado=0"),
        "datSubg"   => $d->getGeneral("select 
           distinct a.idSgrup, c.nombre as nomSgrup, b.idNom, 
           ( select count(bb.id) 
                    from n_subgrupos_e bb  
                         inner join a_empleados cc on cc.id = bb.idEmp 
                           where cc.estado = 0 and cc.activo = 0 and cc.finContrato=0 and bb.idSub = c.id and cc.idGrup=4) as num , c.id as idSub    
                                    from a_empleados a
                                         inner join n_nomina_e b on b.idEmp = a.id
                                         inner join n_subgrupos c on c.id = a.idSgrup 
                                         inner join n_nomina d on d.id = b.idNom 
                                       where d.idGrupo=4 and d.estado = 1"),
        "datAlert"  => $a->getVencimientoContratos(),    
        "datAlertN" => $a->getVencimientoContratosN(),    
        "ttablas"   => $this->ttab,
        'url'       => $this->getRequest()->getBaseUrl(),
        "lin"       => $this->lin,        
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
        //"datEmpNoex"=> $d->getGeneral1("select a.CedEmp, a.nombre , a.apellido 
          //                                 from a_empleados a 
            //                             where a.estado=0 and a.activo=0 and a.idGrup = ".$datPer['idGrupNom']." and 
              //                   ( not exists (SELECT null from n_nomina aa 
                //                    inner join n_nomina_e bb on bb.idNom = aa.id 
                  //                  where a.id = bb.idEmp and aa.idGrupo = a.idGrup and bb.idNom = ".$id." )   )"),

    } // Fin listar registros 
    
 
   // NUEVA NOMINA *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      $perGrupo = 0;
      if ( $daPer['idGrupNom']>0)
           $perGrupo = $daPer['idGrupNom'];

      // ------------------------------------------------------------ Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipo'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                 
      // --------------------------------------------------------------- Empleados
      //$arreglo='';
      //$datos = $d->getEmp(' and finContrato=0'); 
      //foreach ($datos as $dat){
      //   $idc=$dat['id'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
      //   $arreglo[$idc]= $nom;
      //}              
     // $form->get("idEmp")->setValueOptions($arreglo);                                                 
      // MOtivos de liquidacion   
      $arreglo='';
      $datos = $d->getTipLiqui(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom = $dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idTliq")->setValueOptions($arreglo);                                                 
      $form->get("tipo2")->setValueOptions(array("0"=>"Grupo","1"=>"Individual" ));

      $arreglo='';
      $datos = $d->getConnom(); 
      foreach ($datos as $dat)
      {
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipVal'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConcM")->setValueOptions($arreglo);
      //       
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
            $form->setValidationGroup('tipo'); // ---------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) 
            {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u = new Gnomina($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $a = new NominaFunc($this->dbAdapter);
                $d = new AlbumTable($this->dbAdapter);
                $c = new Gnominac($this->dbAdapter);
                $g = new Gnominag($this->dbAdapter);

                $datGen = $d->getConfiguraG(''); //---------------- CONFIGURACIONES GENERALES (1)
                
                // ----------------------------------------------------------------------
                // -------------- Ubicar datos del tipo de calendario (1) ---------------
                // ----------------------------------------------------------------------
                $datos = $d->getCalendario($data->tipo);                    
                //--
                $dias    = $datos['valor'];
                $idCal   = $datos['idTcal'];
                $tipNom  = $datos['tipo'];
                if ($tipNom == 0)//------- NOMINAS GENERALES
                {
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);                
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $g->getCalendarioTipoNomina($data->tipo, $data->idGrupo);           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';     
                   // Validar si es nomina de correccion para empleados 
                   if (isset($data->tipoC2))
                   {
                       if ($data->tipoC2>0)
                       {                                          
                          $idEmp = $data->tipoC2;
                       }   
                   }                              
                   if (isset($data->tipoS))
                   {  
                       if ($data->tipoS>0)
                       {                      
                          $idIcal = $data->tipoS;
                          $datPant = $d->getGeneral1("select * from n_tip_calendario_d where id =".$idIcal);
                          $fechaI = $datPant['fechaI'];
                          $fechaF = $datPant['fechaF']; 
                       }   
                   }                              
                }
                if ( ($tipNom==1) or ($tipNom==6) )//------- CESANTIAS E INTERESES / CONSOLIDADO DE CESANTIAS
                {
                   // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $g->getCalendarioTipoNomina($data->tipo, $data->idGrupo);           
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                   
                }                
                if ($tipNom==2)//------- NOMINA DE VACACIONES 
                {
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $d->getGeneral1("select a.id, now() as fechaI , now() as fechaF  
                                      from n_tip_calendario_d a 
                                      inner join n_tip_nom b on b.idTcal = a.idCal 
                                      where b.id = ".$data->tipo);# Consulta solo para nomina de vacaciones           
                   $idIcal = $datos2['id'];
                   $fechaI = $data->fecDoc;
                   $fechaF = $data->fecDoc;
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                                
                if ($tipNom==3)//-------- PRIMAS
                {
                    // Generacin de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                 // echo $data->tipo.' '.$data->idGrupo.' '.$idCal ;
                   $g->getGenerarP($data->tipo, $data->idGrupo, $idCal);
                   $datos2 = $d->getGeneral1("select a.id, a.fechaI, a.fechaF 
                                      from n_tip_calendario_d a
                                      inner join n_tip_nom b on b.idTcal = a.idCal and b.id = a.idTnom 
                                      where b.id = ".$data->tipo." and a.idGrupo=".$data->idGrupo."  
                                      and a.estado=0 order by a.fechaI limit 1");                           
                   // ------------------------------------------------------ Verificar en movimiento del calendario
                   $datos2 = $d->getGeneral1("select a.id, a.fechaI, a.fechaF 
                                      from n_tip_calendario_d a
                                      inner join n_tip_nom b on b.idTcal = a.idCal and b.id = a.idTnom 
                                      where b.id = ".$data->tipo." and a.idGrupo=".$data->idGrupo."  
                                      and a.estado=0 order by a.fechaI limit 1");          
                   $idIcal = $datos2['id'];
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fechaF'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                                
                if ($tipNom == 4)//--------- LIQUIDACION FINAL 
                {
                    $datos2 = $d->getGeneral1("select case when day(fechaF) = 31 then 
                                   concat(year(fechaF), '-', lpad(month(fechaF),2,'0'), '-30' ) else fechaF end  as fecha 
                                   from n_nomina_l where idNom = 0 order by fechaF desc limit 1");# Consulta solo para nomina de vacaciones                               

                    $idGrupo = 99; 
                    $fechaF = $datos2['fecha']; // fecha de corte de contrato                                   
                    $fechaI = $datos2['fecha']; // fecha de corte de contrato                                   
                    $idIcal = 0;
                }
                if ($tipNom == 5)//------ NOMINA MANUAL (OJO PENDIENTE POR REVISION)
                {
                    // Crear calendario de forma automatica cuando no exista
                    $d->modGeneral("insert into n_tip_calendario_d ( idTnom, idGrupo, idCal  ) 
                         select 11, ".$data->idGrupo.", 9 from c_general 
                  where not exists ( select null 
                                     from n_tip_calendario_d 
                                       where idTnom = 11 and idGrupo = ".$data->idGrupo." and idCal = 9)");
                    $datos2 = $d->getGeneral1("select *, '".$data->fecDoc."' as fecha,
                                       concat( year('".$data->fecDoc."') ,'-', lpad( month('".$data->fecDoc."'),2,'0' ), '-01' ) as fechaI 
                                   from n_tip_calendario_d 
                                       where idGrupo=".$data->idGrupo." and idCal = 9 limit 1 ;");#                   
                   $idIcal = $datos2['id'];
                   $idCal = 9;
                   $fechaI = $datos2['fechaI'];
                   $fechaF = $datos2['fecha'];
                   $idGrupo = $data->idGrupo;
                   $idEmp = '';
                }                
                // ----------------------------------------------------------------------
                // -------------- FIN Ubicar datos del tipo de calendario (1) -----------
                // ----------------------------------------------------------------------                
                //--------------------- INICIO DE TRANSACCIONES ()
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   // Generacion de periodos para grupos y tipos de nominas nuevos en el año, genera el año en curso
                   // Grupo 99 este es grupo de liquidacion final de empleados 
                   if ($idGrupo!=99)
                   {
                      $g->getGenerarP($data->tipo, $idGrupo, 7 ); // Calendario primas               
                      $g->getGenerarP($data->tipo, $idGrupo, 5 ); // Calendario cesantias
                   }   
               
                   // Ubicacion del dia 1 dle calendario para vacaciones 
                   if ( $tipNom==2 )// Se busca la fecha inferior en vacaciones
                   { 
                      $datos2 = $d->getGeneral1("select case when day( '".$fechaI."' ) > 15 then 
                                        concat( year('".$fechaI."') ,'-', lpad(month('".$fechaI."'),2,'0' ) , '-16'  )
                                    else
                                        concat( year('".$fechaI."') ,'-', lpad(month('".$fechaI."'),2,'0' ) , '-01'  ) end as fechaI ,
                       concat( year('".$fechaI."'),'-', lpad(month('".$fechaI."'),2,'0' ) , '-30'  ) as fechaF                                       ") ;
                      $fechaI = $datos2['fechaI'];
                      $fechaF = $datos2['fechaF'];
                       $d->modGeneral("update n_nomina 
                                        set fechaI = '".$fechaI."' ,
                                         fechaF = '".$fechaF."'   
                                       where id=".$id );                      
                   }
                   //----------------------------------------------------------------------
                   //----------------  INSERCCION TABLA N_NOMINA CABECERA -----------------
                   //----------------------------------------------------------------------
                   $id = $u->actRegistro($data,$fechaI,$fechaF,$idCal,$idIcal,$dias,$idGrupo);
                   /// ----------------------***------------------***--------------***-----
                   /// ----------------------***------------------***--------------***-----
                   if ( ( isset($data->fecDoc) ) and ( ( $tipNom==11 ) or ($tipNom==4) or ($tipNom==5) ) ) 
                   {
                       $d->modGeneral("update n_nomina 
                                        set fechaI = 
                        concat( year('".$data->fecDoc."'), '-', lpad( month('".$data->fecDoc."'),2,'0' ), '-01' ) ,
                                         fechaF ='".$data->fecDoc."'  
                                       where id=".$id );

                   } 

                   if ( ( isset($data->fecDoc) ) and ( $tipNom==6) ) 
                   {
                       $d->modGeneral("update n_nomina 
                                        set fechaI = '".$data->fecDoc."', 
                                            fechaF ='".$data->fecDoc."'  
                                       where id=".$id );

                   } 
                   //-----------------------------------------------------------------------------------
                   //--- CONSULTA ULTIMO PERIODO DE NOMINA GENERADO DEL GRUPO DE LOS TIPOS DE NOMINA 
                   //-----------------------------------------------------------------------------------
                   //echo $data->tipo.' - '.$idGrupo.' <br />';
                   $datos2 = $g->getUltimaNomina($data->tipo, $idGrupo);     //print_r($datos2);               
                   $idTnomL = $datos2['idTnom']; // Nomina asociada a la nomina de liquidacion 
                   $idTcalL = $datos2['idTcal']; // Nomina asociada a la nomina de liquidacion                                       
                   $idTnomP = $datos2['idTnomP']; // Prima pendiente
                   $idTnomC = $datos2['idTnomC']; // Cesantias pendiente                   
                   $fechaI = $datos2['fechaI']; // fecha de inicio de nomina para novedades sin liquidar                   
                   // Validacion calendario de cesantias e intereses dias fechaIceasntias
                   if ( ( $tipNom == 1 ) or ( $tipNom == 6 ) )
                   {
                       $d->modGeneral( "update n_nomina 
                               set fechaIc = fechaI 
                                       where id=".$id );                   
                   } 

                   // *** $data->tipo = TIPO 6
                   // ----------------------------------------------------------****
                   // LIQUIDACION FINAL CALENDARIOS DE PRIMAS Y CESANTIAS ACTIVOS---
                   //-----------------------------------------------------**********
                   if ($tipNom==4)
                   {       
                       $i = 0; 
                       //---- CREACION DE EMPLEADO (PENDIENTE GRUPO DE EMPLEADOS A LIQUIDAR) DE LIQUIDACION 
                    //   if ($data->idEmp!='') // Recorrido de empleados a liquidark
                    //   {
                       $datos2 = $d->getGeneral("select a.idEmp, b.idGrup, a.fechaI,
                                                  a.fechaF, a.dias   
                                                     from n_nomina_l a 
                                                       inner join a_empleados b on b.id = a.idEmp where a.idNom = 0");
                       foreach ($datos2 as $datEmpL) 
                       {
                           $idGrupo = $datEmpL['idGrup']; 
                           $idEmp   = $datEmpL['idEmp']; 
                           $fechaC  = $datEmpL['fechaF']; 
                           $fechaI  = $datEmpL['fechaI']; 
                           $dias    = $datEmpL['dias']; 

                           //-----------------------------------------------------------------------------------
                           //--- CONSULTA ULTIMO PERIODO DE NOMINA GENERADO DEL GRUPO DE LOS TIPOS DE NOMINA 
                           //-----------------------------------------------------------------------------------
                           // Se necesita otra vez para obtener datos de la nomina
                           // tipo quincena o nomina mensual solo para liquidacion final
                           $idTNomL=6; // id del tipon de nomina 
                           $datos2 = $g->getUltimaNomina($idTNomL, $idGrupo);     
                           
                           $idTnomL = $datos2['idTnom']; // Nomina asociada a la nomina de liquidacion 
                           $idTcalL = $datos2['idTcal']; // Nomina asociada a la nomina de liquidacion                                       
                           $idTnomP = $datos2['idTnomP']; // Prima pendiente
                           $idTnomC = $datos2['idTnomC']; // Cesantias pendiente                                              //$fechaI = $datos2['fechaI']; // fecha de inicio de nomina para novedades sin liquidar
                           //echo 'Fecha inicial'.$fechaI;
                           $i++; 
                           if ($idEmp>0)
                           {
                               // Consultar ultimo periodo nomina pagada para comparar 
                               $g->getNominaE($id, 0, $idEmp, $fechaI, $tipNom  );  // ***--------------- GENERACION DE EMPLEADOS
                               $d->modGeneral("update n_nomina_e set dias=".$dias." where idNom=".$id." and idEmp=".$idEmp);
 
                           }// Validacion nombre mayor a 0 
                           // -- Fecha de inicio de primas 
                           //echo 'idTNom '.$idTnomC.' - Grupo '.$idGrupo;                           
                         if ($tipNom != 4)  
                         { 
                           $datos2 = $g->getCalendarioTipoNomina($idTnomP, $idGrupo);           
                           $fechaIprima = $datos2['fechaI'];
                           // -- Fecha de inicio de cesantias
                           $datos2 = $g->getCalendarioTipoNomina($idTnomC, $idGrupo);
                           $fechaIcesantiasCalendario = $datos2['fechaI'];

                           //print_r($datos2);
                           // -- Buscar si ya se le pagaron cesantias 
                           $datPces = $d->getGeneral1("select 
                              ( select case when year(aa.fechaF) is null then 0 else year(aa.fechaF) end   
                                  from n_nomina aa 
                                     inner join n_nomina_e bb on bb.idNom = aa.id 
                                where aa.idTnom = 7 and bb.idEmp = b.idEmp 
                                  order by aa.fechaF desc limit 1) as anoUltPag, # Consulta del ultimo pago de nomina realizado  
                             ( select case when aa.estado is null then 0 else aa.estado end 
                                from n_nomina aa 
                                   inner join n_nomina_e bb on bb.idNom = aa.id 
                              where aa.idTnom = 7 and bb.idEmp = b.idEmp 
                                order by aa.fechaF desc limit 1) as estadoUltPag # Estado ultimo pago                          

                                      from n_nomina a
                                        inner join n_nomina_e b on b.idNom = a.id 
                                      where a.id = ".$id);
                             // Si la diferencia es de mas de 1 año, quiere decir que no 
                             // le han pagado el año anterior de nomina 
                             // y debe tomarla apra liquidacion 
                           $fechaIcesantiasAnt='0000-00-00';
                           if ( ($datPces['anoUltPag']-$datos2['ano']>1) )
                           {
                              // Se asinga la fecha de calendario pendiente por pagar
                              // para ser la fecha anterior
                              $fechaIcesantiasAnt = $fechaIcesantiasCalendario; // Asigna la fecha de cesantias del año inicial
                              $fechaIcesantias = $fechaI; 
                           }else 
                            {
                              // Si ya recibio pago de cesntias del año anterior 
                              // debe poner la segunda fecha apra armar segundo concepto 
                              $fechaIcesantias = $fechaIcesantiasCalendario;                             
                            }  
                            $cesAntPag=0;
                            if ( $datPces['estadoUltPag'] > 0 )// Si se pagaron el anterior guaradr dato
                            {
                               $cesAntPag = $datPces['estadoUltPag'];
                            }
                            $idEmp = '';                   
 
                            $d->modGeneral( "update n_nomina 
                               set idCal = ".$idTcalL.",
                                   fechaIp='".$fechaIprima."',
                                   fechaIc='".$fechaIcesantias."',
                                   fechaIcAnt='".$fechaIcesantiasAnt."',
                                   cesAntPag=".$cesAntPag." 
                                       where id=".$id );
                            //$d->modGeneral( "update n_nomina 
                             //  set idTnom=1 where id=".$id );       
                          }                        
                        }
                        //------------------------------------------------------- 
                        // Fin recorrido de empleados a liquidar----------------------
                        //------------------------------------------------------- 

                    }//// FIN VALIDACION CALENDARIOS LIQUIDACION FINAL () 
                    // --------- Inactiva grupo de nomina 
                    //if ($tipNom != 4)// DIFERENTE A LIQUIDACION FINAL 
                        //$d->modGeneral( 'update n_grupos set activa=1 where id='.$idGrupo );
                    // --------- Buscar id de grupo
                    $datos = $d->getGeneral1("Select idGrupo, idTnom from n_nomina where id=".$id); 
                    $idg = $datos['idGrupo'];
                    $idTnom = $datos['idTnom']; // 

                    //--------------------------------------*************************-------///
                    //---- GENERAR EMPLEADOS N_NOMINA_E-******-----------------------          
                    //--------------------------------------*************************-------///                       

                    if ( ($tipNom==0) )// Nomina normal 
                    {
                       // $idEmp se puso en 0 revisar 
                       $g->getNominaE($id, $idg, 0, $fechaF, $tipNom);  // ***--------------- GENERACION DE EMPLEADOS                       
                       if ($idEmp>0)                      
                       {
                          $d->modGeneral("delete from n_nomina_e where idNom=".$id." and idEmp!=".$idEmp);
                          $d->modGeneral("update n_nomina set correccion=1 where id=".$id);                          
                       }                        
                    }
                    if ( ($tipNom==3 ) or ($tipNom==1) or ($tipNom==6)  )// Cesantias y primas y consolidado de ceasntias
                    {
                       $g->getNominaEprimas($id, $idg, $idEmp, $fechaF, $tipNom, $idTnom );  // ***--------------- GENERACION DE EMPLEADOS      
                       if ( ( $tipNom==1 ) or ($tipNom==6) )// Busqueda de fechas de ingresos de empleados y calculo de
                       {
                          $d->modGeneral("update n_nomina_e a 
                                             inner join n_emp_contratos b on b.idEmp = a.idEmp and b.tipo = 1 
                                             inner join a_empleados c on c.id = b.idEmp 
                                             inner join n_nomina d on d.id = a.idNom 
                                          set a.fechaIc = ( case when c.regimen = 1 then b.fechaI
                                             else 
                                               case when b.fechaI > d.fechaI then b.fechaI else d.fechaI end  end ) 
                                             where a.idNom = ".$id);    
                        
                       } 
                    }                    
                    // NOMINAS DOCUMENTOS ESPECIALES -------------------------
                    if ( ($tipNom==5)  ) 
                    {
                       $g->getNominaEmanual($id, $idg, $idEmp, $fechaF, $tipNom);  // ***--------------- GENERACION DE EMPLEADOS
                       // Si es nomina de anticipos se toman la base de lso dias laborados
                       if ( $tipNom == 5 )
                       { 
                          $d->modGeneral("update n_nomina_e a 
                               inner join n_nomina b on b.id = a.idNom 
                                set a.dias = datediff( b.fechaF, b.fechaI ) + 1  
                          where a.idNom =".$id); 
                       
                       }                        
                    }
                    if ($tipNom==2) // GENERACION EMPLEADOS NOMINA DE VACACIONES 
                    {
                       $datEvac = $d->getGeneral("select idEmp 
                                      from n_vacaciones 
                                         where idTnom=5 and estado=1"); // Solo traer nomina de vacaciones 
                     //  print_r($datEvac);
                          foreach ($datEvac as $datVev)
                          {
                              $idEmp = $datVev['idEmp'];  
                              if ($idEmp>0)
                              {
                                  // Consultar ultimo periodo nomina pagada para comparar 
                                  // ultimo pago del empleado 
                                  $dat = $d->getGeneral1("select DATE_ADD( b.fechaF , interval 1 day) as fechaI      
                                             from n_nomina_e a
                                                inner join n_nomina b on b.id = a.idNom  
                                                 where a.idEmp = ".$idEmp." and b.estado=2
                                                   order by b.fechaF desc limit 1");                         
                                  $g->getNominaE($id, 0, $idEmp, $dat['fechaI'], $tipNom  );  // ***--------------- GENERACION DE EMPLEADOS
                              }
                          }
                    }
                    // ***--------------- FIN GENERACION DE EMPLEADOS----------------------------

                    if ( ($tipNom==22)  ) // SOLO PARA NOMINA DE VACACINOES CODIGO PROXIMO A ELIMINAR  -------------------------
                    {
                        // 1. INCAPACIDADES DE EMPLEADOS
                        $g->getIncapaEmp($id, "n_incapacidades"); // Incapacidades
                        $g->getIncapaEmp($id, "n_incapacidades_pro"); // Prorrogra en incapacidades
                        // 2. VALIDAR INCAPACIDADES -----------------------------------
                        $datInc=$g->getIncapacidades($id); // Extraer datos de la incapacidad del empleado si tuviera
                        foreach($datInc as $dat)
                        {
                           $iddn = $dat['id'];                                  
                           $dias    = $dat['dias'];
                           $diasEnt = $dat['diasEnt'];           
                           $diasAp  = $dat['diasAp'];
                           $diasDp  = $dat['diasDp'];            
                           //echo ' g '.$datGen['incAtrasada'].'<br />';
                           // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
                           if ( $datGen['incAtrasada'] == 0)
                              $diasAp = 0;
                           if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                               $diasAp = 0;          
  
                           $diasI = $diasAp + $diasDp ;
                           $dias = $dias - $diasI;                                                    
                           // Empanada para cubrir retornos de vacaciones e incapaciades de mas o son 15 dias exactos de incapacidad
                           if ( ( $diasAp + $diasDp ) > $dat['diasCal'] )
                           {
                               $diasI = $dat['diasCal']; 
                               $dias = 0; 
                           }              
                           //if ($dat['total']==1) // Cubre el periodo todo el dia 
                           //    $dias = 0;                        

                           $d->modGeneral("update n_nomina_e set dias=".$dias.", 
                                     diasI=".$diasI." where id=".$iddn);
                           # Se marca idInc con una 1 para saber que ese empleado tiene incapacidad registrada 
                                                      
                         } // Fin validacion incapacidad                    
                    }
                if ( ($tipNom==0) or ($tipNom==5) or ($tipNom==2) ) // SOLO PARA NOMINAS GENERALES y MANUELAES-------------------------
                {
                    // VALIDAR AUMENTO DE SUELDO EN EL PERIODO 
                    $g->getAumentoEmpleado($id);
                    // VALIDAR FECHA DE INGRESO DEL EMPLEADO  (EN DES USO 
                    // VALIDAR FECHA DE CONTRATO EMPLEADOS
                    $datIng = $g->getContratosActivos($id);        
                    foreach($datIng as $dat)
                    {
                        $iddn = $dat['id'];
                        //echo $dat['diasFinC'].'<br />';
                        if ( $dat['diasFinC'] > 0 ) // Tiene un reinicio de contrato
                           $dias = $dat['diasH'] + $dat['diasFinC'];                                                      
                        else    
                           $dias = $dat['diasH'] + $dat['diasA'];                                                      
                        if ($dat['diasFin']==28 )
                            $dias = $dias + 2;
                        if ($dat['diasFin']==29 )
                            $dias = $dias + 1;                         

                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1, contra=".$dat['contra'].", idCon=".$dat['idCon']." where id=".$iddn); 
                    } // Fin validacion fecha de egreo del empleado
                    
                    // 1. INCAPACIDADES DE EMPLEADOS
                    $g->getIncapaEmp($id, "n_incapacidades"); // Incapacidades
                    $g->getIncapaEmp($id, "n_incapacidades_pro"); // Prorrogra en incapacidades

                    // VALIDAR DIAS LABORADOS EN PROYECTO -- PROYECTOS
                    // EN REVISION -------------------------------------------
                    //$g->getProyectosEmpleado($id);                    

                    // DIAS DE VACACIONES --------------------------------------------
                    $datNome = $d->getNomEmp(" where idVac>0 and idNom=".$id);
                    $diasVaca = 0;
                    foreach($datNome as $dat)
                    {
                        $iddn = $dat['id'];
                        $idEmp = $dat['idEmp']; 

                        $datVac=$g->getVacaciones($iddn); // Extraer datos de la vacacion del empleado si tuviera
                        $dias = $datVac['dias'];
                        $diasVac = 0;
                        // Cuando es nomina de vacaciones debe poner los dias de vacas aun en retornos para descuentos
                        if ($datVac['vacPaga']==0)
                        {  
                            $diasVac = $datVac['diasVac'];
                        }   
                        // Cuando retorna la vacaciones se debe poner dias de retorno dentro del mismo periodo pero con vaca paga
                        if ($datVac['vacPaga']==1)
                        {  
                           if ($datVac['retorna']==1)  
                               $dias = $datVac['diasRetorno'];
                           else 
                               $dias = $dias + $datVac['diasRetorno'];  
                        }                           
 //echo $diasVac.'<br />';
                        if ($dias>=0)
                        {                    
                           if ($datVac['retorna']==1) // Retorno de vacaciones  
                           {           
//                            echo $dias.'<br />';
                              $d->modGeneral("update n_nomina_e set dias = ".$dias." , diasVac = ".$diasVac." , actVac=2  where id=".$iddn);
                              $d->modGeneral("update a_empleados set vacAct = 0  where id=".$idEmp); // Regreso de vacaciones
                              //$diasVaca = $dias;
                           }
                           else {
                            if ($datVac['vacPaga']==0)
                            {  
                              //  echo 'Dias mes '.$diasMes;
                              if ($datVac['diasVac']>0)
                                $d->modGeneral("update n_nomina_e set dias = ".$dias.", diasVac = ".$datVac['diasVac'].", 
                                actVac=1   
                                      where id=".$iddn); 
                              //$d->modGeneral("update a_empleados set vacAct = 0   where id=".$idEmp); // Regreso de vacaciones
                              //$diasVaca = $dias;                              
                             }else{
                                $d->modGeneral("update n_nomina_e set dias =0  
                                        ,actVac=1   
                                      where id=".$iddn);                              
                             } 
                           }                

                        }
                      } // FIN DE VACACIONES                    
                    // 2. VALIDAR INCAPACIDADES -----------------------------------
                    $datInc=$g->getIncapacidades($id); // Extraer datos de la incapacidad del empleado si tuviera
                    foreach($datInc as $dat)
                    {
                        $iddn = $dat['id'];          
                       
                        $dias    = $dat['dias'];
                        $diasEnt = $dat['diasEnt'];           
                        $diasAp  = $dat['diasAp'];
                        $diasDp  = $dat['diasDp'];            
                        //echo ' g '.$datGen['incAtrasada'].'<br />';
                        // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
                        if ( $datGen['incAtrasada'] == 0)
                           $diasAp = 0;
                        if ( $dat['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                           $diasAp = 0;          

                        $diasI = $diasAp + $diasDp ;
                        $dias = $dias - $diasI;                                                    
                        // Empanada para cubrir retornos de vacaciones e incapaciades de mas o son 15 dias exactos de incapacidad
                        if ( ( $diasAp + $diasDp ) > $dat['diasCal'] )
                        {
                            $diasI = $dat['diasCal']; 
                            $dias = 0; 
                        }              
                        //if ($dat['total']==1) // Cubre el periodo todo el dia 
                        //    $dias = 0; 
                        //if ( ( $diasVaca > 0) and ( $diasI>$diasVaca ) ) 
                          //   $diasI = $diasVaca;

                        $d->modGeneral("update n_nomina_e set dias=".$dias.", 
                                     diasI=".$diasI." where id=".$iddn);
                        # Se marca idInc con una 1 para saber que ese empleado tiene incapacidad registrada 
                                                    
                    } // Fin validacion incapacidad                    
                    
                    // VALIDAR AUSENTISMOS -----------------------------------
                    $g->getAusentismosEmp($id); // Generacion de ausentismos
                    // Ausentismos reportados
                    $datAus = $g->getAusentismos($id); // Extraer datos del ausentismos del empleado si tuviera no remunerado
                    foreach($datAus as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = ($dat['dias']+$dat['diasAp']);# Dias de ausentismos no remunerado

                        //else 
                        //   $dias = $dat['dias'];# Dias de ausentismos no remunerado                             
                        if ($dias>0)
                        {
                           $aus = 1;                    
                           if ($dat['tipo']==1)// Licencia remunerada
                           {
                              $d->modGeneral("update n_nomina_e set dias = dias - ".$dias." where id=".$iddn);
                           }
                           if ($dat['tipo']==2)// Licencia no remunerada
                           {                           
                              if ($datGen['asuDesc']==1) // Configuracion general para descontar los dias de lo laborado y dejarlo en el deducido de la nomina
                              {
                                 $d->modGeneral("update n_nomina_e set dias = dias - ".$dias." where id=".$iddn);
                              }
                           }                           
                        }                                    
                    } // Fin validacion ausentismos
                    
                    // Ausentismos reportados tardes
                    $datAus = $g->getAusentismosNoreport($id); // Extraer datos del ausentismos del empleado si tuviera no remunerado
                    foreach($datAus as $dat)
                    {
                        $iddn = $dat['id'];
                        $dias = $dat['dias'];# Dias de ausentismos no remunerado reportado tarde
                        $d->modGeneral("update n_nomina_e set dias = dias-".$dias." where id=".$iddn);
                    } // Fin validacion ausentismos reportados tarde

                    // VALIDAR SI TIENE DIAS DIFERENTES EN UNA NOMINA YA LIQUIDAD                
                    $datIng = $d->getGeneral("Select diasLab, idEmp  
                      from n_nomina_nov 
                      where idConc = 0 and idIcal=".$idIcal." and idCal = ".$idCal." and idGrupo=".$idGrupo." and estado=0 and diasLab>0");        
                    foreach($datIng as $dat)
                    {
                        $idEmp = $dat['idEmp'] ;                   
                        $dias  = $dat['diasLab'] ;                                
                        $d->modGeneral("update n_nomina_e set dias=".$dias.", diaMod=1 where idEmp=".$idEmp." and idNom=".$id );
                    } // Fin validacion fecha de ingreso del empleado
                 } // fin validacion sea solo nomina general

                 if ($tipNom==5)
                 {       
                    // Guardar conceptos que se ejecutaran en nomina manual
                    $d->modGeneral("delete from n_nomina_c where idNom=".$id); 
                    $i=0;
                    if ($data->idConcM!='')
                    {
                      foreach ($data->idConcM as $dato)
                      {
                         $idConcM = $data->idConcM[$i];  $i++; 
                         $d->modGeneral( "insert into n_nomina_c (idNom, idConc)
                           values(".$id.",".$idConcM.")");                
                      }
                    }                   
                  } 
                    $connection->commit();
                    // Redireccionar
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
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
            $u=new Nomina($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 

   //----------------------------------------------------------------------------------------------------------
   // GENERACION NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
   
   // GENERACION NOMINA GENERAL-------------------------------------
   public function listpAction()
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  
         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d = new AlbumTable($this->dbAdapter);                 
         $n = new NominaFunc($this->dbAdapter);
         $g = new Gnominag($this->dbAdapter);
         $c = new PrimasA($this->dbAdapter);

         $pn = new Paranomina($this->dbAdapter);
         $dp = $pn->getGeneral1(1);
         $smlv = $dp['valorNum'];   // SALARIO MINIMO

         $dp = $pn->getGeneral1(12);
         $topRetefuente = $dp['valorNum'];   // BASE RETEFUENTE 

         $datGen = $d->getConfiguraG(''); // CONFIGURACIONES GENERALES   
         $cgIncaCons = $datGen['incapaCons']; // Configuracion vista de incapacidades 
         $incapaEmp = $datGen['incapaEmp']; // Incapacidad aproximada dias de empresas
         $retroAnoAnte = $datGen['retroVacaAnoAnte']; // Retro activos vacaciones año anteri
         $extraConve = $datGen['extraConve']; // Cuota Extra convencion

         // Buscar id de grupo
         $datos  = $d->getPerNomina($id); // Periodo de nomina
         $idg    = $datos['idGrupo'];         
         $idTnomL = $datos['idTnomL']; // Nomina de liquidacion 
         $idTnom = $datos['idTnom']; // Tipo de nomina
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];      
         $idIcal = $datos['idIcal'];         
         $mesNf = $datos['mesF'];         
         $anoNomina = $datos['ano'];         
         $mesNomina = $datos['mes'];                           
         $periodoNomina = $datos['periodo'];           
         $calendario = $datos['idCal'];
         $anticipo = $datos['anticipo']; 
         if ( $calendario == 9 ) // Cuando el calendario es mensual , el periodo es 2 para calculos de solidaridad
              $periodoNomina = 2;    

         $sw=1; // Solo para probar mas rapido ojo                                
         // NOTA DEBAJO HAY CAMPOS ASOCIADO A ESTA CONSULTA DATOS[]
     // INICIO DE TRANSACCIONES
     $connection = null;
     try {
         $connection = $this->dbAdapter->getDriver()->getConnection();
          $connection->beginTransaction();
    
       // $d->modGeneral("update n_nomina_e set dias = 0 where idNom = ".$id);      
        if ($sw==1) // Sw para probar generacion de nonna
        {

         // ( REGISTRO DE NOVEDADES MODIFICADAS DIAS LABORADOS) ( n_nomina_nove ) Guardadas en las novedades anteriores
         $datos2 = $g->getRnovedadesN($id, " and d.laborados > 0");// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              

         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             $idIcal  = $dato["idIcal"];   // Instruccion para calcular o no calcular
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------             
             $d->modGeneral("update n_nomina_e 
                                   set dias = ".$diasLab." 
                                      where id=".$iddn);

         } // FIN REGISTRO DE NOVEDADES MODIFICADAS POR DIAS LABORADOS 

         // PAGO DE RETROACTIVOS$
         //$dat = $d->getConsRetro($idg) ;
         $soloRetro = 0;
         //if ( $dat['num']>0)
         //{
         //   $datos = $g->getRetroActivos($id, $retroAnoAnte, $idg);
         //   $soloRetro = $dat['idPerA'];
         //}// FIN PAGO DE RETROACTIVOS

         // PAGO DE RETROACTIVOS POR AUMENTO DE SUELDO INDIVIDUAL 
         //$dat = $d->getConsRetroI() ;
         //if ( $dat['num']>0)
         //{
//            $datos = $g->getRetroActivosI($id);
         //}// FIN PAGO DE RETROACTIVOS

         // ( REGISTRO DE RETROACTIVOS NOMINA) 
         $tipoRetroSueldo=0; // Metodo cero para recalulo detallado
         $tipoRetroSueldo=0; // Metodo cero para calculo general
         $datos2 = $g->getRetroActivosNom($id, $tipoRetroSueldo);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         $swEmp = 0;
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             if ($dato["idConcRetro"]>0)
                $idCon   = $dato["idConcRetro"];   // Concepto de retroactivo 
             else
                $idCon   = $dato["idCon"];   // Concepto

             $dev=0;
             $ded=0;
             if ($tipo==1)
                $dev     = $dato["valor"];     // Devengado
             else  
                $ded     = $dato["valor"];     // Deducido

             $idfor   = -99;   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 1;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = '';
             $idProy = 0;
             // BUSCAR ALGUN OTRO INCREMENTO ESPECIAL 
             if ( $dato["aumentoConv"]>0 )
             {
                $dev = $dev + ( $dev * ($dato["aumentoConv"]/100) );
                $ded = $ded + ( $ded * ($dato["aumentoConv"]/100) );                
             }

             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set detalle = '".$dato["nomCon"]."', retroActivo = 1 where id=".$idInom);                          
             if ($dato["vlrTrans"]>0) // Descuento de subsidio de transporte
             {
                $idCon   = 114;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded     = $dato["vlrTrans"];     // Deducido              
                //$idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                // $idInom = (int) $idInom;                   

                //$d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                          
             }
             // Especial Cajamag descuento del 30 % solo para empleados convencionados 
             if ( ($extraConve>0) and ($dato["idTemp"]==1) and ($swEmp != $dato["idEmp"]) )
             {
                $swEmp = $dato["idEmp"];
                if ($dato["sueldoAnt"]>0)
                {
                   $idCon   = 59;   // Concepto descuento de subsidio de transporte
                   $tipo    = 2;
                   $dev     = 0;     // Devengado
                   $ded = ($dato["sueldoAct"] - $dato["sueldoAnt"]) * ($extraConve/100);
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                   $idInom = (int) $idInom;                   
                   $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);
                }   
             }             
             //              
         } // FIN REGISTRO DE RETROACTIVOS

        if ($soloRetro == 0) // Valida si el retro activo aplica en nomina quincenal 
        {   
         // ( REGISTRO DE DIAS PARA PAGAR POR DIAS EN NOMINA ) ( n_desvinculaciones )  
         $datos2 = $g->getLiquida($id,$fechaI,$fechaF);                               
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
            //$d->modGeneral("update n_nomina_e  
              //                set dias = ".$diasLab.", liqFinal = 1    
                //            where id = ".$iddn);
         } // FIN REGISTRO DE LIQUIDACIONES PARA PAGAR POR DIA 

         // ( REGISTRO DE NOVEDADES EN PROYECTOS ) ( n_proyectos )  
         $datos2 = $g->getRproyectos($id,$fechaI,$fechaF);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados              
             if ( $dato["horasLiq"] == 1 ) // Valdiar formula semanal en horas
             {
                $horas  = round( ( ($dato["diasProy"]/8) ) , 2 ) ;   // Horas laborados                
             }             
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             if ( $dato["horasLiq"] == 1 ) // Valdiar formula semanal en horas
             {             
                  $d->modGeneral("update n_nomina_e set dias = ".$horas." where id=".$iddn);
                  $idCon   = 305;
                  $idFor = -1;
                  $do = $domingo * 8;
                  //echo $domingo.'<br />';
                  if ( $dato["diasProy"] < 240 )
                  {
                     $horas  = round( ( ( ($dato["diasProy"] ) * 8 ) / 48 ) , 2 ) ;   // Horas laborados                             
                  }  
                  $dev = $horas * ($dato["sueldo"]/240);
             }
             if ( $dato["prog"] == 0 ) // Valdiar si viene con sueldo del proyecto o se deja programacion sola  
             {

                  $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              

                  $idInom = (int) $idInom;                   
                  $d->modGeneral("update n_nomina_e_d set idProy=".$idProy.", fechaEje ='".$dato['fechaEje']."' where id=".$idInom);
             }    
         } // FIN REGISTRO DE NOVEDADES EN PROYECTOS

         // ( REGISTRO DE NOVEDADES EN PROYECTOS PROGRAMACION ) ( n_proyectos )  (CONCEPTOS)
         $datos2 = $g->getRproyectosE($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $idMod   = $dato['idMod'];   // modalidad
             $ideCcos = $dato['idCcos'];   // Id ccos
             $diasLab = 0;    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             // Variables de uso formulas de modalidades
             $datHor = $d->getGeneral("select b.variable 
                                           from n_horarios_f b ");             
             foreach ($datHor as $datH)
             {                    
                $var = $datH["variable"];   // Variable 
                eval("\$".$var." = 0;");
             }                                            
             // Dia 31
             $dia31 =1 ;
             $datHor = $g->getFormulaDias31($ide);
             $dia31 = $datHor['num'];
             // Dia descansos ----------------------------------
             $descanso = 1;
             $festivo = 0;
             $domingo = 0;
             $dia = 0;
             $tipo = 3;
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $descansos = $datHor['num'];     
             // Dia descansos ordinario 
             $descanso = 1;
             $festivo = 0;
             $domingo = 0;
             $dia = 0; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $descansosO = $datHor['num'];                     
             echo 'descansosO :'.$descansosO.'<br />';
             // Dia descansos en domingo 
             $descanso = 1;
             $festivo = 0;
             $domingo = 1;
             $dia = 3; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $descansosD = $datHor['num'];
             echo 'descansosD :'.$descansosD.'<br />';                          
             // Dia descansos en festivos
             $descanso = 1;
             $festivo = 1;
             $domingo = 3;
             $dia = 3; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $descansosF = $datHor['num'];  
             // Nocturnos trabajados   
             $descanso = 3;
             $festivo = 3;
             $domingo = 3;
             $dia = 1; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $nocturnos = $datHor['num'];                         
             $nocturnosH = $datHor['horasAdicionales'];    
             $nocturnosR = $datHor['recargo'];    
             echo 'nocturnos :'.$nocturnos.' Horas : '.$nocturnosH.' <br />';                     
             // Nocturnos trabajados ordinarios  
             $descanso = 3;
             $festivo = 0;
             $domingo = 0;
             $dia = 1; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $nocturnosO = $datHor['num'];       
             $nocturnosOH = $datHor['horasAdicionales'];
             echo 'nocturnosO :'.$nocturnosO.' Horas : '.$nocturnosOH.' <br />';
             // Nocturnos domingos traabajados   
             $descanso = 3;
             $festivo = 3;
             $domingo = 1;
             $dia = 1; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $nocturnosD = $datHor['num'];
             $nocturnosDH = $datHor['horasAdicionales'];
             echo 'nocturnosD :'.$nocturnosD.' Horas : '.$nocturnosDH.' <br />';             
             // Nocturnos festivos traabajados   
             $descanso = 0;
             $festivo = 1;
             $domingo = 3;
             $dia = 1; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $nocturnosF = $datHor['num'];
             $nocturnosFH = $datHor['horasAdicionales'];
             echo 'nocturnosF:'.$nocturnosF.' horas '.$nocturnosFH.'<br />';

             // Domingos   
             $descanso = 0;
             $festivo = 0;
             $domingo = 1;
             $dia = 3; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $domingosW = $datHor['num'];
             $domingosWH = $datHor['horasAdicionales'];
             echo 'domingosW:'.$domingosW.' horas '.$domingosWH.'<br />';
             // Domingos descansos   
             $descanso = 1;
             $festivo = 3;
             $domingo = 1;
             $dia = 3; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo); 
             $domingosD = $datHor['num'];                                       
             // Diurnos  b.dia = 0 
             $descanso = 0;
             $festivo = 3;
             $domingo = 3;
             $dia = 0; 
             $tipo = 3;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $diurnos = $datHor['num'];                                         
             // Diurnos ordinarios   
             $descanso = 0;
             $festivo = 0;
             $domingo = 0;
             $dia = 0; 
             $tipo = 0;            
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $diurnosO = $datHor['num'];
             $diurnosOH = $datHor['horasAdicionales'];
             echo 'Diurnos O:'.$diurnosO.' horas '.$diurnosOH.'<br />';
             // Diurnos domingos 
             $descanso = 0;
             $festivo = 0;
             $domingo = 1;
             $dia = 0;     
             $tipo = 0;        
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $diurnosD = $datHor['num'];
             $diurnosDH = $datHor['horasAdicionales'];
             echo 'Diurnos D:'.$diurnosD.' horas '.$diurnosDH.'<br />';             
             // Diurnos festivos 
             $descanso = 0;
             $festivo = 1;
             $domingo = 0;
             $dia = 0;
             $tipo = 0;             
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);
             $diurnosF = $datHor['num'];
             $diurnosFH = $datHor['horasAdicionales'];
             echo 'Diurnos O:'.$diurnosF.' horas '.$diurnosFH.'<br />';             
             // Dia fetivos
             $descanso = 0;
             $festivo = 1;
             $domingo = 3;
             $dia = 3;
             $tipo = 3;             
             $datHor = $g->getFormulaDias($ide,$descanso,$festivo,$domingo,$dia,$tipo);             

             $festivosW = $datHor['num'];
             $festivosWH = $datHor['horasAdicionales'];
             echo 'festivosW:'.$festivosW.' horas  '.$festivosWH.' <br />';                                                       
             // Variables de uso formulas de modalidades
             $datHor = $d->getGeneral("select b.variable, count(a.id ) as valor 
                                 from n_nov_prog_m a
                                           inner join n_horarios_f b on b.id = a.idHfor 
                                           inner join n_nov_prog c on c.id = a.idNov 
                                        where c.idEmp = ".$ide." 
                                          group by b.variable");             
             foreach ($datHor as $datH)
             {                    
                $var = $datH["variable"];   // Variable 
                eval("\$".$var." = ".$datH["valor"].";");
                //echo $datH["variable"].' '.$diasDescanso.'<br />';
             }                              
             // Grupos de variables asignadas por formula
             $datHor = $d->getGeneral("select * from t_formulas");             
             foreach ($datHor as $datH)
             {                    
                $var = "$".$datH["variable"];   // Variable 
                $formula = $datH["nombre"];
                //eval("\$".$var." = $formula;");
                 eval("\$var =$formula;");
                //echo $var.'<br />';
             }                                           

             // Recorrer turnos del empleado
             $datHor = $d->getGeneral("select d.idCon, count(a.id) as diasTurno, g.horasFijas as horario, g.horas as horasModalidad, a.idHor, e.tipo, e.idFor,f.formula, case when g.formula is null then '' else g.formula end as formulaMod, h.tipo as tipoMod, g.valida, g.si, g.no         
                                        from n_nov_prog_m a
                                           inner join n_horarios_f b on b.id = a.idHfor 
                                           inner join n_nov_prog c on c.id = a.idNov 
                                           inner join n_horarios_c d on d.idHor = a.idHor 
                                           inner join n_conceptos e on e.id = d.idCon 
                                           inner join n_formulas f on f.id = e.idFor 
                                           left join n_modalidad_c g on g.idMod = ".$idMod." and g.idCon = e.id 
                                           left join n_modalidad h on h.id = g.idMod 
                                        where c.idEmp = ".$ide." 
                                             and h.tipo = 0  
                                           group by e.id ");             
             foreach ($datHor as $datH)
             {                    
                   $formMod   = 1;   // Horas 
                   // Formula segun modalidad para calculo de horas a pagar 
                   if ( $datH["formulaMod"] != '' )
                   {
                       $formula = $datH["formulaMod"];
                       eval("\$formMod =$formula;");
                   }
                   if ( ($datH['valida']!='') and ($datH['valida']!=NULL) )
                   {
                       $swVal=0;
                       $val = trim($datH['valida']);  
                       eval(
                          'if ('.$val.'){'.
                               '$swVal=1;'.
                         '}'); 
                       //echo 'DONCIDIONAL : '.$swVal;
                       if ( $swVal == 1 )
                           $formula = $datH["si"];
                       else
                           $formula = $datH["no"]; 
                       eval("\$formMod =$formula;");
                    }   

                   //echo $datH["formulaMod"].'<br />';
                   //$datH["diasTurno"]*
                   $horas = round( $datH["horario"]*$datH["horasModalidad"]*$formMod , 2);

                   $formula = $datH["formula"]; // Formula
                   $tipo    = $datH["tipo"];    // Devengado o Deducido  
                   $idCcos  = $dato["idCcos"];  // Centro de costo   
                   $idCon   = $datH["idCon"];   // Concepto
                   $dev     = 0;     // Devengado
                   $ded     = 0;     // Deducido
                   $idfor   = $datH["idFor"];   // Id de la formula 
                   $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
                   $calc    = 0;   // Instruccion para calcular o no calcular
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 1; // 1 para obtener el id insertado
                   $fechaEje = '';
                   $idProy = 0;
                   // Llamado de funcion   
                   //echo $idCon.'<br />' ;
                   if ( ($idCon>0) and ($horas>0) )
                   { 
                      //-------------------------------------------------------------------
                      $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                   }   
                  // $idInom = (int) $idInom;                                      
             }             

             // Recorrer turnos del empleado
             $datHor = $d->getGeneral("select  a.dia , a.domingo, a.festivo, 
case when ( timediff( d.hoS, d.hoI ) ) < 0 then 
  (timediff( d.hoS, d.hoI) ) * -1 
else 
  timediff( d.hoS, d.hoI ) end as horas,    
     g.id as idFor , g.formula , f.id as idConc, d.codigo, d.hoDiurnas, d.hoNocturnas                        
                        from n_nov_prog_m a
                                           inner join n_nov_prog c on c.id = a.idNov 
                                           inner join n_horarios_c b on b.idHor = a.idHor 
                                           inner join n_horarios d on d.id = b.idHor 
                                           inner join n_conceptos_hor e on e.idConc in ( 338 ) 
                                           inner join n_conceptos f on f.id = e.idConc 
                                           inner join n_formulas g on g.id = f.idFor 
                                        where c.idEmp = ".$ide);             
             $fin = 8;
             $hor = 0;
             $sem = 1;
             $noche = '';
             $swMarca = 0;
             foreach ($datHor as $datH)
             {
                //echo $datH['dia'].'<br />';
              
                if ( $datH['domingo'] == 1 )
                {
                    
                    $fin = $fin - 8;
                    //$hor = $hor - $horLab;
                    // Insertar semana en nomina
                    $formula = $datH['formula'];                                            
                   //echo $datH["formulaMod"].'<br />';
                   //$datH["diasTurno"]*
                   //if ($noche=='N')
                   //    $hor = $hor - 9;                    
                    $horas = 0; 
                   if ( $marca == 4 ) 
                       $horas = 0; 
                   if ( $marca == 12 ) 
                       $horas = 0; 
                   if ( $marca == 18 ) 
                       $horas = 0; 
                   if ( $marca == 26 ) 
                       $horas = 12;                                             


                   //$horas =  $hor - $fin;
                      
                   $tipo    = 1;    // Devengado o Deducido  
                   $idCon   = $datH['idConc'];   // Concepto
                   $dev     = 0;     // Devengado
                   $ded     = 0;     // Deducido
                   $idfor   = $datH['idFor'];   // Id de la formula 
                   $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
                   $calc    = 0;   // Instruccion para calcular o no calcular
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 1; // 1 para obtener el id insertado
                   $fechaEje = '';
                   $idProy = 0;
                   // Llamado de funcion   
                   //echo 'final '.$noche.' <br />' ;
                   if ($horas>0)
                   {                       //-------------------------------------------------------------------
                      $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              

                      $d->modGeneral( "update n_nomina_e_d  
                               set detalle = 'EXTRAS DIURNAS SEMA(".$sem.") ".$fin."-".$hor."'     
                                    where id=".$idInom );
                   }   
                   $horas=0;
                   if ( $marca == 4 ) 
                       $horas = 8; 
                   if ( $marca == 12 ) 
                       $horas = 0; 
                   if ( $marca == 18 ) 
                       $horas = 20; 
                   if ( $marca == 26 ) 
                       $horas = 0;  

                   if ($horas>0)
                   {
                      $idCon = 339;
                      $idfor = 2;                    
                      $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                       $d->modGeneral( "update n_nomina_e_d  
                               set detalle = 'EXTRAS NOCTURNA SEMA(".$sem.") ".$fin."-".$hor."'     
                                       where id=".$idInom );
                   }  


                   if ( $marca == 4 ) 
                       $horas = 1; 
                   if ( $marca == 12 ) 
                       $horas = 0; 
                   if ( $marca == 18 ) 
                       $horas = 1; 
                   if ( $marca == 26 ) 
                       $horas = 0;  

                   if ($horas>0)
                   {
                      $idCon = 170;
                      $idfor = 4;                    
                      $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                       $d->modGeneral( "update n_nomina_e_d  
                               set detalle = 'RECARGO NOCTURNO SEMA(".$sem.") ".$fin."-".$hor."'     
                                       where id=".$idInom );
                   }  

                    echo ' TOTAL '.$hor.' - '.$fin.' = '.($hor-$fin).' MARCA '.$marca.'<br />';

                    echo '<hr />';
                   $fin = 8;
                   $hor = 0;
                   $sem++;
                   $swMarca = 0;
                }else // Fin validacion fin de semana
                {
                  if ( ($datH['festivo'] != 1) )
                  {  
                    $horLab = $datH['hoDiurnas']+$datH['hoNocturnas'];
                    $horLabInf = $datH['hoDiurnas'].'- '.$datH['hoNocturnas'];
                    if ( ($datH['descanso'] == 1) )
                    {  
                      $horLab = 0;
                      $horLabInf = "";
                    }   

                    echo $datH['dia'].' '.$datH['codigo'].' ('.$horLabInf.') '.$horLab.' - '.$fin.'<br />';

                    $noche = $datH['codigo'];
                    $fin = $fin + 8;                
                    $hor = $hor + $horLab;    
                    $marca = '';         
                      if ( $hor >= 48 )
                      {  
                        $marca = $datH['dia'];    
                        $swMarca = 1;                 
                      }else{  
                        if ( $hor >= 40 )
                        {  
                           $marca = $datH['dia'];
                           $swMarca = 1;                 
                        }else{   
                           if ( $hor >= 32 )
                           { 
                             $marca = $datH['dia']; 
                             $swMarca = 1;
                           }  
                        }   
                      } 
                      if ($datH['dia']==5) 
                          $marca = 4; 
                      if ($datH['dia']==19) 
                          $marca = 18;   




                     // echo 'sumatoria '.$hor.'<br />';                               

                  }  
                }// Validacion domingo no va 
             }

         } // FIN REGISTRO DE NOVEDADES EN PROYECTOS


         // NDESCUENTO POR ANTICIPOS
         if ($anticipo==1)
         { 
            $datos2 = $g->getDescontarAnt($id);
            foreach ($datos2 as $dato)
            {             
              $iddn    = $dato['id'];  // Id dcumento de novedad
              $idin    = 0;     // Id novedad
              $ide     = $dato['idEmp'];   // Id empleado
              $diasLab = $dato['dias'];    // Dias laborados
              $diasVac = 0;    // Dias vacaciones
              $horas   = $dato["horas"];   // Horas laborados 
              $formula = $dato["formula"]; // Formula
              $tipo    = $dato["tipo"];    // Devengado o Deducido  
              $idCcos  = $dato["idCcos"];  // Centro de costo   
              $idCon   = $dato["idCon"];   // Concepto
              $dev     = $dato["dev"];     // Devengado
              $ded     = $dato["ded"];     // Deducido
              $idfor   = $dato["idFor"];   // Id de la formula 
              $diasLabC= 0;   // Determinar si la afecta los dias laborados para  convertir las horas laboradas
              $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 0; // 1 para obtener el id insertado
              $fechaEje  = '';
              $idProy  = 0;
              $idIcal  = $dato["idIcal"];   // Instruccion para calcular o no calcular
                  $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              

                  $idInom = (int) $idInom;                   
                  //$d->modGeneral("update n_nomina_e_d set idProy=".$idProy.", fechaEje ='".$dato['fechaEje']."' where id=".$idInom);              
            }
         } // FIN VALIDACION DESCUENTO POR NOMINA DE ANTICIPOS 
         // REEMPLAZOS DE EMPLEADOS
         $datos2 = $g->getReemplazos($id,$fechaI,$fechaF);// Reemplazos 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado

             $dias = $dato['dias'];

             $deta = '';
             if ($dato['reportada']==0)
             {
                $diasLab = $dias + $dato['diasAnt'];
                $deta = '*';
                if ($dato['diasAnt']>0)
                    $deta = '* dias '.$dias.' - ( retro '.$dato['diasAnt'].')'; 
                
             }
             else 
             {
                $deta = '( dias '.$dias.')'; 
                $diasLab = $dias ;
             }
             if ($dato['diasFinMes']==28) // Mes de febreo 
             {
                $diasLab = $diasLab + 2 ;
             }
             if ($dato['diasFinMes']==29) // Mes de febreo 
             {
                $diasLab = $diasLab + 1 ;
             }             

             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["vlrHora"] * ($diasLab) ;     // Devengado
//             $dev     = $dato["vlrHora"] * 15 ;     // Parametrizar esto 
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 0;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy = 0;
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy );              
             $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set detalle ='DIFERENCIA EN SUELDO (".$dato['fechaI']." - ".$dato['fechaF'].")  ".$deta." ', 
                                       idRem=".$dato['idRem']." where id=".$idInom);             
         } // FIN REGISTRO DE NOVEDADES                     

         // ( REGISTRO DE NOVEDADES ) ( n_novedades ) 
         $datos2 = $g->getRnovedades($id,$idIcal);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             $saldoPact = 0;
             if ( $dato["valCuota"] > 0 )
             {                
                if ($dev>0)
                {
                   $saldoPact = $dev-$dato["pagado"];                  
                   $dev = $dato["valCuota"]; 
                }
                else
                {
                   $saldoPact = $ded-$dato["pagado"];                  
                   $ded = $dato["valCuota"]; 
                }                  
             }
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             // GUARDAR REGISTRO 
             $d->modGeneral("update n_nomina_e_d 
                set idProy=".$idProy.", saldoPact=".$saldoPact.",
                 fechaEje ='".$dato['fechaEje']."' , idInov=".$dato['idInov']." where id=".$idInom);
             
             // Validar auxilio añoa nterior, empanada para cajamag
             if ($fechaEje>'0000-00-00')
             {
               if ( ( $idCon == 137 ) and ($fechaEje<'2015-01-01') )
               {
                  $formula = ( 1154540 * (25/100) );
                  //echo $formula;
                  $d->modGeneral("update n_nomina_e_d 
                   set devengado=".$formula." where id=".$idInom);                
                }
             }             
         } // FIN REGISTRO DE NOVEDADES                     


         if ($tipoRetroSueldo==100)
         {
         $datos2 = $d->getGeneral("select a.* ,( select aa.sueldo 
                 from n_nomina_e aa 
                      where aa.idEmp = a.idEmp and aa.idNom < a.idNom order by aa.id desc limit 1 ) as sueldoAnt, c.idCcos  
                         from n_nomina_e a 
                         inner join a_empleados c on c.id = a.idEmp 
                   where contra != 3 and a.idNom = ".$id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de 
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = 1;    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = 132;   // Concepto de retroactivo 
             $dev=0;
             $ded=0;
             $dev     = ( $dato["sueldo"] - $dato["sueldoAnt"] ) * 2;     // Devengado

             $idfor   = -99;   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 1;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = '';
             $idProy = 0;
             //echo $ide.'RETRO  '.$dev.' <br />';
             // BUSCAR ALGUN OTRO INCREMENTO ESPECIAL 
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             if ( $dev > 0) 
             {
//                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
  //              $idInom = (int) $idInom;                   
    //            $d->modGeneral("update n_nomina_e_d set detalle = '* RETROACTIVO SUELDO ', retroActivo = 1 where id=".$idInom);                          
             }
           }    //              
         } // FIN REGISTRO DE RETROACTIVOS PROCESO ESPECIAL SUELDOS          
         
         // ( REGISTRO DE RETROACTIVOS NOMINA POR AUMENTO DE SUELDO INDIVIDUAL) 
         $datos2 = $g->getRetroActivosNomI($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             if ($dato["idConcRetro"]>0)
                $idCon   = $dato["idConcRetro"];   // Concepto de retroactivo 
             else
                $idCon   = $dato["idCon"];   // Concepto

             $dev=0;
             $ded=0;
             if ($tipo==1)
                $dev     = $dato["valor"];     // Devengado
             else  
                $ded     = $dato["valor"];     // Deducido

             $idfor   = -99;   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = 1;   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = '';
             $idProy = 0;
             // BUSCAR ALGUN OTRO INCREMENTO ESPECIAL 
             if ( $dato["aumentoConv"]>0 )
             {
                $dev = $dev + ( $dev * ($dato["aumentoConv"]/100) );
                $ded = $ded + ( $ded * ($dato["aumentoConv"]/100) );                
             }
             if ( $dato["idCon"] !== 134 ) // Que nosea retro por dife en sueldo 
             {
                // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                $idInom = (int) $idInom;                   

             $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                          
             if ($dato["vlrTrans"]>0) // Descuento de subsidio de transporte
             {
                $idCon   = 114;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded     = $dato["vlrTrans"];     // Deducido              
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   

                $d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                          
              }
           }
             // Especial Cajamag descuento del 30 %
             if ($dato["sueldoAnt"]>0)
             {
                $idCon   = 59;   // Concepto descuento de subsidio de transporte
                $tipo    = 2;
                $dev     = 0;     // Devengado
                $ded = ($dato["sueldoAct"] - $dato["sueldoAnt"]) * (30/100);
                //$idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
                // $idInom = (int) $idInom;                   
                ///$d->modGeneral("update n_nomina_e_d set retroActivo = 1 where id=".$idInom);                                                           
             }             
             //              
         } // FIN REGISTRO DE RETROACTIVOS POR AUMENTO DE SUELDO INDIVIDUAL

         // PRIMA DE ANTIGUEDAD
         $datos = $d->getPrimaAnt();
         $con = '';
         foreach($datos as $dat)
         {
           $ano = $dat['ano']; 
           $mes = $dat['mes']; 
           $anoF = $dat['anoF']; 
           if ( $dat['anual'] == 1 )
              $datos2 = $g->getDiasPantiA($id, $ano, $anoF);// Primas por antiguedad anual 
           else 
              $datos2 = $g->getDiasPanti($id, $ano, $mes);// Primas por antiguedad condicionada
              //    
           //print_r($datos2);
           foreach ($datos2 as $dato)
           {             
            if ( $dato['pg']==0 )
            {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $horas   = 0;   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dat["formula"]; // Formula
             $tipo    = $dat["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dat["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = -1;   // Id de la formula ,   -1 para ejecutar formula de primas de antiguerad
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $idPant  = 0;
             $obId    = 1; // 1 para obtener el id insertado             
             $fechaEje  = '';
             $idProy  = 0;
             //echo $formula;
             // Llamado de funion -------------------------------------------------------------------
             if ( $dato['diaI'] == 1) // 0 no esta dentro del periodo, 1 esta dentro del periodo
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy );              
                $idInom = (int) $idInom;                   
                // GUARDAR REGISTRO PAGO PRIMA DE ANTIGUEDAD
                $c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id, $ano, $dat['id']);
                $datAnt = $d->getGeneral1("Select nombre from n_conceptos where id=".$idCon);
                $d->modGeneral("update n_nomina_e_d set detalle='".$datAnt['nombre'].' ('.$dato['fecha'].")' where id=".$idInom);
              }
            }
            } 
         } // FIN PRIMA DE ANTIGUEDAD      
         
        // 3. INCAPACIDADES 
        $datos2 = $g->getIncapNom($id, 0);// ( n_nomina_e_i ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             //$diasLab = $dato['dias'];    // Dias laborados 
             $diasLab = 0;    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Validaciones para incapacidades 
             $diasCal = $dato['diasCal'];
             $diasEnt = $dato['diasEnt'];           
             $diasAp  = $dato['diasAp'];
             $diasDp  = $dato['diasDp'];            
             //echo ' g '.$datGen['incAtrasada'].'<br />';
             // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
             if ( $datGen['incAtrasada'] == 0)
                $diasAp = 0;
             if ( $dato['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                $diasAp = 0;          

             $diasI = $diasAp + $diasDp ; // dias total de incapacidades
             // Validar que no tenga mas dias calendario
             if ($diasI > $dato['diasCal'])
             {
                $diasI = $dato['diasCal'];
             }
             $horasIncaEnt = 0;
             // Convertir a horas
             $ente = '';
             if ( $dato["tipInc"] == 1 )// Empresa
             {
                 $horas = $dato["diasEmp"] * 8; 
                 $ente = 'EMP';
                 if ($incapaEmp==1) // Valida configuracion para aproximar dias de empresa para pago llevado al minimo
                 {
                    $horasIncaEnt = $horas/8;                    
                 }
             }             
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas = ( $diasI - $dato["diasEmp"] ) * 8;
                 // Validar si esta por debajo del valor dia del minimo
                 $horasIncaEnt = $horas/8;                    
                 $ente = 'EPS';
             }  
             $dias = ($horas/8);
             if ($cgIncaCons==0)// Presentar incapacidad en linea dividida en empresa o entidad
              {
                 $ente = '';
                 $dias = $diasI;
              }   
             // Llamado de funion -------------------------------------------------------------------
             if ($horas>0)
             {
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy); 
                $idInom = (int) $idInom;
                $rep='';                
                if ($dato['reportada']==0)
                    $rep = '*';

                $d->modGeneral("update n_nomina_e_d 
                            set detalle='".$rep."INCAPACIDAD ".$ente." - ".$dato['nomTinc']." (".$dato['fechai']." - ".$dato['fechaf'].") -- ".$dias." dias ' where id=".$idInom);
                if ( $horasIncaEnt > 0 ) // VALIDACION SI EL PAGO ESTA POR DEBAJO DEL MINIMO                
                {
                 // if ( $ide == 222 )
                   //  echo 'ENTRO INCAPAC';
                   $datV = $d->getGeneral1("select devengado from n_nomina_e_d where id=".$idInom);                            
                   $horDev = $datV['devengado'] / $horasIncaEnt;
                   if ( $horDev < ( $smlv/30 ) )
                   {
                      $val = (( $smlv/30 ) * $horasIncaEnt ); // a lleva al minimo
                      $d->modGeneral("update n_nomina_e_d set devengado = ".$val." where id=".$idInom);
                   }
                }
                $d->modGeneral("update n_nomina_e_d set idInc = ".$dato['idInc']." where id=".$idInom);                           
                $d->modGeneral("update n_nomina_e_i set diasInc= ".$diasI.",diasEmp = ".$dato["diasEmp"]." where idNom=".$id." and idInc=".$dato['idInc']);                           
             }
         } // FIN INCAPACIDADES 

        // INCAPACIDADES PRO
        $datos2 = $g->getIncapNom($id, 1);// ( n_nomina_e_i ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Validaciones para incapacidades 
             $diasCal = $dato['diasCal'];
             $diasEnt = $dato['diasEnt'];           
             $diasAp  = $dato['diasAp'];
             $diasDp  = $dato['diasDp'];            
             //echo ' g '.$datGen['incAtrasada'].'<br />';
             // Verificar si esta parametrizado para pagar los dias de incapacida o solo reportarlos
             if ( $datGen['incAtrasada'] == 0)
                $diasAp = 0;
             if ( $dato['reportada'] == 1)// Si esta reportada anteriormente no se toman dias anteriores
                $diasAp = 0;          

             $diasI = $diasAp + $diasDp ; // dias total de incapacidades
             // Validar que no tenga mas dias calendario
             if ($diasI > $dato['diasCal'])
             {
                $diasI = $dato['diasCal'];
             }
             // Convertir a horas
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas = ( $diasI - $dato["diasEmp"] ) * 8;
             }             

             // Validar si incapacidad pasa de 90 dias 
             $dat = $d->getGeneral1("select DATEDIFF( a.fechaf, a.fechaI) as dias 
                                        from n_incapacidades a where id=".$dato["idInPadre"]);
             $diasInc = $dat['dias'];
             $dat = $d->getGeneral1("select sum(DATEDIFF( a.fechaf, a.fechaI)) as dias 
                                        from n_incapacidades_pro a where idInc=".$dato["idInPadre"]);             
             $diasInc = $diasInc + $dat['dias'];

             // Convertir a horas
             if ( $dato["tipInc"] == 2 )// Entidad esps u otra
             {
                 $horas   = ( $dato["diasEmp"] + $dato["diasEnt"] ) * 8;
                                  // Validar si esta por debajo del valor dia del minimo
                 $horasIncaEnt = $horas/8;                    
                 
                 if ($diasInc>90) // Ojo esto va por variable 
                 {
                    $idCon = 280 ; 
                    $idfor   = 35;   // Id de la formula  
                    $formula = '($Valhora *(50/100) )'; // Formula
                 }
             }             

             // Llamado de funcion -------------------------------------------------------------------
             if ($horas>0)
             {

                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy); 
                $idInom = (int) $idInom;
                $detalle = '';

                $rep='';
                if ($dato['reportada']==0)
                    $rep = '*';
                 $detalle = $rep." PRORROGA  ".$dato['nomTinc']." (".$dato['fechai']." - ".$dato['fechaf'].") -- ".$diasI." dias ";

//                if ($diasInc > 180)
  //                 $detalle = 'Este empleado ha sobrepasado los 180 dias de incapacidad. Total '.$diasInc ;
                 // Validar si es menor que el salario minimo 
                if ($diasInc < 180)
                {
                   $datV = $d->getGeneral1("select devengado from n_nomina_e_d where id=".$idInom);                            
                   $horDev = $datV['devengado'] / $horasIncaEnt;
                   if ( $horDev < ( $smlv/30 ) )
                   {
                      $val = (( $smlv/30 ) * $horasIncaEnt ); // a lleva al minimo
                      $d->modGeneral("update n_nomina_e_d set devengado = ".$val." where id=".$idInom);
                   }                  
                }
                $d->modGeneral("update n_nomina_e_d set detalle='".$detalle."', idInc = ".$dato['idInc']." where id=".$idInom);                           
                $d->modGeneral("update n_nomina_e_i set diasInc= ".$diasI.",
                                   diasEmp = ".$dato["diasEmp"].", diasProrroga = ".$diasInc."  
                                   where idNom=".$id." and idInc=".$dato['idInc']);                                           
             }
         } // FIN INCAPACIDADES 

         // AUSENTISMOS
         $datos2 = $g->getNominaAus($id);// Por asusentismos ( n_nomina_e_d ) Programado
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $horas   = $dato['horas'];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             if ($tipo==1)
                $dev = $dato["valor"];   
             else 
                $ded = $dato["valor"];                 
             
             if ($dato["horAus"]>0)// Es por fechas de ausencia 
             {
                if ($tipo==1)
                    $dev = ( $dato["valor"]/8 ) * $dato["horAus"] ;                 
                else 
                    $ded = ( $dato["valor"]/8 ) * $dato["horAus"] ;                 
             }

             $idfor   = -99;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;

             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);
             $idInom = (int) $idInom;
             if ($dato["horAus"]>0)// Es por fechas de ausencia 
             {
                $detalle = $dato["nomCon"].' ( el '.$dato["fechai"].' por '.$dato["horas"].' horas )';
                $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);                
             }
             else    
             {
                $detalle = $dato["nomCon"].' ( del '.$dato["fechai"].' al '.$dato["fechaf"].' )';
                // Se ponen los valroes en 0 para que salga impreso en la nomina               
                if ($dato["tipAus"]==1) // Ausentismo remunerado
                {
                   $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);                    
                   if ($dato["horAus"]>0)// Es por fechas de ausencia 
                   {
                      $d->modGeneral("update n_nomina_e_d set devengado=0, deducido=0 where id=".$idInom);                
                   }                   
                }
                if ($dato["tipAus"]==2) // Ausentismo no remunerado
                {
                    if ($datGen['asuDesc']==1) // Descuenta del documento las novedades de ausentismos no remunerados
                      $d->modGeneral("update n_nomina_e_d set devengado=0, deducido=0, detalle = '".$detalle."' where id=".$idInom); 
                    else
                      $d->modGeneral("update n_nomina_e_d set detalle = '".$detalle."' where id=".$idInom);   
                }
                //else                    
                //   $d->modGeneral("update n_nomina_e_d set horas=0, devengado=0, deducido=0, detalle = '".$detalle."' where id=".$idInom); 
             }
                     
         } // FIN AUSENTISMOS    

      //  ------------------------------- ( 111 )

         // VACACIONES EN DISFRUTE GENERACION
         $datos2 = $g->getVacacionesG($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $horas   = $dato["horas"];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $datGen["idVac"]; // Concepto de vacaciones disfrutadas en tabla de configuraciones
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);                                                               

         } // FIN VACACIONES EN DISFRUTE       
         // VACACIONES PAGADAS GENERACION
         $datos2 = $g->getVacacionesGc($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $horas   = $dato["horas"];   // Horas laborados 
             $diasVac = 0;    // Dias vacaciones
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $datGen["idVacP"]; // Concepto de vacaciones disfrutadas en tabla de configuraciones
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac, $obId, $fechaEje, $idProy);                                                               
         } // FIN VACACIONES PAGADAS

         // CONCEPTOS HIJOS 
         $datos2 = $g->getNominaConH($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
           if ($dato['Temp']>0)
           {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
           }
         } // FIN CONCEPTOS AUTOMATICOS POR PERIODO                           

      // SOLO PARA NOMINAS NORMALES 

         // ( POR TIPO DE AUTOMATICOS )
         $auto = 1; // Automatico general numero 1  
         $datos2 = $g->getNominaEtau($id,$idg, $auto );// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
//print_r($datos2);         
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;

             // Si es afectado por los dias laborados
             // y no tiene formula y tiene valor funciona esta forma
             if ( ($formula=='') and ($diasLabC==1) and ($dev > 0 ) and ($dato["horasCal"]==0) )
             {
                $valor = $dev;
                $formula = '($diasLab*('.$valor.'/30))';
                //echo 'ENTRO FORMULAS DIAS AFECTADOS ';
                $dev = 0;
                $idfor   = -1;   // Para ejecutar la formula 
             }else{
              $diasLabC=0;
             }
             // Llamado de funcion -------------------------------------------------------------------
             //if ($dato["actVac"]==0)
             //{

              //  if ($ide == 2803)  
              //echo 'ENTRO '.$ide.' '.$formula .'<br />';
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
                $idInom = (int) $idInom; 
//                echo 'Entro '.$idInom;                  
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                          
              //}

              // Si tiene algun aumento de sueldo dentro del periodo
              if ($dato['diasS'] > 0 )
              {              
                 if ( $idCon == 122 ) // Solo para concepto de sueldo 
                 {
                    $horas    = $dato["diasS"];   // Dias laborados
                    $diasLabC = 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas                    
                    $idfor    = -99;   // Id de la formula           
                    $dev      = $dato["vlrDiaAnt"] * $dato["diasS"];   // Dias laborados
                    $formula = ''; // Formula
                    $idCon = 24;                    
                    $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
                    $detalle = 'SUELDO ANTERIOR ( '.number_format( $dato["sueldoAnt"]).')';//fecAum
                    $idInom = (int) $idInom;                   
                    $d->modGeneral("update n_nomina_e_d set nitTer='', detalle='".$detalle."' where id=".$idInom);
                  }
              }

         } // FIN TIPOS DE AUTOMATICOS

         // ( POR TIPO DE AUTOMATICOS 2 opcionales)
         $auto = 2; // Automatico general numero 2  
         $datos2 = $g->getNominaEtau($id,$idg, $auto );// Insertar nov automaticas (
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Valdiacion especial retro por ingreso a grupos cajamag
            // if ( $dato["estado"]==0)
            // {

            // }
             // Llamado de funcion -------------------------------------------------------------------
             if ($dato["actVac"]==0)
             {             
                 $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                 $idInom = (int) $idInom;                   
                 $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                           
             }

         } // FIN TIPOS DE AUTOMATICOS 2 (opcionales)         
         
         // ( POR TIPO DE AUTOMATICOS 3 opcionales)
         $auto = 3; // Automatico general numero 3  
         $datos2 = $g->getNominaEtau($id,$idg, $auto );// Insertar nov automaticas (
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             if ($dato["actVac"]==0)
             {             
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                       
             }
         } // FIN TIPOS DE AUTOMATICOS 3 (opcionales) 
                 
         // ( POR TIPO DE AUTOMATICOS 4 opcionales)
         $auto = 4; // Automatico general numero 4  
         $datos2 = $g->getNominaEtau($id,$idg, $auto );// Insertar nov automaticas (
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= $dato["diasLab"];   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $conVac  = $dato["vaca"];   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             if ($dato["actVac"]==0)
             {             
                // Llamado de funcion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 1,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                $idInom = (int) $idInom;                   
                $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);                                                    
             }

         } // FIN TIPOS DE AUTOMATICOS 4 (opcionales)  
                         
         // OTROS AUTOMATICOS POR EMPLEADOS
         $datos2 = $g->getNominaEeua($id);// Insertar nov automaticas ( n_nomina_e_d ) por otros automaticos
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             if ( $dato['horasConc'] == 1 ) // Horas
             {
                $horas = $dato['valorFijo'];  
                $dev   = 0;     // Devengado
                $ded   = 0;     // Deducido                  
             } 
             $idfor   = -99;   // Id de la formula no tiene formula asociada, ya viene la formula 
             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Fomrula para dais mas vacaciones en otros automaticos
             $valor = 0;
             if ( $dev > 0 ) 
             {
                $valor = $dev;$dev=0;
             }else
             {
                $valor = $ded;$ded=0;
             }
             //if ( $dato['horasCal'] > 0 ) // Afectado por lso dias laborados
             //{
             //   $formula = ' $diasLab*'.$valor; // Concatenan para armar la formula
             //   $diasLabC = $dato['dias'] ;   // Dias laborados solo para calculados
             //}else{
                if ( $dato['idVac'] > 0 )
                   $formula = ' ($diasLab+$diasVac+$diasInca+$diasAus)*'.$valor; // Concatenan para armar la formula
                else 
                   $formula = ' ($diasLab+$diasVac+$diasInca+$diasAus)*'.$valor; // Concatenan para armar la formula                  
                   //$formula = ' ($diasLab+$diasVac+$diasInca+$diasMod)*'.$valor; // Concatenan para armar la formula
             //}
             if ( ( $dato['idTnom'] == 1 ) and ( $diasLab < 15 ) and ($dato['vacAct']==0) )
                $formula = '15*'.$valor;
                                 
             if ( $dato['formula']!='' )
                $formula = $dato['formula'];  

             if ( ( ( $dato['idTnom'] != 5 ) ) and  ( $dato['perAuto'] == 2 ) or ( $dato['perAuto'] == 1 ) )
             {
                $formula = $dato['valorFijo'];
             } 
             $sw = 0;

             if ( ( $idTnom != 11 ) and ( $dato['nomAnt'] > 0 ) ) ## Valiadr que no sea nomina de anticipos 
                 $sw = 1;
               
              //echo 'ifo  '.$formula;
             //echo $dato['nomAnt'].' form:'.$formula.' sw:'.$sw.'<br />';                 
             // Llamado de funion -------------------------------------------------------------------
             if ( ( ($dato['fecAct']==0) or ($dato['fecAct']==1) ) and ($sw==0) )
             {
                //if ( $periodoNomina = 0)
                $sw = 0;
                //if ( ( $dato['perAuto'] == 2 ) and ($dato['fechaR'] <= '2017-09-15') )
                  //$sw = 1;

                if ($sw==0)
                {  
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 2,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);              
                   $idInom = (int) $idInom;                   
                     $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);             
                   // Validar si tiene descuentos en el mismo mes por conceptos de vacaciones 
                   if ( $dato['perAuto'] == 2 )
                   {
                      $datDst = $d->getGeneral1("select count(aa.id) as num, sum( aa.deducido ) as valor, bb.nombre   
                          from n_nomina_e_d aa 
                                    inner join n_conceptos bb on aa.idConc = bb.id 
                                    inner join n_nomina_e dd on dd.id = aa.idInom
                                    inner join a_empleados ee on ee.id = dd.idEmp 
                                    inner join n_nomina ff on ff.id = dd.idNom    
                                  where aa.idConc = ".$idCon." and year( ff.fechaF ) = ".$dato["ano"]."  
                                     and month( ff.fechaF ) = ".$dato["mes"]." and dd.idEmp = ".$ide." and dd.idNom!=".$id );
                      $conAnt = ''; 
                      if ($datDst['num']>0)
                      { 
                        $conAnt = $datDst['nombre'].'($'.number_format($ded).'- $ '.number_format($datDst['valor']).')'; 
                        $d->modGeneral("update n_nomina_e_d 
                            set  detalle = '".$conAnt."', deducido = deducido - ".$datDst['valor']." 
                                         where id=".$idInom);                                     
                      }                       
                   } 
                }
             }
         } // FIN OTROS AUTOMATICOS POR EMPLEADOS

         // ( REGISTRO DE NOVEDADES MODIFICADAS ) ( n_nomina_nove ) Guardadas en las novedades anteriores
         $datos2 = $g->getRnovedadesN($id, " and d.diasLab = 0");// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              

         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             $idIcal  = $dato["idIcal"];   // Instruccion para calcular o no calcular
             $nombre   = $dato["nombre"];
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------             

             if ( $dato["editado"] == 1) // Editar novedad en nomima_e_d
             {
                   $d->modGeneral("update n_nomina_e_d a 
                                      inner join n_nomina_e b on b.id = a.idInom 
                                   set a.detalle = '(-)".$nombre."' , 
                                     a.devengado = ".$dev.", a.deducido = ".$ded."   
                          where b.idEmp =".$ide." and b.idNom = ".$id." and a.idConc = ".$dato["idCon"] );
             }
             else  
             { 
  //            if ($ide == 4274)
//                  echo 'dd'.$ded  ;  
                $idI = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);

//                $d->modGeneral("update n_nomina_e_d 
  //                                 set detalle = '(-)".$nombre."' 
    //                                  where id = ".$idI );
             }
         } // FIN REGISTRO DE NOVEDADES MODIFICADAS POR OTROS AUTOMATICOS
         // ---------------------------------------------------------------------------------
      } // FIN VALIDACION SOLO NOMINA DE RETRO ACTIVOS 

         // CONCEPTOS CALCULADOS AUTOMATICOS (SALUD , PENSION)---------------------------------------------------------------------------------
         // ---------------------------------------------------------------------------------
         $datos2 = $g->getNominaEcau($id);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             //if ($dato["actVac"]==0)
             //{
             // 
              $sw = 0;

              if ( ($dato["pensionado"]==1) and ( $dato["fondo"]==2 ) )
                   $sw = 1; 

               if ($sw == 0)
               { 
                  $valAnt = 0;
                  if ( $idCon == 174)
                  {  
                     // Validar datos del transporte 
                     $datDst = $d->getGeneral1("select count(aa.id) as num, sum( aa.devengado ) as valor 
                          from n_nomina_e_d aa 
                                    inner join n_conceptos bb on aa.idConc = bb.id 
                                    inner join n_nomina_e dd on dd.id = aa.idInom
                                    inner join a_empleados ee on ee.id = dd.idEmp 
                                    inner join n_nomina ff on ff.id = dd.idNom    
                                  where aa.idConc = 174 and year( ff.fechaF ) = 2017 
                                     and month( ff.fechaF ) = 1 and dd.idEmp = ".$ide );
                     if ($datDst['num']>0)
                         $valAnt = $datDst['valor'];
                  } 


                  if ( ($dato["actVac"]!=1) and ( $idCon == 174) )
                      $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                  if ( $idCon != 174 )
                       $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);                                   

               }
         } // FIN CONCEPTOS AUTOMATICOS         

        // PRESTAMOS 
        $periodoNominaN = $periodoNomina;
        if ( ($calendario == 9) ) // Si es calendario mensual 
             $periodoNominaN = 0;

   //echo 'Prestamo periodo: '.$periodoNominaN.': $<br />';   
        $datos = $g->getPrestamos($id, $periodoNominaN);// Prestamos 

        foreach ($datos as $dato2)
        {                      
           $idEmp = $dato2['idEmp'];            

           if ($dato2['dias'] >= 0)
           {
              // Busqueda de cuotas de prestamos y descargue 
              if ($dato2['vacAct']==0)
                 $datos2 = $g->getCprestamosS($id,$idEmp);
              else // Calculo para el regreso de vacaciones
                 $datos2 = $g->getCprestamosS($id,$idEmp);

              foreach ($datos2 as $dato)
              {
                $iddn    = $dato['id'];  // Id dcumento de novedad
                $idin    = 0;     // Id novedad
                $ide     = $dato['idEmp'];   // Id empleado
                $diasLab = $dato['dias'];    // Dias laborados 

                $diasVac = $dato2["diasVac"];    // Dias vacaciones
                $horas   = $dato["horas"];   // Horas laborados 
                $formula = $dato["formula"]; // Formula
                $tipo    = $dato["tipo"];    // Devengado o Deducido  
                $idCcos  = $dato["idCcos"];  // Centro de costo   
                $idCon   = $dato["idCon"];   // Concepto
                $dev     = 0;     // Devengado
                $ded     = $dato["valor"];     // Deducido         
                //if ( ($diasVac > 0) and ($dato["perAuto"]==2) )
                  //  $ded     = $dato["valor"]*2;     // Deducido         

                $idfor   = $dato["idFor"];   // Id de la formula    
                $diasLabC= 0;   // Dias laborados solo para calculados 
                $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
                $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                $obId    = 1; // 1 para obtener el id insertado
                $nitTer  = $dato['nitTer']; 
                $fechaEje  = '';
                $idProy  = 0;
                // Validar si hay una cuota modificada en la nomina activa
                if ( $dato['idEpres'] > 0 )
                   $ded  = $dato["valorPresN"];// Deducido         

                if ( $dato['perAuto']==2 )
                {  
                  // Buscar valor de prestamos descontado anteriormente en el mismo mes
                  $datDst = $d->getGeneral1("select count(aa.id) as num, sum( aa.deducido ) as valor, bb.nombre   
                          from n_nomina_e_d aa 
                                    inner join n_conceptos bb on aa.idConc = bb.id 
                                    inner join n_nomina_e dd on dd.id = aa.idInom
                                    inner join a_empleados ee on ee.id = dd.idEmp 
                                    inner join n_nomina ff on ff.id = dd.idNom    
                                  where aa.idConc = ".$idCon." and year( ff.fechaF ) = ".$dato["ano"]."  
                                     and month( ff.fechaF ) = ".$dato["mes"]." and dd.idEmp = ".$ide." and dd.idNom!=".$id );
                  $conAnt = ''; 
                  if ($datDst['num']>0)
                  { 
                    $ded = $dato['valCuota'] - $datDst['valor'];                 
                    $conAnt = $datDst['nombre'].'-($ '.number_format($datDst['valor']).')'; 
                  }  
                }
//if ($idEmp==2730)
   //echo 'Prestamo 2: '.$idCpres.': $ '.$ded.'<br />';
                // Llamado de funcion -------------------------------------------------------------------
                // Valida si el prestamo es mensual y el retorno es dentro del periodo no realiza descuento  
                if ( ($dato2['prestMensual']==1 ) and ($dato2['fechaR']<='2017-09-15') )
                {
                  // $ded  = 0;
                }  
                if ( ($dato2['prestMensual']==1 ) and ($dato2['fechaR']>=$dato2['fechaF']) )
                {
                  // $ded  = 0;
                }                  
                if ($ded > 0) 
                {  
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
                   $idInom = (int) $idInom;                   
                   // Colocar saldo del prestamo
                   if ($conAnt!='')
                       $d->modGeneral("update n_nomina_e_d set detalle = '".$conAnt."', nitTer='".$nitTer."' where id=".$idInom);                
                   else 
                       $d->modGeneral("update n_nomina_e_d set 
                        nitTer='".$nitTer."' where id=".$idInom);                                   

                }   
              }  
           }
        }
         // FONDO DE SOLIDARIDAD PARA SEGUNDO PERIODO
        //$periodoNomina = 0
         if ( $periodoNomina == 2)
         {
           $datos2 = $g->getSolidaridad($id);   
           foreach ($datos2 as $dato)
           {             
              if ( ($dato['vacAct']==0) or ($dato['vacAct']==2) ) // En actividad y en retorno 
              {
                $ide     = $dato['idEmp'];   // Id empleado
                $ano     = $dato['ano'];   // Año
                $mes     = $dato['mes'];   // Mes                           
                $dat     = $n->getSolidaridad($ano, $mes, $ide); // Extraer los datos de solidaridad de la funcion
                $iddn    = $dato['id'];  // Id dcumento de novedad             
                $idin    = 0;     // Id novedad
                $diasLab = 0;    // Dias laborados 
                $diasVac = 0;    // Dias vacaciones
                $horas   = 0;   // Horas laborados 
                $formula = ''; // Formula
                $tipo    = 2;    // Devengado o Deducido  
                $idCcos  = $dato["idCcos"];  // Centro de costo   
                $idCon   = 21;   // Concepto
                $dev     = 0;     // Devengado
                $ded     = $dat['valor'];     // Deducido         
                $idfor   = -9;   // Id de la formula    
                $diasLabC= 0;   // Dias laborados solo para calculados 
                $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                $obId    = 1; // 1 para obtener el id insertado
                $fechaEje  = '';
                $idProy  = 0;
                // Llamado de funion -------------------------------------------------------------------
                if ($ded>0)
                {
                   // Buscar valor de concepto pagado anterioremente en el mismo año y
                   // mes
                   $dedAnt = 0; 
                   if ( $calendario != 9 ) // Si es calendario mensual no se tiene en cuenta la busqueda atras de solidaridad para desocntar
                   { 
                      $datAnt  = $g->getFondSolAnt($ano, $mes,$ide, $id, 21);// Concepto de fondo de solidaridad
                      $dedAnt = 0;                
                      if ( $datAnt['deducido'] > 0 )
                      { 
                         $dedAct = $ded;                                          
                         $ded = $ded - $datAnt['deducido'];
                         $dedAnt = $datAnt['deducido'];  
                      }                  
                   }   
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);
                   $idInom = (int) $idInom;                   
                   // Colocar saldo del prestamo
                   if ( $dedAnt > 0 )
                       $d->modGeneral("update n_nomina_e_d 
                     set detalle='FONDO DE SOLIDARIDAD (ANT ".number_format($datAnt['deducido'])."- ACT ".number_format($dedAct)." ) ' where id=".$idInom);                                   

                }
              }
            }// FIN RECORRIDO FONDO DE SOLIDARIDAD               
         } // FIN FONDO DE SOLIDARIDAD
                 
         // VACACIONES FONDO DE SOLIDARIDAD PERIODO DE DESPUES DEL MES ACTUAL (PROC) 
         $datos2 = $g->getVacacionesG($id);// Insertar vacaciones 
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {        
             //if ( $dato['fondo'] > 15) // Validacion momentaena pero debe tener un analisis mas delicado sobre el periodo
             //{
               $iddn    = $dato['id'];  // Id dcumento de novedad
               $idin    = 0;     // Id novedad
               $ide     = $dato['idEmp'];   // Id empleado
               $diasLab = $dato['dias'];    // Dias laborados 
               $horas   = $dato["horas"];   // Horas laborados 
               $diasVac = 0;    // Dias vacaciones
               $formula = $dato["formula"]; // Formula
               $tipo    = $dato["tipo"];    // Devengado o Deducido  
               $idCcos  = $dato["idCcos"];  // Centro de costo   
               $idCon   = $dato["idCon"];   // Concepto
               $dev     = $dato["dev"];     // Devengado
               $ded     = $dato["ded"];     // Deducido
               $idfor   = $dato["idFor"];   // Id de la formula 
               $diasLabC= 0;   // Dias laborados solo para calculados
               $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
               $obId    = 0; // 1 para obtener el id insertado
               // Verificar fondo de solidaridad en vacaciones a partir del segundo periodo 
                $ano     = $dato['ano'];   // Año
                $mes     = $dato['mes'];   // Mes                           
                               
               $dat     = $n->getSolidaridadv($ano, $mes, $id, $ide, $dato['valRestaVaca']); 
               //print_r($dat);
               if ( $dat['valor'] > 0 )
               {
                  $idin    = 0;     // Id novedad
                  $diasLab = 0;    // Dias laborados 
                  $diasVac = 0;    // Dias vacaciones
                  $horas   = 0;   // Horas laborados 
                  $formula = ''; // Formula
                  $tipo    = 2;    // Devengado o Deducido  
                  $idCon   = 21;   // Concepto
                  $dev     = 0;     // Devengado
                  $ded     = $dat['valor'];     // Deducido         
                  $idfor   = 0;   // Id de la formula    
                  $diasLabC= 0;   // Dias laborados solo para calculados 
                  $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                  $obId    = 0; // 1 para obtener el id insertado
                  $fechaEje  = '';
                  $idProy  = 0;
                  // Validar si no tiene fondo de solidaridad calculado anteriormente 
                  // Este caso se presenta cuando en la misma nomina del segundo periodo hay fondo de solidaridad y vacaciones
                  $datVf = $d->getGeneral1("select count(id) as num
                                               from n_nomina_e_d where idConc = 21 and idInom = ".$iddn);
                  
                  if ( $datVf['num'] == 0)  
                  {
                     // Llamado de funion -------------------------------------------------------------------
                     $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);                               
                  }   
               }
           // }// Fin validacion periodo de salida para calcular fondo
             // -------             
         } // FIN SOLIDARIDAD EN VACACIONES QUE TOMAN UN PERIODO FUERA DEL MES ACTUAL
        // EMBARGOS
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargos($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
             $sueldo  = $dato["sueldo"];   // Sueldo 
             // Armar datos de formula 
             $datEmb = $d->getGeneral("select idConc
                                         from n_embargos_dev a 
                                           where a.idEmb = ".$dato['idEmb']);
             $conDd = '';
             foreach ($datEmb as $datE) 
             {
                if ($conDd == '')
                   $conDd = $datE['idConc'];
                else
                   $conDd = $conDd.','.$datE['idConc'];
             }
             $datEmb = $d->getGeneral("select idConc
                                         from n_embargos_ded a 
                                           where a.idEmb = ".$dato['idEmb']);
             foreach ($datEmb as $datE) 
             {
                if ($conDd == '')
                   $conDd = $datE['idConc'];
                else
                   $conDd = $conDd.','.$datE['idConc'];
             }
             if ( $conDd != '')             
             $conDd = " and c.idConc in (".$conDd.")";


             $datDemb = $d->getGeneral1("select sum( c.devengado ) as devengados 
                                        , sum(c.deducido) as deducidos  
                                      from n_nomina a 
                                        inner join n_nomina_e b on b.idNom = a.id
                                        inner join n_nomina_e_d c on c.idInom = b.id 
                                      where b.id = ".$dato['id'].$conDd);
             $devengado = $datDemb['devengados'] ;
             $deducido = $datDemb['deducidos'] ;
             // Ejecutar embargos      
             if ( $dato["formula"] != '')
             {
                $val = $dato["formula"];  
                eval("\$val =$val;");
                $ded   = round( $val, 0 );
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
                echo $ide.' dev:'.$devengado.' ded'.$deducido.' form '.$dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                //if ($idInom>0)
                   //$e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
              }
             
         } // FIN EMBARGOS                                   
         
   // RETENCION DE LA FUENTE
   //if ( ($periodoNomina==$datGen['retePeriodo']) or ($datGen['retePeriodo']==0) )
   //{
//         echo 'PERIDO DE NOMINA '.$periodoNomina;
        // RETENCION DE LA FUENTE
        $r = new Retefuente($this->dbAdapter);
        $datos2 = $g->getRetFuente($id , 0);// 
        foreach ($datos2 as $dato)
        {       
          
           if ( ( $dato['valor'] > $topRetefuente ) or ( $dato['proce'] == 2 )   )
           {                                 
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = 2;    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = 10;   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $ano     = $dato['ano'];   // Año
             $mes     = $dato['mes'];   // Mes                                 
             $ded = 0;
             //if ( $dato['dias']>0)
             $ded = $r->getReteConc($iddn, $ide); // Procedimiento para guardar la retencion
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             if ( $ded>0) 
             {
                // Buscar valor de concepto pagado anterioremente
                // en el mismo año y mes solo para procedimiento 1
                $dedAnt = 0;                
                if ( ( $dato['proce'] )==0 or ($dato['proce']==1) )
                { 
                   $datAnt  = $g->getFondSolAnt($ano, $mes,$ide, $id, 10);// RETEFUENTE
                   
                   if ( $datAnt['deducido'] > 0 )
                   { 
                      $dedAct = $ded;                     
                      $ded = $ded - $datAnt['deducido'];
                      $dedAnt = $datAnt['deducido'];  
                   }                  
                }
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje, $idProy);              
                   $idInom = (int) $idInom;                   

                   $datPorRet = $d->getGeneral1('select a.porcentaje , a.uvtActual 
                                   from n_nomina_e_rete a 
                                      where a.idNom = '.$id.' and a.idEmp = '.$ide);
                   $d->modGeneral("update n_nomina_e_d 
                          set detalle='RETENCION EN LA FUENTE (".number_format($datPorRet['porcentaje'], 2)." %)' where id=".$idInom);
                  
                   // Colocar saldo anterior descontado en vacacione su otros 
                   if ( $dedAnt > 0 )
                       $d->modGeneral("update n_nomina_e_d 
                          set detalle='RETENCION EN LA FUENTE (ANT ".number_format($datAnt['deducido'])."- ACT ".number_format($dedAct)." ) ' where id=".$idInom);

              } // Fin validacion valor de deduccion                
            }// Fin validacion tope de retencion en la fuente           
         } // FIN RETENCION DE LA FUENTE                                   
   // }// Fin validacion periodos de retencion    

        // Envio de datos de bancos para pagar

         
        // Numero de empleados
        $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
        $dato=$d->getGeneral1($con2);                                                  

        // Cambiar estado de nomina
        $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
        $d->modGeneral($con2);                                         
        
        $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado 
        
        
       }// Sw e prueba ojo
        $e = 'Nomina generada de forma correcta';
        $connection->commit();
     }// Fin try casth   
     catch (\Exception $e) {
  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
       $connection->rollback();
           echo $e;
  }
  
  /* Other error handling */
     }// FIN TRANSACCION
        
                 
      }        
      $valores = array( "e" => $e);
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); // Layout del login
      return $view;              
      
    } // Fin generacion nomina


    // GENERACION DE CESANTIAS -------------------------------------
    public function listcAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $c=new Cesantias($this->dbAdapter);
         // Buscar id de grupo
         
         $datos = $d->getPerNomina($id); // Periodo de nomina
         $idTnom = $datos['idTnom']; // 3= Cesantias 7 -- Intereses 
         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaIcesantias = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];    
         $idIcal = $datos['idIcal'];  
         //$diasCesantias = $datos['diasNom'];  

         $mesI   = $datos['mesIp'];   
         $fechaF = $datos['fechaF'];                                   
         $mesF   = $datos['mesF'];                                            
        // Calculo para las censantias por los empleados del grupo
        
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            $dat = $d->getGeneral("select a.idEmp, a.id, b.fechaI, b.fechaF , b.idGrupo, c.promPrimas   
                                       from n_nomina_e a 
                                          inner join n_nomina b on b.id = a.idNom  
                                          inner join c_general c on c.id = 1 
                                         where a.idNom=".$id);
            foreach ($dat as $datoC)
            {              
                $idInom = $datoC['id'];              
                $idEmp = $datoC['idEmp']; 
                $fechaI = $datoC['fechaI'];
                $fechaF = $datoC['fechaF'];
                $fechaIc = $datoC['fechaI'];
                $idGrupo = $datoC['idGrupo'];
                $promPrimas = $datoC['promPrimas'];

                $datCon = $n->getDiasContrato( $idEmp, $fechaI, $fechaF );
                $fechaAnAnt =  $datoC['fechaI'];                             
                $mesIc = '01';
                $mesF = '12';
                $dias = $datCon['diasContrato'];
                $diasAusNomRem = $datCon['diasAusNomRem'];
                $diasAusRem = $datCon['diasAusRem'];
                $tipo = 1;
                $fechaIngre = $datCon['fechaIngreso'];                
                $diasPromedio = $datCon['diasLabor'];
                $regimen =  $datCon['regimen'];
                $datFecI = $d->getGeneral1("select 
                                       case when '".$fechaIngre."' > '".$fechaI."' then '".$fechaIngre."' else '".$fechaI."' end as fecha  ");
                $fechaIc = $datFecI['fecha'];
                if ( ($dias > 360 ) and ($regimen==0)   )
                     $dias = 360;

                   
                // Si no usa el promedio de primas , deben ser igual
                // los dias cesantias a los dias promedio 
                // Solo para casos de sueldo sumado  
                if ( $promPrimas == 0 )
                {
                   if ( $regimen==1 )
                      $diasPromedio = 360;                                    
                   else
                      $diasPromedio = $dias; 
                }else{
                    if ($diasPromedio>360) 
                        $diasPromedio=360;                                    
                }  
                if ( $diasAusNomRem == 0 )
                {
                    if ($diasPromedio>$dias)
                        $diasPromedio=$dias; 
                }      
                if ( $diasAusNomRem > 0 )
                {
                    if ($diasPromedio<$dias) // Restar dias ausentismos 
                        $dias = $diasPromedio;                                        
                    if ($diasPromedio>$dias) // Esto proque antes no habia ause
                        $dias = $diasPromedio; 
                }
                if ( $diasAusRem > 0 )
                     $diasPromedio = $diasPromedio + $diasAusRem;

                $d->modGeneral("update n_nomina_e 
                                   set diasCesantias=".$dias.",
                                       diasPromCesa = ".$diasPromedio." ,
                                       ausCesantias = ".$diasAusNomRem."
                                      where id = ".$idInom);


                $n->getCesantiasInt($fechaF, $fechaAnAnt ,$idEmp,  $idGrupo, $id, $fechaIc, $mesIc, $mesF, $dias, $tipo,$fechaIngre, $diasPromedio);                  
                     
            }

        // EMBARGOS PRIMAS PARA CESANTIAS 
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargosPrimas($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
             // Ejecutar embargos      
             if ( $dato["formula"] != '')
             {
                $con = '"'.$dato["formula"].'"';  
                eval("\$con =$con;");
         
                $datVal = $d->getGeneral1($con);      
                $ded   = $datVal['valor'];
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
                //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                //if ($idInom>0)
                   //$e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
              }
             
         } // FIN EMBARGOS                                   

            // Numero de empleados
            $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
            $dato=$d->getGeneral1($con2);                                                  

            // Cambiar estado de nomina
            $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
            $d->modGeneral($con2);                                         
        
            $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          

           $connection->commit();
        }// Fin try casth   
        catch (\Exception $e) {
     if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
     }
  
   /* Other error handling */
         }// FIN TRANSACCION        
         $view = new ViewModel();        
         $this->layout('layout/blanco'); // Layout del login
         return $view;                    
       }
    }

    // GENERACION INTERESES DE CESANTIAS -------------------------------------
    public function listicAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $c=new Cesantias($this->dbAdapter);
         // Buscar id de grupo
         
         $datos = $d->getPerNomina($id); // Periodo de nomina

         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaIcesantias = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];    
         $idIcal = $datos['idIcal'];  
         //$diasCesantias = $datos['diasNom'];  

         $mesI   = $datos['mesIp'];   
         $fechaF = $datos['fechaF'];                                   
         $mesF   = $datos['mesF'];                                            
        // Calculo para las censantias por los empleados del grupo
        $datos = $g->getDiasCesa($idg,$id,$fechaI); 
        //print_r($datos);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
                
            foreach ($datos as $datoC)
            {              
                $idEmp = $datoC['idEmp'];
                $diasCesantias = $datoC['diasCes'];  
                if ($diasCesantias>365)
                    $diasCesantias=365;
                $mesIc = $mesI   ;
                if ($diasCesantias<365)
                    $mesIc = $datoC['mesIngr'];

                // Dias habiles
                $datDcal = $n->getDiasCalen( $mesIc, $mesF, $fechaF ); // Funcion apra 
                
                if ( ($datDcal['diasS']!=0) or ($datDcal['diasR']!=0) )
                {
                   $diasCesantias = $diasCesantias - $datDcal['diasR'];   
                   $diasCesantias = $diasCesantias + $datDcal['diasS'];                   
                }                                      
                // Buscar ausentismos
                $datAus = $d->getAusentismosDias($idEmp, $fechaI, $fechaF );
                $diasAus = 0;
                if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
                {
                    $diasCesantias = $diasCesantias - $datAus['dias'];  
                    $diasAus = $datAus['dias'];
                }
                // Buscar anticipos de cesantias 
                $datAnt = $d->getAntCesantias($id, $idEmp );
                $valAnt = 0;
                $valInt = 0;
                if ($datAnt['valor']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
                {
                    $valAnt = $datAnt['valor'];
                    $valInt = $datAnt['interes'];
                    echo 'inte ant'.$valInt;
                }

                // Verificar fecha del aumento de sueldo del empleados
                $datFec = $d->getAsalariaF($idEmp, $fechaF); 
                $tipC = 0;
                if ( ( $datFec['meses']>3 ) or ( $datFec['numAum'] == 0 ) ) // Si el ultimo aumento es mayor a 3 meses o no ha habido ningun aumenro no se incluye ne calculo del promedio 
                {
                   // Si el ultimo aumento es mayor a 3 meses o no ha habido ningun aumenro no se incluye ne calculo del promedio 
                   echo 'Dias '.$diasCesantias.'-'.$fechaIcesantias.'- '.$fechaF.'<br />';
                   $datos2 = $g->getCesantias($idEmp, $fechaIcesantias , $fechaF, $diasCesantias);                  
                  // print_r($datos2);
                   $tipC = 1;
                }else{ // Sino se llama la funcion para tenerlo en cuenta en el promedio esto aplica a salarios variables
                   $datos2 = $g->getCesantiasS($idEmp, $fechaIcesantias, $fechaF, $diasCesantias);  
                   $tipC = 2;
                }                   
                // Calcular las cesantias
                foreach ($datos2 as $dato)
                {  
                   $valorCesantias = round( $dato["valorCesantias"], 2); // Buscar subdisio de transporte
                   //$base = $base + $datoC['subTransporte']; // Base mas subsidio de transporte 
                   echo '----------------------- Cesantias <br />';                                               
                   echo 'base '.$valorCesantias.'<br /> ';
                   echo 'dias cesantias '.$diasCesantias.'<br /> ';                                                                                  
                   $id      = $datoC['idNom'];  // Id dcumento de novedad 
                   $iddn    = $datoC['id'];  // Id dcumento de novedad
                   $d->modGeneral("update n_nomina_e set 
                        diasCesantias=".$diasCesantias.",ausCesantias=".$diasAus.", 
                                 baseCesantias=".$valorCesantias." where id =".$iddn);                   
                   $idin    = 0;     // Id novedad
                   $ide     = $idEmp;   // Id empleado
                   //$diasLab = $datoC['diasCes'];    // Dias laborados 
                   $diasLab = $diasCesantias;    // Dias laborados                    
                   $horas   = 0;   // Horas laborados 
                   $diasVac = 0;    // Dias vacaciones
                   $formula = ''; // Formula
                   $tipo    = $datoC["tipo"];    // Devengado o Deducido  
                   $idCcos  = $datoC["idCcos"];  // Centro de costo   
                   $idCon   = 213;   // Concepto
                   //$idCon   = $datoC["idCon"];   // Concepto
                   $dev     = $valorCesantias;   // Devengado
                   $ded     = 0;     // Deducido         
                   $idfor   = '';   // Id de la formula    
                   $diasLabC= 0;   // Dias laborados solo para calculados 
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 1; // 1 para obtener el id insertado
                   $fechaEje  = 0;
                   $idProy  = 0;
                   //echo $dev.'<br />';
                   // Llamado de funion -------------------------------------------------------------------
                  // $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId, $fechaEje, $idProy );              
                  //s $idInom = (int) $idInom;                   
                   // INTERESE DE CENSATIAS 
                   $dev     = ( ( $valorCesantias * ( 12/100 ) )/360 ) * $diasCesantias; // Devengado
                   $idCon   = 195; //
                   $obId    = 1; // 1 para obtener el id insertado
                   echo '----------- Ineteres de cesantias <br />';                                               
                   echo 'Valor '.$diasCesantias.'<br /> ';                                                                                  
                   echo '= '.number_format($dev).'<br /><hr /> ';                                                                                                                        
                   if ($valorCesantias > 0)
                   {
                       // Llamado de funion -------------------------------------------------------------------
                       $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId, $fechaEje, $idProy);                             
                       $idInom = (int) $idInom;                   
                       //if ($valInt>0)
                       //{
                          $d->modGeneral("update n_nomina_e_d set 
                              detalle='INTERESES DE CESANTIAS (".$diasCesantias." dias) Cesantias:(".number_format($valorCesantias).")' where id =".$idInom);
                       //}
                       // REGISTRAR ANTICIPOS DE CESANTIAS    
                       if ($valInt>0)
                       {                        
                          $d->modGeneral("update n_nomina_e_d set 
                              devengado = devengado - ".$valInt." where id =".$idInom);

                           $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,1,$conVac,$obId, $fechaEje, $idProy);                             
                           $idInom = (int) $idInom;                                           
                           $d->modGeneral("update n_nomina_e_d set 
                              detalle='*INT. CES. 31 DIC: ".number_format($dev)." - ANT(".number_format($valInt).") = ".number_format($dev-$valInt).") ',
                                devengado = 0 where id =".$idInom);
                       }   
                       // REGISTRO LIBRO DE CESANTIAS                   
                      // $c->actRegistro($ide, 213, 195, $fechaI, $fechaF, $diasLab, 0, $base, $valor, $dev , $idInom , $id);
                   }
                }
            }

        // EMBARGOS PRIMAS PARA CESANTIAS 
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargosPrimas($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
             // Ejecutar embargos      
             if ( $dato["formula"] != '')
             {
                $con = '"'.$dato["formula"].'"';  
                eval("\$con =$con;");
         
                $datVal = $d->getGeneral1($con);      
                $ded   = $datVal['valor'];
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
                //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                if ($idInom>0)
                   $e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
              }
             
         } // FIN EMBARGOS                                   

            // Numero de empleados
            $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
            $dato=$d->getGeneral1($con2);                                                  

            // Cambiar estado de nomina
            $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
            $d->modGeneral($con2);                                         
        
            $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          

           $connection->commit();
        }// Fin try casth   
        catch (\Exception $e) {
     if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
     }
  
   /* Other error handling */
         }// FIN TRANSACCION        
         $view = new ViewModel();        
         $this->layout('layout/blanco'); // Layout del login
         return $view;                    
       }
    }// FIN INTERESES DE CESANTIAS

    // GENERACION DE PRIMAS -------------------------------------
    public function listpmAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $c=new Primas($this->dbAdapter);
         // Buscar id de grupo
         $datGen = $d->getConfiguraG(''); // CONFIGURACIONES GENERALES

         $datos = $d->getPerNomina($id); // Periodo de nomina

         $idg    = $datos['idGrupo'];         
         $fechaI = $datos['fechaI'];         
         $fechaF = $datos['fechaF'];         
         $idIcal = $datos['idIcal'];                  
         // Calculo para las primas por los empleados del grupo
         $datos = $d->getGeneral("Select a.id, a.idEmp, b.idCcos,"
                                . "  case when b.fecIng > '".$fechaI."' then round( ( ( DATEDIFF( '".$fechaF."' , b.fecIng ) + 1 ) * 15 ) / 180,2 )
                     else 15 end  as diasPrima, b.fecIng 
                                  from n_nomina_e a 
                                  inner join a_empleados b on b.id = a.idEmp 
                                  where a.idNom =".$id); 
                  
         // INICIO DE TRANSACCIONES
         $connection = null;
         try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
           
         // ( REGISTRO DE NOVEDADES ) ( n_novedades ) e
         $datos2 = $g->getRnovedades($id,$idIcal);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             $saldoPact = 0;
             if ( $dato["valCuota"] > 0 )
             {                
                if ($dev>0)
                {
                   $saldoPact = $dev-$dato["pagado"];                  
                   $dev = $dato["valCuota"]; 
                }
                else
                {
                   $saldoPact = $ded-$dato["pagado"];                  
                   $ded = $dato["valCuota"]; 
                }                  
             }
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   
             // GUARDAR REGISTRO PAGO PRIMA DE ANTIGUEDAD
             $d->modGeneral("update n_nomina_e_d 
                set idProy=".$idProy.", saldoPact=".$saldoPact.",
                 fechaEje ='".$dato['fechaEje']."' , idInov=".$dato['idInov']." where id=".$idInom);
            } // FIN REGISTRO DE NOVEDADES                                     


         // CONCEPTOS EXTRALEGALES
         $datos2 = $g->getConceptosExtra($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
          } // FIN CONCEPTOS EXTRALEGALES

        // PRESTAMOS 
          $periodoNomina = 0;
        $datos = $g->getPrestamos($id, $periodoNomina);// Prestamos 
        foreach ($datos as $dato2)
        {                      
           $idEmp = $dato2['idEmp'];            
           if ($dato2['dias'] >= 0){
              // Busqueda de cuotas de prestamos y descargue 
              $datos2 = $g->getCprestamosS($id,$idEmp);

              foreach ($datos2 as $dato)
              {

                $iddn    = $dato['id'];  // Id dcumento de novedad
                $idin    = 0;     // Id novedad
                $ide     = $dato['idEmp'];   // Id empleado
                $diasLab = $dato['dias'];    // Dias laborados 

                $diasVac = 0;    // Dias vacaciones
                $horas   = $dato["horas"];   // Horas laborados 
                $formula = $dato["formula"]; // Formula
                $tipo    = $dato["tipo"];    // Devengado o Deducido  
                $idCcos  = $dato["idCcos"];  // Centro de costo   
                $idCon   = $dato["idCon"];   // Concepto
                $dev     = 0;     // Devengado
                $ded     = $dato["valor"];     // Deducido         
                $idfor   = $dato["idFor"];   // Id de la formula    
                $diasLabC= 0;   // Dias laborados solo para calculados 
                $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
                $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                $obId    = 1; // 1 para obtener el id insertado
                $nitTer  = $dato['nitTer']; 
                $fechaEje  = '';
                $idProy  = 0;
                // Validar si hay una cuota modificada en la nomina activa
                if ( $dato['valorPresN'] > 0 )
                   $ded  = $dato["valorPresN"];// Deducido         
//if ($idEmp==179)
 //  echo 'Prestamo : '.$idCpres.': $ '.$ded.'<br />';
                // Llamado de funcion -------------------------------------------------------------------
               if ($ded>0)
               {   
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
                $idInom = (int) $idInom;                   
                // Colocar saldo del prestamo
                $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
               } 
              }  
           }
        }

        // PRESTAMOS PARA DESCONTAR EN Primas
        $datos = $g->getPrestamosPrimas($id);// Prestamos 
        //print_r($datos);
        foreach ($datos as $dato)
        {                      
           $iddn    = $dato['id'];  // Id dcumento de novedad
           $idin    = 0;     // Id novedad
           $ide     = $dato['idEmp'];   // Id empleado
           $diasLab = $dato['dias'];    // Dias laborados 

           $diasVac = 0;    // Dias vacaciones
           $horas   = $dato["horas"];   // Horas laborados 
           $formula = $dato["formula"]; // Formula
           $tipo    = $dato["tipo"];    // Devengado o Deducido  
           $idCcos  = $dato["idCcos"];  // Centro de costo   
           $idCon   = $dato["idCon"];   // Concepto
           $dev     = 0;     // Devengado
           $ded     = $dato["valor"];     // Deducido         
           $idfor   = $dato["idFor"];   // Id de la formula    
           $diasLabC= 0;   // Dias laborados solo para calculados 
           $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
           $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
           $obId    = 1; // 1 para obtener el id insertado
           $nitTer  = $dato['nitTer']; 
           $fechaEje  = '';
           $idProy  = 0;
//if ($ide==136)
    //echo $idCpres.' -'.$ded.'prestamos <br />';
           // Llamado de funcion -------------------------------------------------------------------
           $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
           $idInom = (int) $idInom;                   

           // Colocar saldo del prestamo
           $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
         }  

        // DESCUENTOS SOLO PRIMAS 
        $datos = $g->getPrestamosPrimasNuev($id);// Prestamos 
        //print_r($datos);
        foreach ($datos as $dato)
        {                      
           $iddn    = $dato['id'];  // Id dcumento de novedad
           $idin    = 0;     // Id novedad
           $ide     = $dato['idEmp'];   // Id empleado
           $diasLab = $dato['dias'];    // Dias laborados 

           $diasVac = 0;    // Dias vacaciones
           $horas   = $dato["horas"];   // Horas laborados 
           $formula = $dato["formula"]; // Formula
           $tipo    = $dato["tipo"];    // Devengado o Deducido  
           $idCcos  = $dato["idCcos"];  // Centro de costo   
           $idCon   = $dato["idCon"];   // Concepto
           $dev     = 0;     // Devengado
           $ded     = $dato["valor"];     // Deducido         
           $idfor   = $dato["idFor"];   // Id de la formula    
           $diasLabC= 0;   // Dias laborados solo para calculados 
           $idCpres = $dato["idPres"];   // Id de la cuota del prestamo
           $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
           $obId    = 1; // 1 para obtener el id insertado
           $nitTer  = $dato['nitTer']; 
           $fechaEje  = '';
           $idProy  = 0;
//if ($ide==136)
    //echo $idCpres.' -'.$ded.'prestamos <br />';
           // Llamado de funcion -------------------------------------------------------------------
           $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 4,$dev,$ded,$idfor,$diasLabC,$idCpres,1,$conVac,$obId,$fechaEje,$idProy);                                           
           $idInom = (int) $idInom;                   

           // Colocar saldo del prestamo
           $d->modGeneral("update n_nomina_e_d set nitTer='".$nitTer."' where id=".$idInom);                
         }// Descuentos solo primas   

          // LIQUIDACION PRIMAS
            $c = new Primas($this->dbAdapter);
            $fechaIprimas = $fechaI; // La fecha real de consulta esta en calendario   
            // Calculo para las primas por los empleados del grupo
            $dat = $d->getFechasPrimas($id); 
            $fechaIprimasCal = $dat['fechaI']; // Dias inicial Calendario 
            $fechaFprimasCal = $dat['fechaF']; // Dias finCalendario 
            $mesI = $dat['mesI'];
            $mesF = $dat['fechaF'];
            $datos = $g->getPrimasFinal( $fechaIprimasCal, $fechaFprimasCal, $id);
            foreach ($datos as $dato)
            {      
              $iddn    = $dato['id'];  // Id dcumento de novedad
              $idin    = 0;     // Id novedad
              $ide     = $dato['idEmp'];   // Id empleado
              $diasLab = 0;    // Dias laborados 
              $horas   = 0;   // Horas laborados 
              $diasVac = 0;    // Dias vacaciones
              $formula = ''; // Formula
              $tipo    = 1;    // Devengado o Deducido  
              $idCcos  = $dato["idCcos"];  // Centro de costo   
              $idCon   = $datGen['idPrima'];   // Concepto
              $diasPrima = $dato['diasPrima'];
              $diasLabor = $dato['diasLabor'];
if ( $diasPrima < 0 ) // Caso para esos contratos no actualiazdos de años anteriores
     $diasPrima = 180;         

if ( $diasLabor > 180 )           
     $diasLabor = 180;

     if ( $ide == 1136 )
     {
       //      echo 'dias primas '.$diasPrima.'<br />';
         //     echo 'Dias laborados '.$diasLabor.'<br />';
      }        
 
              // Buscar ausentismos no remunerado (SEGUN INTERPRETACION DE LA LEY NO SE DESC AUSENTISMOS)
              // $datAus = $d->getAusentismosDias($ide, $fechaIprimasCal, $fechaFprimasCal);
              $diasAus=0;
              // if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
              // {
                 // $diasPrima = $diasPrima - $datAus['dias'];  
                 // $diasAus=$datAus['dias'];
              // }  
              //echo 'empleado : '.$id.'<br />';
              $datPr   = $g->getDiasPrima($ide,$fechaIprimas,$fechaF,$diasPrima,$diasLabor,$id); // Valor de prima a pagar
              //if ($ide==447)
//echo 'Promedio mes EMPLEADO '.$datPr["promedioMes"].'<br />';
              $diasLab = 0;    // Dias laborados 
              $dev     = $datPr["promedioMes"];     // Devengado  Dias trabajados en el semestre
              // Buscar pago de primas en nomian anterior
              $datDev = $d->getGeneral1("select a.devengado
                                    from n_nomina_e_d a 
                                        inner join n_nomina_e b on b.id = a.idInom 
                          where a.idNom=44 and a.idConc = 214 and b.idEmp = ".$ide);
              $devAnt = $dev;
              if ($datDev['devengado']>0)
                  $dev = $datDev['devengado']-$dev;

              $ded     = 0;     // Deducido         
              $idfor   = -99;   // Id de la formula    
              $diasLabC= 0;   // Dias laborados solo para calculados 
              $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
              $obId    = 1; // 1 para obtener el id insertado
              $fechaEje  = '';
              $idProy  = 0;
              // Llamado de funion -------------------------------------------------------------------
              $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              $idInom = (int) $idInom;                   
                  if ($datDev['devengado']>0)
                  {
                     $d->modGeneral("update n_nomina_e_d set 
                                      detalle = 'PRIMA DE SERVICIO (Ant : ".number_format($datDev['devengado'],0)." - ".number_format($devAnt,0).")'  
                                    where id =".$idInom);
                  }                
              // LIBRO DE PRIMAS
              if ($dev > 0)
              {
                  // REGISTRO LIBRO DE PRIMAS
                  //$c->actRegistro($ide, $fechaI, $fechaF, $dev, $idInom , $id);
                  $subTrans = $datPr["subTransporte"];

                  $d->modGeneral("update n_nomina_e set subTransporte=".$subTrans.",
                                    diasPrimas=".$diasPrima.",dias=".$diasPrima.", diasAusPrimas=".$diasAus.",  
                                    promPrimas=".$datPr["promedioMes"]." where id =".$iddn);
              }                 
              // DESCUENTO ESPECIAL CAJAMAG 
              if ( ( $dato["idTau2"]==3 ) or ($dato["idTau3"]==3)  or ($dato["idTau4"]==3) )
              {
                  // Llamado de funion -------------------------------------------------------------------
                  $diasLab = 0;
                  $dev = 0;
                  $idCon   = 62;   // Concepto
                  if ( $dato['sueldo'] > 1092310)
                     $ded = 50000;
                  else
                     $ded = 30000;

                  $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);               
              }
          }
         // CONCEPTOS HIJOS 
         $datos2 = $g->getNominaConH($id);
         //print_r($datos2);
         foreach ($datos2 as $dato)
         {             
           if ($dato['Temp']>0)
           {
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId, $fechaEje, $idProy);              
           }
         } // FIN CONCEPTOS AUTOMATICOS POR PERIODO                           

         $datos2 = $g->getRnovedadesN($id, "");// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              

         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             $idIcal  = $dato["idIcal"];   // Instruccion para calcular o no calcular
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------             

             if ( $dato["editado"] == 1) // Editar novedad en nomima_e_d
             {
                   $d->modGeneral("update n_nomina_e_d a 
                                      inner join n_nomina_e b on b.id = a.idInom 
                                   set a.detalle = '(-)".$nombre."' , 
                                     a.devengado = ".$dev.", a.deducido = ".$ded."   
                          where b.idEmp =".$ide." and b.idNom = ".$id." and a.idConc = ".$dato["idCon"] );
             }
             else  
             { 
                $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             }
         } // FIN REGISTRO DE NOVEDADES MODIFICADAS POR OTROS AUTOMATICOS


         // CONCEPTOS AUTOMATICOS 
         $datos2 = $g->getNominaEcau($id);
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0;     // Deducido         
             $idfor   = $dato["idFor"];   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 0; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             $sw = 0;
             if ( $dato["idTempCon"] > 0 )
             {
                //if ( ($dato["idTempCon"]) != ($dato["tipEmp"]) )  
                //   $sw = 1;
             }     
             if ($sw==0)
             {
                //$sw = 0;
               if ( ($dato["idFpen"]==1) and ( $dato["fondo"]==2 ) ) // Si el concepto de pension no aplica no debe generarlo
                   $sw = 1;    
               if ($sw == 0)
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
              }
         } // FIN CONCEPTOS AUTOMATICOS         

        // EMBARGOS PRIMAS 
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargosPrimas($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
             // Ejecutar embargos      
             if ( $dato["formula"] != '')
             {
                $con = '"'.$dato["formula"].'"';  
                eval("\$con =$con;");
         
                $datVal = $d->getGeneral1($con);      
                $ded   = $datVal['valor'];
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
                //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                if ($idInom>0)
                   $e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
              }
             
         } // FIN EMBARGOS                                   

        // RETENCION DE LA FUENTE
        $r = new Retefuente($this->dbAdapter);
        $datos2 = $g->getRetFuente($id, 0);// 
        foreach ($datos2 as $dato)
        {          
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = 2;    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = 10;   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $ano     = $dato['ano'];   // Año
             $mes     = $dato['mes'];   // Mes                                 
             $ded = 0;
             //if ( $dato['dias']>0)
             $ded = $r->getReteConc($iddn, $ide); // Procedimiento para guardar la retencion
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             if ( $ded>0) 
             {
                // Buscar valor de concepto pagado anterioremente en el mismo año y mes 
                $datAnt  = $g->getFondSolAnt($ano, $mes,$ide, $id, 10);
                $dedAnt = 0;                
                if ( $datAnt['deducido'] > 0 )
                { 
                   // $dedAct = $ded;                                                           
                   // $ded = $ded - $datAnt['deducido'];
                   // $dedAnt = $datAnt['deducido'];  
                }                  

                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje, $idProy);              
                   //$idInom = (int) $idInom;                   
                   // Colocar saldo del prestamo
                   //if ( $dedAnt > 0 )
                   //    $d->modGeneral("update n_nomina_e_d 
                   //  set detalle='RETENCION EN LA FUENTE (ANT //".number_format($datAnt['deducido'])."- ACT //".number_format($dedAct)." ) ' where //id=".$idInom);                                   
              }                
                      
         } // FIN RETENCION DE LA FUENTE                                   


          // Numero de empleados
          $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
          $dato=$d->getGeneral1($con2);                                                  
          // Cambiar estado de nomina
          $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
          $d->modGeneral($con2);                                         
         
          $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          
          $connection->commit();
       }// Fin try casth   
       catch (\Exception $e) 
       {
          if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
          {
              $connection->rollback();
              echo $e;
          } 
          /* Other error handling */
       }// FIN TRANSACCION        
       $view = new ViewModel();        
       $this->layout('layout/blanco'); // Layout del login
       return $view;                    
      }
   }


   // GENERACION LIQUIDACION FINAL -------------------------------------
   public function listlqAction()
   {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  
         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $n = new NominaFunc($this->dbAdapter);
         $e = $n->getLiqFinal( $id );                      
      }        
      $valores = array( "e" => $e);
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); // Layout del login
      return $view;              
      
    } // FIN LIQUIDACION FINAL 
        
    
   // Regenerar nomina ********************************************************************************************
   public function listrgAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Gnomina($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter); 
            $c=new Cesantias($this->dbAdapter); 
            // Consultar nomina
            $datos = $d->getGeneral1("Select idTnom, estado, idGrupo from n_nomina where id=".$id); 
            $idTnom = $datos['idTnom'];            
            $idGrupo = $datos['idGrupo'];            
            // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
               $connection = $this->dbAdapter->getDriver()->getConnection();
               $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores               
               $d->modGeneral("delete from n_pg_embargos where idNom=".$id);                
               $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id);                
               $d->modGeneral("delete from n_primas where idNom=".$id);                
               $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id);
                              
               $datos = $d->getGeneral1("select id from n_nomina_e_d where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               $d->modGeneral("alter table n_nomina_e_d auto_increment = ".$datos['id'] ); 

               $d->modGeneral("update n_nomina set numEmp=0, estado=0 where id=".$id);               
                                            
               $connection->commit();
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);
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

    public function listgAction()
    {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);             

      $dato = $d->getGeneral1("select case when a.idTnomL > 0 
                                  then b.tipo else b.tipo
                                     end as tipo, b.id 
                                from n_nomina a 
                                  inner join n_tip_nom b on b.id=a.idTnom 
                                  left join n_tip_nom c on c.id = a.idTnomL 
                                    where a.id=".$id); // Busco el tipo de nomina para generarla (General, Censatias, Primas, Vacaciones)
            
      $valores=array
      (
        "form"    => $form,
        'url'     => $this->getRequest()->getBaseUrl(),          
        "titulo"  => $this->tlis,
        "datEmp"  => $d->getGeneral("select a.id, b.CedEmp , b.nombre 
                                       ,b.apellido  
                                     from n_nomina_e a 
                                        inner join a_empleados b on b.id= a.idEmp 
                                     where a.idNom = ".$id),
        "datos"   => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, e.fechaI, e.fechaF                        
                       from a_empleados a inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=".$id) ,
        "tipo"    => $dato['tipo'], // Tipo de calendario para esta nomina 
        "lin"     => $this->lin
      );                        
      return new ViewModel($valores);
    }    
    
    public function listg4Action()// Generacion de pruebas 
    {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);             

      $dato = $d->getGeneral1("select b.tipo from n_nomina a 
            inner join n_tip_nom b on b.id=a.idTnom where a.id=".$id); // Busco el tipo de nomina para generarla (General, Censatias, Primas, Vacaciones)
            
      $valores=array
      (
        "form"    => $form,
        'url'     => $this->getRequest()->getBaseUrl(),          
        "titulo"  => $this->tlis,
        "datos"   => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, b.incluido, e.fechaI, e.fechaF                        
                       from a_empleados a inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=".$id) ,
        "tipo"    => $dato['tipo'],
        "lin"     => $this->lin
      );                        
      return new ViewModel($valores);
    }    
    
    // Validar que la nomina no este generada ********************************************************************************************
    public function listvpAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);         
           $datos = $d->getGeneral1("select estado from n_nomina where id=".$id);
           $valido = '';
           if ($datos['estado']==1)
               $valido = 1;
           $valores=array
           (
            "valido"  =>  $valido,
           );                
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;           
        }
      }
    } // Fin listar registros            

   // Mostrar periodos de acuerdo al tipo de nomina *********************************************************************************************
   public function listtnAction() 
   { 
      $form = new Formulario("form");   
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);
           // Grupo de nomina
           $arreglo='';
           $datos = $d->getEmp(' and idGrup = '.$data->id); 
           foreach ($datos as $dat){
               $idc=$dat['id'];$nom=$dat['nombre'];
               $arreglo[$idc]= $nom;
            }              
           $form->get("idEmpM")->setValueOptions($arreglo);                         
        }
      }
      $valores = array("form" => $form );      
      $view = new ViewModel($valores);              
      $this->layout('layout/blancoB'); // Layout del login
      return $view;                 
   }
   
   // ELIMINAR NOMINA ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Gnomina($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter); 
            $c=new Cesantias($this->dbAdapter); 
            // Consultar nomina
            $datos = $d->getGeneral1("Select idTnom, estado, idGrupo from n_nomina where id=".$id); 
            $idTnom = $datos['idTnom'];            
            $idGrupo = $datos['idGrupo'];            
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
               $connection = $this->dbAdapter->getDriver()->getConnection();
               $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores      
           $datos = $d->getGeneral1("select id from n_nomina_c where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_c where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_c auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_h where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_h where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_e_h auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_retro where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_retro_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro_i where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_rete where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_rete where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_e_rete auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_embargos where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_embargos where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_embargos auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_primas_ant where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_primas_ant auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_primas where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_primas where idNom=".$id);
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_primas auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_cesantias where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_cesantias auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_a where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_a where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_a auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d_integrar where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e_d_integrar auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_nomina_e_d auto_increment = ".$datos['id'] ); 


               $datos = $d->getGeneral1("select id from n_nomina_e where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e auto_increment = ".$datos['id'] ); 

               $datos = $d->modGeneral("delete from n_nomina where id=".$id); 
               $d->modGeneral("alter table n_nomina auto_increment = ".$id);
               $datos = $d->modGeneral("update n_grupos set activa=0 where id=".$idGrupo);// Activar grupo de nuevo               
    
                // ---Activar liquidaciones  
              $d->modGeneral("update n_nomina_l set idNom = 0   
                                        where idNom = ".$id);          

               $u->delRegistro($id);
               
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
   }// Fin datos eliminar nomina


   // NOMINA A EXCEL 
   public function listexcelAction() 
   { 
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);  
        // CONSULTA DE DEVENGADOS CON CODIGOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 and c.codigo != ''
                                   and c.tipo=1 and a.estado = 1 
                                   order by c.codigo");
        $campoDevengados = '';
        foreach ($datos as $dat) 
        {
          $campoDevengados .= 'sum(if( c.idConc = '.$dat['id'].',c.devengado,0 )) as "'.$dat['nombre'].'",';
        }
        // CONSULTA DE DEVENGADOS SIN CODIGOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 and c.codigo = ''
                                   and c.tipo=1 and a.estado = 1 
                                   order by c.codigo");
        $campoDevengadosB = '';
        foreach ($datos as $dat) 
        {
          $campoDevengadosB .= 'sum(if( c.idConc = '.$dat['id'].',c.devengado,0 )) as "'.$dat['nombre'].'",';
        }        
        // CONSULTA DE DEDUCIDOS SIN PRESTAMOS
        $datos = $d->getGeneral("select distinct c.id, c.nombre 
                                from n_nomina a
                                  inner join n_nomina_e_d b on b.idNom = a.id 
                                  inner join n_conceptos c on c.id = b.idConc
                                where b.idCpres = 0 
                                   and c.tipo=2 and a.estado = 1 
                                   order by c.codigo");
        $campoDeducidos = '';
        foreach ($datos as $dat) 
        {
          $campoDeducidos .= 'sum(if( c.idConc = '.$dat['id'].',c.deducido,0 )) as "'.$dat['nombre'].'",';
        } // FINCONSULTA DE DEDUCIDOS SIN PRESTAMOS               

        //  CONSULTA DE PRESTAMOS      
        $datos = $d->getGeneral("select distinct f.id, f.nombre  
                                from n_nomina a
                                  inner join n_nomina_e_d c on c.idNom = a.id 
                                  inner join n_prestamos_tn d on d.id = c.idCpres
                                  inner join n_prestamos e on e.id = d.idPres 
                                  inner join n_tip_prestamo f on f.id = e.idTpres 
                              where a.estado = 1 order by f.id ");
        // Armar id para prestamos         
        $campoPresta = '';
        foreach ($datos as $dat) 
        {
          $campoPresta .= 'sum(if( f.id = '.$dat['id'].',c.deducido,0 )) as "'.$dat['nombre'].'",';
        }
        //echo $campoPresta;

        // CONSULTA MAESTRA 
        $datos = $d->getGeneral("select g.CedEmp, g.nombre, g.apellido, b.dias,
                    ".$campoDevengados." 
                    ".$campoDevengadosB." 
                    ".$campoDeducidos." 
                    ".$campoPresta."  
                 a.id as idNom     
                from n_nomina a
                  inner join n_nomina_e b on b.idNom = a.id
                  inner join n_nomina_e_d c on c.idInom = b.id 
                  inner join a_empleados g on g.id = b.idEmp                   
                  left join n_prestamos_tn d on d.id = c.idCpres
                  left join n_prestamos e on e.id = d.idPres 
                  left join n_tip_prestamo f on f.id = e.idTpres 
                  where a.estado=1 
                  group by b.idEmp  
                  order by f.id");
        $c = new ExcelFunc();
        //print_r($datos);
        $c->listexcel($datos, "Nomina");

        $valores = array("datos" => $datos );      
        $view = new ViewModel($valores);              
        return $view;                         
      }
    }// FIN NOMINA A EXCEL

    // LISTAR NOMINAS ARCHIVOS ********************************************************************************************
    public function listarAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $a = new Alertas($this->dbAdapter);

      $form = new Formulario("form");

      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "form"      =>  $form,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $g->getListNominas("a.estado in (2)"), // Listado de nominas 
        "datEmp2"    =>  $d->getGeneral("select c.CedEmp, c.nombre, c.apellido , d.fechaI, d.fechaF  
                              from n_nomina a 
                                  inner join n_nomina_e b on b.idNom = a.id  
                                  inner join a_empleados c on c.id = b.idEmp 
                                  inner join n_emp_contratos d on d.idEmp = c.id and d.estado = 0 # Traer contrato activo 
                            where a.estado=2"),
        "datEmp" => $d->getGeneral("select a.id as idNom, c.id, c.idEmp, d.CedEmp, 
                                   d.nombre , d.apellido, c.fechaIngreso as fechaI , c.fechaF       
                               from n_nomina a 
                                  inner join n_nomina_e b on b.idNom = a.id 
                                  inner join n_nomina_l c on c.idEmp = b.idEmp and c.idNom = a.id   
                                  inner join a_empleados d on d.id = b.idEmp 
                                where a.idGrupo=99 and c.estado=0"),        
        "ttablas"   => "id,Nomina, Periodo, Empleados , Prenomina, Resumida, Retefuente",
        'url'       => $this->getRequest()->getBaseUrl(),
        "lin"       => $this->lin,        
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros     


    // GENERACION DE NOMINAS MANUALES -------------------------------------
    public function listmAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         $pn = new Paranomina($this->dbAdapter);
         $dp = $pn->getGeneral1(12);
         $topRetefuente = $dp['valorNum'];   // BASE RETEFUENTE 
         // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();            
            $connection->beginTransaction();

            $datos  = $d->getGeneral1("select *, month(fechaF) as mes 
                                          from n_nomina 
                                            where id=".$id);
            $fechaI = $datos['fechaI'];
            $fechaF = $datos['fechaF'];       
            $mesF   = $datos['mes'];   
            $idIcal = $datos['idIcal'];    
            $idTnom = $datos['idTnom'];    

     // ( REGISTRO DE NOVEDADES ) ( n_novedades ) 
         $datos2 = $g->getRnovedades($id,$idIcal);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];
             $saldoPact = 0;
             if ( $dato["valCuota"] > 0 )
             {                
                if ($dev>0)
                {
                   $saldoPact = $dev-$dato["pagado"];                  
                   $dev = $dato["valCuota"]; 
                }
                else
                {
                   $saldoPact = $ded-$dato["pagado"];                  
                   $ded = $dato["valCuota"]; 
                }                  
             }
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   
             $d->modGeneral( 'update n_nomina_e set incluido=1 where id='.$iddn );// Activar empleados 

         } // FIN REGISTRO DE NOVEDADES                          
         if ( $idTnom == 4 )
         { 
            $d->modGeneral( 'delete from n_nomina_e 
                                where incluido=0 and idNom = '.$id );
         }   
              // ( REGISTRO DE CONCEPTOW PARA NOMINAS MANUALES ) ( n_proyectos )  
         $datos2 = $g->getNomConceptos($id);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = 1;
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

         } // FIN REGISTRO DE NOVEDADES EN PROYECTOS
     
         // OTROS AUTOMATICOS POR EMPLEADOS
         $datos2 = $g->getNominaEeua($id);// Insertar nov automaticas ( n_nomina_e_d ) por otros automaticos
         foreach ($datos2 as $dato)
         {             
           $datC = $d->getGeneral1("select count(id) as num 
                                        from n_emp_conc_tn 
                                           where idTnom = 11 and idEmCon=".$dato["idEmpCon"]);
           if ($datC["num"]>0)
           { 
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = $dato['diasVac'];    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = -99;   // Id de la formula no tiene formula asociada, ya viene la formula 
             $dev = $dato["valorFijo"] ;

             $d->modGeneral("update n_nomina_e_d a 
                                  inner join n_nomina_e b on b.id = a.idInom 
                              set a.devengado = ".$dev."     
                                where a.idNom = ".$id." and b.idEmp = ".$ide."
                                 and a.idConc=".$idCon);

             $diasLabC= 0;   // Dias laborados solo para calculados
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje  = '';
             $idProy  = 0;
             // Fomrula para dais mas vacaciones en otros automaticos
             $valor = 0;
             if ( $dev > 0 ) 
             {
                $valor = $dev;$dev=0;
             }else
             {
                $valor = $ded;$ded=0;
             }

             //if ( $dato['horasCal'] > 0 ) // Afectado por lso dias laborados
             //{
             //   $formula = ' $diasLab*'.$valor; // Concatenan para armar la formula
             //   $diasLabC = $dato['dias'] ;   // Dias laborados solo para calculados
             //}else{
                if ( $dato['idVac'] > 0 )
                   $formula = ' ($diasLab+$diasVac+$diasInca+$diasAus)*'.$valor; // Concatenan para armar la formula
                else 
                   $formula = ' ($diasLab+$diasVac+$diasInca+$diasAus)*'.$valor; // Concatenan para armar la formula                  
                   //$formula = ' ($diasLab+$diasVac+$diasInca+$diasMod)*'.$valor; // Concatenan para armar la formula
             //}    
             if ( $dato['formula']!='' )
                $formula = $dato['formula'];  
             //echo 'ifo  '.$formula;
             // Llamado de funion -------------------------------------------------------------------
             //if ( ($dato['fecAct']==0) or ($dato['fecAct']==1) )
             //{
                //if ( $periodoNomina = 0)
             //   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 2,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);              
             //   $idInom = (int) $idInom;                   
             //   $d->modGeneral("update n_nomina_e_d set nitTer='".$dato['nitTer']."' where id=".$idInom);             
             //}
           }// Validacion si es nomina de manual   
         } // FIN OTROS AUTOMATICOS POR EMPLEADOS

               // CONCEPTOS AUTOMATICOS 
               $datos2 = $g->getNominaEcau($id);
               foreach ($datos2 as $dato)
               {             
                   $iddn    = $dato['id'];  // Id dcumento de novedad
                   $idin    = 0;     // Id novedad
                   $ide     = $dato['idEmp'];   // Id empleado
                   $diasLab = $dato['dias'];    // Dias laborados 
                   $diasVac = 0;    // Dias vacaciones
                   $horas   = $dato["horas"];   // Horas laborados 
                   $formula = $dato["formula"]; // Formula                   
                   $tipo    = $dato["tipo"];    // Devengado o Deducido  
                   $idCcos  = $dato["idCcos"];  // Centro de costo   
                   $idCon   = $dato["idCon"];   // Concepto
                   $dev     = 0;     // Devengado
                   $ded     = 0;     // Deducido         
                   $idfor   = $dato["idFor"];   // Id de la formula    
                   $diasLabC= 0;   // Dias laborados solo para calculados 
                   $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
                   $obId    = 0; // 1 para obtener el id insertado
                   $fechaEje  = '';
                   $idProy  = 0;
                   // Buscar ausentismos no remunerado
                   $datAus = $d->getAusentismosDias($ide, $fechaI, $fechaF);
                   $diasAus=0;
                   if ($datAus['dias']>0) # Si dias primas modificadas en la liquidacion es mayor a cero se toman esas
                   {   
                      if ( $datAus['idTasu']==13 ) // Ojo validar suspensiones en tipo 
                      {
                         //$diasLab = $diasLab - $datAus['dias'];  
                         $diasAus=$datAus['dias'];
                      }   
                   }                     
                   // Llamado de funion -------------------------------------------------------------------
                   //if ( $diasAus <=3 )
                   //{
                     $sw = 0;
                     if ( ($dato["idFpen"]==1) and ( $dato["fondo"]==2 ) ) // Si el concepto de pension no aplica no debe generarlo
                       $sw = 1;  
                     if ( $dato["tipEmp"]==0 ) // Val empan de cajacopi apra empelados no sena, revisar conceptos              
                     {
                       if ($sw == 0)
                         $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje, $idProy);              
                 
                     if ( $diasAus >0 ) # Si tiene ausentismos se reporta en asustismos primas como campo comun
                     {
                        $d->modGeneral("update n_nomina_e set diasAusPrimas=".$diasAus." where id =".$iddn);
                      }
                     //}  
                   }// Validacion tipo de empleado  

                }            
        // EMBARGOS 
        $e = new EmbargosN($this->dbAdapter);
        $datos2 = $g->getIembargosPrimas($id);// ( n_nomina_e_d ) 
        foreach ($datos2 as $dato)
        {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ""; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $fechaEje  = '';
             $idProy  = 0;
             // Ejecutar embargos      
             if ( $dato["formulaPrimas"] != '')
             {
                $con = '"'.$dato["formulaPrimas"].'"';  
                eval("\$con =$con;");
         
                $datVal = $d->getGeneral1($con);      
                $ded   = $datVal['valor'];
                $saldo = $dato["pagado"];

                if ( ( $dato['valor']>0 ) and ( $ded > $dato["pagado"] ) )# si es mayor que el saldo debe tomar el valor de saldo 
                {
                    $ded = $dato["pagado"];
                    $saldo = 0;
                }
                //echo $dato["formula"].' : '.$ded.'<br />';
                // Llamado de funion -------------------------------------------------------------------
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje,$idProy);              
                // Guardar en tabla de embargos para cruzar documentos
                $idInom = (int) $idInom;                   
                // Colocar saldo del embargo
                $d->modGeneral("update n_nomina_e_d set idRef=".$dato['idEmb'].",  nitTer='".$dato['nitTer']."', saldoPact = ".$saldo." where id=".$idInom);
                // GUARDAR REGISTRO DE EMBARGOS
                if ($idInom>0)
                   $e->actRegistro($ide, $ded, $idInom , $id, $dato['idEmb']);                
              }
             
         } // FIN EMBARGOS                                   

        // RETENCION DE LA FUENTE
        $r = new Retefuente($this->dbAdapter);
        $datos2 = $g->getRetFuente($id , 0);// 
        foreach ($datos2 as $dato)
        {                 
           if ( ( $dato['valor'] > $topRetefuente ) or ( $dato['proce'] == 2 )   )
           {                                 
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = 0;    // Dias laborados 
             $diasVac = 0;    // Dias vacaciones
             $horas   = 0;   // Horas laborados 
             $formula = ''; // Formula
             $tipo    = 2;    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = 10;   // Concepto
             $dev     = 0;     // Devengado
             $ded     = 0; // Deducido   
             $idfor   = 0;   // Id de la formula    
             $diasLabC= 0;   // Dias laborados solo para calculados 
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $calc    = 0;
             $ano     = $dato['ano'];   // Año
             $mes     = $dato['mes'];   // Mes                                 
             $ded = 0;
             //if ( $dato['dias']>0)
             $ded = $r->getReteConc($iddn, $ide); // Procedimiento para guardar la retencion
             $fechaEje  = '';
             $idProy  = 0;
             // Llamado de funion -------------------------------------------------------------------
             if ( $ded>0) 
             {
                // Buscar valor de concepto pagado anterioremente
                // en el mismo año y mes solo para procedimiento 1
                $dedAnt = 0;                
                if ( ( $dato['proce'] )==0 or ($dato['proce']==1) )
                { 
                   $datAnt  = $g->getFondSolAnt($ano, $mes,$ide, $id, 10);// RETEFUENTE
                   
                   if ( $datAnt['deducido'] > 0 )
                   { 
                      $dedAct = $ded;                     
                      $ded = $ded - $datAnt['deducido'];
                      $dedAnt = $datAnt['deducido'];  
                   }                  
                }
                $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac, $obId,$fechaEje, $idProy);              
                   $idInom = (int) $idInom;                   

                   $datPorRet = $d->getGeneral1('select a.porcentaje , a.uvtActual 
                                   from n_nomina_e_rete a 
                                      where a.idNom = '.$id.' and a.idEmp = '.$ide);
                   $d->modGeneral("update n_nomina_e_d 
                          set detalle='RETENCION EN LA FUENTE (".number_format($datPorRet['porcentaje'], 2)." %)' where id=".$idInom);
                  
                   // Colocar saldo anterior descontado en vacacione su otros 
                   if ( $dedAnt > 0 )
                       $d->modGeneral("update n_nomina_e_d 
                          set detalle='RETENCION EN LA FUENTE (ANT ".number_format($datAnt['deducido'])."- ACT ".number_format($dedAct)." ) ' where id=".$idInom);

              } // Fin validacion valor de deduccion                
            }// Fin validacion tope de retencion en la fuente           
         } // FIN RETENCION DE LA FUENTE                                   
         
            // Numero de empleados
            $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
            $dato=$d->getGeneral1($con2);                                                  

            $datos  = $d->getGeneral1("select count( distinct( a.idEmp ) ) as num 
                                           from n_nomina_e a 
                                              where a.idNom = ".$id." and
                                                 ( select count(bb.id) 
                                                      from n_nomina_e_d bb
                                                        inner join n_nomina_e aa on aa.id = bb.idInom 
                                                  where bb.idNom = ".$id." and aa.id = a.id ) > 0");
            $num = $datos['num'];
            // Cambiar estado de nomina
            $d->modGeneral( 'update n_nomina set estado=1, numEmp='.$num.' where id='.$id );
            //$d->modGeneral( 'delete from n_nomina_e where incluido=0 and idNom='.$id );                                                     

           $connection->commit();
        }// Fin try casth   
        catch (\Exception $e) {
     if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
     }
  
   /* Other error handling */
         }// FIN TRANSACCION        
         $view = new ViewModel();        
         $this->layout('layout/blancoC'); // Layout del login
         return $view;                    
       }
    }

    // GENERACION DE NOMINAS DOCUMENTOS ESPECIALES -------------------------------------
    public function listmeAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  

         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d=new AlbumTable($this->dbAdapter);                 
         $n=new NominaFunc($this->dbAdapter);
         $g=new Gnominag($this->dbAdapter);
         // INICIO DE TRANSACCIONES
         $connection = null;
         try 
         {
            $connection = $this->dbAdapter->getDriver()->getConnection();            
            $connection->beginTransaction();

            $datos  = $d->getGeneral1("select *, month(fechaF) as mes 
                                              from n_nomina where id=".$id);
            $fechaI = $datos['fechaI'];
            $fechaF = $datos['fechaF'];       
            $mesF   = $datos['mes'];   
              
         // ( REGISTRO DE NOVEDADES EN NOMINAS MANUALES )
         $datos2 = $g->getManualesDocEspe($id,$fechaI,$fechaF);// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                              
         foreach ($datos2 as $dato)
         {             
             $iddn    = $dato['id'];  // Id dcumento de novedad
             $idin    = 0;     // Id novedad
             $ide     = $dato['idEmp'];   // Id empleado
             $diasLab = $dato['dias'];    // Dias laborados
             $diasVac = 0;    // Dias vacaciones
             $horas   = $dato["horas"];   // Horas laborados 
             $formula = $dato["formula"]; // Formula
             $tipo    = $dato["tipo"];    // Devengado o Deducido  
             $idCcos  = $dato["idCcos"];  // Centro de costo   
             $idCon   = $dato["idCon"];   // Concepto
             $dev     = $dato["dev"];     // Devengado
             $ded     = $dato["ded"];     // Deducido
             $idfor   = $dato["idFor"];   // Id de la formula 
             $diasLabC= 0;   // Determinar si la afecta los dias laborados para convertir las horas laboradas
             $calc    = $dato["calc"];   // Instruccion para calcular o no calcular
             $conVac  = 0;   // Determinar si en caso de vacaciones formular con dias calendario
             $obId    = 1; // 1 para obtener el id insertado
             $fechaEje = $dato["fechaEje"];
             $idProy = $dato["idProy"];

             if ($dato["valor"]>0) 
             {
                $ded = $dato["valor"];
                if ($dato["tipo"]==1)
                    $dev = $dato["valor"];
                else 
                    $ded=0;  
             }     
             // Si es calculado en la novedad, debe permaneces su valor con los parametros del momento, sueldo, conf h extras ,ect
             // Llamado de funcion -------------------------------------------------------------------
             $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 0,$dev,$ded,$idfor,$diasLabC,0,$calc,$conVac,$obId, $fechaEje, $idProy);              
             $idInom = (int) $idInom;                   

             //$d->modGeneral("update n_nomina_e_d set idProy=".$idProy.", fechaEje ='".$dato['fechaEje']."' where id=".$idInom);
         } // FIN REGISTRO DE NOVEDADES EN PROYECTOS
            // FIN NOMINA DE DOCUMENTOS ESPECIALES ---------- 1

            // Numero de empleados
            $con2 = 'select count(id)as num from n_nomina_e where idNom='.$id ;     
            $dato=$d->getGeneral1($con2);                                                  

            // Cambiar estado de nomina
            $con2 = 'update n_nomina set estado=1, numEmp='.$dato['num'].' where id='.$id ;     
            $d->modGeneral($con2);                                         
        
//            $g->getNominaCuP($id);// Mover periodos de conceptos automaticos para tipo de nomina usado          

           $connection->commit();
         }// Fin try casth   
         catch (\Exception $e) {
           if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
            }
         }// FIN TRANSACCION        
         $view = new ViewModel();        
         $this->layout('layout/blanco'); // Layout del login
         return $view;                    
       }
    }

   // EDITAR FORMA DE PAGO
   public function listforAction()
   {
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      
      $request = $this->getRequest();   
      $data = $this->request->getPost();
      $id = $data->idA;

      $t = new LogFunc($this->dbAdapter);
      $dt = $t->getDatLog();

      // INICIO DE TRANSACCIONES
      $connection = null;
      try 
      {
          $connection = $this->dbAdapter->getDriver()->getConnection();
          $connection->beginTransaction();                

          $d->modGeneral("update n_nomina_e set pagoCes=".$data->asis." where id = ".$data->id);          

          $connection->commit();                   
          $this->flashMessenger()->addMessage('');                         
        }// Fin try casth   
        catch (\Exception $e) 
        {
           if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
        }               
      }  
             $view = new ViewModel();        
             $this->layout('layout/blancoC'); // Layout del login
             return $view;                                         

   }// FIN EDITAR FORMA DE PAGO

   // Cerar lista de empleados a liquidar
   public function listavAction() 
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);         
      $f = new NominaFunc($this->dbAdapter);
      $form = new Formulario("form");     

             $valores=array
             (
                "form"      => $form,
                "datos"     => $d->getGeneral("select a.id, b.CedEmp, b.nombre, b.apellido , a.fechaIngreso, 
                  a.fechaIngreso as fechaIcon, c.fechaF as fechaFcon, 
                  a.fechaF, d.nombre as nomTcon, b.estado      
                                                from n_nomina_l a 
                                                   inner join a_empleados b on b.id = a.idEmp 
                                                   left join n_emp_contratos c on c.id = a.idCon 
                                                   left join a_tipcon d on d.id = c.idTcon 
                                              where a.idNom = 0"),
                'url'       => $this->getRequest()->getBaseUrl(),
                "lin"       => $this->lin,        
                "ttablas"   => "Cedula, Nombre, Apellido, Tipo de contrato, Fecha de ingreso, Fecha de fin contrato, Fecha de retiro, Eliminar",
             );                
             $view = new ViewModel($valores);        
             $this->layout('layout/blancoC'); // Layout del login
             return $view;                                            

   }   

   // Eliminar dato ********************************************************************************************
   public function listavdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   $d->modGeneral("update a_empleados a
                              inner join n_nomina_l b on b.idEmp = a.id 
                                 set a.finContrato = 0 
                            where b.id = ".$id);            
                   $d->modGeneral("delete from n_nomina_l where id = ".$id);
                    $connection->commit();
                    
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a');
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                   /* Other error handling */
                }// FIN TRANSACCION                                                

          }
    }// FIN EMPLEADOS A LIQUIDAR

   // VALIDACION GRUPO DE NOMINA
   public function listagAction() 
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);         
      $f = new NominaFunc($this->dbAdapter);
      $form = new Formulario("form");     

      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) {             
             $data = $this->request->getPost();   
             $t = new LogFunc($this->dbAdapter);
             $dt = $t->getDatLog();
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   // ------------------------------------------------------------ Grupo de nomina
                  $d=new AlbumTable($this->dbAdapter);
                  $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
                  $perGrupo = 0;
                  if ( $daPer['idGrupNom']>0)
                       $perGrupo = $daPer['idGrupNom'];

                  $datG = $d->getGeneral1("select idGrupo 
                                from n_nomina 
                          where idTnom = ".$data->idTnom." 
                             and estado in (0,1) order by id desc ");
                  $arreglo='';
                  if ($perGrupo>0)
                      $datos = $d->getGrupoNom(' and id='.$perGrupo); 
                  else
                      $datos = $d->getGrupo(); 

                  foreach ($datos as $dat)
                  {  
                     if ($dat['id']!=$datG['idGrupo'])
                     {
                        $idC=$dat['id'];
                        $nom=$dat['nombre'];
                        $arreglo[$idC]= $nom;          
                      }   
                  }       
                  if ( $arreglo != '' )       
                      $form->get("idGrupo")->setValueOptions($arreglo);
                  
                  $dat = $d->getUsuEspe($dt['idUsu']);
                  $nomIndividual  = $dat['nomIndividual'];// Determinar si solo ve sus requisiciones                    

                  $valores=array
                  (
                      "form"      => $form,
                      'url'       => $this->getRequest()->getBaseUrl(),
                      "idTnom"    => $data->idTnom,
                      "perInd"    => $nomIndividual,
                      "lin"       => $this->lin,        
                  );                
                  $view = new ViewModel($valores);        
                  $this->layout('layout/blancoE'); // Layout del login
                  return $view;                                            

                   $connection->commit();                    
                   $this->flashMessenger()->addMessage('');
                   //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                   /* Other error handling */
                }// FIN TRANSACCION                                                       
         }
      }
   }  // FIN VALIDACION GRUPO DE NOMINA     

   // VALIDACION LISTADO DE EMPLEADOS DE GRUPO DE NOMINA
   public function listagiAction() 
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);         
      $f = new NominaFunc($this->dbAdapter);
      $form = new Formulario("form");     

      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) {             
             $data = $this->request->getPost();   
             $t = new LogFunc($this->dbAdapter);
             $dt = $t->getDatLog();
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   // ------------------------------------------------------------ Grupo de nomina
                  $d=new AlbumTable($this->dbAdapter);
                  $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion

                  $datos = $d->getEmp(' and idGrup='.$data->idGrupo);
                  $arreglo = ''; 
                  foreach ($datos as $dat)
                  {  
                     $idC=$dat['id'];
                     $nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
                     $arreglo[$idC]= $nom;          
                  }       
                  if ( $arreglo != '' )       
                      $form->get("tipoC2")->setValueOptions($arreglo);
                  // Calendario del grupo
                  $idCal = 9;
                  $estado = 1;
                  $orden = 1;
                  $datos = $d->getCalendarios($data->idGrupo, $idCal, $estado, $orden);
                  $arreglo = ''; 
                  foreach ($datos as $dat)
                  {  
                     $idC=$dat['id'];
                     $nom = $dat['id'].' - '.$dat['fechaI'].' '.$dat['fechaF'];
                     $arreglo[$idC]= $nom;          
                  }       
                  if ( $arreglo != '' )       
                      $form->get("tipoS")->setValueOptions($arreglo);                    

                  $valores=array
                  (
                      "form"      => $form,
                      'url'       => $this->getRequest()->getBaseUrl(),
                      "lin"       => $this->lin,        
                  );                
                  $view = new ViewModel($valores);        
                  $this->layout('layout/blancoC'); // Layout del login
                  return $view;                                            

                   $connection->commit();                    
                   $this->flashMessenger()->addMessage('');
                   //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                   /* Other error handling */
                }// FIN TRANSACCION                                                       
         }
      }
   }  // FIN VALIDACION LISTADO DE EMPLEADOS GRUPO DE NOMINA        


   // VALIDACION LISTADO DE EMPLEADOS PARA EXCLUIR DE NOMINA
   public function listagixAction() 
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);         
      $f = new NominaFunc($this->dbAdapter);
      $form = new Formulario("form");     

      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) {             
             $data = $this->request->getPost();   
             $t = new LogFunc($this->dbAdapter);
             $dt = $t->getDatLog();
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   // ------------------------------------------------------------ Grupo de nomina
                  $d=new AlbumTable($this->dbAdapter);
                  $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion

                  $datos = $d->getEmp(' and idGrup='.$data->idGrupo);
                  $arreglo = ''; 
                  foreach ($datos as $dat)
                  {  
                     $idC=$dat['id'];
                     $nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
                     $arreglo[$idC]= $nom;          
                  }       
                  if ( $arreglo != '' )       
                      $form->get("tipoC2")->setValueOptions($arreglo);
                  // Calendario del grupo
                  $idCal = 2;
                  $estado = 1;
                  $orden = 1;
                  $datos = $d->getCalendarios($data->idGrupo, $idCal, $estado, $orden);
                  $arreglo = ''; 
                  foreach ($datos as $dat)
                  {  
                     $idC=$dat['id'];
                     $nom = $dat['id'].' - '.$dat['fechaI'].' '.$dat['fechaF'];
                     $arreglo[$idC]= $nom;          
                  }       
                  if ( $arreglo != '' )       
                      $form->get("tipoS")->setValueOptions($arreglo);                    

                  $valores=array
                  (
                      "form"      => $form,
                      'url'       => $this->getRequest()->getBaseUrl(),
                      "lin"       => $this->lin,        
                  );                
                  $view = new ViewModel($valores);        
                  $this->layout('layout/blancoC'); // Layout del login
                  return $view;                                            

                   $connection->commit();                    
                   $this->flashMessenger()->addMessage('');
                   //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                   /* Other error handling */
                }// FIN TRANSACCION                                                       
         }
      }
   }  // FIN VALIDACION LISTADO DE EMPLEADOS PARA EXCLUIR 

   // Eliminar nomina 
   public function getEliminarNomina() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Gnomina($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d=new AlbumTable($this->dbAdapter); 
            $c=new Cesantias($this->dbAdapter); 
            // Consultar nomina
            $datos = $d->getGeneral1("Select idTnom, estado, idGrupo from n_nomina where id=".$id); 
            $idTnom = $datos['idTnom'];            
            $idGrupo = $datos['idGrupo'];            
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
               $connection = $this->dbAdapter->getDriver()->getConnection();
           $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores      

               $datos = $d->getGeneral1("select id from n_nomina_retro where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_retro_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_retro_i where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_retro_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_rete where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_rete where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_nomina_e_rete auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_embargos where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_embargos where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_embargos auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_pg_primas_ant where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_pg_primas_ant where idNom=".$id); 
               if ( $datos['id'] > 0) 
                   $d->modGeneral("alter table n_pg_primas_ant auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_primas where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_primas where idNom=".$id);
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_primas auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_cesantias where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_cesantias where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_cesantias auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_a where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_a where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_a auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_i where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_i where idNom=".$id);
               if ( $datos['id'] > 0)   
                   $d->modGeneral("alter table n_nomina_e_i auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d_integrar where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d_integrar where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e_d_integrar auto_increment = ".$datos['id'] ); 

               $datos = $d->getGeneral1("select id from n_nomina_e_d where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e_d where idNom=".$id); 
               if ( $datos['id'] > 0)  
                  $d->modGeneral("alter table n_nomina_e_d auto_increment = ".$datos['id'] ); 


               $datos = $d->getGeneral1("select id from n_nomina_e where idNom = ".$id." order by id limit 1"); // Obtener el id de generacion 
               $d->modGeneral("delete from n_nomina_e where idNom=".$id); 
               if ( $datos['id'] > 0)  
                   $d->modGeneral("alter table n_nomina_e auto_increment = ".$datos['id'] ); 

               $datos = $d->modGeneral("delete from n_nomina where id=".$id); 
               $d->modGeneral("alter table n_nomina auto_increment = ".$id);
               $datos = $d->modGeneral("update n_grupos set activa=0 where id=".$idGrupo);// Activar grupo de nuevo               
    
                // ---Activar liquidaciones  
              $d->modGeneral("update n_nomina_l set idNom = 0   
                                        where idNom = ".$id);          

               $u->delRegistro($id);
               
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
   }// Fin datos eliminar nomina


   // VISTA AUDITORIA DE NOMINA
   public function listauAction() 
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
      $pos = strpos( ltrim($data->id) , '.');
      $id = substr( ltrim($data->id), 0 , $pos );
      $tipo = substr( ltrim($data->id), $pos+1,100 );
      //echo $id.' '.$tipo;
      if ( $tipo == 1)
      { 
           $titulo = 'Ingresos';
           $datos  = $d->getGeneral(" select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, 
                                         a.fechaI, a.fechaF, b.idGrup, 
                  
                  ( select count(aa.id) 
                       from n_nomina_l aa 
                          where aa.idEmp = b.id and aa.fechaF between c.fechaI and ( case when day(aa.fechaF)=31 
                                                then concat(year(c.fechaF),'-', lpad(month(c.fechaF),2,'0'), '-31')  else c.fechaF end )  ) as recontra  
                      
                                            from n_emp_contratos a 
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                    and a.fechaI between c.fechaI and c.fechaF  
                                      where a.otroSi = 0 and c.id = ".$id." order by a.fechaI desc");
      }     
      if ( $tipo == 2)
      {
           $titulo = 'Retiros';         
           $dat  = $d->getGeneral1("select ( select aa.id from n_nomina aa 
                  where aa.idGrupo = a.idGrupo and aa.idTnom =1 
                    and aa.id!=".$id." order by aa.id desc limit 1 ) as idNomAnt                                     
             from n_nomina a where a.id = ".$id);
//
           $datos  = $d->getGeneral("# Fin de contratos periodo anterior
                                    select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, 
                                         a.fechaI, a.fechaF, b.idGrup,  
                                     ( select aa.id from n_emp_contratos aa where aa.idEmp = b.id and tipo = 1 order by id desc limit 1  ) as recontra                                               
                                            from n_nomina_l a 
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                    and a.fechaF between c.fechaI and ( case when day(a.fechaF)=31 
                                                then concat(year(c.fechaF),'-', lpad(month(c.fechaF),2,'0'), '-31')  else c.fechaF end )
                                      where c.id = ".$id." order by a.fechaF desc");
      }     
      if ( $tipo == 3)
      {
           $titulo = 'Terminaciones de contratos';         
           $datos  = $d->getGeneral("# Fin de contratos
                                    select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, 
                                         a.fechaI, a.fechaF, b.idGrup 
                                            from n_emp_contratos a 
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                    and a.fechaF between c.fechaI and c.fechaF  
                                      where a.otroSi = 1 and c.id = ".$id." order by a.fechaF desc");
      }           
      if ( $tipo == 4)
      {
           $titulo = 'En desvinculacion sin liquidar';         
           $datos  = $d->getGeneral("select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, aa.fechaF, b.idGrup, 
                            case aa.tipo when 0 then 'ESPERA'
                                         when 1 then 'LIQUIDACION' 
                                         when 2 then 'RETIRO' 
                                         when 3 then 'DESVINCULACION' 
                                         end as fechaI 
                                    from t_desvinculacion a
                                        inner join t_desvinculacion_e aa on aa.idDoc = a.id 
                                              inner join a_empleados b on b.id = aa.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                    and aa.fechaF between c.fechaI and ( case when day(aa.fechaF)=31 
                                                then concat(year(c.fechaF),'-', lpad(month(c.fechaF),2,'0'), '-31')  else c.fechaF end )
                                      where c.id = ".$id." and aa.tipo !=  1 
                           order by a.fechaF desc");
      }     
      if ( $tipo == 5)
      { 
           $titulo = 'Relacion de otro si';
           $datos  = $d->getGeneral("# Inicios de contratos 
                                    select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, 
                                         a.fechaI, a.fechaF, b.idGrup 
                                            from n_emp_contratos a 
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                    and a.fechaI between c.fechaI and c.fechaF  
                                      where a.otroSi = 1 and c.id = ".$id." order by a.fechaI desc");
      }                 

      if ( $tipo == 6)
      { 
           $titulo = 'Proximos ingresos';
           $datos  = $d->getGeneral("select b.CedEmp, upper(b.nombre) as nombre, upper(b.apellido) as apellido, 
                                         a.fechaI, a.fechaF, b.idGrup 
                                            from n_emp_contratos a 
                                              inner join a_empleados b on b.id = a.idEmp 
                                              inner join n_nomina c on c.idGrupo = b.idGrup 
                                      where a.otroSi = 0 and a.fechaI > c.fechaF  and c.id = ".$id."    order by a.fechaI desc");
      }                       

      if ( $tipo == 7)
      { 
           $titulo = 'Proximos ingresos';
           $datos  = $d->getGeneral("select a.CedEmp,  upper(a.nombre) as nombre, upper(a.apellido) as apellido,
  b.idEmp, a.estado , a.activo , a.finContrato, '' as fechaI, '' as fechaF    
from a_empleados a 
  left join n_nomina c on c.id = ".$id." and c.idGrupo = a.idGrup 
  left join n_nomina_e b on b.idNom = c.id and b.idEmp = a.id and b.idNom = ".$id."   
where a.estado = 0 and a.idGrup = c.idGrupo and b.idEmp is null");
      }                             
            $valores=array
            (
              "titulo"  => '',
              "form"    => $form,
              "datos"   => $datos,             
              "ttablas"   =>  "Concepto, Periodo, Valor",
              "tipo"     =>  $tipo,
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;              

   }// AUDITORIA DE NOMINA 

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
             $d->modGeneral("delete from n_nomina_e  
                               where id = ".$data->id);  
          }
      }

            $valores=array
            (
              "id"     =>  $data->id,
            );      
           $view = new ViewModel($valores);        
      $this->layout("layout/blancoC");
      return $view;            
    }   

   // VALIDACION EMPLEADOS INACTIVOS 
   public function listempAction() 
   {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');    
      $d = new AlbumTable($this->dbAdapter);         
      $f = new NominaFunc($this->dbAdapter);
      $form = new Formulario("form");     

      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();
         if ($request->isPost()) {             
             $data = $this->request->getPost();   
             $t = new LogFunc($this->dbAdapter);
             $dt = $t->getDatLog();
                $connection = null;
                try 
                {
                   $connection = $this->dbAdapter->getDriver()->getConnection();
                   $connection->beginTransaction();  

                   // ------------------------------------------------------------ Grupo de nomina
                  $d=new AlbumTable($this->dbAdapter);
                  $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
                  $perGrupo = 0;
                  if ( $daPer['idGrupNom']>0)
                       $perGrupo = $daPer['idGrupNom'];

                  $datG = $d->getGeneral1("select idGrupo 
                                from n_nomina 
                          where idTnom = ".$data->idTnom." 
                             and estado in (0,1) order by id desc ");
                  $arreglo='';
                  if ($perGrupo>0)
                      $datos = $d->getGrupoNom(' and id='.$perGrupo); 
                  else
                      $datos = $d->getGrupo(); 

                  foreach ($datos as $dat)
                  {  
                     if ($dat['id']!=$datG['idGrupo'])
                     {
                        $idC=$dat['id'];
                        $nom=$dat['nombre'];
                        $arreglo[$idC]= $nom;          
                      }   
                  }       
                  if ( $arreglo != '' )       
                      $form->get("idGrupo")->setValueOptions($arreglo);
                  
                  $dat = $d->getUsuEspe($dt['idUsu']);
                  $nomIndividual  = $dat['nomIndividual'];// Determinar si solo ve sus requisiciones                    

                  $valores=array
                  (
                      "form"      => $form,
                      'url'       => $this->getRequest()->getBaseUrl(),
                      "idTnom"    => $data->idTnom,
                      "perInd"    => $nomIndividual,
                      "lin"       => $this->lin,        
                  );                
                  $view = new ViewModel($valores);        
                  $this->layout('layout/blancoE'); // Layout del login
                  return $view;                                            

                   $connection->commit();                    
                   $this->flashMessenger()->addMessage('');
                   //return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'g/'.$id);                    
                    
                }// Fin try casth   
                catch (\Exception $e) 
                {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                   /* Other error handling */
                }// FIN TRANSACCION                                                       
         }
      }
   }  // FIN VALIDACION EMPLEADOS INACTIVOS

}
