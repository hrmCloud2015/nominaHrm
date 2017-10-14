<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Vacaciones;     // (C)
use Nomina\Model\Entity\VacacionesP;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

use Principal\Model\Pgenerales; // Parametros generales

use Principal\Model\LogFunc; // Funciones especiales
use Principal\Model\NominaFunc; // Funciones de nomina


class VacacionesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/vacaciones/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Registro de vacaciones "; // Titulo listado
    private $tfor = "Documento de vacaciones"; // Titulo formulario
    private $ttab = "id,Fecha,Cedula,Empleado,Cargo,Desde, Hasta,Nomina,Estado, Pdf, Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {            
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)      
        $id = (int) $this->params()->fromRoute('id', 0);
        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getSovac(" a.estado in ('0','1') "), // listado de vacaciones     
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,
            "id"        =>  $id,
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
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);  
      $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                           
      // ------------------------------------------------------------ Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(' and activa=0 and tipo in ("0","2")'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipo'].')';
         $arreglo[$idc]= $nom;
      }   
      $form->get("tipo")->setAttribute("value",1); // Prdeterminado nomina quincenal                   

      $form->get("tipo")->setValueOptions($arreglo);                                                       
      if ($id > 0) // Cuando ya hay un registro asociado
        {  
          $u=new Vacaciones($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
          $datos = $u->getRegistroId($id);
          
          $form->get("idEmp")->setAttribute("value",$datos['idEmp'])
                             ->setAttribute("enabled",false);        
          $form->get("tipo")->setAttribute("value",$datos['idTnom']);              
          $form->get("estado")->setAttribute("value",$datos['estado']);
        }      


      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,           
           'url'     => $this->getRequest()->getBaseUrl(),
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin actualizar datos 
   
   public function listagAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);           
      
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();         
            $f=new NominaFunc($this->dbAdapter); // Funciones de nomina     
            $u=new Vacaciones($this->dbAdapter); 
            if ($data->id>0)
            {
              $datos = $u->getRegistroId($data->id);
              $form->get("fecDoc")->setAttribute("value",$datos['fechaI']);                
            }

            $datVac =  $d->getGeneral1("select count(a.id) as num
                               from a_empleados a 
                                   inner join n_emp_contratos b on b.idEmp = a.id 
                                   where b.tipo=1 and a.id = ".$data->idEmp );            
            if ( $datVac['num']>0 )
            {
                // Crear libro de vacaciones para cada empleado por libro de contratos
                $datVac =  $d->getGeneral("select a.id, year(b.fechaI) as ano, month(b.fechaI) as mes,
                              day(b.fechaI) as dia, year(now()) as anoAct
                              , year(b.fechaI) as anoC, month(b.fechaI) as mesC, # Datos del contrato activo
                              day(b.fechaI) as diaC, b.id as idCon    
                              from a_empleados a 
                               inner join n_emp_contratos b on b.idEmp = a.id 
                                 where a.id = ".$data->idEmp." and b.tipo = 1 
                                 order by id desc limit 1 " );
            }else{ // Armar calendario de la fecha de ingreso del empleado ESTE PROESO ES ELIMINADO PORQUE TODOS TENDRAN LIBRO DE CONTRATOS 
                $datVac =  $d->getGeneral("select a.id, year(a.fecIng) as ano, month(a.fecIng) as mes,day(a.fecIng) as dia,
                              year(a.fecIng) as anoC, month(a.fecIng) as mesC, 
                              day(a.fecIng) as diaC, year(now()) as anoAct 
                              from a_empleados a 
                                 where a.id = ".$data->idEmp );
            }
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                     
                foreach( $datVac as $datV)
                {
                    $anoS = $datV['ano'];
                    if ( $datV['anoC'] > 0 ) 
                       $anoS = $datV['anoC'];
                    while( $anoS <= $datV['anoAct'] )
                    {                     
                        $mes = $datV['mes']; 
                        $dia = $datV['dia'];  
                        if ( $datV['anoC'] > 0 ) // Si hay libro de contratos toma los datos de ese libro para el libro de vacaciones
                        {
                           $mes = $datV['mesC'];
                           $dia = $datV['diaC'];
                        } 
                        // Crear periodo de vacaciones 
                        //if ( $anoS >= 2012)                       
                        //{ 
                           $f->getPervaca($data->idEmp, $anoS, $mes, $dia, $datV['idCon'] ); // Crear periodo de vacaciones empleado                            
                        //}
                        $anoS = $anoS + 1;   
                    }  
                } 
                $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) 
            {
               if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                  $connection->rollback();
                      echo $e;
               } 
              /* Other error handling */
            }// FIN TRANSACCION                                                    
            $idVac = 0;
            if (isset($data->id))
                $idVac = $data->id; 
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              'url'     => $this->getRequest()->getBaseUrl(),           
              "lin"     => $this->lin,
              "ttablas" => "id, Periodo, Contrato, días pagados, Días Pendientes, Días para disfrute,Días en dinero, Reportar",
              "datos"   => $d->getGeneral("select a.id, a.idEmp, a.fechaI, a.fechaF, a.diasP, a.diasD,  
                           case when c.dias is null then 0 else c.dias end as diasVac,
                             case when c.diasDin is null then 0 else c.diasDin end as diasDin,
( select case when count(e.id)>0 then 1 else 0 end  from c_general_dh e where e.dia=1 and e.idCcos = b.idCcos ) as sabado,   
( select case when count(e.id)>0 then 1 else 0 end  from c_general_dh e where e.dia=2 and e.idCcos = b.idCcos ) as domingo, e.id as idCon, d.fechaIp                                                           
                              from n_libvacaciones a 
                                inner join a_empleados b on b.id=a.idEmp 
                                left join n_vacaciones_p c on c.idPvac=a.id and c.idVac = ".$idVac." 
                                left join n_vacaciones d on d.id=c.idVac 
                                left join n_emp_contratos e on e.id = a.idCon 
                              where a.estado=0 and a.diasP < 15 and a.idEmp=".$data->idEmp." group by a.id 
                               order by a.fechaI "),
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;        
         }
      }        
   }  
   // Generar promedio a pagar vacaciones
   public function listacAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            $diasVac = $data->diasDisfrute; // Dias solicitados para vacaciones
            $diasDin = $data->diasDinero; // Dias solicitados para pago en dinero
            $chSabado = $data->chSabado; // Determinar sabado como dia habil en la misma vacacion 
            $chDomingo = $data->chDomingo; // Determinar sabado como dia habil en la misma vacacion             
            // Parametros generales
            $pn = new Pgenerales( $this->dbAdapter );
            $dp = $pn->getGeneral1(1);
            $dia31 = $dp['dia31'];// Si el 31 se tiene en cuenta para disfrute de vacaciones
            $chDia31 = $data->chDia31;
