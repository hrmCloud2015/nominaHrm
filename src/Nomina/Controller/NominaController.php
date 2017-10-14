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
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Nomina\Model\Entity\Nomina;        // Tabla n_nomina
use Nomina\Model\Entity\NominaE;       // Tabla n_nomina_e_d
use Nomina\Model\Entity\NominaE2;      // Table n_nomina_e

use Principal\Model\LogFunc; // Funciones especiales

use Principal\Model\Paranomina; // Parametros especiales de nomina
use Principal\Model\Retefuente; // Retefuente
use Principal\Model\Gnominag; // Procesos generacion de automaticos

class NominaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/nomina/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Nominas activas"; // Titulo listado
    private $tfor = "Generación de la nomina"; // Titulo formulario
    private $ttab = "Tipo de nomina, Periodo, Tipo de calendario, Grupo ,Empleados"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, d.nombre as nomtnom 
                                        from n_nomina a 
                                        inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom 
                                        where a.estado = 1 order by a.fechaF desc"),            
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
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idGrupo")->setValueOptions($arreglo);                         
      // Calendario de nomina
      $arreglo='';
      $datos = $d->getCalen(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCal")->setValueOptions($arreglo);                                           
      // Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                 
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
            $form->setValidationGroup('nombre'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Nomina($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $d=new AlbumTable($this->dbAdapter);
                $datos = $d->getCalen('and id='.$data->idCal);
                //--
                foreach ($datos as $dat){
                  $fecha=$dat['fecha'];$dias=$dat['dias'];
                } 
                $nuevafecha = strtotime ( '+'.$dias.' day' , strtotime ( $fecha ) ) ;
                $fechaf = date ( 'Y-m-j' , $nuevafecha );
                //
                $u->actRegistro($data,$fecha,$fechaf);
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
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
   // Listado de empelados por grupos o tipos de calendarios *****************************************************************************
   public function listgAction()
   {      
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Buscar grupo de nomina a liquidar
      $d = new AlbumTable($this->dbAdapter);
      //

      $datos = $d->getGeneral('select b.CedEmp, b.nombre, b.apellido  
                                   from n_nomina_e a
                                     inner join a_empleados b on b.id = a.idEmp  
                               where a.idNom = '.$id );
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['CedEmp'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  

      $valores=array
      (
        "titulo"    =>  "Nomina activa No ".$id,
        "form"      => $form,
        "ttablas"   =>  'Nombres, Apellidos, Cargo, C. Costo, Documento ',  
        "datArb"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos 
                                        from t_sedes a                                         
                                        inner join n_nomina_e d on d.idNom = ".$id." 
 					                         inner join a_empleados e on e.id = d.idEmp   
 					                         inner join n_cencostos b on b.idSed=a.id and b.id =  e.idCcos  
                                        inner join t_cargos c on c.id = e.idCar
                                        where b.estado = 0 
                                        group by b.id                                         
                                        order by a.nombre , b.id"),                     
        "datArbs"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos
                                       from t_sedes a 
                                        inner join n_cencostos b on b.idSed=a.id
                                        order by a.nombre , b.nombre"),                                     
        "lin"      =>  $this->lin,
        'url'      => $this->getRequest()->getBaseUrl(),
      );                
      return new ViewModel($valores);
      
        //"datArb"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos
          //                              from t_sedes a 
            //                            inner join n_cencostos b on b.idSed=a.id
              //                          order by a.nombre , b.nombre"),                           
      
      //select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos 
       //                                 from t_sedes a 
        //                                inner join n_cencostos b on b.idSed=a.id
         //                               inner join t_cargos c on c.idCcos = b.id 
          //                              inner join n_nomina_e d on d.idNom = 10 
//					inner join a_empleados e on e.idCar = c.id and e.id = d.idEmp   
 //                                       order by a.nombre , b.nombre
      
        
   } // Fin listar registros      
   
   
   //----------------------------------------------------------------------------------------------------------
   // NOVEDADES EN NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
    
   
   // Listado de empelados por grupos o tipos de calendarios *****************************************************************************
   public function listiAction()
   {      
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = $this->params()->fromRoute('id', 0);      
      $pos    = strpos($id, ".");      
      $idCcos = substr($id, $pos+1 , 100 );      
      $id = (int) substr($id, 0 , $pos ); // Id Nomina    
      
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Buscar grupo de nomina a liquidar
      $d = new AlbumTable($this->dbAdapter);
      $n = new NominaFunc($this->dbAdapter);
      $e = new NominaE($this->dbAdapter);
      //
      // Guardar cambios en novedades de nomina
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $data = $this->request->getPost();            
            //print_r($data);
            $id   = $data['id'];
            $iddn = $data['id4'];            
            for($i = 1; $i < count($data); $i++)
            {               
               $idi    = $data['idi'.$i];
               $horas  = $data['horas'.$i];
               $idccos = $data['idccos'.$i];
               $dev    = $data['dev'.$i];
               $ded    = $data['ded'.$i];
               if ($idi!='')
               {
                  $con2  = 'Update n_nomina_e_d Set horas='.$horas.', idCcos='.$idccos.', devengado='.$dev.', deducido='.$ded.'  Where id='.$idi ;     
                  $d->modGeneral($con2);                                                              
               }
            }
            // Recalculo de conceptos                                
            // *-------------------------------------------------------------------------------
            // ----------- RECALCULO DE DOCUMENTO DE NOMINA -----------------------------------
            // *-------------------------------------------------------------------------------            
            $u=new Gnominag($this->dbAdapter);
            $datos2 = $u->getDocNove($iddn, " and b.tipo in ('0','1','3')" );// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                                                  
            foreach ($datos2 as $dato)
            {                     
               $iddn    = $iddn;            // Id dcumento de novedad
               $idin    = $dato['id'];      // Id novedad
               $ide     = $dato['idEmp'];   // Id empleado
               $diasLab = $dato['dias'];    // Dias laborados 
               $diasVac = $dato['diasVac'];    // Dias vacaciones
               $horas   = $dato["horas"];   // Horas laborados 
               $formula = $dato["formula"]; // Formula
               $tipo    = $dato["tipo"];    // Devengado o Deducido  
               $idCcos  = $dato["idCcos"];  // Centro de costo   
               $idCon   = $dato["idCon"];   // Concepto
               $dev     = $dato["devengado"];     // Devengado
               $ded     = $dato["deducido"];     // Deducido  
               $idfor   = $dato["idFor"];   // Id de la formula                                  
               $fechaEje = '';
               if ( ($dato["tipo"]>0) or ($idfor != 1 ) )
               {               
                   // Llamado de funion -------------------------------------------------------------------
                  $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 1, 2,$dev,$ded,$idfor,0,0,0,0,0,$fechaEje);                                          
               }
            }

            // *-------------------------------------------------------------------------------
            // ----------- FIN RECALCULO DE DOCUMENTO DE NOMINA -----------------------------------            
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'in/'.$iddn);
        }
      }      
      // Traer el grupo de nomina para filtrar empleados
      $datos = $d->getGeneral("Select idGrupo from n_nomina where id=".$id); 
      $idg=0;
      foreach ($datos as $dat){
         $idg=$dat['idGrupo'];
      }
      //
      $valores=array
      (
        "titulo"    =>  "Nomina activa No ".$id,
        "form"      => $form,
        "ttablas"   =>  'Cedula, Nombres, Apellidos, Cargo, Documento ',  
        "datos"     =>  $d->getGeneral("select a.id ,a.CedEmp,a.nombre,a.apellido,b.idNom as idNom,
                           b.id as idInom, c.nombre as nomCcos, d.nombre as nomCar,
                            a.idVac,a.vacAct,a.idInc,a.idAus, e.idTnomL # si el calendario es de liquidacion     
                           from a_empleados a
                           inner join n_nomina_e b on a.id=b.idEmp 
                           inner join n_cencostos c on c.id=a.idCcos
                           inner join n_nomina e on e.id = b.idNom 
                           left join t_cargos d on d.id=a.idCar 
                           where a.idGrup=".$idg." and b.idNom=".$id." and c.id=".$idCcos),            
        "lin"       =>  $this->lin
      );                
      $view = new ViewModel($valores);
      $this->layout("layout/blancoI");
      return $view;
        
   } // Fin listar registros 
     
   
   
   // Datos de la nomina  ********************************************************************************************
   public function listinAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id4")->setAttribute("value",$id); // Id items de nomina por empleado
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $u=new Gnominag($this->dbAdapter);
      $n=new NominaFunc($this->dbAdapter);      
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          if ($request->isPost()) {
             $data = $this->request->getPost();        
             $datos = $d->getGeneral1("select a.id, a.idEmp  
                                          from n_nomina_e a
                                           inner join a_empleados b on b.id = a.idEmp
                                           where b.CedEmp='".$data->cedula."' and a.idNom=".$data->id);              
             $id = $datos['id'];
             return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'in/'.$id);
           }
      }      

      // Conceptos
      $arreglo='';
      $datos = $d->getConnom(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipVal'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConc")->setValueOptions($arreglo);                                                 
      // 
      $datos = $d->getGeneral1("Select idInom from n_nomina_e_d where idInom=".$id); 
      $idn   = $datos['idInom'];
      // BUscar datos del empleado y de la cabecera de la nomina
      $datos = $d->getGeneral1("Select a.idEmp, a.idNom, b.fechaI, b.fechaF, b.idTnom, b.idGrupo, b.idCal 
                                  from n_nomina_e a 
                                  inner join n_nomina b on b.id = a.idNom
                                  where a.id=".$id); 
      $ide    = $datos['idEmp'];
      $idnp   = $datos['idNom'];
      $fechaI = $datos['fechaI'];      
      $fechaF = $datos['fechaF'];      
      $idCal  = $datos['idCal'];      
      $idGrupo = $datos['idGrupo'];      

      // Buscar constantes de funciones
      $valores=array
      (
        "titulo"    =>  "Novedades",
        "form"      =>  $form,
        "ttablas"   =>  'CONCEPTOS, HORAS ,DEVENGADOS, DEDUCIDOS, CENTROS DE COSTOS,  ELIMINAR ',  
        "datos"     =>  $d->getGeneral("select a.id ,a.nombre,a.apellido,a.idCcos,b.id as idInom,b.idNom, b.dias, c.idTnomL    
                             from a_empleados a
                             inner join n_nomina_e b on a.id=b.idEmp 
                             inner join n_nomina c on c.id = b.idNom 
                             where a.id=".$ide." and b.idNom=".$idnp),            
        'url'       => $this->getRequest()->getBaseUrl(),  
        "datccos"   =>  $d->getCencos(),
        "lin"       =>  $this->lin.'i',
        "idp"       =>  '' // Adicional para retorno 
      );                
      return new ViewModel($valores);
        
   } // Fin listar registros      

   // DOCUMENTOS DE NOMINA
   public function listinovAction()
   {
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d = new AlbumTable($this->dbAdapter);
      $u = new Gnominag($this->dbAdapter);
      $g = new Gnominag($this->dbAdapter);
      $n = new NominaFunc($this->dbAdapter);      
      $e = new NominaE($this->dbAdapter); 
      $f = new NominaE2($this->dbAdapter); 
      $pn = new Paranomina($this->dbAdapter);
      
      $request = $this->getRequest();   
      $data = $this->request->getPost();
      $idn = $data->idInom; // Es importante para cuando no sea valido un formulario abajo mantenga el id actual             
      $tipoA = $data->tipo;

      $t = new LogFunc($this->dbAdapter);
      $dt = $t->getDatLog();

         $dp = $pn->getGeneral1(12);
         $topRetefuente = $dp['valorNum'];   // BASE RETEFUENTE 

      // BUscar datos del empleado y de la cabecera de la nomina
      $datos = $d->getGeneral1("Select a.idEmp, a.idNom, b.fechaI, b.fechaF, b.idTnom, 
                                  b.idGrupo, b.idCal, b.idIcal, b.idTnom   
                                from n_nomina_e a 
                                    inner join n_nomina b on b.id = a.idNom 
                                where a.id=".$idn); 
      $ide    = $datos['idEmp'];
      $idnp   = $datos['idNom'];
      $id     = $datos['idNom'];
      $fechaI = $datos['fechaI'];      
      $fechaF = $datos['fechaF'];      
      $idCal  = $datos['idCal'];  // 2:Quincenal - 9:Mensual - 8:Liquidacion
      $idIcal  = $datos['idIcal'];  
      $idGrupo = $datos['idGrupo'];      
      $idTnom = $datos['idTnom'];    
      $diasLab = $data->valor;
      $liqui = 0;
      $datos  = $d->getPerNomina($idnp); // Periodo de nomina
      $periodoNomina = $datos['periodo'];      
      // Nomina de liquidacion 
      if ( $idTnom == 6 )
      {
         $liqui = 1;
         $n->getLiqFinal( $idnp );    
      }      
      // INICIO DE TRANSACCIONES
      $connection = null;
      try 
      {
          $connection = $this->dbAdapter->getDriver()->getConnection();
          $connection->beginTransaction();                

        //if ($liqui!=110)
        //{  
          // Nueva novedad       
          if ( $tipoA == 0 )
          {                             
              $datos = $d->getGeneral1("Select valor,tipo, nombre 
                                         from n_conceptos 
                                           where id=".$data->idConc); 
              $valcon = $datos['valor'];
              $tipcon = $datos['tipo'];
              $nombre = "-".$datos['nombre'];
              $e->actRegistro($data,$valcon,$tipcon, $nombre);
              // Guardar registro 

              $dev = $data->valor;$ded = 0;
              if ( $tipcon == 2 ) 
              {
                  $ded = $data->valor;$dev = 0;
              } 
              $hor=0;
              if ( $valcon == 1 ) // Concepto por horas o cantidad
              {
                  $ded = 0;$dev = 0;
                  $hor = $data->valor;
              } 
              $d->modGeneral("delete from n_nomina_nov 
                               where idEmp=".$ide." and idGrupo=".$idGrupo."
                                  and fechaI='".$fechaI."' and nuevo = 1  
                          and fechaF='".$fechaF."' and idConc=".$data->idConc);

              $d->modGeneral("insert into n_nomina_nov (idEmp, fechaI, fechaF, idCal, idIcal, idGrupo, idConc, devengado, deducido, horas, idUsu, nuevo  ) 
                              values(".$ide.", '".$fechaI."','".$fechaF."', ".$idCal.", ".$idIcal.", ".$idGrupo.",".$data->idConc.",".$dev.",".$ded.",".$hor.", ".$dt['idUsu'].", 1 )");              
          }     
          // Cambio valores (hora,dev,ded,ccos)
//echo 'valor '.$tipoA;         
          if ( ($tipoA==1) )
          { 
              if ( isset($data->idNov) )
                   $idCon = $data->idNov;

              if ( isset($data->idConc) )
                   $idCon = $data->idConc;

              $datos = $d->getGeneral1("Select valor,tipo,nombre 
                                          from n_conceptos 
                                            where id=".$idCon); 
              $valcon = $datos['valor'];
              $tipcon = $datos['tipo'];
              $nombre = $datos['nombre'];
              //$e->edRegistro($data);                    
              $d->modGeneral("update n_nomina_e_d 
                   set devengado=".$data->dev.", detalle='_".$nombre."',  
                       deducido=".$data ->ded.", horas=".$data->hora.",idUsuM=".$dt['idUsu']." 
                    where id=".$data->idNov); 

              if ( $data->idCpres>0 ) // Guardar en tabla de registro de modificacion en prestamos
              {
                 $d->modGeneral("update n_nomina_pres set estado=1
                  where idEmp=".$ide." and idCal=".$idCal." and fechaI='".$fechaI."' and fechaF='".$fechaF."'
                     and idGrupo=".$idGrupo." and idPres=".$data->idCpres); // Inactivar cambio anterior en prestamo 

                 $datPres = $d->getGeneral1("select valCuota from n_prestamos_tn where id=".$data->idCpres);
                 $d->modGeneral("insert into n_nomina_pres (idEmp, idCal, fechaI, fechaF, idGrupo, idPres, valorCuota, valor, idUsu ) 
                 values(".$ide.", ".$idCal.", '".$fechaI."','".$fechaF."', ".$idGrupo.", ".$data->idCpres.", ".$datPres['valCuota']." ,".$data->ded.",".$dt['idUsu']." )");

               }else{ // Insertar para registro de modificaciones 

                  $dev = $data->dev;
                  $ded = $data->ded;
                  $hor = $data->hora;

                  $editado =  1;
                  if ($data->auto == 0)
                  {
                     $d->modGeneral("delete from n_nomina_nov 
                         where idEmp=".$ide." and idIcal=".$idIcal." and idConc=".$data->idConc);
                     //$editado = 0;
                  }  

                  //$d->modGeneral("delete from n_nomina_nov 
                  //  where idEmp=".$ide." and editado = 1 and idIcal=".$idIcal." and idConc=".$data->idConc);

                  $d->modGeneral("insert into n_nomina_nov 
                    (idEmp, fechaI, fechaF, idCal, idIcal, idGrupo, idConc, devengado, deducido, horas, idUsu, idInov, editado ) 
             values(".$ide.", '".$fechaI."','".$fechaF."', ".$idCal.", ".$idIcal.", ".$idGrupo.",".$data->idConc.",".$dev.",".$ded.",".$hor.", ".$dt['idUsu'].", ".$data->idInov.", ".$editado." )");

               }

          }                     
         // Guardado de dias por novedad 
         $tipCal = 0; 
         if ( $tipoA==6 ) 
         { 
              // Guardar dias laborador en novedades
              $d->modGeneral("delete from n_nomina_nov where idEmp=".$ide." and diasLab>0 and estado=0");                    
              $d->modGeneral("insert into n_nomina_nov (idEmp, fechaI, fechaF, idCal, idIcal, idGrupo, diasLab, horas ) 
                                  values(".$ide.", '".$fechaI."','".$fechaF."', ".$idCal.", ".$idIcal.", ".$idGrupo.", ".$diasLab.", ".($data->dias/8)." )");

             // Consultar si es nomina de primas 
             $datN = $d->getGeneral1("select diasPrimas, promPrimas 
                                        from n_nomina_e where id=".$data->idInom);
             $diasPrimas = $datN['diasPrimas'];
             $promPrimas = $datN['promPrimas'];             
             if ($promPrimas>0) // Valido si tiene promedio de primas para activar el swith 
             {
                 $tipCal = 1;
                 $d->modGeneral("update n_nomina_e 
                                    set diasPrimas = ".$data->valor."
                                           where id=".$data->idInom);
             }
                 $d->modGeneral("update n_nomina_e 
                                    set dias = ".$data->valor."
                                           where id=".$data->idInom);             
             // Reubicar dias laborados 
             $d->modGeneral("update n_nomina_e_d  
                                   set horas = ( ".$data->valor."*8 )  
                                      where idConc = 122 and idInom = ".$data->idInom);             
             // Editar valor de de conceptos que tengan horas y dependan de calendario
         }  


// *-------------------------------------------------------------------------------
          // ----------- RECALCULO DE DOCUMENTO DE NOMINA -----------------------------------
          // *-------------------------------------------------------------------------------
        if ( $tipoA > 0 ) 
        {          
          // Se colocan en 0 calculados para hacer de nuevo el calculo

          $datos2 = $u->getDocNove($idn, " and b.tipo in ('0','1')" );// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                                                  
          foreach ($datos2 as $dato)
          {      
                  $id = $data->idNom; // Id nomina  
                  $iddn    = $idn;            // Id dcumento de novedad
                  $idin    = $dato['id'];      // Id novedad
                  $ide     = $dato['idEmp'];   // Id empleado
                  $diasLab = $dato['dias'];    // Dias laborados 
                  $diasVac = $dato['diasVac'];    // Dias vacaciones
                  $horas   = $dato["horas"];   // Horas laborados 
                        //if ($horas>0)
                        //    $formula = '';
                        //else   
                  $formula = $dato["formula"]; // Formula

                  $tipo    = $dato["tipo"];    // Devengado o Deducido  
                  $idCcos  = $dato["idCcos"];  // Centro de costo   
                  $idCon   = $dato["idCon"];   // Concepto
                  $dev     = $dato["devengado"];     // Devengado
                  $ded     = $dato["deducido"];     // Deducido  
                  $idfor   = $dato["idFor"];   // Id de la formula   
                  $diasLabC= $dato["horDias"];   // Si se afecta el cambio de dias laborados en el registro 
                  $idProy = $dato["idProy"];
                  $fechaEje='';
                  // Caso especial cuando cambian la hroa, se buscan conceptos automaticos que sean afectados por dias laborados
                  //if ( ($tipo==1) and ($tipoA==6) ) // Solo aplica para automaticos el cambio de dai para calcular las horas
                  //{                 
                  //    if ($diasLabC==1) 
                  //    {
                  //        $diasLab = $data->valor; 
                  //        $horas   = $diasLab*8; //Horas por dia
                  //    }
                  //}                        
                  if ( ($tipCal==1) and ($idCon==214) ) // Valida si es primas y el concepto de primas 
                  {
                      $dev = $promPrimas * $data->valor;   
                  }
                  if ( $dato["idTnom"] == 4 ) // Cuando es nomina manual toma los dias 
                       $diasLab = $dato["dias"]; 
                        // Llamado de funion -------------------------------------------------------------------
                        //if ($dato["tipo"]>1) // Solo permite actualizar las novedades 
                        //{                        
                    $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 1, 2,$dev,$ded,$idfor,$diasLabC,0,0,0,0,$fechaEje,$idProy);                                                       
                        // }
              //}
          }  // Valdiar cambiar sueldo       


        }// Fin validacion recalculo sin edicion 

          // Se colocan en 0 calculados para hacer de nuevo el calculo
          // casos salud, pension, que se deben recalcular 
          // casos salud, pension, que se deben recalcular 
       
          $d->modGeneral("update n_nomina_e_d 
                   set devengado = 0, deducido = 0
                      where tipo in ('3') and idInom = ".$idn); 

          $datos2 = $u->getDocNove($idn, " and b.tipo in ('3')" );// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                                                  
          foreach ($datos2 as $dato)
          {
                  $id = $data->idNom; // Id nomina  
                  $iddn    = $idn;            // Id dcumento de novedad
                  $idin    = $dato['id'];      // Id novedad
                  $ide     = $dato['idEmp'];   // Id empleado
                  $diasLab = $dato['dias'];    // Dias laborados 
                  $diasVac = $dato['diasVac'];    // Dias vacaciones
                  $horas   = $dato["horas"];   // Horas laborados 
                        //if ($horas>0)
                        //    $formula = '';
                        //else   
                  $formula = $dato["formula"]; // Formula

                  $tipo    = $dato["tipo"];    // Devengado o Deducido  
                  $idCcos  = $dato["idCcos"];  // Centro de costo   
                  $idCon   = $dato["idCon"];   // Concepto
                  $dev     = $dato["devengado"];     // Devengado
                  $ded     = $dato["deducido"];     // Deducido  
                  $idfor   = $dato["idFor"];   // Id de la formula   
                  $diasLabC= $dato["horDias"];   // Si se afecta el cambio de dias laborados en el registro 
                  $idProy = $dato["idProy"];
                  $calc = 0;
                  $fechaEje='';
                    $n->getNomina($id, $iddn, $idin, $ide ,$diasLab,$diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 1, 2,$dev,$ded,$idfor,$diasLabC,0,$calc,0,0,$fechaEje,$idProy);                                                       
          }  // Valdiar seguridad social 

          // Recalculo fondo de solidaridad general de toda la nomina
          $soli = 0;
          if ( ($periodoNomina==2) and ($idCal == 2) )
             $soli = 1;
          if ( ($idCal == 9) or ($idCal == 6) )
             $soli = 1;          
          

          if ($soli == 1)
          {  
              $d->modGeneral("delete from n_nomina_e_d 
                               where idConc = 21 and idInom = ".$idn);

              $datos2 = $d->getGeneral("Select a.id, a.idEmp , e.idCcos, year(b.fechaI) as ano , month(b.fechaI) as mes, e.vacAct     
                from n_nomina_e a 
                  inner join a_empleados e on e.id = a.idEmp
                  inner join n_nomina b on b.id = a.idNom 
               where a.id = ".$idn ); 
              foreach ($datos2 as $dato)
              {             
                 if ( ( ($dato['vacAct']==0) or ($dato['vacAct']==2) ) ) // En actividad y en retoñrno 
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
                   //if ( $calendario != 9 ) // Si es calendario mensual no se tiene en cuenta la busqueda atras de solidaridad para desocntar
                   //{ 
                      $datAnt  = $u->getFondSolAnt($ano, $mes,$ide, $id, 21);// Concepto de fondo de solidaridad
                      $dedAnt = 0;                
                      if ( $datAnt['deducido'] > 0 )
                      { 
                         $dedAct = $ded;                                          
                         $ded = $ded - $datAnt['deducido'];
                         $dedAnt = $datAnt['deducido'];  
                      }                  
                   //}   
                   $idInom = $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 0, 3,$dev,$ded,$idfor,$diasLabC,0,0,$conVac,$obId,$fechaEje,$idProy);
                   $idInom = (int) $idInom;                   
                   // Colocar saldo del prestamo
                   if ( $dedAnt > 0 )
                       $d->modGeneral("update n_nomina_e_d 
                     set detalle='FONDO DE SOLIDARIDAD (ANT ".number_format($datAnt['deducido'])."- ACT ".number_format($dedAct)." ) ' where id=".$idInom);                                   

                  }
                }
             }// FIN RECORRIDO FONDO DE SOLIDARIDAD               
           } // Fin validacion fondo de solidaridad

        $d->modGeneral("delete from n_nomina_e_d 
               where idConc = 10 and idInom = ".$idn);                    

        // RETENCION DE LA FUENTE
        $r = new Retefuente($this->dbAdapter);
        $datos2 = $g->getRetFuente($id , 0);// 
        foreach ($datos2 as $dato)
        {                 
           if ( ( $dato['valor'] > $topRetefuente ) or ( $dato['proce'] == 2 )   )
           {  
             if ( $dato['id'] == $idn )
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
           }             
          } // FIN RETENCION DE LA FUENTE
          if (isset($idn))
          {  
          // BUscar edicaiones en prestamos 
          $datos = $d->getGeneral("select b.id, 
                                        ( select aa.valor 
                                             from n_nomina_pres aa  
                                               where aa.estado=0 and aa.idPres = b.idCpres limit 1) as valor    
                                    from n_nomina_e_d b 
                                      where b.idCpres>0 and b.idInom=".$idn);
          foreach ($datos as $dat) 
          {
              if ($dat['valor']>0)
              {  
                // $d->modGeneral("update n_nomina_e_d 
                  //              set deducido = ".$dat['valor']." 
                    //              where id =".$dat['id']);
              }   
          }
        }

       //  }// fin validacion si es liquidacion o no 
          $connection->commit();                   
          $this->flashMessenger()->addMessage('');                         
        }// Fin try casth   
        catch (\Exception $e) 
        {
           if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
              $connection->rollback();
              echo $e;
          } 
              /* Other error handling */
        }// FIN TRANSACCION                                          
      
      // Buscar constantes de funciones
      $valores=array
      (
        "formn"     => $form,
        "ttablas"   =>  'CONCEPTO, HORAS ,DEVENGADO, DEDUCIDO, CENTROS DE COSTO ',  
        'url'       => $this->getRequest()->getBaseUrl(),  
        "datNau"    =>  $u->getDocNove($idn," and b.tipo = 0 and d.info = 0"),// Novedades 
        "datTau"    =>  $u->getDocNove($idn," and b.tipo in ('1','2')"),// Automaticos
        "datCau"    =>  $u->getDocNove($idn," and b.tipo = 3"),// Calculados
        "datOau"    =>  $u->getDocNove($idn," and b.tipo = 4"),// Programados
        "datIau"    =>  $u->getDocNove($idn," and b.tipo = 0 and d.info = 1"),// Novedades informativas
        "datNauN"   =>  $u->getDocNoveN($idn," and b.tipo = 0 and d.info = 0"),// Numero de novedades -----------------------------         
        "datTauN"   =>  $u->getDocNoveN($idn," and b.tipo in ('1','2')"),// Automaticos
        "datCauN"   =>  $u->getDocNoveN($idn," and b.tipo = 3"),// Calculados
        "datOauN"   =>  $u->getDocNoveN($idn," and b.tipo = 4"),// Programados        
        "datIauN"   =>  $u->getDocNoveN($idn," and b.tipo = 0 and d.info = 1"),// Novedades informativas
        "datPresM"  =>  $d->getGeneral("select a.*, d.nombre as nomTpres, e.usuario    
                                         from n_nomina_pres a 
                                          inner join n_prestamos_tn b on b.id = a.idPres
                                          inner join n_prestamos c on c.id = b.idPres
                                          inner join n_tip_prestamo d on d.id = c.idTpres
                                          inner join users e on e.id = a.idUsu
                                          where a.idEmp=".$ide." 
                                          and a.fechaI='".$fechaI."' and a.fechaF='".$fechaF."' "),// Prestamos o programados modificados
        "datcon"    =>  $d->getConnom(),
        "datccos"   =>  $d->getCencos(),
        "lin"       =>  $this->lin.'i', 
      );
      $view = new ViewModel($valores);        
      $this->layout('layout/blancoB'); // Layout del login
      return $view;                    
   }// FIN DOCUMENTOS DE NOMINA
   
   // ELIMINACION DE ITEMS DE NOVEDADES 
   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $a = new NominaE($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $d = New AlbumTable($this->dbAdapter);  
            $u = new Gnominag($this->dbAdapter);
            $n = new NominaFunc($this->dbAdapter); 
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
               $connection = $this->dbAdapter->getDriver()->getConnection();
   	           $connection->beginTransaction();            
             
                // Borrar registro de prima de antiguedad si lo hubiera
                $u->modGeneral("delete from n_pg_primas_ant where idInom=".$id);            
                // bucar id de parametro
                $datos = $d->getGeneral1("select a.idInom,a.idNom, a.idConc , b.idIcal, c.idEmp   
                                   from n_nomina_e_d a 
                                     inner join n_nomina b on b.id = a.idNom 
                                     inner join n_nomina_e c on c.id = a.idInom 
                                   where a.id = ".$id);// Listado de formularios                                
                $a->delRegistro($id);
                // Borrar de la tabal de novedades de nomina en vivo
                $u->modGeneral("delete from n_nomina_nov 
                                        where estado = 0 and idIcal=".$datos['idIcal']." and idConc=".$datos['idConc']);            
            
                // *-------------------------------------------------------------------------------
                 // ----------- RECALCULO DE DOCUMENTO DE NOMINA -----------------------------------
                // *-------------------------------------------------------------------------------
               
                $idn = $datos['idInom'];
                $datos2 = $u->getDocNove($idn, " and b.tipo in ('0','1','3')" );// Insertar nov automaticas ( n_nomina_e_d ) por tipos de automaticos                                                  
                foreach ($datos2 as $dato)
                {         
                    $id = $datos['idNom']; // Id nomina  
                    $iddn    = $idn;            // Id dcumento de novedad
                    $idin    = $dato['id'];      // Id novedad
                    $ide     = $dato['idEmp'];   // Id empleado
                    $diasLab = $dato['dias'];    // Dias laborados 
                    $diasVac = $dato['diasVac'];    // Dias vacaciones
                    $horas   = $dato["horas"];   // Horas laborados 
                    $formula = $dato["formula"]; // Formula
                    $tipo    = $dato["tipo"];    // Devengado o Deducido  
                    $idCcos  = $dato["idCcos"];  // Centro de costo   
                    $idCon   = $dato["idCon"];   // Concepto
                    $dev     = $dato["devengado"];     // Devengado
                    $ded     = $dato["deducido"];     // Deducido  
                    $idfor   = $dato["idFor"];   // Id de la formula                                  
                    $fechaEje = '';
                    $idProy = 0;
                    // Llamado de funion -------------------------------------------------------------------
                    $n->getNomina($id, $iddn, $idin, $ide ,$diasLab, $diasVac ,$horas ,$formula ,$tipo ,$idCcos , $idCon, 1, 2,$dev,$ded,$idfor,0,0,0,0,0,$fechaEje,$idProy);                                          
                }                            
               // FIN TRANSACCION 
               $connection->commit();
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'in/'.$datos['idInom']);
            }// Fin try casth   
            catch (\Exception $e) {
    	        if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	           $connection->rollback();
                   echo $e;
 	        }	
 	        /* Other error handling */
            }// FIN TRANSACCION                                    
            
            
          }          
   }// Fin eliminar datos   
   

   // DATOS DE LIQUIDACION FINAL  ********************************************************************************************
   public function listliqAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id4")->setAttribute("value",$id); // Id items de nomina por empleado
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $u=new Gnominag($this->dbAdapter);
      $n=new NominaFunc($this->dbAdapter);      
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          if ($request->isPost()) {
             $data = $this->request->getPost();        
             $datos = $d->getGeneral1("select a.id, a.idEmp  
                                          from n_nomina_e a
                                           inner join a_empleados b on b.id = a.idEmp
                                           where b.CedEmp='".$data->cedula."' and a.idNom=".$data->id);              
             $id = $datos['id'];
             return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'in/'.$id);
           }
      }      

      // Conceptos
      $arreglo='';
      $datos = $d->getConnom(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipVal'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConc")->setValueOptions($arreglo);                                                 
      // 
      $datos = $d->getGeneral1("Select idInom from n_nomina_e_d where idInom=".$id); 
      $idn   = $datos['idInom'];
      // BUscar datos del empleado y de la cabecera de la nomina
      $datos = $d->getGeneral1("Select a.idEmp, a.idNom, b.fechaI, b.fechaF, b.idTnom, b.idGrupo, b.idCal 
                                  from n_nomina_e a 
                                  inner join n_nomina b on b.id = a.idNom
                                  where a.id=".$id); 
      $ide    = $datos['idEmp'];
      $idnp   = $datos['idNom'];
      $fechaI = $datos['fechaI'];      
      $fechaF = $datos['fechaF'];      
      $idCal  = $datos['idCal'];      
      $idGrupo = $datos['idGrupo'];      

      // Buscar constantes de funciones
      $valores=array
      (
        "titulo"    =>  "Novedades",
        "form"      =>  $form,
        "ttablas"   =>  'CONCEPTOS, HORAS ,DEVENGADOS, DEDUCIDOS, CENTROS DE COSTOS,  ELIMINAR ',  
        "datos"     =>  $d->getGeneral("select a.id ,a.nombre,a.apellido,a.idCcos,b.id as idInom,b.idNom, b.dias 
                             from a_empleados a
                             inner join n_nomina_e b on a.id=b.idEmp where a.id=".$ide." and b.idNom=".$idnp),            
        'url'       => $this->getRequest()->getBaseUrl(),  
        "datccos"   =>  $d->getCencos(),
        "lin"       =>  $this->lin.'i',
        "idp"       =>  '' // Adicional para retorno 
      );                
      return new ViewModel($valores);
        
   } // FIN DATOS DE LIQUIDACION FINAL 


   // modificaciones retro  *****************************************************************************
   public function listretrogAction()
   {      
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Buscar grupo de nomina a liquidar
      $d = new AlbumTable($this->dbAdapter);
      //

      $datos = $d->getEmp('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['CedEmp'];$nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  

      $valores=array
      (
        "titulo"    =>  "Nomina activa No ".$id,
        "form"      => $form,
        "ttablas"   =>  'Nombres, Apellidos, Cargo, C. Costo, Documento ',  
        "datArb"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos 
                                        from t_sedes a                                         
                                        inner join n_nomina_e d on d.idNom = ".$id." 
                                   inner join a_empleados e on e.id = d.idEmp   
                                   inner join n_cencostos b on b.idSed=a.id and b.id =  e.idCcos  
                                        inner join t_cargos c on c.id = e.idCar
                                        where b.estado = 0 
                                        group by b.id                                         
                                        order by a.nombre , b.id"),                     
        "datArbs"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos
                                       from t_sedes a 
                                        inner join n_cencostos b on b.idSed=a.id
                                        order by a.nombre , b.nombre"),                                     
        "lin"      =>  $this->lin,
        'url'      => $this->getRequest()->getBaseUrl(),
      );                
      return new ViewModel($valores);
      
        //"datArb"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos
          //                              from t_sedes a 
            //                            inner join n_cencostos b on b.idSed=a.id
              //                          order by a.nombre , b.nombre"),                           
      
      //select a.id as idSed, a.nombre as nomSed, b.nombre as nomCcos, b.id as idCcos 
       //                                 from t_sedes a 
        //                                inner join n_cencostos b on b.idSed=a.id
         //                               inner join t_cargos c on c.idCcos = b.id 
          //                              inner join n_nomina_e d on d.idNom = 10 
//          inner join a_empleados e on e.idCar = c.id and e.id = d.idEmp   
 //                                       order by a.nombre , b.nombre
      
        
   } // Fin listar registros      


   // Datos de la nomina retro activos ********************************************************************************************
   public function listinretroAction()
   {     
      $form  = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id4")->setAttribute("value",$id); // Id items de nomina por empleado
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $u=new Gnominag($this->dbAdapter);
      $n=new NominaFunc($this->dbAdapter);      
      // --      
      if($this->getRequest()->isPost()) // Si es por busqueda
      {
          $request = $this->getRequest();
          if ($request->isPost()) {
             $data = $this->request->getPost();        
             $datos = $d->getGeneral1("select a.id, a.idEmp  
                                          from n_nomina_e a
                                           inner join a_empleados b on b.id = a.idEmp
                                           where b.CedEmp='".$data->cedula."' and a.idNom=".$data->id);              
             $id = $datos['id'];
             return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'in/'.$id);
           }
      }      

      // Conceptos
      $arreglo='';
      $datos = $d->getConnom(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' ('.$dat['tipVal'].')';
         $arreglo[$idc]= $nom;
      }              
      $form->get("idConc")->setValueOptions($arreglo);                                                 
      // 
      $datos = $d->getGeneral1("Select idInom from n_nomina_e_d where idInom=".$id); 
      $idn   = $datos['idInom'];
      // BUscar datos del empleado y de la cabecera de la nomina
      $datos = $d->getGeneral1("Select a.idEmp, a.idNom, b.fechaI, b.fechaF, b.idTnom, b.idGrupo, b.idCal 
                                  from n_nomina_e a 
                                  inner join n_nomina b on b.id = a.idNom
                                  where a.id=".$id); 
      $ide    = $datos['idEmp'];
      $idnp   = $datos['idNom'];
      $fechaI = $datos['fechaI'];      
      $fechaF = $datos['fechaF'];      
      $idCal  = $datos['idCal'];      
      $idGrupo = $datos['idGrupo'];      

      // Buscar constantes de funciones
      $valores=array
      (
        "titulo"    =>  "Novedades",
        "form"      =>  $form,
        "ttablas"   =>  'CONCEPTOS, HORAS ,DEVENGADOS, DEDUCIDOS, CENTROS DE COSTOS,  ELIMINAR ',  
        "datos"     =>  $d->getGeneral("select a.id ,a.nombre,a.apellido,a.idCcos,b.id as idInom,b.idNom, b.dias, c.idTnomL    
                             from a_empleados a
                             inner join n_nomina_e b on a.id=b.idEmp 
                             inner join n_nomina c on c.id = b.idNom 
                             where a.id=".$ide." and b.idNom=".$idnp),            
        'url'       => $this->getRequest()->getBaseUrl(),  
        "datccos"   =>  $d->getCencos(),
        "lin"       =>  $this->lin.'i',
        "idp"       =>  '' // Adicional para retorno 
      );                
      return new ViewModel($valores);
        
   } // Fin listar registros      
}
