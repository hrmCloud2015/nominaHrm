<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Cesantias extends TableGateway
{
    private $id;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_cesantias', $adapter, $databaseSchema,$selectResultPrototype);
    }

    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($ide, $idConC, $idConI, $fechaI, $fechaF , $dias, $sueldo, $promedio, $valor, $interes, $idInom, $idNom )
    {
       $datos=array
       (
           'idNom'  => $idNom,
           'idInom' => $idInom,
           'idEmp'  => $ide,               
           'idConC' => $idConC, // Cesantias                         
           'idConI' => $idConI, // Interese de cesantias                         
           'fechaI' => $fechaI,
           'fechaF' => $fechaF,           
           'dias'     => $dias,
           'sueldo'  => $sueldo,
           'prom'    => $promedio,           
           'valor'    => $valor,
           'interes'  => $interes,                      
        );
        $this->insert($datos);
        $inserted_id = $this->lastInsertValue;  
        return $inserted_id;                    
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
       $this->delete(array('idNom' => $id));               
     }
}
?>