//echo 'Dia sabado:'.$chSabado.'  <br />';
            // Buscar centro de costo 
            $dat = $d->getGeneral1("select idCcos from a_empleados where id=".$data->idEmp); 
            // Buscar dia sabado y dia domingo 
            //$datSab = $d->getGeneral1("select count(id) as sabado from c_general_dh where dia = 1 and idCcos = ".$dat['idCcos']); // Dia habil sabado
            //$sabado = $datSab['sabado'];
            $sabado = $chSabado;
            //$datDom = $d->getGeneral1("select count(id) as domingo from c_general_dh where dia = 2 and idCcos = ".$dat['idCcos']); // Dia habil domingo
            //$domingo = $datDom['domingo'];
            $domingo = $chDomingo;
            $dias=0;      
            $sw=0;
            $swI=0; // Activar que no sume el primer dia
            $diaHabil  = 0;
            $diaNoHabil = 0;
            $diasCal = 0;
            $diasFeb = 0;
            $dias31 = 0;            
            $fecha = $data->fecsal;            
            $fecReg  = '000-00-00';
            $fecRegR = '000-00-00';
//echo 'Fecha inicio'.$fecha.'<br />';
            if ($diasVac>0) // DEBE al menos pagaser un dia de vacaciones para realizar los calculos (1)
            {
               while ($sw==0)// 
               {
                  if ($swI==1)
                  {
                     $datFec = $d->getGeneral1("select date_add('".$fecha."', interval 1 day) as fecha"); // Dia habil domingo
                     $fecha = $datFec['fecha'];
                  }
                  // Buscar si esta en dia festivo (2)
                  $diaFestivo=0;
                  $datFest = $d->getConfHn($fecha); // Verficar si no esta marcado como dia no habil 
                  if ($datFest!='')
                     $diaFestivo=1; // MArcado como dia festivo

                  // Determinar si es año bifiesto 
                  $diaFeb2829 = 29;

                  $datFecha = $d->getGeneral1("select day('".$fecha."') as diaFecha, month('".$fecha."') as mesFecha"); // Dia habil domingo                  
                  $diaFecha = $datFecha['diaFecha'];
                  $mesFecha = $datFecha['mesFecha'];
                  $swI = 1;  
                  //Sacar dia de la fecha ----- (1)
                  $diaSemana = $this->diaSemana(substr($fecha,0,4), substr($fecha,5,2) , substr($fecha,8,2)); // Devuelve el dia de semana
                  switch ($diaSemana) {
                     case 6: // Sabado 
                        if ($sabado == 1 ) // Dia habil para trabajar                                       
                        {
                          if ( $diaFestivo==1 ) // Si el sabado es dia festivo
                          {
                             $diaNoHabil++;                    
                          }else{
                             if ( $chSabado == 1 ) // Es porque en la misma vacaicon se puso como dia habil
                             {
                                $diaHabil++;
                                $dias++; // Para completar los 15 dias                             
                             }else // Es totalmente un dia no habil 
                                $diaNoHabil++;                    
                          }
                        }else // Sabado no es dia habil de trabajo
                           $diaNoHabil++;
                      break;                    
                     case 0: // Domingo 
                        if ($domingo == 1 ) // Dia habil para trabajar                                       
                        {
                          if ( $diaFestivo==1 ) // Si el sabado es dia festivo
                          {
                             $diaNoHabil++;                    
                          }else{
                             if ( $chDomingo == 1 ) // Es porque en la misma vacaicon se puso como dia habil
                             {
                                $diaHabil++;
                                $dias++; // Para completar los 15 dias                             
                             }else // Es totalmente un dia no habil 
                                $diaNoHabil++;                    
                          }
                        }else // Sabado no es dia habil de trabajo
                           $diaNoHabil++;
                      break;                                          
                    default: // Los demas dias de la semana
                      if ($diaFestivo==0)
                      {
                         $diaHabil++;                    
                         $dias++; // Para completar los 15 dias 
                      }else{
                         $diaNoHabil++;                    
                      }
                      break;
                  }      
                  // Dia de febrero   
                  if ( $mesFecha==2 )               
                  {
                     if ($diaFecha==28)
                     {
                        $diasFeb++;
                        $diasFeb++;
                     }   
                     if ($diaFecha==29)
                     {
                        $diasFeb=$diasFeb-1;// Restar el dia de mas cuado es 29 de febrero
                     }   
                  }
                  // Dia 31 
                  if ( $dia31 == 0) 
                  {
                     if ($diaFecha==31)
                        $dias31++;
                  }
                  if ($dias==$diasVac) // Validar si ya se completaron los dias para disfrute
                      $sw=1;
               }
            }// Fin validacion si tiene vacaciones disfrutadas (1)            
