<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 

class Presta extends TableGateway
{
    private $id;
    private $idemp;
    private $comen;
    private $valor;
    private $idTpres;
    private $cuotas;
    private $valCuotas;
    private $estado;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_prestamos', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->idemp   = $datos["idEmp"];   
        $this->idTpres = $datos["idTpres"];  
        $this->comen   = $datos["comenN"];  
        $this->estado  = $datos["estado"];  
    }
    
        //$this->valor   = str_replace( array(",",".") , "", $datos["numero"] );     
       // $this->valCuotas = str_replace( array(",",".") , "", $datos["vcuotas"] );              
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(),$idCar, $idCcos, $idTnom)
    {
       self::cargaAtributos($data);
       $id = $this->id;
       
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();
       // ---         
       $fecApro = '';
       if ($this->estado==1)
       {
           $fecApro = $dt['fecSis'];
       }
       $datos=array
       (           
           'idEmp'     => $this->idemp,
           'docRef'    => $data['nombre'],
           'idTpres'   => $this->idTpres,
           'idCar'     => $idCar,
           'idCcos'    => $idCcos,
           'comen'     => $this->comen,    
           'fecDoc'    => $data['fecDoc'],
           'idUsu'     => $dt['idUsu'],
           'fecApr'    => $fecApro,             
           'estado'    => $this->estado, 
       );    
              
//           'valor'     => str_replace( array(",",".") , "",$this->valor), 
//           'cuotas'    => str_replace( array(",",".") , "",$this->cuotas),   
//            'valCuota'  => $this->valCuotas,        
       
       if ($id==0) // Nuevo registro
       {
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;           
       }
       else // Mdificar registro
       {
          $this->update($datos, array('id' => $id));
          return $id;           
       }
    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }    
     
}
?>
