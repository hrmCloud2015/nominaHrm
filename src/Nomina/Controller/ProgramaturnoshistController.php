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
use Nomina\Model\Entity\Zonas; // (C)

use Principal\Model\LogFunc; // Funciones especiales

class ProgramaturnoshistController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/Programaturnoshist/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Programacion"; // Titulo listado
    private $tfor = "Actualización turnos"; // Titulo formulario
    private $ttab = "id,Zona,Editar,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)

        $t = new LogFunc($this->dbAdapter);
        $dt = $t->getDatLog();

        $dat = $d->getGeneral1("select id 
                                  from n_supervisores  
                                     where idEmp = ".$dt['idEmp']) ;  
        $idSup = $dat['id'];
        $dat = $d->getGeneral1("select id, cedEmp, nombre, apellido  
                                  from a_empleados 
                                    where id = ".$dt['idEmp']) ;  
        $super = ' Coordinador : ('.$dat['nombre'].' '.$dat['apellido'].')';
        $datP = $d->getProgramaPeriodo();
        // Supervisores
        $arreglo='';
        $datos = $d->getSupervisoresNombresActivos(''); 
        $sw = 0;        
        foreach ($datos as $dat)
        {
           $idc=$dat['id'];$nom = $dat['nomComp'];
           if ($sw == 0)
           {
              $sw = $dat['id'];
           }
           $arreglo[$idc]= $nom;
        }              
        $form->get("tipoC")->setValueOptions($arreglo);        
        $form->get("tipoC")->setAttribute("value", $sw);        

        // Puestos
        $arreglo='';
        //if ( $dt['admin'] == 1 )
        //   $datos = $d->getPuesSuper(' '); 
        //else   
           $datos = $d->getPuesSuper(''); 
        $sw = 0;        
        foreach ($datos as $dat)
        {
           $idc=$dat['id'];$nom = $dat['nombre'];
           if ($sw == 0)
           {
              $sw = $dat['id'];
           }
           $arreglo[$idc]= $nom;
        }   
        if ( $arreglo != '' )           
           $form->get("tipo")->setValueOptions($arreglo);        
        //$form->get("tipoC")->setAttribute("value", $sw);                


        $valores=array
        (
            "titulo"    => ' Consulta historica de programaciones ' ,
            "ttablas"   =>  $this->ttab,
            "form"      =>  $form,
            'url'       => $this->getRequest()->getBaseUrl(),            
            "idUsu"     => $idSup,
            "lin"       =>  $this->lin
        );                
      $view = new ViewModel($valores);        
      $this->layout('layout/layoutTurnos'); 
      return $view;                
        
    } // Fin listar registros 
    

    // Programacion ********************************************************************************************
    public function listpAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $dat = $d->getGeneral1("select day( last_day(
                                  concat( ano ,'-', lpad( mes ,2,'0' ), '-01'  )  ) ) as dia, ano, mes 
                         from n_nov_prog_p");        
        $diaPer = $dat['dia'];

        $guardado = 0;
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) 
           {
              $data = $this->request->getPost();
              // INICIO DE TRANSACCIONES
              $connection = null;
              try 
              {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                              

                $connection->commit();
//                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$id);// El 1 es para mostrar mensaje de guardado
              }// Fin try casth   
              catch (\Exception $e) 
              {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                  /* Other error handling */
              }// FIN TRANSACCION                                                          //              return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }// Fin guardado datos
        }
        // BUscar periodos
        $datP = $d->getProgramaPeriodo();
        // Ubicacion de lso dais de la fecha 
        $dia=1;
        $diaC  = 1; 
        $dia  = 1; 
        $sw = 0;
        $titulo = '';
        $semana = '';
        $fechaI = $datP['fecha'];         
        $mes    = $data->mes;     
        $ano    = $data->ano;

        $dat = $d->getGeneral1("select day( last_day(
                                  concat( ".$ano." ,'-', lpad( ".$mes.",2,'0' ), '-01'  )  ) ) as dia");

        $diasMes = $dat['dia'];

