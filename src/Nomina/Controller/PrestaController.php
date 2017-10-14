<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Presta;     // (C)
use Nomina\Model\Entity\Prestan;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Model\NominaFunc;        
use Principal\Model\LogFunc;
use Principal\Model\Gnominag; // Procesos generacion de automaticos

class PrestaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/presta/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Prestamos a empleados"; // Titulo listado
    private $tfor = "Documento de solicitud"; // Titulo formulario
    private $ttab = "id, Fecha, Empleado, Cargo, Tipo, Pdf, Valor, Abonado, Saldo, Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        $form = new Formulario("form");
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        // Empleados     
        $arreglo='';
        $datos = $u->getGeneral('select distinct a.id, a.CedEmp, a.nombre, a.apellido 
                                  from a_empleados a
                                    inner join n_prestamos b on b.idEmp = a.id 
                                    where a.estado = 0 order by a.nombre');
        foreach ($datos as $dat)
        {
            $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
            $arreglo[$idc]= $nom;
        }      
        $form->get("idEmp")->setValueOptions($arreglo);              

        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datArb"   =>  $u->getGeneral("select distinct b.id, b.nombre 
                                                 from n_prestamos a
                                                  inner join n_tip_prestamo b on b.id = a.idTpres order by b.nombre"),                        
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,   
            'url'       => $this->getRequest()->getBaseUrl(),
            "form"      => $form,         
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado                
        );                
        return new ViewModel($valores);
        
    } // Fin listar registros 
    
    public function listeAction()
    {
        $tipo = $this->params()->fromRoute('id', 0);        
        $id = (int) $this->params()->fromRoute('id', 0);
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $con = "a.idTpres = ".$id; 
        if ( substr($tipo,0,1)==0) // Actulizar datos
        {
              $con = "a.idEmp = ".$id;
        }
        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getEmpPrestamos($con),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,            
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado                
        );                

        $view = new ViewModel($valores);        
        $this->layout('layout/blancoI'); // Layout del login
        return $view;      
        
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
      $d = New AlbumTable($this->dbAdapter);      
      // Tipos de nomina
      $arreglo='';
      $datos = $d->getTnom('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTnom")->setValueOptions($arreglo);                    
      // Empleados     
      $arreglo='';
      $datos = $d->getEmp('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);              
      $arreglo='';
      $datos = $d->getTpres("");
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTpres")->setValueOptions($arreglo);                    
      
      $arreglo='';
     // $datos = $d->getEntidades();
     // foreach ($datos as $dat)
     // {
       // $idc=$dat['id'];$nom=$dat['nombre'];
       // $arreglo[$idc]= $nom;
      //}      
      //$form->get("idEnt")->setValueOptions($arreglo);                          
      
      $datTnom = $d->getGeneral1("select idTpres,idPresRef, estado from n_prestamos where id=".$id);
      $estado    = $datTnom['estado'];
      $idPresRef = $datTnom['idPresRef'];
      // Estado
      $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      if ($datTnom['estado']==0)
      { 
         $val=array
         (
            "0"  => 'Revisión',
            "1"  => 'Aprobado'              
          );                 
      }else{
         $val=array
         (
            "1"  => 'Aprobado',
            "3"  => 'Inactivo',             
          );                             
      }
      $form->get("estado")->setValueOptions($val);
      $valores=array
      (
           "titulo"  => "Documento de prestamo N°".$id,
           "form"    => $form,
           "idPresRef" => $idPresRef,
           'datTnom' => $d->getPresCuotas($datTnom['idTpres'], $id),     
           "datPresM"=> $d->getGeneral("select a.* , b.usuario, c.nombre as nomTpres, d.CedEmp, d.nombre, d.apellido        
                                          from n_prestamos_h a 
                                            inner join users b on b.id = a.idUsu 
                                            inner join n_tip_prestamo c on c.id = a.idTpres  
                                            inner join a_empleados d on d.id = a.idEmp  
                                            where a.idPres =".$id), # Cuotas fijas 
           "datAbo" => $d->getAbonosExtra($id), # Dato abonos extraordinarios             
           "datPro" => $d->getGeneral("select count( a.idTnom ) as num, sum(a.valor) as valor, a.idTnom
                                       , b.nombre as nomTnom         
                                           from n_prestamos_pro a
                                           inner join n_tip_nom b on b.id = a.idTnom 
                                             where a.idPres = ".$id."  
                                                group by a.idTnom "), # Cuotas programadas           
           "estado"  => $estado,
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
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();                

                $u    = new Presta($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Buscar datos actuales del empleado
                $dat = $d->getGeneral1("Select idCar, idCcos from a_empleados where id=".$data->idEmp);
                // Buscar tipo de nomina en tipo de prestamo
                $dat2 = $d->getGeneral1("Select idTnom from n_tip_prestamo where id=".$data->idTpres);                
                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
   	                $connection->beginTransaction();                
                    
                    // Guardar en tabla de historicos , modificaciones en prestamos
                    $d->modGeneral("insert into n_prestamos_h ( idPres, fecDoc, fecApr, idTpres, idEmp,
                              idCcos, idCar, comen, idUsuReg, idUsu) 
                          ( select a.id, a.fecDoc, a.fecApr, a.idTpres, a.idEmp, a.idCcos,
                            a.idCar, a.comen, a.idUsu, ".$dt['idUsu']."  
                            from n_prestamos a where a.id=".$data->id." ) ");

                    $d->modGeneral("insert into n_prestamos_tn_h 
                       (idPres, idIpres, idTnom, valor, saldoIni, pagado, cuotas, valCuota  )
                        ( select a.idPres, a.id, a.idTnom, a.valor, a.saldoIni, a.pagado, a.cuotas, a.valCuota  
                            from n_prestamos_tn a where a.idPres=".$data->id." ) ");


                    $idPres = $u->actRegistro($data, $dat['idCar'], $dat['idCcos'], $dat2['idTnom'] );
                    
                    ////// Guardar distribucion de pagos en tipos de nominas //// ---
                    $datTnom = $d->getGeneral1("select idTpres from n_prestamos where id=".$idPres);                    

                    //$d->modGeneral("Delete from n_prestamos_tn where idPres=".$idPres);                 
                    $datos = $d->getPresCuotas($datTnom['idTpres'], $idPres );
                    $f    = new Prestan($this->dbAdapter);
                    $valorT = 0;
                    foreach ($datos as $dato){ 
                        $idLc = $dato['idTnom'];
                        $texto = '$data->valor'.$idLc;                        
                        eval("\$valor = $texto;"); 

                        if ($valor > 0) 
                        {
                            $texto = '$data->cuotas'.$idLc;                        
                            eval("\$cuotas = $texto;");                        

                            $texto = '$data->vcuotas'.$idLc;                        
                            eval("\$vcuotas = $texto;");                                                    
                            // Consultar pagos y saldos iniciales del tipo de prestamo
                            $datTnomPg = $d->getGeneral1("select count(id) as num from n_prestamos_tn where idPres=".$id." and idTnom=".$idLc); 
                            if ( $datTnomPg['num'] == 0 )
                               $f->actRegistro( $idPres,$idLc,$valor,$cuotas );                       
                            else
                            {
                               $d->modGeneral("update n_prestamos_tn 
                                   set valor=".$valor.", cuotas=".$cuotas.", valCuota =".$vcuotas." where idPres=".$id." and idTnom=".$idLc);    
                             }
                            $valorT = $valorT + $valor;  
                        }
                    }                
                    $d->modGeneral("update n_prestamos set valor=".$valorT." where id=".$idPres);                 
                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$idPres);
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
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Presta($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comenN")->setAttribute("value",$datos['comen']); 
            $form->get("numero")->setAttribute("value",0); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("idTpres")->setAttribute("value",$datos['idTpres']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("nombre")->setAttribute("value",$datos['docRef']); 
            $form->get("fecDoc")->setAttribute("value",$datos['fecDoc']);             
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
            $u=new AlbumTable($this->dbAdapter);
            // INICIO DE TRANSACCIONES
            $connection = null;
            try 
            {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                            
                // Se borra tabla de tipos de nominas afectadas por el prestamo para descuento 
                $u->modGeneral("delete from n_prestamos_tn where idPres=".$id); //                       
                $u=new Presta($this->dbAdapter);  
                $u->delRegistro($id);
                $connection->commit();
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
                $this->flashMessenger()->addMessage('');
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
            $datos = $u->getGeneral1("select a.idTnom, b.idTcal from n_tip_prestamo a 
                        inner join n_tip_nom b on b.id=a.idTnom
                        where a.id=".$data->idTpres);
            // Buscar datos del periodo
            $datos = $u->getCalenIniFin2($idGrup, $datos['idTcal'], $datos['idTnom']); 
            $arreglo = '';
            foreach ($datos as $dat){
                $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
                $arreglo[$idc]= $nom;
                break; 
            }  
            // Comprar el periodo que se intenta guardar
            $date   = new \DateTime(); 
            $fecSis = $date->format('Y-m-d');        
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

    // Detalle abonos a prestamos
    public function listpgAction()
    {
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);         
           $valores=array
           (
              "datTit" => $d->getGeneral1("select a.valor, b.nombre as nomTnom   
                           from n_prestamos a 
                               inner join n_tip_prestamo b on b.id = a.idTpres
                               where a.id = ".$data->id), # Abonos por nomina            
              "datos" => $d->getGeneral("select a.fechaI, a.fechaF, b.deducido, ( b.saldoPact - b.deducido ) as saldoPact , 
                     sum(c.valor) as valor, sum(c.valCuota) as valCuota , sum(c.saldoIni ) as saldoIni, 
                       e.nombre as nomTpres , f.idUsu, f.fecha 
                     from n_nomina a
                           inner join n_nomina_e_d b on b.idNom = a.id 
                           inner join n_prestamos_tn c on c.id = b.idCpres   
                           inner join n_prestamos d on d.id = c.idPres 
                           inner join n_tip_prestamo e on e.id = d.idTpres 
                           left join n_nomina_pres f on f.fechaI = a.fechaI and f.idPres = d.id 
                               where a.estado=2 and d.id = ".$data->id." group by b.id, c.id "), # Abonos por nomina
              "datAbo" => $d->getAbonosExtra($data->id), # Dato abonos extraordinarios             
            );
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoC'); // Layout del login
           return $view;           
        }
      }         
    }           

    // abonos extraordinarios en prestamos
    public function listbAction()
    {
        $tipo = $this->params()->fromRoute('id', 0);        
        $id = (int) $this->params()->fromRoute('id', 0);
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $con = "a.idTpres = ".$id; 
        if ( substr($tipo,0,1)==0) // Actulizar datos
        {
              $con ="a.idEmp = ".$id;
        }
        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getGeneral("select a.id, a.fecDoc,fecApr,b.nombre,b.apellido,b.CedEmp, 
                                            c.nombre as nomcar, d.nombre as nomccos, a.estado
                                            , e.nombre as nomTpres, sum(f.saldoIni) + sum(f.pagado) as pagado,
                                            sum(f.valor) as valor
                                            from n_prestamos a 
                                            inner join a_empleados b on a.idEmp=b.id 
                                            left join t_cargos c on c.id=b.idCar
                                            inner join n_cencostos d on d.id=b.idCcos
                                            inner join n_tip_prestamo e on e.id = a.idTpres 
                                            inner join n_prestamos_tn f on f.idPres = a.id 
                                            where ".$con." 
                                            group by a.id
                                            order by a.fecDoc desc "),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,            
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado                
        );                

        $view = new ViewModel($valores);        
        $this->layout('layout/blancoI'); // Layout del login
        return $view;      
        
    } // Fin listar registros     

   // Guardar pagos extras *********************************************************************************************
   public function listpgeAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = New AlbumTable($this->dbAdapter);      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {
            $t = new LogFunc($this->dbAdapter);
            $dt = $t->getDatLog();                
            $data = $this->request->getPost();
            //print_r($data);
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                $connection = $this->dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();                
                    
                    $d->modGeneral("insert into n_abonos_presta (idPres, fecDoc, docRef, valor, comen, idUsu )
                       values(".$data->id.", '".$data->fecDoc."','".$data->docRef."',".$data->numero1.", '".$data->comenN."', ".$dt['idUsu'].")");                 
                    // Modificar 
                    $d->modGeneral("update n_prestamos set abonosExtra = abonosExtra + ".$data->numero1." where id=".$data->id);

                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$data->id);
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
   } // Fin actualizar datos 

    // Detalle cuotas programadas carga ventana modal
    public function listproAction()
    {
      $form = new Formulario("form");      
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $form->get("id")->setAttribute("value",$id); 
           $form->get("id2")->setAttribute("value",$data->idTnom);

           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $f=new NominaFunc($this->dbAdapter);    
           $d=new AlbumTable($this->dbAdapter);         

           $datos = $d->getGeneral1("select idEmp, b.idGrup  
                                       from n_prestamos a
                                         inner join a_empleados b on b.id = a.idEmp
                                           where a.id=".$data->id); 
           $idEmp = $datos['idEmp'];
           $idGrupo = $datos['idGrup'];

           $datos = $d->getGeneral1("select nombre,tipo from n_tip_nom where id=".$data->idTnom);
           $tipo = $datos['tipo']; 

           $arreglo='';
           $fecIni=''; // Matriz de datos de als fechas iniciales
           $fecFin=''; // Matriz de datos de als fechas finales           

           $perPro = 5; 
           // Calendario
           if ($tipo==2) // Calendario de vacaciones
           {
              $dat = $d->getVacaEmpA($idEmp); // Consulta libro de vacaciones
              $i=1;
              foreach ($dat as $dat) 
              {
                 $ano = $dat['ano'];
                 $mes = $dat['mes'];
                 $dia = $dat['dia'];                
                 $anof=$ano+1; 
                 $arreglo[$i] = '('.$ano.'-'.$mes.'-'.$dia.') - ('.$anof.'-'.$mes.'-'.$dia.')'; 
                 $fecIni[$i] = $ano.'-'.$mes.'-'.$dia;  
                 $fecFin[$i] = $anof.'-'.$mes.'-'.$dia;                  
                 $i++;
              }
              // recrear periodos en caso tal no existan en el libro de vacaciones , se va al libro de contratos
              if ($arreglo=='')
              {
                $dat = $d->getContEmpA($idEmp); // Contratos activos en vacaciones
                $ano = $dat['ano'];
                $mes = $dat['mes'];
                $dia = $dat['dia'];                
                $i=1;
                while( $i<=$perPro )
                {
                   $anof=$ano+1; 
                   $arreglo[$i] = '('.$ano.'-'.$mes.'-'.$dia.') - ('.$anof.'-'.$mes.'-'.$dia.')'; 
                   $fecIni[$i] = $ano.'-'.$mes.'-'.$dia;  
                   $fecFin[$i] = $anof.'-'.$mes.'-'.$dia;  
                   $ano ++;
                   $i++;
                }
              }
           }
           if ( ($tipo==3) or ($tipo==1) )// Calendario de primas
           {
              $g = new Gnominag($this->dbAdapter);
              $datos = $d->getCalendario($data->idTnom);                    
              //--
              $dias   = $datos['valor'];
              $idCal  = $datos['idTcal'];
              $datos = $d->getGeneral1("select case when b.id is null
                                    then year( now() ) else year( b.fechaI ) end as ano,
                                       case when b.id is null then month( now() ) else month( b.fechaI ) end as mes,
                                       case when b.id is null then day( now() ) else day( b.fechaI ) end as dia       
                                    from  n_tip_nom a 
                                      left join n_tip_calendario_d b on b.idTnom=a.id and b.estado=0 and b.idCal = a.idTcal and b.idGrupo= ".$idGrupo."   
                                    where a.id = ".$data->idTnom." order by b.fechaI asc limit 1");
              $ano = $datos['ano']; 
              $mes = $datos['mes']; 
              $dia = $datos['dia']; 
              for($i=1; $i<=$perPro; $i++) // Crear 10 periodos siempre para armar calendario de pago
              {
                 // INICIO DE TRANSACCIONES
                 $connection = null;
                 try 
                 {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                           
                    // Contenido
                    $g->getGenerarPro($data->idTnom, $idGrupo, $idCal, $ano); // Generar calendario                                             
                    // Fin Contenido 
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
                 $ano ++;
              }
              // Periodos activos
              //echo 'GRupos '.$idGrupo.' '.$idCal.' '.$data->idTnom;
              $dat = $d->getCalenIniFin2($idGrupo, $idCal, $data->idTnom); // Contratos activos en vacaciones
              //print_r($dat);
              $i=1;              
              foreach($dat as $dat) 
              {
                 $anof=$ano+1; 
                 $arreglo[$i] = '('.$dat['fechaI'].') - ('.$dat['fechaF'].')'; 
                 $fecIni[$i] = $dat['fechaI'];  
                 $fecFin[$i] = $dat['fechaF'];                   
                 $i++;
              }                            
           }
           if ($tipo==1) // Calendario de cesantias
           {
           }                      
           if ( $arreglo != '')
              $form->get("idCal")->setValueOptions($arreglo);                    

           $valores=array
           (
              "datos"   => $datos,
              "form"    => $form,
              'url'     => $this->getRequest()->getBaseUrl(),
              "lin"     => $this->lin,
              "fechaI"  => $fecIni,                          
              "fechaF"  => $fecFin,                              
            );
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoC'); // Layout del login
           return $view;           
        }
      }         
    }// Fin detalle cuotas programadas

    // Guardar abono a prestamos programados
    public function listprogAction()
    {
      $form = new Formulario("form");
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();   
        if ($request->isPost()) 
        {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d = new AlbumTable($this->dbAdapter);         
           $t = new LogFunc($this->dbAdapter);
           $dt = $t->getDatLog();                          
           
           if ( $data->tipo == 1 )// Guardar nuevas cuotas
           { 
             // INICIO DE TRANSACCIONES
             $connection = null;
             try {
                  $connection = $this->dbAdapter->getDriver()->getConnection();
                  $connection->beginTransaction();                                  
                  $fechaI = $data->anoI.'-'.$data->mesI.'-'.$data->diaI;
                  $fechaF = $data->anoF.'-'.$data->mesF.'-'.$data->diaF;
                  $d->modGeneral("insert into n_prestamos_pro (idPres, idTnom, fechaI, fechaF , valor, idUsu  )
                       values(".$data->idPres.", ".$data->idTnom.", '".$fechaI."','".$fechaF."',".$data->valor.", ".$dt['idUsu'].")"); 
                    // Modificar 
                    //$d->modGeneral("update n_prestamos set pagado = pagado + ".$data->valor2." where id=".$data->id);
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
            }// Fin tipo 1
            $valores=array
            (
                "datos"     =>  $d->getGeneral("select * 
                                        from n_prestamos_pro a
                                          where a.idPres = ".$data->idPres." and a.idTnom=".$data->idTnom." order by a.id "),            
                "ttablas"   =>  $this->ttab,
                'url'     => $this->getRequest()->getBaseUrl(),
                "lin"       =>  $this->lin,            
                "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado                
            );                
            $view = new ViewModel($valores);        
            $this->layout('layout/blancoC'); // Layout del login
            return $view;           
          }
        }         
      }                          
   // Eliminar cuota programada ********************************************************************************************
   public function listprodAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);
            $dat = $u->getGeneral1("select idPres from n_prestamos_pro where id=".$id);                        
            $u->modGeneral("delete from n_prestamos_pro where id = ".$id);                        
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$dat['idPres']);
          }          
   }            


   // Financiar prestamo *********************************************************************************************
   public function listrefAction() 
   { 
      $form = new Formulario("form");
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) 
        {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $t = new LogFunc($this->dbAdapter);
            $dt = $t->getDatLog();                
            $d=new AlbumTable($this->dbAdapter);        
            $data = $this->request->getPost();                     
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                
                    
                    // Guardar en tabla de historicos , modificaciones en prestamos
                    $d->modGeneral("insert into n_prestamos (idPresRef, fecDoc, fecApr, idTpres, idEmp,  idCcos, idCar, valor, comen ) (
                              select a.id, a.fecDoc, a.fecApr, a.idTpres, a.idEmp, a.idCcos, a.idCar, a.valor, '".$data->comenN2."' 
                                 from n_prestamos a where a.id = ".$data->id." )");
                    $datId = $d->getGeneral1("SELECT LAST_INSERT_ID() as id");
                    $idI = $datId['id'];                    
                    // Detalle de prestamos y cuotas
                    $d->modGeneral("insert into n_prestamos_tn ( idPres, idTnom, valor, 
                                   saldoIni, pagado, cuotas, valCuota, estado )
                                  (select ".$idI.", a.idTnom, a.valor, 
                                  a.saldoIni, a.pagado, a.cuotas, a.valCuota, a.estado
                                  from n_prestamos_tn a where a.idPres = ".$data->id." )");
                    // Detalle de cuotas programadas
                    $d->modGeneral("insert into n_prestamos_pro ( fecha, idPres, idTnom, valor, pagado   ) 
                                      (select a.fecha, ".$idI.", a.idTnom, a.valor, a.pagado  
                                           from n_prestamos_pro a where a.idPres = ".$data->id.")");

                    // Abonos extraordinarios 
                    $d->modGeneral("insert into n_abonos_presta ( idPres, fecha, fecDoc, docRef, valor,
                                             pagado, cuotas, valCuota, comen  ) 
                                      (select ".$idI.", a.fecha, a.fecDoc, a.docRef, a.valor, a.pagado, 
                                        a.cuotas, a.valCuota, a.comen 
                                                from n_abonos_presta a where a.idPres=".$data->id." )");                    

                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$idI);
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
   } // Fin refinanciar prestamo

    public function listtpAction()
    {
        $tipo = $this->params()->fromRoute('id', 0);        
        $id = (int) $this->params()->fromRoute('id', 0);
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $con = "a.idTpres = ".$id; 
        if ( substr($tipo,0,1)==0) // Actulizar datos
        {
              $con ="a.idEmp = ".$id;
        }
        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getGeneral("select b.id, b.nombre as nomTpres 
                                            from n_tip_prestamo b "),            
            "ttablas"   =>  "tipo de prestamo, items",
            "lin"       =>  $this->lin,            
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado                
        );                

        $view = new ViewModel($valores);        
        return $view;              
    } // Fin tipo de prestamos para ingresos rapidos


   // Editar y nuevos datos *********************************************************************************************
   public function listtpaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);                       
      // Sedes
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = New AlbumTable($this->dbAdapter);      
      // Tipos de nomina
      $arreglo='';
      $datos = $d->getTnom('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTnom")->setValueOptions($arreglo);                    
      // Empleados     
      $arreglo='';
      $datos = $d->getEmp('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);              
      
      $dat = $d->getGeneral1("select * 
                                    from n_tip_prestamo where id = ".$id);
      $nomTpres = $dat['nombre'];
      $form->get("id2")->setAttribute("value", $dat['idTnom'] ); // Tipo de nomina afectada

      $valores=array
      (
           "titulo"  => "Prestamo ".$nomTpres,
           "form"    => $form,
           "datos"   => $d->getGeneral("select a.id, a.fecDoc,a.pagado, a.abonosExtra, a.estado,
                            c.CedEmp, c.nombre, c.apellido, b.cuotas, b.valCuota, b.valor  
                             from n_prestamos a
                                 inner join n_prestamos_tn b on b.idPres = a.id 
                                 inner join a_empleados c on c.id = a.idEmp 
                                 where a.tipo = 1  "),
           "ttablas" =>  ",No, Fecha, Empleado,Valor, Cuotas, Total, Saldo, Eliminar",
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
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $t = new LogFunc($this->dbAdapter);
                $dt = $t->getDatLog();                

                $data = $this->request->getPost();

                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                
                    //echo 'valor '.$data->idEmp;
                    $dat = $d->getGeneral1("select idCar, idCcos  
                                    from a_empleados where id = ".$data->idEmp);
                    $idCar = $dat['idCar'];                    
                    $idCcos = $dat['idCcos'];                    
                    // Guardar cabecera de prestamos
                    $d->modGeneral("insert into n_prestamos ( fecDoc, fecApr, idEmp, idTpres, idCar, idCcos, valor, idUsu, tipo, estado )
                       values ( '".$data->fecDoc."', '".$dt['fecSis']."', ".$data->idEmp.", ".$data->id.", ".$idCar.",
                        ".$idCcos.", ".$data->valorR." ,".$dt['idUsu'].", 1, 1) ");

                    $datId = $d->getGeneral1("SELECT LAST_INSERT_ID() as id");
                    $idI = $datId['id'];

                    // Guardar detalle de prestamos
                    $d->modGeneral("insert into n_prestamos_tn ( idPres, idTnom, valor, cuotas, valCuota )
                       values ( ".$idI.", ".$data->id2.", ".$data->valorR." ,".$data->ncuotas.",".($data->valorR/$data->ncuotas).") ");

                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'tpa/'.$data->id);
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
        
    } 

    return new ViewModel($valores);
     
   } // Fin actualizar datos    

   // Eliminar dato ********************************************************************************************
   public function listtpadAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);
            $u->modGeneral("delete from n_prestamos_tn where idPres=".$id);                        
            $u=new Presta($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'tp');
          }          
   }      

}
