<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class PrimasA extends TableGateway
{
    private $id;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_pg_primas_ant', $adapter, $databaseSchema,$selectResultPrototype);
    }

    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($ide, $fechaI, $fechaF , $valor, $idInom, $idNom, $ano, $idP  )
    {
       $datos=array
       (
           'idNom'  => $idNom,
           'idInom' => $idInom,
           'idPri' => $idP,
           'idEmp'  => $ide,               
           'fechaI' => $fechaI,
           'fechaF' => $fechaF,           
           'valor'    => $valor,
           'ano'    => $ano,
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