//        echo 'dias '.$diasMes;
        while ($dia<=$diasMes)
        {
          $dat = $d->getGeneral1("select DATE_ADD('".$fechaI."', INTERVAL 1 DAY) as fecha ,
                               day(DATE_ADD('".$fechaI."', INTERVAL 1 DAY) ) as dia,
                        CONCAT(ELT(WEEKDAY('".$fechaI."') + 1, 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do')) as diaSemana   ");   
           $fechaI = $dat['fecha'];
           $diaS  = $dat['diaSemana'];       
           $sab ='';     
           if ($diaS=='Do')     
               $color = '#00F700;'; 
           else   
               $color = '#fafafa;'; 

           // verificar 
           $dat = $d->getGeneral1("select count( a.id) as num 
                           from c_general_dnh a 
                               inner join n_nov_prog_p b on b.ano = year( a.fecha ) and b.mes = month( a.fecha ) and day( a.fecha ) = ".$dia);             
           if ($dat['num']>0)     
               $color = '#FAE5D3;';            

           $semana = $semana.'<th style="text-align:center; background-color: '.$color.'"><strong>'.$diaS.'</strong></th>';

           $titulo = $titulo.'<td style="text-align:center;"><strong>'.$dia.'</strong></td>';
           $sw = 1;
           $dia++;
        }
      // Turnos 
      $arreglo='';
      $datos = $d->getTurnos(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);
      // Horarios
      $arreglo='';
      $datos = $d->getHorarios(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idHor")->setValueOptions($arreglo);

      $form->get("id4")->setAttribute("value",$diasMes);
      $idSup = $data->idSup; 

      // Tipo de novedad
      $arreglo='';
      $datos = $d->getGeneral('select * from n_tip_nov_prog '); 
      foreach ($datos as $dat)
      {
         $idc = $dat['id'];$nom = $dat['nombre'];
         $arreglo[$idc] = $nom;
      }              
      $form->get("tipoC2")->setValueOptions($arreglo);

//echo 'd '.$ano.' - '.$diasMes;

        $valores=array
        (
            "titulo"    => $this->tlis,
            "datos"     => $d->getTurnosProgramaAnt($data->idSup, $data->idPue, $ano, $mes), 
            "datFcon"   => $d->getGeneral("select a.idEmp, 
                                          day( a.fechaF ) as diaF  
                                        from n_emp_contratos a 
                                            where year(a.fechaF) = ".$ano."  
                                           and month(a.fechaF) = ".$mes." 
                                            group by a.idEmp  
                                            order by a.fechaI desc "), 
            "datIcon"   => $d->getGeneral("select a.idEmp, 
                                          day( a.fechaI ) as diaI   
                                        from n_emp_contratos a 
                                            where year(a.fechaI) = ".$ano."  
                                           and month(a.fechaI) = ".$mes." 
                                            group by a.idEmp  
                                            order by a.fechaF desc "),             
            "datInc"    => $d->getIncaPrograma($idSup, $mes),                        
            "datIncPr"  => $d->getIncaProgramaPro($idSup, $mes),                        
            "datAus"    => $d->getAusPrograma($idSup, $mes),                        
            "datVac"    => $d->getVacPrograma($idSup, $mes),                        
            "datRem"    => $d->getGeneral("select a.* 
                                             from n_nov_prog_t a 
                                  where a.idSup = ".$idSup),

            "ttablas"   => $this->ttab,
            "form"      => $form,
            'url'       => $this->getRequest()->getBaseUrl(),            
            "lin"       => $this->lin,
            "tablaHor"  => $titulo,  
            "diasMes"   => $diasMes,
            "semana"    => $semana              
        );                

      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); 
      return $view;                
        
    } // Fin listar registros 
    // Reeplazos ********************************************************************************************
    public function listrAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $request = $this->getRequest();
        $data = $this->request->getPost();
        $valores=array
        (
           "form"      => $form,
           "datos"     => $d->getReemTurnosPrograma($data->idSup, $data->dia),            
           "lin"       => $this->lin,
        );                
        $view = new ViewModel($valores);        
        $this->layout('layout/blanco'); 
        return $view;                        
    } // Fin listar registros 

    // Buscar empleados disponibles
    public function listn2Action()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $request = $this->getRequest();
        $data = $this->request->getPost();

        $dia = "a.t".$data->diasMes;
//echo $dia." ".$data->idEmp;
        $valores=array
        (
           "form"      => $form,
           "datNov"    => $d->getGeneral("select c.id as idEmp, c.CedEmp, c.nombre as nomEmp, 
                                               c.apellido as nomApe, e.nombre as nomPues , ".$dia."  
                                             from n_nov_prog a 
                                                inner join n_horarios b on b.id = ".$dia."  
                                                inner join a_empleados c on c.id = a.idEmp  
                                                inner join n_proyectos_ep d on d.idEmp = c.id 
                                                inner join n_proyectos_p e on e.id = d.idPtra                                                 
                                             where a.idEmp != ".$data->idEmp." 
                                             group by c.id "),
           "datNov2"    => $d->getGeneral("select c.id as idEmp, c.CedEmp, c.nombre as nomEmp, 
                                               c.apellido as nomApe, ".$dia."  
                                             from n_nov_prog a 
                                                inner join n_horarios b on b.id = ".$dia."  
                                                inner join a_empleados c on c.id = a.idEmp  
                                             where a.idEmp != ".$data->idEmp." and ( select aa.dia from n_nov_prog_r aa 
                                where aa.idEmpR = a.idEmp 
                                  and aa.dia = ".$data->diasMes." ) is null"),           

           "datNovR"   => $d->getGeneral("Select b.CedEmp, b.nombre, b.apellido  
                                             from n_nov_prog_r a   
                                                inner join a_empleados b on b.id = a.idEmpR 
                                            where a.idEmp = ".$data->idEmp),           
           "datRel"   => $d->getGeneral("select c.id as idEmp, c.CedEmp, c.nombre as nomEmp, 
                                               c.apellido as nomApe   
                                             from n_proyectos_e a  
                                                inner join a_empleados c on c.id = a.idEmp  
                                             where a.relevante = 1 and a.idEmp != ".$data->idEmp." and ( select aa.dia from n_nov_prog_r aa 
                                where aa.idEmpR = a.idEmp 
                                  and aa.dia = ".$data->diasMes." ) is null"),                      
           "lin"       => $this->lin,
        );                
        $view = new ViewModel($valores);        
        $this->layout('layout/blancoI'); 
        return $view;                        
    } // Fin listar registros     

    // -------------------------------------------------------------
    // Funcion para actualizar y edicion de dias en programacion 
    // -------------------------------------------------------------
    public function getActualizar( $id, $idHor, $descanso, $dia, $idEmp, $domingo, $festivo )
    {
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter);       

       // Validar variables en conceptos guardados en horarios 
       $datHor = $d->getGeneral("call pr_programa_turnos_c( ".$idHor.")");

       foreach ($datHor as $datH)
       {                                                   

           $d->modGeneral("delete from n_nov_prog_m 
                 where idNov = ".$id." and idEmp = ".$idEmp." and dia = ".$dia ); 

           $d->modGeneral("insert into n_nov_prog_m ( idNov, idHor, idHfor, tipo, dia, idEmp, domingo, festivo ) 
              values (".$id.",".$idHor.",".$datH['id'].",".$datH['tipo'].",".$dia.", ".$idEmp.", ".$domingo.", ".$festivo.")"); 
       }
       // Validar permiso de trabajo 
       if ($descanso==1)
       {  
           $d->modGeneral("insert into n_nov_prog_m ( idNov, idHfor, tipo, dia, descanso, idEmp, domingo, festivo ) 
              values (".$id.",1 ,0,".$dia.", ".$descanso.", ".$idEmp.", ".$domingo.", ".$festivo.")");                                          
       }
    }                          


    public function listhorAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) 
           {
              $data = $this->request->getPost();
           }// Fin guardado datos
        }
      // Horarios
      $arreglo='';
      $datos = $d->getTurnoHorarios($data->idTur); 
      $i = 1;
      foreach ($datos as $dat)
      {        
         $idc = $i; 
         $nom = $i.' - '.$dat['nombre'];
         $i++;
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipoC")->setValueOptions($arreglo);

        $valores=array
        (
            "titulo"    => $this->tlis,
            "form"      => $form,
            'url'       => $this->getRequest()->getBaseUrl(),            
            "lin"       => $this->lin,
        );                
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); 
      return $view;                
        
    } // Fin listar registros 

    // Edicion rapida de novedades
    public function listnrAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) 
           {
              $data = $this->request->getPost();
              // INICIO DE TRANSACCIONES
              $connection = null;
              try 
              {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                              
                //echo $empArreglo = str_replace('emp','',$data->empArreglo);
//echo $data->diaArreglo.'<br />';
                $can = strlen( ltrim($data->diaArreglo) ); 
                $cadena = ltrim($data->diaArreglo);
//echo 'cadena '.$cadena.' <br />';
                // Buscar posicion final 

                $final = substr(  $cadena , $can-2, $can );
                $posF = strpos( $final, "," );
                $final = substr(  $final , $posF+1, $can );

//echo 'cadena '.$final.' pow '.$posF;
//echo 'cadena '.$final.' pow '.$posF;


                $errores = '';
              if($data->accion == 1)// Borrar
              {
                $d->modGeneral("delete a from n_nov_prog_m a 
                                    inner join n_nov_prog b on b.id = a.idNov 
                                where b.idEmp=".$data->idEmp);

                $d->modGeneral("delete a from n_nov_prog_t a 
                                    inner join n_nov_prog b on b.id = a.idNov 
                                where b.idEmp=".$data->idEmp);                

                $d->modGeneral("delete from n_nov_prog 
                                            where idEmp=".$data->idEmp);
                 
              }else{   // Editar o crear uno nuevo  

                for ($i = 1; $i <= $can; $i++)
                {
                   if ($i<31)
                   { 
                     $pos = strpos( $cadena, "," );
                     //if ( ($i!=31) or ($i!=30) )
                     if ( $pos > 0 )
                        $val = substr( $cadena , 0, $pos);
                     else 
                        $val = $cadena; 
                  //echo $cadena.' : '.$i.' - '.$val.' - pos_'.$pos.' <br />';                     
                     $cadena = substr( $cadena , $pos+1, $can );  
                     $datP = $d->getProgramaPeriodo();
                     // Insertar novedades si no existe  
                     $dat = $d->getGeneral1("select count(id) as num  
                                                from n_nov_prog 
                                            where idEmp=".$data->idEmp);              
                     if ($dat['num']==0)
                        $id = $d->modGeneralId("insert into n_nov_prog (idEmp, fecha, idSup) 
                           values(".$data->idEmp.", '".$datP['fecha']."', ".$data->idSup.")");

                      // Buscar id del horario 
                      $datHor = $d->getGeneral1("Select id, neutro as descanso    
                                       from n_horarios 
                                         where codigo = '".$val."'");

                      if ( $datHor['id'] > 0 )
                      {      
                        //echo '1<br />';                                            
                         $horario = $datHor['id']; 
                         $descanso = $datHor['descanso'];
                         $cam = 't'.$i;  
                         $datHor = $d->getGeneral1("Select * 
                                       from n_nov_prog 
                                         where idEmp = ".$data->idEmp);
                         //echo $cam.' - '.$val.' <br />'; 
                         $d->modGeneral("update n_nov_prog set $cam = ".$horario." 
                                         where idEmp = ".$data->idEmp);    
                         
                         $dat = $d->getGeneral1("select  
                                                 case when DAYOFWEEK( concat( ano ,'-', lpad( mes ,2,'0' ), '-', 
                                             lpad( ".$i." ,2,'0' ) ) ) = 1 then 1 else 0 end as domingo 
                                             from n_nov_prog_p");        
                         $domingo = $dat['domingo'];
//echo 'domingo'.$domingo.'<br />';
                         // Borrar dia de descanso 
                         if ($descanso==1)
                         { 
                            $d->modGeneral("delete from n_nov_prog_m 
                                  where idNov = ".$datHor['id']." and idEmp = ".$data->idEmp." and dia = ".$i ); 
                         }
                         // verificar festivo 
                         $dat = $d->getGeneral1("select count( a.id) as festivo  
                             from c_general_dnh a 
                                  inner join n_nov_prog_p b on b.ano = year( a.fecha ) and b.mes = month( a.fecha ) and day( a.fecha ) = ".$i);
                         $festivo = $dat['festivo'];

                         $this->getActualizar( $datHor['id'] , $horario, $descanso, $i, $data->idEmp, $domingo, $festivo );
                      }else{
                         if ( ( $val != '' ) and ( $val != '1' ) )
                             $errores = $errores.', dia '.$i.'-'.$val;
                      }
                   }// FIn validacion menos de 32 error en turno    
                }
                // Ultimo registrp

              } // Validacion accionn
                
                $connection->commit();
//                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$id);// El 1 es para mostrar mensaje de guardado
              }// Fin try casth   
              catch (\Exception $e) 
              {
                  if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) 
                  {
                      $connection->rollback();
                        echo $e;
                  } 
                  /* Other error handling */
              }// FIN TRANSACCION                                                          //              return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }// Fin guardado datos
        }

        $valores=array
        (
            "errores"    => $errores ,
        );                
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoC'); 
      return $view;                
        
    } // Fin listar registros     

    // Buscar empleados disponibles
    public function listnAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $request = $this->getRequest();
        $data = $this->request->getPost();

        $dia = "a.t".$data->diasMes;
 
        $con = " and a.nombre like '%".ltrim($data->nombre)."%' 
                     or b.nombre like '%".ltrim($data->nombre)."%' ";

        $valores=array
        (
           "form"      => $form,
           "datos"     => $d->getPuesSuper($con),
           "lin"       => $this->lin,
        );                
        $view = new ViewModel($valores);        
        $this->layout('layout/blancoC'); 
        return $view;                        
    } // Fin listar registros         

    // Buscar editar infroamcion 
    public function listneAction()
    {
        $form = new Formulario("form");   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        $request = $this->getRequest();
        $data = $this->request->getPost();

        $dia = "a.t".$data->diasMes;
        //echo $data->idEmp.'<br />';
        //echo $data->diasMes;

        $valores=array
        (
           "form"      => $form,
           "datos"     => $d->getGeneral1("select a.id, a.comen, a.idTnov,  
                                             upper(b.nombre) as nombre  
                                           from n_nov_prog_t a 
                                             inner join n_proyectos_p b on b.id = a.idPues 
                                          where a.idEmp = ".$data->idEmp."
                                              and a.dia=".$data->diasMes),
           "lin"       => $this->lin,
        );                
        $view = new ViewModel($valores);        
        $this->layout('layout/blancoC'); 
        return $view;                        
    } // Fin listar registros             

    public function listeliAction()
    {
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d = new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) 
           {
              $data = $this->request->getPost();
              $d->modGeneral("delete from n_nov_prog_t where id = ".$data->id);

           }// Fin guardado datos
        }

      $view = new ViewModel();        
      $this->layout('layout/blancoC'); 
      return $view;                
        
    } // Fin listar registros     
}
