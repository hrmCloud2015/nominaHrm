<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 

class Aumentosueldo extends TableGateway
{
    private $id;
    private $idemp;
    private $comen;
    private $fechaini;
    private $estado;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_aumento_sueldo', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->idemp   = $datos["idEmp"];   
        $this->comen   = $datos["comen"];  
        $this->fechaini  = $datos["fechaIni"];  
        $this->estado   = $datos["estado"];  
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(), $sueldo)
    {
       self::cargaAtributos($data);
       $id = $this->id;
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();
       // ---        
       $fecha = '';
       if ($this->estado==1)
       {       
           $fecha = $dt['fecSis'];
       } 
       $datos=array
       (
           'idEmp'     => $this->idemp,
           'comen'     => $this->comen,    
           'fecApr'    => $fecha,
           'fecDoc'    => $dt['fecSis'],
           'fechai'    => $this->fechaini, 
           'idSal'     => $data['idSalario'],
           'sueldoNue' => $sueldo,
           'estado'    => $data['estado'],   
       );           
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