//echo 'Fecha Final'.$fecha.'<br />';
//echo 'Dias no habiles'.$diaNoHabil.'<br />';
//echo 'Dias habiles'.$diaHabil.'<br />';
//echo 'Dias 31:'.$dias31.'<br />';
            //--------- CALCULO DEL REGRESO --------------------------------
            $sw=0;
            $fecReg = $fecha;
            while ($sw==0)// 
            {
               //Sacar dia de la fecha ----- (1)
               $datFec = $d->getGeneral1("select date_add('".$fecha."', interval 1 day) as fecha"); // 
               $fecha = $datFec['fecha'];  

               // Buscar si esta en dia festivo (2)
               $diaFestivo=0;
               $datFest = $d->getConfHn($fecha); // Verficar si no esta marcado como dia no habil 
               if ($datFest!='')
                  $diaFestivo=1; // MArcado como dia festivo                            

               $diaSemana = $this->diaSemana(substr($fecha,0,4), substr($fecha,5,2) , substr($fecha,8,2)); // Devuelve el dia de semana              
               switch ($diaSemana) {
                     case 6: // Sabado 

                        if ($sabado == 1 ) // Dia habil para trabajar                                       
                        {
                          if ( $diaFestivo==1 ) // Si el sabado es dia festivo
                          {
                             $diaNoHabil++;$sw=0;                     
                          }else{
                             if ( $chSabado == 1 ) // Es porque en la misma vacaicon se puso como dia habil
                             {
                                $sw=1; 
                             }else // Es totalmente un dia no habil 
                             {
                                $diaNoHabil++;                    
                                $sw=0; 
                             }   
                          }
                        }else // Sabado no es dia habil de trabajo
                        {
                           $diaNoHabil++;
                           $sw=0;
                        }  
                      break;                    
                     case 0: // Domingo 
                        if ( ($domingo == 1 ) and ($diaFestivo==0) )// Dia habil para trabajar 
                           $sw=1; 
                        else
                        {
                           $sw=0; 
                           $diaNoHabil++;
                        }
                      break;                                          
                    default: // Los demas dias de la semana
                      if ($diaFestivo==0)
                      {
                         $sw=1;
                      }else
                        {
                           $sw=0; 
                           $diaNoHabil++;
                        }
                      break;
               }                     
            }
            $fecRegR = $fecha; 
            // Buscar si tiene el dia no habil guardado en la tabla
            // verificando si la fecha de salida es la misma para colocar los dias no habilles
            $datDias = $d->getGeneral1("select diasNh 
                            from n_vacaciones 
                              where idEmp=".$data->idEmp."
                                 and fechaI='".$data->fecsal."' 
                                   and sabado=".$data->chSabado." and domingo=".$data->chDomingo); 
            $diasNoHabiles = $diaNoHabil + ( $diasFeb - $dias31 );
            if ($datDias['diasNh']>0)
                $diasNoHabiles = $datDias['diasNh'];              
             
//echo 'Fecha regreso '.$fecha.'<br />';
            // --
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              'url'     => $this->getRequest()->getBaseUrl(),           
              "lin"     => $this->lin,
              "datos"   => $d->getVacaP($data->idEmp, $data->fecsal, $data->fechaIni, 6), // Conce disfrute
              "datosD"  => $d->getVacaP($data->idEmp, $data->fecsal, $data->fechaIni, 7),// Conce dinero
              "datEmp"  => $d->getEmp(" and a.id=".$data->idEmp),                
              "fecReg"  => $fecReg,      
              "fecRegR" => $fecRegR,  
              "dias31"  => $dias31, 
              "chDia31" => $chDia31,
              "diasFeb"  => $diasFeb,                  
              "diasHab" => $dias , // Dias habiles menos febrero y 31 
              "diasNhab"=> $diasNoHabiles, // Dias no habiles
              "chSabado"=> $chSabado, // Sabado dia no habil marcado en documento de vacacion
              "chDomingo"=> $chDomingo, // Sabado dia no habil marcado en documento de vacacion
              "diasCal" => $diasCal, // Dias calendario                
              "diasDis" => $diasVac, // Dias para disfrute                 
              "diasDin" => $diasDin, // Dias dinero              
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;        
         }
      }        
   }     
   // Generar vacaciones
   public function listgAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u    = new Vacaciones($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();         

                $id = $u->actRegistro($data);
                if ($data->idVac>0)
                    $id = $data->idVac; 
            
                // Periodos de vacaciones
                $u = new VacacionesP($this->dbAdapter);
                $i=0;
                $dat = $d->getGeneral1("select idEmp, dias from n_vacaciones where id=".$id);                           
                $idEmp = $dat['idEmp'];
                $diasVacDif = $dat['dias']; // Dias vacaciones para disfrutes  

                $d->modGeneral("delete from n_vacaciones_p where idVac=".$id);       
                //print_r($data->idPer);
                while ($i < count($data->idPer))
                {                  
                   if ( ($data->diasP[$i]>0) or ($data->diasPd[$i]>0) ) 
                   {                      
                       // Buscar si esta en la convecion y su fecha de ingreso para validar periodos de vacaciones para pago de prima de vacaciones
                       $datPcomv = $d->getGeneral1("select 
                          sum( case when ( ( b.fecha >= a.fechaI ) and ( b.fecha <= a.fechaF ) ) then # Si periodo de covencion esta dentro del periodo de vacaciones, se saca le porcentaje
                                (  ( datediff( a.fechaF , b.fecha )+1 ) * 1  ) / ( datediff( a.fechaF , a.fechaI )+1 ) 
                          else 
                             case when ( b.fecha < a.fechaI ) then # Si es antes ingresa al periodo de convencion 
                                 1
                             else     
                                 0
                            end    
                              end ) as num  
                                from n_libvacaciones a 
                                   left join n_tipemp_p b on b.idEmp = a.idEmp 
                                   where a.idEmp=".$idEmp." and a.id=".$data->idPer[$i]."  
                                      and ( a.fechaI >= b.fecha or a.fechaF >= b.fecha )  ");  
                       $numPerConv = 0;  
                       if ($datPcomv['num']>0)                
                           $numPerConv = $datPcomv['num'];  
                       // actualizacion pago de periodos de vacaciones                                       
                       $u->actRegistro($data->idPer[$i], $data->diasP[$i], $id, $numPerConv, $data->diasPd[$i] );                

                       if ( $data->cerrar == 1 )
                       {
                          $d->modGeneral("update n_libvacaciones 
                                               set diasP = ".$data->diasP[$i]." , 
                                                   diasD = ".$data->diasP[$i]." 
                                            where id = ".$data->idPer[$i]);                        
                          if ( ($data->diasP[$i] + $data->diasPd[$i]) == 15 )
                             $d->modGeneral("update n_libvacaciones 
                                               set estado = 1 
                                            where id = ".$data->idPer[$i]);

                             $d->modGeneral("update n_vacaciones  
                                               set estado = 2, saldoIni = 1   
                                            where id = ".$id);
                       } 
                   }
                   $i++;
                }            
                // Actualizar empleado 
                if ( $data->cerrar == 0 )
                {
                   if ( ($data->estado==1) and ($diasVacDif>0) )
                       $d->modGeneral("update a_empleados set idVac=".$id." where id=".$data->idEmp); 
                }     
                $connection->commit();
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
      $view = new ViewModel();        
      $this->layout('layout/blancoB'); // Layout del login
      return $view;       
   }
   
   
   function diaSemana($ano,$mes,$dia)
   {
      // 0->domingo  | 6->sabado
      $dia= date("w",mktime(0, 0, 0, $mes, $dia, $ano));
      return $dia;
   }

    // Listado de registros ********************************************************************************************
    public function listdAction()
    {            
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)      
        $id = (int) $this->params()->fromRoute('id', 0);
        $dat = $u->getGeneral1("select idEmp from n_vacaciones where id=".$id);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();                                                           
            
            $u->modGeneral("update a_empleados set idVac=0 where id=".$dat['idEmp']);
            $u->modGeneral("delete from n_vacaciones_p where idVac=".$id);
            $u->modGeneral("delete from n_vacaciones where id=".$id);
            $connection->commit();  
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);            
            // FIN GUARDADO 
           }// Fin try casth   
           catch (\Exception $e) {
              if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
                $connection->rollback();
                   echo $e;
        } 
              /* Other error handling */
           }// FIN TRANSACCION                                                              
                    
    } // Fin listar registros         
   
   // Reportar periodo como pagado
   public function listperAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $t = new LogFunc($this->dbAdapter);
      $dt = $t->getDatLog();

      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            $d->modGeneral("update n_libvacaciones set estado=1, diasP=15, idUsuP=".$dt['idUsu']." where id = ".$data->id);       
         }
      }   
      $view = new ViewModel();        
      $this->layout('layout/blancoB'); // Layout del login
      return $view;       
   }

   // Datos del cargo
   public function listeAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         

            $data = $this->request->getPost();       
            $valores = array(
               "datos"  => $d->getGeneral1("select a.sueldo, b.nombre as nomCar, c.nombre as nomCos, 
( select case when count(id)>0 then 'DIA HABIL' else 'DIA NO HABIL' end  from c_general_dh d where d.dia=1 and d.idCcos = c.id ) as sabado,   
( select case when count(id)>0 then 'DIA HABIL' else 'DIA NO HABIL' end  from c_general_dh d where d.dia=2 and d.idCcos = c.id ) as domingo  
                                              from a_empleados a
                                              inner join t_cargos b on b.id = a.idCar
                                              inner join n_cencostos c on c.id = a.idCcos 
                                                where a.id=".$data->idEmp),
               "form"   => $form, 
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
            $u    = new Vacaciones($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();

         }
      }   
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              "datos"   => $d->getVacaPd($data->idEmp, $data->fecsal, $data->fechaIni, $data->proceso),             
              "ttablas"   =>  "Concepto, Periodo, Valor",
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;              

   }// FIN PROMEDIOS    
}
